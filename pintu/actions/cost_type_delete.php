<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_KASIR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
if (!$id) json_response(false, 'ID tidak valid.', null, 422);

$pdo = Database::pdo();
try {
    $s = $pdo->prepare("SELECT * FROM cost_types WHERE id=?");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) json_response(false, 'Data tidak ditemukan.', null, 404);

    // Cek pemakaian
    $u1 = $pdo->prepare("SELECT COUNT(*) FROM biaya_perkara WHERE cost_type=?");
    $u1->execute([$row['code']]);
    $u2 = $pdo->prepare("SELECT COUNT(*) FROM perkara WHERE cost_type=?");
    $u2->execute([$row['code']]);
    $used = (int)$u1->fetchColumn() + (int)$u2->fetchColumn();

    if ($used > 0) {
        // Soft delete: nonaktifkan
        $pdo->prepare("UPDATE cost_types SET is_active=0 WHERE id=?")->execute([$id]);
        audit_log('soft_delete', 'cost_types', $id, $row, ['is_active' => 0]);
        json_response(true, "Jenis biaya dinonaktifkan (dipakai di {$used} data).");
    }

    // Hapus permanen kalau tidak dipakai
    $pdo->prepare("DELETE FROM cost_types WHERE id=?")->execute([$id]);
    audit_log('delete', 'cost_types', $id, $row, null);
    json_response(true, 'Jenis biaya dihapus.');
} catch (Throwable $e) {
    error_log('[PINTU] cost_type_delete: ' . $e->getMessage());
    json_response(false, 'Gagal menghapus data.', null, 500);
}