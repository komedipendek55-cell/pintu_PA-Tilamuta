<?php
/**
 * Trigger sync harian — HANYA klaim lock & hitung total halaman.
 * Tidak menjalankan sync apapun. Frontend yang akan loop per halaman.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metode tidak diizinkan.', null, 405);
}

if (empty($_SESSION['datap_verified']) ||
    (time() - (int)$_SESSION['datap_verified']) > 1800) {
    json_response(false, 'Sesi verifikasi tidak ditemukan.', null, 403);
}

$autoCfg = cfg('auto_sync_daily');
if (empty($autoCfg['enabled'])) {
    json_response(false, 'Auto-sync dimatikan.');
}

$pdo = Database::pdo();

try { $pdo->query("SELECT 1 FROM sync_daily LIMIT 1"); }
catch (Throwable $e) { json_response(false, 'Tabel sync_daily belum dibuat.', null, 500); }

/* Cek hari ini sudah sync? */
$s = $pdo->prepare("SELECT * FROM sync_daily WHERE sync_date = CURDATE() LIMIT 1");
$s->execute();
$row = $s->fetch();

if ($row && $row['status'] === 'SUCCESS') {
    json_response(true, 'Sudah sinkron hari ini.', ['skipped' => true]);
}
if ($row && $row['status'] === 'RUNNING') {
    $elapsed = time() - strtotime($row['triggered_at']);
    if ($elapsed < 900) {
        json_response(true, 'Sync sedang berjalan.', [
            'skipped' => true,
            'total_pages' => (int)($row['total_pages'] ?? 0),
            'current_page'=> (int)($row['current_page'] ?? 0),
        ]);
    }
    // Stuck > 15 menit
    $pdo->prepare("UPDATE sync_daily SET status='FAILED', finished_at=NOW() WHERE sync_date=CURDATE()")->execute();
}

/* ---------- PROBE API: ambil halaman 1 saja ---------- */
$cfg = cfg('sipp_sync');
$apiUrl = $cfg['api_url'] ?? '';
$apiToken = $cfg['api_token'] ?? '';
$limit = (int)($cfg['batch_limit'] ?? 100);
$timeout = (int)($cfg['timeout'] ?? 20);

if ($apiUrl === '' || $apiToken === '') {
    json_response(false, 'Konfigurasi SIPP kosong.', null, 500);
}

$probeUrl = $apiUrl . '?' . http_build_query([
    'token' => $apiToken, 'mode' => 'current_year', 'page' => 1, 'limit' => $limit
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $probeUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'PINTU/1.0',
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($code !== 200) {
    json_response(false, 'API tidak bisa dihubungi: HTTP ' . $code . ($err ? " — $err" : ''), null, 502);
}
$json = json_decode($resp, true);
if (!$json || ($json['status'] ?? '') !== 'success') {
    json_response(false, 'Response API tidak valid.', null, 502);
}

$totalPages = (int)($json['total_pages'] ?? 0);
$totalRows  = (int)($json['total_rows']  ?? 0);

if ($totalPages === 0) {
    json_response(true, 'Tidak ada data baru.', ['skipped' => true]);
}

/* ---------- Klaim lock ---------- */
$pdo->prepare("INSERT INTO sync_daily (sync_date, status, total_pages, current_page, synced_rows)
               VALUES (CURDATE(), 'RUNNING', ?, 0, 0)
               ON DUPLICATE KEY UPDATE
                   status='RUNNING', triggered_at=NOW(), finished_at=NULL,
                   total_pages=?, current_page=0, synced_rows=0")
    ->execute([$totalPages, $totalPages]);

audit_log('sync_triggered', 'data_perkara', null, null, ['total_pages' => $totalPages]);

json_response(true, 'Siap sync.', [
    'started'       => true,
    'total_pages'   => $totalPages,
    'total_rows'    => $totalRows,
    'limit'         => $limit,
]);