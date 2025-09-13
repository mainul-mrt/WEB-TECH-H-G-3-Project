<?php
// lib/auth.php
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}
function requireRole(string $role) {
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== $role) {
        header("Location: ../views/public/index.php"); exit;
    }
}
