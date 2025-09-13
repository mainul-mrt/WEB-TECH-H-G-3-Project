<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* project paths */
if (!defined('APP_BASE')) define('APP_BASE', '/MVC');          // <- change if your folder name differs
if (!defined('ROOT'))     define('ROOT', dirname(__DIR__, 2)); // C:\xampp\htdocs\MVC

/* includes: from controllers/public -> ../../models/... */
require_once ROOT . '/models/config/config.php';
require_once ROOT . '/models/lib/db.php';

$db  = getDB();
$msg = '';

$full = $sid = $dept = $email = ''; // keep values for redisplay

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full  = trim($_POST['full_name'] ?? '');
  $sid   = trim($_POST['user_id'] ?? '');
  $dept  = trim($_POST['dept'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password']  ?? '';
  $cpass = $_POST['cpassword'] ?? '';

  // Gmail-only validation
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $email)) {
    $msg = 'Please use a valid Gmail address (must end with @gmail.com).';

  } elseif ($full && $sid && $dept && $email && $pass && $pass === $cpass) {

    // Email or ID already exists?
    $du = $db->prepare("SELECT 1 FROM users WHERE email = ? OR user_id = ? LIMIT 1");
    $du->execute([$email, $sid]);

    if ($du->fetch()) {
      $msg = 'This email or ID is already in use.';
    } else {
      try {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
          INSERT INTO users (role, status, verified, full_name, user_id, dept, email, password_hash, created_at)
          VALUES ('student','active',1,?,?,?,?,?,NOW())
        ");
        $stmt->execute([$full, $sid, $dept, $email, $hash]);

        // success -> go to login
        $_SESSION['flash'] = 'Signup success! You can now log in.';
        header('Location: ' . APP_BASE . '/views/public/index.php'); exit;

      } catch (Throwable $e) {
        $msg = 'Error: Could not create account. Please try again.';
      }
    }
  } else {
    $msg = 'Please fill all fields and make sure passwords match.';
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Student Signup</title>
  <!-- CSS lives in views/public/assets -->
  <link rel="stylesheet" href="<?= APP_BASE ?>/views/public/assets/app.css">
</head>
<body class="container">
<div class="card" style="max-width:520px;margin:60px auto;">
  <h2>Student Signup</h2>
  <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="post" novalidate>
    <label>Full name</label>
    <input name="full_name" value="<?= htmlspecialchars($full) ?>" required>

    <label>ID</label>
    <input name="user_id" value="<?= htmlspecialchars($sid) ?>" required>

    <label>Dept (CSE/BBA)</label>
    <input name="dept" value="<?= htmlspecialchars($dept) ?>" required>

    <label>Email (Gmail only)</label>
    <input type="email" name="email"
           value="<?= htmlspecialchars($email) ?>"
           required
           pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
           title="Only Gmail addresses (@gmail.com) are allowed">

    <label>Password</label>
    <input type="password" name="password" required>

    <label>Confirm Password</label>
    <input type="password" name="cpassword" required>

    <div style="margin-top:10px">
      <button class="btn">Create</button>
      <!-- Back to login page in views/public -->
      <a class="btn secondary" href="<?= APP_BASE ?>/views/public/index.php">Back</a>
    </div>
  </form>
</div>
</body>
</html>
