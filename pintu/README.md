# PINTU — Portal Informasi Perkara Terpadu
**Pengadilan Agama Tilamuta**

Aplikasi web berbasis PHP Native + MySQL untuk menyediakan informasi perkara & layanan hukum secara terpusat, dengan dashboard admin ber-role untuk Panitera (Gugatan/Permohonan/Hukum), Kasir, dan Admin.

---

## ✨ Fitur Utama

### Publik (tanpa login)
- 🔍 Pencarian live (AJAX) nama perkara/layanan/kategori
- 📄 Halaman detail: deskripsi, persyaratan, alur, produk akhir
- 🖨️ Cetak checklist persyaratan (print view)
- 🧮 Kalkulator estimasi biaya real-time (Gugatan / Permohonan)
- ⭐ Rating & kritik-saran
- 📊 Visitor counter dedup per hari

### Internal (login + RBAC)
- 🎛️ Dashboard dengan statistik real dari DB
- 📁 CRUD Perkara/Layanan (Gugatan, Permohonan, Hukum) + persyaratan multi-item
- 💰 CRUD Biaya Perkara (soft delete)
- 👥 Manajemen pengguna + reset password
- ⚙️ Settings (manual book, sosial media, survey, footer)
- 📈 Monitoring pengunjung harian/bulanan/tahunan
- 📝 Feedback moderation (Pending/Published/Archived)
- 🔐 Change password mandiri

---

## 🖥️ Requirement

- **XAMPP** (Apache + MySQL/MariaDB + PHP 8.0+)
- **PHP** ≥ 8.0 (ext: `pdo_mysql`, `fileinfo`, `mbstring`)
- **MySQL** ≥ 5.7 atau **MariaDB** ≥ 10.3
- Browser modern: Chrome/Edge/Firefox/Safari terbaru
- Internet (untuk CDN Bootstrap 5 & Bootstrap Icons)

---

## 📦 Instalasi

### 1. Siapkan XAMPP
- Install XAMPP
- Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel

### 2. Copy project
- Salin folder `pintu/` ke: