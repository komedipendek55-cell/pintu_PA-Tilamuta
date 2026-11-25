/* ====================================================================
   ACCESSIBILITY WIDGET — Logic
   ==================================================================== */
(function () {
  'use strict';

  var KEY = 'pintu_a11y';
  var fab      = document.getElementById('a11yFab');
  var panel    = document.getElementById('a11yPanel');
  var backdrop = document.getElementById('a11yBackdrop');
  var closeBtn = document.getElementById('a11yClose');
  var resetBtn = document.getElementById('a11yReset');
  var guideEl  = document.getElementById('a11yGuide');
  var fontInd  = document.getElementById('a11yFontIndicator');

  if (!fab || !panel) { return; }

  /* ---------- State ---------- */
  var state = {
    font: 0,           // -1, 0, 1, 2
    contrast: false,
    dyslexia: false,
    grayscale: false,
    links: false,
    cursor: false,
    spacing: false,
    guide: false
  };

  /* ---------- Load from storage ---------- */
  function loadState(){
    try {
      var raw = localStorage.getItem(KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        Object.keys(state).forEach(function (k) {
          if (typeof parsed[k] !== 'undefined') state[k] = parsed[k];
        });
      }
    } catch (e) { /* ignore */ }
  }

  function saveState(){
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
  }

  /* ---------- Apply all to <body> ---------- */
  function applyState(){
    var b = document.body;

    // Font
    b.classList.remove('a11y-font-neg1', 'a11y-font-1', 'a11y-font-2', 'a11y-font-3');
    if (state.font === -1) b.classList.add('a11y-font-neg1');
    else if (state.font === 1) b.classList.add('a11y-font-1');
    else if (state.font === 2) b.classList.add('a11y-font-2');
    else if (state.font === 3) b.classList.add('a11y-font-3');

    // Toggles
    ['contrast','dyslexia','grayscale','links','cursor','spacing'].forEach(function (k) {
      b.classList.toggle('a11y-' + k, !!state[k]);
    });

    // Guide
    if (guideEl) {
      guideEl.classList.toggle('active', !!state.guide);
    }

    // Font indicator
    if (fontInd) {
      var labels = { '-1': 'Kecil (90%)', '0': 'Normal (100%)', '1': 'Besar (110%)', '2': 'Lebih Besar (120%)', '3': 'Terbesar (135%)' };
      fontInd.textContent = labels[String(state.font)] || 'Normal (100%)';
    }

    // Font buttons active state
    document.querySelectorAll('[data-a11y="font"]').forEach(function (btn) {
      var val = parseInt(btn.dataset.value, 10);
      btn.classList.toggle('active', val === state.font);
    });

    // Toggle tiles active state
    document.querySelectorAll('[data-a11y-toggle]').forEach(function (tile) {
      var k = tile.dataset.a11yToggle;
      tile.setAttribute('aria-pressed', state[k] ? 'true' : 'false');
    });
  }

  /* ---------- Open / Close panel ---------- */
  function openPanel(){
    panel.classList.add('show');
    panel.setAttribute('aria-hidden', 'false');
    panel.removeAttribute('inert');
    if (backdrop) backdrop.classList.add('show');
    fab.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    // Fokus ke tombol close setelah animasi selesai
    setTimeout(function () {
      if (closeBtn) closeBtn.focus();
    }, 350);
  }
  function closePanel(){
    panel.classList.remove('show');
    panel.setAttribute('aria-hidden', 'true');
    panel.setAttribute('inert', '');
    if (backdrop) backdrop.classList.remove('show');
    fab.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    // Kembalikan fokus ke FAB
    if (fab) fab.focus();
  }
  function togglePanel(){
    if (panel.classList.contains('show')) closePanel();
    else openPanel();
  }

  /* ---------- Event: FAB ---------- */
  fab.addEventListener('click', function (e) {
    e.preventDefault();
    togglePanel();
  });
  if (closeBtn) closeBtn.addEventListener('click', closePanel);
  if (backdrop) backdrop.addEventListener('click', closePanel);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && panel.classList.contains('show')) closePanel();
  });

  /* ---------- Event: Font size ---------- */
  document.querySelectorAll('[data-a11y="font"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.font = parseInt(btn.dataset.value, 10) || 0;
      applyState();
      saveState();
    });
  });

  /* ---------- Event: Toggle tiles ---------- */
  document.querySelectorAll('[data-a11y-toggle]').forEach(function (tile) {
    tile.addEventListener('click', function () {
      var k = tile.dataset.a11yToggle;
      state[k] = !state[k];
      applyState();
      saveState();
    });
  });

  /* ---------- Event: Reset ---------- */
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      state = {
        font: 0,
        contrast: false,
        dyslexia: false,
        grayscale: false,
        links: false,
        cursor: false,
        spacing: false,
        guide: false
      };
      applyState();
      saveState();

      // Feedback visual singkat
      resetBtn.style.background = '#d4edda';
      setTimeout(function () { resetBtn.style.background = ''; }, 400);
    });
  }

  /* ---------- Reading Guide: follow cursor ---------- */
  var guideActive = false;
  function onMouseMove(e){
    if (!guideEl) return;
    var y = e.clientY - 3;
    guideEl.style.top = y + 'px';
  }
  function enableGuide(){
    if (guideActive) return;
    guideActive = true;
    document.addEventListener('mousemove', onMouseMove, { passive: true });
    if (guideEl) guideEl.style.top = '50%';
  }
  function disableGuide(){
    if (!guideActive) return;
    guideActive = false;
    document.removeEventListener('mousemove', onMouseMove);
  }

  /* ---------- Init ---------- */
  loadState();
  applyState();

  // Kalau guide aktif dari load, aktifkan mousemove
  if (state.guide) enableGuide();

  // Watch: kalau state.guide berubah via klik, enable/disable
  document.querySelectorAll('[data-a11y-toggle="guide"]').forEach(function (tile) {
    tile.addEventListener('click', function () {
      if (state.guide) enableGuide();
      else disableGuide();
    });
  });

   /* ============================================================
     SCREEN READER (Text-to-Speech) — Web Speech API
     Versi stabil — FIX pause/resume
     ============================================================ */
  (function initTTS(){
    if (!('speechSynthesis' in window)) {
      var section = document.getElementById('a11yTtsSection');
      if (section) {
        section.innerHTML =
          '<div class="a11y-tts-tip">' +
          '<i class="bi bi-exclamation-triangle-fill" style="color:#e53935"></i>' +
          '<span>Browser Anda tidak mendukung fitur pembaca layar.</span>' +
          '</div>';
      }
      return;
    }

    var playBtn     = document.getElementById('a11yTtsPlay');
    var pauseBtn    = document.getElementById('a11yTtsPause');
    var stopBtn     = document.getElementById('a11yTtsStop');
    var selBtn      = document.getElementById('a11yTtsSelected');
    var voiceSelect = document.getElementById('a11yTtsVoice');
    var rateSlider  = document.getElementById('a11yTtsRate');
    var rateValue   = document.getElementById('a11yTtsRateValue');
    var highlightCb = document.getElementById('a11yTtsHighlight');
    var autoScrollCb= document.getElementById('a11yTtsAutoScroll');
    var statusEl    = document.getElementById('a11yTtsStatus');
    var statusText  = statusEl ? statusEl.querySelector('.a11y-tts-status-text') : null;

    if (!playBtn) return;

    var synth = window.speechSynthesis;
    var voices = [];
    var selectedVoiceURI = '';

    /* ---- State global, jelas ---- */
    var stopped = true;     // true = idle / berhenti
    var paused  = false;    // true = sedang dijeda
    var currentUtterance = null;
    var chunkQueue = [];
    var chunkIndex = 0;
    var currentHighlight = null;

    /* ---- Prefs ---- */
    var TTS_KEY = 'pintu_tts';
    var prefs = { voiceURI: '', rate: 1, highlight: true, autoScroll: true };
    try {
      var raw = localStorage.getItem(TTS_KEY);
      if (raw) {
        var p = JSON.parse(raw);
        Object.keys(prefs).forEach(function (k) {
          if (typeof p[k] !== 'undefined') prefs[k] = p[k];
        });
      }
    } catch (e) {}
    function savePrefs(){
      try { localStorage.setItem(TTS_KEY, JSON.stringify(prefs)); } catch (e) {}
    }

    /* ---- Load voices ---- */
    function loadVoices(){
      voices = synth.getVoices() || [];
      voiceSelect.innerHTML = '';

      if (voices.length === 0) {
        var opt = document.createElement('option');
        opt.textContent = 'Memuat suara...';
        voiceSelect.appendChild(opt);
        return;
      }

      voices.sort(function (a, b) {
        var aId = (a.lang || '').toLowerCase().indexOf('id') === 0 ? 0 : 1;
        var bId = (b.lang || '').toLowerCase().indexOf('id') === 0 ? 0 : 1;
        if (aId !== bId) return aId - bId;
        var aEn = (a.lang || '').toLowerCase().indexOf('en') === 0 ? 0 : 1;
        var bEn = (b.lang || '').toLowerCase().indexOf('en') === 0 ? 0 : 1;
        if (aEn !== bEn) return aEn - bEn;
        return (a.name || '').localeCompare(b.name || '');
      });

      var groups = { id: [], en: [], other: [] };
      voices.forEach(function (v) {
        var lang = (v.lang || '').toLowerCase();
        if (lang.indexOf('id') === 0) groups.id.push(v);
        else if (lang.indexOf('en') === 0) groups.en.push(v);
        else groups.other.push(v);
      });

      function addGroup(label, arr){
        if (!arr.length) return;
        var og = document.createElement('optgroup');
        og.label = label;
        arr.forEach(function (v) {
          var opt = document.createElement('option');
          opt.value = v.voiceURI;
          opt.textContent = v.name + ' (' + v.lang + ')';
          og.appendChild(opt);
        });
        voiceSelect.appendChild(og);
      }
      addGroup('Bahasa Indonesia', groups.id);
      addGroup('English', groups.en);
      addGroup('Lainnya', groups.other);

      if (prefs.voiceURI) {
        voiceSelect.value = prefs.voiceURI;
      } else {
        var preferred = groups.id[0] || groups.en[0] || voices[0];
        if (preferred) {
          voiceSelect.value = preferred.voiceURI;
          prefs.voiceURI = preferred.voiceURI;
        }
      }
      selectedVoiceURI = voiceSelect.value;
    }

    if (typeof synth.onvoiceschanged !== 'undefined') {
      synth.onvoiceschanged = loadVoices;
    }
    loadVoices();
    setTimeout(loadVoices, 500);

    /* ---- Init UI ---- */
    if (rateSlider) {
      rateSlider.value = prefs.rate;
      rateValue.textContent = parseFloat(prefs.rate).toFixed(1) + '\u00D7';
    }
    if (highlightCb) highlightCb.checked = prefs.highlight;
    if (autoScrollCb) autoScrollCb.checked = prefs.autoScroll;

    /* ---- Status helper ---- */
    function setStatus(state, text){
      if (!statusEl) return;
      statusEl.classList.remove('speaking', 'paused', 'error');
      if (state) statusEl.classList.add(state);
      if (statusText) statusText.textContent = text;
    }

    /* ---- UI mode helper (SATU sumber kebenaran) ---- */
    function setUIMode(mode){
      playBtn.classList.remove('reading');

      if (mode === 'idle') {
        playBtn.querySelector('i').className = 'bi bi-play-fill';
        playBtn.querySelector('span').textContent = 'Bacakan Halaman';
        pauseBtn.disabled = true;
        stopBtn.disabled = true;
      } else if (mode === 'reading') {
        playBtn.classList.add('reading');
        playBtn.querySelector('i').className = 'bi bi-pause-fill';
        playBtn.querySelector('span').textContent = 'Jeda';
        pauseBtn.disabled = false;
        stopBtn.disabled = false;
      } else if (mode === 'paused') {
        playBtn.querySelector('i').className = 'bi bi-play-fill';
        playBtn.querySelector('span').textContent = 'Lanjutkan';
        pauseBtn.disabled = false;
        stopBtn.disabled = false;
      }
    }

    /* ---- Highlight helpers ---- */
    function clearHighlight(){
      if (currentHighlight) {
        currentHighlight.classList.remove('a11y-tts-highlight');
        currentHighlight = null;
      }
      document.querySelectorAll('.a11y-tts-highlight').forEach(function (el) {
        el.classList.remove('a11y-tts-highlight');
      });
    }
    function setHighlight(el){
      clearHighlight();
      if (!el) return;
      if (highlightCb && !highlightCb.checked) return;
      currentHighlight = el;
      el.classList.add('a11y-tts-highlight');
      if (autoScrollCb && autoScrollCb.checked) {
        try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      }
    }

    /* ---- Split text jadi kalimat (chunking) ---- */
    function splitIntoChunks(text){
      return text
        .split(/(?<=[.!?])\s+|\n+/)
        .map(function (s) { return s.trim(); })
        .filter(function (s) { return s.length > 0; });
    }

    /* ---- Stop total ---- */
    function stopAll(msg){
      stopped = true;
      paused = false;
      chunkQueue = [];
      chunkIndex = 0;
      currentUtterance = null;
      try { synth.cancel(); } catch (e) {}
      setUIMode('idle');
      setStatus('', msg || 'Siap membaca');
      setTimeout(clearHighlight, 300);
    }

    /* ---- Speak chunk berikutnya (rekursif) ---- */
    function speakNextChunk(baseVoice, baseRate){
      if (stopped) return;                          // stop di tengah
      if (chunkIndex >= chunkQueue.length) {
        stopAll('Selesai membaca');
        return;
      }

      var chunkText = chunkQueue[chunkIndex];
      chunkIndex++;

      var u = new SpeechSynthesisUtterance(chunkText);

      if (baseVoice) {
        u.voice = baseVoice;
        u.lang  = baseVoice.lang;
      } else {
        u.lang = 'id-ID';
      }
      u.rate   = baseRate;
      u.pitch  = 1;
      u.volume = 1;

      u.onend = function(){
        if (stopped) return;
        speakNextChunk(baseVoice, baseRate);
      };

      u.onerror = function(ev){
        if (ev.error === 'interrupted' || ev.error === 'canceled') return;
        console.warn('[PINTU TTS chunk]', ev.error);
        if (stopped) return;
        speakNextChunk(baseVoice, baseRate);
      };

      currentUtterance = u;

      try { synth.speak(u); } catch (e) {
        console.warn('[PINTU TTS] speak error:', e);
      }
    }

    /* ---- Speak text utama ---- */
    function speakText(text, targetEl){
      if (!text || !text.trim()) {
        setStatus('error', 'Tidak ada teks untuk dibaca');
        setTimeout(function(){ setStatus('', 'Siap membaca'); }, 2000);
        return;
      }

      /* Reset total */
      try { synth.cancel(); } catch (e) {}
      clearHighlight();

      stopped = false;
      paused  = false;
      chunkQueue = splitIntoChunks(text);
      chunkIndex = 0;

      /* Cari voice + rate */
      var v = voices.find(function (x) { return x.voiceURI === selectedVoiceURI; });
      var baseRate = parseFloat(rateSlider ? rateSlider.value : prefs.rate) || 1;

      /* Set UI DULU — sebelum speak() supaya tombol langsung aktif */
      setUIMode('reading');
      setStatus('speaking', 'Sedang membaca...');

      if (targetEl) setHighlight(targetEl);

      /* Delay 120ms — Chromium butuh jeda setelah cancel() sebelum speak() */
      setTimeout(function(){
        if (!stopped) speakNextChunk(v, baseRate);
      }, 120);
    }

    /* ---- Extract teks halaman ---- */
    function extractReadableText(){
      var root =
        document.getElementById('dashboardContent') ||
        document.querySelector('main') ||
        document.querySelector('article') ||
        document.querySelector('.detail-wrap') ||
        document.querySelector('.container');

      if (!root) root = document.body;

      var clone = root.cloneNode(true);

      var skipSelectors = [
        'nav', 'footer', 'script', 'style', 'noscript',
        '.a11y-fab', '.a11y-panel', '.a11y-backdrop', '.a11y-reading-guide',
        '.sync-overlay', '.pintu-fab-wrap', '.pintu-popup', '.pintu-welcome',
        '.pintu-popup-backdrop', '.pintu-welcome-backdrop',
        '.datap-filter', '.datap-filter-bar',
        'canvas', 'svg',
        '.pagination', '.dph-logout', '.btn', '.nav-link', 'button'
      ];
      skipSelectors.forEach(function (sel) {
        try {
          clone.querySelectorAll(sel).forEach(function (el) { el.remove(); });
        } catch (e) {}
      });

      var text = (clone.innerText || clone.textContent || '')
        .replace(/\s+/g, ' ')
        .replace(/\u00A0/g, ' ')
        .trim();

      text = text.replace(/\s+([.,;:!?])/g, '$1');

      if (text.length > 6000) text = text.substring(0, 6000) + '...';

      return text;
    }

    /* ============================================================
       EVENT LISTENERS
       ============================================================ */

    /* PLAY / PAUSE / RESUME */
    playBtn.addEventListener('click', function(e){
      e.preventDefault();

      /* Sedang membaca & tidak paused -> PAUSE */
      if (!stopped && !paused) {
        try { synth.pause(); } catch (err) {}
        paused = true;
        setUIMode('paused');
        setStatus('paused', 'Dijeda');
        return;
      }

      /* Sedang membaca & paused -> RESUME */
      if (!stopped && paused) {
        try { synth.resume(); } catch (err) {}
        paused = false;
        setUIMode('reading');
        setStatus('speaking', 'Sedang membaca...');
        return;
      }

      /* Belum membaca -> START BARU */
      var text = extractReadableText();
      var target =
        document.getElementById('dashboardContent') ||
        document.querySelector('main') ||
        document.querySelector('.detail-wrap') ||
        document.querySelector('.container') ||
        document.body;
      speakText(text, target);
    });

    /* PAUSE button (dedicated) */
    pauseBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (stopped) return;
      if (paused) {
        try { synth.resume(); } catch (err) {}
        paused = false;
        setUIMode('reading');
        setStatus('speaking', 'Sedang membaca...');
      } else {
        try { synth.pause(); } catch (err) {}
        paused = true;
        setUIMode('paused');
        setStatus('paused', 'Dijeda');
      }
    });

    /* STOP button */
    stopBtn.addEventListener('click', function(e){
      e.preventDefault();
      if (stopped) return;
      stopAll('Dihentikan');
    });

    /* Read SELECTED text */
    selBtn.addEventListener('click', function(e){
      e.preventDefault();
      var sel = window.getSelection();
      var text = sel ? sel.toString().trim() : '';
      if (!text) {
        setStatus('error', 'Tidak ada teks yang dipilih');
        setTimeout(function(){ setStatus('', 'Siap membaca'); }, 2000);
        return;
      }
      speakText(text, null);
    });

    /* Update selected button state */
    function updateSelButton(){
      var sel = window.getSelection();
      var txt = sel ? sel.toString().trim() : '';
      selBtn.disabled = (txt.length === 0);
    }
    document.addEventListener('selectionchange', updateSelButton);
    document.addEventListener('mouseup', updateSelButton);
    document.addEventListener('touchend', updateSelButton);
    updateSelButton();

    /* Voice change */
    voiceSelect.addEventListener('change', function(){
      selectedVoiceURI = voiceSelect.value;
      prefs.voiceURI = selectedVoiceURI;
      savePrefs();
      if (!stopped) {
        var text = extractReadableText();
        var target =
          document.getElementById('dashboardContent') ||
          document.querySelector('main') ||
          document.querySelector('.detail-wrap') ||
          document.body;
        stopAll();
        setTimeout(function(){ speakText(text, target); }, 350);
      }
    });

    /* Rate slider */
    function updateRateFill(){
      if (!rateSlider) return;
      var pct = ((rateSlider.value - 0.5) / (2 - 0.5)) * 100;
      rateSlider.style.setProperty('--fill', pct + '%');
    }
    updateRateFill();

    rateSlider.addEventListener('input', function(){
      var v = parseFloat(rateSlider.value);
      rateValue.textContent = v.toFixed(1) + '\u00D7';
      prefs.rate = v;
      updateRateFill();
      savePrefs();
    });

    /* Checkboxes */
    highlightCb.addEventListener('change', function(){
      prefs.highlight = highlightCb.checked;
      savePrefs();
      if (!highlightCb.checked) clearHighlight();
    });
    autoScrollCb.addEventListener('change', function(){
      prefs.autoScroll = autoScrollCb.checked;
      savePrefs();
    });

    /* Auto-stop saat pindah halaman */
    window.addEventListener('beforeunload', function(){
      try { synth.cancel(); } catch (e) {}
    });

    /* Expose */
    window.PINTU_TTS = {
      speak: speakText,
      stop: function(){ stopAll('Dihentikan'); }
    };

    console.log('[PINTU A11y] Screen Reader siap. Suara tersedia: ' + voices.length);
  })();

  console.log('[PINTU A11y] Widget siap.');
})();