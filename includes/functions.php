<?php
/**
 * AquaSense — Fungsi Utilitas Global
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// ================================================================
// FUNGSI SENSOR & STATUS
// ================================================================

function getStatusSuhu(float $suhu): array {
    if ($suhu < SUHU_MIN) {
        return ['status' => 'warning', 'label' => 'Suhu Rendah', 'class' => 'danger'];
    } elseif ($suhu > SUHU_MAX) {
        return ['status' => 'warning', 'label' => 'Suhu Tinggi', 'class' => 'danger'];
    }
    return ['status' => 'normal', 'label' => 'Normal', 'class' => 'success'];
}

function getStatusTurbidity(int $adc): array {
    // Laporan: Normal > 3150, Agak Keruh 2301–3150, Keruh ≤ 2300
    // TURB_NORMAL_MIN = 3150 sebagai batas bawah normal (eksklusif di laporan → ">3150")
    // Sehingga: Normal jika $adc > 3150, yaitu $adc >= 3151
    if ($adc >= TURB_NORMAL_MIN) {
        return ['status' => 'normal', 'label' => 'Normal', 'class' => 'success', 'kategori' => 'Jernih'];
    } elseif ($adc >= TURB_AGAK_KERUH_MIN) {
        return ['status' => 'warning', 'label' => 'Agak Keruh', 'class' => 'warning', 'kategori' => 'Agak Keruh'];
    }
    return ['status' => 'danger', 'label' => 'Keruh', 'class' => 'danger', 'kategori' => 'Keruh'];
}

function getStatusPakan(float $jarak): array {
    if ($jarak >= JARAK_PAKAN_MENIPIS) {
        return ['status' => 'warning', 'label' => 'Menipis', 'class' => 'warning'];
    }
    return ['status' => 'normal', 'label' => 'Cukup', 'class' => 'success'];
}

// ================================================================
// FUNGSI MONITORING
// ================================================================

function getMonitoringTerakhir(): ?array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT * FROM monitoring ORDER BY created_at DESC LIMIT 1');
    return $stmt->fetch() ?: null;
}

function getHistoriMonitoring(int $jam = 24, int $limit = 100): array {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT * FROM monitoring 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
         ORDER BY created_at ASC
         LIMIT ?'
    );
    $stmt->execute([$jam, $limit]);
    return $stmt->fetchAll();
}

function isPerangkatOnline(): bool {
    // Gunakan timestamp heartbeat terakhir dari tabel pengaturan.
    // Endpoint /api/esp32/heartbeat.php menyimpan 'esp32_last_seen' setiap kali
    // ESP32 mengirim heartbeat, terpisah dari data sensor.
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE kunci = 'esp32_last_seen'");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row || empty($row['nilai'])) return false;
    $diff = time() - strtotime($row['nilai']);
    return $diff <= OFFLINE_THRESHOLD;
}

// ================================================================
// FUNGSI JADWAL PAKAN
// ================================================================

function getJadwalAktif(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT * FROM jadwal_pakan WHERE aktif = 1 ORDER BY jam ASC');
    return $stmt->fetchAll();
}

function getSemuaJadwal(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT * FROM jadwal_pakan ORDER BY jam ASC');
    return $stmt->fetchAll();
}

function getJadwalById(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM jadwal_pakan WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getStatusJadwalHariIni(): array {
    $pdo = getDB();
    $stmt = $pdo->query(
        "SELECT jp.*, 
                rp.status AS status_pakan, rp.created_at AS waktu_eksekusi
         FROM jadwal_pakan jp
         LEFT JOIN riwayat_pakan rp 
            ON rp.jadwal_id = jp.id 
            AND DATE(rp.created_at) = CURDATE()
            AND rp.jenis = 'otomatis'
         ORDER BY jp.jam ASC"
    );
    return $stmt->fetchAll();
}

// ================================================================
// FUNGSI FEED NOW & KUOTA
// ================================================================

function getKuotaManualHariIni(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT * FROM v_kuota_manual_hari_ini');
    $data = $stmt->fetch();
    return $data ?: ['jumlah_manual' => 0, 'sisa_kuota' => KUOTA_MANUAL_HARIAN];
}

function getPakanTerakhir(): ?array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT * FROM v_pakan_terakhir');
    return $stmt->fetch() ?: null;
}

function getSisaJedaMenit(): int {
    $pakanTerakhir = getPakanTerakhir();
    if (!$pakanTerakhir || $pakanTerakhir['waktu_terakhir'] === null) {
        return 0;
    }
    $menitkSejak = (int) $pakanTerakhir['menit_sejak_pakan'];
    $sisa = JEDA_MINIMUM_MENIT - $menitkSejak;
    return max(0, $sisa);
}

function canFeedNow(): array {
    $kuota = getKuotaManualHariIni();
    $sisaJeda = getSisaJedaMenit();

    if ($kuota['sisa_kuota'] <= 0) {
        return ['can' => false, 'reason' => 'Kuota pemberian manual hari ini sudah habis.', 'sisa_jeda' => 0];
    }
    if ($sisaJeda > 0) {
        return ['can' => false, 'reason' => 'Masih dalam jeda minimum.', 'sisa_jeda' => $sisaJeda];
    }
    return ['can' => true, 'reason' => '', 'sisa_jeda' => 0];
}

function getPendingFeedNow(): ?array {
    $pdo = getDB();
    $stmt = $pdo->query(
        "SELECT * FROM perintah_pakan WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1"
    );
    return $stmt->fetch() ?: null;
}

// ================================================================
// FUNGSI PENGATURAN
// ================================================================

function getPengaturan(string $kunci): ?string {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT nilai FROM pengaturan WHERE kunci = ?');
    $stmt->execute([$kunci]);
    $row = $stmt->fetch();
    return $row ? $row['nilai'] : null;
}

function setPengaturan(string $kunci, string $nilai): void {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
    );
    $stmt->execute([$kunci, $nilai]);
}

// ================================================================
// FUNGSI NOTIFIKASI
// ================================================================

function getNotifikasiBelumDibaca(int $limit = 10): array {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT * FROM riwayat_peringatan WHERE dibaca = 0 ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function countNotifikasiBelumDibaca(): int {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT COUNT(*) FROM riwayat_peringatan WHERE dibaca = 0');
    return (int) $stmt->fetchColumn();
}

function tandaiDibaca(int $id): void {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE riwayat_peringatan SET dibaca = 1 WHERE id = ?');
    $stmt->execute([$id]);
}

function tandaiSemuaDibaca(): void {
    getDB()->exec('UPDATE riwayat_peringatan SET dibaca = 1 WHERE dibaca = 0');
}

function simpanPeringatan(string $jenis, string $pesan, ?string $nilaiSensor = null): void {
    // Hindari duplikasi peringatan dalam 10 menit terakhir (sesuai rancangan laporan).
    // Cooldown diterapkan per jenis peringatan agar peringatan berbeda tidak saling menghalangi.
    $pdo = getDB();
    $cek = $pdo->prepare(
        "SELECT id FROM riwayat_peringatan
         WHERE jenis = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         LIMIT 1"
    );
    $cek->execute([$jenis]);
    if ($cek->fetch()) return;

    $stmt = $pdo->prepare(
        'INSERT INTO riwayat_peringatan (jenis, pesan, nilai_sensor) VALUES (?, ?, ?)'
    );
    $stmt->execute([$jenis, $pesan, $nilaiSensor]);
}

// ================================================================
// FUNGSI HELPER
// ================================================================

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeString(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

function validateSudutServo(int $sudut): bool {
    return in_array($sudut, [45, 90, 135, 180], true);
}

function formatWaktu(string $datetime): string {
    return date('d/m/Y H:i', strtotime($datetime));
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return $diff . ' detik lalu';
    if ($diff < 3600)   return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400)  return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}
