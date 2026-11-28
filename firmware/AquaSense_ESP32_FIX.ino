#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>

// =====================================
// WIFI DAN SERVER AQUASENSE
// =====================================

const char *ssid = "niea";
const char *password = "tebakberapa";

// GANTI IP INI DENGAN IP LAPTOP YANG MENJALANKAN XAMPP
const char *SERVER_URL = "http://10.123.120.23/AquaSense"; // GANTI dengan IPv4 laptop yang menjalankan XAMPP
// url disesuaikan dengan IPv4 di device, harus sama dan disesuaikan jg sama code yg ada di config

const char *API_TOKEN = "AQUASENSE_ESP32_TOKEN_2024";

const char *serverNTP = "pool.ntp.org";
const long GMT_OFFSET = 7 * 3600;
const int DAYLIGHT_OFFSET = 0;

// =====================================
// PIN ESP32
// =====================================

#define PIN_DS18B20 4
#define PIN_TURBIDITY 34

#define PIN_TRIG 5
#define PIN_ECHO 18

#define PIN_SERVO 13
#define PIN_BUZZER 14
#define PIN_LED 23
#define PIN_BUTTON 19

// =====================================
// [REVISI] POLARITAS BUZZER & LED
// =====================================
// true  = buzzer bunyi saat pin HIGH (buzzer aktif biasa / transistor NPN)
// false = buzzer bunyi saat pin LOW  (modul buzzer active-low)
// Jika buzzer tidak bunyi saat bahaya tetapi bunyi saat normal, ubah ke false.
const bool BUZZER_AKTIF_HIGH = true;

// Pilih sesuai jenis komponen:
// false = buzzer aktif (cukup HIGH/LOW)
// true  = buzzer pasif (memerlukan frekuensi/tone)
const bool BUZZER_PASIF = false;
const unsigned int FREKUENSI_BUZZER = 2000;

void setLED(bool nyala)
{
  digitalWrite(PIN_LED, nyala ? HIGH : LOW);
}

void setBuzzer(bool bunyi)
{
  if (BUZZER_PASIF)
  {
    // Buzzer pasif harus diberi sinyal frekuensi agar menghasilkan suara.
    if (bunyi)
    {
      tone(PIN_BUZZER, FREKUENSI_BUZZER);
    }
    else
    {
      noTone(PIN_BUZZER);
    }
  }
  else
  {
    // Buzzer aktif menggunakan level HIGH/LOW.
    if (BUZZER_AKTIF_HIGH)
    {
      digitalWrite(PIN_BUZZER, bunyi ? HIGH : LOW);
    }
    else
    {
      digitalWrite(PIN_BUZZER, bunyi ? LOW : HIGH);
    }
  }
}

// =====================================
// [REVISI] WAKTU SAAT INI (NTP, WIB)
// =====================================

String waktuSekarang(bool denganTanggal)
{

  struct tm w;

  // timeout singkat agar program tidak tertahan lama
  if (!getLocalTime(&w, 200))
  {
    return denganTanggal ? "waktu belum sinkron" : "--:--:--";
  }

  char buf[24];

  if (denganTanggal)
  {
    strftime(buf, sizeof(buf), "%d/%m/%Y %H:%M:%S", &w);
  }
  else
  {
    strftime(buf, sizeof(buf), "%H:%M:%S", &w);
  }

  return String(buf);
}

// =====================================
// LCD
// =====================================

LiquidCrystal_I2C lcd(0x27, 16, 2);

// =====================================
// SENSOR DAN SERVO
// =====================================

OneWire oneWire(PIN_DS18B20);
DallasTemperature sensorSuhu(&oneWire);

Servo servoPakan;

// =====================================
// BATAS KONDISI
// =====================================

const float BATAS_SUHU_MIN = 24.0;
const float BATAS_SUHU_MAX = 30.0;

const int BATAS_AGAK_KERUH = 2301;
const int BATAS_KERUH = 2300;

const float BATAS_PAKAN_HABIS = 10.0;

// =====================================
// PARAMETER PAKAN
// =====================================

const unsigned long DURASI_BUKA_SERVO = 1000;

