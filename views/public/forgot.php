<?php
if (session_status()===PHP_SESSION_NONE) session_start();
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Forgot Password</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body class="container">
  <div class="card" style="max-width:520px;margin:60px auto;">
    <h2>Forgot Password</h2>
    <p class="muted" style="margin-top:6px">
      Enter your <strong>Full name</strong> and <strong>User ID</strong>. We’ll send a one-time code to the email on file.
    </p>

    <?php if ($msg): ?>
      <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Send to controller -->
    <form method="post" action="../../controllers/public/send_otp.php" novalidate>
      <label>Full name</label>
      <input name="full_name" autocomplete="name" required>

      <label>User ID</label>
      <input name="user_id" autocomplete="username" required>

      <div style="margin-top:12px">
        <button class="btn">Send OTP</button>
        <a class="btn secondary" href="index.php">Back to Login</a>
      </div>
    </form>
  </div>
</body>
</html>
