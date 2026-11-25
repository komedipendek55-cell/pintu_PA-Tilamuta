<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false,'Metode tidak diizinkan.',null,405);
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$newPwd = (string)($_POST['new_password'] ?? '');
if (strlen($newPwd) < 6) json_response(false,'Password minimal 6 karakter.',null,422);

$pdo = Database::pdo();
try {
    $hash = password_hash($newPwd, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?")->execute([$hash,$id]);
    audit_log('reset_password','users',$id,null,null);
    json_response(true,'Password berhasil direset. User harus mengganti saat login berikutnya.');
} catch (Throwable $e) {
    error_log('[PINTU] reset_pwd: '.$e->getMessage());
    json_response(false,'Gagal reset password.',null,500);
}