const unsigned long JEDA_MINIMUM =
    240UL * 60UL * 1000UL;

const int BATAS_PAKAN_MANUAL = 2;

const unsigned long INTERVAL_SENSOR = 2000;
const unsigned long INTERVAL_KIRIM = INTERVAL_SENSOR; // kirim data setiap 2 detik, sama dengan pembacaan sensor
const unsigned long INTERVAL_API = 10000;
const unsigned long INTERVAL_JADWAL = 30000;

const unsigned long COOLDOWN_NOTIFIKASI =
    10UL * 60UL * 1000UL;

// =====================================
// VARIABEL SENSOR
// =====================================

float suhuAir = -127.0;
float jarakPakan = -1;
int nilaiTurbidity = 0;

bool suhuValid = false;
bool turbValid = true;
bool jarakValid = false;

// =====================================
// VARIABEL PAKAN
// =====================================

unsigned long waktuPemberianTerakhir = 0;

// [REVISI] Info pemberian pakan terakhir (untuk ditampilkan)
String teksPakanTerakhir = "Belum ada";

int jumlahPakanManual = 0;
int hariTerakhir = -1;

bool buzzerPakanAktif = true;
bool pakanOtomatisAktif = true;

bool jadwalSudahDiambil = false;

struct JadwalPakan
{
  int id;
  int jam;
  int menit;
  int sudut;
  int hariEksekusi;
  int menitEksekusi;
};

JadwalPakan daftarJadwal[10];
int jumlahJadwal = 0;

// =====================================
// VARIABEL WAKTU
// =====================================

unsigned long waktuBacaSensor = 0;
unsigned long waktuKirimSensor = 0;
unsigned long waktuAmbilAPI = 0;
unsigned long waktuAmbilJadwal = 0;
unsigned long waktuHeartbeat = 0;
unsigned long waktuCekJadwal = 0; // [REVISI] timer pengecekan jadwal pakan

unsigned long waktuNotifikasiTerakhir = 0;

bool statusNotifikasiSebelumnya = false;

// =====================================
// VARIABEL SERVER
// =====================================

bool serverTersambung = false;

// =====================================
// FUNGSI MEMBANGUN URL
// =====================================

String buatURL(String endpoint)
{
  return String(SERVER_URL) + endpoint;
}

// =====================================
// FUNGSI HTTP REQUEST
// =====================================

bool httpRequest(
    String endpoint,
    String method,
    String payload,
    String &response)
{

  if (WiFi.status() != WL_CONNECTED)
  {
    Serial.println("[API ERROR] WiFi terputus.");
    serverTersambung = false;
    return false;
  }

  HTTPClient http;

  String url = buatURL(endpoint);

  http.begin(url);

  http.setTimeout(5000);

  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Token", API_TOKEN);

  int httpCode = -1;

  if (method == "POST")
  {
    httpCode = http.POST(payload);
  }
  else
  {
    httpCode = http.GET();
  }

  if (httpCode > 0)
  {

    response = http.getString();

    Serial.print("[API] ");
    Serial.print(endpoint);
    Serial.print(" : ");
    Serial.println(httpCode);

    if (httpCode >= 200 && httpCode < 300)
    {
      serverTersambung = true;
      http.end();
      return true;
    }

    Serial.println("[API ERROR] Respons server tidak berhasil.");
  }
  else
  {

    Serial.print("[API ERROR] ");
    Serial.println(http.errorToString(httpCode));
  }

  serverTersambung = false;

  http.end();

  return false;
}

// =====================================
// FUNGSI MEMBACA SENSOR SUHU
// =====================================

float bacaSuhu()
{

  sensorSuhu.requestTemperatures();

  float suhu = sensorSuhu.getTempCByIndex(0);

  if (
      suhu == DEVICE_DISCONNECTED_C ||
      suhu == -127.0)
  {

    Serial.println("[ERROR] DS18B20 tidak terdeteksi!");

    suhuValid = false;

    return -127.0;
  }

  suhuValid = true;

  return suhu;
}

// =====================================
// FUNGSI MEMBACA HC-SR04
// =====================================

