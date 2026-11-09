<?php
/**
 * AquaSense — API ESP32: Kirim Status Koneksi (Heartbeat)
 * POST /api/esp32/heartbeat.php
 * Header: X-API-Token: <token>
 * Body JSON: { "ip": "192.168.x.x", "firmware": "1.0.0" }  (opsional)
 * 
 * Cukup menyisipkan data monitoring kosong (null) sebagai tanda heartbeat,
 * atau dapat digunakan bersama endpoint sensor. Endpoint ini HANYA menyimpan
 * timestamp koneksi terakhir tanpa data sensor.
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
}

requireEsp32Token();

// Simpan pengaturan ip/firmware ke tabel pengaturan jika ada
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!empty($body['ip'])) {
    setPengaturan('esp32_ip', sanitizeString($body['ip']));
}
if (!empty($body['firmware'])) {
    setPengaturan('esp32_firmware', sanitizeString($body['firmware']));
}
setPengaturan('esp32_last_seen', date('Y-m-d H:i:s'));

jsonResponse(['success' => true, 'server_time' => date('Y-m-d H:i:s')]);
