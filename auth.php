<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['member_id']);
}


function getUserId(): ?int {
    return $_SESSION['member_id'] ?? null;
}


function getUserRole(): ?string {
    return $_SESSION['member_role'] ?? null;
}


function isAdmin(): bool {
    return getUserRole() === 'admin';
}


function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}


function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}
?>
<?php

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_csrf_token(): string {
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
?>
