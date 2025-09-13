<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

define('APP_BASE', '/MVC'); // <-- change if your folder under htdocs has a different name

// from controllers/public -> ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   header('Location: ' . APP_BASE . '/views/public/index.php');  exit;
}

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

if ($email === '' || $pass === '') {
    $_SESSION['login_error'] = "Email and password are required.";
    header('Location: ' . APP_BASE . '/views/public/index.php');  exit;
}

$stmt = $db->prepare("
  SELECT id, role, email, full_name, dept, password_hash, status, verified
  FROM users WHERE email=? LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['password_hash']) || !password_verify($pass, $user['password_hash'])) {
    $_SESSION['login_error'] = "Invalid email or password.";
    header('Location: ' . APP_BASE . '/views/public/index.php');  exit;
}


$status = $user['status'] ?? 'active';
if ($status === 'blocked') {
    $_SESSION['login_error'] = "Your account is blocked. Please contact the admin.";
    header('Location: ' . APP_BASE . '/views/public/index.php');  exit;
}
if ($status !== 'active') {
    $_SESSION['login_error'] = "Your account is deactivated. Please contact the admin.";
    header('Location: ' . APP_BASE . '/views/public/index.php');  exit;

}
if ($user['role'] === 'faculty' && (int)($user['verified'] ?? 0) !== 1) {
    $_SESSION['login_error'] = "Your faculty account is not verified yet.";
    header('Location: ' . APP_BASE . '/views/public/index.php');  exit;
}


$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role']    = $user['role'];
$_SESSION['dept']    = $user['dept'];
$_SESSION['name']    = $user['full_name'];

header('Location: ' . APP_BASE . '/controllers/public/router.php');  exit;
exit;
