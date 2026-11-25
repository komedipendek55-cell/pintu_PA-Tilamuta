<?php
/**
 * Endpoint AJAX untuk sinkronisasi data dari SIPP.
 * Dipanggil dengan parameter `action`:
 *   - start  : membuat log baru, hitung total halaman
 *   - page   : proses 1 halaman
 *   - finish : tandai log selesai
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_PANITERA_HUKUM]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf();

$action = $_POST['action'] ?? '';
$mode   = $_POST['mode']   ?? 'current_year';
$year   = (int)($_POST['year'] ?? 0);
$page   = max(1, (int)($_POST['page'] ?? 1));
$logId  = (int)($_POST['log_id'] ?? 0);

$cfg = cfg('sipp_sync');
$apiUrl   = $cfg['api_url'];
$apiToken = $cfg['api_token'];
$limit    = (int)$cfg['batch_limit'];
$timeout  = (int)$cfg['timeout'];

/* ============================================================
   Helper: panggil API SIPP
   ============================================================ */
function call_sipp_api(string $apiUrl, string $token, string $mode, int $page, int $limit, int $year, int $timeout): array
{
    $params = [
        'token' => $token,
        'mode'  => $mode,
        'page'  => $page,
        'limit' => $limit,
    ];
    if ($mode === 'year') $params['year'] = $year;

    $url = $apiUrl . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'PINTU-Sync/1.0',
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['error' => "HTTP $httpCode" . ($curlError ? " — $curlError" : '')];
    }
    $json = json_decode($response, true);
    if (!$json || !isset($json['status']) || $json['status'] !== 'success') {
        return ['error' => 'Respons API tidak valid atau status bukan success.'];
    }
    return $json;
}

/* ============================================================
   ACTION: START
   ============================================================ */
