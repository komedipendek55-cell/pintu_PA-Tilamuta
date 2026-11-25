<?php
require_once __DIR__ . '/includes/bootstrap.php';
track_visitor();

$pdo = Database::pdo();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM perkara WHERE id=? AND status='PUBLISHED' LIMIT 1");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    http_response_code(404);
    $page_title = '404';
    include __DIR__ . '/includes/header.php';
    include __DIR__ . '/includes/navbar.php';
    echo '<div class="container py-5 text-center">
            <i class="bi bi-file-earmark-x display-3 text-muted"></i>
            <h2 class="mt-3">Data tidak ditemukan</h2>
            <a class="btn btn-pintu mt-3" href="' . e(base_url()) . '">Kembali ke Home</a>
          </div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$reqs = $pdo->prepare("SELECT requirement_text, sequence_no
                       FROM persyaratan WHERE perkara_id=? AND is_active=1
                       ORDER BY sequence_no");
$reqs->execute([$id]);
$reqs = $reqs->fetchAll();

$costs = [];
if (!empty($p['cost_type'])) {
    $cs = $pdo->prepare("SELECT id, description, amount, calculation_type
                         FROM biaya_perkara WHERE cost_type=? AND status='ACTIVE' ORDER BY id");
    $cs->execute([$p['cost_type']]);
    $costs = $cs->fetchAll();
}

$catLabel = CATEGORY_LABELS[$p['category']] ?? $p['category'];

// Ambil catatan pihak dinamis dari master cost_types
$catatanPihak = !empty($p['cost_type'])
    ? get_cost_type_note($p['cost_type'])
    : '';

// Fallback default jika catatan di master kosong
if ($catatanPihak === '' && !empty($p['cost_type'])) {
    $catatanPihak = 'Nominal ini merupakan perkiraan awal dan dapat berubah. '
                  . 'Biaya pasti ditetapkan setelah pendaftaran selesai di '
                  . 'Pengadilan Agama Tilamuta.';
}
$page_title = $p['name'];
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-4 detail-wrap">

  <!-- ============ BREADCRUMB ============ -->
  <nav class="detail-breadcrumb" aria-label="breadcrumb">
    <a href="<?= e(base_url()) ?>"><i class="bi bi-house-door"></i> Home</a>
    <span class="bc-sep"><i class="bi bi-chevron-right"></i></span>
    <span class="bc-cat"><?= e($catLabel) ?></span>
    <span class="bc-sep"><i class="bi bi-chevron-right"></i></span>
    <span class="bc-current"><?= e($p['name']) ?></span>
  </nav>

  <!-- ============ HEADER CARD ============ -->
  <div class="detail-hero">
    <div class="dh-bg"></div>
    <div class="dh-content">
      <div class="dh-icon">
        <?php if (!empty($p['icon_path'])): ?>
          <img src="<?= e(base_url($p['icon_path'])) ?>" alt="">
        <?php else: ?>
          <i class="bi bi-file-earmark-text"></i>
        <?php endif; ?>
      </div>
      <div class="dh-text">
        <span class="dh-badge"><?= e($catLabel) ?></span>
        <h1 class="dh-title"><?= e($p['name']) ?></h1>
        <?php if (!empty($p['short_description'])): ?>
          <p class="dh-sub"><?= e($p['short_description']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4">

    <!-- ============ KOLOM KIRI ============ -->
    <div class="col-lg-7">

      <!-- DESKRIPSI -->
      <!-- DESKRIPSI (Collapsible) -->
      <?php if (!empty(trim((string)$p['description_html']))): ?>
      <div class="dc-card dc-green mb-4 dc-collapse">
        <div class="dc-head dc-collapse-head"
             data-bs-toggle="collapse"
             data-bs-target="#descCollapse"
             role="button"
             aria-expanded="false"
             aria-controls="descCollapse"
             tabindex="0">
          <div class="dc-icon-wrap"><i class="bi bi-info-circle"></i></div>
          <h5 class="dc-title">Deskripsi</h5>
          <span class="dc-collapse-hint">
            <span class="dch-sparkle"><i class="bi bi-stars"></i></span>
            Klik untuk membuka
            <i class="bi bi-chevron-down dch-chevron"></i>
          </span>
          <span class="dc-toggle-icon">
            <i class="bi bi-chevron-down"></i>
          </span>
        </div>
        <div class="collapse" id="descCollapse">
          <div class="dc-body rich-content">
            <?= sanitize_html((string)$p['description_html']) ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- PERSYARATAN (KUNING) -->
      <div class="dc-card dc-yellow mb-4">
        <div class="dc-head">
          <div class="dc-icon-wrap"><i class="bi bi-list-check"></i></div>
          <h5 class="dc-title">Persyaratan</h5>
          <a href="<?= e(base_url('print.php?id=' . (int)$p['id'])) ?>"
             target="_blank" class="dc-action">
            <i class="bi bi-printer"></i> Cetak Checklist
          </a>
        </div>
        <div class="dc-body">
          <?php if (!$reqs): ?>
            <p class="text-muted mb-0">Belum ada persyaratan untuk layanan ini.</p>
          <?php else: ?>
            <ol class="req-modern">
              <?php foreach ($reqs as $i => $r): ?>
                <li>
                  <span class="req-num"><?= $i + 1 ?></span>
                  <span class="req-text"><?= e($r['requirement_text']) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>
      </div>

      <!-- KETERANGAN TAMBAHAN (BIRU) -->
      <?php if (!empty(trim((string)$p['additional_notes']))): ?>
      <div class="dc-card dc-blue mb-4">
        <div class="dc-head">
          <div class="dc-icon-wrap"><i class="bi bi-sticky"></i></div>
          <h5 class="dc-title">Keterangan Tambahan</h5>
        </div>
        <div class="dc-body">
          <p class="mb-0" style="white-space: pre-line; line-height:1.65;">
            <?= e($p['additional_notes']) ?>
          </p>
        </div>
      </div>
      <?php endif; ?>

      <!-- ALUR (UNGU) -->
      <?php if (!empty($p['process_flow']) || !empty($p['process_flow_url'])): ?>
      <div class="dc-card dc-purple mb-4">
        <div class="dc-head">
          <div class="dc-icon-wrap"><i class="bi bi-diagram-3"></i></div>
          <h5 class="dc-title">Alur Berperkara / Layanan</h5>
        </div>
        <div class="dc-body">
          <?php if (!empty($p['process_flow'])):
              // Parse numbered steps (1. 2. 3. atau 1) 2) dst.)
              $lines = preg_split('/\r\n|\r|\n/', trim($p['process_flow']));
              $steps = [];
              foreach ($lines as $ln) {
                  $ln = trim($ln);
                  if ($ln === '') continue;
                  $ln = preg_replace('/^\s*\d+[\.\)]\s*/', '', $ln);
                  $steps[] = $ln;
              }
          ?>
            <?php if ($steps): ?>
              <ol class="flow-timeline">
                <?php foreach ($steps as $i => $step): ?>
                  <li>
                    <span class="flow-num"><?= $i + 1 ?></span>
                    <span class="flow-text"><?= e($step) ?></span>
                  </li>
                <?php endforeach; ?>
              </ol>
            <?php else: ?>
              <p class="mb-0" style="white-space: pre-line;"><?= e($p['process_flow']) ?></p>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (!empty($p['process_flow_url'])): ?>
            <a href="<?= e($p['process_flow_url']) ?>" target="_blank" rel="noopener"
               class="flow-link">
              <i class="bi bi-box-arrow-up-right"></i> Lihat detail alur
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- PRODUK AKHIR (HIJAU) -->
      <?php if (!empty($p['final_product'])): ?>
      <div class="dc-card dc-green mb-4">
        <div class="dc-head">
          <div class="dc-icon-wrap"><i class="bi bi-award"></i></div>
          <h5 class="dc-title">Produk Akhir</h5>
        </div>
        <div class="dc-body">
          <p class="mb-0" style="white-space: pre-line; line-height:1.65;">
            <?= e($p['final_product']) ?>
          </p>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /col-lg-7 -->

    <!-- ============ KOLOM KANAN (KALKULATOR) ============ -->
    <div class="col-lg-5">
      <?php if (!empty($p['cost_type']) && $costs): ?>
      <div class="calc-card" id="calculatorCard" data-cost-type="<?= e($p['cost_type']) ?>">
        <div class="calc-head">
          <div class="calc-icon"><i class="bi bi-calculator"></i></div>
          <div>
            <h5 class="calc-title">Kalkulator Estimasi Biaya</h5>
            <p class="calc-sub">Perkiraan biaya <?= e($p['cost_type']) ?></p>
          </div>
        </div>

        <div class="calc-body">

          <?php if ($p['cost_type'] === 'GUGATAN'): ?>
            <div class="calc-field">
              <label><i class="bi bi-person-fill"></i> Jumlah Penggugat</label>
              <input type="number" min="1" value="1" class="form-control" id="qtyPenggugat">
            </div>
            <div class="calc-field">
              <label><i class="bi bi-person-x-fill"></i> Jumlah Tergugat</label>
              <input type="number" min="1" value="1" class="form-control" id="qtyTergugat">
            </div>
          <?php elseif ($p['cost_type'] === 'PERMOHONAN'): ?>
            <div class="calc-field">
              <label><i class="bi bi-person-fill"></i> Jumlah Pemohon</label>
              <input type="number" min="1" value="1" class="form-control" id="qtyPemohon">
            </div>
          <?php endif; ?>

          <div id="costBreakdown" class="calc-breakdown"></div>

          <div class="calc-total">
            <span class="ct-label">Total</span>
            <span class="ct-value" id="totalCost">Rp 0</span>
          </div>

          <?php if ($catatanPihak !== ''): ?>
            <p class="cost-note">
              <em><?= e($catatanPihak) ?></em>
            </p>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
        <!-- Kartu info jika tidak ada kalkulator -->
        <div class="dc-card dc-blue">
          <div class="dc-head">
            <div class="dc-icon-wrap"><i class="bi bi-info-lg"></i></div>
            <h5 class="dc-title">Informasi Biaya</h5>
          </div>
          <div class="dc-body">
            <p class="mb-0 text-muted small">
              Rincian biaya untuk layanan ini belum dikonfigurasi.
              Silakan hubungi petugas PTSP untuk informasi lebih lanjut.
            </p>
          </div>
        </div>
      <?php endif; ?>
    </div><!-- /col-lg-5 -->

  </div><!-- /row -->
</div>

<script id="costsData" type="application/json"><?= json_encode($costs, JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(base_url('assets/js/calculator.js')) ?>"></script>

<?php include __DIR__ . '/includes/accessibility.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>