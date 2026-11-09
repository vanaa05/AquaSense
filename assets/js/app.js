/**
 * AquaSense — app.js
 * Polling, Feed Now, Notifikasi, Charts, Jadwal CRUD
 */

'use strict';

// ================================================================
// UTILITIES
// ================================================================

function showToast(message, type = 'info', duration = 4000) {
    let container = document.getElementById('aqToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'aqToastContainer';
        container.className = 'aq-toast-container';
        document.body.appendChild(container);
    }
    const icons = { success: 'bi-check-circle-fill', warning: 'bi-exclamation-triangle-fill',
                    danger: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
    const toast = document.createElement('div');
    toast.className = `aq-toast ${type}`;
    toast.innerHTML = `<i class="bi ${icons[type] || icons.info} aq-toast-icon"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all .25s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

async function apiFetch(url, options = {}) {
    try {
        const defaults = {
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        };
        const res = await fetch(url, { ...defaults, ...options });
        const json = await res.json();
        return { ok: res.ok, status: res.status, data: json };
    } catch (e) {
        console.error('API error:', url, e);
        return { ok: false, status: 0, data: { success: false, message: 'Koneksi gagal.' } };
    }
}

function formatCountdown(minutes) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h > 0) return `${h} jam ${m} menit`;
    return `${m} menit`;
}

// ================================================================
// SIDEBAR TOGGLE
// ================================================================

(function initSidebar() {
    const sidebar  = document.getElementById('aqSidebar');
    const toggle   = document.getElementById('sidebarToggle');
    const overlay  = document.querySelector('.aq-sidebar-overlay');
    if (!sidebar || !toggle) return;

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay && overlay.classList.toggle('show');
    });
    overlay && overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });
})();

// ================================================================
// NOTIFIKASI
// ================================================================

async function loadNotifications() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/notifikasi.php`);
    if (!result.ok) return;

    const { count, notifikasi } = result.data;
    const badge  = document.getElementById('notifBadge');
    const list   = document.getElementById('notifList');

    // Update badge
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Update list
    if (!list) return;
    if (!notifikasi || notifikasi.length === 0) {
        list.innerHTML = `<div class="aq-notif-empty"><i class="bi bi-check-circle"></i><p>Tidak ada notifikasi baru</p></div>`;
        return;
    }

    const typeIcon = {
        suhu_tinggi: 'bi-thermometer-high',
        suhu_rendah: 'bi-thermometer-low',
        keruh: 'bi-water',
        pakan_menipis: 'bi-exclamation-diamond-fill',
        perangkat_offline: 'bi-wifi-off',
        sensor_error: 'bi-exclamation-triangle-fill',
    };
    const typeClass = {
        suhu_tinggi: 'danger', suhu_rendah: 'info', keruh: 'warning',
        pakan_menipis: 'warning', perangkat_offline: 'danger', sensor_error: 'warning',
    };

    list.innerHTML = notifikasi.map(n => `
        <div class="aq-notif-item" data-id="${n.id}" onclick="markRead(${n.id})">
            <div class="aq-notif-icon ${typeClass[n.jenis] || 'info'}">
                <i class="bi ${typeIcon[n.jenis] || 'bi-bell'}"></i>
            </div>
            <div class="aq-notif-body">
                <div class="aq-notif-msg">${n.pesan}</div>
                <div class="aq-notif-time">${n.time_ago}${n.nilai_sensor ? ' · ' + n.nilai_sensor : ''}</div>
            </div>
        </div>
    `).join('');
}

async function markRead(id) {
    await apiFetch(`${APP_URL}/api/dashboard/notifikasi.php?baca=${id}`, { method: 'POST' });
    loadNotifications();
}

async function clearAllNotif() {
    await apiFetch(`${APP_URL}/api/dashboard/notifikasi.php?baca=all`, { method: 'POST' });
    loadNotifications();
    showToast('Semua notifikasi ditandai dibaca.', 'success');
}

document.getElementById('clearAllNotif')?.addEventListener('click', clearAllNotif);
document.getElementById('notifBtn')?.addEventListener('click', loadNotifications);

