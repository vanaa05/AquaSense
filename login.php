<?php
// Laporan tidak merancang halaman login.
// Akses dashboard terbuka untuk semua perangkat di jaringan lokal yang sama.
require_once __DIR__ . '/config/app.php';

header('Location: ' . APP_URL . '/pages/beranda.php');
exit;
