-- ============================================================
-- AquaSense — Migration: Penyelarasan Database
-- Jalankan pada database yang sudah ada (bukan instalasi baru).
-- Instalasi baru: gunakan aquasense_database.sql langsung.
-- ============================================================

USE `iot`;

-- ============================================================
-- 1. Pastikan tabel admin_users ada dan memiliki akun default.
--    Password default: aquasense123
--    Ganti setelah deploy ke produksi!
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)     NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `nama`          VARCHAR(100)    NOT NULL DEFAULT 'Administrator',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tambah akun admin default jika belum ada
-- Hash dibuat dengan: password_hash('aquasense123', PASSWORD_BCRYPT)
INSERT IGNORE INTO `admin_users` (`username`, `password_hash`, `nama`) VALUES
('admin', '$2y$10$JRgLxmbkts8mvvMV3lNENO9titOfHGez3Dw9zCblQ6qrO4dhgY6eS', 'Administrator AquaSense');

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
-- 5. Ganti password admin (opsional — isi hash baru di sini)
--    Gunakan tools/generate_hash.php untuk membuat hash:
--    php tools/generate_hash.php <password_baru>
-- ============================================================
-- UPDATE admin_users SET password_hash = '<hash_baru>' WHERE username = 'admin';

-- ============================================================
-- Catatan verifikasi setelah migrasi:
-- 1. Buka http://localhost/AquaSense/login.php
-- 2. Login dengan admin / aquasense123
-- 3. Periksa bahwa halaman Beranda, Sensor, Jadwal memerlukan login
-- 4. Periksa halaman Beranda — status ESP32 harus "Offline" (belum ada heartbeat)
-- 5. Kirim POST ke /api/esp32/heartbeat.php dengan header X-API-Token
--    untuk menguji status online ESP32
-- ============================================================