// ================================================================
// BERANDA PAGE
// ================================================================

let feedNowData = null;

async function loadBerandaData() {
    await Promise.all([loadSensorSummary(), loadFeedNowStatus(), loadJadwalHariIni()]);
}

async function loadSensorSummary() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/sensor.php`);
    if (!result.ok) return;

    const d = result.data;
    const hero = document.getElementById('heroCard');

    // Hero card
    if (hero) {
        const liveEl = hero.querySelector('.aq-hero-live');
        const titleEl = hero.querySelector('.aq-hero-status-title');
        const subEl = hero.querySelector('.aq-hero-status-sub');

        if (liveEl) {
            liveEl.className = `aq-hero-live ${d.online ? '' : 'offline'}`;
            liveEl.querySelector('.live-dot') && (liveEl.querySelector('.live-dot').className = 'live-dot');
            liveEl.lastChild.textContent = d.online ? ' LIVE' : ' OFFLINE';
        }
        if (titleEl) {
            titleEl.textContent = !d.online ? 'Tidak Ada Data' :
                (d.status_suhu?.status === 'normal' && d.status_turb?.status === 'normal' ? 'Kondisi Normal' : 'Perlu Perhatian');
        }
        if (subEl) {
            subEl.textContent = d.online
                ? `Terakhir diperbarui: ${d.time_ago || '-'}`
                : 'Perangkat tidak mengirim data';
        }
    }

    // Sensor summary cards
    updateSensorCard('sensorSuhu', d.suhu !== null ? `${d.suhu}°C` : '-', d.status_suhu, 'Suhu Air');
    updateSensorCard('sensorTurb', d.turbidity_adc !== null ? `${d.turbidity_adc}` : '-', d.status_turb, 'Turbidity ADC');
    updateSensorCard('sensorPakan', d.jarak_pakan !== null ? `${d.jarak_pakan} cm` : '-', d.status_pakan, 'Jarak Pakan');

    const updEl = document.getElementById('sensorUpdated');
    if (updEl && d.updated_at) updEl.textContent = `Update: ${d.time_ago}`;
}

function updateSensorCard(id, value, statusObj, label) {
    const el = document.getElementById(id);
    if (!el) return;
    const valEl   = el.querySelector('.aq-sensor-value');
    const badgeEl = el.querySelector('.aq-sensor-badge');
    if (valEl) valEl.textContent = value;
    if (badgeEl && statusObj) {
        badgeEl.textContent = statusObj.label;
        badgeEl.className = `aq-sensor-badge ${statusObj.class}`;
    }
}

async function loadFeedNowStatus() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/feed_now.php`);
    if (!result.ok) return;

    feedNowData = result.data;
    renderFeedNow(feedNowData);
}

