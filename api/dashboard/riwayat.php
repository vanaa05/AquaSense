<?php
/**
 * AquaSense — API Dashboard: Riwayat Pakan
 * GET /api/dashboard/riwayat.php
 * GET /api/dashboard/riwayat.php?limit=20&jenis=manual
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method tidak diizinkan.'], 405);
}

$limit = min((int) ($_GET['limit'] ?? 20), 100);
$jenis = in_array($_GET['jenis'] ?? '', ['manual', 'otomatis']) ? $_GET['jenis'] : null;

$pdo = getDB();

if ($jenis) {
    $stmt = $pdo->prepare(
        'SELECT rp.*, jp.nama AS nama_jadwal
         FROM riwayat_pakan rp
         LEFT JOIN jadwal_pakan jp ON jp.id = rp.jadwal_id
         WHERE rp.jenis = ?
         ORDER BY rp.created_at DESC LIMIT ?'
    );
    $stmt->execute([$jenis, $limit]);
} else {
    $stmt = $pdo->prepare(
        'SELECT rp.*, jp.nama AS nama_jadwal
         FROM riwayat_pakan rp
         LEFT JOIN jadwal_pakan jp ON jp.id = rp.jadwal_id
         ORDER BY rp.created_at DESC LIMIT ?'
    );
    $stmt->execute([$limit]);
}

$rows = $stmt->fetchAll();
$result = array_map(fn($r) => [
    'id'          => (int) $r['id'],
    'jenis'       => $r['jenis'],
    'sumber'      => $r['sumber'],
    'nama_jadwal' => $r['nama_jadwal'],
    'sudut_servo' => (int) $r['sudut_servo'],
    'status'      => $r['status'],
    'alasan'      => $r['alasan'],
    'time_ago'    => timeAgo($r['created_at']),
    'created_at'  => $r['created_at'],
], $rows);

jsonResponse(['success' => true, 'riwayat' => $result]);
