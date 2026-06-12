<?php
/**
 * SmartTV CMS configuration template.
 *
 * This file is copied to config.php by the installation wizard with the
 * values you provide. Do not edit config.php by hand unless you know what
 * you are doing.
 */

declare(strict_types=1);

// --- Database ---
define('DB_HOST', '{{DB_HOST}}');
define('DB_PORT', '{{DB_PORT}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');
define('DB_CHARSET', 'utf8mb4');

// --- Application ---
define('APP_NAME', 'SmartTV CMS');
define('APP_ENV', 'production');           // production | development
define('APP_DEBUG', false);
define('BASE_URL', '{{BASE_URL}}');         // empty string = auto-detect

// --- Security ---
define('APP_KEY', '{{APP_KEY}}');           // 32+ char random string
define('STREAM_TOKEN_TTL', 7200);           // seconds a stream token stays valid
define('STREAM_TOKEN_SECRET', '{{STREAM_SECRET}}');

// --- Sessions ---
define('SESSION_NAME', 'smarttv_session');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);