float bacaJarak()
{

  float hasil[5];

  int jumlah = 0;

  for (int i = 0; i < 5; i++)
  {

    digitalWrite(PIN_TRIG, LOW);
    delayMicroseconds(2);

    digitalWrite(PIN_TRIG, HIGH);
    delayMicroseconds(10);

    digitalWrite(PIN_TRIG, LOW);

    long durasi = pulseIn(PIN_ECHO, HIGH, 30000);

    if (durasi > 0)
    {

      float jarak = durasi * 0.0343 / 2.0;

      if (jarak >= 2 && jarak <= 400)
      {

        hasil[jumlah] = jarak;
        jumlah++;
      }
    }

    delay(20);
  }

  if (jumlah == 0)
  {

    jarakValid = false;

    return -1;
  }

  // Urutkan data untuk mendapatkan median

  for (int i = 0; i < jumlah - 1; i++)
  {

    for (int j = i + 1; j < jumlah; j++)
    {

      if (hasil[i] > hasil[j])
      {

        float temp = hasil[i];

        hasil[i] = hasil[j];

        hasil[j] = temp;
      }
    }
  }

  jarakValid = true;

  return hasil[jumlah / 2];
}

// =====================================
// FUNGSI MEMBACA SEMUA SENSOR
// =====================================

void bacaSensor()
{

  suhuAir = bacaSuhu();

  nilaiTurbidity = analogRead(PIN_TURBIDITY);

  turbValid = true;

  jarakPakan = bacaJarak();

  Serial.println();
  Serial.println("========== DATA SENSOR ==========");

  Serial.print("Suhu air: ");

  if (suhuValid)
  {
    Serial.print(suhuAir);
    Serial.println(" C");
  }
  else
  {
    Serial.println("Sensor Error");
  }

  Serial.print("Turbidity: ");
  Serial.println(nilaiTurbidity);

  Serial.print("Jarak pakan: ");

  if (jarakValid)
  {
    Serial.print(jarakPakan);
    Serial.println(" cm");
  }
  else
  {
    Serial.println("Tidak valid");
  }

  // [REVISI] waktu pemberian pakan terakhir
  Serial.print("Pakan terakhir: ");
  Serial.println(teksPakanTerakhir);

  Serial.println("================================");

  tampilkanLCD();
}

// =====================================
// LCD
// =====================================

void tampilkanLCD()
{

  lcd.setCursor(0, 0);

  if (!suhuValid)
  {

    lcd.print("Suhu: Sensor Err");
  }
  else
  {

    lcd.print("Suhu:");
    lcd.print(suhuAir, 1);
    lcd.print((char)223);
    lcd.print("C     ");
  }

  lcd.setCursor(0, 1);

  lcd.print("T:");
  lcd.print(nilaiTurbidity);

  lcd.print(" P:");

  if (!jarakValid)
  {

    lcd.print("Error");
  }
  else
  {

    lcd.print(jarakPakan, 1);
    lcd.print("cm");
  }

  lcd.print("   ");
}

// =====================================
// KIRIM DATA SENSOR KE PHP
// =====================================

void kirimDataSensor()
{

  StaticJsonDocument<256> doc;

  if (suhuValid)
  {
    doc["suhu"] = suhuAir;
  }
  else
  {
    doc["suhu"] = nullptr;
  }

  doc["turbidity_adc"] = nilaiTurbidity;

  if (jarakValid)
  {
    doc["jarak_pakan"] = jarakPakan;
  }
  else
  {
    doc["jarak_pakan"] = nullptr;
  }

  doc["suhu_valid"] = suhuValid ? 1 : 0;
  doc["turb_valid"] = turbValid ? 1 : 0;
  doc["jarak_valid"] = jarakValid ? 1 : 0;

  String payload;

  serializeJson(doc, payload);

  String response;

  httpRequest(
      "/api/esp32/sensor.php",
      "POST",
      payload,
      response);
}

// =====================================
// HEARTBEAT ESP32
// =====================================

void kirimHeartbeat()
{

  StaticJsonDocument<128> doc;

  doc["ip"] = WiFi.localIP().toString();
  doc["firmware"] = "1.0.0";

  String payload;

  serializeJson(doc, payload);

  String response;

  httpRequest(
      "/api/esp32/heartbeat.php",
      "POST",
      payload,
      response);
}

