<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$err = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>A SIMPLE QUIZ — Login</title>
  <link rel="stylesheet" href="assets/app.css" />
  <style>
  
    body.container{
      min-height: 100vh;
      display: grid;
      grid-template-rows: auto 1fr auto;
      gap: 14px;
    
      background:
        radial-gradient(1000px 500px at -10% -20%, #e0f2fe 0, transparent 55%),
        radial-gradient(900px 480px at 110% 110%, #eef2ff 0, transparent 60%),
        var(--bg, #f6f7fb);
    }
    .brand{
      display:flex; align-items:center; justify-content:center; gap:12px;
      margin-top: 28px;
    }
    .brand .logo{
      width:42px; height:42px; border-radius:10px;
      background: linear-gradient(135deg,#2563eb, #22c55e);
      box-shadow: 0 8px 20px rgba(37,99,235,.25);
    }
    .brand h1{
      margin:0; font-size:28px; font-weight:900; letter-spacing:.5px;
      color:#0f172a;
    }
    .auth-wrap{
      display: grid; place-items: center; padding: 8px 12px 40px;
    }
    .auth-card{
      width: min(520px, 92vw);
      background:#fff; border:1px solid #e5e7eb; border-radius:16px;
      box-shadow: 0 12px 36px rgba(2,6,23,.08), 0 2px 8px rgba(2,6,23,.06);
      padding: 22px 22px 18px;
    }
    .auth-card h2{ margin: 0 0 12px; font-weight:800; }
    .sub{ color:#6b7280; font-size:13px; margin-top:-4px; margin-bottom:14px; }
    .auth-card label{ display:block; color:#6b7280; font-size:13px; margin:8px 0 6px; }
    .auth-card input{
      width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; outline:none;
      transition: box-shadow .15s, border-color .15s;
      background:#fff; font-size:15px;
    }
    .auth-card input:focus{ border-color:#2563eb; box-shadow:0 0 0 4px rgba(147,197,253,.35); }
    .auth-actions{ margin-top:14px; display:flex; gap:10px; align-items:center; }
    .foot-note{
      text-align:center; color:#94a3b8; font-size:12.5px; padding-bottom:16px;
    }
  </style>
</head>
<body class="container">

  
  <header class="brand">
    <h1>A SIMPLE QUIZ</h1>
  </header>

  
  <main class="auth-wrap">
    <div class="auth-card">
      <h2>Sign in</h2>
      <div class="sub">Welcome back! Please use your registered email and password.</div>

      <?php if ($err): ?>
        <div class="alert" role="alert"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="post" action="/MVC/controllers/public/process_login.php" autocomplete="on">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" required autofocus />

        <label for="pwd">Password</label>
        <input id="pwd" type="password" name="password" required />

        <div class="auth-actions">
          <button class="btn" type="submit">Login</button>
          <a class="btn secondary" href="/MVC/controllers/public/signup.php">Signup</a>
          <a href="forgot.php" class="muted">Forgot password?</a>
        </div>
      </form>
    </div>
  </main>

  <footer class="foot-note">
    © <?= date('Y') ?> A Simple Quiz — All rights reserved.
  </footer>
</body>
</html>
