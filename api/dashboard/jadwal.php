<?php
/**
 * AquaSense — API Dashboard: Jadwal Pakan
 * GET    /api/dashboard/jadwal.php           → daftar semua jadwal
 * POST   /api/dashboard/jadwal.php           → tambah jadwal baru
 * PUT    /api/dashboard/jadwal.php?id=N      → edit jadwal
 * DELETE /api/dashboard/jadwal.php?id=N      → hapus jadwal
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDB();

// ----------------------------------------------------------------
// GET — Daftar jadwal
// ----------------------------------------------------------------
if ($method === 'GET') {
    jsonResponse(['success' => true, 'jadwal' => getSemuaJadwal()]);
}

// ----------------------------------------------------------------
// POST — Tambah jadwal
// ----------------------------------------------------------------
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $nama        = sanitizeString($body['nama']        ?? '');
    $jam         = sanitizeString($body['jam']         ?? '');
    $sudutServo  = (int) ($body['sudut_servo']         ?? 90);
    $aktif       = isset($body['aktif']) ? (int)(bool)$body['aktif'] : 1;

    if (empty($nama) || empty($jam)) {
        jsonResponse(['success' => false, 'message' => 'Nama dan jam wajib diisi.'], 422);
    }
    if (!validateSudutServo($sudutServo)) {
        jsonResponse(['success' => false, 'message' => 'Sudut servo harus 45, 90, 135, atau 180.'], 422);
    }
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam)) {
        jsonResponse(['success' => false, 'message' => 'Format jam tidak valid (HH:MM).'], 422);
    }

    // Pastikan jam belum digunakan
    $cek = $pdo->prepare('SELECT id FROM jadwal_pakan WHERE jam = ?');
    $cek->execute([$jam]);
    if ($cek->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Jadwal dengan jam tersebut sudah ada.'], 409);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO jadwal_pakan (nama, jam, sudut_servo, aktif) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nama, $jam, $sudutServo, $aktif]);
        $id = (int) $pdo->lastInsertId();
        jsonResponse(['success' => true, 'message' => 'Jadwal berhasil ditambahkan.', 'id' => $id], 201);
    } catch (PDOException $e) {
        error_log('jadwal POST: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menyimpan jadwal.'], 500);
    }
}

// ----------------------------------------------------------------
// PUT — Edit jadwal
// ----------------------------------------------------------------
if ($method === 'PUT') {
    $id   = (int) ($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$id) jsonResponse(['success' => false, 'message' => 'ID jadwal tidak valid.'], 400);

    $jadwal = getJadwalById($id);
    if (!$jadwal) jsonResponse(['success' => false, 'message' => 'Jadwal tidak ditemukan.'], 404);

    $nama       = sanitizeString($body['nama']       ?? $jadwal['nama']);
    $jam        = sanitizeString($body['jam']        ?? $jadwal['jam']);
    $sudutServo = (int) ($body['sudut_servo']        ?? $jadwal['sudut_servo']);
    $aktif      = isset($body['aktif']) ? (int)(bool)$body['aktif'] : (int)$jadwal['aktif'];

    if (!validateSudutServo($sudutServo)) {
        jsonResponse(['success' => false, 'message' => 'Sudut servo harus 45, 90, 135, atau 180.'], 422);
    }
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam)) {
        jsonResponse(['success' => false, 'message' => 'Format jam tidak valid (HH:MM).'], 422);
    }

    // Cek duplikasi jam (kecuali jadwal itu sendiri)
    $cek = $pdo->prepare('SELECT id FROM jadwal_pakan WHERE jam = ? AND id != ?');
    $cek->execute([$jam, $id]);
    if ($cek->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Jadwal dengan jam tersebut sudah ada.'], 409);
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE jadwal_pakan SET nama = ?, jam = ?, sudut_servo = ?, aktif = ? WHERE id = ?'
        );
        $stmt->execute([$nama, $jam, $sudutServo, $aktif, $id]);
        jsonResponse(['success' => true, 'message' => 'Jadwal berhasil diperbarui.']);
    } catch (PDOException $e) {
        error_log('jadwal PUT: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal memperbarui jadwal.'], 500);
    }
}

// ----------------------------------------------------------------
// DELETE — Hapus jadwal
// ----------------------------------------------------------------
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID jadwal tidak valid.'], 400);

    $jadwal = getJadwalById($id);
    if (!$jadwal) jsonResponse(['success' => false, 'message' => 'Jadwal tidak ditemukan.'], 404);

    try {
        // Histori riwayat_pakan akan tetap (jadwal_id = NULL via FK SET NULL)
        $stmt = $pdo->prepare('DELETE FROM jadwal_pakan WHERE id = ?');
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Jadwal berhasil dihapus.']);
    } catch (PDOException $e) {
        error_log('jadwal DELETE: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Gagal menghapus jadwal.'], 500);
    }
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
