<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// from views/student → ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('student');

$db  = getDB();
$uid = (int)($_SESSION['user_id'] ?? 0);
$attemptId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
  SELECT a.*, q.title AS quiz_title, q.id AS quiz_id
  FROM quiz_attempts a
  JOIN quizzes q ON q.id = a.quiz_id
  WHERE a.id = ? AND a.student_id = ?
  LIMIT 1
");
$stmt->execute([$attemptId, $uid]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$attempt) {
  http_response_code(404);
  echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Not found</title></head><body>";
  echo "<h2>Attempt not found</h2><p><a href='results.php'>Back to My Results</a></p></body></html>";
  exit;
}

$qStmt = $db->prepare("
  SELECT id, type, question, options, answer, marks, position
  FROM quiz_questions
  WHERE quiz_id = ?
  ORDER BY COALESCE(position, 999999), id
");
$qStmt->execute([(int)$attempt['quiz_id']]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

function norm_str(?string $s): string {
  if ($s === null) return '';
  $s = preg_replace('/\s+/u', ' ', $s);
  return mb_strtolower(trim($s), 'UTF-8');
}

function normalize_answer_to_text($raw, array $options): ?string {
  if ($raw === null) return null;
  $raw = trim((string)$raw);
  if ($raw === '') return null;

  // try JSON shapes first
  $json = json_decode($raw, true);
  if (is_array($json)) {
    if (isset($json['text']) && is_string($json['text']) && $json['text'] !== '') {
      $raw = $json['text'];
    } else {
      foreach (['index','i','choice','selected','value'] as $k) {
        if (isset($json[$k]) && (is_int($json[$k]) || ctype_digit((string)$json[$k]))) {
          $idx = (int)$json[$k];
          if ($idx >= 0 && $idx < count($options)) return (string)$options[$idx];
          if ($idx >= 1 && $idx <= count($options)) return (string)$options[$idx-1];
        }
      }
    }
  }

  // "option 2"
  if (preg_match('/^option\s*(\d+)$/i', $raw, $m)) {
    $i = (int)$m[1];
    if ($i >= 1 && $i <= count($options)) return (string)$options[$i-1];
  }

  // "B" → 2
  if (preg_match('/^[A-Za-z]$/', $raw)) {
    $i = ord(strtoupper($raw)) - ord('A') + 1;
    if ($i >= 1 && $i <= count($options)) return (string)$options[$i-1];
  }

  // "2" or "0"
  if (ctype_digit($raw)) {
    $i = (int)$raw;
    if ($i >= 1 && $i <= count($options)) return (string)$options[$i-1];
    if ($i >= 0 && $i < count($options))   return (string)$options[$i];
  }

  // exact text (case/space-insensitive)
  $normRaw = norm_str($raw);
  foreach ($options as $opt) {
    if (norm_str($opt) === $normRaw) return (string)$opt;
  }
  $nospace = function($s){ return mb_strtolower(preg_replace('/\s+/u','',$s),'UTF-8'); };
  $raw2 = $nospace($raw);
  foreach ($options as $opt) {
    if ($nospace($opt) === $raw2) return (string)$opt;
  }

  return $raw;
}

$answersByQ = [];
$haveAnswersTable = false;
try {
  $t = $db->query("SHOW TABLES LIKE 'quiz_attempt_answers'");
  if ($t && $t->fetch()) $haveAnswersTable = true;
} catch (Throwable $e) { $haveAnswersTable = false; }

if ($haveAnswersTable) {
  $aStmt = $db->prepare("
    SELECT question_id, answer_text, awarded, auto_awarded
    FROM quiz_attempt_answers
    WHERE attempt_id = ?
  ");
  $aStmt->execute([$attemptId]);
  foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $answersByQ[(int)$row['question_id']] = [
      'text'    => $row['answer_text'],
      'awarded' => ($row['awarded'] === null ? null : (int)$row['awarded']),
      'auto'    => ($row['auto_awarded'] === null ? null : (int)$row['auto_awarded']),
    ];
  }
} else {
  $map = [];
  if (!empty($attempt['answers'])) {
    $decoded = json_decode($attempt['answers'], true);
    if (is_array($decoded)) $map = $decoded;
  }
  foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $answersByQ[$qid] = [
      'text'    => array_key_exists($qid, $map) ? (string)$map[$qid] : null,
      'awarded' => null,
      'auto'    => null,
    ];
  }
}

$totalMarks = 0;
$totalAwarded = 0;
$totalMCQ = 0; $totalCQ = 0;
$awardMCQ = 0; $awardCQ = 0;

$rows = [];
foreach ($questions as $q) {
  $qid    = (int)$q['id'];
  $type   = mb_strtolower(trim($q['type'] ?? 'mcq'), 'UTF-8');
  $prompt = (string)$q['question'];
  $marks  = (int)($q['marks'] ?? 0);
  $totalMarks += $marks;
  if ($type === 'mcq') $totalMCQ += $marks; else $totalCQ += $marks;

  $options = [];
  if (!empty($q['options'])) {
    $decoded = json_decode($q['options'], true);
    if (is_array($decoded)) $options = $decoded;
  }
  $correctText = (string)($q['answer'] ?? '');

  $ans     = $answersByQ[$qid] ?? ['text'=>null,'awarded'=>null,'auto'=>null];
  $stuRaw  = $ans['text'];
  $stuAnsText = normalize_answer_to_text($stuRaw, $options);

  $finalAward = $ans['awarded'];
  $isCorrect  = null;

  if ($type === 'mcq') {
    $isCorrect = (norm_str($stuAnsText) !== '' && norm_str($correctText) !== '')
               ? (norm_str($stuAnsText) === norm_str($correctText))
               : false;
    $finalAward = $isCorrect ? $marks : 0;
    $awardMCQ += (int)$finalAward;
  } else {
    if ($finalAward === null && (int)($attempt['graded'] ?? 0) === 1) {
      $finalAward = 0;
    }
    $awardCQ += (int)$finalAward;
  }

  if ($finalAward !== null) $totalAwarded += (int)$finalAward;

  $rows[] = [
    'id'        => $qid,
    'type'      => $type,
    'question'  => $prompt,
    'options'   => $options,
    'correct'   => $correctText,
    'marks'     => $marks,
    'answer'    => $stuAnsText,
    'awarded'   => $finalAward,
    'isCorrect' => $isCorrect,
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Attempt #<?= htmlspecialchars($attemptId) ?> — <?= htmlspecialchars($attempt['quiz_title']) ?></title>
  <!-- views/student → ../public/assets/... -->
  <link rel="stylesheet" href="../public/assets/app.css">
  <style>
    .qrow{padding:14px;border-radius:12px;background:#fff;box-shadow:var(--shadow);margin:10px 0;}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px}
    .badge.mcq{background:#E8F3FF;color:#1C64F2}
    .badge.qa{background:#FFF1E6;color:#9A3412}
    .ans{margin-top:8px}
    .pill{display:inline-block;padding:6px 10px;border-radius:10px;margin-right:8px;margin-bottom:6px;border:1px solid #eee}
    .pill.correct{background:#E7F8EF;border-color:#34D399;color:#065F46}
    .pill.wrong{background:#FFE8E8;border-color:#FCA5A5;color:#991B1B}
    .muted{color:#6b7280}
    .scorebox{padding:12px 16px;border-radius:12px;background:#111827;color:#fff;display:inline-flex;gap:6px;flex-direction:column;align-items:flex-start}
    .scorebox small{opacity:.85}
  </style>
</head>
<body class="container">

  <div class="card" style="margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:14px;justify-content:space-between;">
      <div>
        <h2 style="margin:0;"><?= htmlspecialchars($attempt['quiz_title']) ?></h2>
        <div class="muted">
          Attempt #<?= (int)$attemptId ?> —
          <?= htmlspecialchars(date('Y-m-d H:i', strtotime($attempt['created_at'] ?? 'now'))) ?>
          <?php if (!empty($attempt['submitted_at'])): ?>
            • Submitted: <?= htmlspecialchars(date('Y-m-d H:i', strtotime($attempt['submitted_at']))) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="scorebox">
        <div><strong>Final: <?= (int)$totalAwarded ?> / <?= (int)$totalMarks ?></strong></div>
        <small>MCQ: <?= (int)$awardMCQ ?> / <?= (int)$totalMCQ ?></small>
        <small>CQ: <?= (int)$awardCQ ?> / <?= (int)$totalCQ ?></small>
        <a class="btn secondary" href="results.php">Back to My Results</a>
      </div>
    </div>
  </div>

  <?php foreach ($rows as $i => $r): ?>
    <div class="qrow">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <strong>Q<?= $i+1 ?>.</strong>
          <?= htmlspecialchars($r['question']) ?>
        </div>
        <span class="badge <?= $r['type']==='mcq'?'mcq':'qa' ?>"><?= strtoupper($r['type']) ?></span>
      </div>

      <?php if ($r['type'] === 'mcq'): ?>
        <div class="ans">
          <?php foreach ($r['options'] as $opt):
            $cls = '';
            if (norm_str($r['answer']) !== '') {
              if (norm_str($opt) === norm_str($r['answer'])) {
                $cls = ($r['isCorrect'] ? 'correct' : 'wrong');
              }
            }
          ?>
            <span class="pill <?= $cls ?>"><?= htmlspecialchars($opt) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="muted" style="margin-top:6px">
          Correct: <strong><?= htmlspecialchars($r['correct']) ?></strong>
          • Awarded: <strong><?= (int)$r['awarded'] ?></strong> / <?= (int)$r['marks'] ?>
        </div>
      <?php else: ?>
        <div class="ans" style="white-space:pre-wrap;border:1px dashed #e5e7eb;padding:10px;border-radius:8px;background:#fafafa">
          <?= $r['answer'] !== null ? htmlspecialchars($r['answer']) : '<span class="muted">No answer submitted</span>' ?>
        </div>
        <div class="muted" style="margin-top:6px">
          Awarded: <strong><?= ($r['awarded'] === null ? '—' : (int)$r['awarded']) ?></strong> / <?= (int)$r['marks'] ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</body>
</html>
