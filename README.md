# AquaSense — Dokumentasi Sistem
## Smart Aquarium Feeder & Monitoring

---

## 1. STRUKTUR FOLDER FINAL

```
aquasense/
├── index.php                    ← Redirect ke login atau beranda
├── login.php                    ← Halaman login admin
├── logout.php                   ← Handler logout
│
├── config/
│   ├── database.php             ← Koneksi PDO MySQL
│   └── app.php                  ← Konstanta & konfigurasi aplikasi
│
├── includes/
│   ├── header.php               ← Header HTML global (sidebar + header)
│   ├── footer.php               ← Footer HTML global (script)
│   ├── auth.php                 ← Autentikasi session & CSRF
│   └── functions.php            ← Fungsi utilitas global
│
├── pages/
│   ├── beranda.php              ← Dashboard utama
│   ├── sensor.php               ← Monitoring sensor
│   └── jadwal.php               ← Manajemen jadwal pakan
│
├── api/
│   ├── esp32/
│   │   ├── sensor.php           ← POST: terima data sensor
│   │   ├── jadwal.php           ← GET: jadwal aktif
│   │   ├── pengaturan.php       ← GET: pengaturan sistem
│   │   ├── perintah.php         ← GET: antrean Feed Now
│   │   ├── status_pakan.php     ← POST: laporan status pakan
│   │   └── heartbeat.php        ← POST: heartbeat perangkat
│   │
│   └── dashboard/
│       ├── sensor.php           ← GET: data sensor & histori
│       ├── jadwal.php           ← CRUD jadwal
│       ├── pengaturan.php       ← GET/POST pengaturan
│       ├── feed_now.php         ← GET status / POST perintah
│       ├── notifikasi.php       ← GET/POST notifikasi
│       └── riwayat.php          ← GET riwayat pakan
│
├── assets/
│   ├── css/style.css            ← Design system global
│   ├── js/app.js                ← JavaScript polling & interaksi
│   └── img/ icons/              ← Aset gambar (opsional)
│
├── database/
│   ├── aquasense_database.sql   ← Database utama (schema + seed)
│   └── migration.sql            ← Migrasi tambahan
│
└── tools/
    └── generate_hash.php        ← Helper generate password hash
```

---

## 2. PANDUAN INSTALASI XAMPP

### Langkah 1 — Salin Project

```
Salin folder aquasense/ ke:
C:\xampp\htdocs\AquaSense\
```

### Langkah 2 — Jalankan XAMPP

1. Buka **XAMPP Control Panel**
2. Klik **Start** pada Apache
3. Klik **Start** pada MySQL

### Langkah 3 — Import Database

1. Buka browser → `http://localhost/phpmyadmin`
2. Klik **New** → buat database baru bernama `iot`
3. Pilih database `iot` → klik tab **Import**
4. Pilih file `database/aquasense_database.sql`
5. Klik **Impor**

### Langkah 4 — Set Password Admin

Setelah import, jalankan:
```
http://localhost/AquaSense/tools/generate_hash.php
```
- Masukkan password yang diinginkan
- Copy hash yang dihasilkan
- Jalankan query:
```sql
USE iot;
UPDATE admin_users SET password_hash = '<hash_yang_dicopy>' WHERE username = 'admin';
```

### Langkah 5 — Konfigurasi

Edit `config/app.php`:
```php
define('APP_URL', 'http://localhost/AquaSense');   // URL lokal
```

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Password MySQL XAMPP (kosong default)
define('DB_NAME', 'iot');
```

### Langkah 6 — Akses Website

```
http://localhost/AquaSense
```

Login dengan: **admin** / password yang sudah di-set

---

## 3. PANDUAN KONFIGURASI API ESP32

### API Token

Token default: `AQUASENSE_ESP32_TOKEN_2024`

Ganti di `config/app.php`:
```php
define('ESP32_API_TOKEN', 'TOKEN_BARU_ANDA');
```

Dan update di database:
```sql
UPDATE pengaturan SET nilai = 'TOKEN_BARU_ANDA' WHERE kunci = 'api_token_esp32';
```

### URL API untuk ESP32 (lokal)

| Endpoint | Method | Keterangan |
|---|---|---|
| `http://localhost/AquaSense/api/esp32/sensor.php` | POST | Kirim data sensor |
| `http://localhost/AquaSense/api/esp32/jadwal.php` | GET | Ambil jadwal aktif |
| `http://localhost/AquaSense/api/esp32/pengaturan.php` | GET | Ambil pengaturan |
| `http://localhost/AquaSense/api/esp32/perintah.php` | GET | Cek Feed Now pending |
| `http://localhost/AquaSense/api/esp32/status_pakan.php` | POST | Lapor status pakan |
| `http://localhost/AquaSense/api/esp32/heartbeat.php` | POST | Heartbeat koneksi |

