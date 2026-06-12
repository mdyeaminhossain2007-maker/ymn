<?php
/**
 * SmartTV CMS - Installation Wizard
 *
 * Self-contained, dependency-free installer. Walks the user through:
 *   1. Requirements check
 *   2. Database configuration (with live connection test)
 *   3. Administrator account
 *   4. Installation (schema, config, seed data, lock)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

define('INSTALL_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_DIR', ROOT_DIR . '/config');
define('LOCK_FILE', CONFIG_DIR . '/install.lock');
define('CONFIG_FILE', CONFIG_DIR . '/config.php');

// Already installed? Block re-installation unless lock is manually removed.
if (is_file(LOCK_FILE) && is_file(CONFIG_FILE)) {
    http_response_code(403);
    render_locked();
    exit;
}

$step   = (int) ($_GET['step'] ?? $_POST['step'] ?? 1);
$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$errors = [];

/* ----------------------------------------------------------------------------
 * AJAX: test database connection
 * ------------------------------------------------------------------------- */
if ($action === 'test_db') {
    header('Content-Type: application/json');
    $res = test_database($_POST);
    echo json_encode($res);
    exit;
}

/* ----------------------------------------------------------------------------
 * Handle step submissions
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $db = [
            'host' => trim($_POST['db_host'] ?? 'localhost'),
            'port' => trim($_POST['db_port'] ?? '3306'),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => $_POST['db_pass'] ?? '',
        ];
        $test = test_database([
            'db_host' => $db['host'], 'db_port' => $db['port'],
            'db_name' => $db['name'], 'db_user' => $db['user'], 'db_pass' => $db['pass'],
        ]);
        if (!$test['ok']) {
            $errors[] = $test['message'];
        } else {
            $_SESSION['install_db'] = $db;
            header('Location: ?step=3');
            exit;
        }
    } elseif ($step === 3) {
        $admin = [
            'name'     => trim($_POST['admin_name'] ?? ''),
            'email'    => trim($_POST['admin_email'] ?? ''),
            'username' => trim($_POST['admin_username'] ?? ''),
            'password' => $_POST['admin_password'] ?? '',
            'site'     => trim($_POST['site_title'] ?? 'SmartTV CMS'),
        ];
        if ($admin['name'] === '')                                   $errors[] = 'Administrator name is required.';
        if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL))     $errors[] = 'A valid email is required.';
        if (strlen($admin['username']) < 3)                          $errors[] = 'Username must be at least 3 characters.';
        if (strlen($admin['password']) < 6)                          $errors[] = 'Password must be at least 6 characters.';
        if (empty($_SESSION['install_db']))                          $errors[] = 'Database configuration is missing. Go back to step 2.';
        if (!$errors) {
            $_SESSION['install_admin'] = $admin;
            header('Location: ?step=4');
            exit;
        }
    } elseif ($step === 4) {
        $result = run_installation();
        if ($result['ok']) {
            header('Location: ?step=5');
            exit;
        }
        $errors = $result['errors'];
    }
}

/* ----------------------------------------------------------------------------
 * Functions
 * ------------------------------------------------------------------------- */
function test_database(array $in): array
{
    $host = $in['db_host'] ?? 'localhost';
    $port = $in['db_port'] ?? '3306';
    $name = $in['db_name'] ?? '';
    $user = $in['db_user'] ?? '';
    $pass = $in['db_pass'] ?? '';

    if ($name === '' || $user === '') {
        return ['ok' => false, 'message' => 'Database name and username are required.'];
    }
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Create database if it does not exist.
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");
        return ['ok' => true, 'message' => 'Connection successful. Database is ready.'];
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
    }
}

