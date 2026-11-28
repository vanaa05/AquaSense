# AquaSense

## Tema Proyek
Internet of Things (IoT) – Smart Aquarium Feeder & Monitoring.

## Deskripsi Singkat
AquaSense merupakan sistem IoT untuk membantu pemberian pakan ikan secara otomatis dan memantau kondisi akuarium. Sistem menggunakan ESP32, sensor suhu DS18B20, sensor kekeruhan air, sensor ultrasonik HC-SR04, dan servo motor. Data sensor serta pengaturan pemberian pakan dikelola melalui aplikasi web berbasis PHP dan MySQL.

## Struktur Repositori
- `api/` – endpoint komunikasi antara ESP32 dan aplikasi web.
- `assets/` – file CSS dan JavaScript.
- `config/` – konfigurasi aplikasi dan koneksi database.
- `database/` – berkas SQL untuk membuat dan menyesuaikan database.
- `firmware/` – kode program ESP32.
- `includes/` – fungsi dan komponen pendukung aplikasi.
- `pages/` – halaman dashboard.
- `tools/` – skrip pendukung.

## Berkas Program Utama
- Aplikasi web: `index.php`
- Firmware ESP32: `firmware/AquaSense_ESP32_FIX.ino`
- Database: `database/aquasense_database.sql`

## Menjalankan Aplikasi Web
1. Salin folder repositori ke direktori `htdocs` pada XAMPP.
2. Jalankan Apache dan MySQL.
3. Buat database dengan mengimpor `database/aquasense_database.sql` melalui phpMyAdmin.
4. Sesuaikan konfigurasi database pada `config/database.php`.
5. Sesuaikan alamat aplikasi pada `config/app.php`.
6. Buka `http://localhost/AquaSense` melalui browser.

## Konfigurasi Firmware
Buka `firmware/AquaSense_ESP32_FIX.ino` melalui Arduino IDE. Sesuaikan SSID, password Wi-Fi, alamat server, dan token API dengan lingkungan yang digunakan. Pastikan token API pada firmware sama dengan token pada konfigurasi server.

**Keamanan:** Jangan mengunggah password Wi-Fi, token API pribadi, API key, atau kredensial lain ke repositori publik. Gunakan nilai konfigurasi milik masing-masing pengguna dan simpan kredensial pribadi di luar berkas yang diunggah.