<?php
require_once __DIR__ . '/../models/config/config.php';
require_once __DIR__ . '/../models/lib/db.php';

try {
  $db = getDB();
  echo "DB OK";
} catch (Throwable $e) {
  echo "DB ERROR: " . $e->getMessage();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
