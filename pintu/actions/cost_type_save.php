<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_KASIR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('cost-types.php'));
verify_csrf();

$pdo = Database::pdo();
$id         = (int)($_POST['id'] ?? 0);
$code       = strtoupper(trim((string)($_POST['code'] ?? '')));
$label      = trim((string)($_POST['label'] ?? ''));
$description= trim((string)($_POST['description'] ?? ''));
$catatanPihak = trim((string)($_POST['catatan_pihak'] ?? ''));
$sortOrder  = (int)($_POST['sort_order'] ?? 0);
$isActive   = isset($_POST['is_active']) ? 1 : 0;

$errors = [];
if (!preg_match('/^[A-Z0-9_\-]{2,50}$/', $code)) {
    $errors[] = 'Kode minimal 2 karakter, hanya huruf (A-Z), angka, underscore, dan dash.';
}
if ($label === '' || mb_strlen($label) > 100) {
    $errors[] = 'Label wajib diisi (max 100 karakter).';
}

if ($errors) {
    set_flash('danger', implode(' ', $errors));
    redirect(admin_url('cost-types.php' . ($id ? "?edit=$id" : '')));
}

try {
    if ($id) {
        // Update (kode TIDAK boleh diubah)
        $old = $pdo->prepare("SELECT * FROM cost_types WHERE id=?");
        $old->execute([$id]);
        $before = $old->fetch();
        if (!$before) throw new RuntimeException('Data tidak ditemukan.');

        $pdo->prepare("UPDATE cost_types SET label=?, description=?, catatan_pihak=?, sort_order=?, is_active=? WHERE id=?")
            ->execute([$label, $description ?: null, $catatanPihak ?: null, $sortOrder, $isActive, $id]);

                audit_log('update', 'cost_types', $id, $before,
                  ['label' => $label, 'is_active' => $isActive, 'has_note' => $catatanPihak !== '']);
        set_flash('success', 'Jenis biaya diperbarui.');
    } else {
        // Cek duplikat kode
        $chk = $pdo->prepare("SELECT id FROM cost_types WHERE code=?");
        $chk->execute([$code]);
        if ($chk->fetch()) throw new RuntimeException("Kode '{$code}' sudah digunakan.");

        $pdo->prepare("INSERT INTO cost_types (code, label, description, catatan_pihak, sort_order, is_active) VALUES (?,?,?,?,?,?)")
            ->execute([$code, $label, $description ?: null, $catatanPihak ?: null, $sortOrder, $isActive]);
        $newId = (int)$pdo->lastInsertId();

        audit_log('create', 'cost_types', $newId, null,
                  ['code' => $code, 'label' => $label]);
        set_flash('success', "Jenis biaya '{$label}' ditambahkan.");
    }
} catch (Throwable $e) {
    error_log('[PINTU] cost_type_save: ' . $e->getMessage());
    set_flash('danger', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan data.');
}
redirect(admin_url('cost-types.php'));