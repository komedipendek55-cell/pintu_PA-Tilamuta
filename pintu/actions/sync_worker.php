<?php
/**
 * Worker sinkronisasi — dijalankan sebagai PROSES TERPISAH.
 * Bisa dijalankan manual untuk testing:
 *   php actions/sync_worker.php
 *   php actions/sync_worker.php force   ← paksa jalankan walau tidak ada lock
 */

/* ---------- Deteksi CLI vs HTTP ---------- */
$IS_CLI = (php_sapi_name() === 'cli');
$FORCE  = $IS_CLI && isset($argv[1]) && strtolower($argv[1]) === 'force';

/* ---------- Tampilkan error kalau CLI ---------- */
if ($IS_CLI) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

/* ---------- Log file ---------- */
$logFile = __DIR__ . '/../storage/logs/sync-worker.log';
@ini_set('error_log', $logFile);

/* ---------- Helper cetak ---------- */
function worker_echo(string $msg): void {
    $line = '[' . date('H:i:s') . '] ' . $msg;
    if (php_sapi_name() === 'cli') {
        echo $line . PHP_EOL;
    }
    error_log('[PINTU worker] ' . $msg);
}

worker_echo('=== START (PID=' . getmypid() . ', CLI=' . (int)$IS_CLI . ', FORCE=' . (int)$FORCE . ') ===');

/* ============================================================
   CLI MODE: Set flag global supaya bootstrap skip session
   ============================================================ */
if ($IS_CLI) {
    define('PINTU_CLI', true);
}

/* ---------- Load bootstrap ---------- */
$bootstrap = __DIR__ . '/../includes/bootstrap.php';
if (!file_exists($bootstrap)) {
    worker_echo('FATAL: bootstrap.php tidak ditemukan di ' . $bootstrap);
    exit(1);
}
require_once $bootstrap;
worker_echo('Bootstrap loaded.');

/* ---------- Koneksi database ---------- */
try {
    $pdo = Database::pdo();
    worker_echo('Database terkoneksi.');
} catch (Throwable $e) {
    worker_echo('FATAL: DB error — ' . $e->getMessage());
    exit(1);
}

/* ---------- Cek lock ---------- */
$s = $pdo->prepare("SELECT * FROM sync_daily WHERE sync_date = CURDATE() LIMIT 1");
$s->execute();
$row = $s->fetch();

