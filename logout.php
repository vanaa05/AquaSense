<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
logoutAdmin();
header('Location: ' . APP_URL . '/login.php');
exit;
