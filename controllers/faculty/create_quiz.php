<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';
requireRole('faculty');

$db   = getDB();
$dept = $_SESSION['dept'] ?? '';
$fid  = (int)($_SESSION['user_id'] ?? 0); 


$c = $db->prepare("SELECT id, title FROM courses WHERE dept_code=? ORDER BY title");
$c->execute([$dept]);
$courses = $c->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title    = trim($_POST['title'] ?? '');
  $course   = (int)($_POST['course_id'] ?? 0);
  $duration = (int)($_POST['duration'] ?? 1);

  if ($fid > 0 && $title !== '' && $course > 0) {
    
    $ins = $db->prepare("
      INSERT INTO quizzes
        (title, dept_code, course_id, duration_minutes, type, faculty_id, created_at)
      VALUES
        (?,?,?,?, 'MIXED', ?, NOW())
    ");
    $ins->execute([$title, $dept, $course, $duration, $fid]);
    $quizId = (int)$db->lastInsertId();

    
    $q_type = $_POST['q_type'] ?? [];
    $q_text = $_POST['q_text'] ?? [];
    $q_m1   = $_POST['q_m1'] ?? [];
    $q_m2   = $_POST['q_m2'] ?? [];
    $q_m3   = $_POST['q_m3'] ?? [];
    $q_m4   = $_POST['q_m4'] ?? [];
    $q_ans  = $_POST['q_ans'] ?? [];    
    $q_mark = $_POST['q_mark'] ?? [];   

    $pos = 1;
    foreach ($q_type as $i => $t) {
      $type  = strtolower(trim($t));
      $text  = trim($q_text[$i] ?? '');
      $marks = (int)($q_mark[$i] ?? 1);
      if ($text === '' || $marks <= 0) continue;

      if ($type === 'mcq') {
        $opt1 = trim($q_m1[$i] ?? '');
        $opt2 = trim($q_m2[$i] ?? '');
        $opt3 = trim($q_m3[$i] ?? '');
        $opt4 = trim($q_m4[$i] ?? '');
        $correctIx = (int)($q_ans[$i] ?? 1);
        if ($correctIx < 1 || $correctIx > 4) $correctIx = 1;

        $options     = [$opt1, $opt2, $opt3, $opt4];
        $answerStr   = $options[$correctIx - 1] ?? $opt1;
        $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);

        $iq = $db->prepare("
          INSERT INTO quiz_questions
            (quiz_id, type, question, options, answer, marks, position)
          VALUES (?,?,?,?,?,?,?)
        ");
        $iq->execute([$quizId, 'mcq', $text, $optionsJson, $answerStr, $marks, $pos++]);
      } else {
        $iq = $db->prepare("
          INSERT INTO quiz_questions
            (quiz_id, type, question, options, answer, marks, position)
          VALUES (?,?,?,?,?,?,?)
        ");
        $iq->execute([$quizId, 'qa', $text, null, null, $marks, $pos++]);
      }
    }

    $_SESSION['msg'] = "Quiz created.";
    header("Location: /MVC/views/faculty/dashboard.php");
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Create Quiz</title>
  <link rel="stylesheet" href="../../views/public/assets/app.css">
  <style>
    
  </style>
</head>
<body class="container">

  <form class="card" method="post">
    <div class="form-head">
      <div class="form-head-left">
        <a href="../../views/faculty/dashboard.php" class="btn back">Back</a>
        <h2 class="form-title">Create Quiz</h2>
      </div>
    </div>

    <div class="grid">
      <div>
        <label>Title</label>
        <input name="title" required>
      </div>
      <div class="row2">
        <div>
          <label>Course</label>
          <select name="course_id" required>
            <option value="">Select...</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Duration (minutes)</label>
          <input name="duration" type="number" value="10" min="1">
        </div>
      </div>
    </div>

    <hr>

    <div id="qs"></div>

    <div class="actions">
      <button type="button" class="btn add" onclick="addQ()">Add Question</button>
      <button type="submit" class="btn save">Save Quiz</button>
    </div>
  </form>

<script>
let qIndex = 0;
function qTpl(i){
  return `
  <div class="card" style="margin:10px 0">
    <div class="row2">
      <div>
        <label>Type</label>
        <select name="q_type[${i}]" onchange="toggleMCQ(${i},this.value)">
          <option value="mcq">MCQ</option>
          <option value="qa">QUESTION ANSWER</option>
        </select>
      </div>
      <div>
        <label>Marks</label>
        <input type="number" name="q_mark[${i}]" value="1" min="1">
      </div>
    </div>
    <div style="margin-top:10px">
      <label>Question</label>
      <textarea name="q_text[${i}]" required></textarea>
    </div>
    <div id="mcq_${i}" style="margin-top:10px">
      <label>Options</label>
      <div class="row2">
        <input name="q_m1[${i}]" placeholder="Option 1">
        <input name="q_m2[${i}]" placeholder="Option 2">
      </div>
      <div class="row2" style="margin-top:6px">
        <input name="q_m3[${i}]" placeholder="Option 3">
        <input name="q_m4[${i}]" placeholder="Option 4">
      </div>
      <div style="margin-top:6px">
        <label>Correct Option</label>
        <select name="q_ans[${i}]">
          <option value="1">Option 1</option>
          <option value="2">Option 2</option>
          <option value="3">Option 3</option>
          <option value="4">Option 4</option>
        </select>
      </div>
    </div>
  </div>`;
}
function addQ(){
  const wrap = document.getElementById('qs');
  wrap.insertAdjacentHTML('beforeend', qTpl(qIndex));
  qIndex++;
}
function toggleMCQ(i,val){
  document.getElementById('mcq_'+i).style.display = (val==='mcq') ? '' : 'none';
}
addQ(); 
</script>
</body>
</html>
