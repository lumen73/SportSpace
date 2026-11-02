<?php
/** Connexion PDO centralisée et configuration de session. */

require_once __DIR__ . '/env.php';

$appEnv = env('APP_ENV', 'production');
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
$timezone = env('APP_TIMEZONE', 'UTC');
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$secureCookie = filter_var(
    env('SESSION_COOKIE_SECURE', $appEnv === 'production' ? 'true' : ($isHttps ? 'true' : 'false')),
    FILTER_VALIDATE_BOOLEAN
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', $secureCookie ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Ces en-tetes sont aussi emis par PHP pour les installations Apache ou
// mod_headers n'est pas active ; .htaccess les complete pour les fichiers statiques.
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com");
}

$dbHost = env('DB_HOST');
$dbPort = env('DB_PORT', '3306');
$dbName = env('DB_NAME');
$dbUser = env('DB_USER');
$dbPass = env('DB_PASS', '');

if (!$dbHost || !$dbName || !$dbUser) {
    error_log('Configuration SportSpace incomplète : variables DB manquantes.');
    http_response_code(500);
    exit('Configuration serveur incomplète.');
}

try {
    $bd = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Erreur PDO SportSpace : ' . $e->getMessage());
    http_response_code(503);
    exit('Service temporairement indisponible.');
}
