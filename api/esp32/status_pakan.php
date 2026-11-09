<?php
/**
 * AquaSense — API ESP32: Laporan Status Pakan
 * POST /api/esp32/status_pakan.php
 * Header: X-API-Token: <token>
 * Body JSON:
 *   Pakan manual: { "perintah_id": 5, "status": "executed"|"rejected", "alasan": "..." }
 *   Pakan jadwal: { "jadwal_id": 2, "sudut_servo": 90, "status": "berhasil"|"gagal", "alasan": "..." }
 *   Push button:  { "sumber": "push_button", "sudut_servo": 90, "status": "berhasil", "alasan": "" }
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

$pdo = getDB();

try {
    $pdo->beginTransaction();

    // === Kasus 1: Perintah manual dari dashboard ===
    if (!empty($body['perintah_id'])) {
        $perintahId = (int) $body['perintah_id'];
        $status     = in_array($body['status'], ['executed', 'rejected']) ? $body['status'] : 'executed';
        $alasan     = sanitizeString($body['alasan'] ?? '');
        $sudut      = (int) ($body['sudut_servo'] ?? 90);

        $stmt = $pdo->prepare(
            "UPDATE perintah_pakan SET status = ?, alasan = ?, executed_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$status, $alasan, $perintahId]);

        if ($status === 'executed') {
            $pdo->prepare(
                "INSERT INTO riwayat_pakan (jenis, sumber, perintah_id, sudut_servo, status)
                 VALUES ('manual', 'dashboard', ?, ?, 'berhasil')"
            )->execute([$perintahId, $sudut]);
        } elseif ($status === 'rejected') {
            $pdo->prepare(
                "INSERT INTO riwayat_pakan (jenis, sumber, perintah_id, sudut_servo, status, alasan)
                 VALUES ('manual', 'dashboard', ?, ?, 'ditolak', ?)"
            )->execute([$perintahId, $sudut, $alasan]);
        }

    // === Kasus 2: Pakan dari jadwal otomatis ===
    } elseif (!empty($body['jadwal_id'])) {
        $jadwalId = (int) $body['jadwal_id'];
        $sudut    = (int) ($body['sudut_servo'] ?? 90);
        $status   = $body['status'] === 'berhasil' ? 'berhasil' : 'gagal';
        $alasan   = sanitizeString($body['alasan'] ?? '');

        $pdo->prepare(
            "INSERT INTO riwayat_pakan (jenis, sumber, jadwal_id, sudut_servo, status, alasan)
             VALUES ('otomatis', 'jadwal', ?, ?, ?, ?)"
        )->execute([$jadwalId, $sudut, $status, $alasan]);

    // === Kasus 3: Push button ===
    } elseif (isset($body['sumber']) && $body['sumber'] === 'push_button') {
        $sudut  = (int) ($body['sudut_servo'] ?? 90);
        $status = $body['status'] === 'berhasil' ? 'berhasil' : 'gagal';
        $alasan = sanitizeString($body['alasan'] ?? '');

        $pdo->prepare(
            "INSERT INTO riwayat_pakan (jenis, sumber, sudut_servo, status, alasan)
             VALUES ('manual', 'push_button', ?, ?, ?)"
        )->execute([$sudut, $status, $alasan]);

    } else {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'Parameter tidak lengkap.'], 400);
    }

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Status pakan disimpan.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('status_pakan.php error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan status.'], 500);
}
