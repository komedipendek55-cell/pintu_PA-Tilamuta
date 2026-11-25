<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('profile.php'));
verify_csrf();

$fullName = trim((string)($_POST['full_name'] ?? ''));
if ($fullName === '') { set_flash('danger','Nama wajib diisi.'); redirect(admin_url('profile.php')); }

$pdo = Database::pdo();
try {
    $pdo->prepare("UPDATE users SET full_name=? WHERE id=?")->execute([$fullName, current_user()['id']]);
    $_SESSION['user']['full_name'] = $fullName;
    audit_log('update_profile','users',(int)current_user()['id'],null,['full_name'=>$fullName]);
    set_flash('success','Profil diperbarui.');
} catch (Throwable $e) {
    error_log('[PINTU] profile_update: '.$e->getMessage());
    set_flash('danger','Gagal memperbarui profil.');
}
redirect(admin_url('profile.php'));