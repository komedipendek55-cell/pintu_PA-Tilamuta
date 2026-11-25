<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_PANITERA_HUKUM]);

$pdo = Database::pdo();
$page_title = 'Sync Data Perkara';

/* Statistik tabel data_perkara */
$totalPerkara = (int)$pdo->query("SELECT COUNT(*) FROM data_perkara")->fetchColumn();
$totalTahunIni = (int)$pdo->query("SELECT COUNT(*) FROM data_perkara WHERE YEAR(tglPendaftaran)=YEAR(CURDATE())")->fetchColumn();
$lastSync = $pdo->query("SELECT * FROM sync_logs ORDER BY id DESC LIMIT 1")->fetch();

$cfg = cfg('sipp_sync');

include __DIR__ . '/../includes/admin_header.php';
?>

<!-- =================== STATISTIK =================== -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label small text-muted text-uppercase">Total Data Perkara</div>
            <div class="fs-4 fw-bold text-success" id="statTotal"><?= number_format($totalPerkara, 0, ',', '.') ?></div>
          </div>
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-database"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label small text-muted text-uppercase">Tahun <?= date('Y') ?></div>
            <div class="fs-4 fw-bold text-primary"><?= number_format($totalTahunIni, 0, ',', '.') ?></div>
          </div>
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-check"></i></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small text-uppercase mb-1">Sinkronisasi Terakhir</div>
        <?php if ($lastSync): ?>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-<?= $lastSync['status']==='SUCCESS'?'success':($lastSync['status']==='FAILED'?'danger':'warning') ?>">
              <?= e($lastSync['status']) ?>
            </span>
            <strong><?= e(strtoupper($lastSync['mode'])) ?></strong>
            <?php if ($lastSync['year']): ?><small class="text-muted">(<?= (int)$lastSync['year'] ?>)</small><?php endif; ?>
          </div>
          <div class="small text-muted">
            <i class="bi bi-clock"></i> <?= e(date('d M Y H:i', strtotime($lastSync['started_at']))) ?>
            &middot; <?= (int)$lastSync['total_inserted'] ?> ditambah, <?= (int)$lastSync['total_updated'] ?> diperbarui
          </div>
        <?php else: ?>
          <p class="small text-muted mb-0">Belum pernah sinkronisasi.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- =================== PANEL SYNC =================== -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white fw-bold">
    <i class="bi bi-arrow-repeat text-success"></i> Pilih Metode Sinkronisasi
  </div>
  <div class="card-body">

    <div class="sync-modes">
      <!-- FULL -->
      <div class="sync-card" data-mode="full">
        <div class="sc-icon bg-danger-subtle text-danger">
          <i class="bi bi-cloud-download"></i>
        </div>
        <div class="sc-body">
          <h6 class="sc-title">Sinkronisasi Penuh</h6>
          <p class="sc-desc">Ambil <b>semua data historis</b> dari SIPP. Gunakan hanya untuk sinkronisasi pertama kali atau rebuild database.</p>
          <button class="btn btn-sm btn-danger btn-sync" data-mode="full">
            <i class="bi bi-play-fill"></i> Mulai Full Sync
          </button>
        </div>
      </div>

      <!-- CURRENT YEAR -->
      <div class="sync-card" data-mode="current_year">
        <div class="sc-icon bg-success-subtle text-success">
          <i class="bi bi-calendar-check"></i>
        </div>
        <div class="sc-body">
          <h6 class="sc-title">Tahun Berjalan (<?= date('Y') ?>)</h6>
          <p class="sc-desc">Ambil data <b>tahun <?= date('Y') ?></b> saja. Rekomendasi untuk sinkronisasi rutin harian/mingguan.</p>
          <button class="btn btn-sm btn-success btn-sync" data-mode="current_year">
            <i class="bi bi-play-fill"></i> Mulai Sync <?= date('Y') ?>
          </button>
        </div>
      </div>

      <!-- SPECIFIC YEAR -->
      <div class="sync-card" data-mode="year">
        <div class="sc-icon bg-warning-subtle text-warning">
          <i class="bi bi-calendar-event"></i>
        </div>
        <div class="sc-body">
          <h6 class="sc-title">Tahun Tertentu</h6>
          <p class="sc-desc">Ambil data dari <b>tahun yang dipilih</b>. Cocok untuk menarik ulang data historis tertentu.</p>
          <div class="d-flex gap-2">
            <input type="number" class="form-control form-control-sm" id="syncYear"
                   min="2000" max="<?= date('Y') + 1 ?>" value="<?= date('Y') - 1 ?>"
                   style="max-width:110px;">
            <button class="btn btn-sm btn-warning btn-sync" data-mode="year">
              <i class="bi bi-play-fill"></i> Mulai
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- API Info -->
    <div class="alert alert-light border small mt-4 mb-0">
      <i class="bi bi-info-circle text-primary"></i>
      <b>Sumber data:</b>
      <code><?= e($cfg['api_url']) ?></code>
      &middot; Batch: <b><?= (int)$cfg['batch_limit'] ?></b> data/halaman
      &middot; Timeout: <b><?= (int)$cfg['timeout'] ?> detik</b>
    </div>

  </div>
