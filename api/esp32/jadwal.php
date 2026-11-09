<?php
/**
 * AquaSense — API ESP32: Ambil Jadwal Aktif
 * GET /api/esp32/jadwal.php
 * Header: X-API-Token: <token>
 * Response: { "success": true, "pakan_otomatis": 1, "jadwal": [...] }
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

$pakanOtomatis = (int) getPengaturan('pakan_otomatis');
$jadwal = $pakanOtomatis ? getJadwalAktif() : [];

$result = array_map(fn($j) => [
    'id'          => (int)  $j['id'],
    'nama'        => $j['nama'],
    'jam'         => $j['jam'],
    'sudut_servo' => (int)  $j['sudut_servo'],
], $jadwal);

jsonResponse([
    'success'        => true,
    'pakan_otomatis' => $pakanOtomatis,
    'jadwal'         => $result,
    'server_time'    => date('Y-m-d H:i:s'),
]);
