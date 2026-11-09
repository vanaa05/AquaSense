<?php
/**
 * AquaSense — API Dashboard: Feed Now
 * POST /api/dashboard/feed_now.php   → buat perintah Feed Now
 * GET  /api/dashboard/feed_now.php   → status Feed Now (kuota, jeda, pending)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];

// ----------------------------------------------------------------
// GET — Cek status Feed Now
// ----------------------------------------------------------------
if ($method === 'GET') {
    $kuota    = getKuotaManualHariIni();
    $sisaJeda = getSisaJedaMenit();
    $can      = canFeedNow();
    $pending  = getPendingFeedNow();

    jsonResponse([
        'success'       => true,
        'sisa_kuota'    => (int) $kuota['sisa_kuota'],
        'jumlah_manual' => (int) $kuota['jumlah_manual'],
        'sisa_jeda'     => $sisaJeda,           // dalam menit
        'can_feed'      => $can['can'],
        'reason'        => $can['reason'],
        'pending'       => $pending ? [
            'id'          => (int) $pending['id'],
            'sudut_servo' => (int) $pending['sudut_servo'],
            'status'      => $pending['status'],
            'created_at'  => $pending['created_at'],
        ] : null,
    ]);
}

// ----------------------------------------------------------------
// POST — Buat perintah Feed Now
// ----------------------------------------------------------------
if ($method === 'POST') {
    $body       = json_decode(file_get_contents('php://input'), true);
    $sudutServo = (int) ($body['sudut_servo'] ?? 90);

    if (!validateSudutServo($sudutServo)) {
        jsonResponse(['success' => false, 'message' => 'Sudut servo harus 45, 90, 135, atau 180.'], 422);
    }

    // Validasi aturan Feed Now
    $can = canFeedNow();
    if (!$can['can']) {
        jsonResponse([
            'success'    => false,
            'message'    => $can['reason'],
            'sisa_jeda'  => $can['sisa_jeda'],
        ], 422);
    }

    // Pastikan tidak ada perintah pending sebelumnya
    $pending = getPendingFeedNow();
    if ($pending) {
        jsonResponse([
            'success' => false,
            'message' => 'Masih ada perintah Feed Now yang menunggu dieksekusi.',
        ], 409);
    }

    $pdo = getDB();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO perintah_pakan (sudut_servo, sumber, status) VALUES (?, 'dashboard', 'pending')"
        );
        $stmt->execute([$sudutServo]);
        $id = (int) $pdo->lastInsertId();

        jsonResponse([
            'success'    => true,
            'message'    => 'Perintah Feed Now dikirim. Menunggu konfirmasi perangkat...',
            'perintah_id'=> $id,
        ], 201);
    } catch (PDOException $e) {
        error_log('feed_now POST: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal membuat perintah.'], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
