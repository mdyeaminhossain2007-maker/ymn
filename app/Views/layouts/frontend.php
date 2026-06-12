<?php
use App\Models\Setting;
use App\Models\Menu;
use App\Models\Ad;

$settings = $_settings ?? Setting::all();
$siteTitle = $settings['site_title'] ?? 'SmartTV CMS';
$primary = $settings['primary_color'] ?? '#e50914';
$accent  = $settings['accent_color'] ?? '#0ea5e9';
$bg      = $settings['background_color'] ?? '#0b0b0f';
$user    = $_user ?? null;
$headerMenu = (new Menu())->byLocation('header');
$footerMenu = (new Menu())->byLocation('footer');
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Home') . ' — ' . $siteTitle) ?></title>
    <meta name="description" content="<?= e($settings['meta_description'] ?? '') ?>">
    <?php if (!empty($settings['favicon'])): ?><link rel="icon" href="<?= e($settings['favicon']) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <style>:root{--stv-primary:<?= e($primary) ?>;--stv-accent:<?= e($accent) ?>;--stv-bg:<?= e($bg) ?>;}</style>
    <meta name="csrf-token" content="<?= e($_csrf ?? '') ?>">
</head>
<body class="stv-body">
<nav class="navbar navbar-expand-lg stv-nav sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= base_url('/') ?>">
            <?php if (!empty($settings['logo'])): ?>
                <img src="<?= e($settings['logo']) ?>" alt="<?= e($siteTitle) ?>" height="32">
            <?php else: ?>
                <span class="stv-logo">Smart<span style="color:var(--stv-primary)">TV</span></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($headerMenu as $m): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(strpos($m['url'], 'http') === 0 ? $m['url'] : base_url($m['url'])) ?>"><?= e($m['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <form class="d-flex me-3" action="<?= base_url('search') ?>" method="get" role="search">
                <input class="form-control form-control-sm stv-search" type="search" name="q" placeholder="Search channels…" value="<?= e($term ?? '') ?>">
            </form>
            <?php if ($user): ?>
                <div class="dropdown">
                    <a class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" href="#">
                        <i class="bi bi-person-circle"></i> <?= e($user['name']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= base_url('account') ?>">My Account</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('account/favorites') ?>">Favorites</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('account/history') ?>">History</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('logout') ?>">Logout</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-light me-2" href="<?= base_url('login') ?>">Sign In</a>
                <a class="btn btn-sm stv-btn" href="<?= base_url('register') ?>">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?= (new Ad())->render('header') ?>

<main class="stv-main">
    <?= $content ?? '' ?>
</main>

<footer class="stv-footer">
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="text-secondary small"><?= e($settings['footer_text'] ?? '') ?></div>
            <ul class="nav">
                <?php foreach ($footerMenu as $m): ?>
                    <li class="nav-item"><a class="nav-link text-secondary small" href="<?= e(strpos($m['url'], 'http') === 0 ? $m['url'] : base_url($m['url'])) ?>"><?= e($m['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</footer>

<?= (new Ad())->render('footer') ?>
<?= (new Ad())->render('popunder') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.STV = { base: "<?= rtrim(base_url('/'), '/') ?>", csrf: "<?= e($_csrf ?? '') ?>" };</script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $settings['analytics_code'] ?? '' ?>
</body>
</html>
