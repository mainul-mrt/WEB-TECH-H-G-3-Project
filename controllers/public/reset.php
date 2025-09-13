<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../models/lib/db.php';

$db = getDB();
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);


$email = $_SESSION['reset_email'] ?? '';


$MASK_EMAIL = true ;
function mask_email(string $e): string {
  if ($e === '') return '';
  [$user, $domain] = explode('@', $e, 2) + ['', ''];
  $keep = mb_substr($user, 0, min(3, mb_strlen($user)));
  return $keep . str_repeat('*', max(0, mb_strlen($user) - mb_strlen($keep))) . '@' . $domain;
}
$shownEmail = $MASK_EMAIL ? mask_email($email) : $email;

$step = empty($_SESSION['reset_verified']) ? 'otp' : 'password';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($step === 'otp') {
    $otp = trim($_POST['otp'] ?? '');
    if (isset($_SESSION['reset_otp'], $_SESSION['reset_exp'], $_SESSION['reset_uid'])
        && time() < $_SESSION['reset_exp']
        && hash_equals($_SESSION['reset_otp'], $otp)) {
      $_SESSION['reset_verified'] = 1;
      $_SESSION['msg'] = 'OTP verified. Enter new password.';
      header("Location: reset.php"); exit;
    } else {
      $_SESSION['msg'] = 'Invalid or expired OTP.';
      header("Location: reset.php"); exit;
    }
  } elseif ($step === 'password') {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm'] ?? '';
    if ($p1 === '' || $p2 === '' || $p1 !== $p2) {
      $_SESSION['msg'] = 'Passwords do not match.';
      header("Location: reset.php"); exit;
    }
    $hash = password_hash($p1, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
       ->execute([$hash, $_SESSION['reset_uid']]);

    unset($_SESSION['reset_uid'], $_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_exp'], $_SESSION['reset_verified']);
    $_SESSION['login_error'] = 'Password updated. Please login.';
    header("Location:/MVC/views/public/index.php"); exit;
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="../../views/public/assets/app.css">
</head>
<body class="container">
  <div class="card" style="max-width:520px;margin:60px auto;">
    <h2>Reset Password</h2>

    <?php if ($msg): ?>
      <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($step === 'otp'): ?>
      <?php if ($shownEmail): ?>
        <p class="muted" style="margin-top:8px;">
          OTP sent to your email <strong><?= htmlspecialchars($shownEmail) ?></strong>.
        </p>
      <?php endif; ?>
      <form method="post" style="margin-top:12px;">
        <label>Enter OTP</label>
        <input name="otp" maxlength="6" required autocomplete="one-time-code">
        <div style="margin-top:12px">
          <button class="btn">Verify OTP</button>
          <a class="btn secondary" href="/MVC/views/public/forgot.php">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <form method="post" style="margin-top:12px;">
        <label>New Password</label>
        <input type="password" name="password" required>
        <label>Confirm Password</label>
        <input type="password" name="confirm" required>
        <div style="margin-top:12px">
          <button class="btn">Update Password</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
