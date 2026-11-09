<?php
/**
 * AquaSense — API ESP32: Kirim Data Sensor
 * POST /api/esp32/sensor.php
 * Header: X-API-Token: <token>
 * Body JSON: { "suhu": 26.5, "turbidity_adc": 3200, "jarak_pakan": 7.5, "suhu_valid":1, "turb_valid":1, "jarak_valid":1 }
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

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    jsonResponse(['success' => false, 'message' => 'Body JSON tidak valid.'], 400);
}

$suhu          = isset($body['suhu'])          ? (float) $body['suhu']          : null;
$turbidityAdc  = isset($body['turbidity_adc']) ? (int)   $body['turbidity_adc'] : null;
$jarakPakan    = isset($body['jarak_pakan'])   ? (float) $body['jarak_pakan']   : null;
$suhuValid     = isset($body['suhu_valid'])    ? (int)   $body['suhu_valid']    : 1;
$turbValid     = isset($body['turb_valid'])    ? (int)   $body['turb_valid']    : 1;
$jarakValid    = isset($body['jarak_valid'])   ? (int)   $body['jarak_valid']   : 1;

// Validasi rentang sensor
if ($suhuValid && $suhu !== null && ($suhu < -55 || $suhu > 125)) {
    jsonResponse(['success' => false, 'message' => 'Nilai suhu di luar rentang sensor.'], 422);
}

$pdo = getDB();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO monitoring (suhu, turbidity_adc, jarak_pakan, suhu_valid, turb_valid, jarak_valid)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$suhu, $turbidityAdc, $jarakPakan, $suhuValid, $turbValid, $jarakValid]);

    // Periksa dan buat peringatan otomatis
    if ($suhuValid && $suhu !== null) {
        if ($suhu < SUHU_MIN) {
            simpanPeringatan('suhu_rendah', "Suhu air terlalu rendah: {$suhu}°C", "{$suhu}°C");
        } elseif ($suhu > SUHU_MAX) {
            simpanPeringatan('suhu_tinggi', "Suhu air terlalu tinggi: {$suhu}°C", "{$suhu}°C");
        }
    }
    // Peringatan keruh: ADC <= 2300 sesuai laporan (kategori "Keruh")
    // Ambang TURB_AGAK_KERUH_MIN = 2301, maka keruh adalah ADC < TURB_AGAK_KERUH_MIN
    if ($turbValid && $turbidityAdc !== null && $turbidityAdc < TURB_AGAK_KERUH_MIN) {
        simpanPeringatan('keruh', "Air akuarium keruh, ADC: {$turbidityAdc}", "ADC:{$turbidityAdc}");
    }
    if ($jarakValid && $jarakPakan !== null && $jarakPakan >= JARAK_PAKAN_MENIPIS) {
        simpanPeringatan('pakan_menipis', "Persediaan pakan menipis, jarak: {$jarakPakan}cm", "{$jarakPakan}cm");
    }

    jsonResponse(['success' => true, 'message' => 'Data sensor diterima.']);
} catch (PDOException $e) {
    error_log('sensor.php error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan data.'], 500);
}
