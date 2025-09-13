<?php
if (session_status()===PHP_SESSION_NONE) session_start();

// from views/student -> ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('student');

$db   = getDB();
$uid  = (int)($_SESSION['user_id'] ?? 0);
$dept = $_SESSION['dept'] ?? '';

/** Resolve the student's avatar to a web path under ../public/, with a safe fallback */
function resolve_avatar(PDO $db, int $uid): string {
    $default = '../public/assets/default.png';
    if ($uid <= 0) return $default;

    $stmt = $db->prepare("SELECT COALESCE(profile_pic,'') FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $raw = trim((string)$stmt->fetchColumn());
    if ($raw === '') return $default;

    $p = str_replace('\\', '/', $raw);
    if (preg_match('#^https?://#i', $p)) return $p; // external URL stored

    // If a filename or relative path is stored, look in ../public/uploads
    $rel = 'uploads/' . basename($p);
    $fs  = __DIR__ . '/../public/' . $rel;
    $web = '../public/' . $rel;
    return is_file($fs) ? $web : $default;
}

$avatar = resolve_avatar($db, $uid);

// Quizzes the student has NOT attempted yet, for their dept
$st = $db->prepare("
  SELECT q.*
  FROM quizzes q
  WHERE q.dept_code = ?
    AND NOT EXISTS (
      SELECT 1 FROM quiz_attempts a
      WHERE a.quiz_id = q.id AND a.student_id = ?
    )
  ORDER BY q.created_at DESC
");
$st->execute([$dept, $uid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Available Quizzes</title>
  <!-- views/student -> ../public/assets/... -->
  <link rel="stylesheet" href="../public/assets/app.css">
</head>
<body class="container">
  <div class="header">
    <img class="avatar" src="<?= htmlspecialchars($avatar) ?>" alt="Profile">
    <div><h2 style="margin:0">Available Quizzes (<?= htmlspecialchars($dept) ?>)</h2></div>
    <div class="spacer"></div>
    <a class="btn secondary" href="dashboard.php">Home</a>
  </div>

  <?php if (!$rows): ?>
    <div class="card"><p class="muted">No quizzes left to attempt. 🎉</p></div>
  <?php endif; ?>

  <?php foreach ($rows as $q): ?>
    <div class="card" style="margin-bottom:12px">
      <h3 style="margin:0 0 8px"><?= htmlspecialchars($q['title']) ?></h3>
      <div class="muted">
        Course: <?= htmlspecialchars($q['course_id'] ?? '—') ?>
        • Duration: <?= (int)($q['duration_minutes'] ?? 0) ?> min
      </div>
      <div style="margin-top:10px">
        <!-- take_quiz now accepts ?id=... (and also ?quiz=...) -->
        <a class="btn" href="take_quiz.php?id=<?= (int)$q['id'] ?>">Start</a>
      </div>
    </div>
  <?php endforeach; ?>
</body>
</html>
