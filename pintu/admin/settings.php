<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_ADMIN]);

$pdo = Database::pdo();
$page_title = 'Pengaturan';

$keys = ['site_name','site_short_name','site_instansi','manual_book_url','facebook_url','instagram_url','youtube_url','tiktok_url','survey_url','footer_note','site_logo','whatsapp_url','chatbot_url','popup_image','popup_enabled'];
$in = implode(',', array_fill(0, count($keys), '?'));
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)");
$stmt->execute($keys);
$vals = [];
foreach ($stmt->fetchAll() as $r) $vals[$r['setting_key']] = $r['setting_value'];

$currentLogo  = $vals['site_logo'] ?? '';
$currentPopup = $vals['popup_image'] ?? '';

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-bold"><i class="bi bi-gear text-success"></i> Pengaturan Situs</div>
  <div class="card-body">

    <!-- ⚠️ enctype WAJIB untuk upload file -->
    <form method="post" action="<?= e(base_url('actions/settings_save.php')) ?>"
      class="row g-3" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="col-md-6">
        <label class="form-label">Nama Aplikasi</label>
        <input class="form-control" name="site_name" value="<?= e($vals['site_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Nama Singkat</label>
        <input class="form-control" name="site_short_name" value="<?= e($vals['site_short_name'] ?? '') ?>">
      </div>
     
	      <!-- ============ POPUP SAMBUTAN ============ -->
      <div class="col-12">
        <hr>
        <h6 class="fw-bold mb-2">
          <i class="bi bi-image text-success"></i> Popup Gambar Sambutan
        </h6>
        <p class="small text-muted mb-3">
          Gambar ini akan muncul otomatis sebagai popup ketika pengunjung
          <b>pertama kali</b> membuka website dalam satu sesi.
          Popup menutup otomatis setelah <b>8 detik</b> atau bisa ditutup manual.
          Ukuran popup otomatis mengikuti resolusi gambar.
          Format: <b>PNG/JPG/WEBP</b>, maks <b>200 KB</b>.
        </p>

        <div class="row g-3 align-items-center">
          <div class="col-md-3 text-center">
            <div class="border rounded p-2 bg-light" style="min-height:100px;">
              <?php if ($currentPopup): ?>
                <img src="<?= e(base_url($currentPopup)) ?>?v=<?= time() ?>"
                     alt="Popup" style="max-height:100px;max-width:100%;">
              <?php else: ?>
                <i class="bi bi-image fs-1 text-muted"></i>
                <p class="small text-muted mb-0 mt-1">Belum ada gambar</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <input type="file" class="form-control" name="popup_image"
                   accept=".png,.jpg,.jpeg,.webp">
            <?php if ($currentPopup): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_popup" value="1" id="removePopup">
                <label class="form-check-label text-danger small" for="removePopup">
                  Hapus gambar popup
                </label>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-bold">Status Popup</label>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="popup_enabled" value="1"
                     id="popupEnabled" <?= ($vals['popup_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
              <label class="form-check-label" for="popupEnabled">Aktifkan</label>
            </div>
          </div>
        </div>
        <hr>
      </div>

	 <!-- ============ KONTAK FLOATING BUTTON ============ -->
		<div class="col-12">
		  <hr>
		  <h6 class="fw-bold mb-2">
			<i class="bi bi-headset text-success"></i> Kontak Floating Button
		  </h6>
		  <p class="small text-muted mb-3">
			Tombol melayang akan muncul di pojok kanan bawah halaman publik.
			Kosongkan salah satu URL jika card tidak ingin ditampilkan.
			Jika <b>kedua</b> URL kosong, tombol tidak muncul sama sekali.
		  </p>
		</div>

		<div class="col-md-6">
		  <label class="form-label">
			<i class="bi bi-whatsapp text-success"></i> URL WhatsApp Petugas PTSP
		  </label>
		  <input class="form-control" name="whatsapp_url" maxlength="500"
				 value="<?= e($vals['whatsapp_url'] ?? '') ?>"
				 placeholder="https://wa.me/6281234567890">
		  <small class="text-muted">
			Format: <code>https://wa.me/628xxxxxxxxxx</code> untuk WA,
			atau <code>tel:+628xxxxxxxxxx</code> untuk telpon langsung.
		  </small>
		</div>

		<div class="col-md-6">
		  <label class="form-label">
			<i class="bi bi-robot text-success"></i> URL Chatbot AI
		  </label>
		  <input class="form-control" name="chatbot_url" maxlength="500"
				 value="<?= e($vals['chatbot_url'] ?? '') ?>"
				 placeholder="https://chat.example.com/pintu">
		  <small class="text-muted">
			Link ke halaman/webhook chatbot AI. Bisa Telegram, WhatsApp bot, atau web custom.
		  </small>
		</div>
		<hr>
	  <div class="col-12">
        <label class="form-label">Nama Instansi</label>
        <input class="form-control" name="site_instansi" value="<?= e($vals['site_instansi'] ?? '') ?>">
      </div>

      <!-- ============ UPLOAD LOGO ============ -->
      <div class="col-12">
        <hr>
        <label class="form-label fw-bold">Logo Aplikasi</label>
        <div class="row g-3 align-items-center">
          <div class="col-md-3 text-center">
            <div class="border rounded p-3 bg-light" style="min-height:80px;">
              <?php if ($currentLogo): ?>
                <img src="<?= e(base_url($currentLogo)) ?>?v=<?= time() ?>"
                     alt="Logo" style="max-height:60px;max-width:100%;">
              <?php else: ?>
                <i class="bi bi-building-check fs-1 text-success"></i>
                <p class="small text-muted mb-0 mt-1">Default icon</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-9">
            <input type="file" class="form-control" name="site_logo"
                   accept=".png,.jpg,.jpeg,.webp">
            <small class="text-muted">
              Format: <b>PNG/JPG/WEBP</b> &middot;
              Maks: <b>500 KB</b> &middot;
              Rekomendasi rasio horizontal (mis. 200×60 px) dengan latar transparan (PNG).
            </small>
            <?php if ($currentLogo): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
                <label class="form-check-label text-danger small" for="removeLogo">
                  Hapus logo &amp; kembali ke icon default
                </label>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <hr>
      </div>

      <div class="col-md-6">
        <label class="form-label">URL Manual Book</label>
        <input class="form-control" name="manual_book_url" value="<?= e($vals['manual_book_url'] ?? '') ?>" maxlength="500">
      </div>
      <div class="col-md-6">
        <label class="form-label">URL Survey</label>
        <input class="form-control" name="survey_url" value="<?= e($vals['survey_url'] ?? '') ?>" maxlength="500">
      </div>
            <div class="col-md-3">
        <label class="form-label">
          <i class="bi bi-facebook text-primary"></i> Facebook URL
        </label>
        <input class="form-control" name="facebook_url"
               value="<?= e($vals['facebook_url'] ?? '') ?>" maxlength="500"
               placeholder="https://facebook.com/...">
      </div>
      <div class="col-md-3">
        <label class="form-label">
          <i class="bi bi-instagram text-danger"></i> Instagram URL
        </label>
        <input class="form-control" name="instagram_url"
               value="<?= e($vals['instagram_url'] ?? '') ?>" maxlength="500"
               placeholder="https://instagram.com/...">
      </div>
      <div class="col-md-3">
        <label class="form-label">
          <i class="bi bi-youtube text-danger"></i> YouTube URL
        </label>
        <input class="form-control" name="youtube_url"
               value="<?= e($vals['youtube_url'] ?? '') ?>" maxlength="500"
               placeholder="https://youtube.com/@...">
      </div>
      <div class="col-md-3">
        <label class="form-label">
          <i class="bi bi-tiktok"></i> TikTok URL
        </label>
        <input class="form-control" name="tiktok_url"
               value="<?= e($vals['tiktok_url'] ?? '') ?>" maxlength="500"
               placeholder="https://tiktok.com/@...">
        <small class="text-muted">
          Format: <code>https://www.tiktok.com/@username</code>
        </small>
      </div>
      <div class="col-12">
        <label class="form-label">Catatan Footer</label>
        <textarea class="form-control" name="footer_note" rows="2"><?= e($vals['footer_note'] ?? '') ?></textarea>
      </div>
      <div class="col-12">
        <button class="btn btn-pintu"><i class="bi bi-save"></i> Simpan Pengaturan</button>
      </div>
    </form>

  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>