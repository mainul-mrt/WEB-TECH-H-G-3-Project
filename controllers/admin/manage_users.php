<?php
// /MVC/controllers/admin/manage_users.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ✅ from controllers/admin → ../../models/...
require_once __DIR__ . '/../../models/config/config.php';
require_once __DIR__ . '/../../models/lib/db.php';
require_once __DIR__ . '/../../models/lib/auth.php';

requireRole('admin');

$db = getDB();

/* ---------------- helpers ---------------- */

function resolve_avatar(?string $raw): string {
    // this PHP file is in /controllers/admin; assets live under /views/public/assets
    $default = '../../views/public/assets/default.png';
    if (!$raw) return $default;

    $p = str_replace('\\','/', trim($raw));
    if ($p === '') return $default;

    // already a full URL or absolute path
    if (preg_match('#^https?://#i', $p) || $p[0] === '/') return $p;

    // normalize possible prefixes
    if (stripos($p, 'public/') === 0)     $p = substr($p, 7);     // drop leading "public/"
    if (stripos($p, '../public/') === 0)  $p = substr($p, 10);    // drop leading "../public/"

    // our uploads are served from /controllers/public/uploads
    $web = '../public/' . ltrim($p, '/');                    // relative to /controllers/admin
    $fs  = __DIR__ . '/../public/' . ltrim($p, '/');         // filesystem check

    if (is_file($fs)) return $web;

    // fallback: just the basename inside uploads
    $fallbackRel = 'uploads/' . basename($p);
    $fallbackWeb = '../public/' . $fallbackRel;
    $fallbackFs  = __DIR__ . '/../public/' . $fallbackRel;

    return is_file($fallbackFs) ? $fallbackWeb : $default;
}