// =====================================
// AMBIL PENGATURAN DASHBOARD
// =====================================

void ambilPengaturan()
{

  String response;

  if (!httpRequest(
          "/api/esp32/pengaturan.php",
          "GET",
          "",
          response))
  {
    return;
  }

  StaticJsonDocument<512> doc;

  DeserializationError error =
      deserializeJson(doc, response);

  if (error)
  {

    Serial.println("[JSON ERROR] Pengaturan.");

    return;
  }

  if (doc["success"] != true)
  {
    return;
  }

  pakanOtomatisAktif =
      doc["pakan_otomatis"] | 1;

  buzzerPakanAktif =
      doc["buzzer_peringatan"] | 1;

  Serial.println("[INFO] Pengaturan berhasil diperbarui.");

  Serial.print("Pakan otomatis: ");
  Serial.println(pakanOtomatisAktif);

  Serial.print("Buzzer pakan: ");
  Serial.println(buzzerPakanAktif);
}

// =====================================
// AMBIL JADWAL DARI DASHBOARD
// =====================================

void ambilJadwal()
{

  String response;

  if (!httpRequest(
          "/api/esp32/jadwal.php",
          "GET",
          "",
          response))
  {
    return;
  }

  StaticJsonDocument<2048> doc;

  DeserializationError error =
      deserializeJson(doc, response);

  if (error)
  {

    Serial.println("[JSON ERROR] Jadwal.");

    return;
  }

  if (doc["success"] != true)
  {
    return;
  }

  pakanOtomatisAktif =
      doc["pakan_otomatis"] | 1;

  JsonArray jadwal = doc["jadwal"].as<JsonArray>();

  jumlahJadwal = 0;

  for (JsonObject item : jadwal)
  {

    if (jumlahJadwal >= 10)
    {
      break;
    }

    String jam = item["jam"] | "00:00";

    daftarJadwal[jumlahJadwal].id =
        item["id"] | 0;

    daftarJadwal[jumlahJadwal].jam =
        jam.substring(0, 2).toInt();

    daftarJadwal[jumlahJadwal].menit =
        jam.substring(3, 5).toInt();

    daftarJadwal[jumlahJadwal].sudut =
        item["sudut_servo"] | 90;

    jumlahJadwal++;
  }

  jadwalSudahDiambil = true;

  Serial.print("[INFO] Jumlah jadwal aktif: ");
  Serial.println(jumlahJadwal);
}

// =====================================
// MELAPORKAN STATUS PAKAN
// =====================================

void laporStatusPakan(
    String jenis,
    int id,
    int sudut,
    String status,
    String alasan = "")
{

  StaticJsonDocument<256> doc;

  if (jenis == "dashboard")
  {

    doc["perintah_id"] = id;

    doc["status"] =
        status == "berhasil" ? "executed" : "rejected";
  }
  else if (jenis == "jadwal")
  {

    doc["jadwal_id"] = id;
    doc["status"] = status;
  }
  else
  {

    doc["sumber"] = "push_button";
    doc["status"] = status;
  }

  doc["sudut_servo"] = sudut;
  doc["alasan"] = alasan;

  String payload;

  serializeJson(doc, payload);

  String response;

  httpRequest(
      "/api/esp32/status_pakan.php",
      "POST",
      payload,
      response);
}

// =====================================
// MEMERIKSA JEDA MINIMUM
// =====================================

bool bolehMemberiPakan()
{

  if (waktuPemberianTerakhir == 0)
  {
    return true;
  }

  if (
      millis() - waktuPemberianTerakhir >=
      JEDA_MINIMUM)
  {
    return true;
  }

  Serial.println(
      "[PERINGATAN] Jeda minimum 240 menit belum terpenuhi.");

  return false;
}

// =====================================
// MENJALANKAN SERVO
// =====================================

