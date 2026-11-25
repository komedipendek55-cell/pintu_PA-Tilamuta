/* PINTU - App JS */
(function(){
  'use strict';

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

  // ==========================================
  // Toggle sidebar (desktop hide/show + mobile off-canvas)
  // ==========================================
  (function initSidebarToggle(){
    const btnToggle = document.getElementById('btnToggleSidebar');
    const sidebar   = document.getElementById('sidebarPintu');
    if (!btnToggle || !sidebar) return;

    // Restore status terakhir (khusus desktop)
    if (window.innerWidth >= 992 && localStorage.getItem('pintu_sidebar_hidden') === '1') {
      document.body.classList.add('sidebar-hidden');
    }

    btnToggle.addEventListener('click', () => {
      if (window.innerWidth < 992) {
        // ---- Mobile: off-canvas ----
        sidebar.classList.toggle('show');
      } else {
        // ---- Desktop: hide/show + simpan preferensi ----
        document.body.classList.toggle('sidebar-hidden');
        const hidden = document.body.classList.contains('sidebar-hidden');
        localStorage.setItem('pintu_sidebar_hidden', hidden ? '1' : '0');
      }
    });

    // Klik di luar sidebar (mobile) → tutup
    document.addEventListener('click', e => {
      if (window.innerWidth < 992
          && sidebar.classList.contains('show')
          && !sidebar.contains(e.target)
          && !btnToggle.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    });

    // Resize dari mobile → desktop: reset state mobile
    let lastW = window.innerWidth;
    window.addEventListener('resize', () => {
      const w = window.innerWidth;
      if (w !== lastW) {
        if (w >= 992) {
          sidebar.classList.remove('show');
          // Restore preferensi desktop
          if (localStorage.getItem('pintu_sidebar_hidden') === '1') {
            document.body.classList.add('sidebar-hidden');
          } else {
            document.body.classList.remove('sidebar-hidden');
          }
        }
        lastW = w;
      }
    });

    // Keyboard shortcut: Ctrl+B → toggle sidebar (opsional, enak buat admin)
    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
        e.preventDefault();
        btnToggle.click();
      }
    });
  })();

  // ------- Public search (AJAX) -------
  const searchInput = document.getElementById('searchInput');
  const cardGrid = document.getElementById('cardGrid');
  const emptyState = document.getElementById('emptyState');

  if (searchInput && cardGrid) {
    let timer = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(runSearch, 250);
    });
    function runSearch(){
      const q = searchInput.value.trim();
      const url = (window.PINTU_BASE || '') + 'api/public_search.php?q=' + encodeURIComponent(q);
      fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' } })
        .then(r => r.json())
        .then(res => {
          if (!res.success) return;
          renderCards(res.data);
        })
        .catch(() => {});
    }
    function renderCards(items){
      if (!items.length){
        cardGrid.innerHTML = '';
        emptyState?.classList.remove('d-none');
        return;
      }
      emptyState?.classList.add('d-none');
      cardGrid.innerHTML = items.map(it => `
        <div class="col-12 col-sm-6 col-lg-4">
          <a class="card card-pintu h-100 text-decoration-none" href="${it.url}">
            <div class="card-body d-flex gap-3">
              <div class="icon-wrap">
                ${it.icon_url
                  ? `<img src="${escapeAttr(it.icon_url)}" alt="">`
                  : `<i class="bi bi-file-earmark-text fs-2"></i>`}
              </div>
              <div>
                <span class="badge badge-cat mb-1">${escapeHtml(it.category_label)}</span>
                <h6 class="mb-1 fw-bold">${escapeHtml(it.name)}</h6>
                <p class="small text-muted mb-0">${escapeHtml(truncate(it.short_description||'', 120))}</p>
              </div>
            </div>
          </a>
        </div>`).join('');
    }
  }

  function escapeHtml(s){ return String(s==null?'':s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
  function escapeAttr(s){ return escapeHtml(s); }
  function truncate(s,n){ return s.length>n ? s.slice(0,n)+'…' : s; }

  // ------- Rating stars (click + hover preview) -------
  (function initRatingStars(){
    const ratingStars = document.getElementById('ratingStars');
    const ratingVal   = document.getElementById('ratingVal');
    if (!ratingStars || !ratingVal) return;

    const stars = Array.from(ratingStars.querySelectorAll('i'));
    if (!stars.length) return;

    // Fungsi utama: warna bintang 1..value jadi kuning
    function paint(value){
      stars.forEach(s => {
        const v = parseInt(s.dataset.val, 10);
        if (v <= value) {
          s.classList.remove('bi-star');
          s.classList.add('bi-star-fill');
        } else {
          s.classList.remove('bi-star-fill');
          s.classList.add('bi-star');
        }
      });
    }

    stars.forEach(star => {
      // Klik → kunci pilihan
      star.addEventListener('click', () => {
        const v = parseInt(star.dataset.val, 10) || 0;
        ratingVal.value = v;
        paint(v);
      });

      // Hover → preview sementara
      star.addEventListener('mouseenter', () => {
        paint(parseInt(star.dataset.val, 10) || 0);
      });
    });

    // Mouse keluar area bintang → balik ke nilai yang sudah dipilih
    ratingStars.addEventListener('mouseleave', () => {
      paint(parseInt(ratingVal.value || '0', 10));
    });

    // Kalau form pre-fill (mis. repopulate setelah error), tampilkan
    if (ratingVal.value) {
      paint(parseInt(ratingVal.value, 10));
    }
  })();

  // ------- Delete buttons (AJAX + confirm) -------
  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-delete');
    if (!btn) return;
    e.preventDefault();
    const url = btn.dataset.url, id = btn.dataset.id, name = btn.dataset.name || 'data ini';
    if (!confirm(`Yakin menghapus ${name}? Tindakan ini tidak dapat dibatalkan.`)) return;

    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', csrfToken);
    btn.disabled = true;

    fetch(url, { method:'POST', body:fd, headers:{ 'X-Requested-With':'XMLHttpRequest' }})
      .then(r => r.json())
      .then(res => {
        if (res.success){
          btn.closest('tr')?.remove();
          showToast(res.message || 'Berhasil.', 'success');
        } else {
          showToast(res.message || 'Gagal.', 'danger');
          btn.disabled = false;
        }
      })
      .catch(() => { showToast('Terjadi kesalahan jaringan.','danger'); btn.disabled = false; });
  });

  // ------- Reset password (AJAX) -------
  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-reset-pwd');
    if (!btn) return;
    e.preventDefault();
    const url = btn.dataset.url, id = btn.dataset.id, name = btn.dataset.name;
    const pwd = prompt(`Masukkan password baru untuk user "${name}" (min 6 karakter):`);
    if (!pwd) return;
    if (pwd.length < 6) { alert('Password minimal 6 karakter.'); return; }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('new_password', pwd);
    fd.append('csrf_token', csrfToken);

    fetch(url, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r => r.json())
      .then(res => showToast(res.message, res.success ? 'success':'danger'))
      .catch(() => showToast('Error jaringan.','danger'));
  });

  // ------- Toast helper -------
  function showToast(msg, type){
    type = type || 'info';
    let box = document.getElementById('pintuToast');
    if (!box){
      box = document.createElement('div');
      box.id = 'pintuToast';
      box.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
      document.body.appendChild(box);
    }
    const el = document.createElement('div');
    el.className = `alert alert-${type} shadow-sm mb-0`;
    el.style.minWidth = '260px';
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

    // ==========================================
  // Welcome Popup — gambar sambutan
  // Muncul sekali per sesi browser, auto-close 8 detik
  // ==========================================
  (function initWelcomePopup(){
    var popup    = document.getElementById('pintuWelcome');
    var backdrop = document.getElementById('pintuWelcomeBackdrop');
    var closeBtn = document.getElementById('pintuWelcomeClose');
    var img      = document.getElementById('pintuWelcomeImg');
    var timerEl  = document.getElementById('pintuWelcomeTimer');
    if (!popup || !img) return;

    var SESSION_KEY   = 'pintu_welcome_shown';
    var AUTO_CLOSE_S  = 8;
    var countdownId   = null;
    var closeTimeout  = null;

    // Sudah ditampilkan di sesi ini? → jangan tampilkan lagi
    try {
      if (sessionStorage.getItem(SESSION_KEY) === '1') return;
    } catch(e) { /* sessionStorage mungkin disabled */ }

    function openPopup(){
      // Tunggu gambar selesai load dulu supaya ukuran pas
      popup.classList.add('show');
      if (backdrop) backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';   // lock scroll

      // Set flag supaya tidak muncul lagi
      try { sessionStorage.setItem(SESSION_KEY, '1'); } catch(e) {}

      // Countdown
      var remaining = AUTO_CLOSE_S;
      if (timerEl) timerEl.textContent = remaining;
      countdownId = setInterval(function(){
        remaining--;
        if (timerEl) timerEl.textContent = Math.max(0, remaining);
        if (remaining <= 0) closePopup();
      }, 1000);

      // Fallback auto-close (double safety)
      closeTimeout = setTimeout(closePopup, AUTO_CLOSE_S * 1000);
    }

    function closePopup(){
      if (countdownId)  { clearInterval(countdownId); countdownId = null; }
      if (closeTimeout) { clearTimeout(closeTimeout); closeTimeout = null; }
      popup.classList.remove('show');
      if (backdrop) backdrop.classList.remove('show');
      document.body.style.overflow = '';
    }

    // Tunggu image benar-benar load → baru tampilkan
    function tryOpen(){
      setTimeout(openPopup, 400);   // delay kecil agar tidak terasa "nyerobot"
    }

    if (img.complete && img.naturalWidth > 0) {
      tryOpen();
    } else {
      img.addEventListener('load', tryOpen);
      img.addEventListener('error', function(){
        console.warn('[PINTU Popup] Gambar gagal dimuat:', img.src);
      });
    }

    // Close button
    if (closeBtn) closeBtn.addEventListener('click', closePopup);

    // Klik backdrop = close
    if (backdrop) backdrop.addEventListener('click', closePopup);

    // ESC = close
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && popup.classList.contains('show')) closePopup();
    });
  })();

  window.PINTU = { showToast, csrfToken };
})();