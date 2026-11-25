<?php $page_title = '403 - Akses Ditolak'; include __DIR__ . '/header.php'; include __DIR__ . '/navbar.php'; ?>
<div class="container py-5 text-center">
  <h1 class="display-4 text-danger"><i class="bi bi-shield-lock"></i></h1>
  <h3>Akses Ditolak</h3>
  <p class="text-muted">Anda tidak memiliki hak untuk mengakses halaman ini.</p>
  <a class="btn btn-pintu" href="<?= e(base_url()) ?>">Kembali ke Home</a>
</div>
<?php include __DIR__ . '/footer.php'; ?>