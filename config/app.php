<?php
/**
 * AquaSense — Konfigurasi Aplikasi
 */

// -------------------------------------------------------
// URL Dasar Aplikasi (sesuaikan saat deploy ke server publik)
// -------------------------------------------------------
define('APP_URL', 'http://localhost/AquaSense');
define('APP_NAME', 'AquaSense');
define('APP_VERSION', '1.0.0');

// -------------------------------------------------------
// Zona Waktu
// -------------------------------------------------------
date_default_timezone_set('Asia/Jakarta');

// -------------------------------------------------------
// API Token ESP32
// Harus sama dengan yang tersimpan di tabel `pengaturan`
// -------------------------------------------------------
define('ESP32_API_TOKEN', 'AQUASENSE_ESP32_TOKEN_2024');

// -------------------------------------------------------
// Batas waktu offline perangkat (detik)
// -------------------------------------------------------
define('OFFLINE_THRESHOLD', 120);

// -------------------------------------------------------
// Aturan Feed Now
// -------------------------------------------------------
define('JEDA_MINIMUM_MENIT', 240);
define('KUOTA_MANUAL_HARIAN', 2);

// -------------------------------------------------------
// Ambang batas sensor
// -------------------------------------------------------
// Ambang turbidity dari laporan:
//   Normal     : ADC > 3150 → tersimpan sebagai 3150, cek dengan >=3150 di PHP
//   Agak Keruh : 2301 ≤ ADC ≤ 3150
//   Keruh      : ADC ≤ 2300
// getStatusTurbidity() menggunakan >= sehingga TURB_NORMAL_MIN = 3150
// berarti "ADC >= 3150 → Normal" yang identik dengan "ADC > 3149" ≈ ">3150"
// (integer, ADC 3150 dianggap batas bawah normal — konsisten dengan tabel laporan)
define('SUHU_MIN', 24);
define('SUHU_MAX', 30);
define('TURB_NORMAL_MIN', 3151);       // ADC >= 3151 → Normal  (ADC > 3150)
define('TURB_AGAK_KERUH_MIN', 2301);   // ADC >= 2301 → Agak Keruh
define('JARAK_PAKAN_MENIPIS', 10);

// -------------------------------------------------------
// Sesi
// -------------------------------------------------------
define('SESSION_NAME', 'aquasense_session');
define('SESSION_LIFETIME', 7200); // 2 jam

// -------------------------------------------------------
// Mode Debug (set false di produksi)
// -------------------------------------------------------
define('DEBUG_MODE', true);

if (!DEBUG_MODE) {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
