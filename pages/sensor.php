<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle  = 'Sensor — Monitoring';
$activePage = 'sensor';
require_once __DIR__ . '/../includes/header.php';
?>

<div data-page="sensor">

    <!-- BARIS 1: BIG SENSOR CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="aq-sensor-big-card" id="bigSuhu">
                <div class="aq-sensor-big-icon" style="background:#fff7ed;color:#f97316;">
                    <i class="bi bi-thermometer-half"></i>
                </div>
                <div class="aq-sensor-big-value">—</div>
                <div class="aq-sensor-big-label">Suhu Air</div>
                <div class="aq-sensor-status-pill secondary">
                    <i class="bi bi-circle-fill" style="font-size:6px"></i>Memuat...
                </div>
                <div style="font-size:11px;color:var(--aq-muted);margin-top:8px;">Normal: 24–30 °C</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="aq-sensor-big-card" id="bigTurb">
                <div class="aq-sensor-big-icon" style="background:#eff6ff;color:#3b82f6;">
                    <i class="bi bi-water"></i>
                </div>
                <div class="aq-sensor-big-value">—</div>
                <div class="aq-sensor-big-label">Turbidity ADC</div>
                <div class="aq-sensor-status-pill secondary">
                    <i class="bi bi-circle-fill" style="font-size:6px"></i>Memuat...
                </div>
                <div style="font-size:11px;color:var(--aq-muted);margin-top:8px;">Normal: ADC &gt; 3150</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="aq-sensor-big-card" id="bigPakan">
                <div class="aq-sensor-big-icon" style="background:#f0fdf4;color:#22c55e;">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="aq-sensor-big-value">—</div>
                <div class="aq-sensor-big-label">Jarak Pakan</div>
                <div class="aq-sensor-status-pill secondary">
                    <i class="bi bi-circle-fill" style="font-size:6px"></i>Memuat...
                </div>
                <div style="font-size:11px;color:var(--aq-muted);margin-top:8px;">Menipis jika ≥ 10 cm</div>
            </div>
        </div>
    </div>

    <!-- STATUS KONEKSI -->
    <div class="aq-card mb-4">
        <div class="aq-card-header">
            <h3 class="aq-card-title"><i class="bi bi-wifi"></i>Status Koneksi Perangkat</h3>
            <div class="aq-sensor-status-pill secondary" id="deviceOnlineStatus">
                <span class="aq-status-dot"></span>Memeriksa...
            </div>
        </div>
        <div class="aq-card-body p-0">
            <table class="aq-device-table">
                <thead>
                    <tr>
                        <th>Sensor</th>
                        <th>Komponen</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-thermometer-half me-2" style="color:#f97316;"></i>Suhu Air</td>
                        <td>DS18B20</td>
                        <td id="rowSuhu">—</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-water me-2" style="color:#3b82f6;"></i>Kekeruhan</td>
                        <td>Turbidity Sensor</td>
                        <td id="rowTurb">—</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-box-seam me-2" style="color:#22c55e;"></i>Jarak Pakan</td>
                        <td>HC-SR04</td>
                        <td id="rowPakan">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- GRAFIK -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="aq-card">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-graph-up" style="color:#f97316;"></i>Histori Suhu</h3>
                    <span style="font-size:11px;color:var(--aq-muted);">24 jam terakhir</span>
                </div>
                <div class="aq-card-body">
                    <div class="aq-chart-wrap">
                        <canvas id="suhuChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="aq-card">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-graph-up" style="color:#3b82f6;"></i>Histori Turbidity</h3>
                    <span style="font-size:11px;color:var(--aq-muted);">24 jam terakhir</span>
                </div>
                <div class="aq-card-body">
                    <div class="aq-chart-wrap">
                        <canvas id="turbChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AMBANG BATAS -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="aq-card">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-sliders"></i>Ambang Batas Suhu</h3>
                </div>
                <div class="aq-card-body">
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-success);"></div>
                        <span class="aq-threshold-label">Normal</span>
                        <span class="aq-threshold-value">24 – 30 °C</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-danger);"></div>
                        <span class="aq-threshold-label">Suhu Rendah</span>
                        <span class="aq-threshold-value">&lt; 24 °C</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-danger);"></div>
                        <span class="aq-threshold-label">Suhu Tinggi</span>
                        <span class="aq-threshold-value">&gt; 30 °C</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="aq-card">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-sliders"></i>Ambang Batas Kekeruhan</h3>
                </div>
                <div class="aq-card-body">
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-success);"></div>
                        <span class="aq-threshold-label">Normal / Jernih</span>
                        <span class="aq-threshold-value">ADC &gt; 3150</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-warning);"></div>
                        <span class="aq-threshold-label">Agak Keruh</span>
                        <span class="aq-threshold-value">ADC 2301–3150</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-danger);"></div>
                        <span class="aq-threshold-label">Keruh (Bahaya)</span>
                        <span class="aq-threshold-value">ADC ≤ 2300</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-warning);"></div>
                        <span class="aq-threshold-label">Pakan Menipis</span>
                        <span class="aq-threshold-value">Jarak ≥ 10 cm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>document.body.dataset.page = 'sensor';</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
