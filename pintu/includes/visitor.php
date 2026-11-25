<?php
/**
 * Visitor counter dengan dedup ringan:
 * key = hash(IP + User-Agent + tanggal). Satu IP/UA hanya dihitung 1x per hari.
 */
function track_visitor(): void {
    // Jangan track halaman admin
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $today = date('Y-m-d');
    $key = hash('sha256', $ip . '|' . $ua . '|' . $today);

    try {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT id FROM visitor_logs WHERE visit_date = ? AND visitor_key = ? LIMIT 1");
        $stmt->execute([$today, $key]);
        if ($stmt->fetch()) return;

        $ins = $pdo->prepare("INSERT INTO visitor_logs (visit_date, visitor_key, path) VALUES (?, ?, ?)");
        $ins->execute([$today, $key, substr($_SERVER['REQUEST_URI'] ?? '/', 0, 250)]);
    } catch (Throwable $e) {
        error_log('[PINTU] visitor track: ' . $e->getMessage());
    }
}

function visitor_stats(): array {
    $pdo = Database::pdo();
    $today = date('Y-m-d');
    $month = date('Y-m');

    $vToday  = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_key) FROM visitor_logs WHERE visit_date='$today'")->fetchColumn();
    $vMonth  = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_key) FROM visitor_logs WHERE DATE_FORMAT(visit_date,'%Y-%m')='$month'")->fetchColumn();
    $vYear   = (int)$pdo->query("SELECT COUNT(DISTINCT visitor_key) FROM visitor_logs WHERE YEAR(visit_date)=YEAR(CURDATE())")->fetchColumn();
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM visitor_logs")->fetchColumn();
    return compact('vToday','vMonth','vYear','total');
}