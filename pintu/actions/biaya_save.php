<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_KASIR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('biaya.php'));
verify_csrf();

$pdo = Database::pdo();
$id = (int)($_POST['id'] ?? 0);
$ct = $_POST['cost_type'] ?? '';
$desc = trim((string)($_POST['description'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);
$calc = $_POST['calculation_type'] ?? 'FIXED';
$st = $_POST['status'] ?? 'ACTIVE';

$errors = [];
if (!cost_type_exists($ct, true)) $errors[] = 'Jenis biaya tidak valid atau tidak aktif.';
if ($desc === '') $errors[] = 'Keterangan wajib diisi.';
if ($amount < 0) $errors[] = 'Nominal tidak boleh negatif.';
if (!in_array($calc, ALL_CALC_TYPES, true)) $errors[] = 'Tipe perhitungan tidak valid.';
if (!in_array($st, ['ACTIVE','INACTIVE'], true)) $st = 'ACTIVE';

if ($errors) { set_flash('danger', implode(' ', $errors)); redirect(admin_url('biaya.php')); }

try {
    if ($id) {
        $old = $pdo->prepare("SELECT * FROM biaya_perkara WHERE id=?"); $old->execute([$id]); $before = $old->fetch();
        $pdo->prepare("UPDATE biaya_perkara SET cost_type=?, description=?, amount=?, calculation_type=?, status=? WHERE id=?")
            ->execute([$ct,$desc,$amount,$calc,$st,$id]);
        audit_log('update','biaya_perkara',$id,$before,['description'=>$desc,'amount'=>$amount]);
    } else {
        $pdo->prepare("INSERT INTO biaya_perkara (cost_type, description, amount, calculation_type, status) VALUES (?,?,?,?,?)")
            ->execute([$ct,$desc,$amount,$calc,$st]);
        audit_log('create','biaya_perkara',(int)$pdo->lastInsertId(),null,['description'=>$desc,'amount'=>$amount]);
    }
    set_flash('success','Item biaya berhasil disimpan.');
} catch (Throwable $e) {
    error_log('[PINTU] biaya_save: ' . $e->getMessage());
    set_flash('danger','Gagal menyimpan data biaya.');
}
redirect(admin_url('biaya.php'));