bool beriPakan(
    int sudut,
    String sumber,
    int id = 0)
{

  // Validasi sudut servo
  if (
      sudut != 45 &&
      sudut != 90 &&
      sudut != 135 &&
      sudut != 180)
  {
    Serial.println("[ERROR] Sudut servo tidak valid.");
    return false;
  }

  // Pengecekan jeda 4 jam hanya untuk
  // push button dan Feed Now dashboard.
  // Pakan otomatis dari jadwal tidak dibatasi jeda 4 jam.
  if (sumber != "jadwal" && !bolehMemberiPakan())
  {

    Serial.println("[PERINGATAN] Pemberian pakan ditolak karena jeda 4 jam belum terpenuhi.");

    laporStatusPakan(
        sumber,
        id,
        sudut,
        "ditolak",
        "Jeda minimum 240 menit belum terpenuhi.");

    return false;
  }

  Serial.println();
  Serial.println("================================");
  Serial.println("       PEMBERIAN PAKAN");
  Serial.println("================================");

  lcd.clear();

  lcd.setCursor(0, 0);
  lcd.print("MEMBERI PAKAN");

  lcd.setCursor(0, 1);
  lcd.print("Servo membuka");

  Serial.print("Sudut servo: ");
  Serial.println(sudut);

  Serial.print("Sumber pakan: ");
  Serial.println(sumber);

  // Membuka servo
  servoPakan.write(sudut);

  delay(DURASI_BUKA_SERVO);

  // Menutup servo
  servoPakan.write(0);

  delay(1000);

  // Mencatat waktu pemberian pakan
  waktuPemberianTerakhir = millis();

  String jamPakan = waktuSekarang(false);
  String waktuLengkap = waktuSekarang(true);

  teksPakanTerakhir =
      waktuLengkap + " (" + sumber + ", " +
      String(sudut) + " derajat)";

  Serial.println("[INFO] Pemberian pakan selesai.");

  Serial.print("[WAKTU PAKAN] ");
  Serial.println(waktuLengkap);

  Serial.print("Sumber: ");
  Serial.print(sumber);
  Serial.print(" | Sudut: ");
  Serial.println(sudut);

  lcd.clear();

  lcd.setCursor(0, 0);
  lcd.print("Pakan selesai!");

  lcd.setCursor(0, 1);
  lcd.print("Jam ");
  lcd.print(jamPakan);

  // Melaporkan status pemberian pakan ke server
  laporStatusPakan(
      sumber,
      id,
      sudut,
      "berhasil");

  delay(3000);

  tampilkanLCD();

  return true;
}

// =====================================
// PAKAN MANUAL PUSH BUTTON
// =====================================

void pakanManual()
{

  if (jumlahPakanManual >= BATAS_PAKAN_MANUAL)
  {

    Serial.println(
        "[PERINGATAN] Kuota manual 2 kali sehari habis.");

    lcd.clear();

    lcd.setCursor(0, 0);
    lcd.print("Batas pakan");

    lcd.setCursor(0, 1);
    lcd.print("manual tercapai");

    delay(1500);

    tampilkanLCD();

    return;
  }

  if (!bolehMemberiPakan())
  {

    lcd.clear();

    lcd.setCursor(0, 0);
    lcd.print("Tunggu minimal");

    lcd.setCursor(0, 1);
    lcd.print("4 jam");

    delay(1500);

    tampilkanLCD();

    return;
  }

  if (beriPakan(90, "push_button"))
  {

    jumlahPakanManual++;

    Serial.print("Pakan manual hari ini: ");
    Serial.println(jumlahPakanManual);
  }
}

// =====================================
// PAKAN OTOMATIS DARI JADWAL
// =====================================

void pakanOtomatis()
{

  if (!pakanOtomatisAktif)
  {
    return;
  }

  if (!jadwalSudahDiambil)
  {
    return;
  }

  struct tm waktu;

  if (!getLocalTime(&waktu))
  {
    return;
  }

  int menitSekarang =
      waktu.tm_hour * 60 + waktu.tm_min;

  for (int i = 0; i < jumlahJadwal; i++)
  {

    if (
        waktu.tm_hour == daftarJadwal[i].jam &&
        waktu.tm_min == daftarJadwal[i].menit)
    {

      if (
          daftarJadwal[i].hariEksekusi == waktu.tm_yday &&
          daftarJadwal[i].menitEksekusi == menitSekarang)
      {
        continue;
      }

      daftarJadwal[i].hariEksekusi = waktu.tm_yday;
      daftarJadwal[i].menitEksekusi = menitSekarang;

      Serial.println("[JADWAL] Waktu pemberian pakan.");

      beriPakan(
          daftarJadwal[i].sudut,
          "jadwal",
          daftarJadwal[i].id);
    }
  }
}

