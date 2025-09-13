<?php
// /MVC/controllers/public/send_otp.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/smtp_gmail.php'; // provides smtp_gmail()

$db = getDB();

// Target view pages
$FORGOT = '../../views/public/forgot.php';
$RESET  = '../../controllers/public/reset.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$FORGOT}"); exit;
}

// Inputs
$full_name = trim($_POST['full_name'] ?? '');
$user_id   = trim($_POST['user_id'] ?? '');

if ($full_name === '' || $user_id === '') {
  $_SESSION['msg'] = 'Please fill all fields.';
  header("Location: {$FORGOT}"); exit;
}

// Optional throttle: 1 request / 60s
if (!empty($_SESSION['reset_last']) && (time() - (int)$_SESSION['reset_last']) < 60) {
  $_SESSION['msg'] = 'Please wait a minute before requesting another OTP.';
  header("Location: {$FORGOT}"); exit;
}

// Find user
$st = $db->prepare("
  SELECT id, email, full_name
  FROM users
  WHERE full_name = ? AND user_id = ?
  LIMIT 1
");
$st->execute([$full_name, $user_id]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  $_SESSION['msg'] = 'No matching user found.';
  header("Location: {$FORGOT}"); exit;
}

// Generate OTP (6 digits)
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Clear any old state, then store the new one
unset($_SESSION['reset_uid'], $_SESSION['reset_email'], $_SESSION['reset_name'],
      $_SESSION['reset_otp'], $_SESSION['reset_exp']);

$_SESSION['reset_uid']   = (int)$user['id'];
$_SESSION['reset_email'] = (string)$user['email'];
$_SESSION['reset_name']  = (string)$user['full_name'];
$_SESSION['reset_otp']   = $otp;
$_SESSION['reset_exp']   = time() + 600; // 10 minutes
$_SESSION['reset_last']  = time();

// Email content
$subject = 'Your Password Reset OTP';
$html = "
  <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
  <p>This is <strong>A.K.M Tamim Rahman, Administrator</strong> of <em>SIMPLE QUIZ APPLICATION</em>.</p>
  <p>Your OTP is:
     <span style='font-size:22px;font-weight:700;letter-spacing:3px'>{$otp}</span>
  </p>
  <p>This code will expire in 10 minutes.</p>
  <p>— A Simple Quiz • Explore Your Knowledge</p>
";
$text = "Hello {$user['full_name']}\n\n"
      . "Your OTP is: {$otp}\n"
      . "This code will expire in 10 minutes.\n\n"
      . "— A Simple Quiz";

// Try to send (on local XAMPP mail() often fails — we handle that gracefully)
$sent = smtp_gmail($user['email'], $subject, $html, $text);

if ($sent) {
  $_SESSION['msg'] = 'We sent a 6-digit OTP to your email. Please enter it below.';
  header("Location: {$RESET}"); exit;
} else {
  // DEV fallback so you can continue the flow without SMTP configured
  $_SESSION['msg'] = 'Email is not configured on this server. DEV mode — your OTP is: '
                   . "<strong>{$otp}</strong>. Enter it below.";
  header("Location: {$RESET}"); exit;
}
