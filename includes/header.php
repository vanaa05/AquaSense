<?php
/**
 * AquaSense — Header HTML Global
 * Dipanggil di setiap halaman utama
 * Variabel yang harus didefinisikan sebelum include:
 *   $pageTitle  — judul halaman
 *   $activePage — 'beranda' | 'sensor' | 'jadwal'
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
startSecureSession();

$notifCount = countNotifikasiBelumDibaca();
$pageTitle  = $pageTitle ?? APP_NAME;

$isOnline   = isPerangkatOnline();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — AquaSense</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="aq-wrapper">
    <!-- ===== SIDEBAR ===== -->
    <aside class="aq-sidebar" id="aqSidebar">
        <div class="aq-sidebar-brand">
            <div class="aq-logo-icon">
                <i class="bi bi-water"></i>
            </div>
            <div class="aq-logo-text">
                <span class="aq-logo-name">AquaSense</span>
                <span class="aq-logo-sub">Smart Aquarium</span>
            </div>
        </div>

        <nav class="aq-sidebar-nav">
            <div class="aq-nav-label">Menu Utama</div>
            <a href="<?= APP_URL ?>/pages/beranda.php" class="aq-nav-link <?= ($activePage ?? '') === 'beranda' ? 'active' : '' ?>">
                <i class="bi bi-house-fill"></i>
                <span>Beranda</span>
            </a>
            <a href="<?= APP_URL ?>/pages/sensor.php" class="aq-nav-link <?= ($activePage ?? '') === 'sensor' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i>
                <span>Sensor</span>
            </a>
            <a href="<?= APP_URL ?>/pages/jadwal.php" class="aq-nav-link <?= ($activePage ?? '') === 'jadwal' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                <span>Jadwal Pakan</span>
            </a>
        </nav>

        <div class="aq-sidebar-footer">
            <div class="aq-device-status <?= $isOnline ? 'online' : 'offline' ?>">
                <span class="aq-status-dot"></span>
                <span><?= $isOnline ? 'Perangkat Online' : 'Perangkat Offline' ?></span>
            </div>
        </div>
    </aside>

    <!-- ===== KONTEN UTAMA ===== -->
    <div class="aq-main">
        <!-- HEADER -->
        <header class="aq-header">
            <div class="aq-header-left">
                <button class="aq-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div class="aq-header-title">
                    <h1 class="aq-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                    <div class="aq-breadcrumb">
                        <span>AquaSense</span> <i class="bi bi-chevron-right"></i> <span><?= htmlspecialchars($pageTitle) ?></span>
                    </div>
                </div>
            </div>
            <div class="aq-header-right">
                <!-- Status Akuarium -->
                <div class="aq-aquarium-badge <?= $isOnline ? 'online' : 'offline' ?>">
                    <span class="aq-status-dot"></span>
                    <?= $isOnline ? 'Akuarium Aktif' : 'Akuarium Offline' ?>
                </div>

                <!-- Notifikasi -->
                <div class="aq-notif-wrap dropdown">
                    <button class="aq-notif-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notifBtn">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="aq-notif-badge" id="notifBadge"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end aq-notif-dropdown" id="notifDropdown">
                        <div class="aq-notif-header">
                            <span>Notifikasi</span>
                            <button class="aq-notif-clear-btn" id="clearAllNotif">Tandai semua dibaca</button>
                        </div>
                        <div class="aq-notif-list" id="notifList">
                            <div class="aq-notif-empty">
                                <i class="bi bi-check-circle"></i>
                                <p>Tidak ada notifikasi baru</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        <!-- KONTEN HALAMAN -->
        <main class="aq-content">

<!-- Mobile sidebar overlay -->
<div class="aq-sidebar-overlay" id="sidebarOverlay"></div>
