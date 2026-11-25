<?php
require_once __DIR__ . '/includes/bootstrap.php';
track_visitor();

$pdo = Database::pdo();
$q = trim((string)($_GET['q'] ?? ''));

// Ambil semua kategori aktif
$sql = "SELECT p.id, p.category, p.name, p.icon_path, p.short_description
        FROM perkara p
        WHERE p.status = 'PUBLISHED'";
$params = [];
if ($q !== '') {
    $sql .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR p.category LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY p.category, p.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Rating agregat
$avg = $pdo->query("SELECT ROUND(AVG(rating),1), COUNT(*) FROM feedback WHERE status='PUBLISHED'")->fetch(PDO::FETCH_NUM);

$page_title = 'Beranda';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<section class="hero-pintu">
  <!-- Canvas animasi network -->
  <canvas id="heroCanvas" aria-hidden="true"></canvas>

  <!-- Overlay gradient untuk kontras teks -->
  <div class="hero-overlay"></div>

  <div class="container py-4 hero-content">
    <span class="hero-badge">
      <i class="bi bi-shield-check"></i> Portal Informasi Perkara Terpadu PA Tilamuta
    </span>

    <h1 class="hero-title">Cari Jenis Perkara atau Layanan</h1>

    <p class="hero-subtitle">
      Temukan informasi persyaratan, biaya, alur, dan produk layanan
      Pengadilan Agama Tilamuta — cepat, jelas, dan tanpa login.
    </p>

    <div class="row justify-content-center">
      <div class="col-md-7">
        <div class="hero-search">
          <i class="bi bi-search hero-search-icon"></i>
          <input type="text" id="searchInput" class="hero-search-input"
                 placeholder="Ketik kata kunci, mis. 'perceraian', 'dispensasi'..."
                 value="<?= e($q) ?>" autocomplete="off">
          <span class="hero-search-glow"></span>
        </div>
      </div>
    </div>

    <div class="hero-meta">
      <span class="hero-meta-item">
        <i class="bi bi-star-fill"></i>
        Rating: <strong><?= e($avg[0] ?? '0') ?>/5</strong>
        <small>(<?= (int)($avg[1] ?? 0) ?> ulasan)</small>
      </span>
      <span class="hero-meta-divider"></span>
      <span class="hero-meta-item">
        <i class="bi bi-people-fill"></i>
        Pengunjung hari ini: <strong><?= (int)visitor_stats()['vToday'] ?></strong>
      </span>
    </div>
  </div>
</section>

<!-- Animasi network matrix -->
<script>
(function(){
  'use strict';
  var canvas = document.getElementById('heroCanvas');
  if (!canvas) return;
  var hero = canvas.parentElement;
  var ctx = canvas.getContext('2d');
  var W = 0, H = 0, particles = [], rafId = null, visible = true;

  var DPR          = Math.min(window.devicePixelRatio || 1, 2);
  var PARTICLE_CLR = 'rgba(120, 210, 130, .85)';
  var LINE_RGB     = '76, 175, 80';
  var MAX_DIST     = 150;

  function resize(){
    W = hero.clientWidth;
    H = hero.clientHeight;
    canvas.width  = Math.floor(W * DPR);
    canvas.height = Math.floor(H * DPR);
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    initParticles();
  }

  function initParticles(){
    // Auto-scale: 1 partikel per ~18000 px², max 55 partikel
    var count = Math.min(55, Math.max(18, Math.floor(W * H / 18000)));
    particles = [];
    for (var i = 0; i < count; i++){
      particles.push({
        x:  Math.random() * W,
        y:  Math.random() * H,
        vx: (Math.random() - 0.5) * 0.35,
        vy: (Math.random() - 0.5) * 0.35,
        r:  1.6 + Math.random() * 1.6
      });
    }
  }

  function draw(){
    if (!visible) { rafId = null; return; }
    ctx.clearRect(0, 0, W, H);

    for (var i = 0; i < particles.length; i++){
      var p = particles[i];
      p.x += p.vx;
      p.y += p.vy;

      // Bounce dari tepi
      if (p.x <= 0){ p.x = 0; p.vx = Math.abs(p.vx); }
      else if (p.x >= W){ p.x = W; p.vx = -Math.abs(p.vx); }
      if (p.y <= 0){ p.y = 0; p.vy = Math.abs(p.vy); }
      else if (p.y >= H){ p.y = H; p.vy = -Math.abs(p.vy); }

      // Gambar node
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = PARTICLE_CLR;
      ctx.fill();

      // Koneksi ke node lain yang dekat
      for (var j = i + 1; j < particles.length; j++){
        var q = particles[j];
        var dx = p.x - q.x, dy = p.y - q.y;
        var d2 = dx*dx + dy*dy;
        if (d2 < MAX_DIST * MAX_DIST){
          var d = Math.sqrt(d2);
          var alpha = (1 - d / MAX_DIST) * 0.55;
          ctx.beginPath();
          ctx.strokeStyle = 'rgba(' + LINE_RGB + ',' + alpha.toFixed(3) + ')';
          ctx.lineWidth = 1;
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.stroke();
        }
      }
    }
    rafId = requestAnimationFrame(draw);
  }

  function start(){ if (!rafId) rafId = requestAnimationFrame(draw); }
  function stop(){ if (rafId){ cancelAnimationFrame(rafId); rafId = null; } }

  // Resize
  var rt = null;
  window.addEventListener('resize', function(){
    clearTimeout(rt);
    rt = setTimeout(function(){ resize(); if (visible) start(); }, 180);
  }, { passive: true });

  // Hemat CPU: pause saat hero tidak terlihat
  if ('IntersectionObserver' in window){
    new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        visible = e.isIntersecting;
        if (visible) start(); else stop();
      });
    }, { threshold: 0.05 }).observe(hero);
  }

  // Pause saat tab tidak aktif
  document.addEventListener('visibilitychange', function(){
    if (document.hidden) stop();
    else if (visible) start();
  });

  // Hormati reduce-motion
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches){
    resize();
    // Gambar sekali tanpa animasi
    ctx.clearRect(0, 0, W, H);
    return;
  }

  resize();
  start();
})();
</script>


