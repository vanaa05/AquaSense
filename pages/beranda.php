<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pakanOtomatis  = getPengaturan('pakan_otomatis');
$buzzer         = getPengaturan('buzzer_peringatan');
$kuota          = getKuotaManualHariIni();
$sisaJeda       = getSisaJedaMenit();
$can            = canFeedNow();

$pageTitle  = 'Beranda';
$activePage = 'beranda';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4" data-page="beranda">

    <!-- ===== KOLOM KIRI ===== -->
    <div class="col-xl-8">

        <!-- HERO CARD -->
        <div class="aq-hero-card mb-4" id="heroCard">
            <div class="aq-hero-left">
                <div class="aq-hero-live" id="heroLive">
                    <span class="live-dot"></span>
                    <span>LIVE</span>
                </div>
                <h2 class="aq-hero-status-title" id="heroTitle">Memuat...</h2>
                <p class="aq-hero-status-sub" id="heroSub">Mengambil data sensor terbaru</p>
            </div>
            <div class="aq-hero-fish">🐟</div>
        </div>

        <!-- SENSOR SUMMARY -->
        <div class="aq-sensor-cards mb-4">
            <div class="aq-sensor-card" id="sensorSuhu">
                <div class="aq-sensor-icon suhu"><i class="bi bi-thermometer-half"></i></div>
                <div>
                    <div class="aq-sensor-value">—</div>
                    <div class="aq-sensor-label">Suhu Air</div>
                    <span class="aq-sensor-badge secondary">Memuat...</span>
                </div>
            </div>
            <div class="aq-sensor-card" id="sensorTurb">
                <div class="aq-sensor-icon turb"><i class="bi bi-water"></i></div>
                <div>
                    <div class="aq-sensor-value">—</div>
                    <div class="aq-sensor-label">Turbidity ADC</div>
                    <span class="aq-sensor-badge secondary">Memuat...</span>
                </div>
            </div>
            <div class="aq-sensor-card" id="sensorPakan">
                <div class="aq-sensor-icon pakan"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="aq-sensor-value">—</div>
                    <div class="aq-sensor-label">Jarak Pakan</div>
                    <span class="aq-sensor-badge secondary">Memuat...</span>
                </div>
            </div>
        </div>
        <div class="text-end mb-4" style="font-size:11px;color:var(--aq-muted);" id="sensorUpdated">—</div>

        <!-- JADWAL HARI INI -->
        <div class="aq-card">
            <div class="aq-card-header">
                <h3 class="aq-card-title"><i class="bi bi-calendar-check"></i>Jadwal Pakan Hari Ini</h3>
                <a href="<?= APP_URL ?>/pages/jadwal.php" class="aq-btn aq-btn-outline aq-btn-sm">Kelola Jadwal</a>
            </div>
            <div class="aq-card-body">
                <div id="jadwalHariIniList">
                    <p class="text-muted small text-center py-2">Memuat...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== KOLOM KANAN ===== -->
    <div class="col-xl-4">

        <!-- FEED NOW -->
        <div class="aq-card mb-4">
            <div class="aq-card-header">
                <h3 class="aq-card-title"><i class="bi bi-lightning-charge-fill"></i>Feed Now</h3>
            </div>
            <div class="aq-card-body">
                <div class="aq-feed-now">
                    <!-- Quota Info -->
                    <div class="aq-quota-info" id="feedNowQuota">
                        <span>Kuota hari ini: <strong>—</strong></span>
                        <span>Sisa: <strong>—</strong></span>
                    </div>

                    <!-- Countdown -->
                    <div class="aq-countdown" id="feedNowCountdown" style="display:none;">
                        <i class="bi bi-clock me-1"></i>Menghitung...
                    </div>

                    <!-- Status Pending -->
                    <div class="aq-feed-status" id="feedNowStatus" style="display:none;"></div>

                    <!-- Servo Selector -->
                    <div>
                        <div style="font-size:12px;font-weight:600;margin-bottom:8px;color:var(--aq-muted);">Sudut Servo</div>
                        <div class="aq-servo-selector">
                            <button class="aq-servo-btn" data-sudut="45">45°</button>
                            <button class="aq-servo-btn active" data-sudut="90">90°</button>
                            <button class="aq-servo-btn" data-sudut="135">135°</button>
                            <button class="aq-servo-btn" data-sudut="180">180°</button>
                        </div>
                    </div>

                    <!-- Feed Button -->
                    <button class="aq-feed-btn" id="feedNowBtn" disabled>
                        <i class="bi bi-lightning-charge-fill"></i>
                        <span class="btn-text">Beri Pakan Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- PERINGATAN PAKAN (BUZZER) -->
        <div class="aq-card mb-4">
            <div class="aq-card-header">
                <h3 class="aq-card-title"><i class="bi bi-bell-fill"></i>Peringatan Pakan</h3>
            </div>
            <div class="aq-card-body">
                <div class="aq-toggle-wrap">
                    <div class="aq-toggle-label">
                        <span class="aq-toggle-label-text">Buzzer Peringatan</span>
                        <span class="aq-toggle-label-sub">Aktifkan suara peringatan ESP32</span>
                    </div>
                    <label class="aq-toggle-switch">
                        <input type="checkbox" id="toggleBuzzer" <?= $buzzer == '1' ? 'checked' : '' ?>>
                        <span class="aq-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ATURAN FEED NOW INFO -->
        <div class="aq-card">
            <div class="aq-card-header">
                <h3 class="aq-card-title"><i class="bi bi-info-circle"></i>Aturan Feed Now</h3>
            </div>
            <div class="aq-card-body">
                <div class="aq-threshold-item">
                    <div class="aq-threshold-dot" style="background:var(--aq-primary);"></div>
                    <span class="aq-threshold-label">Jeda minimum</span>
                    <span class="aq-threshold-value">240 menit</span>
                </div>
                <div class="aq-threshold-item">
                    <div class="aq-threshold-dot" style="background:var(--aq-warning);"></div>
                    <span class="aq-threshold-label">Maks. per hari</span>
                    <span class="aq-threshold-value">2x pemberian</span>
                </div>
                <div class="aq-threshold-item">
                    <div class="aq-threshold-dot" style="background:var(--aq-success);"></div>
                    <span class="aq-threshold-label">Reset kuota</span>
                    <span class="aq-threshold-value">Pukul 00.00 WIB</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>document.body.dataset.page = 'beranda';</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
