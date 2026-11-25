<?php
/**
 * Widget Aksesibilitas — Floating panel di kiri bawah.
 * Include di halaman publik (index, detail).
 */
if (!defined('PINTU_A11Y_LOADED')) {
    define('PINTU_A11Y_LOADED', true);
?>

<!-- ============================================================
     ACCESSIBILITY WIDGET (A11y)
     ============================================================ -->
<button type="button"
        class="a11y-fab"
        id="a11yFab"
        aria-label="Buka menu aksesibilitas"
        aria-expanded="false"
        aria-controls="a11yPanel"
        title="Menu Aksesibilitas">
  <i class="bi bi-person-wheelchair"></i>
  <span class="a11y-fab-pulse"></span>
</button>

<div class="a11y-backdrop" id="a11yBackdrop"></div>

<aside class="a11y-panel" id="a11yPanel" aria-hidden="true" inert role="dialog" aria-label="Menu Aksesibilitas">
  <header class="a11y-header">
    <div class="a11y-header-icon">
      <i class="bi bi-person-wheelchair"></i>
    </div>
    <div class="a11y-header-text">
      <h5>Aksesibilitas</h5>
      <p>Sesuaikan tampilan sesuai kebutuhan Anda</p>
    </div>
    <button type="button" class="a11y-close" id="a11yClose" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </header>

  <div class="a11y-body">

    <!-- Ukuran Teks -->
    <div class="a11y-group">
      <label class="a11y-label">
        <i class="bi bi-fonts"></i> Ukuran Teks
      </label>
      <div class="a11y-font-controls">
        <button type="button" class="a11y-font-btn" data-a11y="font" data-value="-1" aria-label="Perkecil teks">
          <span style="font-size:.75rem;font-weight:800;">A</span><sup>-</sup>
        </button>
        <button type="button" class="a11y-font-btn" data-a11y="font" data-value="0" aria-label="Ukuran normal">
          <span style="font-size:1rem;font-weight:800;">A</span>
        </button>
        <button type="button" class="a11y-font-btn" data-a11y="font" data-value="1" aria-label="Perbesar teks">
          <span style="font-size:1.1rem;font-weight:800;">A</span><sup>+</sup>
        </button>
        <button type="button" class="a11y-font-btn" data-a11y="font" data-value="2" aria-label="Perbesar teks lebih">
          <span style="font-size:1.2rem;font-weight:800;">A</span><sup>++</sup>
        </button>
      </div>
      <div class="a11y-font-indicator" id="a11yFontIndicator">Normal (100%)</div>
    </div>

        <!-- ============================================================
         PEMBACA LAYAR (Text-to-Speech)
         ============================================================ -->
    <div class="a11y-tts-section" id="a11yTtsSection">
      <label class="a11y-label">
        <i class="bi bi-volume-up-fill"></i> Pembaca Layar
      </label>

      <!-- Tombol kontrol utama -->
      <div class="a11y-tts-main">
        <button type="button" class="a11y-tts-btn a11y-tts-play" id="a11yTtsPlay">
          <i class="bi bi-play-fill"></i>
          <span>Bacakan Halaman</span>
        </button>
        <button type="button" class="a11y-tts-btn a11y-tts-pause" id="a11yTtsPause" disabled aria-label="Pause">
          <i class="bi bi-pause-fill"></i>
        </button>
        <button type="button" class="a11y-tts-btn a11y-tts-stop" id="a11yTtsStop" disabled aria-label="Stop">
          <i class="bi bi-stop-fill"></i>
        </button>
      </div>

      <!-- Tombol baca teks terpilih -->
      <button type="button" class="a11y-tts-select" id="a11yTtsSelected" disabled>
        <i class="bi bi-highlighter"></i>
        <span>Bacakan Teks yang Dipilih</span>
        <small>(blok dulu teksnya di halaman)</small>
      </button>

      <!-- Pengaturan -->
      <div class="a11y-tts-settings">
        <div class="a11y-tts-row">
          <label for="a11yTtsVoice">
            <i class="bi bi-mic-fill"></i> Suara
          </label>
          <select id="a11yTtsVoice" class="a11y-tts-select-input">
            <option value="">Memuat suara...</option>
          </select>
        </div>

        <div class="a11y-tts-row">
          <label for="a11yTtsRate">
            <i class="bi bi-speedometer2"></i> Kecepatan
            <span class="a11y-tts-rate-value" id="a11yTtsRateValue">1.0×</span>
          </label>
          <input type="range" id="a11yTtsRate" class="a11y-tts-range"
                 min="0.5" max="2" step="0.1" value="1">
        </div>

        <div class="a11y-tts-row a11y-tts-row-inline">
          <label class="a11y-tts-checkbox">
            <input type="checkbox" id="a11yTtsHighlight" checked>
            <span>Sorot kalimat yang dibaca</span>
          </label>
        </div>

        <div class="a11y-tts-row a11y-tts-row-inline">
          <label class="a11y-tts-checkbox">
            <input type="checkbox" id="a11yTtsAutoScroll" checked>
            <span>Auto-scroll ke kalimat aktif</span>
          </label>
        </div>
      </div>

      <!-- Status / indikator -->
      <div class="a11y-tts-status" id="a11yTtsStatus" aria-live="polite">
        <span class="a11y-tts-status-dot"></span>
        <span class="a11y-tts-status-text">Siap membaca</span>
      </div>

      <div class="a11y-tts-tip">
        <i class="bi bi-info-circle-fill"></i>
        <span>Gunakan tombol <b>Play</b> untuk membaca, atau blok teks apa pun lalu klik <b>Bacakan Teks</b>.</span>
      </div>
    </div>
	<!-- Toggle Grid -->
    <div class="a11y-grid">

      <button type="button" class="a11y-tile" data-a11y-toggle="contrast" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-circle-half"></i></span>
        <span class="a11y-tile-label">Kontras Tinggi</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile" data-a11y-toggle="dyslexia" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-book-half"></i></span>
        <span class="a11y-tile-label">Font Disleksia</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile" data-a11y-toggle="grayscale" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-droplet-half"></i></span>
        <span class="a11y-tile-label">Skala Abu-abu</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile" data-a11y-toggle="links" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-link-45deg"></i></span>
        <span class="a11y-tile-label">Sorot Tautan</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile" data-a11y-toggle="cursor" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-cursor-fill"></i></span>
        <span class="a11y-tile-label">Kursor Besar</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile" data-a11y-toggle="spacing" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-textarea-resize"></i></span>
        <span class="a11y-tile-label">Spasi Teks</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile" data-a11y-toggle="guide" aria-pressed="false">
        <span class="a11y-tile-icon"><i class="bi bi-textarea-t"></i></span>
        <span class="a11y-tile-label">Panduan Baca</span>
        <span class="a11y-tile-check"><i class="bi bi-check-circle-fill"></i></span>
      </button>

      <button type="button" class="a11y-tile a11y-tile-reset" id="a11yReset">
        <span class="a11y-tile-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
        <span class="a11y-tile-label">Reset</span>
      </button>

    </div>

    <div class="a11y-footer">
      <i class="bi bi-info-circle"></i>
      <span>Pengaturan tersimpan otomatis di browser Anda.</span>
    </div>

  </div>
</aside>

<!-- Garis panduan baca -->
<div class="a11y-reading-guide" id="a11yGuide" aria-hidden="true"></div>

<?php } ?>