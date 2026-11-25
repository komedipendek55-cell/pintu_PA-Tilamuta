<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('perkara.php'));
verify_csrf();

$role = current_role();
$pdo = Database::pdo();

$id = (int)($_POST['id'] ?? 0);
$category = (string)($_POST['category'] ?? '');
$name = trim((string)($_POST['name'] ?? ''));
$short = trim((string)($_POST['short_description'] ?? ''));
$desc = (string)($_POST['description_html'] ?? '');
$notes = trim((string)($_POST['additional_notes'] ?? ''));
$costType = $_POST['cost_type'] ?: null;
$flow = trim((string)($_POST['process_flow'] ?? ''));
$flowUrl = trim((string)($_POST['process_flow_url'] ?? ''));
$finalProduct = trim((string)($_POST['final_product'] ?? ''));
$status = $_POST['status'] ?? STATUS_PUBLISHED;
$reqs = array_values(array_filter(array_map('trim', (array)($_POST['requirements'] ?? [])), fn($s) => $s !== ''));

$errors = [];
if (!in_array($category, ALL_CATEGORIES, true)) $errors[] = 'Kategori tidak valid.';
if ($name === '') $errors[] = 'Nama wajib diisi.';
if (mb_strlen($name) > 200) $errors[] = 'Nama maksimal 200 karakter.';
if (!in_array($status, [STATUS_DRAFT, STATUS_PUBLISHED], true)) $status = STATUS_PUBLISHED;
if ($costType !== null && $costType !== '' && !cost_type_exists($costType, true)) {
    $errors[] = 'Jenis biaya tidak valid atau tidak aktif.';
}
if (!role_can_manage_category($role, $category)) $errors[] = 'Anda tidak berhak mengelola kategori ini.';

if ($errors) {
    keep_old($_POST);
    set_flash('danger', implode(' ', $errors));
    redirect(admin_url('perkara-form.php' . ($id ? "?id=$id" : '')));
}

try {
    $pdo->beginTransaction();

    $iconPath = null;
    try {
        $iconPath = handle_icon_upload($_FILES['icon'] ?? [], $_POST['old_icon'] ?? null);
    } catch (Throwable $e) {
        throw new RuntimeException($e->getMessage());
    }

    // Cek duplikasi
    $chkSql = "SELECT id FROM perkara WHERE category=? AND name=?" . ($id ? " AND id<>?" : "");
    $chk = $pdo->prepare($chkSql);
    $chk->execute($id ? [$category, $name, $id] : [$category, $name]);
    if ($chk->fetch()) throw new RuntimeException('Nama sudah ada di kategori ini.');

    if ($id) {
        // ambil data lama untuk audit
        $old = $pdo->prepare("SELECT * FROM perkara WHERE id=?"); $old->execute([$id]); $before = $old->fetch();
        $sql = "UPDATE perkara SET category=?, name=?, short_description=?, description_html=?, additional_notes=?,
                cost_type=?, process_flow=?, process_flow_url=?, final_product=?, status=?, updated_by=?
                " . ($iconPath ? ", icon_path=?" : "") . " WHERE id=?";
        $params = [$category,$name,$short,sanitize_html($desc),$notes,$costType,$flow,$flowUrl,$finalProduct,$status,current_user()['id']];
        if ($iconPath) $params[] = $iconPath;
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        audit_log('update','perkara',$id,$before,['name'=>$name,'category'=>$category]);
    } else {
        $sql = "INSERT INTO perkara (category,name,icon_path,short_description,description_html,additional_notes,
                cost_type,process_flow,process_flow_url,final_product,status,created_by,updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([
            $category,$name,$iconPath,$short,sanitize_html($desc),$notes,$costType,$flow,$flowUrl,$finalProduct,$status,
            current_user()['id'],current_user()['id']
        ]);
        $id = (int)$pdo->lastInsertId();
        audit_log('create','perkara',$id,null,['name'=>$name,'category'=>$category]);
    }

    // Sinkronisasi persyaratan
    $pdo->prepare("DELETE FROM persyaratan WHERE perkara_id=?")->execute([$id]);
    if ($reqs) {
        $ins = $pdo->prepare("INSERT INTO persyaratan (perkara_id, requirement_text, sequence_no) VALUES (?,?,?)");
        $seq = 1;
        foreach ($reqs as $r) { $ins->execute([$id, mb_substr($r,0,500), $seq++]); }
    }

    $pdo->commit();
    set_flash('success', 'Data perkara berhasil disimpan.');
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PINTU] perkara_save: ' . $ex->getMessage());
    set_flash('danger', $ex instanceof RuntimeException ? $ex->getMessage() : 'Terjadi kesalahan saat menyimpan. Silakan coba kembali.');
}

redirect(admin_url('perkara.php'));