function run_installation(): array
{
    $db    = $_SESSION['install_db'] ?? null;
    $admin = $_SESSION['install_admin'] ?? null;
    if (!$db || !$admin) {
        return ['ok' => false, 'errors' => ['Session expired. Please restart the installer.']];
    }

    $errors = [];

    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 1. Run schema.
        $schema = file_get_contents(INSTALL_DIR . '/schema.sql');
        if ($schema === false) {
            return ['ok' => false, 'errors' => ['Could not read schema.sql.']];
        }
        run_sql_script($pdo, $schema);

        // 2. Seed data (roles, admin, settings, sample categories/channels/pages).
        seed_database($pdo, $admin);

        // 3. Write config file.
        if (!write_config($db)) {
            $errors[] = 'Could not write config/config.php. Check folder permissions.';
        }

        // 4. Ensure runtime folders exist & are writable.
        foreach (['/storage/logs', '/storage/cache', '/assets/uploads/logos', '/assets/uploads/covers'] as $dir) {
            @mkdir(ROOT_DIR . $dir, 0775, true);
        }

        // 5. Lock the installer.
        @file_put_contents(LOCK_FILE, 'Installed at ' . date('c') . "\nRemove this file only to reinstall.\n");

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Clear session install data.
        unset($_SESSION['install_db'], $_SESSION['install_admin']);
        return ['ok' => true, 'errors' => []];
    } catch (PDOException $e) {
        return ['ok' => false, 'errors' => ['Installation failed: ' . $e->getMessage()]];
    }
}

