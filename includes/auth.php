<?php
/**
 * AquaSense — Autentikasi
 * Mengelola sesi admin dan validasi login.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Validasi token ESP32 dari header HTTP.
 * Gunakan di setiap endpoint API ESP32.
 */
function requireEsp32Token(): void {
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($token !== ESP32_API_TOKEN) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Token tidak valid.']));
    }
}

/**
 * Proteksi CSRF — generate token
 */
function getCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Proteksi CSRF — validasi token
 */
function validateCsrfToken(string $token): bool {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
