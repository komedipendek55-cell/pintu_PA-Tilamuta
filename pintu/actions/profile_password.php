<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('profile.php'));
verify_csrf();

$cur = (string)($_POST['current_password'] ?? '');
$new = (string)($_POST['new_password'] ?? '');
$conf = (string)($_POST['confirm_password'] ?? '');

if (strlen($new) < 6) { set_flash('danger','Password baru minimal 6 karakter.'); redirect(admin_url('profile.php')); }
if ($new !== $conf)    { set_flash('danger','Konfirmasi password tidak cocok.'); redirect(admin_url('profile.php')); }

$pdo = Database::pdo();
$s = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
$s->execute([(int)current_user()['id']]);
$row = $s->fetch();

if (!$row || !password_verify($cur, $row['password_hash'])) {
    set_flash('danger','Password lama tidak sesuai.'); redirect(admin_url('profile.php'));
}

try {
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?")
        ->execute([$hash, (int)current_user()['id']]);
    audit_log('change_password','users',(int)current_user()['id']);
    set_flash('success','Password berhasil diubah.');
} catch (Throwable $e) {
    error_log('[PINTU] profile_password: '.$e->getMessage());
    set_flash('danger','Gagal mengganti password.');
}
redirect(admin_url('profile.php'));