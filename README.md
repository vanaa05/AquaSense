
# AquaSense
## Smart Aquarium Feeder & Monitoring System

AquaSense adalah sistem IoT untuk memantau kondisi aquarium dan membantu pengelolaan pemberian pakan ikan. Sistem menggunakan ESP32 sebagai pengendali perangkat, sensor untuk membaca kondisi aquarium, servo motor untuk mekanisme pemberian pakan, serta aplikasi web berbasis PHP dan MySQL untuk menampilkan data dan mengelola pengaturan.

Dokumentasi ini mencakup source code aplikasi web dan firmware ESP32.

---

## 1. Fitur Sistem

### Monitoring Aquarium
- Membaca suhu air menggunakan sensor DS18B20.
- Membaca tingkat kekeruhan air menggunakan turbidity sensor.
- Memantau ketersediaan pakan menggunakan sensor ultrasonik HC-SR04.
- Mengirim data sensor dari ESP32 ke server.
- Menampilkan data sensor terkini dan histori monitoring pada dashboard.
- Menampilkan status koneksi perangkat melalui mekanisme heartbeat.

### Pengelolaan Pemberian Pakan
- Pemberian pakan otomatis berdasarkan jadwal yang tersimpan di database.
- Pengaturan aktif/nonaktif pakan otomatis melalui dashboard.
- Pemberian pakan manual melalui fitur **Feed Now** pada dashboard.
- Pemberian pakan manual melalui push button pada perangkat.
- Pengaturan sudut servo untuk mekanisme pemberian pakan.
- Pembatasan pakan manual dan jeda minimum pemberian pakan.
- Pencatatan riwayat pemberian pakan.

### Peringatan dan Antarmuka
- LED sebagai indikator kondisi aquarium.
- Buzzer sebagai indikator peringatan.
- LCD I2C untuk menampilkan informasi perangkat.
- Notifikasi kondisi sensor pada dashboard.
- Pengaturan buzzer peringatan melalui dashboard.

---

## 2. Teknologi yang Digunakan

| Bagian | Teknologi |
|---|---|
| Mikrokontroler | ESP32 |
| Firmware | Arduino C++ |
| Backend Web | PHP |
| Database | MySQL |
| Frontend | HTML, CSS, JavaScript |
| Web Server Lokal | Apache (XAMPP) |
| Komunikasi Perangkat | Wi-Fi dan HTTP API |
| Format Pertukaran Data | JSON |

### Library Firmware

Firmware menggunakan library berikut:
- `Wire`
- `LiquidCrystal_I2C`
- `OneWire`
- `DallasTemperature`
- `ESP32Servo`
- `WiFi`
- `HTTPClient`
- `ArduinoJson`
- `time`

Pastikan library eksternal yang diperlukan sudah terpasang pada Arduino IDE.

---

## 3. Perangkat Keras dan Pin ESP32

Pemetaan pin berdasarkan firmware `AquaSense_ESP32_Revisi.ino`:

| Komponen | Pin ESP32 |
|---|---:|
| DS18B20 | GPIO 4 |
| Turbidity Sensor (analog) | GPIO 34 |
| HC-SR04 Trig | GPIO 5 |
| HC-SR04 Echo | GPIO 18 |
| Servo Motor | GPIO 13 |
| Buzzer | GPIO 14 |
| LED | GPIO 23 |
| Push Button | GPIO 19 |

Komponen tampilan menggunakan LCD I2C dengan alamat `0x27`.

**Catatan perakitan:**
- Pastikan seluruh komponen memiliki ground (GND) yang terhubung bersama.
- Perhatikan kebutuhan tegangan setiap modul.
- Pin Echo HC-SR04 umumnya menghasilkan sinyal 5 V; sesuaikan level tegangan agar aman untuk input ESP32.
- Pastikan catu daya servo memadai dan ground catu daya servo terhubung dengan ground ESP32.
- Konfirmasi kembali susunan kabel dengan rangkaian fisik yang digunakan.

---

## 4. Struktur Folder Project

Berikut struktur folder repository AquaSense:

