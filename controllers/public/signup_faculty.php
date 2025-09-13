<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// controllers/public -> ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';

$db = getDB();
$msg = '';

// keep old values for redisplay
$full = $fid = $dept = $desg = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full  = trim($_POST['full_name'] ?? '');
  $fid   = trim($_POST['user_id'] ?? '');
  $dept  = trim($_POST['dept'] ?? '');
  $desg  = trim($_POST['designation'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $cpass = $_POST['cpassword'] ?? '';

  // Gmail-only
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $email)) {
    $msg = 'Please use a valid Gmail address (must end with @gmail.com).';
  } elseif ($full && $fid && $dept && $desg && $email && $pass && $pass === $cpass) {
    $du = $db->prepare("SELECT 1 FROM users WHERE email=? OR user_id=? LIMIT 1");
    $du->execute([$email, $fid]);

    if ($du->fetch()) {
      $msg = 'This email or ID is already in use.';
    } else {
      try {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $db->prepare("
          INSERT INTO users
            (role, status, verified, full_name, user_id, dept, designation, email, password_hash, created_at)
          VALUES
            ('faculty','pending',0,?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([$full, $fid, $dept, $desg, $email, $hash]);

        $msg = 'Signup submitted. Please wait for admin approval.';
        $full = $fid = $dept = $desg = $email = '';
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
  <title>Faculty Signup</title>
  <!-- controllers/public -> ../../views/public/assets/... -->
  <link rel="stylesheet" href="../../views/public/assets/app.css">
</head>
<body class="container">
<div class="card" style="max-width:520px;margin:60px auto;">
  <h2>Faculty Signup</h2>
  <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <form method="post" novalidate>
    <label>Full name</label>
    <input name="full_name" value="<?= htmlspecialchars($full) ?>" required>

    <label>ID</label>
    <input name="user_id" value="<?= htmlspecialchars($fid) ?>" required>

    <label>Dept (CSE/BBA)</label>
    <input name="dept" value="<?= htmlspecialchars($dept) ?>" required>

    <label>Designation</label>
    <input name="designation" value="<?= htmlspecialchars($desg) ?>" required>

    <label>Email (Gmail only)</label>
    <input type="email"
           name="email"
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
      <!-- go back to the login page in views -->
      <a class="btn secondary" href="../../views/public/index.php">Back</a>
    </div>

    <p class="muted" style="margin-top:8px;">
      Note: Faculty accounts are created as <strong>pending</strong>. Admin must approve them before login.
    </p>
  </form>
</div>
</body>
</html>
