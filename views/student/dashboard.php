<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// from views/student → ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('student');

$db   = getDB();
$uid  = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Student';
$dept = $_SESSION['dept'] ?? '';

// profile picture
$picStmt = $db->prepare("SELECT profile_pic FROM users WHERE id=?");
$picStmt->execute([$uid]);
$pic = $picStmt->fetchColumn();
$avatar = $pic ? "../public/uploads/" . basename($pic) : "../public/assets/default.png";

// attempts
$attempts = $db->prepare("
  SELECT a.quiz_id, a.grade, a.graded, a.created_at, q.title
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  WHERE a.student_id = ?
  ORDER BY a.created_at DESC
");
$attempts->execute([$uid]);
$attempts = $attempts->fetchAll();

$attemptedIds = array_map(fn($r) => (int)$r['quiz_id'], $attempts);

// max marks by quiz
$maxByQuiz = [];
if ($attemptedIds) {
  $in = implode(',', array_fill(0, count($attemptedIds), '?'));
  $mm = $db->prepare("SELECT quiz_id, SUM(marks) total FROM quiz_questions WHERE quiz_id IN ($in) GROUP BY quiz_id");
  $mm->execute($attemptedIds);
  foreach ($mm->fetchAll() as $m) {
    $maxByQuiz[(int)$m['quiz_id']] = (int)$m['total'];
  }
}

// unattempted quizzes for dept
if ($attemptedIds) {
  $in = implode(',', array_fill(0, count($attemptedIds), '?'));
  $params = array_merge([$dept], $attemptedIds);
  $sql = "SELECT q.id, q.title, q.duration_minutes, c.title AS course
          FROM quizzes q JOIN courses c ON c.id=q.course_id
          WHERE q.dept_code=? AND q.id NOT IN ($in)
          ORDER BY q.created_at DESC";
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
} else {
  $stmt = $db->prepare("SELECT q.id, q.title, q.duration_minutes, c.title AS course
                        FROM quizzes q JOIN courses c ON c.id=q.course_id
                        WHERE q.dept_code=? ORDER BY q.created_at DESC");
  $stmt->execute([$dept]);
}
$unattempted = $stmt->fetchAll();

// stats
function pct($n,$d){ return $d>0 ? round(($n/$d)*100) : 0; }
$totalAttempted = count($attempts);
$graded = []; $best = 0;
foreach ($attempts as $a) {
  if ((int)$a['graded'] === 1) {
    $max = $maxByQuiz[(int)$a['quiz_id']] ?? 0;
    if ($max > 0) {
      $p = pct((int)$a['grade'], $max);
      $graded[] = $p;
      if ($p > $best) $best = $p;
    }
  }
}
$avg = $graded ? round(array_sum($graded)/count($graded)) : 0;
$pending = $totalAttempted - count($graded);

function ringClass($p){ if($p<=25)return 'ring-red'; if($p<=50)return 'ring-orange'; if($p<=75)return 'ring-blue'; return 'ring-green'; }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Student Dashboard</title>
  <!-- from views/student → ../public/assets/... -->
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
    .quiz{padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f9fafb;margin-bottom:10px;}
    .quiz p{margin:6px 0 0;color:#64748b;font-size:14px;}
    .ring{position:relative;width:86px;height:86px;border-radius:50%;background:conic-gradient(var(--ring-color) calc(var(--p)*1%), #e5e7eb 0);}
    .ring::after{content:"";position:absolute;inset:8px;background:#fff;border-radius:50%;}
    .ring .val{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;}
    .ring-red{--ring-color:#ef4444}.ring-orange{--ring-color:#f59e0b}.ring-blue{--ring-color:#3b82f6}.ring-green{--ring-color:#22c55e}
  </style>
</head>
<body>
<div class="shell">

  <div class="glass hero">
    <img src="<?= htmlspecialchars($avatar) ?>" class="avatar" alt="Profile picture">
    <div style="flex:1">
      <h2>Welcome, <?= htmlspecialchars($name) ?></h2>
      <div class="sub">Department: <strong><?= htmlspecialchars($dept) ?></strong></div>
      <div class="hero-actions">
        <a class="btn" href="results.php">My Results</a>
        <!-- from views/student → ../../controllers/public/... -->
        <a class="btn secondary" href="../../controllers/public/profile.php">Edit Profile</a>
        <a class="btn danger" href="../../controllers/public/logout.php">Logout</a>
      </div>
    </div>

    <div class="stat" style="border:none;background:transparent;display:block;padding:0;">
      <div class="ring <?= ringClass($avg) ?>" style="--p:<?= $avg ?>;">
        <div class="val"><?= (int)$avg ?>%</div>
      </div>
      <div class="label" style="margin-top:6px;text-align:center">Avg. Score</div>
    </div>
  </div>

  <div class="grid" style="margin-top:16px">

    <div class="glass panel">
      <div class="stats-stacked">
        <div class="stat"><div class="label">Attempted</div><div class="num"><?= (int)$totalAttempted ?></div></div>
        <div class="stat"><div class="label">Avg %</div><div class="num"><?= (int)$avg ?>%</div></div>
        <div class="stat"><div class="label">Best %</div><div class="num"><?= (int)$best ?>%</div></div>
        <div class="stat"><div class="label">Pending Grades</div><div class="num"><?= (int)$pending ?></div></div>
      </div>
    </div>

    <div class="glass panel">
      <h3> Available Quizzes </h3>
      <?php if (!$unattempted): ?>
        <p class="sub">No quizzes left unattempted for your department</p>
      <?php else: ?>
        <?php foreach ($unattempted as $q): ?>
          <div class="quiz">
            <strong><?= htmlspecialchars($q['title']) ?></strong>
            <p>Course: <?= htmlspecialchars($q['course']) ?> • Duration: <?= (int)$q['duration_minutes'] ?> min</p>
            <div style="margin-top:8px">
              <a class="btn" href="take_quiz.php?id=<?= (int)$q['id'] ?>">Start</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h3 style="margin-top:16px"> Tips</h3>
      <ul class="sub" style="margin-top:6px;">
        <li>Check your internet and set aside quiet time before starting a quiz.</li>
        <li>Once submitted, you can’t retake the same quiz.</li>
      </ul>
    </div>
  </div>

</div>
</body>
</html>
