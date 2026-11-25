<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

$pdo = Database::pdo();
$page_title = 'Manajemen Pengguna';

$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id=?");
    $s->execute([(int)$_GET['edit']]); $edit = $s->fetch() ?: null;
}
$rows = $pdo->query("SELECT id, username, full_name, role, is_active, last_login_at FROM users ORDER BY role, username")->fetchAll();

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold"><?= $edit ? 'Edit' : 'Tambah' ?> Pengguna</div>
      <div class="card-body">
        <form method="post" action="<?= e(base_url('actions/user_save.php')) ?>" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="col-12">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input class="form-control" name="username" required maxlength="100" value="<?= e($edit['username'] ?? '') ?>" <?= $edit?'readonly':'' ?>>
          </div>
          <div class="col-12">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input class="form-control" name="full_name" required maxlength="150" value="<?= e($edit['full_name'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select class="form-select" name="role" required>
              <?php foreach (ALL_ROLES as $r): ?>
                <option value="<?= e($r) ?>" <?= ($edit['role']??'')===$r?'selected':'' ?>><?= e(ROLE_LABELS[$r]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!$edit): ?>
          <div class="col-12">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="password" required minlength="6" placeholder="Min 6 karakter">
          </div>
          <?php else: ?>
          <div class="col-12">
            <label class="form-label">Password Baru (opsional)</label>
            <input type="password" class="form-control" name="password" minlength="6" placeholder="Kosongkan jika tidak diubah">
          </div>
          <?php endif; ?>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                     <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="isActive">Aktif</label>
            </div>
          </div>
          <div class="col-12 d-grid gap-2">
            <button class="btn btn-pintu"><i class="bi bi-save"></i> Simpan</button>
            <?php if ($edit): ?>
              <a href="<?= e(admin_url('users.php')) ?>" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th width="150">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= e($r['username']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= e(ROLE_LABELS[$r['role']] ?? $r['role']) ?></span></td>
                <td>
                  <?php if ((int)$r['is_active']): ?>
                    <span class="badge bg-success-subtle text-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?= $r['last_login_at'] ? e(date('d M Y H:i', strtotime($r['last_login_at']))) : '—' ?></td>
                <td>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <button class="btn btn-sm btn-outline-warning btn-reset-pwd"
                          data-url="<?= e(base_url('actions/user_reset_password.php')) ?>"
                          data-id="<?= (int)$r['id'] ?>"
                          data-name="<?= e($r['username']) ?>"><i class="bi bi-key"></i></button>
                  <button class="btn btn-sm btn-outline-danger btn-delete"
                          data-url="<?= e(base_url('actions/user_delete.php')) ?>"
                          data-id="<?= (int)$r['id'] ?>"
                          data-name="<?= e($r['username']) ?>"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>