function is_gmail(string $email): bool {
    return (bool)(filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/@gmail\.com$/i', $email));
}

/* --------------- sticky old inputs --------------- */
$old = $_SESSION['old'] ?? [
    'role'        => 'student',
    'full_name'   => '',
    'email'       => '',
    'user_id'     => '',
    'dept'        => '',
    'designation' => ''
];
unset($_SESSION['old']);

/* --------------- create user --------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    $role        = $_POST['role'] ?? 'student';
    $full_name   = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $user_id     = trim($_POST['user_id'] ?? '');
    $dept        = trim($_POST['dept'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    // keep inputs for redisplay
    $_SESSION['old'] = [
        'role'        => $role,
        'full_name'   => $full_name,
        'email'       => $email,
        'user_id'     => $user_id,
        'dept'        => $dept,
        'designation' => $designation
    ];

    // basic validation
    $ok = $full_name !== '' && $email !== '' && $password !== '';
    if ($role !== 'admin') $ok = $ok && $user_id !== '' && $dept !== '';
    if ($role === 'faculty') $ok = $ok && $designation !== '';

    if (!$ok) {
        $_SESSION['flash'] = "Please fill the required fields for the selected role.";
        header("Location: manage_users.php"); exit;
    }

    // Gmail-only emails
    if (!is_gmail($email)) {
        $_SESSION['flash'] = "Please use a valid Gmail address (must end with @gmail.com).";
        header("Location: manage_users.php"); exit;
    }

    // uniqueness checks
    if ($role === 'admin') {
        $du = $db->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $du->execute([$email]);
    } else {
        $du = $db->prepare("SELECT 1 FROM users WHERE email = ? OR user_id = ? LIMIT 1");
        $du->execute([$email, $user_id]);
    }
    if ($du->fetch()) {
        $_SESSION['flash'] = "This email or user ID is already in use.";
        header("Location: manage_users.php"); exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        if ($role === 'admin') {
            $stmt = $db->prepare("
                INSERT INTO users (role, full_name, email, password_hash, verified, status, created_at)
                VALUES ('admin', ?, ?, ?, 1, 'active', NOW())
            ");
            $stmt->execute([$full_name, $email, $hash]);

        } elseif ($role === 'faculty') {
            // faculty created pending + unverified
            $stmt = $db->prepare("
                INSERT INTO users (role, full_name, user_id, dept, designation, email, password_hash, verified, status, created_at)
                VALUES ('faculty', ?, ?, ?, ?, ?, ?, 0, 'pending', NOW())
            ");
            $stmt->execute([$full_name, $user_id, $dept, $designation, $email, $hash]);

        } else { // student
            $stmt = $db->prepare("
                INSERT INTO users (role, full_name, user_id, dept, email, password_hash, verified, status, created_at)
                VALUES ('student', ?, ?, ?, ?, ?, 1, 'active', NOW())
            ");
            $stmt->execute([$full_name, $user_id, $dept, $email, $hash]);
        }

        unset($_SESSION['old']);
        $_SESSION['flash'] = ucfirst($role) . " account created.";
        header("Location: manage_users.php"); exit;

    } catch (Throwable $e) {
        $_SESSION['flash'] = "Create failed: " . $e->getMessage();
        header("Location: manage_users.php"); exit;
    }
}

/* --------------- status actions --------------- */
$actMap = [
    'deactivate' => "UPDATE users SET status='inactive' WHERE id=?",
    'activate'   => "UPDATE users SET status='active'  WHERE id=?",
    'block'      => "UPDATE users SET status='blocked' WHERE id=?",
    'unblock'    => "UPDATE users SET status='active'  WHERE id=?",
    'delete'     => "DELETE FROM users WHERE id=?"
];
foreach ($actMap as $key => $sql) {
    if (isset($_GET[$key])) {
        $id = (int)$_GET[$key];
        $db->prepare($sql)->execute([$id]);
        $_SESSION['flash'] = "User {$key}d.";
        header("Location: manage_users.php"); exit;
    }
}

/* --------------- lists --------------- */
$users = $db->query("
  SELECT id, role, full_name, user_id, dept, designation, email, status, COALESCE(profile_pic,'') AS profile_pic
  FROM users
  ORDER BY role, full_name
")->fetchAll(PDO::FETCH_ASSOC);

$blocked = $db->query("
  SELECT id, role, full_name, user_id, dept, designation, email, status, COALESCE(profile_pic,'') AS profile_pic
  FROM users
  WHERE status='blocked'
  ORDER BY role, full_name
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as &$u)   { $u['avatar'] = resolve_avatar($u['profile_pic']); }
foreach ($blocked as &$u) { $u['avatar'] = resolve_avatar($u['profile_pic']); }
unset($u);

$byRole = ['admin'=>[], 'faculty'=>[], 'student'=>[]];
foreach ($users as $u) {
    $k = strtolower($u['role']);
    if (isset($byRole[$k])) $byRole[$k][] = $u;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Users</title>
  <!-- ✅ from controllers/admin → ../../views/public/assets/app.css -->
  <link rel="stylesheet" href="../../views/public/assets/app.css">
  <style>
    .field-row{display:grid;grid-template-columns:160px 1fr;gap:10px;align-items:center}
    .role-form{display:none;margin-top:12px}
    .role-form.active{display:block}

    .user-row{display:flex;align-items:center;gap:16px}
    .avatar-sm{width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #eef2f7;flex:0 0 90px}
    .chip{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;background:#f3f4f6;margin-left:4px}
    .actions{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap}

    .user-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
    .user-col-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
    @media (max-width:980px){ .user-grid-3{grid-template-columns:1fr} }
  </style>
</head>
<body class="container">

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1>Manage Users</h1>
    <div>
      <!-- ✅ back to admin dashboard in /views/admin -->
      <a class="btn" href="../../views/admin/dashboard.php">Go to Dashboard</a>
      <!-- ✅ logout lives in /controllers/public -->
      <a class="btn danger" href="../public/logout.php">Logout</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert ok">
      <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
    </div>
  <?php endif; ?>

  <!-- Create -->
  <div class="card" style="max-width:980px;margin-bottom:16px;">
    <h2>Create User</h2>

    <div class="field-row">
      <label><strong>Role</strong></label>
      <select id="roleSwitch">
        <option value="student" <?= $old['role']==='student'?'selected':'' ?>>student</option>
        <option value="faculty" <?= $old['role']==='faculty'?'selected':'' ?>>faculty</option>
        <option value="admin"   <?= $old['role']==='admin'  ?'selected':'' ?>>admin</option>
      </select>
    </div>

    <!-- Student -->
    <form method="post" class="role-form <?= $old['role']==='student'?'active':'' ?>" id="form-student" novalidate>
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="role" value="student">
      <div class="field-row"><label>Full name</label><input name="full_name" value="<?= htmlspecialchars($old['full_name']) ?>" required></div>
      <div class="field-row"><label>User ID</label><input name="user_id" value="<?= htmlspecialchars($old['user_id']) ?>" required></div>
      <div class="field-row"><label>Dept</label><input name="dept" value="<?= htmlspecialchars($old['dept']) ?>" required></div>
      <div class="field-row">
        <label>Email</label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($old['email']) ?>"
               required
               pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
               title="Only Gmail addresses (@gmail.com) are allowed">
      </div>
      <div class="field-row"><label>Password</label><input type="password" name="password" required></div>
      <div style="margin-top:10px"><button class="btn">Create</button></div>
    </form>

    <!-- Faculty -->
    <form method="post" class="role-form <?= $old['role']==='faculty'?'active':'' ?>" id="form-faculty" novalidate>
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="role" value="faculty">
      <div class="field-row"><label>Full name</label><input name="full_name" value="<?= htmlspecialchars($old['full_name']) ?>" required></div>
      <div class="field-row"><label>User ID</label><input name="user_id" value="<?= htmlspecialchars($old['user_id']) ?>" required></div>
      <div class="field-row"><label>Dept</label><input name="dept" value="<?= htmlspecialchars($old['dept']) ?>" required></div>
      <div class="field-row"><label>Designation</label><input name="designation" value="<?= htmlspecialchars($old['designation']) ?>" required></div>
      <div class="field-row">
        <label>Email</label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($old['email']) ?>"
               required
               pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
               title="Only Gmail addresses (@gmail.com) are allowed">
      </div>
      <div class="field-row"><label>Password</label><input type="password" name="password" required></div>
      <div class="muted" style="margin-top:6px;">Note: Faculty are created as <strong>pending</strong>. Approve them from “Verify Faculty”.</div>
      <div style="margin-top:10px"><button class="btn">Create</button></div>
    </form>

    <!-- Admin -->
    <form method="post" class="role-form <?= $old['role']==='admin'?'active':'' ?>" id="form-admin" novalidate>
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="role" value="admin">
      <div class="field-row"><label>Full name</label><input name="full_name" value="<?= htmlspecialchars($old['full_name']) ?>" required></div>
      <div class="field-row">
        <label>Email</label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($old['email']) ?>"
               required
               pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
               title="Only Gmail addresses (@gmail.com) are allowed">
      </div>
      <div class="field-row"><label>Password</label><input type="password" name="password" required></div>
      <div class="muted" style="margin-top:6px;">Admin does not require User ID / Dept / Designation.</div>
      <div style="margin-top:10px"><button class="btn">Create</button></div>
    </form>
  </div>

  <!-- All Users (3 columns) -->
  <div class="card" style="max-width:980px;">
    <h2>All Users</h2>

    <div class="user-grid-3">
      <!-- Admins -->
      <div class="user-col">
        <div class="user-col-head">
          <strong>Admins</strong>
          <span class="chip"><?= count($byRole['admin']) ?></span>
        </div>
        <?php if (!$byRole['admin']): ?>
          <div class="muted">No admin users.</div>
        <?php else: foreach ($byRole['admin'] as $u): ?>
          <div class="card" style="margin:8px 0;">
            <div class="user-row">
              <img class="avatar-sm" src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
              <div style="flex:1;">
                <div>
                  <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                  <span class="chip"><?= htmlspecialchars($u['role']) ?></span>
                  <span class="chip"><?= htmlspecialchars($u['status']) ?></span>
                </div>
                <div class="muted"><?= htmlspecialchars($u['email']) ?></div>
                <div class="actions">
                  <?php if ($u['status'] === 'active'): ?>
                    <a class="btn secondary" href="?deactivate=<?= (int)$u['id'] ?>">Deactivate</a>
                    <a class="btn danger" href="?block=<?= (int)$u['id'] ?>" onclick="return confirm('Block this account?')">Block</a>
                  <?php elseif ($u['status'] === 'inactive'): ?>
                    <a class="btn" href="?activate=<?= (int)$u['id'] ?>">Activate</a>
                    <a class="btn danger" href="?block=<?= (int)$u['id'] ?>" onclick="return confirm('Block this account?')">Block</a>
                  <?php else: ?>
                    <a class="btn" href="?unblock=<?= (int)$u['id'] ?>">Unblock</a>
                  <?php endif; ?>
                  <a class="btn danger" href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')">Delete</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Faculty -->
      <div class="user-col">
        <div class="user-col-head">
          <strong>Faculty</strong>
          <span class="chip"><?= count($byRole['faculty']) ?></span>
        </div>
        <?php if (!$byRole['faculty']): ?>
          <div class="muted">No faculty users.</div>
        <?php else: foreach ($byRole['faculty'] as $u): ?>
          <div class="card" style="margin:8px 0;">
            <div class="user-row">
              <img class="avatar-sm" src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
              <div style="flex:1;">
                <div>
                  <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                  <span class="chip"><?= htmlspecialchars($u['role']) ?></span>
                  <span class="chip"><?= htmlspecialchars($u['status']) ?></span>
                </div>
                <div class="muted">
                  <?= htmlspecialchars($u['email']) ?>
                  <?php if ($u['user_id']): ?> — <?= htmlspecialchars($u['user_id']) ?><?php endif; ?>
                  <?php if ($u['dept']): ?> — <?= htmlspecialchars($u['dept']) ?><?php endif; ?>
                  <?php if ($u['designation']): ?> — <?= htmlspecialchars($u['designation']) ?><?php endif; ?>
                </div>
                <div class="actions">
                  <?php if ($u['status'] === 'active'): ?>
                    <a class="btn secondary" href="?deactivate=<?= (int)$u['id'] ?>">Deactivate</a>
                    <a class="btn danger" href="?block=<?= (int)$u['id'] ?>" onclick="return confirm('Block this account?')">Block</a>
                  <?php elseif ($u['status'] === 'inactive'): ?>
                    <a class="btn" href="?activate=<?= (int)$u['id'] ?>">Activate</a>
                    <a class="btn danger" href="?block=<?= (int)$u['id'] ?>" onclick="return confirm('Block this account?')">Block</a>
                  <?php else: ?>
                    <a class="btn" href="?unblock=<?= (int)$u['id'] ?>">Unblock</a>
                  <?php endif; ?>
                  <a class="btn danger" href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')">Delete</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Students -->
      <div class="user-col">
        <div class="user-col-head">
          <strong>Students</strong>
          <span class="chip"><?= count($byRole['student']) ?></span>
        </div>
        <?php if (!$byRole['student']): ?>
          <div class="muted">No student users.</div>
        <?php else: foreach ($byRole['student'] as $u): ?>
          <div class="card" style="margin:8px 0;">
            <div class="user-row">
              <img class="avatar-sm" src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
              <div style="flex:1;">
                <div>
                  <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                  <span class="chip"><?= htmlspecialchars($u['role']) ?></span>
                  <span class="chip"><?= htmlspecialchars($u['status']) ?></span>
                </div>
                <div class="muted">
                  <?= htmlspecialchars($u['email']) ?>
                  <?php if ($u['user_id']): ?> — <?= htmlspecialchars($u['user_id']) ?><?php endif; ?>
                  <?php if ($u['dept']): ?> — <?= htmlspecialchars($u['dept']) ?><?php endif; ?>
                </div>
                <div class="actions">
                  <?php if ($u['status'] === 'active'): ?>
                    <a class="btn secondary" href="?deactivate=<?= (int)$u['id'] ?>">Deactivate</a>
                    <a class="btn danger" href="?block=<?= (int)$u['id'] ?>" onclick="return confirm('Block this account?')">Block</a>
                  <?php elseif ($u['status'] === 'inactive'): ?>
                    <a class="btn" href="?activate=<?= (int)$u['id'] ?>">Activate</a>
                    <a class="btn danger" href="?block=<?= (int)$u['id'] ?>" onclick="return confirm('Block this account?')">Block</a>
                  <?php else: ?>
                    <a class="btn" href="?unblock=<?= (int)$u['id'] ?>">Unblock</a>
                  <?php endif; ?>
                  <a class="btn danger" href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')">Delete</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <?php if ($blocked): ?>
    <div class="card" style="max-width:980px; margin-top:16px;">
      <h2>Blocked Users</h2>
      <?php foreach ($blocked as $u): ?>
        <div class="card" style="margin:8px 0;">
          <div class="user-row">
            <img class="avatar-sm" src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
            <div style="flex:1;">
              <div>
                <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                <span class="chip"><?= htmlspecialchars($u['role']) ?></span>
                <span class="chip"><?= htmlspecialchars($u['status']) ?></span>
              </div>
              <div class="muted">
                <?= htmlspecialchars($u['email']) ?>
                <?php if ($u['user_id']): ?> — <?= htmlspecialchars($u['user_id']) ?><?php endif; ?>
                <?php if ($u['dept']): ?> — <?= htmlspecialchars($u['dept']) ?><?php endif; ?>
                <?php if ($u['designation']): ?> — <?= htmlspecialchars($u['designation']) ?><?php endif; ?>
              </div>
              <div class="actions">
                <a class="btn" href="?unblock=<?= (int)$u['id'] ?>">Unblock</a>
                <a class="btn danger" href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')">Delete</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<script>
  const switcher = document.getElementById('roleSwitch');
  const forms = {
    student: document.getElementById('form-student'),
    faculty: document.getElementById('form-faculty'),
    admin:   document.getElementById('form-admin')
  };
  function showForm() {
    const role = switcher.value;
    Object.values(forms).forEach(f => f.classList.remove('active'));
    forms[role].classList.add('active');
  }
  switcher.addEventListener('change', showForm);
  showForm();
</script>
</body>
</html>
