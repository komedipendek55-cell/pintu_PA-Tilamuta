<footer class="footer-pintu mt-5 py-4">
  <div class="container">
    <div class="row gy-3">
       <div class="col-md-5">
        <h6 class="fw-bold"><?= e(cfg('app_full_name')) ?></h6>
        <p class="small mb-1"><?= e(cfg('instansi')) ?></p>
        <?php
        try {
            $fn = Database::pdo()->query("SELECT setting_value FROM settings WHERE setting_key='footer_note' LIMIT 1")->fetchColumn();
        } catch (Throwable $e) { $fn = ''; }
        ?>
        <p class="small text-muted mb-3"><?= e($fn) ?></p>

        <!-- ============ LINK WEBSITE RESMI ============ -->
        <a href="https://pa-tilamuta.go.id/"
           target="_blank"
           rel="noopener noreferrer"
           class="footer-website-link">
          <span class="fwl-icon">
            <i class="bi bi-globe2"></i>
          </span>
          <span class="fwl-text">
            Informasi Lengkap Tentang <b>Pengadilan Agama Tilamuta</b>
            kunjungi <span class="fwl-url">https://pa-tilamuta.go.id/</span>
          </span>
          <i class="bi bi-box-arrow-up-right fwl-ext"></i>
        </a>
      </div>
      <div class="col-md-4">
        <h6 class="fw-bold">Ikuti Kami</h6>
        <?php
        $socials = [
            'facebook_url'  => ['icon' => 'bi-facebook',  'label' => 'Facebook',  'color' => '#1877F2'],
            'instagram_url' => ['icon' => 'bi-instagram', 'label' => 'Instagram', 'color' => '#E1306C'],
            'youtube_url'   => ['icon' => 'bi-youtube',   'label' => 'YouTube',   'color' => '#FF0000'],
            'tiktok_url'    => ['icon' => 'bi-tiktok',    'label' => 'TikTok',    'color' => '#000000'],
        ];
        ?>
        <div class="d-flex gap-3 fs-5">
          <?php foreach ($socials as $key => $s):
              $url = trim((string) get_setting($key, ''));
              if ($url === '' || $url === '#') continue; ?>
            <a href="<?= e($url) ?>"
               target="_blank" rel="noopener"
               aria-label="<?= e($s['label']) ?>"
               class="social-link"
               style="--brand-color: <?= e($s['color']) ?>; color: <?= e($s['color']) ?>;">
              <i class="bi <?= e($s['icon']) ?>"></i>
            </a>
          <?php endforeach; ?>
        </div>
        <p class="small text-muted mt-2 mb-0">
          <?= e(get_setting('site_instansi', cfg('instansi'))) ?>
        </p>
      </div>
      <div class="col-md-3">
        <h6 class="fw-bold">Statistik Pengunjung</h6>
        <?php $vs = visitor_stats(); ?>
        <ul class="list-unstyled small mb-0">
          <li><i class="bi bi-calendar-day"></i> Hari ini: <strong><?= (int)$vs['vToday'] ?></strong></li>
          <li><i class="bi bi-calendar-month"></i> Bulan ini: <strong><?= (int)$vs['vMonth'] ?></strong></li>
          <li><i class="bi bi-calendar-year"></i> Tahun ini: <strong><?= (int)$vs['vYear'] ?></strong></li>
        </ul>
      </div>
    </div>
    <hr>
    <div class="text-center small text-muted">
      &copy; <?= date('Y') ?> <?= e(cfg('instansi')) ?>. All rights reserved.
    </div>
  </div>
</footer>

<!-- ============ membuat Floating Button ============ -->
<?php
$__wa_url = trim((string) get_setting('whatsapp_url', ''));
$__cb_url = trim((string) get_setting('chatbot_url', ''));
$__has_contact = ($__wa_url !== '') || ($__cb_url !== '');
?>

<?php if ($__has_contact): ?>
<!-- ============ Floating Contact Button ============ -->
<div class="pintu-fab-wrap">
  <button type="button" class="pintu-fab" id="pintuFab" aria-label="Hubungi Kami" aria-haspopup="dialog">
    <i class="bi bi-headset"></i>
    <span class="pintu-fab-ping"></span>
  </button>
