<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(admin_url('users.php'));
verify_csrf();

$pdo = Database::pdo();
$id = (int)($_POST['id'] ?? 0);
$username = trim((string)($_POST['username'] ?? ''));
$fullName = trim((string)($_POST['full_name'] ?? ''));
$role = $_POST['role'] ?? '';
$pwd = (string)($_POST['password'] ?? '');
$active = isset($_POST['is_active']) ? 1 : 0;

$errors = [];
if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) $errors[] = 'Username minimal 3 karakter alfanumerik.';
if ($fullName === '') $errors[] = 'Nama lengkap wajib diisi.';
if (!in_array($role, ALL_ROLES, true)) $errors[] = 'Role tidak valid.';
if (!$id && strlen($pwd) < 6) $errors[] = 'Password minimal 6 karakter.';
if ($id && $pwd !== '' && strlen($pwd) < 6) $errors[] = 'Password minimal 6 karakter.';

if ($errors) { set_flash('danger', implode(' ', $errors)); redirect(admin_url('users.php')); }

try {
    if ($id) {
        $old = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id=?"); $old->execute([$id]);
        $before = $old->fetch();
        if (!$before) { set_flash('danger','User tidak ditemukan.'); redirect(admin_url('users.php')); }

        // Cegah admin menonaktifkan dirinya sendiri
        if ($id === (int)current_user()['id'] && !$active) {
            set_flash('danger', 'Anda tidak dapat menonaktifkan akun sendiri.');
            redirect(admin_url('users.php'));
        }

        if ($pwd !== '') {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET full_name=?, role=?, is_active=?, password_hash=? WHERE id=?")
                ->execute([$fullName,$role,$active,$hash,$id]);
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, role=?, is_active=? WHERE id=?")
                ->execute([$fullName,$role,$active,$id]);
        }
        audit_log('update','users',$id,$before,['full_name'=>$fullName,'role'=>$role,'is_active'=>$active]);
    } else {
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?,?,?,?,?)")
            ->execute([$username,$hash,$fullName,$role,$active]);
        audit_log('create','users',(int)$pdo->lastInsertId(),null,['username'=>$username,'role'=>$role]);
    }
    set_flash('success','Pengguna berhasil disimpan.');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') set_flash('danger','Username sudah digunakan.');
    else { error_log('[PINTU] user_save: '.$e->getMessage()); set_flash('danger','Gagal menyimpan pengguna.'); }
}
redirect(admin_url('users.php'));