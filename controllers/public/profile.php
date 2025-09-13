<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Paths */
if (!defined('APP_BASE')) define('APP_BASE', '/MVC');                  // <-- change if your folder name differs
if (!defined('ROOT'))     define('ROOT', dirname(__DIR__, 2));         // C:\xampp\htdocs\MVC
$PUBLIC_WEB = APP_BASE . '/views/public';
$PUBLIC_FS  = ROOT . '/views/public';

/* Includes (from controllers/public -> ../../models/...) */
require_once ROOT . '/models/config/config.php';
require_once ROOT . '/models/lib/db.php';
require_once ROOT . '/models/lib/auth.php';

if (!isLoggedIn()) { header('Location: ' . $PUBLIC_WEB . '/index.php'); exit; }

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

/* flash */
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
function set_flash($msg) { $_SESSION['flash'] = $msg; }

/* uploads live under views/public/uploads */
$uploadDir = $PUBLIC_FS . '/uploads';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

/* handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'save_profile') {
    $full = trim($_POST['full_name'] ?? '');
    $dept = trim($_POST['dept'] ?? '');
    $desg = trim($_POST['designation'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($full === '' || ($_SESSION['role'] !== 'admin' && $dept === '')) {
      set_flash('Full name and dept are required.');
      header('Location: ' . APP_BASE . '/controllers/public/profile.php'); exit;
    }

    $stmt = $db->prepare("UPDATE users SET full_name=?, dept=?, designation=? WHERE id=?");
    $stmt->execute([$full, $dept, $desg, $uid]);

    if ($pass !== '') {
      $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
         ->execute([password_hash($pass, PASSWORD_BCRYPT), $uid]);
    }

    set_flash('Profile updated.');
    header('Location: ' . APP_BASE . '/controllers/public/profile.php'); exit;
  }

  if ($action === 'upload_pic') {
    if (!empty($_FILES['profile_pic']['name'])) {
      $f = $_FILES['profile_pic'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed, true)) {
          if ($f['size'] <= 2 * 1024 * 1024) {
            $cur = $db->prepare("SELECT profile_pic FROM users WHERE id=?");
            $cur->execute([$uid]);
            $old = (string)$cur->fetchColumn();

            $newName = 'user_'.$uid.'_'.time().'.'.$ext;
            $destAbs = $uploadDir . '/' . $newName;

            if (move_uploaded_file($f['tmp_name'], $destAbs)) {
              $db->prepare("UPDATE users SET profile_pic=? WHERE id=?")->execute([$newName, $uid]);

              if ($old && $old !== $newName) {
                $oldAbs = $uploadDir . '/' . basename($old);
                if (is_file($oldAbs)) { @unlink($oldAbs); }
              }

              set_flash('Profile picture updated.');
              header('Location: ' . APP_BASE . '/controllers/public/profile.php'); exit;
            } else set_flash('Could not move uploaded file.');
          } else set_flash('Image must be 2MB or smaller.');
        } else set_flash('Invalid image type. Allowed: jpg, jpeg, png, gif, webp.');
      } else set_flash('Upload error (code '.$f['error'].').');
    } else set_flash('Please choose an image to upload.');

    header('Location: ' . APP_BASE . '/controllers/public/profile.php'); exit;
  }
}

/* fetch user */
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$role   = $_SESSION['role'] ?? 'student';
$picWeb = (!empty($user['profile_pic']))
  ? $PUBLIC_WEB . '/uploads/' . basename($user['profile_pic'])
  : $PUBLIC_WEB . '/assets/default.png';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Profile</title>
  <link rel="stylesheet" href="<?= $PUBLIC_WEB ?>/assets/app.css">
  <style>
    .profile-wrap { display:grid; grid-template-columns:260px 1fr; gap:24px; align-items:start }
    @media (max-width:800px){ .profile-wrap{ grid-template-columns:1fr } }
    .profile-card { text-align:center }
    .profile-pic { width:180px; height:180px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0; background:#f1f5f9 }
    .muted { color:#64748b; font-size:13px; margin:6px 0 10px }
    .upload-box { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; margin-top:12px }
  </style>
</head>
<body class="container">

<div class="nav">
  <a href="<?= APP_BASE ?>/controllers/public/router.php">Go to Dashboard</a>
  <a href="<?= APP_BASE ?>/controllers/public/logout.php">Logout</a>
</div>

<div class="card" style="max-width:1000px;margin:20px auto;">
  <h2>My Profile (<?= htmlspecialchars($role) ?>)</h2>

  <?php if ($flash): ?>
    <div class="alert ok"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <div class="profile-wrap">
    <div class="card profile-card">
      <img class="profile-pic" src="<?= htmlspecialchars($picWeb) ?>" alt="Profile Picture">
      <div class="muted">Profile Picture</div>

      <form method="post" enctype="multipart/form-data" class="upload-box">
        <input type="hidden" name="action" value="upload_pic">
        <input type="file" name="profile_pic" accept="image/*" required>
        <div style="margin-top:10px"><button class="btn">Upload</button></div>
        <div class="muted">Max 2MB. jpg, jpeg, png, gif, webp.</div>
      </form>
    </div>

    <div class="card">
      <form method="post">
        <input type="hidden" name="action" value="save_profile">

        <label>Full name</label>
        <input name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>

        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>

        <?php if (($_SESSION['role'] ?? 'student') !== 'admin'): ?>
          <label>Dept</label>
          <input name="dept" value="<?= htmlspecialchars($user['dept'] ?? '') ?>" required>
        <?php endif; ?>

        <?php if (($_SESSION['role'] ?? '') === 'faculty'): ?>
          <label>Designation</label>
          <input name="designation" value="<?= htmlspecialchars($user['designation'] ?? '') ?>">
        <?php endif; ?>

        <label>New Password (optional)</label>
        <input type="password" name="password" placeholder="Leave blank to keep current password">

        <div style="margin-top:10px"><button class="btn">Save</button></div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
