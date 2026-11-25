<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect(admin_url('dashboard.php'));

/* ============================================================
   Generate CAPTCHA (disimpan di session, server-side verified)
   ============================================================ */
if (empty($_SESSION['captcha']) || (time() - ($_SESSION['captcha']['created'] ?? 0) > 600)) {
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $op = random_int(0, 1) === 1 ? '+' : '-';
    if ($op === '-') {
        if ($a < $b) { $t = $a; $a = $b; $b = $t; }
    }
    $_SESSION['captcha'] = [
        'q'       => "$a $op $b",
        'a'       => $op === '+' ? ($a + $b) : ($a - $b),
        'created' => time(),
    ];
}

/* ============================================================
   Form token & timestamp (anti-bot)
   ============================================================ */
$_SESSION['login_form_token'] = bin2hex(random_bytes(16));
$_SESSION['login_form_time']  = time();

$err     = $_SESSION['login_error'] ?? null;
$oldUser = $_SESSION['login_old_user'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_old_user']);

$logo = get_setting('site_logo');
$short = get_setting('site_short_name', 'PINTU');
$instansi = get_setting('site_instansi', cfg('instansi'));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login &mdash; <?= e($short) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-split">

  <!-- ============ KIRI — BRANDING ============ -->
  <aside class="login-brand">
    <canvas id="loginCanvas" aria-hidden="true"></canvas>
    <div class="login-brand-overlay"></div>

    <div class="login-brand-content">
      <div class="lb-logo-wrap">
        <?php if ($logo): ?>
          <img src="<?= e(base_url($logo)) ?>" alt="Logo" class="lb-logo">
        <?php else: ?>
          <div class="lb-logo-fallback"><i class="bi bi-building-check"></i></div>
        <?php endif; ?>
      </div>

      <h1 class="lb-title"><?= e($short) ?></h1>
      <p class="lb-sub"><?= e(cfg('app_full_name')) ?></p>
      <p class="lb-instansi"><?= e($instansi) ?></p>

      <ul class="lb-features">
        <li><i class="bi bi-shield-lock-fill"></i> Akses terbatas &amp; aman</li>
        <li><i class="bi bi-activity"></i> Pemantauan aktivitas pengguna</li>
        <li><i class="bi bi-database-lock"></i> Data terenkripsi &amp; terverifikasi</li>
      </ul>

      <div class="lb-footer-note">
        <i class="bi bi-c-circle"></i> <?= date('Y') ?> <?= e($instansi) ?>
      </div>
    </div>
  </aside>

  <!-- ============ KANAN — FORM ============ -->
  <main class="login-form-wrap">

    <div class="login-form-card">

      <!-- Warning pengguna umum -->
      <div class="login-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
          <strong>Perhatian</strong><br>
          Halaman login ini <b>hanya untuk pegawai Pengadilan Agama Tilamuta</b>.
          Untuk pengguna umum <b>tidak perlu login</b> — cukup gunakan pencarian di
          halaman utama.
        </div>
      </div>

      <div class="login-form-head">
        <h2>Selamat Datang</h2>
        <p>Masuk menggunakan akun internal Anda</p>
      </div>

      <?php if ($err): ?>
        <div class="login-alert-error" role="alert">
          <i class="bi bi-x-octagon-fill"></i>
          <span><?= e($err) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('actions/login_process.php')) ?>"
            autocomplete="off" id="loginForm" novalidate>
        <?= csrf_field() ?>

        <!-- Honeypot: bot biasanya mengisi field ini -->
        <div class="login-hp" aria-hidden="true">
          <label>Jangan diisi</label>
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <!-- Token & timestamp -->
        <input type="hidden" name="form_token" value="<?= e($_SESSION['login_form_token']) ?>">
        <input type="hidden" name="form_time"  value="<?= e($_SESSION['login_form_time']) ?>">

        <!-- Username -->
        <div class="lf-group">
          <label for="username">Username</label>
          <div class="lf-input">
            <i class="bi bi-person-fill lf-icon"></i>
            <input type="text" id="username" name="username"
                   required autofocus maxlength="100"
                   value="<?= e($oldUser) ?>"
                   placeholder="Masukkan username">
          </div>
        </div>

        <!-- Password -->
        <div class="lf-group">
          <label for="password">Password</label>
          <div class="lf-input">
            <i class="bi bi-key-fill lf-icon"></i>
            <input type="password" id="password" name="password"
                   required maxlength="200"
                   placeholder="Masukkan password">
            <button type="button" class="lf-toggle-pwd" id="togglePwd" aria-label="Tampilkan password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <!-- CAPTCHA -->
        <div class="lf-group">
          <label for="captcha">Verifikasi Anti-Robot</label>
          <div class="lf-captcha">
            <div class="lf-captcha-q">
              <i class="bi bi-shield-check"></i>
              <span><?= e($_SESSION['captcha']['q']) ?> = ?</span>
            </div>
            <input type="text" id="captcha" name="captcha"
                   required inputmode="numeric" pattern="-?[0-9]+"
                   placeholder="Jawaban"
                   autocomplete="off" maxlength="4">
          </div>
          <small class="lf-help">
            <i class="bi bi-info-circle"></i>
            Jawab operasi matematika di atas untuk membuktikan Anda bukan robot.
          </small>
        </div>

        <!-- Submit -->
        <button type="submit" class="lf-submit" id="loginSubmit">
          <span class="lf-submit-text">
            <i class="bi bi-box-arrow-in-right"></i> Masuk
          </span>
          <span class="lf-submit-loading d-none">
            <span class="lf-spinner"></span> Memverifikasi...
          </span>
        </button>

        <a href="<?= e(base_url()) ?>" class="lf-back">
          <i class="bi bi-arrow-left"></i> Kembali ke Beranda
        </a>
      </form>

      <div class="login-secure-note">
        <i class="bi bi-lock-fill"></i>
        Koneksi aman &middot; Dilindungi dari serangan bot
      </div>

    </div>
  </main>

