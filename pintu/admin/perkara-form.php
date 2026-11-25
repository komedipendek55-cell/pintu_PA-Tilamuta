<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$role = current_role();
$allowed = role_allowed_categories($role);
if (empty($allowed)) {
    http_response_code(403);
    include __DIR__ . '/../includes/403.php';
    exit;
}

$pdo = Database::pdo();
$id  = (int)($_GET['id'] ?? 0);

$data = [
    'id'                 => 0,
    'category'           => ($allowed[0] ?? CAT_GUGATAN),
    'name'               => '',
    'icon_path'          => '',
    'short_description'  => '',
    'description_html'   => '',
    'additional_notes'   => '',
    'cost_type'          => '',
    'process_flow'       => '',
    'process_flow_url'   => '',
    'final_product'      => '',
    'status'             => STATUS_PUBLISHED,
];
$reqs = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM perkara WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found) {
        set_flash('danger', 'Data tidak ditemukan.');
        redirect(admin_url('perkara.php'));
    }
    if (!in_array($found['category'], $allowed, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/403.php';
        exit;
    }

    $data = array_merge($data, $found);

    $rq = $pdo->prepare("SELECT * FROM persyaratan WHERE perkara_id=? ORDER BY sequence_no");
    $rq->execute([$id]);
    $reqs = $rq->fetchAll();
}

$page_title = $id ? 'Edit Perkara/Layanan' : 'Tambah Perkara/Layanan';
include __DIR__ . '/../includes/admin_header.php';
?>

<!-- ============================================================
     FORM PERKARA / LAYANAN
     ============================================================ -->
