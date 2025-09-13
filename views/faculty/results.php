<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';
requireRole('faculty');

$db = getDB();
$qid = (int)($_GET['quiz'] ?? 0);
if ($qid <= 0) {
  header("Location: dashboard.php");
  exit;
}


$qt = $db->prepare("SELECT title, dept_code FROM quizzes WHERE id=? LIMIT 1");
$qt->execute([$qid]);
$quiz = $qt->fetch(PDO::FETCH_ASSOC);
if (!$quiz) {
  header("Location: dashboard.php"); exit;
}


$mx = $db->prepare("SELECT SUM(marks) AS total FROM quiz_questions WHERE quiz_id=?");
$mx->execute([$qid]);
$maxMarks = (int)($mx->fetchColumn() ?: 0);


$st = $db->prepare("
  SELECT a.id AS attempt_id, a.grade, a.graded, a.created_at,
         u.full_name, u.user_id, u.dept
  FROM quiz_attempts a
  JOIN users u ON u.id = a.student_id
  WHERE a.quiz_id = ?
  ORDER BY a.created_at DESC
");
$st->execute([$qid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);


function pctClass(int $p): string {
  if ($p >= 100) return 'p100';
  if ($p >= 75)  return 'p75';
  if ($p >= 50)  return 'p50';
  return 'p25';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Results — <?= htmlspecialchars($quiz['title']) ?></title>
  
  <link rel="stylesheet" href="../../views/public/assets/app.css">
  <style>
    .row-line{display:flex;align-items:center;gap:12px}
    .row-line .who{flex:1}
    .badge{padding:4px 8px;border-radius:999px;font-size:12px}
    .badge.green{background:#e7f7ec;color:#18794e}
    .badge.orange{background:#fff3e6;color:#b35c00}
  </style>
</head>
<body class="container">

  <div class="header">
    <div>
      <h2 style="margin:0">Results: <?= htmlspecialchars($quiz['title']) ?></h2>
      <div class="muted">Department: <?= htmlspecialchars($quiz['dept_code'] ?? '—') ?></div>
    </div>
    <div class="spacer"></div>
    <a class="btn secondary" href="dashboard.php">Back</a>
  </div>

  <?php if (!$rows): ?>
    <div class="card"><p class="muted">No attempts yet for this quiz.</p></div>
  <?php else: ?>
    <?php foreach ($rows as $r):
      $grade = (int)$r['grade'];
      $pct   = ($maxMarks > 0) ? (int)round(($grade / $maxMarks) * 100) : 0;
      $cls   = pctClass($pct);
      $isFinal = (int)$r['graded'] === 1;
    ?>
      <div class="card" style="margin-bottom:12px">
        <div class="row-line">
          <div class="who">
            <strong><?= htmlspecialchars($r['full_name']) ?></strong>
            — <?= htmlspecialchars($r['user_id'] ?: '') ?>
            <?php if (!empty($r['dept'])): ?>
              <span class="muted"> (<?= htmlspecialchars($r['dept']) ?>)</span>
            <?php endif; ?>
            <div class="muted" style="margin-top:4px"><?= htmlspecialchars($r['created_at']) ?></div>
          </div>

          <div class="muted" style="text-align:right; min-width:110px;">
            Grade: <strong><?= $grade ?></strong> / <?= $maxMarks ?>
            <div class="progress <?= $cls ?>" style="margin-top:6px; width:140px;"><span style="width:<?= $pct ?>%"></span></div>
          </div>

          <span class="badge <?= $isFinal ? 'green' : 'orange' ?>" title="<?= $isFinal ? 'Grading complete' : 'Pending manual grading' ?>">
            <?= $isFinal ? 'Final' : 'Pending' ?>
          </span>

          <a class="btn" href="grade_quiz.php?attempt=<?= (int)$r['attempt_id'] ?>" style="margin-left:8px;">
            <?= $isFinal ? 'View' : 'Grade' ?>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</body>
</html>
