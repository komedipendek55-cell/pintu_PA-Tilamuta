<?php
/**
 * Proses 1 halaman sync. Ringan & cepat (~1-3 detik).
 * Dipanggil berulang oleh frontend sampai semua halaman selesai.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metode tidak diizinkan.', null, 405);
}

$page = max(1, (int)($_POST['page'] ?? 1));

$pdo = Database::pdo();

/* ============================================================
   Verifikasi: cek LOCK di database, bukan session
   (session bisa expired / locked di tengah proses)
   ============================================================ */
$s = $pdo->prepare("SELECT * FROM sync_daily WHERE sync_date = CURDATE() LIMIT 1");
$s->execute();
$log = $s->fetch();

if (!$log) {
    json_response(false, 'Tidak ada sesi sync hari ini.', null, 404);
}
if ($log['status'] !== 'RUNNING') {
    json_response(false, 'Sesi sync sudah berakhir.', ['status' => $log['status']], 409);
}

// Lock hanya boleh dipakai oleh pengunjung yang membuka halaman dalam
// window yang wajar (2 jam). Cegah abuse kalau lock basi.
$age = time() - strtotime($log['triggered_at']);
if ($age > 7200) {
    $pdo->prepare("UPDATE sync_daily SET status='FAILED', finished_at=NOW() WHERE sync_date=CURDATE()")
        ->execute();
    json_response(false, 'Sesi sync kadaluarsa. Silakan refresh halaman.', null, 410);
}

$cfg = cfg('sipp_sync');
$apiUrl = $cfg['api_url'] ?? '';
$apiToken = $cfg['api_token'] ?? '';
$limit = (int)($cfg['batch_limit'] ?? 100);
$timeout = (int)($cfg['timeout'] ?? 20);

/* ---------- Fetch 1 halaman ---------- */
$url = $apiUrl . '?' . http_build_query([
    'token' => $apiToken, 'mode' => 'current_year', 'page' => $page, 'limit' => $limit
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
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
    json_response(false, 'Fetch gagal: HTTP ' . $code . ($err ? " — $err" : ''), null, 502);
}
$json = json_decode($resp, true);
$rows = $json['data'] ?? [];
if (!is_array($rows)) $rows = [];

/* ---------- UPSERT ---------- */
$inserted = 0; $updated = 0; $failed = 0;

if ($rows) {
    $sql = "INSERT INTO data_perkara
            (IDPerkara, tglPendaftaran, klasifikasiPerkara, IDJenisPerkara, jenisPerkara,
             noPerkara, tglSurat, noSurat, petitumDakwaan, dipublikasikan, prodeo,
             nilai_sengketa, IDProses, statusAkhir, pihakPertama, pihakKedua,
             pihakKetiga, pihakKeempat, tglPutusan, tglMinutasi, pasalDakwaan, tglBHT)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
             tglPendaftaran=VALUES(tglPendaftaran), klasifikasiPerkara=VALUES(klasifikasiPerkara),
             IDJenisPerkara=VALUES(IDJenisPerkara), jenisPerkara=VALUES(jenisPerkara),
             noPerkara=VALUES(noPerkara), tglSurat=VALUES(tglSurat), noSurat=VALUES(noSurat),
             petitumDakwaan=VALUES(petitumDakwaan), dipublikasikan=VALUES(dipublikasikan),
             prodeo=VALUES(prodeo), nilai_sengketa=VALUES(nilai_sengketa), IDProses=VALUES(IDProses),
             statusAkhir=VALUES(statusAkhir), pihakPertama=VALUES(pihakPertama),
             pihakKedua=VALUES(pihakKedua), pihakKetiga=VALUES(pihakKetiga),
             pihakKeempat=VALUES(pihakKeempat), tglPutusan=VALUES(tglPutusan),
             tglMinutasi=VALUES(tglMinutasi), pasalDakwaan=VALUES(pasalDakwaan),
             tglBHT=VALUES(tglBHT), synced_at=NOW()";
    $stmt = $pdo->prepare($sql);

    $v = function($x) {
        if ($x === null || $x === '') return null;
        return ($x === '0000-00-00') ? null : $x;
    };

    $ids = array_filter(array_column($rows, 'IDPerkara'));
    $existing = [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $chk = $pdo->prepare("SELECT IDPerkara FROM data_perkara WHERE IDPerkara IN ($ph)");
        $chk->execute(array_values($ids));
        foreach ($chk->fetchAll() as $r) $existing[$r['IDPerkara']] = true;
    }

    foreach ($rows as $r) {
        $idp = trim((string)($r['IDPerkara'] ?? ''));
        if ($idp === '') { $failed++; continue; }
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
            if (isset($existing[$idp])) $updated++; else $inserted++;
        } catch (Throwable $e) {
            $failed++;
            error_log('[PINTU chunk] ' . $idp . ': ' . $e->getMessage());
        }
    }
}

/* ---------- Update progress ---------- */
$pdo->prepare("UPDATE sync_daily
               SET current_page = ?, synced_rows = synced_rows + ?
               WHERE sync_date = CURDATE()")
    ->execute([$page, $inserted + $updated]);

/* Ambil status terkini */
$s = $pdo->prepare("SELECT * FROM sync_daily WHERE sync_date = CURDATE() LIMIT 1");
$s->execute();
$cur = $s->fetch();

$totalPages = (int)$cur['total_pages'];
$isLast = ($page >= $totalPages);

/* Kalau halaman terakhir → tandai SUCCESS */
if ($isLast) {
    $pdo->prepare("UPDATE sync_daily SET status='SUCCESS', finished_at=NOW() WHERE sync_date=CURDATE()")
        ->execute();
    audit_log('sync_finished', 'data_perkara', null, null, [
        'total_rows' => (int)$cur['synced_rows'],
    ]);
}

json_response(true, 'Halaman ' . $page . ' selesai.', [
    'page'          => $page,
    'inserted'      => $inserted,
    'updated'       => $updated,
    'failed'        => $failed,
    'current_page'  => (int)$cur['current_page'],
    'total_pages'   => $totalPages,
    'synced_rows'   => (int)$cur['synced_rows'],
    'is_last'       => $isLast,
    'finished'      => $isLast,
]);