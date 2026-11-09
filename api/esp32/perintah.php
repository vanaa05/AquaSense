<?php
/**
 * AquaSense — API ESP32: Ambil Antrean Feed Now
 * GET /api/esp32/perintah.php
 * Header: X-API-Token: <token>
 * Response: pending perintah, jika ada
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
}

requireEsp32Token();

// Timeout perintah lama (>5 menit) secara otomatis
$pdo = getDB();
$pdo->exec(
    "UPDATE perintah_pakan SET status = 'timeout'
     WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
);

$perintah = getPendingFeedNow();

if ($perintah) {
    jsonResponse([
        'success'     => true,
        'ada_perintah'=> true,
        'perintah'    => [
            'id'          => (int) $perintah['id'],
            'sudut_servo' => (int) $perintah['sudut_servo'],
            'sumber'      => $perintah['sumber'],
            'created_at'  => $perintah['created_at'],
        ],
    ]);
} else {
    jsonResponse(['success' => true, 'ada_perintah' => false]);
}