### Header yang wajib disertakan ESP32

```
X-API-Token: AQUASENSE_ESP32_TOKEN_2024
Content-Type: application/json
```

---

## 4. DOKUMENTASI KOMUNIKASI ESP32 & PHP

### A. Alur: Kirim Data Sensor

```
ESP32 → POST /api/esp32/sensor.php

Body JSON:
{
    "suhu": 26.5,
    "turbidity_adc": 3200,
    "jarak_pakan": 7.5,
    "suhu_valid": 1,
    "turb_valid": 1,
    "jarak_valid": 1
}

Response berhasil:
{ "success": true, "message": "Data sensor diterima." }
```

### B. Alur: Ambil Jadwal

```
ESP32 → GET /api/esp32/jadwal.php

Response:
{
    "success": true,
    "pakan_otomatis": 1,
    "jadwal": [
        { "id": 1, "nama": "Pagi", "jam": "08:00:00", "sudut_servo": 90 }
    ],
    "server_time": "2024-01-01 08:00:00"
}
```

### C. Alur: Cek Perintah Feed Now

```
ESP32 → GET /api/esp32/perintah.php

Response (ada perintah):
{
    "success": true,
    "ada_perintah": true,
    "perintah": {
        "id": 5,
        "sudut_servo": 90,
        "sumber": "dashboard",
        "created_at": "2024-01-01 10:00:00"
    }
}

Response (tidak ada):
{ "success": true, "ada_perintah": false }
```

### D. Alur: Lapor Status Pakan Manual

```
ESP32 → POST /api/esp32/status_pakan.php

Body JSON (berhasil):
{
    "perintah_id": 5,
    "sudut_servo": 90,
    "status": "executed",
    "alasan": ""
}

Body JSON (ditolak):
{
    "perintah_id": 5,
    "sudut_servo": 90,
    "status": "rejected",
    "alasan": "Jeda minimum belum terpenuhi"
}
```

### E. Alur: Lapor Status Pakan Jadwal

```
ESP32 → POST /api/esp32/status_pakan.php

Body JSON:
{
    "jadwal_id": 1,
    "sudut_servo": 90,
    "status": "berhasil",
    "alasan": ""
}
```

### F. Alur: Push Button Manual

```
ESP32 → POST /api/esp32/status_pakan.php

Body JSON:
{
    "sumber": "push_button",
    "sudut_servo": 90,
    "status": "berhasil",
    "alasan": ""
}
```

### Interval Polling yang Disarankan

| Endpoint | Interval |
|---|---|
| Kirim sensor | Setiap 10 detik |
| Ambil jadwal | Setiap 60 detik |
| Ambil pengaturan | Setiap 60 detik |
| Cek perintah Feed Now | Setiap 5 detik |
| Heartbeat | Setiap 30 detik |

---

## 5. PANDUAN TUNNELING (DEMO SEMENTARA)

Jika server masih XAMPP dan ingin diakses ESP32 dari internet, gunakan:

### Opsi A — Ngrok

```bash
# Install ngrok (https://ngrok.com)
ngrok http 80

# Output:
# Forwarding https://abc123.ngrok.io → localhost:80

# URL API ESP32:
# https://abc123.ngrok.io/AquaSense/api/esp32/sensor.php
```

### Opsi B — Cloudflare Tunnel

```bash
cloudflared tunnel --url http://localhost:80
```

Update URL di firmware ESP32:
```cpp
const char* serverUrl = "https://abc123.ngrok.io/AquaSense";
```

---

## 6. PANDUAN DEPLOYMENT SERVER ONLINE

### Persyaratan Server

- PHP 7.4+ (disarankan PHP 8.1+)
- MySQL 5.7+ atau MariaDB 10.3+
- Apache/Nginx dengan mod_rewrite
- HTTPS (SSL/TLS)

### Langkah Deploy

1. **Upload** semua file ke hosting (via FTP/SFTP)
2. **Import** `database/aquasense_database.sql` ke MySQL server
3. **Edit** `config/database.php` dengan kredensial database server
4. **Edit** `config/app.php`:
   ```php
   define('APP_URL', 'https://yourdomain.com');
   define('DEBUG_MODE', false);
   ```
5. **Set** token ESP32 yang kuat di `config/app.php`:
   ```php
   define('ESP32_API_TOKEN', 'TOKEN_AMAN_PANJANG_RANDOM');
   ```
