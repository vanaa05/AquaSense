<?php
/**
 * AquaSense — API Dashboard: Pengaturan (buzzer, pakan otomatis)
 * GET  /api/dashboard/pengaturan.php           → semua pengaturan
 * POST /api/dashboard/pengaturan.php           → ubah satu atau beberapa pengaturan
 * Body JSON: { "buzzer_peringatan": 1 } atau { "pakan_otomatis": 0 }
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pdo  = getDB();
    $rows = $pdo->query(
        "SELECT kunci, nilai, keterangan FROM pengaturan WHERE kunci NOT IN ('api_token_esp32')"
    )->fetchAll();
    $data = [];
    foreach ($rows as $row) {
        $data[$row['kunci']] = $row['nilai'];
    }
    jsonResponse(['success' => true, 'pengaturan' => $data]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        jsonResponse(['success' => false, 'message' => 'Body JSON tidak valid.'], 400);
    }

    // Hanya izinkan pengaturan tertentu diubah dari dashboard
    $allowed = ['buzzer_peringatan', 'pakan_otomatis'];

    foreach ($body as $kunci => $nilai) {
        if (!in_array($kunci, $allowed, true)) continue;
        setPengaturan($kunci, (string)(int)$nilai);
    }

    jsonResponse(['success' => true, 'message' => 'Pengaturan berhasil disimpan.']);
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