function renderFeedNow(d) {
    const btn        = document.getElementById('feedNowBtn');
    const statusEl   = document.getElementById('feedNowStatus');
    const countdownEl= document.getElementById('feedNowCountdown');
    const quotaEl    = document.getElementById('feedNowQuota');

    if (quotaEl) {
        quotaEl.innerHTML = `
            <span>Kuota hari ini: <strong>${d.jumlah_manual}/${d.jumlah_manual + d.sisa_kuota}</strong></span>
            <span>Sisa: <strong>${d.sisa_kuota}x</strong></span>
        `;
    }

    if (countdownEl) {
        if (d.sisa_jeda > 0) {
            countdownEl.style.display = '';
            countdownEl.innerHTML = `<i class="bi bi-clock me-1"></i>Tunggu ${formatCountdown(d.sisa_jeda)} sebelum memberi pakan lagi`;
        } else {
            countdownEl.style.display = 'none';
        }
    }

    // Status pending
    if (d.pending) {
        if (statusEl) {
            statusEl.className = 'aq-feed-status pending';
            statusEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i>Menunggu konfirmasi perangkat...`;
            statusEl.style.display = '';
        }
        if (btn) btn.disabled = true;
        return;
    }

    if (statusEl) statusEl.style.display = 'none';

    if (btn) {
        btn.disabled = !d.can_feed;
        btn.querySelector('.btn-text') && (btn.querySelector('.btn-text').textContent =
            d.sisa_kuota <= 0 ? 'Kuota Habis' : (d.sisa_jeda > 0 ? 'Dalam Jeda' : 'Beri Pakan Sekarang'));
    }
}

async function doFeedNow() {
    const sudut = parseInt(document.querySelector('.aq-servo-btn.active')?.dataset?.sudut || '90');
    const btn = document.getElementById('feedNowBtn');
    if (btn) { btn.disabled = true; }

    const result = await apiFetch(`${APP_URL}/api/dashboard/feed_now.php`, {
        method: 'POST',
        body: JSON.stringify({ sudut_servo: sudut }),
    });

    if (result.data.success) {
        showToast('Perintah dikirim. Menunggu konfirmasi perangkat...', 'info');
        const statusEl = document.getElementById('feedNowStatus');
        if (statusEl) {
            statusEl.className = 'aq-feed-status pending';
            statusEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i>Menunggu konfirmasi perangkat...`;
            statusEl.style.display = '';
        }
    } else {
        showToast(result.data.message || 'Gagal mengirim perintah.', 'danger');
        await loadFeedNowStatus();
    }
}

document.getElementById('feedNowBtn')?.addEventListener('click', doFeedNow);

// Servo selector on beranda
document.querySelectorAll('.aq-servo-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.aq-servo-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});

async function loadJadwalHariIni() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/jadwal.php`);
    if (!result.ok) return;

    const list = document.getElementById('jadwalHariIniList');
    if (!list) return;

    const now = new Date();
    const nowTime = now.getHours() * 60 + now.getMinutes();

    const jadwal = result.data.jadwal || [];
    if (jadwal.length === 0) {
        list.innerHTML = '<p class="text-muted small text-center py-2">Belum ada jadwal aktif</p>';
        return;
    }

    list.innerHTML = jadwal.filter(j => j.aktif).map(j => {
        const [h, m] = j.jam.split(':').map(Number);
        const jTime = h * 60 + m;
        let statusClass, statusLabel;
        if (jTime < nowTime) {
            statusClass = 'selesai'; statusLabel = 'Selesai';
        } else if (jTime - nowTime <= 30) {
            statusClass = 'menunggu'; statusLabel = 'Segera';
        } else {
            statusClass = 'menunggu'; statusLabel = 'Menunggu';
        }
        return `
            <div class="aq-jadwal-item">
                <div class="aq-jadwal-time">${j.jam.substring(0,5)}</div>
                <div class="aq-jadwal-info">
                    <div class="aq-jadwal-name">${j.nama}</div>
                    <div class="aq-jadwal-servo"><i class="bi bi-gear me-1"></i>Servo ${j.sudut_servo}°</div>
                </div>
                <div class="aq-jadwal-status ${statusClass}">${statusLabel}</div>
            </div>
        `;
    }).join('');
}

// ================================================================
// SENSOR PAGE
// ================================================================

let suhuChart = null;
let turbChart = null;

async function loadSensorPage() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/sensor.php`);
    if (!result.ok) return;

    const d = result.data;
    updateBigCard('bigSuhu', d.suhu !== null ? `${d.suhu}°C` : '—', d.status_suhu);
    updateBigCard('bigTurb', d.turbidity_adc !== null ? `${d.turbidity_adc}` : '—', d.status_turb);
    updateBigCard('bigPakan', d.jarak_pakan !== null ? `${d.jarak_pakan} cm` : '—', d.status_pakan);

    const onlineEl = document.getElementById('deviceOnlineStatus');
    if (onlineEl) {
        onlineEl.className = `aq-sensor-status-pill ${d.online ? 'success' : 'danger'}`;
        onlineEl.innerHTML = `<span class="aq-status-dot"></span>${d.online ? 'Online' : 'Offline'}`;
    }

    // Device table
    updateDeviceRow('rowSuhu',   d.suhu_valid,   d.online);
    updateDeviceRow('rowTurb',   d.turb_valid,   d.online);
    updateDeviceRow('rowPakan',  d.jarak_valid,  d.online);

    await loadCharts();
}