</div>

<!-- =================== PROGRESS PANEL =================== -->
<div class="card border-0 shadow-sm mb-4 d-none" id="progressPanel">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-hourglass-split text-warning"></i> Proses Sinkronisasi Berjalan</strong>
    <span class="badge bg-warning" id="progressStatus">RUNNING</span>
  </div>
  <div class="card-body">

    <div class="d-flex justify-content-between mb-2 small">
      <span><b id="progressLabel">Menyiapkan...</b></span>
      <span><b id="progressPercent">0%</b></span>
    </div>
    <div class="progress mb-3" style="height:10px;">
      <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
           id="progressBar" style="width:0%"></div>
    </div>

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="mini-stat"><span class="ms-label">Halaman</span><span class="ms-value" id="statPage">0/0</span></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="mini-stat"><span class="ms-label">Diproses</span><span class="ms-value text-primary" id="statProcessed">0</span></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="mini-stat"><span class="ms-label">Ditambah</span><span class="ms-value text-success" id="statInserted">0</span></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="mini-stat"><span class="ms-label">Gagal</span><span class="ms-value text-danger" id="statFailed">0</span></div>
      </div>
    </div>

    <!-- Console log -->
    <div class="sync-console" id="syncConsole"></div>

    <div class="d-flex gap-2 mt-3">
      <button class="btn btn-sm btn-outline-danger" id="btnStop" disabled>
        <i class="bi bi-x-circle"></i> Hentikan
      </button>
      <button class="btn btn-sm btn-outline-secondary" id="btnClose" disabled>
        <i class="bi bi-x-lg"></i> Tutup
      </button>
    </div>

  </div>
</div>

