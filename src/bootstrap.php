<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
if (!defined('DB_PATH')) {
    define('DB_PATH', getenv('CHECKPRICE_DB') ?: ROOT . '/data/checkprice.sqlite');
}

$GLOBALS['config'] = require __DIR__ . '/config.php';
date_default_timezone_set('UTC');

foreach (['Database', 'Catalog', 'Stats', 'Users', 'Prices', 'Photo', 'Card', 'Mailer', 'helpers'] as $f) {
    require __DIR__ . "/$f.php";
}

if (PHP_SAPI !== 'cli') {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}