function updateBigCard(id, value, statusObj) {
    const el = document.getElementById(id);
    if (!el) return;
    const valEl    = el.querySelector('.aq-sensor-big-value');
    const pillEl   = el.querySelector('.aq-sensor-status-pill');
    if (valEl) valEl.textContent = value;
    if (pillEl && statusObj) {
        pillEl.className = `aq-sensor-status-pill ${statusObj.class}`;
        pillEl.innerHTML = `<i class="bi bi-circle-fill" style="font-size:6px"></i>${statusObj.label}`;
    }
}

function updateDeviceRow(id, valid, online) {
    const el = document.getElementById(id);
    if (!el) return;
    if (!online) {
        el.className = 'aq-device-offline';
        el.textContent = 'Offline';
    } else if (!valid) {
        el.className = 'aq-device-offline';
        el.textContent = 'Error';
    } else {
        el.className = 'aq-device-online';
        el.textContent = 'Online';
    }
}

async function loadCharts() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/sensor.php?histori=1&jam=24`);
    if (!result.ok || !result.data.labels) return;

    const { labels, suhu, turbidity_adc } = result.data;

    if (document.getElementById('suhuChart')) {
        renderChart('suhuChart', labels, suhu, 'Suhu (°C)', '#f97316', suhuChart, c => suhuChart = c);
    }
    if (document.getElementById('turbChart')) {
        renderChart('turbChart', labels, turbidity_adc, 'ADC Turbidity', '#3b82f6', turbChart, c => turbChart = c);
    }
}

function renderChart(canvasId, labels, data, label, color, existingChart, setter) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    if (existingChart) existingChart.destroy();

    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label,
                data,
                borderColor: color,
                backgroundColor: color + '22',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5,
                spanGaps: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { family: 'Poppins', size: 11 },
                    bodyFont: { family: 'Poppins', size: 12 },
                    cornerRadius: 8,
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(0,0,0,.04)' },
                    ticks: { font: { family: 'Poppins', size: 10 }, maxTicksLimit: 8 }
                },
                y: {
                    grid: { color: 'rgba(0,0,0,.04)' },
                    ticks: { font: { family: 'Poppins', size: 10 } }
                }
            }
        }
    });
    setter(chart);
}

// ================================================================
// JADWAL PAGE
// ================================================================

let editJadwalId = null;
let editServoValue = 90;

async function loadJadwalPage() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/jadwal.php`);
    if (!result.ok) return;

    renderJadwalList(result.data.jadwal || []);
}

function renderJadwalList(jadwal) {
    const list = document.getElementById('jadwalList');
    if (!list) return;

    if (jadwal.length === 0) {
        list.innerHTML = '<p class="text-muted small text-center py-3">Belum ada jadwal. Tambah jadwal baru.</p>';
        return;
    }

    list.innerHTML = jadwal.map(j => `
        <div class="aq-jadwal-table-item ${j.aktif ? '' : 'nonaktif'}" data-id="${j.id}">
            <div>
                <div style="font-size:16px;font-weight:700;">${j.jam.substring(0,5)}</div>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;">${j.nama}</div>
                <div style="font-size:11px;color:var(--aq-muted);">Servo ${j.sudut_servo}° · ${j.aktif ? 'Aktif' : 'Nonaktif'}</div>
            </div>
            <span class="aq-jadwal-status ${j.aktif ? 'menunggu' : 'nonaktif'}">${j.aktif ? 'Aktif' : 'Nonaktif'}</span>
            <div class="aq-jadwal-table-actions">
                <button class="aq-btn-icon" onclick="openEditModal(${j.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                <button class="aq-btn-icon danger" onclick="hapusJadwal(${j.id}, '${j.nama}')" title="Hapus"><i class="bi bi-trash"></i></button>
            </div>
        </div>
    `).join('');
}

