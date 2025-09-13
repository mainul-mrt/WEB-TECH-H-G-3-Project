<?php
// faculty/save_quiz.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';
requireRole('faculty');

$db = getDB();

function str_or_empty($v) { return is_string($v) ? trim($v) : ''; }
function int_or_zero($v) { return is_numeric($v) ? (int)$v : 0; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: create_quiz.php");
  exit;
}

$title   = str_or_empty($_POST['title'] ?? '');
$course  = int_or_zero($_POST['course_id'] ?? 0);
$dur     = int_or_zero($_POST['duration_minutes'] ?? 0);
$dept    = $_SESSION['dept'] ?? '';
$faculty = (int)($_SESSION['user_id'] ?? 0);
$qs      = $_POST['q'] ?? null;

$errors = [];
if ($title === '') $errors[] = "Title is required.";
if ($course <= 0)  $errors[] = "Course is required.";
if ($dur <= 0)     $errors[] = "Duration must be > 0.";
if (!is_array($qs) || count($qs) === 0) $errors[] = "At least one question required.";

if ($errors) {
  $_SESSION['flash_error'] = implode(" ", $errors);
  header("Location: create_quiz.php");
  exit;
}

$db->beginTransaction();

try {
  
  $stmt = $db->prepare("
    INSERT INTO quizzes (faculty_id, dept_code, course_id, created_by, title, type, duration_minutes, created_at)
    VALUES (?, ?, ?, ?, ?, 'MIXED', ?, NOW())
  ");
  $stmt->execute([$faculty, $dept, $course, $faculty, $title, $dur]);
  $quizId = (int)$db->lastInsertId();

  
  $qstmt = $db->prepare("
    INSERT INTO quiz_questions (quiz_id, type, question, options, answer, marks, position)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");

  $pos = 1;
  foreach ($qs as $qq) {
    $qtype = strtoupper(str_or_empty($qq['qtype'] ?? ''));
    $qtext = str_or_empty($qq['question'] ?? '');
    $marks = int_or_zero($qq['marks'] ?? 0);

    if ($qtext === '' || $marks <= 0) continue;

    $options = null;
    $answer  = null;

    if ($qtype === 'MCQ') {
      $o1 = str_or_empty($qq['opt'][1] ?? '');
      $o2 = str_or_empty($qq['opt'][2] ?? '');
      $o3 = str_or_empty($qq['opt'][3] ?? '');
      $o4 = str_or_empty($qq['opt'][4] ?? '');
      $options = json_encode([$o1, $o2, $o3, $o4], JSON_UNESCAPED_UNICODE);

      $correct = int_or_zero($qq['correct'] ?? 0);
      $answer  = ($correct >= 1 && $correct <= 4) ? (${"o$correct"}) : null;

      $qtype = 'mcq';
    } else {
      $qtype = 'qa';
      $options = null;
      $answer  = null;
    }

    $qstmt->execute([$quizId, $qtype, $qtext, $options, $answer, $marks, $pos++]);
  }

  $db->commit();
  $_SESSION['flash_ok'] = "Quiz created successfully.";
  header("Location: dashboard.php");
  exit;

} catch (Throwable $e) {
  $db->rollBack();
  $_SESSION['flash_error'] = "Failed to save quiz: " . $e->getMessage();
  header("Location: create_quiz.php");
  exit;
}
