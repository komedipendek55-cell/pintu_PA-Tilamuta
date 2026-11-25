<?php
/**
 * Endpoint polling status sinkronisasi harian.
 * GET request — dipanggil berulang oleh frontend selama overlay berjalan.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Metode tidak diizinkan.', null, 405);
}

/* Verifikasi: user harus lulus anti-robot gate */
if (empty($_SESSION['datap_verified']) ||
    (time() - (int)$_SESSION['datap_verified']) > 1800) {
    json_response(false, 'Sesi verifikasi tidak ditemukan.', null, 403);
}

try {
    $pdo = Database::pdo();
    $s = $pdo->prepare("SELECT * FROM sync_daily WHERE sync_date = CURDATE() LIMIT 1");
    $s->execute();
    $row = $s->fetch();

    if (!$row) {
        json_response(true, 'Belum ada sync hari ini.', [
            'status'       => 'NOT_STARTED',
            'percent'      => 0,
            'current_page' => 0,
            'total_pages'  => 0,
            'synced_rows'  => 0,
            'started_at'   => null,
        ]);
    }

    $total   = (int)($row['total_pages'] ?? 0);
    $curr    = (int)($row['current_page'] ?? 0);
    $percent = ($total > 0) ? min(100, round($curr / $total * 100)) : 0;

    // Kalau sudah selesai → 100%
    if (in_array($row['status'], ['SUCCESS', 'FAILED'], true)) {
        $percent = 100;
    } elseif ($total === 0 && $row['status'] === 'RUNNING') {
        // Baru mulai, belum dapat info total halaman
        $percent = 2;
    }

    json_response(true, 'OK', [
        'status'       => $row['status'],           // RUNNING | SUCCESS | FAILED
        'percent'      => $percent,
        'current_page' => $curr,
        'total_pages'  => $total,
        'synced_rows'  => (int)$row['synced_rows'],
        'started_at'   => $row['triggered_at'],
        'finished_at'  => $row['finished_at'],
    ]);

} catch (Throwable $e) {
    error_log('[PINTU progress] ' . $e->getMessage());
    json_response(false, 'Gagal memuat progres.', null, 500);
}