async function openEditModal(id) {
    editJadwalId = id;
    const result = await apiFetch(`${APP_URL}/api/dashboard/jadwal.php`);
    if (!result.ok) return;

    const jadwal = (result.data.jadwal || []).find(j => j.id == id);
    if (!jadwal) return;

    document.getElementById('editNama').value       = jadwal.nama;
    document.getElementById('editJam').value        = jadwal.jam.substring(0,5);
    document.getElementById('editAktif').checked    = jadwal.aktif == 1;
    editServoValue = jadwal.sudut_servo;

    // Highlight servo option
    document.querySelectorAll('.aq-servo-option').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.sudut) === editServoValue);
    });

    updatePreview(jadwal.nama, jadwal.jam.substring(0,5), editServoValue, jadwal.aktif);
    openModal('editJadwalModal');
}

function openAddModal() {
    editJadwalId = null;
    document.getElementById('editNama').value   = '';
    document.getElementById('editJam').value    = '08:00';
    document.getElementById('editAktif').checked = true;
    editServoValue = 90;
    document.querySelectorAll('.aq-servo-option').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.sudut) === 90);
    });
    updatePreview('', '08:00', 90, true);
    openModal('editJadwalModal');
}

function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
});

document.querySelectorAll('.aq-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// Servo options in modal
document.querySelectorAll('.aq-servo-option').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.aq-servo-option').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        editServoValue = parseInt(btn.dataset.sudut);
        const jam   = document.getElementById('editJam')?.value   || '';
        const nama  = document.getElementById('editNama')?.value  || '';
        const aktif = document.getElementById('editAktif')?.checked;
        updatePreview(nama, jam, editServoValue, aktif);
    });
});

// Quick time buttons
document.querySelectorAll('.aq-quick-time-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.aq-quick-time-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const jamEl = document.getElementById('editJam');
        if (jamEl) {
            jamEl.value = btn.dataset.time;
            const nama  = document.getElementById('editNama')?.value || '';
            const aktif = document.getElementById('editAktif')?.checked;
            updatePreview(nama, btn.dataset.time, editServoValue, aktif);
        }
    });
});

['editNama', 'editJam', 'editAktif'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
        const jam   = document.getElementById('editJam')?.value   || '';
        const nama  = document.getElementById('editNama')?.value  || '';
        const aktif = document.getElementById('editAktif')?.checked;
        updatePreview(nama, jam, editServoValue, aktif);
    });
});

function updatePreview(nama, jam, sudut, aktif) {
    const el = document.getElementById('jadwalPreview');
    if (!el) return;
    el.innerHTML = `
        <div class="aq-preview-row"><span class="aq-preview-key">Nama Jadwal</span><span class="aq-preview-value">${nama || '-'}</span></div>
        <div class="aq-preview-row"><span class="aq-preview-key">Jam Pemberian</span><span class="aq-preview-value">${jam || '-'}</span></div>
        <div class="aq-preview-row"><span class="aq-preview-key">Sudut Servo</span><span class="aq-preview-value">${sudut}°</span></div>
        <div class="aq-preview-row"><span class="aq-preview-key">Status</span><span class="aq-preview-value">${aktif ? 'Aktif' : 'Nonaktif'}</span></div>
    `;
}

async function saveJadwal() {
    const nama  = document.getElementById('editNama')?.value?.trim() || '';
    const jam   = document.getElementById('editJam')?.value?.trim()  || '';
    const aktif = document.getElementById('editAktif')?.checked ? 1 : 0;

    if (!nama) { showToast('Nama jadwal wajib diisi.', 'warning'); return; }
    if (!jam)  { showToast('Jam wajib diisi.', 'warning'); return; }

    const body = { nama, jam, sudut_servo: editServoValue, aktif };

    let result;
    if (editJadwalId) {
        result = await apiFetch(`${APP_URL}/api/dashboard/jadwal.php?id=${editJadwalId}`, {
            method: 'PUT', body: JSON.stringify(body)
        });
    } else {
        result = await apiFetch(`${APP_URL}/api/dashboard/jadwal.php`, {
            method: 'POST', body: JSON.stringify(body)
        });
    }

    if (result.data.success) {
        showToast(result.data.message, 'success');
        closeModal('editJadwalModal');
        await loadJadwalPage();
    } else {
        showToast(result.data.message || 'Gagal menyimpan jadwal.', 'danger');
    }
}

