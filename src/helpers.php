<?php
// src/helpers.php – general utility functions

require_once __DIR__ . '/config.php';

/**
 * Safe output (prevent XSS)
 */
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format price in Kenyan style
 */
function format_money(float $amount): string {
    return 'KSh ' . number_format($amount, 0);
}

/**
 * Redirect with message (optional)
 */
function redirect(string $url, string $message = '', string $type = 'success'): void {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit();
}

/**
 * Get & clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}