</div>

<script>
(function(){
  'use strict';

  /* ---------- Canvas network (sama seperti hero) ---------- */
  var canvas = document.getElementById('loginCanvas');
  if (canvas && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var ctx = canvas.getContext('2d');
    var W = 0, H = 0, particles = [], rafId = null;
    var DPR = Math.min(window.devicePixelRatio || 1, 2);
    var MAX_DIST = 140;
    var NODE_CLR = 'rgba(200, 240, 210, .85)';
    var LINE_RGB = '168, 214, 76';

    function resize(){
      var host = canvas.parentElement;
      W = host.clientWidth; H = host.clientHeight;
      canvas.width  = Math.floor(W * DPR);
      canvas.height = Math.floor(H * DPR);
      canvas.style.width  = W + 'px';
      canvas.style.height = H + 'px';
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
      var n = Math.min(40, Math.max(14, Math.floor(W * H / 20000)));
      particles = [];
      for (var i = 0; i < n; i++){
        particles.push({
          x: Math.random() * W, y: Math.random() * H,
          vx: (Math.random() - .5) * .3, vy: (Math.random() - .5) * .3,
          r: 1.4 + Math.random() * 1.5
        });
      }
    }
    function draw(){
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < particles.length; i++){
        var p = particles[i];
        p.x += p.vx; p.y += p.vy;
        if (p.x <= 0 || p.x >= W) p.vx *= -1;
        if (p.y <= 0 || p.y >= H) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = NODE_CLR; ctx.fill();
        for (var j = i + 1; j < particles.length; j++){
          var q = particles[j];
          var dx = p.x - q.x, dy = p.y - q.y, d2 = dx*dx + dy*dy;
          if (d2 < MAX_DIST * MAX_DIST){
            var d = Math.sqrt(d2);
            ctx.strokeStyle = 'rgba(' + LINE_RGB + ',' + ((1 - d/MAX_DIST) * .45).toFixed(3) + ')';
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
          }
        }
      }
      rafId = requestAnimationFrame(draw);
    }
    function start(){ if (!rafId) rafId = requestAnimationFrame(draw); }
    function stop(){ if (rafId){ cancelAnimationFrame(rafId); rafId = null; } }
    window.addEventListener('resize', function(){ resize(); }, { passive: true });
    document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });
    resize(); start();
  }

  /* ---------- Show / hide password ---------- */
  var btn = document.getElementById('togglePwd');
  var pwd = document.getElementById('password');
  if (btn && pwd){
    btn.addEventListener('click', function(){
      var isPwd = pwd.type === 'password';
      pwd.type = isPwd ? 'text' : 'password';
      btn.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
      pwd.focus();
    });
  }

  /* ---------- Loading state ---------- */
  var form = document.getElementById('loginForm');
  var submitBtn = document.getElementById('loginSubmit');
  if (form && submitBtn){
    form.addEventListener('submit', function(){
      submitBtn.disabled = true;
      submitBtn.querySelector('.lf-submit-text').classList.add('d-none');
      submitBtn.querySelector('.lf-submit-loading').classList.remove('d-none');
    });
  }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>