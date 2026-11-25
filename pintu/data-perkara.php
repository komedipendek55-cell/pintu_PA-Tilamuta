<?php
require_once __DIR__ . '/includes/bootstrap.php';
track_visitor();

/* ============================================================
   Anti-robot gate
   ============================================================ */
$VERIFY_TTL = 1800;
$now        = time();
$verified   = !empty($_SESSION['datap_verified'])
              && ($now - (int)$_SESSION['datap_verified'] < $VERIFY_TTL);

if (isset($_GET['reset'])) {
    unset($_SESSION['datap_verified'], $_SESSION['datap_captcha']);
    redirect(base_url('data-perkara.php'));
}
if (empty($_SESSION['datap_captcha']) || ($now - (int)($_SESSION['datap_captcha']['created'] ?? 0) > 600)) {
    $a = random_int(3, 12); $b = random_int(2, 9);
    $_SESSION['datap_captcha'] = ['q' => "$a + $b", 'a' => $a + $b, 'created' => $now];
}
$gate_error = $_SESSION['datap_error'] ?? null;
unset($_SESSION['datap_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['datap_verify'])) {
    verify_csrf();
    if (!empty($_POST['website'])) {
        $_SESSION['datap_error'] = 'Akses ditolak.';
        audit_log('datap_honeypot', 'public', null);
        redirect(base_url('data-perkara.php'));
    }
    $ft = (int)($_POST['form_time'] ?? 0);
    if ($ft <= 0 || ($now - $ft) < 2) {
        $_SESSION['datap_error'] = 'Formulir terlalu cepat diproses. Coba lagi.';
        redirect(base_url('data-perkara.php'));
    }
    $expected = $_SESSION['datap_captcha']['a'] ?? null;
    $given    = trim((string)($_POST['captcha'] ?? ''));
    unset($_SESSION['datap_captcha']);
    if ($expected === null || !is_numeric($given) || (int)$given !== (int)$expected) {
        $_SESSION['datap_error'] = 'Jawaban verifikasi anti-robot salah.';
        audit_log('datap_captcha_fail', 'public', null);
        redirect(base_url('data-perkara.php'));
    }
    $_SESSION['datap_verified'] = $now;
    audit_log('datap_verified', 'public', null);
    set_flash('success', 'Verifikasi berhasil.');
    redirect(base_url('data-perkara.php'));
}
$_SESSION['datap_form_time'] = $now;

$page_title = 'Data Perkara';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';

/* ============================================================
   Helper: konversi kode klasifikasi -> label
   ============================================================ */
if (!function_exists('klasifikasi_label')) {
    function klasifikasi_label($code): string {
        $code = (int)$code;
        if ($code === 15) return 'Gugatan';
        if ($code === 16) return 'Permohonan';
        return 'Lainnya';
    }
}
if (!function_exists('klasifikasi_badge_class')) {
    function klasifikasi_badge_class($code): string {
        $code = (int)$code;
        if ($code === 15) return 'badge-gugatan';
        if ($code === 16) return 'badge-permohonan';
        return 'badge-lainnya';
    }
}

/* ============================================================
   TAMPILKAN GATE kalau belum terverifikasi
   ============================================================ */
if (!$verified):
?>
<div class="container py-5 datap-wrap">
  <div class="datap-gate">
    <div class="dg-icon"><i class="bi bi-shield-lock-fill"></i></div>
    <h2 class="dg-title">Verifikasi Akses</h2>
    <p class="dg-sub">
      Untuk menjaga <b>keamanan sistem</b>,
      silakan <b>verifikasi</b> bahwa Anda bukan <b>robot</b>. dengan cara menjawab soal berikut ini :
    </p>
    <?php if ($gate_error): ?>
      <div class="dg-error"><i class="bi bi-x-octagon-fill"></i> <?= e($gate_error) ?></div>
    <?php endif; ?>
    <form method="post" class="dg-form" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="datap_verify" value="1">
      <input type="hidden" name="form_time" value="<?= (int)$_SESSION['datap_form_time'] ?>">
      <div class="dg-hp"><label>Jangan diisi</label><input type="text" name="website" tabindex="-1"></div>
      <div class="dg-row">
        <div class="dg-q"><i class="bi bi-question-circle-fill"></i>
          <span><?= e($_SESSION['datap_captcha']['q']) ?> = ?</span></div>
        <input type="text" name="captcha" class="dg-input" inputmode="numeric"
               pattern="[0-9]+" maxlength="3" placeholder="Jawaban" required autofocus>
      </div>
      <button class="dg-btn"><i class="bi bi-shield-check"></i> Verifikasi &amp; Lanjutkan</button>
    </form>
    <div class="dg-note"><i class="bi bi-info-circle"></i>
      Verifikasi berlaku <b>30 menit</b>.</div>
  </div>
</div>
<?php
include __DIR__ . '/includes/footer.php';
return;
endif;

/* ============================================================
   AUTO-SYNC HARIAN - cek & trigger sekali per hari
   ============================================================ */
$autoSyncCfg   = cfg('auto_sync_daily');
$needsAutoSync = false;
$syncStatusRow = null;
$skipSync      = !empty($_GET['skip_sync']);

if (!$skipSync && !empty($autoSyncCfg['enabled'])) {
    try {
        $pdo = Database::pdo();
        $s = $pdo->prepare("SELECT * FROM sync_daily WHERE sync_date = CURDATE() LIMIT 1");
        $s->execute();
        $syncStatusRow = $s->fetch();

        if (!$syncStatusRow) {
            $needsAutoSync = true;
        } elseif ($syncStatusRow['status'] === 'RUNNING') {
            $elapsed = time() - strtotime($syncStatusRow['triggered_at']);
            if ($elapsed > 900) $needsAutoSync = true;
        }
    } catch (Throwable $e) {
        error_log('[PINTU datap] ' . $e->getMessage());
    }
}

/* ============================================================
   FILTER & QUERY DASHBOARD
   ============================================================ */
$pdo = Database::pdo();

$filterYear  = (int)($_GET['tahun'] ?? date('Y'));
$filterMode  = $_GET['mode']   ?? 'year';
$filterMonth = $_GET['bulan']  ?? date('m');

if ($filterYear < 2000 || $filterYear > 2100) $filterYear = (int)date('Y');
if (!in_array($filterMode, ['year', 'month'], true)) $filterMode = 'year';
if ($filterMonth < 1 || $filterMonth > 12) $filterMonth = date('m');

if ($filterMode === 'month') {
    $periodeLabel = date('F Y', strtotime("$filterYear-$filterMonth-01"));
    $dateWhere    = "YEAR(tglPendaftaran) = :y AND MONTH(tglPendaftaran) = :m";
    $params       = [':y' => $filterYear, ':m' => (int)$filterMonth];
} else {
    $periodeLabel = "Tahun $filterYear";
    $dateWhere    = "YEAR(tglPendaftaran) = :y";
    $params       = [':y' => $filterYear];
}

/* ---- Ringkasan: GROUP BY jenisPerkara + klasifikasiPerkara ---- */
$klasSql = "SELECT
    COALESCE(NULLIF(TRIM(jenisPerkara), ''), 'TIDAK TERDEFINISI') AS jenis,
    CAST(klasifikasiPerkara AS UNSIGNED) AS klas_code,
    COUNT(*) AS diterima,
    SUM(CASE WHEN tglPutusan IS NOT NULL AND tglPutusan <> '0000-00-00' THEN 1 ELSE 0 END) AS diputus,
    SUM(CASE WHEN tglPutusan IS NULL OR tglPutusan = '0000-00-00' THEN 1 ELSE 0 END)     AS sisa
    FROM data_perkara
    WHERE $dateWhere
    GROUP BY jenis, klas_code
    ORDER BY diterima DESC";
$stmt = $pdo->prepare($klasSql);
$stmt->execute($params);
$klasOrdered = $stmt->fetchAll();

$totalDiterima = 0; $totalDiputus = 0; $totalSisa = 0;
foreach ($klasOrdered as $r) {
    $totalDiterima += (int)$r['diterima'];
    $totalDiputus  += (int)$r['diputus'];
    $totalSisa     += (int)$r['sisa'];
}

/* ---- Chart: klasifikasiPerkara (15=Gugatan, 16=Permohonan) ---- */
$chartSql = "SELECT
    SUM(CASE WHEN CAST(klasifikasiPerkara AS UNSIGNED) = 15 THEN 1 ELSE 0 END) AS gugatan,
    SUM(CASE WHEN CAST(klasifikasiPerkara AS UNSIGNED) = 16 THEN 1 ELSE 0 END) AS permohonan
    FROM data_perkara
    WHERE $dateWhere";
$stmt = $pdo->prepare($chartSql);
$stmt->execute($params);
$chartRow = $stmt->fetch();

$chartGugatanDiterima    = (int)($chartRow['gugatan']    ?? 0);
$chartPermohonanDiterima = (int)($chartRow['permohonan'] ?? 0);

/* ---- Filter tambahan untuk tabel utama ---- */
$filterJenis = trim((string)($_GET['f_jenis'] ?? ''));
$filterKlas  = $_GET['f_klas'] ?? '';
if (!in_array($filterKlas, ['', '15', '16', '0'], true)) $filterKlas = '';

$jenisList = $pdo->query("SELECT DISTINCT TRIM(jenisPerkara) AS j
                          FROM data_perkara
                          WHERE jenisPerkara IS NOT NULL AND TRIM(jenisPerkara) <> ''
                          ORDER BY j ASC")->fetchAll();
$jenisList = array_column($jenisList, 'j');

/* ---- Pagination tabel utama ---- */
$pageTab   = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;
$offsetTab = ($pageTab - 1) * $perPage;

$extraWhere  = [];
$extraParams = [];
if ($filterJenis !== '') {
    $extraWhere[] = "TRIM(jenisPerkara) = :f_jenis";
    $extraParams[':f_jenis'] = $filterJenis;
}
if ($filterKlas !== '') {
    if ($filterKlas === '0') {
        $extraWhere[] = "(klasifikasiPerkara IS NULL OR CAST(klasifikasiPerkara AS UNSIGNED) NOT IN (15,16))";
    } else {
        $extraWhere[] = "CAST(klasifikasiPerkara AS UNSIGNED) = :f_klas";
        $extraParams[':f_klas'] = (int)$filterKlas;
    }
}
$extraSql = $extraWhere ? ' AND ' . implode(' AND ', $extraWhere) : '';

$countSql = "SELECT COUNT(*) FROM data_perkara WHERE $dateWhere $extraSql";
$stmt = $pdo->prepare($countSql);
$stmt->execute(array_merge($params, $extraParams));
$totalTabRows  = (int)$stmt->fetchColumn();
$totalTabPages = max(1, (int)ceil($totalTabRows / $perPage));
if ($pageTab > $totalTabPages) $pageTab = $totalTabPages;
$offsetTab = ($pageTab - 1) * $perPage;

$tabSql = "SELECT IDPerkara, noPerkara, tglPendaftaran,
                  jenisPerkara, klasifikasiPerkara, IDJenisPerkara,
                  tglPutusan, statusAkhir, pihakPertama, pihakKedua
           FROM data_perkara
           WHERE $dateWhere $extraSql
           ORDER BY tglPendaftaran DESC, IDPerkara DESC
           LIMIT $perPage OFFSET $offsetTab";
$stmt = $pdo->prepare($tabSql);
$stmt->execute(array_merge($params, $extraParams));
$perkaraList = $stmt->fetchAll();

/* ---- Daftar tahun ---- */
$yearsAvail = $pdo->query("SELECT DISTINCT YEAR(tglPendaftaran) AS y
                            FROM data_perkara
                            WHERE tglPendaftaran IS NOT NULL
                            ORDER BY y DESC")->fetchAll();
$years = array_column($yearsAvail, 'y');
if (!in_array(date('Y'), $years)) $years[] = date('Y');
rsort($years);

$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
?>

<div class="container py-4 datap-wrap-wide" id="dashboardContent">

  <!-- ================= HEADER ================= -->
  <div class="datap-header">
    <div class="dph-left">
      <span class="dph-badge"><i class="bi bi-check-circle-fill"></i> Terverifikasi</span>
      <h1 class="dph-title">Data Perkara</h1>
      <p class="dph-sub">
        Pengadilan Agama Tilamuta &middot; Periode: <b><?= e($periodeLabel) ?></b>
        <?php if ($syncStatusRow): ?>
          &middot; Sync terakhir:
          <?= e(date('d M H:i', strtotime($syncStatusRow['triggered_at']))) ?>
        <?php endif; ?>
      </p>
    </div>
    <a href="<?= e(base_url('data-perkara.php?reset=1')) ?>" class="dph-logout">
      <i class="bi bi-box-arrow-right"></i> Akhiri Sesi
    </a>
  </div>

  <!-- ================= FILTER ================= -->
  <div class="datap-filter card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end" id="filterForm">
        <input type="hidden" name="f_jenis" value="<?= e($filterJenis) ?>">
        <input type="hidden" name="f_klas"  value="<?= e($filterKlas) ?>">

        <div class="col-md-3">
          <label class="form-label small fw-bold">Mode</label>
          <select name="mode" class="form-select form-select-sm" id="filterMode">
            <option value="year"  <?= $filterMode==='year' ?'selected':'' ?>>Tahunan</option>
            <option value="month" <?= $filterMode==='month'?'selected':'' ?>>Bulanan</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Tahun</label>
          <select name="tahun" class="form-select form-select-sm">
            <?php foreach ($years as $y): ?>
              <option value="<?= (int)$y ?>" <?= $y == $filterYear ? 'selected' : '' ?>><?= (int)$y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3" id="monthField" style="<?= $filterMode==='month'?'':'display:none' ?>">
          <label class="form-label small fw-bold">Bulan</label>
          <select name="bulan" class="form-select form-select-sm">
            <?php for ($i=1; $i<=12; $i++): ?>
              <option value="<?= $i ?>" <?= $i == $filterMonth ? 'selected' : '' ?>>
                <?= $namaBulan[$i] ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button class="btn btn-pintu btn-sm flex-grow-1"><i class="bi bi-funnel"></i> Terapkan</button>
          <a href="<?= e(base_url('data-perkara.php')) ?>" class="btn btn-outline-secondary btn-sm" title="Reset semua filter">
            <i class="bi bi-arrow-clockwise"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- ================= SUMMARY CARDS ================= -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="dstat-card dstat-blue">
        <div class="dstat-icon"><i class="bi bi-inbox-fill"></i></div>
        <div class="dstat-body">
          <div class="dstat-label">Total Diterima</div>
          <div class="dstat-value"><?= number_format($totalDiterima, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="dstat-card dstat-green">
        <div class="dstat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="dstat-body">
          <div class="dstat-label">Total Diputus</div>
          <div class="dstat-value"><?= number_format($totalDiputus, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="dstat-card dstat-yellow">
        <div class="dstat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div class="dstat-body">
          <div class="dstat-label">Sisa Perkara</div>
          <div class="dstat-value"><?= number_format($totalSisa, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= DIAGRAM ================= -->
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100 chart-card">
        <div class="card-header bg-white fw-bold">
          <i class="bi bi-pie-chart-fill text-primary"></i>
          Perbandingan Gugatan vs Permohonan
        </div>
        <div class="card-body d-flex align-items-center justify-content-center" style="min-height:280px;">
          <canvas id="chartKlasifikasi" style="max-height:260px;"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100 chart-card">
        <div class="card-header bg-white fw-bold">
          <i class="bi bi-bar-chart-fill text-success"></i>
          Informasi Total Perkara
        </div>
        <div class="card-body d-flex align-items-center justify-content-center" style="min-height:280px;">
          <canvas id="chartStatus" style="max-height:260px;"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- ================= TABEL KLASIFIKASI ================= -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong><i class="bi bi-table text-warning"></i> Rekap Klasifikasi Perkara</strong>
      <div class="d-flex gap-2">
        <a href="<?= e(base_url('actions/export-perkara.php?type=klasifikasi&format=excel&' . http_build_query(['tahun'=>$filterYear,'mode'=>$filterMode,'bulan'=>$filterMonth]))) ?>"
           class="btn btn-sm btn-outline-success">
          <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <a href="<?= e(base_url('actions/export-perkara.php?type=klasifikasi&format=pdf&' . http_build_query(['tahun'=>$filterYear,'mode'=>$filterMode,'bulan'=>$filterMonth]))) ?>"
           target="_blank" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 datap-table">
          <thead class="table-light">
            <tr>
              <th width="50" class="text-center">No.</th>
              <th>Jenis Perkara</th>
              <th class="text-center" width="120">Klasifikasi</th>
              <th class="text-center" width="110">Diterima</th>
              <th class="text-center" width="110">Diputus</th>
              <th class="text-center" width="110">Sisa</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; foreach ($klasOrdered as $r):
                $kode = (int)$r['klas_code'];
            ?>
              <tr>
                <td class="text-center"><?= $i++ ?></td>
                <td><span class="badge-kat badge-jenis"><?= e($r['jenis']) ?></span></td>
                <td class="text-center">
                  <span class="badge-kat <?= e(klasifikasi_badge_class($kode)) ?>">
                    <?= e(klasifikasi_label($kode)) ?>
                  </span>
                </td>
                <td class="text-center fw-semibold"><?= number_format((int)$r['diterima'], 0, ',', '.') ?></td>
                <td class="text-center text-success fw-semibold"><?= number_format((int)$r['diputus'], 0, ',', '.') ?></td>
                <td class="text-center text-warning fw-semibold"><?= number_format((int)$r['sisa'], 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="3" class="text-end">TOTAL</td>
              <td class="text-center"><?= number_format($totalDiterima, 0, ',', '.') ?></td>
              <td class="text-center text-success"><?= number_format($totalDiputus, 0, ',', '.') ?></td>
              <td class="text-center text-warning"><?= number_format($totalSisa, 0, ',', '.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- ================= TABEL UTAMA ================= -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong><i class="bi bi-list-ul text-primary"></i> Daftar Perkara</strong>
      <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-light text-dark border">
          <?= number_format($totalTabRows, 0, ',', '.') ?> data
        </span>
        <a href="<?= e(base_url('actions/export-perkara.php?type=utama&format=excel&' . http_build_query(['tahun'=>$filterYear,'mode'=>$filterMode,'bulan'=>$filterMonth,'f_jenis'=>$filterJenis,'f_klas'=>$filterKlas]))) ?>"
           class="btn btn-sm btn-outline-success">
          <i class="bi bi-file-earmark-excel"></i> Excel
        </a>
        <a href="<?= e(base_url('actions/export-perkara.php?type=utama&format=pdf&' . http_build_query(['tahun'=>$filterYear,'mode'=>$filterMode,'bulan'=>$filterMonth,'f_jenis'=>$filterJenis,'f_klas'=>$filterKlas]))) ?>"
           target="_blank" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-file-earmark-pdf"></i> PDF
        </a>
      </div>
    </div>

    <!-- FILTER BAR TABEL UTAMA -->
    <div class="datap-filter-bar">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="mode"  value="<?= e($filterMode) ?>">
        <input type="hidden" name="tahun" value="<?= (int)$filterYear ?>">
        <?php if ($filterMode === 'month'): ?>
          <input type="hidden" name="bulan" value="<?= (int)$filterMonth ?>">
        <?php endif; ?>

        <div class="col-md-4">
          <label class="form-label small fw-bold mb-1">
            <i class="bi bi-tag text-primary"></i> Jenis Perkara
          </label>
          <select name="f_jenis" class="form-select form-select-sm">
            <option value="">— Semua Jenis —</option>
            <?php foreach ($jenisList as $j): ?>
              <option value="<?= e($j) ?>" <?= $filterJenis===$j?'selected':'' ?>>
                <?= e($j) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-bold mb-1">
            <i class="bi bi-diagram-3 text-warning"></i> Klasifikasi
          </label>
          <select name="f_klas" class="form-select form-select-sm">
            <option value="">— Semua —</option>
            <option value="15" <?= $filterKlas==='15'?'selected':'' ?>>Gugatan</option>
            <option value="16" <?= $filterKlas==='16'?'selected':'' ?>>Permohonan</option>
            <option value="0"  <?= $filterKlas==='0' ?'selected':'' ?>>Lainnya</option>
          </select>
        </div>

        <div class="col-md-5 d-flex gap-2 flex-wrap">
          <button class="btn btn-pintu btn-sm">
            <i class="bi bi-funnel-fill"></i> Proses Filter
          </button>
          <?php if ($filterJenis !== '' || $filterKlas !== ''): ?>
            <a href="?<?= e(http_build_query([
                'mode'  => $filterMode,
                'tahun' => $filterYear,
                'bulan' => $filterMonth,
            ])) ?>" class="btn btn-outline-danger btn-sm">
              <i class="bi bi-x-circle"></i> Bersihkan Filter
            </a>
          <?php endif; ?>
          <?php if ($filterJenis !== '' || $filterKlas !== ''): ?>
            <span class="badge bg-info-subtle text-info align-self-center ms-auto">
              <i class="bi bi-funnel"></i>
              <?= $filterJenis !== '' ? 'Jenis: ' . e(mb_strimwidth($filterJenis, 0, 25, '...')) : '' ?>
              <?= ($filterJenis !== '' && $filterKlas !== '') ? ' | ' : '' ?>
              <?= $filterKlas !== '' ? 'Klasifikasi: ' . ($filterKlas==='15'?'Gugatan':($filterKlas==='16'?'Permohonan':'Lainnya')) : '' ?>
            </span>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 datap-table">
          <thead class="table-light">
            <tr>
              <th width="50" class="text-center">No.</th>
              <th>Nomor Perkara</th>
              <th class="text-nowrap">Tanggal Register</th>
              <th>Jenis Perkara</th>
              <th class="text-center" width="120">Klasifikasi</th>
              <th class="text-center">Status Perkara</th>
              <th class="text-nowrap">Tanggal Putus</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$perkaraList): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                Tidak ada data untuk filter ini.
              </td></tr>
            <?php else: $no = $offsetTab + 1; foreach ($perkaraList as $p):
              $jenis    = trim((string)($p['jenisPerkara'] ?? ''));
              if ($jenis === '') $jenis = 'Tidak Terdefinisi';
              $kode     = (int)$p['klasifikasiPerkara'];
              $isPutus  = !empty($p['tglPutusan']) && $p['tglPutusan'] !== '0000-00-00';
            ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="fw-semibold"><?= e($p['noPerkara'] ?: $p['IDPerkara']) ?></td>
                <td class="text-nowrap"><?= $p['tglPendaftaran'] ? e(date('d/m/Y', strtotime($p['tglPendaftaran']))) : '-' ?></td>
                <td>
                  <span class="badge-kat badge-jenis" title="<?= e($jenis) ?>">
                    <?= e(mb_strimwidth($jenis, 0, 30, '...')) ?>
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge-kat <?= e(klasifikasi_badge_class($kode)) ?>">
                    <?= e(klasifikasi_label($kode)) ?>
                  </span>
                </td>
                <td class="text-center">
                  <?php if ($isPutus): ?>
                    <span class="badge bg-success-subtle text-success">
                      <i class="bi bi-check-circle-fill"></i> Diputus
                    </span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning">
                      <i class="bi bi-hourglass-split"></i> Berjalan
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap">
                  <?= $isPutus ? e(date('d/m/Y', strtotime($p['tglPutusan']))) : '-' ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalTabPages > 1): ?>
    <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="small text-muted">
        Menampilkan <b><?= number_format($offsetTab + 1, 0, ',', '.') ?></b>
        &ndash;
        <b><?= number_format(min($offsetTab + $perPage, $totalTabRows), 0, ',', '.') ?></b>
        dari <b><?= number_format($totalTabRows, 0, ',', '.') ?></b> perkara
      </div>
      <nav aria-label="Pagination tabel utama">
        <ul class="pagination pagination-sm mb-0">
          <?php
          $baseParams = $_GET;
          unset($baseParams['page']);

          if ($pageTab > 1):
              $baseParams['page'] = $pageTab - 1;
          ?>
            <li class="page-item">
              <a class="page-link" href="?<?= e(http_build_query($baseParams)) ?>">
                <i class="bi bi-chevron-left"></i>
              </a>
            </li>
          <?php else: ?>
            <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
          <?php endif; ?>

          <?php
          $startP = max(1, $pageTab - 3);
          $endP   = min($totalTabPages, $startP + 6);
          if ($endP - $startP < 6) $startP = max(1, $endP - 6);

          if ($startP > 1):
          ?>
            <li class="page-item">
              <a class="page-link" href="?<?= e(http_build_query(array_merge($baseParams, ['page'=>1]))) ?>">1</a>
            </li>
            <?php if ($startP > 2): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($pg = $startP; $pg <= $endP; $pg++):
              $baseParams['page'] = $pg;
          ?>
            <li class="page-item <?= $pg === $pageTab ? 'active' : '' ?>">
              <a class="page-link" href="?<?= e(http_build_query($baseParams)) ?>"><?= $pg ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($endP < $totalTabPages): ?>
            <?php if ($endP < $totalTabPages - 1): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item">
              <a class="page-link" href="?<?= e(http_build_query(array_merge($baseParams, ['page'=>$totalTabPages]))) ?>">
                <?= $totalTabPages ?>
              </a>
            </li>
          <?php endif; ?>

          <?php
          if ($pageTab < $totalTabPages):
              $baseParams['page'] = $pageTab + 1;
          ?>
            <li class="page-item">
              <a class="page-link" href="?<?= e(http_build_query($baseParams)) ?>">
                <i class="bi bi-chevron-right"></i>
              </a>
            </li>
          <?php else: ?>
            <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>

  </div><!-- /tabel utama -->

</div><!-- /container dashboardContent -->


<!-- ============================================================
     SYNC OVERLAY (kalau butuh sync)
     ============================================================ -->
<?php if ($needsAutoSync): ?>
<div class="sync-overlay d-none" id="syncOverlay">
  <div class="so-backdrop"></div>

  <div class="so-card">
    <div class="so-ring-wrap">
      <svg class="so-ring" viewBox="0 0 120 120">
        <circle class="so-ring-track" cx="60" cy="60" r="52"></circle>
        <circle class="so-ring-fill"  cx="60" cy="60" r="52"
                id="soRingFill" stroke-dasharray="326.72" stroke-dashoffset="326.72"></circle>
      </svg>
      <div class="so-ring-center">
        <div class="so-percent" id="soPercent">0<span>%</span></div>
        <div class="so-percent-label">Selesai</div>
      </div>
    </div>

    <h3 class="so-title">
      <i class="bi bi-cloud-arrow-down-fill"></i>
      Sinkronisasi Sedang Berjalan
    </h3>
    <p class="so-subtitle">
      Menarik data terbaru dari database SIPP Pengadilan Agama Tilamuta.
      Dashboard akan tampil otomatis setelah proses selesai.
    </p>

    <div class="so-bar">
      <div class="so-bar-fill" id="soBarFill"></div>
    </div>

    <div class="so-stats">
      <div class="so-stat">
        <div class="ss-icon bg-primary-subtle text-primary">
          <i class="bi bi-file-earmark-text"></i>
        </div>
        <div>
          <div class="ss-label">Halaman</div>
          <div class="ss-value" id="soPage">0 / 0</div>
        </div>
      </div>
      <div class="so-stat">
        <div class="ss-icon bg-success-subtle text-success">
          <i class="bi bi-database-fill-check"></i>
        </div>
        <div>
          <div class="ss-label">Data Tersinkron</div>
          <div class="ss-value" id="soRows">0</div>
        </div>
      </div>
      <div class="so-stat">
        <div class="ss-icon bg-warning-subtle text-warning">
          <i class="bi bi-stopwatch"></i>
        </div>
        <div>
          <div class="ss-label">Waktu Berjalan</div>
          <div class="ss-value" id="soElapsed">0:00</div>
        </div>
      </div>
    </div>

    <div class="so-estimate d-none" id="soEstimate">
      <i class="bi bi-info-circle-fill"></i>
      <span>Total estimasi: <b id="soEstimateRows">0</b> data dalam <b id="soEstimatePages">0</b> halaman</span>
    </div>

    <div class="so-phase" id="soPhase">
      <span class="so-phase-dot"></span>
      <span class="so-phase-text">Menghubungi server SIPP...</span>
    </div>

    <div class="so-error-actions d-none" id="soErrorActions">
      <button type="button" class="btn btn-outline-warning btn-sm" id="btnSkipSync">
        <i class="bi bi-eye"></i> Lanjut ke Dashboard Tanpa Sync
      </button>
    </div>

    <div class="so-tip">
      <i class="bi bi-lightbulb-fill"></i>
      <span id="soTip">Sinkronisasi otomatis dilakukan sekali sehari pada kunjungan pertama.</span>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ================= CHART.JS ================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  // ============ CHART 1: Pie Klasifikasi ============
  var ctx1 = document.getElementById('chartKlasifikasi');
  if (ctx1) {
    var __gugatan    = <?= (int)$chartGugatanDiterima ?>;
    var __permohonan = <?= (int)$chartPermohonanDiterima ?>;
    var __total      = __gugatan + __permohonan;

    new Chart(ctx1, {
      type: 'pie',
      data: {
        labels: ['Gugatan', 'Permohonan'],
        datasets: [{
          data: [__gugatan, __permohonan],
          backgroundColor: ['#1f7a3d', '#f5b301'],
          borderColor: '#fff',
          borderWidth: 3,
          hoverOffset: 12
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 16,
              font: { size: 13, weight: '600' },
              generateLabels: function (chart) {
                var data = chart.data;
                return data.labels.map(function (label, i) {
                  var val = data.datasets[0].data[i];
                  var pct = __total > 0 ? Math.round(val / __total * 100) : 0;
                  return {
                    text: label + ' (' + pct + '%)',
                    fillStyle: data.datasets[0].backgroundColor[i],
                    strokeStyle: data.datasets[0].backgroundColor[i],
                    lineWidth: 0,
                    hidden: false,
                    index: i
                  };
                });
              }
            }
          },
          tooltip: {
            callbacks: {
              label: function (c) {
                var pct = __total > 0 ? Math.round(c.parsed / __total * 100) : 0;
                return ' ' + c.label + ': ' + c.parsed.toLocaleString('id-ID') + ' perkara (' + pct + '%)';
              }
            }
          }
        }
      },
      plugins: [{
        id: 'pieLabels',
        afterDatasetsDraw: function (chart) {
          var ctx = chart.ctx;
          chart.data.datasets.forEach(function (ds, di) {
            var meta = chart.getDatasetMeta(di);
            meta.data.forEach(function (arc, i) {
              var value = ds.data[i];
              if (!value) return;
              var pct = __total > 0 ? Math.round(value / __total * 100) : 0;
              var pos = arc.tooltipPosition();

              ctx.save();
              ctx.fillStyle = '#fff';
              ctx.font = 'bold 14px Segoe UI, sans-serif';
              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              ctx.shadowColor = 'rgba(0,0,0,.35)';
              ctx.shadowBlur = 4;
              ctx.fillText(value.toLocaleString('id-ID'), pos.x, pos.y - 8);
              ctx.font = 'bold 11px Segoe UI, sans-serif';
              ctx.fillText(pct + '%', pos.x, pos.y + 8);
              ctx.restore();
            });
          });
        }
      }]
    });
  }

  // ============ CHART 2: Bar Total ============
  var ctx2 = document.getElementById('chartStatus');
  if (ctx2) {
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: ['Diterima', 'Diputus', 'Sisa'],
        datasets: [{
          label: 'Jumlah Perkara',
          data: [<?= (int)$totalDiterima ?>, <?= (int)$totalDiputus ?>, <?= (int)$totalSisa ?>],
          backgroundColor: ['#0d6efd', '#1f7a3d', '#f5b301'],
          borderRadius: 8,
          borderSkipped: false,
          barThickness: 46
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (c) { return c.parsed.y.toLocaleString('id-ID') + ' perkara'; }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 11 } },
            grid: { color: 'rgba(0,0,0,.04)' }
          },
          x: {
            grid: { display: false },
            ticks: { font: { size: 12, weight: '600' } }
          }
        }
      }
    });
  }

  // ============ Filter: toggle field bulan ============
  var fmode  = document.getElementById('filterMode');
  var fmonth = document.getElementById('monthField');
  if (fmode && fmonth) {
    fmode.addEventListener('change', function () {
      fmonth.style.display = (fmode.value === 'month') ? '' : 'none';
    });
  }
})();
</script>


