<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Set your project base (change '/MVC' if your folder name is different)
if (!defined('APP_BASE')) define('APP_BASE', '/MVC');

// Optional: if you keep APP_BASE in config.php, include it instead of the line above:
// require_once __DIR__ . '/../../models/config/config.php';

// Clear session data
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Absolute redirect to the public login page
header('Location: ' . APP_BASE . '/views/public/index.php');
exit;
