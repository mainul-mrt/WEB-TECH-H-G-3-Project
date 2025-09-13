<?php
require_once __DIR__ . '/../models/config/config.php';
require_once __DIR__ . '/../models/lib/db.php';

$db = getDB();
$email = 'admin@webproject.local';
$hash  = password_hash('Admin@1234', PASSWORD_BCRYPT);

// if admin exists, update; else insert
$sel = $db->prepare("SELECT id FROM users WHERE email=?");
$sel->execute([$email]);
if ($sel->fetch()) {
  $db->prepare("UPDATE users SET role='admin', status='active', verified=1, full_name='System Admin', user_id='ADM-001', password_hash=? WHERE email=?")
     ->execute([$hash, $email]);
  echo "Updated admin. Login: $email / Admin@1234";
} else {
  $db->prepare("INSERT INTO users (role,status,verified,full_name,user_id,email,password_hash) VALUES ('admin','active',1,'System Admin','ADM-001',?,?)")
     ->execute([$email,$hash]);
  echo "Created admin. Login: $email / Admin@1234";
}
