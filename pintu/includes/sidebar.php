<?php
$role = current_role();
$__menu = [
    ['url' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'speedometer2', 'roles' => ALL_ROLES],
];
if (in_array($role, [ROLE_ADMIN, ROLE_PANITERA_GUGATAN, ROLE_PANITERA_PERMOHONAN, ROLE_PANITERA_HUKUM], true)) {
    $__menu[] = ['url' => 'perkara.php', 'label' => 'Perkara & Layanan', 'icon' => 'file-earmark-text', 'roles' => [ROLE_ADMIN, ROLE_PANITERA_GUGATAN, ROLE_PANITERA_PERMOHONAN, ROLE_PANITERA_HUKUM]];
}
if (in_array($role, [ROLE_ADMIN, ROLE_KASIR], true)) {
    $__menu[] = ['url' => 'biaya.php', 'label' => 'Biaya Perkara', 'icon' => 'cash-coin', 'roles' => [ROLE_ADMIN, ROLE_KASIR]];
}
// Menu Jenis Biaya → Admin & Kasir
if (in_array($role, [ROLE_ADMIN, ROLE_KASIR], true)) {
    $__menu[] = ['url'=>'cost-types.php','label'=>'Jenis Biaya','icon'=>'tags'];
}
// Menu Sync Data Perkara → Admin & Panitera Hukum
if (in_array($role, [ROLE_ADMIN, ROLE_PANITERA_HUKUM], true)) {
    $__menu[] = [
        'url'   => 'sync-data.php',
        'label' => 'Sync Data Perkara',
        'icon'  => 'arrow-repeat',
    ];
}
if ($role === ROLE_ADMIN) {
    $__menu[] = ['url'=>'users.php','label'=>'Pengguna','icon'=>'people'];
    $__menu[] = ['url'=>'settings.php','label'=>'Settings','icon'=>'gear'];
    $__menu[] = ['url'=>'monitoring.php','label'=>'Monitoring','icon'=>'graph-up'];
    $__menu[] = ['url'=>'feedback.php','label'=>'Feedback','icon'=>'chat-square-heart'];
}
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar-pintu" id="sidebarPintu">
  <div class="brand">
  <?php $__logo = get_setting('site_logo'); ?>
  <?php if ($__logo): ?>
    <img src="<?= e(base_url($__logo)) ?>" alt="Logo" style="max-height:28px;width:auto;">
  <?php else: ?>
    <i class="bi bi-building-check"></i>
  <?php endif; ?>
  <span>PINTU Admin</span>
</div>
  <ul class="nav flex-column">
    <?php foreach ($__menu as $m): ?>
      <li class="nav-item">
        <a class="nav-link <?= $current === $m['url'] ? 'active' : '' ?>" href="<?= e(admin_url($m['url'])) ?>">
          <i class="bi bi-<?= e($m['icon']) ?>"></i> <span><?= e($m['label']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
    <li class="nav-item mt-2"><hr class="mx-3 text-muted"></li>
    <li class="nav-item">
      <a class="nav-link" href="<?= e(base_url()) ?>" target="_blank"><i class="bi bi-globe"></i> <span>Lihat Publik</span></a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?= e(admin_url('profile.php')) ?>"><i class="bi bi-person-gear"></i> <span>Profil Saya</span></a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
    </li>
  </ul>
</aside>