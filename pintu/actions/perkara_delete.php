<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf();

$role = current_role();
$id = (int)($_POST['id'] ?? 0);
$pdo = Database::pdo();

$s = $pdo->prepare("SELECT * FROM perkara WHERE id=?"); $s->execute([$id]);
$p = $s->fetch();
if (!$p) json_response(false, 'Data tidak ditemukan.', null, 404);
if (!role_can_manage_category($role, $p['category'])) json_response(false, 'Akses ditolak.', null, 403);

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM persyaratan WHERE perkara_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM perkara WHERE id=?")->execute([$id]);
    audit_log('delete','perkara',$id,$p,null);
    $pdo->commit();
    json_response(true, 'Data berhasil dihapus.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PINTU] delete perkara: ' . $e->getMessage());
    json_response(false, 'Gagal menghapus data.', null, 500);
}