<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = Database::pdo();
$user = current_user();
$s = $pdo->prepare("SELECT username, full_name, role FROM users WHERE id=?");
$s->execute([(int)$user['id']]);
$me = $s->fetch();

$page_title = 'Profil Saya';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">Data Profil</div>
      <div class="card-body">
        <form method="post" action="<?= e(base_url('actions/profile_update.php')) ?>" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-12">
            <label class="form-label">Username</label>
            <input class="form-control" value="<?= e($me['username']) ?>" disabled>
          </div>
          <div class="col-12">
            <label class="form-label">Role</label>
            <input class="form-control" value="<?= e(ROLE_LABELS[$me['role']] ?? $me['role']) ?>" disabled>
          </div>
          <div class="col-12">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input class="form-control" name="full_name" required maxlength="150" value="<?= e($me['full_name']) ?>">
          </div>
          <div class="col-12"><button class="btn btn-pintu"><i class="bi bi-save"></i> Simpan</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">Ganti Password</div>
      <div class="card-body">
        <form method="post" action="<?= e(base_url('actions/profile_password.php')) ?>" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-12">
            <label class="form-label">Password Lama <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="current_password" required>
          </div>
          <div class="col-12">
            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="new_password" required minlength="6">
          </div>
          <div class="col-12">
            <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="confirm_password" required minlength="6">
          </div>
          <div class="col-12"><button class="btn btn-warning"><i class="bi bi-key"></i> Ganti Password</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>