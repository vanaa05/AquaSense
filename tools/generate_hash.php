<?php
/**
 * AquaSense — Generate Hash Password untuk database
 * Jalankan sekali: http://localhost/AquaSense/tools/generate_hash.php
 * HAPUS file ini setelah digunakan!
 */
if (!isset($_GET['confirm'])) {
    echo '<form><input name="confirm" value="yes" type="hidden">
    <p>Password: <input name="pass" value="aquasense123"></p>
    <button type="submit">Generate Hash</button></form>';
    exit;
}
$pass = $_GET['pass'] ?? 'aquasense123';
$hash = password_hash($pass, PASSWORD_BCRYPT);
echo "<p>Password: <code>$pass</code></p>";
echo "<p>Hash: <code>$hash</code></p>";
echo "<p>Verify: " . (password_verify($pass, $hash) ? '✅ OK' : '❌ FAIL') . "</p>";
echo "<p>SQL:<br><code>UPDATE admin_users SET password_hash = '$hash' WHERE username = 'admin';</code></p>";
?>
