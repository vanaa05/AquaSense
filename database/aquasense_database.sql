-- ============================================================
-- AquaSense — Smart Aquarium Feeder & Monitoring
-- Database: iot
-- ============================================================

CREATE DATABASE IF NOT EXISTS `iot` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `iot`;

-- ============================================================
-- Tabel: monitoring
-- Data sensor dari ESP32
-- ============================================================
CREATE TABLE IF NOT EXISTS `monitoring` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `suhu`          DECIMAL(5,2)    NULL        COMMENT 'Suhu air dalam °C (DS18B20)',
    `turbidity_adc` INT             NULL        COMMENT 'Nilai ADC turbidity sensor',
    `jarak_pakan`   DECIMAL(5,2)    NULL        COMMENT 'Jarak permukaan pakan dalam cm (HC-SR04)',
    `suhu_valid`    TINYINT(1)      NOT NULL DEFAULT 1,
    `turb_valid`    TINYINT(1)      NOT NULL DEFAULT 1,
    `jarak_valid`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel: jadwal_pakan
-- Jadwal pemberian pakan otomatis
-- ============================================================
CREATE TABLE IF NOT EXISTS `jadwal_pakan` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nama`          VARCHAR(100)    NOT NULL,
    `jam`           TIME            NOT NULL,
    `sudut_servo`   TINYINT         NOT NULL DEFAULT 90 COMMENT '45, 90, 135, atau 180 derajat',
    `aktif`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_jam` (`jam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel: pengaturan
-- Konfigurasi global sistem
-- ============================================================
CREATE TABLE IF NOT EXISTS `pengaturan` (
    `kunci`         VARCHAR(100)    NOT NULL,
    `nilai`         TEXT            NOT NULL,
    `keterangan`    VARCHAR(255)    NULL,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel: perintah_pakan
-- Antrean perintah Feed Now dari dashboard ke ESP32
-- ============================================================
CREATE TABLE IF NOT EXISTS `perintah_pakan` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `sudut_servo`   TINYINT         NOT NULL DEFAULT 90,
    `sumber`        ENUM('dashboard','push_button') NOT NULL DEFAULT 'dashboard',
    `status`        ENUM('pending','executed','rejected','timeout') NOT NULL DEFAULT 'pending',
    `alasan`        VARCHAR(255)    NULL        COMMENT 'Alasan penolakan dari ESP32',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `executed_at`   DATETIME        NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel: riwayat_pakan
-- Log semua pemberian pakan yang berhasil
-- ============================================================
CREATE TABLE IF NOT EXISTS `riwayat_pakan` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `jenis`         ENUM('otomatis','manual') NOT NULL,
    `sumber`        ENUM('jadwal','dashboard','push_button') NOT NULL,
    `jadwal_id`     INT UNSIGNED    NULL,
    `perintah_id`   INT UNSIGNED    NULL,
    `sudut_servo`   TINYINT         NOT NULL,
    `status`        ENUM('berhasil','gagal','ditolak') NOT NULL DEFAULT 'berhasil',
    `alasan`        VARCHAR(255)    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_jenis` (`jenis`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`jadwal_id`)   REFERENCES `jadwal_pakan`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`perintah_id`) REFERENCES `perintah_pakan`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel: riwayat_peringatan
-- Log peringatan kondisi sensor
-- ============================================================
CREATE TABLE IF NOT EXISTS `riwayat_peringatan` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `jenis`         ENUM('suhu_tinggi','suhu_rendah','keruh','pakan_menipis','perangkat_offline','sensor_error') NOT NULL,
    `pesan`         VARCHAR(255)    NOT NULL,
    `nilai_sensor`  VARCHAR(100)    NULL        COMMENT 'Nilai sensor pemicu',
    `dibaca`        TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dibaca` (`dibaca`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel: admin_users
-- Autentikasi dashboard (migrasi tambahan)
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

-- ============================================================
-- View: v_kuota_manual_hari_ini
-- Menghitung sisa kuota pemberian pakan manual hari ini.
-- Laporan: pemberian pakan manual dari dashboard DAN push_button
-- keduanya dihitung terhadap batas 2x/hari.
-- Batas maksimal diambil dari tabel pengaturan (kuota_manual_per_hari).
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
-- View: v_pakan_terakhir
-- Waktu pemberian pakan terakhir dari semua sumber
-- ============================================================
CREATE OR REPLACE VIEW `v_pakan_terakhir` AS
SELECT
    MAX(`created_at`) AS waktu_terakhir,
    TIMESTAMPDIFF(MINUTE, MAX(`created_at`), NOW()) AS menit_sejak_pakan
FROM `riwayat_pakan`
WHERE `status` = 'berhasil';

-- ============================================================
-- Data awal: pengaturan sistem
-- ============================================================
INSERT IGNORE INTO `pengaturan` (`kunci`, `nilai`, `keterangan`) VALUES
('pakan_otomatis',        '1',            'Status pakan otomatis (1=aktif, 0=nonaktif)'),
('buzzer_peringatan',     '1',            'Status buzzer peringatan pakan (1=aktif, 0=nonaktif)'),
('jeda_minimum_menit',    '240',          'Jeda minimum antar pemberian pakan manual (menit)'),
('kuota_manual_per_hari', '2',            'Maksimal pemberian pakan manual per hari'),
('suhu_min',              '24',           'Suhu minimum normal (°C)'),
('suhu_max',              '30',           'Suhu maksimum normal (°C)'),
('turb_normal_min',       '3151',         'ADC turbidity — batas bawah normal (ADC >= 3151 → Normal)'),
('turb_agak_keruh_min',   '2301',         'ADC turbidity — batas bawah agak keruh (ADC >= 2301 → Agak Keruh)'),
('jarak_pakan_menipis',   '10',           'Jarak pakan dianggap menipis (cm)'),
('offline_threshold_detik','120',         'Detik tanpa heartbeat sebelum perangkat dinyatakan offline'),
('api_token_esp32',       'AQUASENSE_ESP32_TOKEN_2024', 'Token autentikasi perangkat ESP32'),
('esp32_last_seen',       '',             'Timestamp terakhir ESP32 mengirim heartbeat'),
('esp32_ip',              '',             'Alamat IP ESP32 terakhir terdaftar'),
('esp32_firmware',        '',             'Versi firmware ESP32 terakhir terdaftar');

-- ============================================================
-- Data awal: jadwal pakan default
-- ============================================================
INSERT IGNORE INTO `jadwal_pakan` (`nama`, `jam`, `sudut_servo`, `aktif`) VALUES
('Pagi',    '08:00:00', 90, 1),
('Siang',   '12:00:00', 90, 1),
('Sore',    '17:00:00', 90, 1),
('Malam',   '21:00:00', 90, 1);

-- ============================================================
-- Data awal: admin default
-- Password: aquasense123 (ganti setelah deploy!)
-- ============================================================
-- password default: "aquasense123"
-- Hash ini dibuat dengan: password_hash('aquasense123', PASSWORD_BCRYPT)
-- Ganti setelah deploy ke produksi!
INSERT IGNORE INTO `admin_users` (`username`, `password_hash`, `nama`) VALUES
('admin', '$2y$10$JRgLxmbkts8mvvMV3lNENO9titOfHGez3Dw9zCblQ6qrO4dhgY6eS', 'Administrator AquaSense');