</div>

<!-- ============ Popup Kontak (4:6) ============ -->
<div class="pintu-popup-backdrop" id="pintuPopupBackdrop"></div>
<div class="pintu-popup" id="pintuPopup" role="dialog" aria-labelledby="pintuPopupTitle" aria-hidden="true">
  <div class="pintu-popup-header">
    <strong id="pintuPopupTitle"><i class="bi bi-chat-dots-fill me-1"></i> Hubungi Kami</strong>
    <button type="button" class="pintu-popup-close" id="pintuPopupClose" aria-label="Tutup">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <div class="pintu-popup-body">
    <p class="pintu-popup-intro">Butuh Informasi atau Konsultasi silahkan hubungi :</p>

    <?php if ($__wa_url !== ''): ?>
    <a class="pintu-contact-card" href="<?= e($__wa_url) ?>" target="_blank" rel="noopener">
      <div class="pcc-icon pcc-wa"><i class="bi bi-whatsapp"></i></div>
      <div class="pcc-body">
        <div class="pcc-title">Chat WA / Telpon dengan Petugas PTSP PA Tilamuta</div>
        <div class="pcc-note">Aktif pada pukul 08.00&ndash;16.30 WITA</div>
      </div>
      <i class="bi bi-chevron-right pcc-arrow"></i>
    </a>
    <?php endif; ?>

    <?php if ($__cb_url !== ''): ?>
    <a class="pintu-contact-card" href="<?= e($__cb_url) ?>" target="_blank" rel="noopener">
      <div class="pcc-icon pcc-bot"><i class="bi bi-robot"></i></div>
      <div class="pcc-body">
        <div class="pcc-title">Chat dengan Chatbot AI PA Tilamuta</div>
        <div class="pcc-note">Aktif 24 jam</div>
      </div>
      <i class="bi bi-chevron-right pcc-arrow"></i>
    </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- tambah popup awal masuk -->
<?php
$__popupImg = trim((string) get_setting('popup_image', ''));
$__popupOn  = get_setting('popup_enabled', '1') === '1';
if ($__popupImg !== '' && $__popupOn):
?>
<!-- ============ WELCOME POPUP ============ -->
<div class="pintu-welcome-backdrop" id="pintuWelcomeBackdrop"></div>
<div class="pintu-welcome" id="pintuWelcome" role="dialog" aria-modal="true" aria-label="Pengumuman">
  <button type="button" class="pintu-welcome-close" id="pintuWelcomeClose" aria-label="Tutup">
    <i class="bi bi-x-lg"></i>
  </button>
  <div class="pintu-welcome-countdown">
    <span id="pintuWelcomeTimer">8</span>s
  </div>
  <img src="<?= e(base_url($__popupImg)) ?>"
       alt="Pengumuman"
       class="pintu-welcome-img"
       id="pintuWelcomeImg">
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>window.PINTU_BASE = "<?= e(base_url()) ?>";</script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/accessibility.js')) ?>"></script>

<!-- Floating Contact Button — inline, tidak bergantung app.js -->
<script>
(function(){
  'use strict';
  function init(){
    var fab      = document.getElementById('pintuFab');
    var popup    = document.getElementById('pintuPopup');
    var backdrop = document.getElementById('pintuPopupBackdrop');
    var closeBtn = document.getElementById('pintuPopupClose');

    if (!fab || !popup) return;

    function openP(){
      popup.classList.add('show');
      if (backdrop) backdrop.classList.add('show');
      popup.setAttribute('aria-hidden','false');
    }
    function closeP(){
      popup.classList.remove('show');
      if (backdrop) backdrop.classList.remove('show');
      popup.setAttribute('aria-hidden','true');
    }
    function toggleP(){
      if (popup.classList.contains('show')) closeP(); else openP();
    }

    fab.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      toggleP();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeP);
    if (backdrop) backdrop.addEventListener('click', closeP);

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && popup.classList.contains('show')) closeP();
    });

    // Tutup otomatis saat klik card kontak
    var cards = popup.querySelectorAll('.pintu-contact-card');
    for (var i = 0; i < cards.length; i++) {
      cards[i].addEventListener('click', function(){ setTimeout(closeP, 250); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

</body></html>