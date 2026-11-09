<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pakanOtomatis  = getPengaturan('pakan_otomatis');
$jadwal         = getSemuaJadwal();

$pageTitle  = 'Jadwal Pakan';
$activePage = 'jadwal';
require_once __DIR__ . '/../includes/header.php';
?>

<div data-page="jadwal">

    <div class="row g-4">

        <!-- ===== KOLOM UTAMA ===== -->
        <div class="col-xl-8">

            <!-- TOGGLE PAKAN OTOMATIS -->
            <div class="aq-card mb-4">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-robot"></i>Pakan Otomatis</h3>
                </div>
                <div class="aq-card-body">
                    <div class="aq-toggle-wrap">
                        <div class="aq-toggle-label">
                            <span class="aq-toggle-label-text">Aktifkan Pakan Otomatis</span>
                            <span class="aq-toggle-label-sub">ESP32 akan menjalankan jadwal yang aktif secara otomatis</span>
                        </div>
                        <label class="aq-toggle-switch">
                            <input type="checkbox" id="togglePakanOtomatis" <?= $pakanOtomatis == '1' ? 'checked' : '' ?>>
                            <span class="aq-toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- DAFTAR JADWAL -->
            <div class="aq-card">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-list-ul"></i>Daftar Jadwal</h3>
                    <button class="aq-btn aq-btn-primary aq-btn-sm" onclick="openAddModal()">
                        <i class="bi bi-plus-lg"></i>Tambah Jadwal
                    </button>
                </div>
                <div class="aq-card-body">
                    <div id="jadwalList">
                        <?php if (empty($jadwal)): ?>
                        <p class="text-muted small text-center py-3">Belum ada jadwal. Tambah jadwal baru.</p>
                        <?php else: ?>
                        <?php foreach ($jadwal as $j): ?>
                        <div class="aq-jadwal-table-item <?= $j['aktif'] ? '' : 'nonaktif' ?>" data-id="<?= $j['id'] ?>">
                            <div>
                                <div style="font-size:16px;font-weight:700;"><?= substr($j['jam'], 0, 5) ?></div>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($j['nama']) ?></div>
                                <div style="font-size:11px;color:var(--aq-muted);">Servo <?= $j['sudut_servo'] ?>° · <?= $j['aktif'] ? 'Aktif' : 'Nonaktif' ?></div>
                            </div>
                            <span class="aq-jadwal-status <?= $j['aktif'] ? 'menunggu' : 'nonaktif' ?>"><?= $j['aktif'] ? 'Aktif' : 'Nonaktif' ?></span>
                            <div class="aq-jadwal-table-actions">
                                <button class="aq-btn-icon" onclick="openEditModal(<?= $j['id'] ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="aq-btn-icon danger" onclick="hapusJadwal(<?= $j['id'] ?>, '<?= htmlspecialchars($j['nama'], ENT_QUOTES) ?>')" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== KOLOM KANAN ===== -->
        <div class="col-xl-4">

            <!-- PERSEDIAAN PAKAN -->
            <div class="aq-card mb-4">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-box-seam"></i>Persediaan Pakan</h3>
                </div>
                <div class="aq-card-body">
                    <div id="pakanCard">
                        <?php
                        $mon = getMonitoringTerakhir();
                        if ($mon && $mon['jarak_valid']) {
                            $sp = getStatusPakan((float)$mon['jarak_pakan']);
                            echo '<div style="text-align:center;">';
                            echo '<div class="aq-sensor-big-value">' . $mon['jarak_pakan'] . ' cm</div>';
                            echo '<span class="aq-sensor-status-pill ' . $sp['class'] . '"><i class="bi bi-circle-fill" style="font-size:6px"></i>' . $sp['label'] . '</span>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-muted small text-center">Tidak ada data sensor</p>';
                        }
                        ?>
                    </div>
                    <div class="aq-threshold-item mt-3">
                        <div class="aq-threshold-dot" style="background:var(--aq-success);"></div>
                        <span class="aq-threshold-label">Cukup</span>
                        <span class="aq-threshold-value">&lt; 10 cm</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-warning);"></div>
                        <span class="aq-threshold-label">Menipis</span>
                        <span class="aq-threshold-value">≥ 10 cm</span>
                    </div>
                </div>
            </div>

            <!-- ATURAN JADWAL -->
            <div class="aq-card">
                <div class="aq-card-header">
                    <h3 class="aq-card-title"><i class="bi bi-info-circle"></i>Aturan Jadwal</h3>
                </div>
                <div class="aq-card-body">
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-primary);"></div>
                        <span class="aq-threshold-label">Waktu tersedia</span>
                        <span class="aq-threshold-value">08:00 / 12:00 / 17:00 / 21:00</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-warning);"></div>
                        <span class="aq-threshold-label">Servo tersedia</span>
                        <span class="aq-threshold-value">45° / 90° / 135° / 180°</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-danger);"></div>
                        <span class="aq-threshold-label">Tidak boleh</span>
                        <span class="aq-threshold-value">Waktu yang sama</span>
                    </div>
                    <div class="aq-threshold-item">
                        <div class="aq-threshold-dot" style="background:var(--aq-muted);"></div>
                        <span class="aq-threshold-label">Nonaktif</span>
                        <span class="aq-threshold-value">Tidak dijalankan ESP32</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL EDIT / TAMBAH JADWAL ===== -->