// =====================================
// MENGAMBIL PERINTAH FEED NOW
// =====================================

void ambilPerintah()
{

  String response;

  if (!httpRequest(
          "/api/esp32/perintah.php",
          "GET",
          "",
          response))
  {
    return;
  }

  StaticJsonDocument<512> doc;

  DeserializationError error =
      deserializeJson(doc, response);

  if (error)
  {

    Serial.println("[JSON ERROR] Feed Now.");

    return;
  }

  if (doc["success"] != true)
  {
    return;
  }

  bool adaPerintah =
      doc["ada_perintah"] | false;

  if (!adaPerintah)
  {
    return;
  }

  JsonObject perintah =
      doc["perintah"].as<JsonObject>();

  int id = perintah["id"] | 0;

  int sudut =
      perintah["sudut_servo"] | 90;

  Serial.println("[SERVER] Perintah Feed Now diterima.");

  if (id <= 0)
  {
    return;
  }

  if (beriPakan(sudut, "dashboard", id))
  {

    Serial.println("[SERVER] Feed Now berhasil dijalankan.");
  }
  else
  {

    Serial.println("[SERVER] Feed Now ditolak.");
  }
}

// =====================================
// RESET PAKAN HARIAN
// =====================================

void periksaHari()
{

  struct tm waktu;

  if (!getLocalTime(&waktu))
  {
    return;
  }

  if (hariTerakhir != waktu.tm_yday)
  {

    hariTerakhir = waktu.tm_yday;

    jumlahPakanManual = 0;

    Serial.println(
        "[INFO] Kuota pakan manual direset.");
  }
}

// =====================================
// SISTEM PERINGATAN
// =====================================

void periksaKondisi()
{

  bool bahayaSuhu = false;
  bool bahayaTurbidity = false;
  bool bahayaPakan = false;

  Serial.println();
  Serial.println("======= STATUS AQUARIUM =======");

  // ---------------------------------
  // SUHU
  // ---------------------------------

  if (!suhuValid)
  {

    Serial.println("[ERROR] Sensor suhu tidak terdeteksi!");
  }
  else if (
      suhuAir < BATAS_SUHU_MIN ||
      suhuAir > BATAS_SUHU_MAX)
  {

    Serial.println("[BAHAYA] Suhu air di luar batas!");

    bahayaSuhu = true;
  }
  else
  {

    Serial.println("[NORMAL] Suhu air normal.");
  }

  // ---------------------------------
  // TURBIDITY
  // ---------------------------------

  Serial.print("Kekeruhan air: ");
  Serial.println(nilaiTurbidity);

  if (nilaiTurbidity <= BATAS_KERUH)
  {

    Serial.println("[BAHAYA] Air keruh!");

    bahayaTurbidity = true;
  }
  else if (
      nilaiTurbidity >= BATAS_AGAK_KERUH &&
      nilaiTurbidity <= 3150)
  {

    Serial.println("[PERINGATAN] Air agak keruh!");
  }
  else
  {

    Serial.println("[NORMAL] Kekeruhan air normal.");
  }

  // ---------------------------------
  // PAKAN
  // ---------------------------------

  Serial.print("Jarak ke pakan: ");

  if (!jarakValid)
  {

    Serial.println("Tidak valid");
  }
  else
  {

    Serial.print(jarakPakan);
    Serial.println(" cm");

    if (jarakPakan >= BATAS_PAKAN_HABIS)
    {

      Serial.println("[BAHAYA] PAKAN MENIPIS!");

      bahayaPakan = true;
    }
    else
    {

      Serial.println("[NORMAL] Pakan masih tersedia.");
    }
  }

  // ---------------------------------
  // LED DAN BUZZER
  // ---------------------------------

  bool bahaya =
      bahayaSuhu ||
      bahayaTurbidity ||
      bahayaPakan;

  bool buzzerAktif =
      bahayaSuhu ||
      bahayaTurbidity ||
      (bahayaPakan && buzzerPakanAktif);

  // [REVISI] LED & buzzer lewat fungsi agar polaritas mudah diatur
  setLED(bahaya);

  setBuzzer(buzzerAktif);

  Serial.print("[OUTPUT] LED: ");
  Serial.print(bahaya ? "ON" : "OFF");
  Serial.print(" | Buzzer: ");
  Serial.print(buzzerAktif ? "ON" : "OFF");

  if (bahayaPakan && !buzzerPakanAktif)
  {
    Serial.print(" (buzzer pakan dimatikan dari dashboard)");
  }

  Serial.println();

  // ---------------------------------
  // COOLDOWN NOTIFIKASI
  // ---------------------------------

  unsigned long sekarang = millis();

  if (bahaya)
  {

    if (
        !statusNotifikasiSebelumnya ||
        sekarang - waktuNotifikasiTerakhir >=
            COOLDOWN_NOTIFIKASI)
    {

      waktuNotifikasiTerakhir = sekarang;

      Serial.println(
          "[NOTIFIKASI] Kondisi peringatan aktif.");
    }

    statusNotifikasiSebelumnya = true;
  }
  else
  {

    statusNotifikasiSebelumnya = false;

    Serial.println("[NORMAL] Tidak ada peringatan.");
  }

  Serial.println("===============================");
}

