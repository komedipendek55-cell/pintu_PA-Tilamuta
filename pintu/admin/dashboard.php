<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$pdo = Database::pdo();
$role = current_role();

// Statistik
$totalPerkara = (int)$pdo->query("SELECT COUNT(*) FROM perkara")->fetchColumn();
$totalBiaya   = (int)$pdo->query("SELECT COUNT(*) FROM biaya_perkara WHERE status='ACTIVE'")->fetchColumn();
$totalUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$totalFeedback= (int)$pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$visToday     = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_key) FROM visitor_logs WHERE visit_date=CURDATE()")->fetchColumn();

// Perkara terbaru
$latest = $pdo->query("SELECT id, category, name, status, updated_at FROM perkara ORDER BY updated_at DESC LIMIT 8")->fetchAll();

// Distribusi per kategori
$dist = $pdo->query("SELECT category, COUNT(*) c FROM perkara GROUP BY category")->fetchAll();
$distMap = [];
foreach ($dist as $d) $distMap[$d['category']] = (int)$d['c'];

// Trend 7 hari
$trend = $pdo->query("SELECT visit_date, COUNT(DISTINCT visitor_key) c
                      FROM visitor_logs
                      WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                      GROUP BY visit_date ORDER BY visit_date")->fetchAll();

$page_title = 'Dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Total Perkara</div>
            <div class="stat-value"><?= $totalPerkara ?></div>
          </div>
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-file-earmark-text"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Biaya Aktif</div>
            <div class="stat-value"><?= $totalBiaya ?></div>
          </div>
          <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-cash-coin"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Pengunjung Hari Ini</div>
            <div class="stat-value"><?= $visToday ?></div>
          </div>
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label">Feedback</div>
            <div class="stat-value"><?= $totalFeedback ?></div>
          </div>
          <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-chat-square-heart"></i></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history text-success"></i> Perkara Terbaru</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Nama</th><th>Kategori</th><th>Status</th><th>Update</th></tr></thead>
          <tbody>
            <?php if (!$latest): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data perkara.</td></tr>
            <?php else: foreach ($latest as $p): ?>
              <tr>
                <td class="fw-semibold"><?= e($p['name']) ?></td>
                <td><span class="badge badge-cat"><?= e(CATEGORY_LABELS[$p['category']]) ?></span></td>
                <td>
                  <?php if ($p['status'] === 'PUBLISHED'): ?>
                    <span class="badge bg-success-subtle text-success">Published</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Draft</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= e(date('d M Y H:i', strtotime($p['updated_at']))) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold"><i class="bi bi-pie-chart text-warning"></i> Distribusi Kategori</div>
      <div class="card-body">
        <?php foreach (ALL_CATEGORIES as $c): $val = $distMap[$c] ?? 0; $pct = $totalPerkara ? round($val/$totalPerkara*100) : 0; ?>
          <div class="d-flex justify-content-between small mb-1">
            <span><?= e(CATEGORY_LABELS[$c]) ?></span>
            <span class="fw-bold"><?= $val ?> (<?= $pct ?>%)</span>
          </div>
          <div class="progress mb-3" style="height:8px">
            <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold"><i class="bi bi-graph-up text-primary"></i> Kunjungan 7 Hari Terakhir</div>
      <div class="card-body">
        <?php
        $maxC = 1; foreach ($trend as $t) $maxC = max($maxC, (int)$t['c']);
        if (!$trend): ?>
          <p class="text-muted small mb-0">Belum ada data kunjungan.</p>
        <?php else: ?>
          <div class="d-flex align-items-end gap-2" style="height:100px">
            <?php foreach ($trend as $t): $h = (int)($t['c']/$maxC*100); ?>
              <div class="flex-fill text-center" title="<?= e($t['visit_date']) ?>: <?= (int)$t['c'] ?>">
                <div class="bg-success rounded-top mx-auto" style="height:<?= max(4,$h) ?>%;width:100%;min-height:4px"></div>
                <small class="text-muted" style="font-size:.65rem"><?= e(date('d/m', strtotime($t['visit_date']))) ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>