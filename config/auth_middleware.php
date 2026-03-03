<?php
// Simple role-based middleware helpers
// Usage: require_role('super_admin');

function require_role($role) {
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Location: /codesamplecaps/public/login.php');
        exit();
    }
}

function require_any_role(array $roles) {
    session_start();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        header('Location: /codesamplecaps/public/login.php');
        exit();
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role() {
    return $_SESSION['role'] ?? null;
}

?>