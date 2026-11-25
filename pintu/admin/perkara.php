<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$role = current_role();
$allowed = role_allowed_categories($role);
if (empty($allowed)) { http_response_code(403); include __DIR__.'/../includes/403.php'; exit; }

$pdo = Database::pdo();
$page_title = 'Perkara & Layanan';

// Filter
$q   = trim((string)($_GET['q'] ?? ''));
$cat = $_GET['cat'] ?? '';
$st  = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 10;
$off  = ($page - 1) * $per;

$where = ["category IN ('" . implode("','", $allowed) . "')"];
$params = [];
if ($q !== '')     { $where[] = "(name LIKE ? OR short_description LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat && in_array($cat, $allowed, true)) { $where[] = "category = ?"; $params[] = $cat; }
if ($st && in_array($st, [STATUS_DRAFT, STATUS_PUBLISHED], true)) { $where[] = "status = ?"; $params[] = $st; }
$wsql = 'WHERE ' . implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM perkara $wsql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

$stmt = $pdo->prepare("SELECT * FROM perkara $wsql ORDER BY category, name LIMIT $per OFFSET $off");
$stmt->execute($params);
$rows = $stmt->fetchAll();

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
      <form class="row g-2 flex-grow-1" method="get">
        <div class="col-md-4">
          <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari nama / deskripsi...">
        </div>
        <div class="col-md-3">
          <select class="form-select" name="cat">
            <option value="">Semua kategori</option>
            <?php foreach ($allowed as $c): ?>
              <option value="<?= e($c) ?>" <?= $cat===$c?'selected':'' ?>><?= e(CATEGORY_LABELS[$c]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="status">
            <option value="">Semua status</option>
            <option value="PUBLISHED" <?= $st==='PUBLISHED'?'selected':'' ?>>Published</option>
            <option value="DRAFT"     <?= $st==='DRAFT'?'selected':'' ?>>Draft</option>
          </select>
        </div>
        <div class="col-md-2"><button class="btn btn-pintu w-100"><i class="bi bi-search"></i></button></div>
      </form>
      <a class="btn btn-success" href="<?= e(admin_url('perkara-form.php')) ?>"><i class="bi bi-plus-lg"></i> Tambah</a>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th width="60">Icon</th><th>Nama</th><th>Kategori</th><th>Biaya</th><th>Status</th><th width="140">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td>
              <?php if ($r['icon_path']): ?>
                <img src="<?= e(base_url($r['icon_path'])) ?>" width="40" height="40" class="rounded">
              <?php else: ?>
                <span class="text-muted"><i class="bi bi-image"></i></span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?= e($r['name']) ?></div>
              <small class="text-muted"><?= e(mb_strimwidth((string)$r['short_description'],0,80,'…')) ?></small>
            </td>
            <td><span class="badge badge-cat"><?= e(CATEGORY_LABELS[$r['category']]) ?></span></td>
            <td><small><?= e($r['cost_type'] ?: '—') ?></small></td>
            <td>
              <?php if ($r['status']==='PUBLISHED'): ?>
                <span class="badge bg-success-subtle text-success">Published</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary">Draft</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('perkara-form.php?id=' . (int)$r['id'])) ?>"><i class="bi bi-pencil"></i></a>
              <button class="btn btn-sm btn-outline-danger btn-delete"
                      data-url="<?= e(base_url('actions/perkara_delete.php')) ?>"
                      data-id="<?= (int)$r['id'] ?>"
                      data-name="<?= e($r['name']) ?>"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
          <?php for ($i=1; $i<=$pages; $i++): $u = '?' . http_build_query(array_merge($_GET, ['page'=>$i])); ?>
            <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="<?= e($u) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>