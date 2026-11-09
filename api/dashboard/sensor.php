<?php
/**
 * AquaSense — API Dashboard: Data Sensor Terkini & Histori
 * GET /api/dashboard/sensor.php              → data terkini + status
 * GET /api/dashboard/sensor.php?histori=1    → histori 24 jam
 * GET /api/dashboard/sensor.php?histori=1&jam=48  → histori N jam
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

if (!empty($_GET['histori'])) {
    $jam   = min((int) ($_GET['jam'] ?? 24), 168); // maks 7 hari
    $data  = getHistoriMonitoring($jam);

    $labels = [];
    $suhu   = [];
    $turb   = [];

    foreach ($data as $row) {
        $labels[] = date('H:i', strtotime($row['created_at']));
        $suhu[]   = $row['suhu_valid']  ? (float) $row['suhu']          : null;
        $turb[]   = $row['turb_valid']  ? (int)   $row['turbidity_adc'] : null;
    }

    jsonResponse([
        'success' => true,
        'labels'  => $labels,
        'suhu'    => $suhu,
        'turbidity_adc' => $turb,
    ]);
    return;
}

// Data terkini
$data   = getMonitoringTerakhir();
$online = isPerangkatOnline();

if (!$data) {
    jsonResponse([
        'success'       => true,
        'online'        => false,
        'suhu'          => null,
        'turbidity_adc' => null,
        'jarak_pakan'   => null,
        'updated_at'    => null,
    ]);
    return;
}

$statusSuhu   = $data['suhu_valid']  ? getStatusSuhu((float) $data['suhu'])          : ['status' => 'error', 'label' => 'Sensor Error', 'class' => 'secondary'];
$statusTurb   = $data['turb_valid']  ? getStatusTurbidity((int) $data['turbidity_adc']) : ['status' => 'error', 'label' => 'Sensor Error', 'class' => 'secondary', 'kategori' => '-'];
$statusPakan  = $data['jarak_valid'] ? getStatusPakan((float) $data['jarak_pakan'])   : ['status' => 'error', 'label' => 'Sensor Error', 'class' => 'secondary'];

jsonResponse([
    'success'           => true,
    'online'            => $online,
    'suhu'              => $data['suhu_valid']  ? (float) $data['suhu']          : null,
    'turbidity_adc'     => $data['turb_valid']  ? (int)   $data['turbidity_adc'] : null,
    'jarak_pakan'       => $data['jarak_valid'] ? (float) $data['jarak_pakan']   : null,
    'suhu_valid'        => (bool) $data['suhu_valid'],
    'turb_valid'        => (bool) $data['turb_valid'],
    'jarak_valid'       => (bool) $data['jarak_valid'],
    'status_suhu'       => $statusSuhu,
    'status_turb'       => $statusTurb,
    'status_pakan'      => $statusPakan,
    'updated_at'        => $data['created_at'],
    'time_ago'          => timeAgo($data['created_at']),
]);
