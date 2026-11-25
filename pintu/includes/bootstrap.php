<?php
declare(strict_types=1);

// Config
require_once __DIR__ . '/../config/constants.php';
$GLOBALS['__cfg'] = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

date_default_timezone_set(cfg('timezone', 'Asia/Jakarta'));
error_reporting(E_ALL);
ini_set('display_errors', cfg('debug', false) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

/* Session hanya untuk web — skip di CLI */
$__is_cli = (php_sapi_name() === 'cli' || defined('PINTU_CLI'));

if (!$__is_cli && session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name(cfg('session_name','PINTU_SESS'));
    session_set_cookie_params([
        'lifetime'=>cfg('session_lifetime',7200),
        'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$secure,
    ]);
    session_start();
}

// Session timeout
$lifetime = cfg('session_lifetime', 7200);
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $lifetime)) {
    session_unset(); session_destroy();
    session_start();
    set_flash('warning', 'Sesi Anda telah berakhir. Silakan login kembali.');
}
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/visitor.php';
require_once __DIR__ . '/audit.php';