// =====================================
// RECONNECT WIFI
// =====================================

void periksaWiFi()
{

  if (WiFi.status() != WL_CONNECTED)
  {

    Serial.println("[WIFI] Mencoba menyambungkan ulang...");

    WiFi.disconnect();
    WiFi.begin(ssid, password);

    // Jika WiFi tersambung kembali, sinkronkan ulang waktu WIB.
    unsigned long mulaiTunggu = millis();
    while (WiFi.status() != WL_CONNECTED &&
           millis() - mulaiTunggu < 10000)
    {
      delay(250);
    }

    if (WiFi.status() == WL_CONNECTED)
    {
      configTime(GMT_OFFSET, DAYLIGHT_OFFSET, serverNTP);
      struct tm waktu;
      if (getLocalTime(&waktu, 10000))
      {
        Serial.println("[NTP] Waktu berhasil disinkronkan ulang.");
      }
      else
      {
        Serial.println("[NTP ERROR] Sinkronisasi ulang gagal.");
      }
    }
  }
}

// =====================================
// SETUP
// =====================================

void setup()
{

  Serial.begin(115200);

  delay(1000);

  // SENSOR SUHU

  sensorSuhu.begin();

  sensorSuhu.setResolution(12);

  // HC-SR04

  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);

  // LED DAN BUZZER

  pinMode(PIN_LED, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);

  setLED(false);
  setBuzzer(false);

  // [REVISI] tes singkat LED & buzzer saat alat menyala
  setLED(true);
  setBuzzer(true);
  delay(300);
  setLED(false);
  setBuzzer(false);

  // PUSH BUTTON

  pinMode(PIN_BUTTON, INPUT_PULLUP);

  // Inisialisasi penanda eksekusi jadwal.
  // Nilai -1 mencegah jadwal pukul 00:00 dianggap sudah dijalankan.
  for (int i = 0; i < 10; i++)
  {
    daftarJadwal[i].hariEksekusi = -1;
    daftarJadwal[i].menitEksekusi = -1;
  }

  // SERVO

  servoPakan.setPeriodHertz(50);

  servoPakan.attach(PIN_SERVO, 500, 2400);

  servoPakan.write(0);

  // LCD

  Wire.begin(21, 22);

  lcd.init();

  lcd.backlight();

  lcd.setCursor(0, 0);
  lcd.print("SMART AQUARIUM");

  lcd.setCursor(0, 1);
  lcd.print("System Starting");

  Serial.println();
  Serial.println("================================");
  Serial.println("   SMART AQUARIUM FEEDER");
  Serial.println("================================");

  // WIFI

  WiFi.begin(ssid, password);

  lcd.clear();

  lcd.setCursor(0, 0);
  lcd.print("Connecting WiFi");

  Serial.print("Menghubungkan WiFi");

  unsigned long batasWiFi = millis();

  while (
      WiFi.status() != WL_CONNECTED &&
      millis() - batasWiFi < 20000)
  {

    delay(500);

    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED)
  {

    Serial.println();
    Serial.println("[WIFI] Terhubung.");

    Serial.print("IP ESP32: ");
    Serial.println(WiFi.localIP());

    lcd.clear();

    lcd.setCursor(0, 0);
    lcd.print("WiFi Connected");

    // NTP

    configTime(
        GMT_OFFSET,
        DAYLIGHT_OFFSET,
        serverNTP);

    struct tm waktu;

    if (getLocalTime(&waktu, 10000))
    {

      Serial.println("[NTP] Waktu berhasil disinkronkan.");
    }
    else
    {

      Serial.println("[NTP ERROR] Sinkronisasi gagal.");
    }

    // AMBIL KONFIGURASI AWAL

    ambilPengaturan();

    ambilJadwal();

    kirimHeartbeat();
  }
  else
  {

    Serial.println();
    Serial.println("[WIFI ERROR] WiFi belum terhubung.");

    lcd.clear();

    lcd.setCursor(0, 0);
    lcd.print("WiFi Offline");
  }

  delay(1500);

  bacaSensor();

  periksaKondisi();

  Serial.println("[SYSTEM] Monitoring dimulai.");

  Serial.println("================================");
}