6. **Update** URL di firmware ESP32:
   ```cpp
   const char* serverUrl = "https://yourdomain.com";
   const char* apiToken  = "TOKEN_AMAN_PANJANG_RANDOM";
   ```

### Keamanan Produksi

- Hapus folder `tools/` setelah setup
- Set `DEBUG_MODE = false`
- Gunakan kredensial database yang kuat
- Aktifkan HTTPS
- Ganti `ESP32_API_TOKEN` ke string random panjang
- Ganti password admin default

---

## 7. SKENARIO PENGUJIAN

### Uji 1 — Login

| Langkah | Hasil yang Diharapkan |
|---|---|
| Buka `http://localhost/AquaSense` | Redirect ke login |
| Masukkan username/password salah | Error "Username atau password salah" |
| Masukkan admin/aquasense123 | Redirect ke Beranda |
| Buka URL halaman langsung tanpa login | Redirect ke login |

### Uji 2 — Sensor Dashboard

| Langkah | Hasil yang Diharapkan |
|---|---|
| Kirim POST sensor.php dengan data valid | `{"success": true}` |
| Buka halaman Sensor | Tampil nilai sensor terbaru |
| Tunggu >120 detik tanpa kirim data | Status berubah ke "Offline" |
| Kirim data dengan suhu=35 | Notifikasi "Suhu Tinggi" muncul |

### Uji 3 — Feed Now

| Langkah | Hasil yang Diharapkan |
|---|---|
| Klik "Beri Pakan Sekarang" (kuota tersedia) | Status berubah "Menunggu konfirmasi" |
| POST status_pakan.php dengan status=executed | Status berubah "Berhasil" |
| Klik Feed Now saat jeda < 240 menit | Tombol disabled + countdown |
| Klik Feed Now setelah 2x pakan hari ini | Pesan "Kuota habis" |

### Uji 4 — Jadwal CRUD

| Langkah | Hasil yang Diharapkan |
|---|---|
| Tambah jadwal baru jam 08:00 | Jadwal tersimpan |
| Tambah jadwal lain jam 08:00 | Error "Jadwal sudah ada" |
| Edit jadwal → nonaktifkan | Status berubah "Nonaktif" |
| GET jadwal.php dari ESP32 | Jadwal nonaktif tidak muncul |
| Hapus jadwal | Jadwal hilang, riwayat tetap ada |

### Uji 5 — Notifikasi

| Langkah | Hasil yang Diharapkan |
|---|---|
| Kirim sensor dengan ADC=2000 | Badge notifikasi bertambah |
| Klik notifikasi | Notifikasi ditandai dibaca |
| Klik "Tandai semua dibaca" | Badge menghilang |

---

## 8. TABEL DATABASE

| Tabel | Keterangan |
|---|---|
| `monitoring` | Data sensor dari ESP32 |
| `jadwal_pakan` | Konfigurasi jadwal pemberian pakan |
| `pengaturan` | Konfigurasi global sistem |
| `perintah_pakan` | Antrean Feed Now dari dashboard |
| `riwayat_pakan` | Log semua pemberian pakan |
| `riwayat_peringatan` | Log peringatan kondisi sensor |
| `admin_users` | Akun admin dashboard |

| View | Keterangan |
|---|---|
| `v_kuota_manual_hari_ini` | Sisa kuota pemberian manual hari ini |
| `v_pakan_terakhir` | Waktu & menit sejak pakan terakhir |

---

## 9. FIRMWARE ESP32 — Catatan Integrasi

Kontrak API ini dirancang untuk diimplementasikan di firmware ESP32. Poin yang harus dikonfigurasi:

```cpp
// config ESP32 firmware
const char* WIFI_SSID     = "NamaWiFi";
const char* WIFI_PASSWORD = "PasswordWiFi";
const char* SERVER_URL    = "https://yourdomain.com/AquaSense";
const char* API_TOKEN     = "AQUASENSE_ESP32_TOKEN_2024";

// Interval polling (ms)
const int SENSOR_INTERVAL    = 10000;   // 10 detik
const int JADWAL_INTERVAL    = 60000;   // 60 detik
const int PERINTAH_INTERVAL  =  5000;   // 5 detik
const int HEARTBEAT_INTERVAL = 30000;   // 30 detik
```

Semua request harus menyertakan header:
```
X-API-Token: AQUASENSE_ESP32_TOKEN_2024
Content-Type: application/json
```

ESP32 adalah pengendali hardware utama. Server PHP hanya menyimpan data dan antrean perintah. ESP32 wajib memvalidasi ulang aturan pakan sebelum menggerakkan servo.
