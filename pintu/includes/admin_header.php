<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$page_title = $page_title ?? 'Dashboard';
$__flashes = get_flashes();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> &mdash; Admin PINTU</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="admin-body">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main" id="adminMain">
  <nav class="topbar-pintu d-flex align-items-center justify-content-between px-3">
    <button class="btn btn-sm btn-outline-secondary" id="btnToggleSidebar" title="Toggle Sidebar">
		<i class="bi bi-list"></i>
	</button>
    <div class="ms-2">
      <h5 class="mb-0"><?= e($page_title) ?></h5>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-light text-dark border">
        <i class="bi bi-person-circle"></i>
        <?= e(current_user()['full_name']) ?>
        <small class="text-muted">&middot; <?= e(ROLE_LABELS[current_role()] ?? current_role()) ?></small>
      </span>
    </div>
  </nav>
  <div class="admin-content">
  <?php if ($__flashes): ?>
    <?php foreach ($__flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
        <?= e($f['msg']) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>