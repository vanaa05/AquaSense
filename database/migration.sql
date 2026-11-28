-- ============================================================
-- AquaSense — Migration: Penyelarasan Database
-- Jalankan pada database yang sudah ada (bukan instalasi baru).
-- Instalasi baru: gunakan aquasense_database.sql langsung.
-- ============================================================

USE `iot`;

-- ============================================================
-- 1. Hapus tabel admin_users lama.
--    Dashboard AquaSense tidak menggunakan login admin.
-- ============================================================
DROP TABLE IF EXISTS `admin_users`;

-- ============================================================
-- 2. Pastikan pengaturan esp32_last_seen tersedia.
--    isPerangkatOnline() membaca nilai ini untuk menentukan
--    status koneksi ESP32 berdasarkan heartbeat terakhir.
-- ============================================================
INSERT IGNORE INTO `pengaturan` (`kunci`, `nilai`, `keterangan`) VALUES
('esp32_last_seen', '', 'Timestamp terakhir ESP32 mengirim heartbeat'),
('esp32_ip',       '', 'Alamat IP ESP32 terakhir terdaftar'),
('esp32_firmware', '', 'Versi firmware ESP32 terakhir terdaftar');

-- ============================================================
-- 3. Update View: v_kuota_manual_hari_ini
--    Perbaikan:
--    - Sumber 'push_button' sekarang ikut dihitung dalam kuota manual.
--      (Laporan: pembatasan manual berlaku untuk dashboard dan push_button)
--    - Batas kuota diambil dari tabel pengaturan, bukan hardcode angka 2.
-- ============================================================
CREATE OR REPLACE VIEW `v_kuota_manual_hari_ini` AS
SELECT
    COUNT(*) AS jumlah_manual,
    (COALESCE(
        (SELECT CAST(nilai AS UNSIGNED) FROM pengaturan WHERE kunci = 'kuota_manual_per_hari'),
        2
    ) - COUNT(*)) AS sisa_kuota
FROM `riwayat_pakan`
WHERE `jenis` = 'manual'
  AND `sumber` IN ('dashboard', 'push_button')
  AND `status` = 'berhasil'
  AND DATE(`created_at`) = CURDATE();

-- ============================================================
-- 4. Update View: v_pakan_terakhir (tidak berubah, sudah benar)
--    Mencakup semua sumber pakan (otomatis dan manual) untuk
--    perhitungan jeda minimum 240 menit.
-- ============================================================
CREATE OR REPLACE VIEW `v_pakan_terakhir` AS
SELECT
    MAX(`created_at`) AS waktu_terakhir,
    TIMESTAMPDIFF(MINUTE, MAX(`created_at`), NOW()) AS menit_sejak_pakan
FROM `riwayat_pakan`
WHERE `status` = 'berhasil';

-- ============================================================
-- Catatan verifikasi setelah migrasi:
-- 1. Buka http://localhost/AquaSense
-- 2. Pastikan halaman Beranda dapat diakses.
-- 3. Periksa halaman Sensor dan Jadwal.
-- 4. Periksa status ESP32 pada dashboard.
-- 5. Kirim POST ke /api/esp32/heartbeat.php dengan header X-API-Token
--    untuk menguji status online ESP32.
-- ============================================================