if (!$row) {
    worker_echo('Tidak ada baris sync_daily hari ini.');
    if (!$FORCE) {
        worker_echo('Exit (tidak ada lock). Jalankan dengan argumen "force" untuk paksa.');
        exit(0);
    }
    worker_echo('FORCE mode: membuat lock baru...');
    $pdo->prepare("INSERT INTO sync_daily (sync_date, status) VALUES (CURDATE(), 'RUNNING')
                   ON DUPLICATE KEY UPDATE status='RUNNING', triggered_at=NOW()")
        ->execute();
    worker_echo('Lock dibuat.');
} else {
    worker_echo('Baris sync_daily ditemukan. status=' . $row['status']
              . ', current_page=' . ($row['current_page'] ?? '?')
              . ', total_pages=' . ($row['total_pages'] ?? '?'));

    if ($row['status'] !== 'RUNNING' && !$FORCE) {
        worker_echo('Status bukan RUNNING. Exit. (pakai "force" untuk paksa)');
        exit(0);
    }
    if ($row['status'] !== 'RUNNING' && $FORCE) {
        worker_echo('FORCE mode: reset status ke RUNNING...');
        $pdo->prepare("UPDATE sync_daily SET status='RUNNING', triggered_at=NOW(), finished_at=NULL
                       WHERE sync_date=CURDATE()")
            ->execute();
    }
}

/* ---------- Cek config SIPP ---------- */
$cfg = cfg('sipp_sync');
$apiUrl   = $cfg['api_url']     ?? '';
$apiToken = $cfg['api_token']   ?? '';
$limit    = (int)($cfg['batch_limit'] ?? 500);
$timeout  = (int)($cfg['timeout']     ?? 90);

worker_echo('Config: url=' . $apiUrl . ', limit=' . $limit . ', timeout=' . $timeout);

if ($apiUrl === '' || $apiToken === '') {
    worker_echo('FATAL: konfigurasi SIPP kosong (sipp_sync)');
    $pdo->prepare("UPDATE sync_daily SET status='FAILED', finished_at=NOW() WHERE sync_date=CURDATE()")
        ->execute();
    exit(1);
}

/* ---------- Cek curl ---------- */
if (!function_exists('curl_init')) {
    worker_echo('FATAL: ekstensi curl tidak aktif di PHP CLI!');
    $pdo->prepare("UPDATE sync_daily SET status='FAILED', finished_at=NOW() WHERE sync_date=CURDATE()")
        ->execute();
    exit(1);
}
worker_echo('cURL OK. Mulai proses sinkronisasi...');

/* ============================================================
   HELPER: FETCH API
   ============================================================ */
function _worker_fetch($url, $timeout) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'PINTU-Worker/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code !== 200) return ['error' => "HTTP $code" . ($err ? " — $err" : '')];
    $j = json_decode($resp, true);
    if (!$j || ($j['status'] ?? '') !== 'success') return ['error' => 'Response invalid'];
    return $j;
}

/* ============================================================
   UPSERT STATEMENT
   ============================================================ */
$sql = "INSERT INTO data_perkara
        (IDPerkara, tglPendaftaran, klasifikasiPerkara, IDJenisPerkara, jenisPerkara,
         noPerkara, tglSurat, noSurat, petitumDakwaan, dipublikasikan, prodeo,
         nilai_sengketa, IDProses, statusAkhir, pihakPertama, pihakKedua,
         pihakKetiga, pihakKeempat, tglPutusan, tglMinutasi, pasalDakwaan, tglBHT)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         tglPendaftaran=VALUES(tglPendaftaran),
         klasifikasiPerkara=VALUES(klasifikasiPerkara),
         IDJenisPerkara=VALUES(IDJenisPerkara),
         jenisPerkara=VALUES(jenisPerkara),
         noPerkara=VALUES(noPerkara),
         tglSurat=VALUES(tglSurat),
         noSurat=VALUES(noSurat),
         petitumDakwaan=VALUES(petitumDakwaan),
         dipublikasikan=VALUES(dipublikasikan),
         prodeo=VALUES(prodeo),
         nilai_sengketa=VALUES(nilai_sengketa),
         IDProses=VALUES(IDProses),
         statusAkhir=VALUES(statusAkhir),
         pihakPertama=VALUES(pihakPertama),
         pihakKedua=VALUES(pihakKedua),
         pihakKetiga=VALUES(pihakKetiga),
         pihakKeempat=VALUES(pihakKeempat),
         tglPutusan=VALUES(tglPutusan),
         tglMinutasi=VALUES(tglMinutasi),
         pasalDakwaan=VALUES(pasalDakwaan),
         tglBHT=VALUES(tglBHT),
         synced_at=NOW()";
$stmt = $pdo->prepare($sql);

$v = function($x) {
    if ($x === null || $x === '') return null;
    return ($x === '0000-00-00') ? null : $x;
};

$totalInserted = 0;
$totalUpdated  = 0;
$totalFailed   = 0;
$page          = 1;
$totalPages    = 1;

/* ============================================================
   LOOP PER HALAMAN
   ============================================================ */
while ($page <= $totalPages) {
    $url = $apiUrl . '?' . http_build_query([
        'token' => $apiToken,
        'mode'  => 'current_year',
        'page'  => $page,
        'limit' => $limit,
    ]);

    worker_echo("Fetch halaman $page...");
    $json = _worker_fetch($url, $timeout);

    if (isset($json['error'])) {
        worker_echo("Fetch error halaman $page: " . $json['error']);
        break;
    }

    if ($page === 1) {
        $totalPages = (int)($json['total_pages'] ?? 1);
        worker_echo("Total halaman: $totalPages");

        // Update total_pages ke tabel
        try {
            $pdo->prepare("UPDATE sync_daily SET total_pages=? WHERE sync_date=CURDATE()")
                ->execute([$totalPages]);
        } catch (Throwable $e) {}
    }

    $rows = $json['data'] ?? [];
    if (!is_array($rows) || !$rows) {
        worker_echo("Halaman $page kosong.");
        break;
    }

    // Deteksi existing untuk klasifikasi insert/update
    $ids = [];
    foreach ($rows as $r) {
        if (!empty($r['IDPerkara'])) $ids[] = $r['IDPerkara'];
    }
    $existing = [];
    if ($ids) {
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $chk = $pdo->prepare("SELECT IDPerkara FROM data_perkara WHERE IDPerkara IN ($ph)");
        $chk->execute($ids);
        foreach ($chk->fetchAll() as $r) $existing[$r['IDPerkara']] = true;
    }

    foreach ($rows as $r) {
        $idp = trim((string)($r['IDPerkara'] ?? ''));
        if ($idp === '') { $totalFailed++; continue; }
        try {
            $stmt->execute([
                $idp,
                $v($r['tglPendaftaran']    ?? null),
                $v($r['klasifikasiPerkara'] ?? null),
                $v($r['IDJenisPerkara']    ?? null),
                $v($r['jenisPerkara']      ?? null),
                $v($r['noPerkara']         ?? null),
                $v($r['tglSurat']          ?? null),
                $v($r['noSurat']           ?? null),
                $v($r['petitumDakwaan']    ?? null),
                $v($r['dipublikasikan']    ?? null),
                $v($r['prodeo']            ?? null),
                $v($r['nilai_sengketa']    ?? null),
                $v($r['IDProses']          ?? null),
                $v($r['statusAkhir']       ?? null),
                $v($r['pihakPertama']      ?? null),
                $v($r['pihakKedua']        ?? null),
                $v($r['pihakKetiga']       ?? null),
                $v($r['pihakKeempat']      ?? null),
                $v($r['tglPutusan']        ?? null),
                $v($r['tglMinutasi']       ?? null),
                $v($r['pasalDakwaan']      ?? null),
                $v($r['tglBHT']            ?? null),
            ]);
            if (isset($existing[$idp])) $totalUpdated++; else $totalInserted++;
        } catch (Throwable $e) {
            $totalFailed++;
            worker_echo('Row ' . $idp . ': ' . $e->getMessage());
        }
    }

    worker_echo("Halaman $page selesai. I=$totalInserted U=$totalUpdated F=$totalFailed");

    // Update progress ke tabel
    try {
        $pdo->prepare("UPDATE sync_daily
                       SET current_page = ?, synced_rows = ?
                       WHERE sync_date = CURDATE()")
            ->execute([$page, $totalInserted + $totalUpdated]);
    } catch (Throwable $e) {}

    $page++;
}

/* ============================================================
   SELESAI
   ============================================================ */
$status = 'SUCCESS';
if ($totalFailed > 0 && ($totalInserted + $totalUpdated) === 0) {
    $status = 'FAILED';
}

$pdo->prepare("UPDATE sync_daily
               SET status=?, finished_at=NOW(), synced_rows=?
               WHERE sync_date=CURDATE()")
    ->execute([$status, $totalInserted + $totalUpdated]);

worker_echo("=== SELESAI. Status=$status I=$totalInserted U=$totalUpdated F=$totalFailed ===");