async function hapusJadwal(id, nama) {
    if (!confirm(`Hapus jadwal "${nama}"? Riwayat pakan tidak akan dihapus.`)) return;

    const result = await apiFetch(`${APP_URL}/api/dashboard/jadwal.php?id=${id}`, { method: 'DELETE' });
    if (result.data.success) {
        showToast(result.data.message, 'success');
        await loadJadwalPage();
    } else {
        showToast(result.data.message || 'Gagal menghapus jadwal.', 'danger');
    }
}

async function hapusJadwalDariModal() {
    if (!editJadwalId) return;
    const nama = document.getElementById('editNama')?.value || '';
    closeModal('editJadwalModal');
    await hapusJadwal(editJadwalId, nama);
}

// Toggle pakan otomatis
document.getElementById('togglePakanOtomatis')?.addEventListener('change', async function() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/pengaturan.php`, {
        method: 'POST',
        body: JSON.stringify({ pakan_otomatis: this.checked ? 1 : 0 }),
    });
    showToast(result.data.success
        ? (this.checked ? 'Pakan otomatis diaktifkan.' : 'Pakan otomatis dinonaktifkan.')
        : 'Gagal mengubah pengaturan.', result.data.success ? 'success' : 'danger');
});

// Toggle buzzer
document.getElementById('toggleBuzzer')?.addEventListener('change', async function() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/pengaturan.php`, {
        method: 'POST',
        body: JSON.stringify({ buzzer_peringatan: this.checked ? 1 : 0 }),
    });
    showToast(result.data.success
        ? (this.checked ? 'Buzzer peringatan diaktifkan.' : 'Buzzer peringatan dinonaktifkan.')
        : 'Gagal mengubah pengaturan.', result.data.success ? 'success' : 'danger');
});

// Load pengaturan toggle state
async function loadPengaturan() {
    const result = await apiFetch(`${APP_URL}/api/dashboard/pengaturan.php`);
    if (!result.ok) return;
    const p = result.data.pengaturan || {};
    const togglePA = document.getElementById('togglePakanOtomatis');
    const toggleBuz= document.getElementById('toggleBuzzer');
    if (togglePA)  togglePA.checked  = p.pakan_otomatis === '1';
    if (toggleBuz) toggleBuz.checked = p.buzzer_peringatan === '1';
}

// ================================================================
// POLLING — Auto-refresh
// ================================================================

const POLLING_INTERVALS = {
    sensor:      10000,   // 10 detik
    feedNow:      5000,   // 5 detik (lebih cepat saat ada pending)
    notification: 30000,  // 30 detik
};

function startPolling() {
    const page = document.body.dataset.page;

    // Notifikasi di semua halaman
    setInterval(loadNotifications, POLLING_INTERVALS.notification);

    if (page === 'beranda') {
        setInterval(loadSensorSummary, POLLING_INTERVALS.sensor);
        setInterval(loadFeedNowStatus, POLLING_INTERVALS.feedNow);
        setInterval(loadJadwalHariIni, 60000);
    }

    if (page === 'sensor') {
        setInterval(loadSensorPage, POLLING_INTERVALS.sensor);
    }

    if (page === 'jadwal') {
        setInterval(loadJadwalPage, 30000);
    }
}

// ================================================================
// INIT — berdasarkan halaman aktif
// ================================================================

document.addEventListener('DOMContentLoaded', async () => {
    const page = document.body.dataset.page;

    loadNotifications();

    if (page === 'beranda') {
        await loadBerandaData();
        await loadPengaturan();
    }

    if (page === 'sensor') {
        await loadSensorPage();
    }

    if (page === 'jadwal') {
        await loadJadwalPage();
        await loadPengaturan();
    }

    startPolling();
});