```text
AquaSense/
├── api/
│   ├── dashboard/
│   │   ├── feed_now.php
│   │   ├── jadwal.php
│   │   ├── notifikasi.php
│   │   ├── pengaturan.php
│   │   ├── riwayat.php
│   │   └── sensor.php
│   └── esp32/
│       ├── heartbeat.php
│       ├── jadwal.php
│       ├── pengaturan.php
│       ├── perintah.php
│       ├── sensor.php
│       └── status_pakan.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── config/
│   ├── app.php
│   └── database.php
├── database/
│   ├── aquasense_database.sql
│   └── migration.sql
├── firmware/
│   └── AquaSense_ESP32_Revisi.ino
├── includes/
│   ├── auth.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── pages/
│   ├── beranda.php
│   ├── jadwal.php
│   └── sensor.php
├── tools/
│   └── generate_hash.php
├── .gitignore
├── .htaccess
├── index.php
├── login.php
├── logout.php
└── README.md
```

Repository terdiri dari source code aplikasi web, firmware ESP32, serta file database yang diperlukan untuk menjalankan sistem AquaSense.

---

## 5. Persyaratan Menjalankan Aplikasi Web

- Windows atau sistem operasi lain yang dapat menjalankan web server.
- XAMPP atau lingkungan server setara.
- Apache.
- MySQL atau MariaDB.
- PHP yang mendukung sintaks PHP 7.4 atau lebih baru.
- Browser web.

---

## 6. Instalasi Aplikasi Web dengan XAMPP

### Langkah 1 — Salin Folder Project

Salin folder `AquaSense` ke direktori:

```text
C:\xampp\htdocs\AquaSense
```

### Langkah 2 — Jalankan Layanan XAMPP

Buka XAMPP Control Panel, kemudian jalankan:
1. Apache
2. MySQL

### Langkah 3 — Buat dan Import Database

1. Buka `http://localhost/phpmyadmin`.
2. Pilih menu **Import** atau buat database bernama `iot`.
3. Import file `database/aquasense_database.sql`.
4. Pastikan proses import selesai tanpa error.

File SQL utama membuat database `iot` dan tabel-tabel yang dibutuhkan aplikasi.

File `database/migration.sql` disediakan untuk penyesuaian database yang sudah ada. Untuk instalasi baru, gunakan `aquasense_database.sql` terlebih dahulu. Jangan menjalankan migration secara otomatis tanpa memeriksa kondisi database.

### Langkah 4 — Periksa Konfigurasi Database

Buka `config/database.php` dan sesuaikan konfigurasi dengan MySQL lokal:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'iot');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

Nilai di atas merupakan konfigurasi bawaan pada source code. Ubah `DB_USER` dan `DB_PASS` jika konfigurasi MySQL berbeda.

### Langkah 5 — Periksa URL Aplikasi

Buka `config/app.php` dan sesuaikan `APP_URL` dengan alamat aplikasi:

```php
define('APP_URL', 'http://localhost/AquaSense');
```

### Langkah 6 — Akses Dashboard

Buka browser:

```text
http://localhost/AquaSense
```

`index.php` mengarahkan pengguna ke halaman dashboard Beranda.

**Catatan akses:** Pada source code yang diperiksa, `login.php` mengarahkan pengguna ke dashboard dan `requireLogin()` belum menerapkan pembatasan akses. Jangan menganggap aplikasi sudah memiliki autentikasi admin aktif. Batasi akses melalui jaringan tepercaya dan aktifkan autentikasi sebelum aplikasi dipublikasikan ke internet.

---

## 7. Konfigurasi Firmware ESP32

Firmware ESP32 tersedia pada folder:

```text
firmware/AquaSense_ESP32_Revisi.ino
```

Buka file tersebut menggunakan Arduino IDE.

### Langkah 1 — Atur Koneksi Wi-Fi

Sesuaikan variabel SSID dan password Wi-Fi pada firmware dengan jaringan yang akan digunakan.

Jangan mengunggah kredensial Wi-Fi asli ke repository publik.

### Langkah 2 — Atur Alamat Server

Firmware menggunakan alamat server lokal melalui variabel `SERVER_URL`.

Contoh format:

```cpp
const char* SERVER_URL = "http://ALAMAT_IPV4_LAPTOP/AquaSense";
```

Ganti `ALAMAT_IPV4_LAPTOP` dengan alamat IPv4 komputer yang menjalankan XAMPP. ESP32 dan laptop harus dapat saling berkomunikasi melalui jaringan yang sama.

