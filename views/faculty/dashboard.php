<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';
requireRole('faculty');

$db   = getDB();
$me   = $_SESSION['name'] ?? 'Faculty';
$dept = $_SESSION['dept'] ?? '';       
$uid  = (int)($_SESSION['user_id'] ?? 0);


$avatar = '../views/public/assets/default.png';
try {
    $picStmt = $db->prepare("SELECT COALESCE(profile_pic,'') FROM users WHERE id=?");
    $picStmt->execute([$uid]);
    $rawPic = (string)$picStmt->fetchColumn();

    if ($rawPic !== '') {
        $p = str_replace('\\', '/', $rawPic);
        $p = ltrim($p, '/');

        if (preg_match('#^https?://#i', $p) || (isset($p[0]) && $p[0] === '/')) {
            $avatar = $p; 
        } else {
            
            if (stripos($p, 'public/') === 0)   { $p = substr($p, 7); }
            if (stripos($p, '../public/') === 0){ $p = substr($p, 10); }

            $candidateWeb = '../public/' . $p;
            $candidateFs  = __DIR__ . '/../public/' . $p;

            if (file_exists($candidateFs)) {
                $avatar = $candidateWeb;
            } else {
                
                $fallbackP  = 'uploads/' . basename($p);
                $fallbackFs = __DIR__ . '/../public/' . $fallbackP;
                if (file_exists($fallbackFs)) {
                    $avatar = '../public/' . $fallbackP;
                }
            }
        }
    }
} catch (Throwable $e) {
    
}




$stm = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE dept_code = ?");
$stm->execute([$dept]);
$totalQuizzes = (int)$stm->fetchColumn();