<!-- =================== RIWAYAT =================== -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-clock-history text-primary"></i> Riwayat Sinkronisasi</strong>
    <a href="?" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th width="60">#</th>
            <th>Waktu</th>
            <th>Mode</th>
            <th class="text-center">Data</th>
            <th class="text-center">Ditambah</th>
            <th class="text-center">Update</th>
            <th class="text-center">Gagal</th>
            <th class="text-center">Status</th>
            <th>User</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $logs = $pdo->query("SELECT sl.*, u.username FROM sync_logs sl
                               LEFT JOIN users u ON u.id = sl.user_id
                               ORDER BY sl.id DESC LIMIT 20")->fetchAll();
          if (!$logs): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada riwayat sinkronisasi.</td></tr>
          <?php else: foreach ($logs as $l): ?>
            <tr>
              <td>#<?= (int)$l['id'] ?></td>
              <td class="small"><?= e(date('d M Y H:i', strtotime($l['started_at']))) ?></td>
              <td>
                <span class="badge bg-secondary-subtle text-secondary">
                  <?= e(strtoupper($l['mode'])) ?>
                  <?php if ($l['year']): ?>&middot; <?= (int)$l['year'] ?><?php endif; ?>
                </span>
              </td>
              <td class="text-center"><?= number_format((int)$l['total_data'], 0, ',', '.') ?></td>
              <td class="text-center text-success fw-semibold"><?= number_format((int)$l['total_inserted'], 0, ',', '.') ?></td>
              <td class="text-center text-primary fw-semibold"><?= number_format((int)$l['total_updated'], 0, ',', '.') ?></td>
              <td class="text-center <?= (int)$l['total_failed'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <?= number_format((int)$l['total_failed'], 0, ',', '.') ?>
              </td>
              <td class="text-center">
                <?php
                $cls = ['SUCCESS'=>'success','FAILED'=>'danger','PARTIAL'=>'warning','RUNNING'=>'info'][$l['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $cls ?>"><?= e($l['status']) ?></span>
              </td>
              <td class="small text-muted"><?= e($l['username'] ?? '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- =================== JS =================== -->
<script>
(function(){
  'use strict';

  var BASE_URL  = window.PINTU_BASE || '/pintu/';
  var CSRF      = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  var panel     = document.getElementById('progressPanel');
  var bar       = document.getElementById('progressBar');
  var label     = document.getElementById('progressLabel');
  var percentEl = document.getElementById('progressPercent');
  var statusEl  = document.getElementById('progressStatus');
  var consoleEl = document.getElementById('syncConsole');
  var btnStop   = document.getElementById('btnStop');
  var btnClose  = document.getElementById('btnClose');

  var statPage  = document.getElementById('statPage');
  var statProc  = document.getElementById('statProcessed');
  var statIns   = document.getElementById('statInserted');
  var statFail  = document.getElementById('statFailed');

  var running   = false;
  var shouldStop = false;
  var logId     = null;

  function fmt(n){ return (n||0).toLocaleString('id-ID'); }

  function log(msg, type){
    var cls = type ? 'log-'+type : '';
    var time = new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
    var line = document.createElement('div');
    line.className = 'log-line ' + cls;
    line.innerHTML = '<span class="log-time">' + time + '</span> ' + msg;
    consoleEl.appendChild(line);
    consoleEl.scrollTop = consoleEl.scrollHeight;
  }

  function resetUI(){
    bar.style.width = '0%';
    percentEl.textContent = '0%';
    label.textContent = 'Menyiapkan...';
    statusEl.textContent = 'RUNNING';
    statusEl.className = 'badge bg-warning';
    consoleEl.innerHTML = '';
    statPage.textContent = '0/0';
    statProc.textContent = '0';
    statIns.textContent  = '0';
    statFail.textContent = '0';
    panel.classList.remove('d-none');
    btnClose.disabled = true;
    btnStop.disabled = false;
    shouldStop = false;
  }

  async function postJSON(url, data){
    var fd = new FormData();
    Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
    fd.append('csrf_token', CSRF);
    var res = await fetch(url, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return await res.json();
  }

  async function startSync(mode, year){
    if (running) return;
    running = true;
    resetUI();

    try {
      // ---------- STEP 1: Start ----------
      log('Memulai sinkronisasi mode <b>' + mode + '</b>' + (year ? ' tahun ' + year : '') + '...', 'info');
      var startRes = await postJSON(BASE_URL + 'actions/sync_trigger.php', {
        action: 'start', mode: mode, year: year || ''
      });

      if (!startRes.success){
        log('❌ ' + (startRes.message || 'Gagal memulai'), 'error');
        finishWithStatus('FAILED');
        return;
      }

      logId = startRes.data.log_id;
      var totalPages = startRes.data.total_pages;
      var totalRows  = startRes.data.total_rows;

      log('✅ Terhubung ke API SIPP', 'success');
      log('📊 Total data di sumber: <b>' + fmt(totalRows) + '</b> (' + totalPages + ' halaman)', 'info');

      if (totalPages === 0){
        log('Tidak ada data untuk disinkronisasi.', 'warn');
        await finalize();
        return;
      }

      // ---------- STEP 2: Loop per halaman ----------
      var totalInserted = 0, totalUpdated = 0, totalFailed = 0, totalProcessed = 0;
      var limit = startRes.data.limit;

      for (var p = 1; p <= totalPages; p++){
        if (shouldStop){
          log('⏹ Dibatalkan oleh pengguna pada halaman ' + p, 'warn');
          break;
        }

        log('📥 Memproses halaman ' + p + '/' + totalPages + '...', 'info');

        var pageRes = await postJSON(BASE_URL + 'actions/sync_trigger.php', {
          action: 'page', log_id: logId, mode: mode, year: year || '', page: p
        });

        if (!pageRes.success){
          log('❌ Halaman ' + p + ' gagal: ' + (pageRes.message || ''), 'error');
          totalFailed += limit;
          continue;
        }

        totalInserted  += pageRes.data.inserted || 0;
        totalUpdated   += pageRes.data.updated  || 0;
        totalFailed    += pageRes.data.failed   || 0;
        totalProcessed += pageRes.data.processed || 0;

        // Update UI
        var pct = Math.round((p / totalPages) * 100);
        bar.style.width = pct + '%';
        percentEl.textContent = pct + '%';
        label.textContent = 'Halaman ' + p + ' dari ' + totalPages;
        statPage.textContent = p + '/' + totalPages;
        statProc.textContent = fmt(totalProcessed);
        statIns.textContent  = fmt(totalInserted);
        statFail.textContent = fmt(totalFailed);

        log('   └ ✅ +' + (pageRes.data.inserted||0)
            + ' baru, ~' + (pageRes.data.updated||0) + ' update'
            + (pageRes.data.failed ? ', ❌ ' + pageRes.data.failed + ' gagal' : ''), 'success');
      }

      // ---------- STEP 3: Finish ----------
      await finalize();

    } catch(err){
      console.error(err);
      log('❌ Error: ' + err.message, 'error');
      finishWithStatus('FAILED');
    }
  }

  async function finalize(){
    try {
      var res = await postJSON(BASE_URL + 'actions/sync_trigger.php', {
        action: 'finish', log_id: logId, stopped: shouldStop ? 1 : 0
      });

      if (res.success){
        var s = res.data;
        bar.style.width = '100%';
        percentEl.textContent = '100%';
        label.textContent = 'Selesai';
        statusEl.textContent = shouldStop ? 'STOPPED' : (s.status || 'SUCCESS');
        statusEl.className = 'badge bg-' + (s.status === 'SUCCESS' ? 'success' : (s.status === 'FAILED' ? 'danger' : 'warning'));
        log('🎉 Sinkronisasi selesai! Total: ' + fmt(s.total_inserted) + ' baru, '
            + fmt(s.total_updated) + ' update, ' + fmt(s.total_failed) + ' gagal', 'success');
      }
    } catch(e){
      log('❌ Gagal finalize: ' + e.message, 'error');
    }
    running = false;
    btnStop.disabled = true;
    btnClose.disabled = false;
  }

  function finishWithStatus(st){
    statusEl.textContent = st;
    statusEl.className = 'badge bg-' + (st === 'SUCCESS' ? 'success' : 'danger');
    running = false;
    btnStop.disabled = true;
    btnClose.disabled = false;
  }

  // ---------- Event: Tombol Sync ----------
  document.querySelectorAll('.btn-sync').forEach(function(btn){
    btn.addEventListener('click', function(){
      var mode = btn.dataset.mode;
      var year = mode === 'year' ? document.getElementById('syncYear').value : '';
      if (mode === 'year'){
        var y = parseInt(year, 10);
        if (!y || y < 2000 || y > 2100){
          alert('Tahun tidak valid.');
          return;
        }
      }
      var confirmMsg = 'Mulai sinkronisasi mode "' + mode + '"'
                     + (year ? ' tahun ' + year : '') + '?\n\n'
                     + 'Proses ini akan menarik data dari API SIPP dan bisa memakan waktu.';
      if (!confirm(confirmMsg)) return;
      startSync(mode, year);
    });
  });

  // ---------- Event: Stop ----------
  btnStop.addEventListener('click', function(){
    if (running){
      shouldStop = true;
      btnStop.disabled = true;
      log('⏹ Menghentikan...', 'warn');
    }
  });

  // ---------- Event: Close ----------
  btnClose.addEventListener('click', function(){
    panel.classList.add('d-none');
    setTimeout(function(){ location.reload(); }, 250);
  });
})();
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>