<section class="container py-4">
  <div id="emptyState" class="text-center text-muted py-5 d-none">
    <i class="bi bi-inbox display-4"></i>
    <p class="mt-2">Tidak ada hasil. Coba kata kunci lain.</p>
  </div>
  <div class="row g-4" id="cardGrid">
    <?php foreach ($items as $it): ?>
      <div class="col-12 col-sm-6 col-lg-4">
		<a class="card card-pintu h-100 text-decoration-none"
		   href="<?= e(base_url('detail.php?id='.(int)$it['id'])) ?>">
		  <div class="card-body d-flex gap-3">
			<div class="icon-wrap">
			  <?php if ($it['icon_path']): ?>
				<img src="<?= e(base_url($it['icon_path'])) ?>" alt="">
			  <?php else: ?>
				<i class="bi bi-file-earmark-text fs-2"></i>
			  <?php endif; ?>
			</div>
			<div class="flex-grow-1">
			  <span class="badge badge-cat mb-1"><?= e(CATEGORY_LABELS[$it['category']]) ?></span>
			  <h6 class="mb-1 fw-bold"><?= e($it['name']) ?></h6>
			  <p class="small text-muted mb-0"><?= e(mb_strimwidth((string)$it['short_description'],0,120,'…')) ?></p>
			</div>
		  </div>
		  <span class="card-arrow"><i class="bi bi-arrow-right"></i></span>
		</a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Feedback -->
<section class="container py-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="fw-bold"><i class="bi bi-chat-square-heart text-warning"></i> Beri Rating & Kritik/Saran</h5>
      <form id="feedbackForm" class="row g-3 mt-2" method="post" action="<?= e(base_url('actions/feedback_save.php')) ?>">
        <?= csrf_field() ?>
        <div class="col-12">
          <label class="form-label d-block">Rating</label>
          <div class="rating-stars" id="ratingStars">
            <?php for ($i=1;$i<=5;$i++): ?>
              <i class="bi bi-star" data-val="<?= $i ?>" role="button" aria-label="<?= $i ?> bintang"></i>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="ratingVal" required>
        </div>
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Nama (opsional)" maxlength="100"></div>
        <div class="col-md-4"><input class="form-control" name="email" type="email" placeholder="Email (opsional)" maxlength="150"></div>
        <div class="col-12"><textarea class="form-control" name="message" rows="3" placeholder="Kritik & saran" maxlength="1000"></textarea></div>
        <div class="col-12"><button class="btn btn-pintu"><i class="bi bi-send"></i> Kirim</button></div>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/accessibility.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>