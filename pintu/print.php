<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = Database::pdo();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM perkara WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); die('Data tidak ditemukan'); }

$reqs = $pdo->prepare("SELECT requirement_text, sequence_no FROM persyaratan WHERE perkara_id=? AND is_active=1 ORDER BY sequence_no");
$reqs->execute([$id]);
$reqs = $reqs->fetchAll();

$logo     = get_setting('site_logo');
$instansi = get_setting('site_instansi', cfg('instansi'));
$appName  = get_setting('site_short_name', cfg('app_name'));
$catLabel = CATEGORY_LABELS[$p['category']] ?? $p['category'];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Checklist <?= e($p['name']) ?> &mdash; <?= e($instansi) ?></title>
<style>
  @page { size: A4; margin: 1cm 1cm 1.5cm 1cm; }
  * { box-sizing: border-box; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11pt;
    color: #000;
    margin: 0;
    background: #fff;
  }
  .wrap { max-width: 21cm; margin: 0 auto; padding: 12px 0; }

  /* ============ Header ============ */
  .header {
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 3px double #000;
    padding-bottom: 8px;
    margin-bottom: 15px;
  }
  .logo { height: 80px; width: auto; max-width: 100px; object-fit: contain; }
  .logo-fallback {
    height: 80px; width: 80px; display: flex; align-items: center; justify-content: center;
    font-size: 2rem; color: #1f7a3d; border: 1px solid #ccc; border-radius: 8px;
  }
  .header-text { text-align: center; flex: 1; }
  .header-text h1 { font-size: 16pt; margin: 0; letter-spacing: .5px; }
  .header-text h2 { font-size: 13pt; margin: 4px 0; font-weight: normal; }
  .header-text p  { margin: 2px 0; font-size: 10pt; }
  .header-text .instansi-sub { font-size: 10pt; margin-top: 2px; }

  /* ============ Data Diri ============ */
  .data { margin-bottom: 15px; }
  .data table { width: 100%; border-collapse: collapse; }
  .data td { padding: 4px 0; vertical-align: top; }
  .data td:first-child { width: 180px; }
  .data td:nth-child(2) { width: 10px; }
  .data td:last-child { border-bottom: 1px dotted #000; height: 18px; }

  /* ============ Tabel Persyaratan ============ */
  table.persyaratan { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.persyaratan th,
  table.persyaratan td {
    border: 1px solid #000;
    padding: 6px 8px;
    vertical-align: top;
  }
  table.persyaratan th { background: #e8e8e8; text-align: center; font-weight: bold; }
  table.persyaratan tr { page-break-inside: avoid; }
  .col-no          { width: 5%;  text-align: center; }
  .col-persyaratan { width: 40%; }
  .col-cek         { width: 15%; text-align: center; }
  .col-ket         { width: 40%; }
  .checkbox {
    display: inline-block;
    width: 16px; height: 16px;
    border: 1.5px solid #000;
    border-radius: 2px;
    vertical-align: middle;
  }
  /* Baris keterangan tinggi */
  table.persyaratan td.col-ket { height: 26px; }

    /* ============ Keterangan Tambahan ============ */
  .keterangan-tambahan {
    margin-top: 18px;
    padding: 12px 16px;
    border: 1px solid #999;
    border-left: 4px solid #1f7a3d;
    background: #f5faf6;
    border-radius: 4px;
    page-break-inside: avoid;
  }
  .kt-title {
    font-weight: bold;
    font-size: 11pt;
    color: #1f7a3d;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .kt-icon {
    font-size: 12pt;
  }
  .kt-body {
    font-size: 10.5pt;
    line-height: 1.55;
    color: #222;
    white-space: pre-line;
  }

  @media print {
    .keterangan-tambahan {
      background: #f5faf6 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
  }
  
  /* ============ Footer ============ */
  .footer {
    margin-top: 15px;
    font-size: 9pt;
    font-style: italic;
    color: #333;
    border-top: 1px solid #999;
    padding-top: 6px;
  }

  /* ============ Tombol Print ============ */
  .print-btn { margin: 15px 0; text-align: right; }
  .print-btn button {
    padding: 8px 16px;
    font-size: 11pt;
    background: #1f7a3d;
    color: #fff;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
  }
  .print-btn button:hover { background: #155c2c; }

  /* ============ Print Mode ============ */
  @media print {
    body { background: #fff; }
    .print-btn { display: none !important; }
    .wrap { padding: 0; }
  }
</style>
</head>
<body>
<div class="wrap">

  <div class="print-btn">
    <button onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
  </div>

  <!-- ============ HEADER ============ -->
  <div class="header">
    <?php if ($logo): ?>
      <img src="<?= e(base_url($logo)) ?>" alt="Logo" class="logo">
    <?php else: ?>
      <div class="logo-fallback"><i>⚖️</i></div>
    <?php endif; ?>
    <div class="header-text">
      <h1><?= e(strtoupper($instansi)) ?></h1>
      <h2>Checklist Persyaratan <?= e($catLabel) ?> &mdash; <?= e($p['name']) ?></h2>
      <p>Tanggal cetak: <?= date('d-m-Y') ?></p>
    </div>
  </div>

  <!-- ============ DATA DIRI PEMOHON ============ -->
  <div class="data">
    <table>
      <tr><td>Nomor HP (Whatsapp)</td><td>:</td><td></td></tr>
      <tr><td>Alamat Email</td><td>:</td><td></td></tr>
      <tr><td>Nomor Rekening</td><td>:</td><td></td></tr>
      <tr><td>Bank</td><td>:</td><td></td></tr>
      <tr><td>Atas Nama Bank</td><td>:</td><td></td></tr>
    </table>
  </div>

  <!-- ============ TABEL PERSYARATAN ============ -->
  <table class="persyaratan">
    <thead>
      <tr>
        <th class="col-no">No</th>
        <th class="col-persyaratan">Persyaratan</th>
        <th class="col-cek">Cek List</th>
        <th class="col-ket">Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$reqs): ?>
        <tr>
          <td colspan="4" style="text-align:center;color:#888;padding:20px">
            Belum ada persyaratan yang diinput untuk layanan ini.
          </td>
        </tr>
      <?php else: $no = 1; foreach ($reqs as $r): ?>
        <tr>
          <td class="col-no"><?= $no++ ?></td>
          <td><?= e($r['requirement_text']) ?></td>
          <td class="col-cek"><span class="checkbox"></span></td>
          <td class="col-ket"></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  
  <!-- ============ KETERANGAN TAMBAHAN ============ -->
  <?php if (!empty(trim((string)$p['additional_notes']))): ?>
  <div class="keterangan-tambahan">
    <div class="kt-title">
      <span class="kt-icon">ℹ️</span> Keterangan Tambahan
    </div>
    <div class="kt-body">
      <?= nl2br(e($p['additional_notes'])) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ============ FOOTER ============ -->
  <div class="footer">
    Sumber informasi: <?= e($appName) ?> &mdash; <?= e($instansi) ?>.
    Checklist ini hanya untuk kemudahan Anda mempersiapkan dokumen.
  </div>

</div>
</body>
</html>