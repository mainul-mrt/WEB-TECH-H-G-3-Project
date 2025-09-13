<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// define your project base once per request (change '/MVC' if your folder name differs)
define('APP_BASE', '/MVC');

if (empty($_SESSION['role'])) {
  header('Location: ' . APP_BASE . '/views/public/index.php'); 
  exit;
}

switch ($_SESSION['role']) {
  case 'student': header('Location: ' . APP_BASE . '/views/student/dashboard.php'); break;
  case 'faculty': header('Location: ' . APP_BASE . '/views/faculty/dashboard.php'); break;
  case 'admin':   header('Location: ' . APP_BASE . '/views/admin/dashboard.php');   break;
  default:        header('Location: ' . APP_BASE . '/views/public/index.php');      break;
}
exit;