Pastikan alamat server di firmware sesuai dengan konfigurasi aplikasi dan Apache dapat diakses dari perangkat ESP32.

### Langkah 3 — Samakan API Token

Firmware dan server menggunakan token API yang harus sama:
- Firmware: variabel `API_TOKEN`.
- Server: konstanta `ESP32_API_TOKEN` pada `config/app.php`.

Gunakan token milik sendiri dan jangan membagikan token produksi di repository publik. Jika token diganti, perbarui kedua sisi.

### Langkah 4 — Pilih Board dan Upload

1. Hubungkan ESP32 ke komputer.
2. Pilih board ESP32 yang sesuai di Arduino IDE.
3. Pilih port perangkat.
4. Pastikan library firmware sudah tersedia.
5. Compile dan upload firmware.
6. Buka Serial Monitor untuk memeriksa koneksi Wi-Fi, pembacaan sensor, dan komunikasi API.

---

## 8. Ambang Kondisi Sensor

Berdasarkan implementasi firmware:

| Parameter | Kondisi | Batas |
|---|---|---|
| Suhu Air | Rendah / di luar batas | Di bawah 24 °C |
| Suhu Air | Normal | 24–30 °C |
| Suhu Air | Tinggi / di luar batas | Di atas 30 °C |
| Turbidity ADC | Keruh | ≤ 2300 |
| Turbidity ADC | Agak keruh | 2301–3150 |
| Turbidity ADC | Normal | Di atas 3150 |
| Jarak Pakan | Pakan menipis | ≥ 10 cm |

Nilai turbidity merupakan pembacaan ADC. Interpretasi kondisi perlu disesuaikan dengan karakteristik sensor dan hasil kalibrasi pada rangkaian yang digunakan.

---

## 9. Aturan Pemberian Pakan

Konfigurasi pada firmware dan aplikasi mencakup:
- Durasi pembukaan servo: 1000 ms.
- Jeda minimum pemberian pakan: 240 menit.
- Batas pakan manual: 2 kali.
- Pakan otomatis mengikuti jadwal aktif yang diterima dari server.
- Feed Now dari dashboard diterima ESP32 melalui antrean perintah.
- Push button dapat digunakan untuk memicu pemberian pakan manual.

ESP32 memeriksa aturan pemberian pakan sebelum menjalankan mekanisme servo dan mengirimkan status pelaksanaan ke server.

---

## 10. Komunikasi API ESP32

Semua endpoint ESP32 menggunakan header token:

```http
X-API-Token: TOKEN_MILIK_ANDA
Content-Type: application/json
```

Ganti `TOKEN_MILIK_ANDA` dengan token yang sama pada firmware dan konfigurasi server.

### Daftar Endpoint ESP32

| Endpoint | Method | Fungsi |
|---|---|---|
| `/api/esp32/sensor.php` | POST | Mengirim data sensor |
| `/api/esp32/jadwal.php` | GET | Mengambil jadwal aktif |
| `/api/esp32/pengaturan.php` | GET | Mengambil pengaturan sistem |
| `/api/esp32/perintah.php` | GET | Memeriksa antrean Feed Now |
| `/api/esp32/status_pakan.php` | POST | Mengirim status pemberian pakan |
| `/api/esp32/heartbeat.php` | POST | Mengirim heartbeat perangkat |

Contoh alamat endpoint lokal:

```text
http://ALAMAT_IPV4_LAPTOP/AquaSense/api/esp32/sensor.php
```

### Format Data Sensor

ESP32 mengirim data JSON dengan field berikut:

```json
{
  "suhu": 26.5,
  "turbidity_adc": 3200,
  "jarak_pakan": 7.5,
  "suhu_valid": 1,
  "turb_valid": 1,
  "jarak_valid": 1
}
```

### Interval Komunikasi Firmware

| Aktivitas | Interval |
|---|---:|
| Pembacaan sensor | 2 detik |
| Pengiriman data sensor | 2 detik |
| Pengambilan pengaturan dan pemeriksaan Feed Now | 10 detik |
| Pengambilan jadwal | 30 detik |
| Heartbeat | 30 detik |

Interval tersebut merupakan nilai konfigurasi firmware saat diperiksa dan dapat berubah jika firmware direvisi.

---

## 11. API Dashboard

Endpoint dashboard digunakan oleh aplikasi web untuk mengambil dan mengelola data:

