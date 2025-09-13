<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// views/admin -> ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';
requireRole('admin');

$db   = getDB();
$aid  = (int)($_SESSION['user_id'] ?? 0);
$name = $_SESSION['name'] ?? 'Admin';

// profile picture (web path from /views/admin to /views/public/uploads)
$picStmt = $db->prepare("SELECT profile_pic FROM users WHERE id=?");
$picStmt->execute([$aid]);
$pic = $picStmt->fetchColumn();
$avatar = $pic ? "../public/uploads/" . basename($pic) : "../public/assets/default.png";

// quick counters
$cnt = function(string $role) use ($db): int {
  $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
  $stmt->execute([$role]);
  return (int)$stmt->fetchColumn();
};

$students = $cnt('student');
$faculty  = $cnt('faculty');
$admins   = $cnt('admin');
$quizzes  = (int)$db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();

$pendingFaculty = (int)$db->query("
  SELECT COUNT(*) FROM users
  WHERE role='faculty' AND COALESCE(verified,0)=0
")->fetchColumn();

$verify = $db->query("
  SELECT id, full_name, user_id, dept, email
  FROM users
  WHERE role='faculty' AND COALESCE(verified,0)=0
  ORDER BY created_at DESC
  LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
  <!-- views/admin -> ../public/assets/... -->
  <link rel="stylesheet" href="../public/assets/app.css">
  <style>
    body { background: linear-gradient(120deg,#eef2ff 0%,#f8fafc 40%,#ecfeff 100%) fixed; }
    .shell{max-width:1100px;margin:28px auto;padding:0 16px;}
    .glass{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);}
    .hero{display:flex;align-items:center;gap:18px;padding:20px;}
    .avatar{width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid #e2e8f0;}
    .hero h2{margin:0;font-size:26px;}
    .sub{color:#64748b;margin-top:4px;}
    .hero-actions{display:flex;gap:8px;margin-top:8px;}
    .grid{display:grid;gap:16px;grid-template-columns:1fr 1fr;}
    @media(max-width:960px){.grid{grid-template-columns:1fr;}}
    .panel{padding:16px;}
    .stats-stacked { display:grid; grid-template-columns: 1fr; gap: 12px; margin-bottom:12px; }
    .stat{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;justify-content:space-between;align-items:center;}
    .stat .label{font-size:13px;color:#64748b;}
    .stat .num{font-size:22px;font-weight:800;margin-left:12px;}
    .item{padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f9fafb;margin-bottom:10px;}
    .item p{margin:6px 0 0;color:#64748b;font-size:14px;}
  </style>
</head>
<body>
<div class="shell">

  <div class="glass hero">
    <img src="<?= htmlspecialchars($avatar) ?>" class="avatar" alt="Profile">
    <div style="flex:1">
      <h2>Welcome, <?= htmlspecialchars($name) ?></h2>
      <div class="sub">System Administrator</div>
      <div class="hero-actions">
        <!-- views/admin -> ../../controllers/admin/... -->
        <a class="btn" href="../../controllers/admin/manage_users.php">Manage Users</a>
        <a class="btn secondary" href="../../controllers/public/profile.php">Edit Profile</a>
        <a class="btn danger" href="../../controllers/public/logout.php">Logout</a>
      </div>
    </div>
  </div>

  <div class="grid" style="margin-top:16px">
    <div class="glass panel">
      <div class="stats-stacked">
        <div class="stat"><div class="label">Total Students</div><div class="num"><?= $students ?></div></div>
        <div class="stat"><div class="label">Total Faculty</div><div class="num"><?= $faculty ?></div></div>
        <div class="stat"><div class="label">Total Admins</div><div class="num"><?= $admins ?></div></div>
        <div class="stat"><div class="label">Total Quizzes</div><div class="num"><?= $quizzes ?></div></div>
      </div>

      <h3>Announcements</h3>
      <p class="sub">No new announcements at this time.</p>
    </div>

    <div class="glass panel">
      <h3>Faculty Verification (Pending: <?= $pendingFaculty ?>)</h3>
      <?php if (!$verify): ?>
        <p class="sub">No pending verifications.</p>
      <?php else: ?>
        <?php foreach ($verify as $f): ?>
          <div class="item">
            <strong><?= htmlspecialchars($f['full_name']) ?></strong> —
            <?= htmlspecialchars($f['user_id']) ?> (<?= htmlspecialchars($f['dept']) ?>)
            <p><?= htmlspecialchars($f['email']) ?></p>
            <div style="margin-top:8px">
              <a class="btn" href="../../controllers/admin/verify_faculty.php?approve=<?= (int)$f['id'] ?>">Approve</a>
              <a class="btn secondary" href="../../controllers/admin/verify_faculty.php?reject=<?= (int)$f['id'] ?>">Reject</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
