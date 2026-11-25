<?php
/** Kumpulan helper umum */

function cfg(string $key, $default = null) {
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/../config/config.php';
    return $c[$key] ?? $default;
}

function e($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function base_url(string $path = ''): string {
    $base = cfg('base_url', '/pintu/');
    // Pastikan selalu diawali dan diakhiri "/"
    $base = '/' . trim($base, '/') . '/';
    if ($base === '//') $base = '/';
    return $base . ltrim($path, '/');
}

function admin_url(string $path = ''): string {
    return base_url('admin/' . ltrim($path, '/'));
}

function set_flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function old(string $key, $default = '') {
    return $_SESSION['old'][$key] ?? $default;
}

function keep_old(array $data): void {
    $_SESSION['old'] = $data;
}

function clear_old(): void {
    unset($_SESSION['old']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']['id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect(base_url('login.php'));
    }
}

function require_role(array $roles): void {
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/403.php';
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        if (is_ajax()) json_response(false, 'CSRF token tidak valid. Refresh halaman.', null, 419);
        die('CSRF token tidak valid.');
    }
}

function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response(bool $ok, string $msg = '', $data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $out = ['success' => $ok, 'message' => $msg];
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function role_can_manage_category(string $role, string $category): bool {
    if ($role === ROLE_ADMIN) return true;
    return match ($role) {
        ROLE_PANITERA_GUGATAN    => $category === CAT_GUGATAN,
        ROLE_PANITERA_PERMOHONAN => $category === CAT_PERMOHONAN,
        ROLE_PANITERA_HUKUM      => $category === CAT_HUKUM,
        default => false,
    };
}

function role_allowed_categories(string $role): array {
    return match ($role) {
        ROLE_ADMIN               => ALL_CATEGORIES,
        ROLE_PANITERA_GUGATAN    => [CAT_GUGATAN],
        ROLE_PANITERA_PERMOHONAN => [CAT_PERMOHONAN],
        ROLE_PANITERA_HUKUM      => [CAT_HUKUM],
        default => [],
    };
}

/** Sanitasi HTML allow-list sederhana */
function sanitize_html(string $html): string {
    // Hapus tag script/style/iframe + event handler
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|form)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|form)[^>]*/?>#is', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html);
    $html = preg_replace('#javascript\s*:#i', '', $html);
    return $html;
}

function format_rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function detect_mime(string $file): string {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    return $fi->file($file) ?: '';
}

/**
 * Ambil nilai setting dari tabel `settings` dengan cache per-request.
 */
function get_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = Database::pdo()->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
        } catch (Throwable $e) {
            error_log('[PINTU] get_setting: ' . $e->getMessage());
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Ambil semua jenis biaya dari database.
 * @param bool $onlyActive  Kalau true, hanya yang is_active=1.
 */
function get_cost_types(bool $onlyActive = true): array
{
    static $all = null;
    if ($all === null) {
        try {
            $all = Database::pdo()
                ->query("SELECT id, code, label, description, is_active, sort_order
                         FROM cost_types ORDER BY sort_order ASC, label ASC")
                ->fetchAll();
        } catch (Throwable $e) {
            error_log('[PINTU] get_cost_types: ' . $e->getMessage());
            $all = [];
        }
    }
    if (!$onlyActive) return $all;
    return array_values(array_filter($all, fn($r) => (int)$r['is_active'] === 1));
}

/**
 * Cari label dari kode jenis biaya.
 */
function get_cost_type_label(string $code): string
{
    foreach (get_cost_types(false) as $c) {
        if ($c['code'] === $code) return $c['label'];
    }
    return $code;
}

/**
 * Ambil catatan pihak dari master cost_types berdasarkan kode.
 * Return string kosong jika tidak ada.
 */
function get_cost_type_note(string $code): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = Database::pdo()
                ->query("SELECT code, catatan_pihak FROM cost_types")
                ->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['code']] = (string)($r['catatan_pihak'] ?? '');
            }
        } catch (Throwable $e) {
            error_log('[PINTU] get_cost_type_note: ' . $e->getMessage());
        }
    }
    return $cache[$code] ?? '';
}