function run_sql_script(PDO $pdo, string $sql): void
{
    // Split on semicolons at end of line (schema uses simple statements).
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));
    foreach ($statements as $stmt) {
        $stmt = trim(rtrim($stmt, ';'));
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

function seed_database(PDO $pdo, array $admin): void
{
    // Super-admin role with full permissions.
    $pdo->prepare('INSERT INTO roles (name, slug, permissions) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)')
        ->execute(['Super Administrator', 'super', json_encode(['*'])]);
    $pdo->prepare('INSERT INTO roles (name, slug, permissions) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE name = VALUES(name)')
        ->execute(['Editor', 'editor', json_encode(['channels', 'categories', 'pages', 'ads'])]);
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'super'")->fetchColumn();

    // Administrator account.
    $hash = password_hash($admin['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare('INSERT INTO admins (role_id, name, email, username, password, status)
                           VALUES (?, ?, ?, ?, ?, 1)
                           ON DUPLICATE KEY UPDATE password = VALUES(password), role_id = VALUES(role_id)');
    $stmt->execute([$roleId, $admin['name'], $admin['email'], $admin['username'], $hash]);

    // Default settings.
    $settings = [
        'site_title'        => $admin['site'],
        'site_tagline'      => 'Live TV, the way it should be',
        'logo'              => '',
        'favicon'           => '',
        'theme_mode'        => 'dark',
        'primary_color'     => '#e50914',
        'accent_color'      => '#0ea5e9',
        'background_color'  => '#0b0b0f',
        'glassmorphism'     => '1',
        'startup_channel'   => '1',
        'autoplay'          => '1',
        'enable_register'   => '1',
        'meta_description'  => 'Watch live TV channels online in stunning quality.',
        'footer_text'       => '© ' . date('Y') . ' ' . $admin['site'] . '. All rights reserved.',
        'analytics_code'    => '',
        'installed_version' => '1.0.0',
    ];
    $ins = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($settings as $k => $v) {
        $ins->execute([$k, $v]);
    }

    // Sample categories.
    $cats = [
        ['News', 'news', 'bi-newspaper', 1],
        ['Sports', 'sports', 'bi-trophy', 2],
        ['Entertainment', 'entertainment', 'bi-film', 3],
        ['Movies', 'movies', 'bi-camera-reels', 4],
        ['Music', 'music', 'bi-music-note-beamed', 5],
        ['Kids', 'kids', 'bi-balloon', 6],
    ];
    $catStmt = $pdo->prepare('INSERT INTO categories (name, slug, icon, sort_order, status) VALUES (?, ?, ?, ?, 1)');
    $catIds = [];
    foreach ($cats as $c) {
        if (!$pdo->query("SELECT id FROM categories WHERE slug = " . $pdo->quote($c[1]))->fetchColumn()) {
            $catStmt->execute($c);
        }
        $catIds[$c[1]] = (int) $pdo->query("SELECT id FROM categories WHERE slug = " . $pdo->quote($c[1]))->fetchColumn();
    }

    // Sample channels using well-known public HLS test streams so the player
    // works out-of-the-box. The administrator can edit or delete these.
    $existing = (int) $pdo->query('SELECT COUNT(*) FROM channels')->fetchColumn();
    if ($existing === 0) {
        $channels = [
            [1, 'Big Buck Bunny TV', 'movies', 'US', 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', 'FHD', 1],
            [2, 'Apple Basic Stream', 'entertainment', 'US', 'https://devstreaming-cdn.apple.com/videos/streaming/examples/img_bipbop_adv_example_ts/master.m3u8', 'HD', 1],
            [3, 'Sintel Cinema', 'movies', 'NL', 'https://bitdash-a.akamaihd.net/content/sintel/hls/playlist.m3u8', 'FHD', 0],
            [4, 'Tears of Steel', 'movies', 'NL', 'https://test-streams.mux.dev/tos_ismc/main.m3u8', 'HD', 0],
            [5, 'Akamai Live Demo', 'news', 'US', 'https://cph-p2p-msl.akamaized.net/hls/live/2000341/test/master.m3u8', 'HD', 1],
        ];
        $chStmt = $pdo->prepare(
            'INSERT INTO channels (number, name, slug, category_id, country, logo, description, stream_url, backup_streams, quality, is_featured, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        foreach ($channels as $i => $ch) {
            $chStmt->execute([
                $ch[0], $ch[1], strtolower(preg_replace('/[^a-z0-9]+/i', '-', $ch[1])),
                $catIds[$ch[2]] ?? null, $ch[3], '',
                'Sample channel installed with SmartTV CMS. Edit or remove from the admin panel.',
                $ch[4], json_encode([]), $ch[5], $ch[6], $i,
            ]);
        }
    }

    // Default pages.
    $pages = [
        ['About', 'about', '<h2>About Us</h2><p>Welcome to our SmartTV platform.</p>'],
        ['Privacy Policy', 'privacy', '<h2>Privacy Policy</h2><p>Your privacy matters to us.</p>'],
        ['Contact', 'contact', '<h2>Contact</h2><p>Reach us anytime.</p>'],
    ];
    $pgStmt = $pdo->prepare('INSERT INTO pages (title, slug, content, status) VALUES (?, ?, ?, 1)
                             ON DUPLICATE KEY UPDATE title = VALUES(title)');
    foreach ($pages as $p) {
        $pgStmt->execute($p);
    }

    // Default menus.
    $menus = [
        ['Home', '/', 'header', 1],
        ['Live TV', '/watch', 'header', 2],
        ['About', '/page/about', 'header', 3],
        ['Privacy', '/page/privacy', 'footer', 1],
        ['Contact', '/page/contact', 'footer', 2],
    ];
    $mnStmt = $pdo->prepare('INSERT INTO menus (label, url, location, sort_order, status) VALUES (?, ?, ?, ?, 1)');
    if ((int) $pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn() === 0) {
        foreach ($menus as $m) {
            $mnStmt->execute($m);
        }
    }
}

function write_config(array $db): bool
{
    $template = file_get_contents(CONFIG_DIR . '/config.sample.php');
    if ($template === false) {
        return false;
    }
    $key    = bin2hex(random_bytes(24));
    $secret = bin2hex(random_bytes(24));
    $replacements = [
        '{{DB_HOST}}'       => $db['host'],
        '{{DB_PORT}}'       => $db['port'],
        '{{DB_NAME}}'       => $db['name'],
        '{{DB_USER}}'       => $db['user'],
        '{{DB_PASS}}'       => addslashes($db['pass']),
        '{{BASE_URL}}'      => '',
        '{{APP_KEY}}'       => $key,
        '{{STREAM_SECRET}}' => $secret,
    ];
    $config = strtr($template, $replacements);
    return @file_put_contents(CONFIG_FILE, $config) !== false;
}

/* ----------------------------------------------------------------------------
 * Requirements
 * ------------------------------------------------------------------------- */
function check_requirements(): array
{
    $checks = [];
    $checks[] = [
        'label' => 'PHP version >= 8.0 (current ' . PHP_VERSION . ')',
        'ok'    => version_compare(PHP_VERSION, '8.0.0', '>='),
        'fatal' => true,
    ];
    foreach (['pdo_mysql', 'mbstring', 'json', 'openssl', 'curl', 'gd'] as $ext) {
        $checks[] = [
            'label' => "PHP extension: {$ext}",
            'ok'    => extension_loaded($ext),
            'fatal' => in_array($ext, ['pdo_mysql', 'mbstring', 'json', 'openssl'], true),
        ];
    }
    foreach (['config', 'storage', 'assets/uploads'] as $dir) {
        $path = ROOT_DIR . '/' . $dir;
        @mkdir($path, 0775, true);
        $checks[] = [
            'label' => "Writable: /{$dir}",
            'ok'    => is_writable($path),
            'fatal' => true,
        ];
    }
    return $checks;
}

function render_locked(): void
{
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Already Installed</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<style>body{font-family:system-ui;background:#0b0b0f;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}'
        . '.box{max-width:520px;padding:40px;background:#16161d;border-radius:16px;border:1px solid #26262f}'
        . 'h1{color:#e50914} a{color:#0ea5e9}</style></head><body><div class="box">'
        . '<h1>SmartTV CMS is already installed</h1>'
        . '<p>The installer is locked to prevent accidental data loss.</p>'
        . '<p>To reinstall, manually delete <code>config/install.lock</code> and <code>config/config.php</code> on your server.</p>'
        . '<p><a href="../">Go to the website &rarr;</a> &nbsp; <a href="../admin/login">Admin Login &rarr;</a></p>'
        . '</div></body></html>';
}

$requirements = check_requirements();
$canProceed   = !array_filter($requirements, static fn ($c) => $c['fatal'] && !$c['ok']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartTV CMS &mdash; Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body{background:radial-gradient(1200px 600px at 50% -10%,#1a1326,#0b0b0f);color:#e9e9ef;font-family:system-ui,Segoe UI,Roboto,sans-serif;min-height:100vh}
        .wrap{max-width:720px;margin:40px auto;padding:0 16px}
        .card-stv{background:rgba(22,22,29,.85);backdrop-filter:blur(12px);border:1px solid #2a2a35;border-radius:18px}
        .brand{font-weight:800;letter-spacing:.5px}
        .brand span{color:#e50914}
        .steps{display:flex;gap:8px;margin:24px 0}
        .steps .s{flex:1;height:6px;border-radius:6px;background:#2a2a35}
        .steps .s.active{background:linear-gradient(90deg,#e50914,#0ea5e9)}
        .form-control,.form-select{background:#101017;border:1px solid #2a2a35;color:#fff}
        .form-control:focus{background:#101017;color:#fff;border-color:#0ea5e9;box-shadow:none}
        .btn-stv{background:linear-gradient(90deg,#e50914,#b00710);border:0;color:#fff;font-weight:600}
        .req-ok{color:#22c55e}.req-bad{color:#ef4444}.req-warn{color:#f59e0b}
        code{color:#0ea5e9}
        a{color:#0ea5e9}
    </style>
</head>
<body>
<div class="wrap">
    <div class="text-center mb-3">
        <h1 class="brand">Smart<span>TV</span> CMS</h1>
        <p class="text-secondary mb-0">Production-ready Live TV platform &mdash; Installation Wizard</p>
    </div>

    <div class="steps">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="s <?= $step >= $i ? 'active' : '' ?>"></div>
        <?php endfor; ?>
    </div>

    <div class="card-stv p-4 p-md-5">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $err) echo '<div>' . htmlspecialchars($err) . '</div>'; ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h3 class="mb-3"><i class="bi bi-1-circle"></i> Server Requirements</h3>
            <ul class="list-group list-group-flush mb-4">
                <?php foreach ($requirements as $c): ?>
                    <li class="list-group-item bg-transparent text-light d-flex justify-content-between border-secondary">
                        <span><?= htmlspecialchars($c['label']) ?></span>
                        <?php if ($c['ok']): ?>
                            <span class="req-ok"><i class="bi bi-check-circle-fill"></i> OK</span>
                        <?php elseif ($c['fatal']): ?>
                            <span class="req-bad"><i class="bi bi-x-circle-fill"></i> Required</span>
                        <?php else: ?>
                            <span class="req-warn"><i class="bi bi-exclamation-triangle-fill"></i> Recommended</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canProceed): ?>
                <a href="?step=2" class="btn btn-stv btn-lg w-100">Continue <i class="bi bi-arrow-right"></i></a>
            <?php else: ?>
                <div class="alert alert-warning">Please resolve the required items above, then refresh this page.</div>
                <a href="?step=1" class="btn btn-outline-light w-100">Re-check</a>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <h3 class="mb-3"><i class="bi bi-2-circle"></i> Database Configuration</h3>
            <form method="post" action="?step=2" id="dbForm">
                <input type="hidden" name="step" value="2">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Host</label>
                        <input class="form-control" name="db_host" value="<?= htmlspecialchars($_SESSION['install_db']['host'] ?? 'localhost') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Port</label>
                        <input class="form-control" name="db_port" value="<?= htmlspecialchars($_SESSION['install_db']['port'] ?? '3306') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Database Name</label>
                        <input class="form-control" name="db_name" value="<?= htmlspecialchars($_SESSION['install_db']['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="db_user" value="<?= htmlspecialchars($_SESSION['install_db']['user'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="db_pass">
                    </div>
                </div>
                <div id="dbResult" class="mt-3"></div>
                <div class="d-flex gap-2 mt-4">
                    <button type="button" class="btn btn-outline-light" id="testBtn"><i class="bi bi-plug"></i> Test Connection</button>
                    <button type="submit" class="btn btn-stv flex-grow-1">Save &amp; Continue <i class="bi bi-arrow-right"></i></button>
                </div>
            </form>
            <script>
            document.getElementById('testBtn').addEventListener('click', function () {
                const fd = new FormData(document.getElementById('dbForm'));
                fd.append('action', 'test_db');
                const box = document.getElementById('dbResult');
                box.innerHTML = '<div class="text-info">Testing…</div>';
                fetch('?action=test_db', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        box.innerHTML = '<div class="alert ' + (d.ok ? 'alert-success' : 'alert-danger') + '">' + d.message + '</div>';
                    })
                    .catch(() => box.innerHTML = '<div class="alert alert-danger">Request failed.</div>');
            });
            </script>

        <?php elseif ($step === 3): ?>
            <h3 class="mb-3"><i class="bi bi-3-circle"></i> Administrator Account</h3>
            <form method="post" action="?step=3">
                <input type="hidden" name="step" value="3">
                <div class="mb-3">
                    <label class="form-label">Site Title</label>
                    <input class="form-control" name="site_title" value="<?= htmlspecialchars($_SESSION['install_admin']['site'] ?? 'SmartTV CMS') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input class="form-control" name="admin_name" value="<?= htmlspecialchars($_SESSION['install_admin']['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="admin_email" value="<?= htmlspecialchars($_SESSION['install_admin']['email'] ?? '') ?>" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input class="form-control" name="admin_username" value="<?= htmlspecialchars($_SESSION['install_admin']['username'] ?? 'admin') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="admin_password" required minlength="6">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <a href="?step=2" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Back</a>
                    <button type="submit" class="btn btn-stv flex-grow-1">Continue <i class="bi bi-arrow-right"></i></button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <h3 class="mb-3"><i class="bi bi-4-circle"></i> Ready to Install</h3>
            <p class="text-secondary">The wizard will now create the database tables, configuration files, your administrator account and sample content.</p>
            <ul class="text-secondary">
                <li>Database: <code><?= htmlspecialchars($_SESSION['install_db']['name'] ?? '') ?></code></li>
                <li>Admin: <code><?= htmlspecialchars($_SESSION['install_admin']['username'] ?? '') ?></code></li>
            </ul>
            <form method="post" action="?step=4">
                <input type="hidden" name="step" value="4">
                <button type="submit" class="btn btn-stv btn-lg w-100"><i class="bi bi-gear-fill"></i> Run Installation</button>
            </form>

        <?php elseif ($step === 5): ?>
            <div class="text-center">
                <i class="bi bi-check-circle-fill req-ok" style="font-size:64px"></i>
                <h3 class="mt-3">Installation Complete!</h3>
                <p class="text-secondary">SmartTV CMS has been installed successfully. For security, the installer is now locked.</p>
                <div class="alert alert-warning text-start">
                    <strong>Recommended:</strong> delete the <code>/install</code> folder from your server.
                </div>
                <div class="d-grid gap-2">
                    <a href="../" class="btn btn-stv btn-lg"><i class="bi bi-tv"></i> Go to Website</a>
                    <a href="../admin/login" class="btn btn-outline-light"><i class="bi bi-speedometer2"></i> Admin Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <p class="text-center text-secondary mt-4 small">SmartTV CMS v1.0.0 &mdash; PHP <?= PHP_VERSION ?></p>
</div>
</body>
</html>
