<?php
$__logo     = get_setting('site_logo');
$__short    = get_setting('site_short_name', 'PINTU');
$__instansi = get_setting('site_instansi', cfg('instansi'));
$__mb       = get_setting('manual_book_url', '#');
$__current  = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-pintu shadow-sm sticky-top">
  <div class="container">

    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(base_url()) ?>">
      <?php if ($__logo): ?>
        <img src="<?= e(base_url($__logo)) ?>" alt="Logo <?= e($__short) ?>" class="nb-logo">
      <?php else: ?>
        <span class="nb-brand-fallback">
          <i class="bi bi-building-check"></i>
        </span>
        <span class="nb-brand-text">
          <strong><?= e($__short) ?></strong>
          <small><?= e($__instansi) ?></small>
        </span>
      <?php endif; ?>
    </a>

    <!-- Toggler -->
    <button class="navbar-toggler nb-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#navMain"
            aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>

    <!-- Menu -->
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav ms-auto align-items-lg-center nb-menu">

        <li class="nav-item">
          <a class="nav-link nb-link <?= $__current === 'index.php' || $__current === '' ? 'active' : '' ?>"
             href="<?= e(base_url()) ?>">
            <i class="bi bi-house-door"></i>
            <span>Home</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link nb-link <?= $__current === 'data-perkara.php' ? 'active' : '' ?>"
             href="<?= e(base_url('data-perkara.php')) ?>">
            <i class="bi bi-folder2-open"></i>
            <span>Data Perkara</span>
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link nb-link" href="<?= e($__mb ?: '#') ?>" target="_blank" rel="noopener">
            <i class="bi bi-book"></i>
            <span>Manual Book</span>
          </a>
        </li>

        <?php if (is_logged_in()): ?>
          <li class="nav-item nb-cta-wrap">
            <a class="nb-cta" href="<?= e(admin_url('dashboard.php')) ?>">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item nb-cta-wrap">
            <a class="nb-cta nb-cta-login" href="<?= e(base_url('login.php')) ?>">
              <i class="bi bi-box-arrow-in-right"></i> Login
            </a>
          </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>