<?php
if (session_status()===PHP_SESSION_NONE) session_start();

if (!defined('APP_BASE')) define('APP_BASE','/MVC');              // change if your folder name differs
if (!defined('ROOT'))     define('ROOT', dirname(__DIR__, 2));    // …/MVC

require_once ROOT . '/models/config/config.php';
require_once ROOT . '/models/lib/db.php';
require_once ROOT . '/models/lib/auth.php';

requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . APP_BASE . '/views/student/available_quizzes.php'); exit;
}

$db    = getDB();
$uid   = (int)($_SESSION['user_id'] ?? 0);
$quizId = (int)($_POST['quiz_id'] ?? 0);
$answers = $_POST['ans'] ?? [];

/* validate quiz */
$qz = $db->prepare("SELECT * FROM quizzes WHERE id=?");
$qz->execute([$quizId]);
$quiz = $qz->fetch(PDO::FETCH_ASSOC);
if (!$quiz) { $_SESSION['msg'] = 'Invalid quiz.'; header('Location: ' . APP_BASE . '/views/student/available_quizzes.php'); exit; }

/* prevent duplicate attempts */
$dup = $db->prepare("SELECT 1 FROM quiz_attempts WHERE quiz_id=? AND student_id=?");
$dup->execute([$quizId, $uid]);
if ($dup->fetch()) { $_SESSION['msg']='Already attempted.'; header('Location: ' . APP_BASE . '/views/student/results.php'); exit; }

/* load questions */
$qs = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY COALESCE(position,999999), id ASC");
$qs->execute([$quizId]);
$rows = $qs->fetchAll(PDO::FETCH_ASSOC);

/* create attempt + answers */
$db->beginTransaction();
$db->prepare("INSERT INTO quiz_attempts(student_id,quiz_id,grade,graded,grading_details,created_at) VALUES (?,?,?,?,?,NOW())")
   ->execute([$uid,$quizId,0,0,'{}']);
$attemptId = (int)$db->lastInsertId();

$earned = 0;
$insAns = $db->prepare("INSERT INTO quiz_attempt_answers(attempt_id,question_id,answer_text,auto_awarded,awarded) VALUES (?,?,?,?,0)");

foreach ($rows as $r) {
  $qid   = (int)$r['id'];
  $marks = (int)$r['marks'];
  $given = trim((string)($answers[$qid] ?? ''));
  $auto  = 0;

  if (strtolower($r['type']) === 'mcq') {
    $correct = trim((string)$r['answer']);
    if ($given !== '' && strcasecmp($given, $correct) === 0) {
      $auto = $marks; $earned += $marks;
    }
  }
  $insAns->execute([$attemptId, $qid, $given, $auto]);
}

/* finalize grade flags */
$hasCQ = (bool)array_filter($rows, fn($q) => strtolower($q['type']) !== 'mcq');

if (!$hasCQ) {
  $db->prepare("UPDATE quiz_attempts SET grade=?, graded=1 WHERE id=?")->execute([$earned,$attemptId]);
  $_SESSION['msg'] = "Submitted. MCQ score: $earned";
} else {
  $db->prepare("UPDATE quiz_attempts SET grade=?, graded=0 WHERE id=?")->execute([$earned,$attemptId]);
  $_SESSION['msg'] = "Submitted. MCQ part saved; CQ will be graded by faculty.";
}

$db->commit();

/* go to results */
header('Location: ' . APP_BASE . '/views/student/results.php'); exit;