/**
 * Cek apakah kode cost_type valid (ada di master dan aktif).
 */
function cost_type_exists(string $code, bool $onlyActive = true): bool
{
    foreach (get_cost_types($onlyActive) as $c) {
        if ($c['code'] === $code) return true;
    }
    return false;
}

/**
 * Handle upload gambar popup sambutan.
 * Max 200 KB, format PNG/JPG/WEBP.
 */
function handle_popup_upload(array $file, ?string $oldPath = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal (kode: ' . $file['error'] . ').');
    }
    $cfg = cfg('popup_upload');

    if ($file['size'] > $cfg['max_size']) {
        throw new RuntimeException('Ukuran gambar maksimal 200 KB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['allowed_ext'], true)) {
        throw new RuntimeException('Format gambar harus PNG, JPG, atau WEBP.');
    }
    $mime = detect_mime($file['tmp_name']);
    if (!in_array($mime, $cfg['allowed_mime'], true)) {
        throw new RuntimeException('Tipe file tidak valid.');
    }
    if (!is_dir($cfg['dir'])) @mkdir($cfg['dir'], 0755, true);

    $newName = 'popup_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $cfg['dir'] . $newName)) {
        throw new RuntimeException('Gagal menyimpan file.');
    }
    if ($oldPath) {
        $oldFull = __DIR__ . '/../' . $oldPath;
        if (is_file($oldFull)) @unlink($oldFull);
    }
    return $cfg['url'] . $newName;
}

/**
 * Handle upload logo. Return path relatif (mis. 'uploads/logo/logo_xxx.png').
 */
function handle_logo_upload(array $file, ?string $oldPath = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload logo gagal (kode: ' . $file['error'] . ').');
    }
    $cfg = cfg('logo_upload');

    if ($file['size'] > $cfg['max_size']) {
        throw new RuntimeException('Ukuran logo maksimal 500 KB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['allowed_ext'], true)) {
        throw new RuntimeException('Format logo harus PNG, JPG, atau WEBP.');
    }
    $mime = detect_mime($file['tmp_name']);
    if (!in_array($mime, $cfg['allowed_mime'], true)) {
        throw new RuntimeException('Tipe file logo tidak valid.');
    }
    if (!is_dir($cfg['dir'])) @mkdir($cfg['dir'], 0755, true);

    $newName = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $cfg['dir'] . $newName)) {
        throw new RuntimeException('Gagal menyimpan file logo.');
    }

    // Hapus logo lama (kalau ada)
    if ($oldPath) {
        $oldFull = __DIR__ . '/../' . $oldPath;
        if (is_file($oldFull)) @unlink($oldFull);
    }
    return $cfg['url'] . $newName;
}

/**
 * Handle upload icon. Return relative path atau throw exception.
 */
function handle_icon_upload(array $file, ?string $oldPath = null): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload gagal (kode: '.$file['error'].')');

    $cfg = cfg('upload');
    if ($file['size'] > $cfg['max_size']) throw new RuntimeException('Ukuran file maksimal 200 KB.');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $cfg['allowed_ext'], true)) throw new RuntimeException('Ekstensi file tidak diizinkan.');

    $mime = detect_mime($file['tmp_name']);
    if (!in_array($mime, $cfg['allowed_mime'], true)) throw new RuntimeException('Tipe file tidak valid.');

    // Cek rasio 1:1
    $info = @getimagesize($file['tmp_name']);
    if ($info && ($info[0] !== $info[1])) {
        // Peringatan rasio tapi tidak blocker
        // throw new RuntimeException('Rasio icon harus 1:1.');
    }

    if (!is_dir($cfg['icon_dir'])) @mkdir($cfg['icon_dir'], 0755, true);

    $newName = 'icon_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $cfg['icon_dir'] . $newName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Gagal memindahkan file.');

    if ($oldPath) {
        $oldFull = __DIR__ . '/../' . $oldPath;
        if (is_file($oldFull)) @unlink($oldFull);
    }
    return $cfg['icon_url'] . $newName;
}