<div class="aq-modal-overlay" id="editJadwalModal">
    <div class="aq-modal">
        <div class="aq-modal-header">
            <h4 class="aq-modal-title" id="editModalTitle">Edit Jadwal</h4>
            <button class="aq-modal-close" data-close-modal="editJadwalModal">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="aq-modal-body">

            <div class="aq-form-group">
                <label class="aq-form-label" for="editNama">Nama Jadwal</label>
                <input type="text" class="aq-form-control" id="editNama" placeholder="cth: Makan Pagi">
            </div>

            <div class="aq-form-group">
                <label class="aq-form-label">Jam Pemberian</label>
                <div class="aq-quick-time" style="margin-bottom:8px;">
                    <button class="aq-quick-time-btn" data-time="08:00">08:00</button>
                    <button class="aq-quick-time-btn" data-time="12:00">12:00</button>
                    <button class="aq-quick-time-btn" data-time="17:00">17:00</button>
                    <button class="aq-quick-time-btn" data-time="21:00">21:00</button>
                </div>
                <input type="time" class="aq-form-control" id="editJam" value="08:00">
            </div>

            <div class="aq-form-group">
                <label class="aq-form-label">Sudut Servo</label>
                <div class="aq-servo-options">
                    <button class="aq-servo-option" data-sudut="45">45°</button>
                    <button class="aq-servo-option active" data-sudut="90">90°</button>
                    <button class="aq-servo-option" data-sudut="135">135°</button>
                    <button class="aq-servo-option" data-sudut="180">180°</button>
                </div>
            </div>

            <div class="aq-form-group">
                <div class="aq-toggle-wrap">
                    <div class="aq-toggle-label">
                        <span class="aq-toggle-label-text">Status Jadwal</span>
                        <span class="aq-toggle-label-sub">Jadwal nonaktif tidak dijalankan ESP32</span>
                    </div>
                    <label class="aq-toggle-switch">
                        <input type="checkbox" id="editAktif" checked>
                        <span class="aq-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- Preview -->
            <div class="aq-form-group mb-0">
                <label class="aq-form-label">Preview Konfigurasi</label>
                <div class="aq-preview-box" id="jadwalPreview">
                    <div class="aq-preview-row"><span class="aq-preview-key">Nama Jadwal</span><span class="aq-preview-value">—</span></div>
                    <div class="aq-preview-row"><span class="aq-preview-key">Jam Pemberian</span><span class="aq-preview-value">—</span></div>
                    <div class="aq-preview-row"><span class="aq-preview-key">Sudut Servo</span><span class="aq-preview-value">—</span></div>
                    <div class="aq-preview-row"><span class="aq-preview-key">Status</span><span class="aq-preview-value">—</span></div>
                </div>
            </div>

        </div>
        <div class="aq-modal-footer">
            <button class="aq-btn aq-btn-danger aq-btn-sm" onclick="hapusJadwalDariModal()" id="btnHapusModal" style="margin-right:auto;">
                <i class="bi bi-trash me-1"></i>Hapus
            </button>
            <button class="aq-btn aq-btn-outline" data-close-modal="editJadwalModal">Batal</button>
            <button class="aq-btn aq-btn-primary" onclick="saveJadwal()">
                <i class="bi bi-floppy me-1"></i>Simpan Perubahan
            </button>
        </div>
    </div>
</div>

<!-- Overlay untuk close modal dengan klik di luar -->
<div class="aq-sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.body.dataset.page = 'jadwal';

// Override modal title based on action
const origOpen = openEditModal;
const origAdd  = openAddModal;

function openEditModal(id) {
    document.getElementById('editModalTitle').textContent = 'Edit Jadwal';
    document.getElementById('btnHapusModal').style.display = '';
    origOpen(id);
}

function openAddModal() {
    document.getElementById('editModalTitle').textContent = 'Tambah Jadwal Baru';
    document.getElementById('btnHapusModal').style.display = 'none';
    origAdd();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
