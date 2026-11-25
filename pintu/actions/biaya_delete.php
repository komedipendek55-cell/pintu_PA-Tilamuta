<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_KASIR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false,'Metode tidak diizinkan.',null,405);
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$pdo = Database::pdo();

try {
    $old = $pdo->prepare("SELECT * FROM biaya_perkara WHERE id=?"); $old->execute([$id]);
    $before = $old->fetch();
    if (!$before) json_response(false,'Data tidak ditemukan.',null,404);

    // Soft delete: set INACTIVE
    $pdo->prepare("UPDATE biaya_perkara SET status='INACTIVE' WHERE id=?")->execute([$id]);
    audit_log('soft_delete','biaya_perkara',$id,$before,['status'=>'INACTIVE']);
    json_response(true,'Item biaya dinonaktifkan.');
} catch (Throwable $e) {
    error_log('[PINTU] biaya_delete: '.$e->getMessage());
    json_response(false,'Gagal menonaktifkan item biaya.',null,500);
}