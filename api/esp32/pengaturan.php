<?php
/**
 * AquaSense — API ESP32: Ambil Pengaturan Sistem
 * GET /api/esp32/pengaturan.php
 * Header: X-API-Token: <token>
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

jsonResponse([
    'success'           => true,
    'pakan_otomatis'    => (int) getPengaturan('pakan_otomatis'),
    'buzzer_peringatan' => (int) getPengaturan('buzzer_peringatan'),
    'jeda_minimum_menit'=> (int) getPengaturan('jeda_minimum_menit'),
    'server_time'       => date('Y-m-d H:i:s'),
]);
