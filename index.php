<?php
/**
 * SmartTV CMS - Front Controller
 *
 * Single entry point. Bootstraps the application, handles installation
 * redirect, and dispatches the request to the router.
 */

declare(strict_types=1);

define('SMARTTV_START', microtime(true));
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');

require APP_PATH . '/Core/helpers.php';

// PSR-4-ish autoloader for the App\ namespace.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// If the CMS is not installed yet, redirect to the installer.
$configFile = CONFIG_PATH . '/config.php';
$lockFile   = CONFIG_PATH . '/install.lock';

if (!is_file($configFile) || !is_file($lockFile)) {
    // Allow the installer itself to run.
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if (strpos($uri, '/install') === false) {
        header('Location: ' . rtrim(base_url(), '/') . '/install/');
        exit;
    }
}

require $configFile;

use App\Core\App;

$app = new App();
$app->run();
