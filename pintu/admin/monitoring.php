<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

$pdo = Database::pdo();
$page_title = 'Monitoring Pengunjung';

$year = (int)($_GET['year'] ?? date('Y'));
$month = $_GET['month'] ?? date('Y-m');

// Harian (30 hari terakhir)
$daily = $pdo->query("SELECT visit_date, COUNT(DISTINCT visitor_key) c
                      FROM visitor_logs
                      WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                      GROUP BY visit_date ORDER BY visit_date")->fetchAll();

// Bulanan
$stmt = $pdo->prepare("SELECT DATE_FORMAT(visit_date,'%Y-%m') ym, COUNT(DISTINCT visitor_key) c
                       FROM visitor_logs WHERE YEAR(visit_date)=? GROUP BY ym ORDER BY ym");
$stmt->execute([$year]); $monthly = $stmt->fetchAll();

// Harian dalam bulan yang dipilih
$stmt2 = $pdo->prepare("SELECT visit_date, COUNT(DISTINCT visitor_key) c
                        FROM visitor_logs WHERE DATE_FORMAT(visit_date,'%Y-%m')=? GROUP BY visit_date ORDER BY visit_date");
$stmt2->execute([$month]); $monthDays = $stmt2->fetchAll();

// Ringkasan
$sum = visitor_stats();
$avgPerDay = count($daily) ? round(array_sum(array_column($daily, 'c'))/count($daily), 1) : 0;

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-label">Hari ini</div><div class="stat-value"><?= (int)$sum['vToday'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-label">Bulan ini</div><div class="stat-value"><?= (int)$sum['vMonth'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-label">Tahun ini</div><div class="stat-value"><?= (int)$sum['vYear'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-label">Rata-rata/hari (30h)</div><div class="stat-value"><?= $avgPerDay ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">Kunjungan 30 Hari Terakhir</div>
      <div class="card-body">
        <div class="table-responsive" style="max-height:400px">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th class="text-end">Pengunjung Unik</th></tr></thead>
            <tbody>
              <?php if (!$daily): ?><tr><td colspan="2" class="text-center text-muted">Belum ada data.</td></tr><?php endif; ?>
              <?php foreach (array_reverse($daily) as $d): ?>
                <tr><td><?= e(date('d M Y', strtotime($d['visit_date']))) ?></td><td class="text-end fw-semibold"><?= (int)$d['c'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Rekap Bulanan <?= $year ?></span>
        <form method="get" class="d-flex gap-1">
          <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
              <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </form>
      </div>
      <div class="card-body">
        <?php if (!$monthly): ?><p class="text-muted small mb-0">Belum ada data.</p><?php endif; ?>
        <?php $maxM = 1; foreach ($monthly as $m) $maxM = max($maxM, (int)$m['c']); ?>
        <?php foreach ($monthly as $m): $pct = round($m['c']/$maxM*100); ?>
          <div class="d-flex justify-content-between small"><span><?= e($m['ym']) ?></span><span class="fw-bold"><?= (int)$m['c'] ?></span></div>
          <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Harian Bulan <?= e($month) ?></span>
        <form method="get" class="d-flex gap-1">
          <input type="hidden" name="year" value="<?= $year ?>">
          <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>" onchange="this.form.submit()">
        </form>
      </div>
      <div class="card-body">
        <div class="table-responsive" style="max-height:300px">
          <table class="table table-sm mb-0">
            <tbody>
              <?php if (!$monthDays): ?><tr><td class="text-muted small">Belum ada data.</td></tr><?php endif; ?>
              <?php foreach ($monthDays as $d): ?>
                <tr><td><?= e(date('d M', strtotime($d['visit_date']))) ?></td><td class="text-end"><?= (int)$d['c'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>