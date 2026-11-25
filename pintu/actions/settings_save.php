<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('settings.php'));
verify_csrf();

$keys = ['site_name','site_short_name','site_instansi','manual_book_url',
         'facebook_url','instagram_url','youtube_url','tiktok_url','survey_url','footer_note',
         'whatsapp_url','chatbot_url','popup_enabled'];

$pdo = Database::pdo();
$oldLogo  = get_setting('site_logo');
$oldPopup = get_setting('popup_image');

// ---- Handle upload logo ----
$newLogo   = null;
$removeLogo = !empty($_POST['remove_logo']);

try {
    $newLogo = handle_logo_upload($_FILES['site_logo'] ?? [], $oldLogo);
} catch (Throwable $ex) {
    set_flash('danger', $ex->getMessage());
    redirect(admin_url('settings.php'));
}

// ---- Handle upload popup ----
$newPopup    = null;
$removePopup = !empty($_POST['remove_popup']);

try {
    $newPopup = handle_popup_upload($_FILES['popup_image'] ?? [], $oldPopup);
} catch (Throwable $ex) {
    set_flash('danger', $ex->getMessage());
    redirect(admin_url('settings.php'));
}

try {
    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
                          ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");

    foreach ($keys as $k) {
        $val = (string)($_POST[$k] ?? '');
        $ins->execute([$k, $val]);
    }

    // Simpan logo baru
    if ($newLogo) {
        $ins->execute(['site_logo', $newLogo]);
        audit_log('update', 'settings', null, ['logo' => $oldLogo], ['logo' => $newLogo]);
    }
        // Hapus logo
    elseif ($removeLogo && $oldLogo) {
        $oldFull = __DIR__ . '/../' . $oldLogo;
        if (is_file($oldFull)) @unlink($oldFull);
        $ins->execute(['site_logo', '']);
        audit_log('delete', 'settings', null, ['logo' => $oldLogo], ['logo' => null]);
    }

    // Simpan popup baru
    if ($newPopup) {
        $ins->execute(['popup_image', $newPopup]);
        audit_log('update', 'settings', null, ['popup' => $oldPopup], ['popup' => $newPopup]);
    }
    // Hapus popup
    elseif ($removePopup && $oldPopup) {
        $oldFull = __DIR__ . '/../' . $oldPopup;
        if (is_file($oldFull)) @unlink($oldFull);
        $ins->execute(['popup_image', '']);
        audit_log('delete', 'settings', null, ['popup' => $oldPopup], ['popup' => null]);
    }

    $pdo->commit();
    set_flash('success', 'Pengaturan berhasil disimpan.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[PINTU] settings: ' . $e->getMessage());
    set_flash('danger', 'Gagal menyimpan pengaturan.');
}
redirect(admin_url('settings.php'));