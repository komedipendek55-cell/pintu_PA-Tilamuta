<?php
$__flashes = get_flashes();
$page_title = $page_title ?? cfg('app_name');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> &mdash; <?= e(cfg('app_name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
<link href="<?= e(base_url('assets/css/accessibility.css')) ?>" rel="stylesheet">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>
<?php if ($__flashes): ?>
<div class="container mt-3">
  <?php foreach ($__flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
      <?= e($f['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>