| Endpoint | Method | Fungsi |
|---|---|---|
| `/api/dashboard/sensor.php` | GET | Data sensor terkini dan histori |
| `/api/dashboard/jadwal.php` | GET, POST, PUT, DELETE | Pengelolaan jadwal |
| `/api/dashboard/pengaturan.php` | GET, POST | Pengaturan sistem |
| `/api/dashboard/feed_now.php` | GET, POST | Status dan permintaan Feed Now |
| `/api/dashboard/notifikasi.php` | GET, POST | Daftar dan status notifikasi |
| `/api/dashboard/riwayat.php` | GET | Riwayat pemberian pakan |

---

## 12. Struktur Database

Database yang digunakan bernama `iot`.

### Tabel

| Tabel | Fungsi |
|---|---|
| `monitoring` | Menyimpan data sensor |
| `jadwal_pakan` | Menyimpan jadwal pemberian pakan |
| `pengaturan` | Menyimpan konfigurasi sistem |
| `perintah_pakan` | Menyimpan antrean perintah Feed Now |
| `riwayat_pakan` | Menyimpan riwayat pemberian pakan |
| `riwayat_peringatan` | Menyimpan riwayat peringatan |
| `admin_users` | Menyimpan data akun admin |

### View

| View | Fungsi |
|---|---|
| `v_kuota_manual_hari_ini` | Mendukung perhitungan kuota pakan manual harian |
| `v_pakan_terakhir` | Menyediakan informasi pemberian pakan terakhir |

---

## 13. Troubleshooting

| Kendala | Hal yang Perlu Diperiksa |
|---|---|
| Dashboard tidak dapat dibuka | Status Apache dan alamat folder project |
| Koneksi database gagal | Status MySQL dan konfigurasi `config/database.php` |
| ESP32 tidak terhubung ke server | SSID, jaringan, alamat IPv4 laptop, firewall, dan Apache |
| API mengembalikan token tidak valid | Kesamaan token pada firmware dan `config/app.php` |
| Data sensor tidak tampil | Koneksi sensor, Serial Monitor, endpoint API, dan database |
| Status perangkat offline | Pengiriman heartbeat dan koneksi Wi-Fi |
| Servo tidak bergerak | Catu daya servo, kabel, pin GPIO 13, dan logika pemberian pakan |
| Buzzer tidak berbunyi | Polaritas buzzer dan konfigurasi `BUZZER_AKTIF_HIGH` serta `BUZZER_PASIF` |
| LCD tidak menampilkan informasi | Alamat I2C, koneksi SDA/SCL, dan catu daya LCD |

---

## 14. Catatan Keamanan dan Pengembangan

- Jangan menyimpan password Wi-Fi, kredensial database, atau token produksi di repository publik.
- Ganti token API sebelum digunakan pada jaringan publik.
- Batasi akses dashboard karena implementasi saat ini belum menerapkan autentikasi login aktif.
- Gunakan HTTPS dan konfigurasi server yang aman jika aplikasi dipublikasikan.
- Nonaktifkan `DEBUG_MODE` pada lingkungan produksi.
- Hapus atau batasi akses ke script bantuan setup setelah tidak diperlukan.
- Lakukan pengujian perangkat keras setelah perubahan firmware, khususnya sensor, servo, LED, dan buzzer.

---

## 15. Source Code

Source code AquaSense tersedia dalam repository ini dengan pembagian sebagai berikut:

| Folder/File | Keterangan |
|---|---|
| `api/` | Endpoint komunikasi antara aplikasi web dan ESP32 |
| `assets/` | File CSS dan JavaScript |
| `config/` | Konfigurasi aplikasi dan database |
| `database/` | File SQL untuk database |
| `firmware/` | Program ESP32 menggunakan Arduino C++ |
| `includes/` | Komponen dan fungsi pendukung aplikasi |
| `pages/` | Halaman dashboard |
| `tools/` | Script bantuan pengembangan |

Firmware utama:

```text
firmware/AquaSense_ESP32_Revisi.ino
```

Pastikan konfigurasi jaringan, alamat server, token API, dan pemetaan pin firmware sesuai dengan konfigurasi aplikasi web serta rangkaian perangkat yang digunakan.

---

**AquaSense — Smart Aquarium Feeder & Monitoring System**