// =====================================
// LOOP UTAMA
// =====================================

void loop()
{

  unsigned long sekarang = millis();

  // ---------------------------------
  // WIFI
  // ---------------------------------

  if (WiFi.status() != WL_CONNECTED)
  {

    periksaWiFi();
  }

  // ---------------------------------
  // RESET HARIAN
  // ---------------------------------

  periksaHari();

  // ---------------------------------
  // PUSH BUTTON
  // ---------------------------------

  if (digitalRead(PIN_BUTTON) == LOW)
  {

    pakanManual();

    while (digitalRead(PIN_BUTTON) == LOW)
    {
      delay(20);
    }
  }

  // ---------------------------------
  // [REVISI] CEK JADWAL PAKAN OTOMATIS
  // ---------------------------------

  if (sekarang - waktuCekJadwal >= 1000)
  {

    waktuCekJadwal = sekarang;

    pakanOtomatis();
  }

  // ---------------------------------
  // BACA SENSOR SETIAP 2 DETIK
  // ---------------------------------

  if (
      sekarang - waktuBacaSensor >=
      INTERVAL_SENSOR)
  {

    waktuBacaSensor = sekarang;

    bacaSensor();

    periksaKondisi();
  }

  // ---------------------------------
  // KIRIM SENSOR SETIAP 2 DETIK (sama dengan interval pembacaan)
  // ---------------------------------

  if (
      WiFi.status() == WL_CONNECTED &&
      sekarang - waktuKirimSensor >=
          INTERVAL_KIRIM)
  {

    waktuKirimSensor = sekarang;

    kirimDataSensor();
  }

  // ---------------------------------
  // AMBIL PENGATURAN DAN FEED NOW
  // ---------------------------------

  if (
      WiFi.status() == WL_CONNECTED &&
      sekarang - waktuAmbilAPI >=
          INTERVAL_API)
  {

    waktuAmbilAPI = sekarang;

    ambilPengaturan();

    ambilPerintah();
  }

  // ---------------------------------
  // AMBIL JADWAL SETIAP 30 DETIK
  // ---------------------------------

  if (
      WiFi.status() == WL_CONNECTED &&
      sekarang - waktuAmbilJadwal >=
          INTERVAL_JADWAL)
  {

    waktuAmbilJadwal = sekarang;

    ambilJadwal();
  }

  // ---------------------------------
  // HEARTBEAT SETIAP 30 DETIK
  // ---------------------------------

  if (
      WiFi.status() == WL_CONNECTED &&
      sekarang - waktuHeartbeat >= 30000)
  {

    waktuHeartbeat = sekarang;

    kirimHeartbeat();
  }
}