<form method="post" action="<?= e(base_url('actions/perkara_save.php')) ?>"
      enctype="multipart/form-data" class="row g-3">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">

  <!-- ==================== KOLOM KIRI (UTAMA) ==================== -->
  <div class="col-lg-8">

    <!-- CARD: Informasi Utama -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold">
        <i class="bi bi-info-circle text-success"></i> Informasi Utama
      </div>
      <div class="card-body row g-3">

        <div class="col-md-4">
          <label class="form-label">Kategori <span class="text-danger">*</span></label>
          <select class="form-select" name="category" required>
            <?php foreach ($allowed as $c): ?>
              <option value="<?= e($c) ?>" <?= $data['category'] === $c ? 'selected' : '' ?>>
                <?= e(CATEGORY_LABELS[$c]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label">Nama Jenis <span class="text-danger">*</span></label>
          <input class="form-control" name="name" maxlength="200" required
                 value="<?= e($data['name']) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Deskripsi Singkat</label>
          <textarea class="form-control" name="short_description" rows="2" maxlength="500"><?= e($data['short_description']) ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">
            <i class="bi bi-pencil-square text-success"></i> Deskripsi Lengkap
            <small class="text-muted fw-normal ms-1">
              (gunakan toolbar untuk format teks, tabel, gambar, dan tautan)
            </small>
          </label>
          <div class="rte-wrapper">
            <textarea id="descriptionEditor" name="description_html"
                      class="form-control" rows="10"><?= e($data['description_html']) ?></textarea>
          </div>
          <small class="text-muted mt-1 d-block">
            <i class="bi bi-shield-check text-success"></i>
            Konten akan otomatis disanitasi di server sebelum disimpan — tag berbahaya seperti
            <code>&lt;script&gt;</code>, <code>&lt;iframe&gt;</code>, dan event handler akan dihapus.
          </small>
        </div>

        <div class="col-12">
          <label class="form-label">Keterangan Tambahan</label>
          <textarea class="form-control" name="additional_notes" rows="2"><?= e($data['additional_notes']) ?></textarea>
        </div>

      </div>
    </div>

    <!-- CARD: Persyaratan -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <div>
          <span>Persyaratan</span>
          <small class="text-muted ms-2 fw-normal">
            <i class="bi bi-info-circle"></i>
            Drag ikon <i class="bi bi-grip-vertical"></i> atau pakai tombol ↑↓ untuk atur urutan
          </small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnAddReq">
          <i class="bi bi-plus-lg"></i> Tambah
        </button>
      </div>
      <div class="card-body">
        <div id="reqList" class="req-list">

          <?php if (!$reqs): ?>
            <div class="req-item">
              <span class="req-handle" title="Geser untuk atur urutan">
                <i class="bi bi-grip-vertical"></i>
              </span>
              <span class="req-num">1</span>
              <input class="form-control req-input" name="requirements[]"
                     placeholder="Tulis persyaratan..." maxlength="500">
              <span class="req-actions">
                <button type="button" class="req-btn btn-up" title="Naik" disabled>
                  <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="req-btn btn-down" title="Turun" disabled>
                  <i class="bi bi-arrow-down"></i>
                </button>
                <button type="button" class="req-btn btn-del-req text-danger" title="Hapus">
                  <i class="bi bi-trash"></i>
                </button>
              </span>
            </div>
          <?php else: ?>
            <?php foreach ($reqs as $r): ?>
              <div class="req-item">
                <span class="req-handle" title="Geser untuk atur urutan">
                  <i class="bi bi-grip-vertical"></i>
                </span>
                <span class="req-num"></span>
                <input class="form-control req-input" name="requirements[]"
                       value="<?= e($r['requirement_text']) ?>"
                       placeholder="Tulis persyaratan..." maxlength="500">
                <span class="req-actions">
                  <button type="button" class="req-btn btn-up" title="Naik">
                    <i class="bi bi-arrow-up"></i>
                  </button>
                  <button type="button" class="req-btn btn-down" title="Turun">
                    <i class="bi bi-arrow-down"></i>
                  </button>
                  <button type="button" class="req-btn btn-del-req text-danger" title="Hapus">
                    <i class="bi bi-trash"></i>
                  </button>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- CARD: Alur & Produk Akhir -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold">
        <i class="bi bi-diagram-3 text-primary"></i> Alur &amp; Produk Akhir
      </div>
      <div class="card-body row g-3">

        <div class="col-12">
          <label class="form-label">Alur Berperkara / Layanan</label>
          <textarea class="form-control" name="process_flow" rows="4"
                    placeholder="Satu langkah per baris..."><?= e($data['process_flow']) ?></textarea>
          <small class="text-muted">Tulis satu langkah per baris. Akan ditampilkan sebagai timeline.</small>
        </div>

        <div class="col-12">
          <label class="form-label">URL Alur <span class="text-muted small">(opsional)</span></label>
          <input class="form-control" name="process_flow_url"
                 value="<?= e($data['process_flow_url']) ?>" maxlength="500"
                 placeholder="https://...">
        </div>

        <div class="col-12">
          <label class="form-label">Produk Akhir</label>
          <textarea class="form-control" name="final_product" rows="3"><?= e($data['final_product']) ?></textarea>
        </div>

      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- ==================== KOLOM KANAN (SIDEBAR) ==================== -->
  <div class="col-lg-4">

    <!-- CARD: Publikasi -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold">
        <i class="bi bi-globe text-primary"></i> Publikasi
      </div>
      <div class="card-body">

        <label class="form-label">Status</label>
        <select class="form-select mb-3" name="status">
          <option value="PUBLISHED" <?= $data['status'] === 'PUBLISHED' ? 'selected' : '' ?>>Published</option>
          <option value="DRAFT"     <?= $data['status'] === 'DRAFT'     ? 'selected' : '' ?>>Draft</option>
        </select>

        <label class="form-label">Jenis Biaya (untuk kalkulator)</label>
        <select class="form-select" name="cost_type">
          <option value="">— Tidak ada biaya —</option>
          <?php foreach (get_cost_types() as $ct): ?>
            <option value="<?= e($ct['code']) ?>"
                    <?= $data['cost_type'] === $ct['code'] ? 'selected' : '' ?>>
              <?= e($ct['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>

      </div>
    </div><!-- /Card Publikasi -->

    <!-- CARD: Icon -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-bold">
        <i class="bi bi-image text-warning"></i> Icon
        <small class="text-muted fw-normal">(1:1, max 200 KB)</small>
      </div>
      <div class="card-body">

        <?php if (!empty($data['icon_path'])): ?>
          <div class="mb-2 text-center">
            <img src="<?= e(base_url($data['icon_path'])) ?>"
                 class="rounded border" width="80" height="80" alt="Icon">
          </div>
        <?php endif; ?>

        <input type="file" class="form-control" name="icon"
               accept=".png,.jpg,.jpeg,.webp,.svg">
        <small class="text-muted d-block mt-1">
          Format: PNG / JPG / WEBP / SVG
        </small>

      </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="d-grid gap-2">
      <button class="btn btn-pintu">
        <i class="bi bi-save"></i> Simpan
      </button>
      <a href="<?= e(admin_url('perkara.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-x-lg"></i> Batal
      </a>
    </div>

  </div><!-- /col-lg-4 -->

</form>


<!-- ============================================================
     SCRIPT: SortableJS — drag & drop persyaratan
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function(){
  'use strict';

  var list   = document.getElementById('reqList');
  var btnAdd = document.getElementById('btnAddReq');
  if (!list) return;

  /* ---------- Renumber + atur disable tombol tepi ---------- */
  function renumber(){
    var items = list.querySelectorAll('.req-item');
    items.forEach(function(item, i){
      var num = item.querySelector('.req-num');
      if (num) num.textContent = i + 1;

      var up   = item.querySelector('.btn-up');
      var down = item.querySelector('.btn-down');
      if (up)   up.disabled   = (i === 0);
      if (down) down.disabled = (i === items.length - 1);
    });
  }

  /* ---------- Buat item requirement baru ---------- */
  function createItem(value){
    var el = document.createElement('div');
    el.className = 'req-item';
    el.innerHTML =
      '<span class="req-handle" title="Geser untuk atur urutan">' +
        '<i class="bi bi-grip-vertical"></i>' +
      '</span>' +
      '<span class="req-num"></span>' +
      '<input class="form-control req-input" name="requirements[]" ' +
             'placeholder="Tulis persyaratan..." maxlength="500">' +
      '<span class="req-actions">' +
        '<button type="button" class="req-btn btn-up" title="Naik">' +
          '<i class="bi bi-arrow-up"></i>' +
        '</button>' +
        '<button type="button" class="req-btn btn-down" title="Turun">' +
          '<i class="bi bi-arrow-down"></i>' +
        '</button>' +
        '<button type="button" class="req-btn btn-del-req text-danger" title="Hapus">' +
          '<i class="bi bi-trash"></i>' +
        '</button>' +
      '</span>';
    if (value) el.querySelector('input').value = value;
    return el;
  }

  /* ---------- SortableJS ---------- */
  if (window.Sortable) {
    new Sortable(list, {
      handle:        '.req-handle',
      animation:     180,
      ghostClass:    'req-ghost',
      chosenClass:   'req-chosen',
      dragClass:     'req-drag',
      forceFallback: false,
      onEnd:         renumber,
      onSort:        renumber
    });
  }

  /* ---------- Tombol Tambah ---------- */
  if (btnAdd) {
    btnAdd.addEventListener('click', function(){
      var items = list.querySelectorAll('.req-item');
      if (items.length === 0) {
        var empty = createItem('');
        list.appendChild(empty);
        empty.querySelector('input').focus();
        renumber();
        return;
      }
      var fresh = createItem('');
      list.appendChild(fresh);
      fresh.querySelector('input').focus();
      fresh.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      renumber();
    });
  }

  /* ---------- Event: Hapus / Naik / Turun ---------- */
  list.addEventListener('click', function(e){
    var btn = e.target.closest('button');
    if (!btn) return;
    var item = btn.closest('.req-item');
    if (!item) return;

    // Hapus
    if (btn.classList.contains('btn-del-req')) {
      var items = list.querySelectorAll('.req-item');
      if (items.length > 1) {
        item.style.transition = 'opacity .2s, transform .2s';
        item.style.opacity    = '0';
        item.style.transform  = 'translateX(-12px)';
        setTimeout(function(){ item.remove(); renumber(); }, 180);
      } else {
        item.querySelector('input').value = '';
        item.querySelector('input').focus();
      }
      return;
    }

    // Naik
    if (btn.classList.contains('btn-up')) {
      var prev = item.previousElementSibling;
      if (prev) {
        list.insertBefore(item, prev);
        renumber();
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }

    // Turun
    if (btn.classList.contains('btn-down')) {
      var next = item.nextElementSibling;
      if (next) {
        list.insertBefore(next, item);
        renumber();
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }
  });

  /* ---------- Shortcut: Alt + ↑/↓ ---------- */
  list.addEventListener('keydown', function(e){
    if (!e.altKey) return;
    var input = e.target.closest('.req-input');
    if (!input) return;
    var item = input.closest('.req-item');

    if (e.key === 'ArrowUp') {
      e.preventDefault();
      var up = item.querySelector('.btn-up');
      if (up && !up.disabled) up.click();
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      var down = item.querySelector('.btn-down');
      if (down && !down.disabled) down.click();
    }
  });

  /* ---------- Auto-scroll saat drag ke tepi ---------- */
  var autoScrollTimer = null;
  document.addEventListener('dragover', function(e){
    if (!document.querySelector('.req-drag')) return;
    var y = e.clientY;
    var edge = 100;
    var speed = 0;
    if (y < edge) speed = -(edge - y) / 6;
    else if (y > window.innerHeight - edge) speed = (y - (window.innerHeight - edge)) / 6;

    if (speed !== 0) {
      if (!autoScrollTimer) {
        autoScrollTimer = setInterval(function(){ window.scrollBy(0, speed); }, 16);
      }
    } else if (autoScrollTimer) {
      clearInterval(autoScrollTimer);
      autoScrollTimer = null;
    }
  });
  document.addEventListener('dragend', function(){
    if (autoScrollTimer) {
      clearInterval(autoScrollTimer);
      autoScrollTimer = null;
    }
  });

  /* ---------- Init ---------- */
  renumber();
})();
</script>


<!-- ============================================================
     SCRIPT: TinyMCE — Rich Text Editor Deskripsi Lengkap
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.3.0/tinymce.min.js"
        referrerpolicy="origin"></script>
<script>
(function(){
  'use strict';

  if (!window.tinymce) {
    console.warn('[PINTU RTE] TinyMCE gagal dimuat. Textarea fallback aktif.');
    return;
  }

  tinymce.init({
    selector: '#descriptionEditor',
    height: 420,
    menubar: false,
    branding: false,
    promotion: false,
    elementpath: false,
    statusbar: true,
    resize: true,

    plugins: [
      'lists', 'link', 'image', 'table', 'code',
      'fullscreen', 'preview', 'searchreplace',
      'autolink', 'charmap', 'insertdatetime',
      'wordcount', 'visualblocks', 'quickbars'
    ],

    toolbar: [
      'undo redo | blocks | bold italic underline strikethrough',
      'forecolor backcolor | alignleft aligncenter alignright alignjustify',
      'bullist numlist outdent indent | link image table | removeformat',
      'code preview fullscreen'
    ].join(' | '),

    block_formats:
      'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Preformatted=pre',

    language: 'id',
    language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.9.17/langs7/id.js',

    /* Paste — bersihkan otomatis dari Word */
    paste_as_text: false,
    paste_auto_cleanup_on_paste: true,
    paste_remove_styles_if_webkit: true,
    paste_remove_styles: true,
    paste_merge_formats: true,
    paste_webkit_styles: 'none',

    /* Link default */
    link_default_target: '_blank',
    link_assume_external_targets: true,

    /* Gambar — embed base64 (tanpa upload ke server) */
    automatic_uploads: true,
    images_upload_handler: function(blobInfo){
      return new Promise(function(resolve, reject){
        var reader = new FileReader();
        reader.onload = function(){ resolve(reader.result); };
        reader.onerror = function(){
          reject({ message: 'Gagal membaca file gambar.', remove: true });
        };
        reader.readAsDataURL(blobInfo.blob());
      });
    },
    image_advtab: true,
    image_title: true,

    /* Content style — selaras tema PINTU */
    content_style: `
      body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        font-size: 15px; line-height: 1.65;
        color: #1a2a1e; padding: 12px 16px; background: #fff;
      }
      h2, h3, h4 { color: #0f3d24; font-weight: 700; margin-top: 1.3em; margin-bottom: .5em; }
      h2 { font-size: 1.5rem; border-bottom: 2px solid #e8f5ec; padding-bottom: 6px; }
      h3 { font-size: 1.25rem; }
      h4 { font-size: 1.05rem; }
      a { color: #1f7a3d; text-decoration: underline; }
      a:hover { color: #0f3d24; }
      ul, ol { padding-left: 1.5em; margin-bottom: 1em; }
      li { margin-bottom: .3em; }
      blockquote {
        border-left: 4px solid #f5b301; background: #fffbe9;
        padding: 10px 16px; margin: 1em 0;
        color: #6b5a1e; font-style: italic;
        border-radius: 0 8px 8px 0;
      }
      table { border-collapse: collapse; width: 100%; margin: 1em 0; }
      table th, table td { border: 1px solid #d5dcd7; padding: 8px 12px; text-align: left; }
      table th { background: #e8f5ec; color: #0f3d24; font-weight: 700; }
      img { max-width: 100%; height: auto; border-radius: 8px; }
      code, pre {
        background: #f1f4f2; border-radius: 6px; padding: 2px 6px;
        font-family: Consolas, Monaco, monospace; font-size: .9em;
      }
      pre { padding: 12px 16px; display: block; white-space: pre-wrap; }
    `,

    setup: function(editor){
      editor.on('init', function(){
        console.log('[PINTU RTE] TinyMCE siap.');
      });
    },

    mobile: {
      menubar: false,
      plugins: 'lists link image code',
      toolbar: 'undo redo | bold italic | bullist numlist | link image | code'
    }
  });
})();
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>