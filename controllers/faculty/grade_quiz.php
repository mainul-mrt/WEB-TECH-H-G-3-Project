<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';
requireRole('faculty');

$db = getDB();
$attId = (int)($_GET['attempt'] ?? 0);


$st = $db->prepare("
  SELECT a.*, 
         q.title        AS quiz_title, 
         q.id           AS quiz_id, 
         u.id           AS student_id,
         u.full_name    AS student_name, 
         u.user_id      AS student_uid,
         COALESCE(u.profile_pic,'') AS student_pic
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  JOIN users   u ON u.id = a.student_id
  WHERE a.id = ?
");
$st->execute([$attId]);
$att = $st->fetch(PDO::FETCH_ASSOC);
if (!$att) { header("Location: /MVC/views/faculty/dashboard.php"); exit; }


$avatar = '../../views/public/assets/default.png';
$rawPic = (string)$att['student_pic'];
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


$qa = $db->prepare("
 SELECT q.id AS question_id, q.type, q.question, q.marks, q.answer,
        a.answer_text, a.auto_awarded, a.awarded
 FROM quiz_questions q
 LEFT JOIN quiz_attempt_answers a
   ON a.question_id = q.id AND a.attempt_id = ?
 WHERE q.quiz_id = ?
 ORDER BY q.position ASC, q.id ASC
");
$qa->execute([$attId, (int)$att['quiz_id']]);
$items = $qa->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grades = $_POST['qa'] ?? [];

    $upd = $db->prepare("
      UPDATE quiz_attempt_answers 
      SET awarded = ? 
      WHERE attempt_id = ? AND question_id = ?
    ");

    foreach ($items as $it) {
        if (strtolower($it['type']) !== 'qa') continue;
        $qid   = (int)$it['question_id'];
        $max   = (int)$it['marks'];
        $given = isset($grades[$qid]) ? (int)$grades[$qid] : 0;
        if ($given < 0)   $given = 0;
        if ($given > $max) $given = $max;
        $upd->execute([$given, $attId, $qid]);
    }

   
    $sum = $db->prepare("
      SELECT COALESCE(SUM(COALESCE(auto_awarded,0) + COALESCE(awarded,0)),0)
      FROM quiz_attempt_answers
      WHERE attempt_id = ?
    ");
    $sum->execute([$attId]);
    $total = (int)$sum->fetchColumn();

    $db->prepare("UPDATE quiz_attempts SET grade=?, graded=1 WHERE id=?")
       ->execute([$total, $attId]);

    $_SESSION['msg'] = "Grading saved. Final: $total";
    header("Location: /MVC/views/faculty/dashboard.php");
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Grade Attempt</title>
  <link rel="stylesheet" href="../../views/public/assets/app.css">
</head>
<body class="container">
  <div class="header">
    <img class="avatar" src="<?= htmlspecialchars($avatar) ?>" alt="Student">
    <div>
      <h2 style="margin:0">Grade: <?= htmlspecialchars($att['quiz_title']) ?></h2>
      <div class="muted">
        Student: <?= htmlspecialchars($att['student_name']) ?> — <?= htmlspecialchars($att['student_uid']) ?>
      </div>
    </div>
    <div class="spacer"></div>
    <a class="btn secondary" href="../../views/faculty/dashboard.php">Back</a>
  </div>

  <form method="post" class="card">
    <?php foreach ($items as $i => $it): ?>
      <div class="question">
        <div style="font-weight:700;margin-bottom:6px">
          Q<?= ($i + 1) ?>. <?= htmlspecialchars($it['question']) ?>
        </div>

        <?php if (strtolower($it['type']) === 'mcq'):
          $isCorr = (strcasecmp((string)$it['answer'], (string)($it['answer_text'] ?? '')) === 0);
        ?>
          <div class="option <?= $isCorr ? 'correct' : 'wrong' ?>">
            Student: <?= htmlspecialchars($it['answer_text'] ?? '') ?>
          </div>
          <div class="option" style="margin-top:6px">
            Correct: <?= htmlspecialchars($it['answer'] ?? '') ?> (<?= (int)$it['marks'] ?>)
          </div>
          <div class="muted" style="margin-top:6px">
            Auto: <?= (int)($it['auto_awarded'] ?? 0) ?> mark(s)
          </div>
        <?php else: ?>
          <label>Student Answer</label>
          <div class="option" style="white-space:pre-wrap">
            <?= nl2br(htmlspecialchars($it['answer_text'] ?? '')) ?>
          </div>
          <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
            <div class="muted">Max <?= (int)$it['marks'] ?> • Award:</div>
            <input
              type="number"
              name="qa[<?= (int)$it['question_id'] ?>]"
              min="0"
              max="<?= (int)$it['marks'] ?>"
              value="<?= (int)($it['awarded'] ?? 0) ?>"
              style="width:110px"
            >
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:16px">
      <button class="btn">Save Grades</button>
    </div>
  </form>
</body>
</html>
