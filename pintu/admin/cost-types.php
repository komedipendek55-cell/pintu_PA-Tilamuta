<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_KASIR]);

$pdo = Database::pdo();
$page_title = 'Master Jenis Biaya';

// Mode edit
$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM cost_types WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

// List semua (aktif + nonaktif)
$rows = $pdo->query("SELECT * FROM cost_types ORDER BY sort_order ASC, label ASC")->fetchAll();

// Hitung pemakaian setiap jenis biaya (biaya_perkara + perkara)
$usage = [];
$u1 = $pdo->query("SELECT cost_type, COUNT(*) c FROM biaya_perkara GROUP BY cost_type")->fetchAll();
$u2 = $pdo->query("SELECT cost_type, COUNT(*) c FROM perkara WHERE cost_type IS NOT NULL GROUP BY cost_type")->fetchAll();
foreach ($u1 as $r) $usage[$r['cost_type']] = ($usage[$r['cost_type']] ?? 0) + (int)$r['c'];
foreach ($u2 as $r) $usage[$r['cost_type']] = ($usage[$r['cost_type']] ?? 0) + (int)$r['c'];

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-3">
  <!-- FORM TAMBAH / EDIT -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">
        <?= $edit ? 'Edit' : 'Tambah' ?> Jenis Biaya
      </div>
      <div class="card-body">
        <form method="post" action="<?= e(base_url('actions/cost_type_save.php')) ?>" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

          <div class="col-12">
            <label class="form-label">Kode <span class="text-danger">*</span></label>
            <input class="form-control" name="code" required maxlength="50"
                   pattern="[A-Za-z0-9_\-]+"
                   value="<?= e($edit['code'] ?? '') ?>"
                   <?= $edit ? 'readonly' : '' ?>
                   placeholder="mis. GUGATAN, PERMOHONAN, PNBP, MEDIASI">
            <small class="text-muted">
              Huruf besar/kecil, angka, underscore. Digunakan internal — <b>tidak dapat diubah</b> setelah dibuat.
            </small>
          </div>

          <div class="col-12">
            <label class="form-label">Label <span class="text-danger">*</span></label>
            <input class="form-control" name="label" required maxlength="100"
                   value="<?= e($edit['label'] ?? '') ?>"
                   placeholder="mis. Gugatan, Permohonan, Mediasi">
            <small class="text-muted">Nama yang tampil di dropdown.</small>
          </div>

          <div class="col-12">
            <label class="form-label">Deskripsi</label>
            <textarea class="form-control" name="description" rows="2" maxlength="255"><?= e($edit['description'] ?? '') ?></textarea>
          </div>
		  
		  <div class="col-12">
            <label class="form-label">
              <i class="bi bi-chat-left-text text-warning"></i>
              Catatan Pihak
            </label>
            <textarea class="form-control" name="catatan_pihak" rows="4"
                      placeholder="Contoh: Nominal ini merupakan perkiraan awal dan dapat berubah..."><?= e($edit['catatan_pihak'] ?? '') ?></textarea>
            <small class="text-muted">
              Catatan ini akan tampil otomatis di halaman detail perkara
              pada bagian bawah kalkulator biaya.
            </small>
          </div>

          <div class="col-md-6">
            <label class="form-label">Urutan</label>
            <input type="number" class="form-control" name="sort_order" min="0"
                   value="<?= e($edit['sort_order'] ?? 0) ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label d-block">Status</label>
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" name="is_active" value="1"
                     id="isActive" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="isActive">Aktif</label>
            </div>
          </div>

          <div class="col-12 d-grid gap-2">
            <button class="btn btn-pintu"><i class="bi bi-save"></i> Simpan</button>
            <?php if ($edit): ?>
              <a href="<?= e(admin_url('cost-types.php')) ?>" class="btn btn-outline-secondary">Batal Edit</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- LIST -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Daftar Jenis Biaya</span>
        <span class="badge bg-light text-dark border"><?= count($rows) ?> item</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
               <tr>
                <th width="50">Urut</th>
                <th>Kode</th>
                <th>Label</th>
                <th>Deskripsi</th>
                <th width="100" class="text-center">Catatan</th>
                <th width="90" class="text-center">Dipakai</th>
                <th width="90">Status</th>
                <th width="120">Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada jenis biaya.</td></tr>
            <?php else: foreach ($rows as $r): ?>
               <tr>
                <td><span class="badge bg-light text-dark"><?= (int)$r['sort_order'] ?></span></td>
                <td><code><?= e($r['code']) ?></code></td>
                <td class="fw-semibold"><?= e($r['label']) ?></td>
                <td><small class="text-muted"><?= e($r['description'] ?: '—') ?></small></td>
                <td class="text-center">
                  <?php if (!empty(trim((string)$r['catatan_pihak']))): ?>
                    <span class="badge bg-warning-subtle text-warning" title="<?= e(mb_strimwidth($r['catatan_pihak'], 0, 200, '…')) ?>">
                      <i class="bi bi-check-circle-fill"></i> Ada
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php $u = $usage[$r['code']] ?? 0; ?>
                  <?php if ($u > 0): ?>
                    <span class="badge bg-info-subtle text-info"><?= $u ?></span>
                  <?php else: ?>
                    <span class="text-muted small">0</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$r['is_active']): ?>
                    <span class="badge bg-success-subtle text-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn btn-sm btn-outline-primary"
                     href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <button class="btn btn-sm btn-outline-danger btn-delete"
                          data-url="<?= e(base_url('actions/cost_type_delete.php')) ?>"
                          data-id="<?= (int)$r['id'] ?>"
                          data-name="<?= e($r['label']) ?>"
                          <?= $u > 0 ? 'disabled title="Masih dipakai"' : '' ?>>
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="alert alert-info small mt-3">
      <i class="bi bi-info-circle"></i>
      <b>Tips:</b> Jenis biaya yang sudah "Dipakai" (&gt;0) tidak dapat dihapus, hanya bisa
      <b>dinonaktifkan</b> — supaya history biaya lama tidak rusak.
      Nonaktif = tidak muncul di dropdown form baru, tapi data lama tetap valid.
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>