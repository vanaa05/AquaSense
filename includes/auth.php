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

function isLoggedIn(): bool {
    // Laporan tidak merancang autentikasi login.
    // Akses dashboard dilindungi oleh jaringan lokal (Wi-Fi), bukan oleh session.
    return true;
}

function requireLogin(): void {
    // Tidak ada pembatasan akses — sesuai rancangan laporan.
}

function loginAdmin(string $username, string $password): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, nama FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        startSecureSession();
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $user['id'];
        $_SESSION['admin_user'] = $user['username'];
        $_SESSION['admin_nama'] = $user['nama'];
        return true;
    }
    return false;
}

function logoutAdmin(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
}

function getAdminNama(): string {
    return $_SESSION['admin_nama'] ?? 'Admin';
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
