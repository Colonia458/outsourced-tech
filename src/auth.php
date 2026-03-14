<?php
// src/auth.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Check if customer is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get current logged-in user ID
 */
function current_user_id(): ?int {
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Require login – redirect if not authenticated
 */
function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        header("Location: $redirect");
        exit();
    }
}

/**
 * Require admin login
 */
function require_admin(): void {
    if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] <= 0) {
        header("Location: ../login.php");
        exit();
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) &&
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}