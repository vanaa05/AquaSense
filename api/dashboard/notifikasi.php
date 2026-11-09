<?php
/**
 * AquaSense — API Dashboard: Notifikasi
 * GET  /api/dashboard/notifikasi.php           → daftar notifikasi belum dibaca
 * POST /api/dashboard/notifikasi.php?baca=id   → tandai satu notifikasi dibaca
 * POST /api/dashboard/notifikasi.php?baca=all  → tandai semua dibaca
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $notif = getNotifikasiBelumDibaca(20);
    $count = countNotifikasiBelumDibaca();

    $result = array_map(fn($n) => [
        'id'           => (int) $n['id'],
        'jenis'        => $n['jenis'],
        'pesan'        => $n['pesan'],
        'nilai_sensor' => $n['nilai_sensor'],
        'time_ago'     => timeAgo($n['created_at']),
        'created_at'   => $n['created_at'],
    ], $notif);

    jsonResponse(['success' => true, 'count' => $count, 'notifikasi' => $result]);
}

if ($method === 'POST') {
    $baca = $_GET['baca'] ?? '';

    if ($baca === 'all') {
        tandaiSemuaDibaca();
        jsonResponse(['success' => true, 'message' => 'Semua notifikasi ditandai dibaca.']);
    }

    $id = (int) $baca;
    if ($id > 0) {
        tandaiDibaca($id);
        jsonResponse(['success' => true, 'message' => 'Notifikasi ditandai dibaca.']);
    }

    jsonResponse(['success' => false, 'message' => 'Parameter tidak valid.'], 400);
}

jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
