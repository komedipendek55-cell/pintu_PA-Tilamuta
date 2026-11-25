<?php
/**
 * Export data perkara ke Excel / PDF (print view).
 * Param: type=klasifikasi|utama, format=excel|pdf, tahun, mode, bulan
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = Database::pdo();
$type   = $_GET['type']   ?? 'utama';
$format = $_GET['format'] ?? 'excel';
$year   = (int)($_GET['tahun'] ?? date('Y'));
$mode   = $_GET['mode']   ?? 'year';
$month  = (int)($_GET['bulan'] ?? date('m'));

if (!in_array($type, ['klasifikasi', 'utama'], true)) $type = 'utama';
if (!in_array($format, ['excel', 'pdf'], true))      $format = 'excel';
if (!in_array($mode, ['year', 'month'], true))       $mode = 'year';

if ($mode === 'month') {
    $dateWhere = "YEAR(tglPendaftaran) = :y AND MONTH(tglPendaftaran) = :m";
    $params    = [':y' => $year, ':m' => $month];
    $periode   = date('F Y', strtotime("$year-$month-01"));
} else {
    $dateWhere = "YEAR(tglPendaftaran) = :y";
    $params    = [':y' => $year];
    $periode   = "Tahun $year";
}

$instansi = get_setting('site_instansi', cfg('instansi'));
$logo     = get_setting('site_logo');

/* ============ DATA ============ */
if ($type === 'klasifikasi') {
    $stmt = $pdo->prepare("SELECT
        COALESCE(NULLIF(TRIM(jenisPerkara), ''), 'TIDAK TERDEFINISI') AS jenis,
        CAST(klasifikasiPerkara AS UNSIGNED) AS klas_code,
        COUNT(*) AS diterima,
        SUM(CASE WHEN tglPutusan IS NOT NULL AND tglPutusan <> '0000-00-00' THEN 1 ELSE 0 END) AS diputus,
        SUM(CASE WHEN tglPutusan IS NULL OR tglPutusan = '0000-00-00' THEN 1 ELSE 0 END) AS sisa
        FROM data_perkara WHERE $dateWhere
        GROUP BY jenis, klas_code
        ORDER BY diterima DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $title = 'Rekap Klasifikasi Perkara';
} else {
    $stmt = $pdo->prepare("SELECT IDPerkara, noPerkara, tglPendaftaran,
                              jenisPerkara, IDJenisPerkara, klasifikasiPerkara, tglPutusan
                       FROM data_perkara WHERE $dateWhere
                       ORDER BY tglPendaftaran DESC LIMIT 5000");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $title = 'Daftar Perkara';
}

/* ============================================================
   EXPORT EXCEL (.xls via HTML table — dibuka Excel/LibreOffice)
   ============================================================ */
if ($format === 'excel') {
    $filename = strtolower(str_replace(' ', '-', $title)) . '-' . $year
              . ($mode === 'month' ? '-' . sprintf('%02d', $month) : '') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    ?>
<html><head><meta charset="utf-8"></head><body>
<table border="1" cellpadding="6" cellspacing="0">
  <tr><td colspan="<?= $type === 'klasifikasi' ? 5 : 6 ?>" style="font-size:16px;font-weight:bold;text-align:center;">
    <?= htmlspecialchars($instansi) ?>
  </td></tr>
  <tr><td colspan="<?= $type === 'klasifikasi' ? 5 : 6 ?>" style="text-align:center;font-weight:bold;">
    <?= htmlspecialchars($title) ?> &mdash; <?= htmlspecialchars($periode) ?>
  </td></tr>
  <tr><td colspan="<?= $type === 'klasifikasi' ? 5 : 6 ?>" style="text-align:center;font-size:11px;">
    Dicetak: <?= date('d-m-Y H:i') ?>
  </td></tr>
  <tr><td colspan="<?= $type === 'klasifikasi' ? 5 : 6 ?>"></td></tr>

  <?php if ($type === 'klasifikasi'): ?>
    <thead>
    <tr style="background:#e8f5ec;font-weight:bold;">
        <th>No.</th><th>Jenis Perkara</th><th>Klasifikasi</th>
        <th>Diterima</th><th>Diputus</th><th>Sisa</th>
    </tr>
    </thead><tbody>
      <?php $i=1;$td=0;$tp=0;$ts=0; foreach ($rows as $r):
        $td += (int)$r['diterima']; $tp += (int)$r['diputus']; $ts += (int)$r['sisa']; ?>
        <tr>
          <td align="center"><?= $i++ ?></td>
          <td><?= htmlspecialchars($r['klas']) ?></td>
          <td align="center"><?= (int)$r['diterima'] ?></td>
          <td align="center"><?= (int)$r['diputus'] ?></td>
          <td align="center"><?= (int)$r['sisa'] ?></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f5f5f5;font-weight:bold;">
        <td colspan="3" align="right">TOTAL</td>
        <td align="center"><?= $td ?></td>
        <td align="center"><?= $tp ?></td>
        <td align="center"><?= $ts ?></td>
      </tr>
    </tbody>
  <?php else: ?>
    <thead>
      <tr style="background:#e8f5ec;font-weight:bold;">
        <th>No.</th><th>Nomor Perkara</th><th>Tanggal Register</th>
        <th>Jenis Perkara</th><th>Klasifikasi</th><th>Status Perkara</th><th>Tanggal Putus</th>
      </tr>
    </thead><tbody>
    <?php $i=1; foreach ($rows as $r):
        $jenis = trim((string)($r['jenisPerkara'] ?? ''));
        if ($jenis === '') $jenis = 'Tidak Terdefinisi';
        $kode  = (int)$r['klasifikasiPerkara'];
        $klab  = ($kode === 15) ? 'Gugatan' : (($kode === 16) ? 'Permohonan' : 'Lainnya');
        $isPutus = !empty($r['tglPutusan']) && $r['tglPutusan'] !== '0000-00-00'; ?>
        <tr>
          <td align="center"><?= $i++ ?></td>
          <td><?= htmlspecialchars($r['noPerkara'] ?: $r['IDPerkara']) ?></td>
          <td align="center"><?= $r['tglPendaftaran'] ? date('d/m/Y', strtotime($r['tglPendaftaran'])) : '—' ?></td>
          <td><?= htmlspecialchars($jenis) ?></td>
          <td align="center"><?= $klab ?></td>
          <td align="center"><?= $isPutus ? 'Diputus' : 'Berjalan' ?></td>
          <td align="center"><?= $isPutus ? date('d/m/Y', strtotime($r['tglPutusan'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  <?php endif; ?>
</table>
</body></html>
<?php
    exit;
}

/* ============================================================
   EXPORT PDF — Print-friendly page (auto-print)
   ============================================================ */
?><!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($periode) ?></title>
<style>
  @page { size: A4 landscape; margin: 1cm; }
  body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0; color: #000; }
  .wrap { max-width: 28cm; margin: 0 auto; padding: 10px; }
  .header { display: flex; align-items: center; gap: 14px; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 14px; }
  .header img { height: 70px; }
  .header .ht { flex: 1; text-align: center; }
  .ht h1 { margin: 0; font-size: 15pt; letter-spacing: .5px; }
  .ht h2 { margin: 4px 0; font-size: 12pt; font-weight: normal; }
  .ht p { margin: 2px 0; font-size: 9.5pt; color: #333; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
  th { background: #e8f5ec; text-align: center; font-weight: bold; }
  .no { width: 40px; text-align: center; }
  .c { text-align: center; }
  tfoot td { background: #f5f5f5; font-weight: bold; }
  .footer { margin-top: 14px; font-size: 9pt; font-style: italic; color: #333; text-align: center; }
  .no-print { text-align: right; margin-bottom: 10px; }
  .btn-print { padding: 8px 16px; background: #1f7a3d; color: #fff; border: 0; border-radius: 6px; font-size: 11pt; cursor: pointer; }
  @media print { .no-print { display: none; } }
</style>
</head><body>
<div class="wrap">

  <div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
  </div>

  <div class="header">
    <?php if ($logo): ?>
      <img src="<?= e(base_url($logo)) ?>" alt="Logo">
    <?php endif; ?>
    <div class="ht">
      <h1><?= e(strtoupper($instansi)) ?></h1>
      <h2><?= htmlspecialchars($title) ?> &mdash; <?= htmlspecialchars($periode) ?></h2>
      <p>Dicetak: <?= date('d-m-Y H:i') ?></p>
    </div>
  </div>

  <?php if ($type === 'klasifikasi'): ?>
    <table>
      <thead>
        <tr>
          <th class="no">No.</th>
          <th>Jenis Perkara</th>
		  <th style="width:120px">Klasifikasi</th>
		  <th style="width:120px">Diterima</th>
          <th style="width:120px">Diputus</th>
          <th style="width:120px">Sisa</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1;$td=0;$tp=0;$ts=0; foreach ($rows as $r):
          $td += (int)$r['diterima']; $tp += (int)$r['diputus']; $ts += (int)$r['sisa'];
          $kode = (int)$r['klas_code'];
          $klab = ($kode === 15) ? 'Gugatan' : (($kode === 16) ? 'Permohonan' : 'Lainnya'); ?>
          <tr>
            <td class="no"><?= $i++ ?></td>
            <td><?= htmlspecialchars($r['jenis']) ?></td>
            <td class="c"><?= $klab ?></td>
            <td class="c"><?= number_format((int)$r['diterima'], 0, ',', '.') ?></td>
            <td class="c"><?= number_format((int)$r['diputus'], 0, ',', '.') ?></td>
            <td class="c"><?= number_format((int)$r['sisa'], 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="c">TOTAL</td>
          <td class="c"><?= number_format($td, 0, ',', '.') ?></td>
          <td class="c"><?= number_format($tp, 0, ',', '.') ?></td>
          <td class="c"><?= number_format($ts, 0, ',', '.') ?></td>
        </tr>
      </tfoot>
    </table>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th class="no">No.</th>
          <th>Nomor Perkara</th>
          <th style="width:110px">Tanggal Register</th>
          <th>Jenis Perkara</th>
          <th style="width:110px">Klasifikasi</th>
          <th style="width:100px">Status</th>
          <th style="width:110px">Tanggal Putus</th>
        </tr>
      </thead>
      <tbody>
      <?php $i=1; foreach ($rows as $r):
          $jenis = trim((string)($r['jenisPerkara'] ?? ''));
          if ($jenis === '') $jenis = 'Tidak Terdefinisi';
          $kode  = (int)$r['klasifikasiPerkara'];
          $klab  = ($kode === 15) ? 'Gugatan' : (($kode === 16) ? 'Permohonan' : 'Lainnya');
          $isPutus = !empty($r['tglPutusan']) && $r['tglPutusan'] !== '0000-00-00'; ?>
          <tr>
            <td class="no"><?= $i++ ?></td>
            <td><?= htmlspecialchars($r['noPerkara'] ?: $r['IDPerkara']) ?></td>
            <td class="c"><?= $r['tglPendaftaran'] ? date('d/m/Y', strtotime($r['tglPendaftaran'])) : '—' ?></td>
            <td><?= htmlspecialchars($jenis) ?></td>
            <td class="c"><?= $klab ?></td>
            <td class="c"><?= $isPutus ? 'Diputus' : 'Berjalan' ?></td>
            <td class="c"><?= $isPutus ? date('d/m/Y', strtotime($r['tglPutusan'])) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="footer">
    Sumber: <?= e(cfg('app_full_name')) ?> &mdash; <?= e($instansi) ?>.
  </div>

</div>
<script>
  // Auto-open print dialog saat halaman siap
  window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });
</script>
</body></html>