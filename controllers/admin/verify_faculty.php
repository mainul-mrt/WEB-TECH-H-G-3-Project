<?php
// /MVC/controllers/admin/verify_faculty.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ✅ from controllers/admin → ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('admin');

$db = getDB();

/* ---------- actions ---------- */
if (isset($_GET['approve'])) {
  $id = (int)$_GET['approve'];
  $db->prepare("UPDATE users SET verified=1, status='active' WHERE id=? AND role='faculty'")->execute([$id]);
  $_SESSION['flash'] = "Faculty approved.";
  header("Location: verify_faculty.php"); exit;
}
if (isset($_GET['reject'])) {
  $id = (int)$_GET['reject'];
  $db->prepare("UPDATE users SET verified=0, status='blocked' WHERE id=? AND role='faculty'")->execute([$id]);
  $_SESSION['flash'] = "Faculty rejected and blocked.";
  header("Location: verify_faculty.php"); exit;
}

/* ---------- load pending ---------- */
$pending = $db->query("
  SELECT id, full_name, user_id, dept, email
  FROM users
  WHERE role='faculty' AND (COALESCE(verified,0)=0) AND status<>'blocked'
  ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Verify Faculty</title>
  <!-- ✅ from controllers/admin → ../../views/public/assets/app.css -->
  <link rel="stylesheet" href="../../views/public/assets/app.css">
</head>
<body class="container">

  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h1>Verify Faculty</h1>
    <div>
      <!-- ✅ admin dashboard lives in /views/admin -->
      <a class="btn" href="../../views/admin/dashboard.php">Go to Dashboard</a>
      <!-- ✅ logout lives in /controllers/public -->
      <a class="btn danger" href="../public/logout.php">Logout</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert ok"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:980px;">
    <h2>Pending Faculty (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?>
      <p class="muted">No pending requests.</p>
    <?php else: foreach ($pending as $f): ?>
      <div class="card" style="margin:8px 0;">
        <strong><?= htmlspecialchars($f['full_name']) ?></strong>
        — <?= htmlspecialchars($f['user_id']) ?> (<?= htmlspecialchars($f['dept']) ?>)
        <div class="muted"><?= htmlspecialchars($f['email']) ?></div>
        <div style="margin-top:8px;">
          <a class="btn" href="?approve=<?= (int)$f['id'] ?>">Approve</a>
          <a class="btn danger" href="?reject=<?= (int)$f['id'] ?>" onclick="return confirm('Reject and block this faculty?')">Reject</a>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</body>
</html>
