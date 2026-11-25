<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('login.php'));
verify_csrf();

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$captcha  = trim((string)($_POST['captcha'] ?? ''));

/* ============================================================
   ANTI-BOT LAYER 1 — Honeypot
   Field `website` harus kosong. Bot biasanya mengisinya.
   ============================================================ */
if (!empty($_POST['website'])) {
    $_SESSION['login_error'] = 'Akses ditolak.';
    audit_log('bot_honeypot', 'users', null, null, ['ua' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
    redirect(base_url('login.php'));
}

/* ============================================================
   ANTI-BOT LAYER 2 — Time-trap
   Form harus diisi minimal 2 detik. Submit instan = bot.
   ============================================================ */
$formTime = (int)($_POST['form_time'] ?? 0);
$elapsed  = time() - $formTime;
if ($formTime <= 0 || $elapsed < 2) {
    $_SESSION['login_error'] = 'Formulir terlalu cepat diproses. Coba lagi.';
    audit_log('bot_too_fast', 'users', null, null, ['elapsed' => $elapsed]);
    redirect(base_url('login.php'));
}
if ($elapsed > 3600) {
    $_SESSION['login_error'] = 'Sesi formulir kadaluarsa. Silakan muat ulang halaman.';
    redirect(base_url('login.php'));
}

/* ============================================================
   ANTI-BOT LAYER 3 — Math CAPTCHA (session-stored)
   ============================================================ */
$expected = $_SESSION['captcha']['a'] ?? null;
$capTime  = (int)($_SESSION['captcha']['created'] ?? 0);

// Hapus captcha (one-time use)
unset($_SESSION['captcha']);

if ($expected === null || (time() - $capTime > 600)) {
    $_SESSION['login_error'] = 'Verifikasi kadaluarsa. Silakan coba lagi.';
    redirect(base_url('login.php'));
}
if (!is_numeric($captcha) || (int)$captcha !== (int)$expected) {
    $_SESSION['login_error'] = 'Jawaban verifikasi anti-robot salah.';
    $_SESSION['login_old_user'] = $username;
    audit_log('captcha_fail', 'users', null, null, ['user' => $username]);
    redirect(base_url('login.php'));
}

/* ============================================================
   Validasi dasar
   ============================================================ */
if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Username dan password wajib diisi.';
    $_SESSION['login_old_user'] = $username;
    redirect(base_url('login.php'));
}

/* ============================================================
   ANTI-BOT LAYER 4 — Rate limit per (IP + username)
   Max 5 percobaan / 15 menit.
   ============================================================ */
$rlKey = 'login_attempts_' . md5($username . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
$now = time();
$attempts = $_SESSION[$rlKey] ?? ['count' => 0, 'first' => $now];
if ($now - $attempts['first'] > 900) {
    $attempts = ['count' => 0, 'first' => $now];
}
if ($attempts['count'] >= 5) {
    $_SESSION['login_error'] = 'Terlalu banyak percobaan login. Coba lagi 15 menit kemudian.';
    audit_log('login_rate_limited', 'users', null, null, ['user' => $username]);
    redirect(base_url('login.php'));
}

/* ============================================================
   Autentikasi
   ============================================================ */
$u = find_user_by_username($username);

if (!$u || !(int)$u['is_active'] || !password_verify($password, $u['password_hash'])) {
    $attempts['count']++;
    $_SESSION[$rlKey] = $attempts;
    $_SESSION['login_error'] = 'Username atau password salah.';
    $_SESSION['login_old_user'] = $username;
    audit_log('login_failed', 'users', $u['id'] ?? null, null, ['username' => $username]);
    redirect(base_url('login.php'));
}

/* ============================================================
   Login sukses
   ============================================================ */
unset($_SESSION[$rlKey]);
login_user($u);
audit_log('login_success', 'users', (int)$u['id']);
set_flash('success', 'Selamat datang, ' . $u['full_name'] . '!');

if ((int)($u['must_change_password'] ?? 0)) {
    set_flash('warning', 'Silakan ganti password Anda terlebih dahulu.');
    redirect(admin_url('profile.php'));
}
redirect(admin_url('dashboard.php'));