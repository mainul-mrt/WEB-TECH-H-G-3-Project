<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

define('DB_HOST', 'localhost');
define('DB_NAME', 'WEB_PROJECT');
define('DB_USER', 'root');   
define('DB_PASS', '');       


function base_path(string $p=''): string {
    return __DIR__ . '/../' . ltrim($p, '/');
}