if ($action === 'start') {
    if (!in_array($mode, ['full', 'current_year', 'year'], true)) {
        json_response(false, 'Mode tidak valid.', null, 422);
    }
    if ($mode === 'year' && ($year < 2000 || $year > 2100)) {
        json_response(false, 'Tahun tidak valid.', null, 422);
    }

    // Cek koneksi API dengan request halaman 1
    $probe = call_sipp_api($apiUrl, $apiToken, $mode, 1, $limit, $year, $timeout);
    if (isset($probe['error'])) {
        json_response(false, 'Gagal menghubungi API SIPP: ' . $probe['error'], null, 502);
    }

    $totalRows  = (int)($probe['total_rows'] ?? 0);
    $totalPages = (int)($probe['total_pages'] ?? 0);

    // Buat log baru
    $pdo = Database::pdo();
    $stmt = $pdo->prepare("INSERT INTO sync_logs (user_id, mode, year, total_pages, total_data, status)
                           VALUES (?, ?, ?, ?, ?, 'RUNNING')");
    $stmt->execute([
        (int)current_user()['id'],
        $mode,
        $mode === 'year' ? $year : ($mode === 'current_year' ? (int)date('Y') : null),
        $totalPages,
        $totalRows,
    ]);
    $logId = (int)$pdo->lastInsertId();

    json_response(true, 'Siap', [
        'log_id'      => $logId,
        'total_rows'  => $totalRows,
        'total_pages' => $totalPages,
        'limit'       => $limit,
    ]);
}

/* ============================================================
   ACTION: PAGE — proses 1 halaman
   ============================================================ */
if ($action === 'page') {
    if (!$logId) json_response(false, 'Log ID tidak valid.', null, 422);

    $pdo = Database::pdo();

    // Ambil info log
    $st = $pdo->prepare("SELECT * FROM sync_logs WHERE id=?");
    $st->execute([$logId]);
    $log = $st->fetch();
    if (!$log) json_response(false, 'Log tidak ditemukan.', null, 404);
    if ($log['status'] !== 'RUNNING') json_response(false, 'Sesi sudah tidak berjalan.', null, 409);

    // Panggil API
    $json = call_sipp_api($apiUrl, $apiToken, $mode, $page, $limit, $year, $timeout);
    if (isset($json['error'])) {
        json_response(false, 'Gagal halaman ' . $page . ': ' . $json['error'], null, 502);
    }

    $rows = $json['data'] ?? [];
    if (!is_array($rows)) $rows = [];

    /* ========================================================
       Cek keberadaan IDPerkara untuk klasifikasi insert/update
       ======================================================== */
    $inserted = 0;
    $updated  = 0;
    $failed   = 0;

    if ($rows) {
        // Ambil semua ID yang ada dulu dalam 1 query
        $ids = [];
        foreach ($rows as $r) {
            if (!empty($r['IDPerkara'])) $ids[] = $r['IDPerkara'];
        }
        $existingIds = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $chk = $pdo->prepare("SELECT IDPerkara FROM data_perkara WHERE IDPerkara IN ($placeholders)");
            $chk->execute($ids);
            foreach ($chk->fetchAll() as $r) $existingIds[$r['IDPerkara']] = true;
        }

        /* ========================================================
           Prepared statement untuk UPSERT
           ======================================================== */
        $sql = "INSERT INTO data_perkara
                (IDPerkara, tglPendaftaran, klasifikasiPerkara, IDJenisPerkara, jenisPerkara,
                 noPerkara, tglSurat, noSurat, petitumDakwaan, dipublikasikan, prodeo,
                 nilai_sengketa, IDProses, statusAkhir, pihakPertama, pihakKedua,
                 pihakKetiga, pihakKeempat, tglPutusan, tglMinutasi, pasalDakwaan, tglBHT)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                 tglPendaftaran     = VALUES(tglPendaftaran),
                 klasifikasiPerkara = VALUES(klasifikasiPerkara),
                 IDJenisPerkara     = VALUES(IDJenisPerkara),
                 jenisPerkara       = VALUES(jenisPerkara),
                 noPerkara          = VALUES(noPerkara),
                 tglSurat           = VALUES(tglSurat),
                 noSurat            = VALUES(noSurat),
                 petitumDakwaan     = VALUES(petitumDakwaan),
                 dipublikasikan     = VALUES(dipublikasikan),
                 prodeo             = VALUES(prodeo),
                 nilai_sengketa     = VALUES(nilai_sengketa),
                 IDProses           = VALUES(IDProses),
                 statusAkhir        = VALUES(statusAkhir),
                 pihakPertama       = VALUES(pihakPertama),
                 pihakKedua         = VALUES(pihakKedua),
                 pihakKetiga        = VALUES(pihakKetiga),
                 pihakKeempat       = VALUES(pihakKeempat),
                 tglPutusan         = VALUES(tglPutusan),
                 tglMinutasi        = VALUES(tglMinutasi),
                 pasalDakwaan       = VALUES(pasalDakwaan),
                 tglBHT             = VALUES(tglBHT),
                 synced_at          = NOW()";
        $stmt = $pdo->prepare($sql);

        // Helper: sanitasi nilai (string / null / date)
        $v = function($val) {
            if ($val === null || $val === '') return null;
            return $val === '0000-00-00' ? null : $val;
        };

        foreach ($rows as $r) {
            $idp = trim((string)($r['IDPerkara'] ?? ''));
            if ($idp === '') { $failed++; continue; }

            $params = [
                $idp,
                $v($r['tglPendaftaran']   ?? null),
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
            ];

            try {
                $stmt->execute($params);
                if (isset($existingIds[$idp])) $updated++; else $inserted++;
            } catch (Throwable $e) {
                $failed++;
                error_log('[PINTU sync] ID ' . $idp . ': ' . $e->getMessage());
            }
        }
    }

    // Update log dengan progres
    $pdo->prepare("UPDATE sync_logs
                   SET current_page = ?,
                       total_inserted = total_inserted + ?,
                       total_updated  = total_updated  + ?,
                       total_failed   = total_failed   + ?
                   WHERE id = ?")
        ->execute([$page, $inserted, $updated, $failed, $logId]);

    json_response(true, 'OK', [
        'page'      => $page,
        'processed' => count($rows),
        'inserted'  => $inserted,
        'updated'   => $updated,
        'failed'    => $failed,
    ]);
}

/* ============================================================
   ACTION: FINISH
   ============================================================ */
if ($action === 'finish') {
    if (!$logId) json_response(false, 'Log ID tidak valid.', null, 422);

    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT * FROM sync_logs WHERE id=?");
    $st->execute([$logId]);
    $log = $st->fetch();
    if (!$log) json_response(false, 'Log tidak ditemukan.', null, 404);

    $stopped = (int)($_POST['stopped'] ?? 0);

    $status = 'SUCCESS';
    if ($stopped) {
        $status = 'PARTIAL';
    } elseif ((int)$log['total_failed'] > 0 && (int)$log['total_inserted'] + (int)$log['total_updated'] > 0) {
        $status = 'PARTIAL';
    } elseif ((int)$log['total_inserted'] + (int)$log['total_updated'] === 0 && (int)$log['total_failed'] > 0) {
        $status = 'FAILED';
    }

    $pdo->prepare("UPDATE sync_logs SET status=?, finished_at=NOW() WHERE id=?")
        ->execute([$status, $logId]);

    audit_log('sync_finish', 'data_perkara', null, null, [
        'mode'     => $log['mode'],
        'inserted' => (int)$log['total_inserted'],
        'updated'  => (int)$log['total_updated'],
        'failed'   => (int)$log['total_failed'],
        'status'   => $status,
    ]);

    json_response(true, 'Selesai', [
        'status'         => $status,
        'total_inserted' => (int)$log['total_inserted'],
        'total_updated'  => (int)$log['total_updated'],
        'total_failed'   => (int)$log['total_failed'],
    ]);
}

json_response(false, 'Action tidak dikenali.', null, 400);