<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// from views/student -> ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('student');

$db   = getDB();
$uid  = (int)($_SESSION['user_id'] ?? 0);

// accept either ?id= or ?quiz=
$quizId = (int)($_GET['id'] ?? ($_GET['quiz'] ?? 0));
if (!$quizId) { header("Location: available_quizzes.php"); exit; }

// already attempted?
$chk = $db->prepare("SELECT 1 FROM quiz_attempts WHERE quiz_id=? AND student_id=?");
$chk->execute([$quizId, $uid]);
if ($chk->fetch()) { $_SESSION['msg'] = "You already attempted this quiz."; header("Location: results.php"); exit; }

// load quiz + questions
$qz = $db->prepare("SELECT * FROM quizzes WHERE id=?");
$qz->execute([$quizId]);
$quiz = $qz->fetch(PDO::FETCH_ASSOC);
if (!$quiz) { header("Location: available_quizzes.php"); exit; }

$qs = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY position ASC, id ASC");
$qs->execute([$quizId]);
$questions = $qs->fetchAll(PDO::FETCH_ASSOC);

$duration = max(1, (int)($quiz['duration_minutes'] ?? 1));
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Take Quiz</title>
  <!-- from views/student -> ../public/assets/... -->
  <link rel="stylesheet" href="../public/assets/app.css">
  <style>
    .timer{min-width:70px;text-align:center;background:#111827;color:#fff;padding:8px 12px;border-radius:12px;font-weight:700}
    .option-box{display:block;padding:12px 16px;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:10px;background:#fff;cursor:pointer;font-size:15px;transition:border-color .15s ease, background .15s ease, box-shadow .15s ease;user-select:none}
    .option-box>input[type="radio"]{position:absolute;opacity:0;pointer-events:none}
    .option-box:hover{border-color:#2563eb;background:#f0f7ff}
    .option-box:has(input[type="radio"]:checked){border-color:#2563eb;background:#eaf2ff;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
    .option-box:has(input[type="radio"]:checked) span{color:#2563eb;font-weight:600}
    .question{padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;margin-bottom:14px}
    .answer-text{width:100%;min-height:110px;resize:vertical;border:1px solid #e5e7eb;border-radius:10px;padding:10px;background:#fff}
  </style>
</head>
<body class="container">

  <div class="card">
    <div class="header">
      <div>
        <h2 style="margin:0"><?= htmlspecialchars($quiz['title']) ?></h2>
        <div class="muted">Duration: <?= (int)$duration ?> minute(s)</div>
      </div>
      <div class="spacer"></div>
      <div class="timer" id="timer">--:--</div>
    </div>

    <div id="confirm" class="card" style="margin-bottom:12px">
      <strong>Ready?</strong>
      <p class="muted" style="margin:6px 0 12px">Once you start, the timer begins and submission is automatic when time ends.</p>
      <button class="btn" id="startBtn">I'm Ready</button>
      <a class="btn secondary" href="available_quizzes.php" style="margin-left:8px">Cancel</a>
    </div>

    <!-- post to the controller -->
    <form id="quizForm" method="post" action="../../controllers/student/submit_quiz.php" style="display:none">
      <input type="hidden" name="quiz_id" value="<?= (int)$quizId ?>">

      <?php foreach ($questions as $i => $q): ?>
        <div class="question">
          <div style="font-weight:700;margin-bottom:8px">Q<?= ($i+1) ?>. <?= htmlspecialchars($q['question']) ?></div>

          <?php
            $type = strtolower(trim($q['type'] ?? ''));
            $opts = [];
            if (!empty($q['options'])) {
              $decoded = json_decode($q['options'], true);
              if (is_array($decoded)) $opts = $decoded;
            }
            // only render as MCQ if there are actually 2+ options
            $isMcq = ($type === 'mcq' && count($opts) >= 2);
          ?>

          <?php if ($isMcq): ?>
            <?php $requiredPrinted = false; ?>
            <?php foreach ($opts as $opt): ?>
              <label class="option-box">
                <input type="radio"
                       name="ans[<?= (int)$q['id'] ?>]"
                       value="<?= htmlspecialchars($opt) ?>"
                       <?= $requiredPrinted ? '' : 'required' ?>>
                <span><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php $requiredPrinted = true; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <label>Your Answer</label>
            <textarea class="answer-text"
                      name="ans[<?= (int)$q['id'] ?>]"
                      placeholder="Type your answer..."
                      required></textarea>
          <?php endif; ?>

          <div class="muted" style="margin-top:6px">Marks: <?= (int)$q['marks'] ?></div>
        </div>
      <?php endforeach; ?>

      <div style="margin-top:16px">
        <button class="btn">Submit</button>
        <a class="btn secondary" href="available_quizzes.php" style="margin-left:8px">Quit</a>
      </div>
    </form>
  </div>

<script>
const startBtn   = document.getElementById('startBtn');
const confirmBox = document.getElementById('confirm');
const form       = document.getElementById('quizForm');
const timerEl    = document.getElementById('timer');
const durMinutes = <?= (int)$duration ?>;

let endAt = null, tick = null;

function fmt(ms){
  const s = Math.max(0, Math.floor(ms/1000));
  const m = Math.floor(s/60), r = s%60;
  return String(m).padStart(2,'0') + ':' + String(r).padStart(2,'0');
}
function runTimer(){
  tick = setInterval(()=>{
    const left = endAt - Date.now();
    timerEl.textContent = fmt(left);
    if (left <= 0){ clearInterval(tick); form.submit(); }
  }, 250);
}
startBtn?.addEventListener('click', ()=>{
  if(!confirm('Start the quiz now? The timer will begin.')) return;
  confirmBox.style.display='none';
  form.style.display='block';
  endAt = Date.now() + durMinutes*60*1000;
  runTimer();
});
</script>
</body>
</html>
