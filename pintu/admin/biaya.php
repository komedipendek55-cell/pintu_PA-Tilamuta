<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_KASIR]);

$pdo = Database::pdo();
$page_title = 'Biaya Perkara';

$q = trim((string)($_GET['q'] ?? ''));
$ct = $_GET['ct'] ?? '';
$st = $_GET['st'] ?? '';

$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = "description LIKE ?"; $params[] = "%$q%"; }
if ($ct !== '' && cost_type_exists($ct, false)) {
    $where[] = "cost_type=?";
    $params[] = $ct;
}
if ($st && in_array($st, ['ACTIVE','INACTIVE'], true)) { $where[] = "status=?"; $params[] = $st; }
$wsql = 'WHERE ' . implode(' AND ', $where);

$rows = $pdo->prepare("SELECT * FROM biaya_perkara $wsql ORDER BY cost_type, id");
$rows->execute($params); $rows = $rows->fetchAll();

// Edit mode
$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM biaya_perkara WHERE id=?"); $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch() ?: null;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold"><?= $edit ? 'Edit' : 'Tambah' ?> Item Biaya</div>
      <div class="card-body">
        <form method="post" action="<?= e(base_url('actions/biaya_save.php')) ?>" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="col-12">
			  <label class="form-label">Jenis Biaya <span class="text-danger">*</span></label>
			  <select class="form-select" name="cost_type" required>
				<?php foreach (get_cost_types() as $ct): ?>
				  <option value="<?= e($ct['code']) ?>"
					<?= ($edit['cost_type'] ?? '') === $ct['code'] ? 'selected' : '' ?>>
					<?= e($ct['label']) ?> (<?= e($ct['code']) ?>)
				  </option>
				<?php endforeach; ?>
			  </select>
			</div>
          <div class="col-12">
            <label class="form-label">Keterangan <span class="text-danger">*</span></label>
            <input class="form-control" name="description" maxlength="200" required value="<?= e($edit['description'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount" required value="<?= e($edit['amount'] ?? '0') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Tipe Perhitungan <span class="text-danger">*</span></label>
            <select class="form-select" name="calculation_type" required>
              <?php foreach (ALL_CALC_TYPES as $cv): ?>
                <option value="<?= e($cv) ?>" <?= ($edit['calculation_type']??'')===$cv?'selected':'' ?>><?= e(CALC_LABELS[$cv]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="ACTIVE" <?= ($edit['status']??'ACTIVE')==='ACTIVE'?'selected':'' ?>>Aktif</option>
              <option value="INACTIVE" <?= ($edit['status']??'')==='INACTIVE'?'selected':'' ?>>Nonaktif</option>
            </select>
          </div>
          <div class="col-12 d-grid gap-2">
            <button class="btn btn-pintu"><i class="bi bi-save"></i> Simpan</button>
            <?php if ($edit): ?>
              <a href="<?= e(admin_url('biaya.php')) ?>" class="btn btn-outline-secondary">Batal Edit</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form class="row g-2 mb-3" method="get">
          <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari keterangan..."></div>
          <div class="col-md-3">
		  <select class="form-select" name="ct">
			<option value="">Semua jenis</option>
			<?php foreach (get_cost_types(false) as $ct): ?>
			  <option value="<?= e($ct['code']) ?>" <?= $ct===$ct['code']?'selected':'' ?>>
				<?= e($ct['label']) ?>
			  </option>
			<?php endforeach; ?>
		  </select>
		</div>
          <div class="col-md-2">
            <select class="form-select" name="st">
              <option value="">Status</option>
              <option value="ACTIVE" <?= $st==='ACTIVE'?'selected':'' ?>>Aktif</option>
              <option value="INACTIVE" <?= $st==='INACTIVE'?'selected':'' ?>>Nonaktif</option>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-pintu w-100"><i class="bi bi-search"></i></button></div>
        </form>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>Jenis</th><th>Keterangan</th><th>Nominal</th><th>Calc</th><th>Status</th><th width="120">Aksi</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= e($r['cost_type']) ?></span></td>
                <td><?= e($r['description']) ?></td>
                <td class="fw-semibold"><?= e(format_rupiah($r['amount'])) ?></td>
                <td><small><?= e(CALC_LABELS[$r['calculation_type']]) ?></small></td>
                <td>
                  <?php if ($r['status']==='ACTIVE'): ?>
                    <span class="badge bg-success-subtle text-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>"><i class="bi bi-pencil"></i></a>
                  <button class="btn btn-sm btn-outline-danger btn-delete"
                          data-url="<?= e(base_url('actions/biaya_delete.php')) ?>"
                          data-id="<?= (int)$r['id'] ?>"
                          data-name="<?= e($r['description']) ?>"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>