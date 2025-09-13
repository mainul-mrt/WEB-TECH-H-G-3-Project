<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// from views/student → ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('student');

$db  = getDB();
$uid = (int)($_SESSION['user_id'] ?? 0);

function resolveAvatar(PDO $db, int $uid): string {
    // views/student → views/public
    $WEB_BASE = '../public/';
    $FS_BASE  = __DIR__ . '/../public/';

    $default = $WEB_BASE . 'assets/default.png';
    if ($uid <= 0) return $default;

    $stmt = $db->prepare("SELECT COALESCE(profile_pic,'') FROM users WHERE id=?");
    $stmt->execute([$uid]);
    $p = trim((string)$stmt->fetchColumn());
    if ($p === '') return $default;

    $p = str_replace('\\','/',$p);

    // absolute URL or absolute-from-webroot
    if (preg_match('#^https?://#i', $p) || (isset($p[0]) && $p[0] === '/')) return $p;

    // normalize any leading public/ prefixes
    if (stripos($p, 'public/') === 0)        $p = substr($p, 7);
    if (stripos($p, '../public/') === 0)     $p = substr($p, 10);

    $web = $WEB_BASE . ltrim($p,'/');
    $fs  = $FS_BASE  . ltrim($p,'/');

    if (is_file($fs)) return $web;

    // fallback: look under uploads/ by filename
    $fallbackRel = 'uploads/' . basename($p);
    $fallbackWeb = $WEB_BASE . $fallbackRel;
    $fallbackFs  = $FS_BASE  . $fallbackRel;

    return is_file($fallbackFs) ? $fallbackWeb : $default;
}

$avatar = resolveAvatar($db, $uid);

// fetch attempts
$st = $db->prepare("
  SELECT a.id, a.quiz_id, a.grade, a.graded, a.created_at, q.title
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  WHERE a.student_id = ?
  ORDER BY a.created_at DESC
");
$st->execute([$uid]);
$attempts = $st->fetchAll(PDO::FETCH_ASSOC);

// precompute max marks per quiz
$quizIds = array_unique(array_map(fn($r) => (int)$r['quiz_id'], $attempts));
$maxByQuiz = [];
if ($quizIds) {
    $in = implode(',', array_fill(0, count($quizIds), '?'));
    $m  = $db->prepare("
      SELECT quiz_id, SUM(marks) AS total
      FROM quiz_questions
      WHERE quiz_id IN ($in)
      GROUP BY quiz_id
    ");
    $m->execute($quizIds);
    foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $x) {
        $maxByQuiz[(int)$x['quiz_id']] = (int)$x['total'];
    }
}

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
  <title>My Results</title>
  <!-- views/student → ../public/assets/... -->
  <link rel="stylesheet" href="../public/assets/app.css">
</head>
<body class="container">
  <div class="header">
    <img class="avatar" src="<?= htmlspecialchars($avatar) ?>" alt="Profile Picture">
    <h2 style="margin:0">My Results</h2>
    <div class="spacer"></div>
    <a class="btn secondary" href="dashboard.php">Home</a>
  </div>

  <?php if (!$attempts): ?>
    <div class="card"><p class="muted">No attempts yet.</p></div>
  <?php else: ?>
    <?php foreach ($attempts as $a):
      $max = $maxByQuiz[(int)$a['quiz_id']] ?? 0;
      $pct = ($max > 0) ? (int) round(($a['grade'] / $max) * 100) : 0;
      $cls = pctClass($pct);
    ?>
      <div class="card" style="margin-bottom:12px">
        <div style="display:flex;align-items:center;gap:16px">
          <div style="flex:1">
            <strong><?= htmlspecialchars($a['title']) ?></strong>
            <div class="muted">
              Score: <?= (int)$a['grade'] ?> / <?= (int)$max ?> — <?= $a['graded'] ? 'Final' : 'Pending QA' ?>
            </div>
            <div class="progress <?= $cls ?>" style="margin-top:8px">
              <span style="width:<?= $pct ?>%"></span>
            </div>
          </div>
          <div>
            <a class="btn secondary" href="view_attempt.php?id=<?= (int)$a['id'] ?>">Details</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