$stm = $db->prepare("
  SELECT COUNT(*) 
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  WHERE q.dept_code = ?
");
$stm->execute([$dept]);
$totalAttempts = (int)$stm->fetchColumn();


$stm = $db->prepare("
  SELECT COUNT(*)
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  WHERE q.dept_code = ? AND COALESCE(a.graded,0) = 0
");
$stm->execute([$dept]);
$pendingGrades = (int)$stm->fetchColumn();


$stm = $db->prepare("
  SELECT a.quiz_id, a.grade
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  WHERE q.dept_code = ?
");
$stm->execute([$dept]);
$attemptRows = $stm->fetchAll(PDO::FETCH_ASSOC);


$quizIds  = array_unique(array_map(fn ($r) => (int)$r['quiz_id'], $attemptRows));
$maxByQuiz = [];

if (!empty($quizIds)) {
    $ids = array_values($quizIds); 
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT quiz_id, SUM(marks) AS total
            FROM quiz_questions
            WHERE quiz_id IN ($in)
            GROUP BY quiz_id";
    $m = $db->prepare($sql);
    $m->execute($ids);

    foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $x) {
        $maxByQuiz[(int)$x['quiz_id']] = (int)$x['total'];
    }
}

$bestPct = 0;
foreach ($attemptRows as $r) {
    $max = $maxByQuiz[(int)$r['quiz_id']] ?? 0;
    if ($max > 0) {
        $pct = (int)round(($r['grade'] / $max) * 100);
        if ($pct > $bestPct) $bestPct = $pct;
    }
}


$toGrade = $db->prepare("
  SELECT 
    a.id AS attempt_id,
    a.quiz_id,
    a.created_at,
    u.full_name AS student_name,
    u.user_id   AS student_uid,
    u.dept      AS student_dept,
    q.title     AS quiz_title
  FROM quiz_attempts a
  JOIN quizzes q   ON q.id = a.quiz_id
  JOIN users  u    ON u.id = a.student_id
  WHERE q.dept_code = ?
    AND COALESCE(a.graded,0) = 0
  ORDER BY a.created_at DESC
  LIMIT 20
");
$toGrade->execute([$dept]);
$pendingRows = $toGrade->fetchAll(PDO::FETCH_ASSOC);


$quizStmt = $db->prepare("
  SELECT id, title, course_id, duration_minutes, created_at
  FROM quizzes
  WHERE dept_code = ?
  ORDER BY created_at DESC
  LIMIT 10
");
$quizStmt->execute([$dept]);
$deptQuizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Faculty Dashboard</title>
  <link rel="stylesheet" href="../public/assets/app.css">
  <style>
    .row { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .stats { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .stat-card { padding:16px; border-radius:12px; background:#fff; box-shadow:var(--shadow); }
    .knum { font-size: 26px; font-weight:700; margin-top:6px; }
    @media (max-width:900px){ .row{ grid-template-columns: 1fr; } .stats{ grid-template-columns:1fr; } }
  </style>
</head>
<body class="container">

  <!-- Header -->
  <div class="card" style="margin-bottom:16px;">
    <div style="display:flex; align-items:center; gap:16px;">
      <img src="<?= htmlspecialchars($avatar) ?>"
           alt="Profile Picture"
           style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #eee;">

      <div style="flex:1;">
        <h2 style="margin:0;">Welcome, <?= htmlspecialchars($me) ?></h2>
        <div class="muted">Department: <?= htmlspecialchars($dept ?: '—') ?></div>
        <div style="margin-top:12px;">
          <a class="btn" href="../../controllers/faculty/create_quiz.php">Create Quiz</a>
          <a class="btn" href="../../controllers/public/profile.php">Edit Profile</a>
          <a class="btn danger" href="../../controllers/public/logout.php">Logout</a>
        </div>
      </div>
      <div class="muted" title="Best % among attempts">
        Best %<br><span style="font-size:22px;font-weight:700;"><?= (int)$bestPct ?>%</span>
      </div>
    </div>
  </div>

  <div class="row">
    
    <div>
      <div class="card">
        <div class="stats">
          <div class="stat-card">
            <div class="muted">Total Quizzes (Dept)</div>
            <div class="knum"><?= $totalQuizzes ?></div>
          </div>
          <div class="stat-card">
            <div class="muted">Total Attempts</div>
            <div class="knum"><?= $totalAttempts ?></div>
          </div>
          <div class="stat-card">
            <div class="muted">Pending Grading</div>
            <div class="knum"><?= $pendingGrades ?></div>
          </div>
          <div class="stat-card">
            <div class="muted">Best %</div>
            <div class="knum"><?= (int)$bestPct ?>%</div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:16px;">
        <h3>Announcements</h3>
        <p class="muted">No new announcements at this time.</p>
      </div>
    </div>

    
    <div>
      <div class="card">
        <h3>To Grade (Pending)</h3>
        <?php if (!$pendingRows): ?>
          <p class="muted">Nothing pending right now </p>
        <?php else: foreach ($pendingRows as $p): ?>
          <div class="card" style="margin:8px 0;">
            <strong><?= htmlspecialchars($p['student_name']) ?></strong>
            — <?= htmlspecialchars($p['student_uid'] ?: '') ?>
            <?php if (!empty($p['student_dept'])): ?> (<?= htmlspecialchars($p['student_dept']) ?>)<?php endif; ?>
            <div class="muted">Quiz: <?= htmlspecialchars($p['quiz_title']) ?> — Submitted: <?= htmlspecialchars($p['created_at']) ?></div>
            <div style="margin-top:8px;">
              <a class="btn" href="../../controllers/faculty/grade_quiz.php?attempt=<?= (int)$p['attempt_id'] ?>">Grade</a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="card" style="margin-top:16px;">
        <h3>Dept Quizzes</h3>
        <?php if (!$deptQuizzes): ?>
          <p class="muted">No quizzes yet. </p>
        <?php else: foreach ($deptQuizzes as $q): ?>
          <div class="card" style="margin:8px 0;">
            <strong><?= htmlspecialchars($q['title']) ?></strong>
            <div class="muted">
              Course: <?= htmlspecialchars($q['course_id'] ?? '—') ?> • Duration: <?= (int)($q['duration_minutes'] ?? 0) ?> min
            </div>
            <div class="muted">Created: <?= htmlspecialchars($q['created_at']) ?></div>
            <div style="margin-top:8px;">
              <a class="btn" href="results.php?quiz=<?= (int)$q['id'] ?>">Results</a>
              <a class="btn secondary" href="../../controllers/faculty/create_quiz.php?edit=<?= (int)$q['id'] ?>">Edit</a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

</body>
</html>