<!-- ============================================================
     AUTO-SYNC CHUNK - ringan untuk hosting
     ============================================================ -->
<?php if ($needsAutoSync): ?>
<script>
(function () {
  'use strict';

  var overlay = document.getElementById('syncOverlay');
  var dash    = document.getElementById('dashboardContent');
  if (!overlay) { return; }

  var ring      = document.getElementById('soRingFill');
  var percentEl = document.getElementById('soPercent');
  var barFill   = document.getElementById('soBarFill');
  var pageEl    = document.getElementById('soPage');
  var rowsEl    = document.getElementById('soRows');
  var elapsedEl = document.getElementById('soElapsed');
  var phaseWrap = document.getElementById('soPhase');
  var phaseEl   = phaseWrap ? phaseWrap.querySelector('.so-phase-text') : null;
  var estWrap   = document.getElementById('soEstimate');
  var estRows   = document.getElementById('soEstimateRows');
  var estPages  = document.getElementById('soEstimatePages');
  var errActions= document.getElementById('soErrorActions');
  var btnSkip   = document.getElementById('btnSkipSync');
  var tipEl     = document.getElementById('soTip');

  var BASE       = '<?= e(base_url()) ?>';
  var RING_LEN   = 2 * Math.PI * 52;
  var startTime  = Date.now();
  var lastPct    = 0;
  var elapsedTimer  = null;
  var watchdogTimer = null;
  var tipsTimer     = null;
  var finished      = false;

  var TIPS = [
    'Sinkronisasi otomatis dilakukan sekali sehari pada kunjungan pertama.',
    'Data bersumber langsung dari SIPP - sistem informasi penanganan perkara.',
    'Filter Tahun dan Bulan bisa dikombinasikan dengan filter Jenis Perkara.',
    'Anda bisa export data ke Excel atau PDF setelah proses selesai.',
    'Gunakan mode Tahunan untuk melihat rekap setahun penuh.'
  ];

  function lockDashboard() {
    if (dash) { dash.classList.add('sync-locked'); }
    document.body.style.overflow = 'hidden';
    overlay.classList.remove('d-none');
    requestAnimationFrame(function () {
      overlay.classList.add('show');
    });
  }

  function stopTimers() {
    if (elapsedTimer)  { clearInterval(elapsedTimer);  elapsedTimer  = null; }
    if (watchdogTimer) { clearInterval(watchdogTimer); watchdogTimer = null; }
    if (tipsTimer)     { clearInterval(tipsTimer);     tipsTimer     = null; }
  }

  function paint(percent, curr, total, rows) {
    percent = Math.max(lastPct, Math.min(100, Math.round(percent || 0)));
    lastPct = percent;
    if (ring) {
      ring.style.strokeDashoffset = RING_LEN - (RING_LEN * percent / 100);
    }
    if (percentEl) {
      percentEl.innerHTML = percent + '<span>%</span>';
    }
    if (barFill) {
      barFill.style.width = percent + '%';
    }
    if (pageEl) {
      pageEl.textContent = (total > 0) ? (curr + ' / ' + total) : '- / -';
    }
    if (rowsEl) {
      rowsEl.textContent = (rows || 0).toLocaleString('id-ID');
    }
  }

  function setPhase(txt) {
    if (!phaseEl) { return; }
    if (phaseEl.textContent !== txt) {
      phaseEl.style.opacity = 0;
      setTimeout(function () {
        phaseEl.textContent = txt;
        phaseEl.style.opacity = 1;
      }, 180);
    }
  }

  function showError(msg) {
    if (finished) { return; }
    finished = true;
    stopTimers();
    setPhase('ERROR: ' + msg);
    if (phaseWrap) { phaseWrap.classList.add('so-phase-error'); }
    var card = overlay.querySelector('.so-card');
    if (card) { card.classList.add('so-card-error'); }
    if (errActions) { errActions.classList.remove('d-none'); }
  }

  function finishSuccess(totalPages, rows) {
    if (finished) { return; }
    finished = true;
    stopTimers();
    paint(100, totalPages, totalPages, rows);
    setPhase('Selesai! Memuat dashboard...');
    setTimeout(function () {
      location.reload();
    }, 900);
  }

  function startElapsed() {
    elapsedTimer = setInterval(function () {
      var s = Math.floor((Date.now() - startTime) / 1000);
      var m = Math.floor(s / 60);
      var r = s % 60;
      if (elapsedEl) {
        elapsedEl.textContent = m + ':' + (r < 10 ? '0' : '') + r;
      }
    }, 1000);
  }

  function startTips() {
    if (!tipEl) { return; }
    var idx = 0;
    tipEl.style.transition = 'opacity .2s';
    tipsTimer = setInterval(function () {
      idx = (idx + 1) % TIPS.length;
      tipEl.style.opacity = 0;
      setTimeout(function () {
        tipEl.textContent = TIPS[idx];
        tipEl.style.opacity = 1;
      }, 220);
    }, 4000);
  }

  function startWatchdog() {
    watchdogTimer = setTimeout(function () {
      if (finished) { return; }
      showError('Proses terlalu lama. Klik tombol di bawah untuk lanjut.');
    }, 10 * 60 * 1000);
  }

  function processPage(page, totalPages, limit) {
    if (finished) { return; }

    var fd = new FormData();
    fd.append('page', page);

    fetch(BASE + 'actions/sync_chunk.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
      return r.json().catch(function () {
        return { success: false, message: 'HTTP ' + r.status + ' - response tidak valid' };
      });
    })
    .then(function (res) {
      if (finished) { return; }

      if (!res.success) {
        if (res.data && res.data.status === 'SUCCESS') {
          finishSuccess(totalPages, 0);
          return;
        }
        if (res.data && res.data.status === 'FAILED') {
          showError('Sync dihentikan oleh server.');
          return;
        }
        showError(res.message || ('Gagal halaman ' + page));
        return;
      }

      var d = res.data;
      var pct = (d.total_pages > 0) ? Math.round(d.current_page / d.total_pages * 100) : 0;
      paint(pct, d.current_page, d.total_pages, d.synced_rows);
      setPhase('Memproses halaman ' + d.current_page + ' dari ' + d.total_pages + '...');

      if (d.finished || page >= totalPages) {
        finishSuccess(d.total_pages, d.synced_rows);
      } else {
        setTimeout(function () {
          processPage(page + 1, totalPages, limit);
        }, 250);
      }
    })
    .catch(function (err) {
      if (finished) { return; }
      console.error('[PINTU Sync page ' + page + ']', err);
      setPhase('Retry halaman ' + page + '...');
      setTimeout(function () {
        if (!finished && page <= totalPages) {
          processPage(page, totalPages, limit);
        }
      }, 2000);
    });
  }

  if (btnSkip) {
    btnSkip.addEventListener('click', function () {
      window.location.href = BASE + 'data-perkara.php?skip_sync=1';
    });
  }

  // MULAI
  lockDashboard();
  startElapsed();
  startTips();
  startWatchdog();
  paint(1, 0, 0, 0);
  setPhase('Menghubungi server SIPP...');

  var fd = new FormData();
  fd.append('action', 'run');

  fetch(BASE + 'actions/sync_daily.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function (r) { return r.json(); })
  .then(function (res) {
    if (finished) { return; }
    if (!res.success) {
      showError(res.message || 'Server menolak permintaan.');
      return;
    }
    if (res.data && res.data.skipped) {
      setPhase('Data sudah tersinkron. Memuat dashboard...');
      finishSuccess(1, 0);
      return;
    }
    var totalPages = (res.data && res.data.total_pages) || 0;
    var totalRows  = (res.data && res.data.total_rows)  || 0;
    var limit      = (res.data && res.data.limit)       || 100;
    if (estWrap && totalRows > 0) {
      if (estRows)  { estRows.textContent  = totalRows.toLocaleString('id-ID'); }
      if (estPages) { estPages.textContent = totalPages; }
      estWrap.classList.remove('d-none');
    }
    setPhase('Memproses ' + totalPages + ' halaman...');
    paint(2, 0, totalPages, 0);
    processPage(1, totalPages, limit);
  })
  .catch(function (err) {
    if (finished) { return; }
    console.error('[PINTU Sync trigger]', err);
    showError('Kesalahan jaringan saat memulai sync.');
  });

})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>