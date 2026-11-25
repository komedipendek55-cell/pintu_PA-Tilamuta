<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false,'Metode tidak diizinkan.',null,405);
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id === (int)current_user()['id']) json_response(false,'Tidak dapat menghapus akun sendiri.',null,422);

$pdo = Database::pdo();
try {
    $old = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE id=?"); $old->execute([$id]);
    $before = $old->fetch();
    if (!$before) json_response(false,'User tidak ditemukan.',null,404);

    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    audit_log('delete','users',$id,$before,null);
    json_response(true,'Pengguna dihapus.');
} catch (Throwable $e) {
    error_log('[PINTU] user_delete: '.$e->getMessage());
    json_response(false,'Gagal menghapus pengguna.',null,500);
}