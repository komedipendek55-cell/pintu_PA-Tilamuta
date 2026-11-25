-- ============================================================
-- PINTU - Portal Informasi Perkara Terpadu
-- Pengadilan Agama Tilamuta
-- Database schema v1.0
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `pintu`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pintu`;

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('ADMIN','PANITERA_GUGATAN','PANITERA_PERMOHONAN','PANITERA_HUKUM','KASIR') NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  KEY `idx_users_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PERKARA (Gugatan, Permohonan, Hukum)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `perkara`;
CREATE TABLE `perkara` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` ENUM('GUGATAN','PERMOHONAN','HUKUM') NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `icon_path` VARCHAR(255) NULL,
  `short_description` TEXT NULL,
  `description_html` LONGTEXT NULL,
  `additional_notes` TEXT NULL,
  `cost_type` ENUM('GUGATAN','PERMOHONAN','PNBP') NULL,
  `process_flow` TEXT NULL,
  `process_flow_url` VARCHAR(500) NULL,
  `final_product` TEXT NULL,
  `status` ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'PUBLISHED',
  `created_by` INT UNSIGNED NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perkara_cat_name` (`category`, `name`),
  KEY `idx_perkara_cat_status` (`category`, `status`),
  KEY `idx_perkara_name` (`name`),
  CONSTRAINT `fk_perkara_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_perkara_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PERSYARATAN
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `persyaratan`;
CREATE TABLE `persyaratan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `perkara_id` INT UNSIGNED NOT NULL,
  `requirement_text` VARCHAR(500) NOT NULL,
  `sequence_no` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_persyaratan_perkara_seq` (`perkara_id`, `sequence_no`),
  CONSTRAINT `fk_persyaratan_perkara` FOREIGN KEY (`perkara_id`) REFERENCES `perkara`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- BIAYA PERKARA
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `biaya_perkara`;
CREATE TABLE `biaya_perkara` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cost_type` ENUM('GUGATAN','PERMOHONAN','PNBP') NOT NULL,
  `description` VARCHAR(200) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `calculation_type` ENUM('FIXED','PER_PENGGUGAT','PER_TERGUGAT','PER_PEMOHON') NOT NULL DEFAULT 'FIXED',
  `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_biaya_type_status` (`cost_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PERKARA x BIAYA (opsional mapping khusus)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `perkara_biaya`;
CREATE TABLE `perkara_biaya` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `perkara_id` INT UNSIGNED NOT NULL,
  `biaya_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perkara_biaya` (`perkara_id`, `biaya_id`),
  CONSTRAINT `fk_pb_perkara` FOREIGN KEY (`perkara_id`) REFERENCES `perkara`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pb_biaya`  FOREIGN KEY (`biaya_id`)   REFERENCES `biaya_perkara`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SETTINGS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- VISITOR LOGS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `visitor_logs`;
CREATE TABLE `visitor_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `visit_date` DATE NOT NULL,
  `visitor_key` CHAR(64) NOT NULL,
  `path` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_date` (`visit_date`),
  KEY `idx_visitor_unique` (`visit_date`, `visitor_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FEEDBACK
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rating` TINYINT NOT NULL,
  `message` TEXT NULL,
  `name` VARCHAR(100) NULL,
  `email` VARCHAR(150) NULL,
  `status` ENUM('PENDING','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'PENDING',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_status` (`status`),
  KEY `idx_feedback_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- AUDIT LOGS
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,
  `table_name` VARCHAR(64) NOT NULL,
  `record_id` INT UNSIGNED NULL,
  `before_json` LONGTEXT NULL,
  `after_json` LONGTEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_table` (`table_name`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default users (password: Admin123! / Panitera123! / Kasir123!)
-- Hash di bawah dihasilkan menggunakan password_hash('...', PASSWORD_BCRYPT)
INSERT INTO `users` (`username`,`password_hash`,`full_name`,`role`,`is_active`,`must_change_password`) VALUES
('admin',      '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1H.9jhBJ6tXHZKnj8fG9z7zZ6vX.Km6', 'Administrator',       'ADMIN', 1, 0),
('pgugatan',   '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1H.9jhBJ6tXHZKnj8fG9z7zZ6vX.Km6', 'Panitera Gugatan',    'PANITERA_GUGATAN', 1, 0),
('ppermohonan','$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1H.9jhBJ6tXHZKnj8fG9z7zZ6vX.Km6', 'Panitera Permohonan', 'PANITERA_PERMOHONAN', 1, 0),
('phukum',     '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1H.9jhBJ6tXHZKnj8fG9z7zZ6vX.Km6', 'Panitera Hukum',      'PANITERA_HUKUM', 1, 0),
('kasir',      '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1H.9jhBJ6tXHZKnj8fG9z7zZ6vX.Km6', 'Kasir',               'KASIR', 1, 0);

-- PERINGATAN: Ganti hash di atas dengan hasil password_hash() yang baru.
-- Cara generate: php -r "echo password_hash('Admin123!', PASSWORD_DEFAULT);"
-- Kredensial default: admin/Admin123! , pgugatan/Panitera123! , dst.

-- Settings
INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
('site_name',        'PINTU - Portal Informasi Perkara Terpadu'),
('site_short_name',  'PINTU'),
('site_instansi',    'Pengadilan Agama Tilamuta'),
('manual_book_url',  '#'),
('facebook_url',     '#'),
('instagram_url',    '#'),
('youtube_url',      '#'),
('survey_url',       '#'),
('footer_note',      'Portal ini dikelola oleh Pengadilan Agama Tilamuta.');

-- Biaya Perkara contoh (dari PRD Lampiran B)
INSERT INTO `biaya_perkara` (`cost_type`,`description`,`amount`,`calculation_type`) VALUES
('GUGATAN',    'PNBP Biaya Pendaftaran',                    30000.00, 'FIXED'),
('GUGATAN',    'Biaya Proses dan ATK',                      150000.00,'FIXED'),
('GUGATAN',    'Panggilan Penggugat',                       0.00,     'PER_PENGGUGAT'),
('GUGATAN',    'Panggilan Tergugat',                        100000.00,'PER_TERGUGAT'),
('GUGATAN',    'PNBP Panggilan Pertama Penggugat',          10000.00, 'PER_PENGGUGAT'),
('GUGATAN',    'PNBP Panggilan Pertama Tergugat',           100000.00,'PER_TERGUGAT'),
('GUGATAN',    'Redaksi',                                   100000.00,'FIXED'),
('GUGATAN',    'Materai',                                   100000.00,'FIXED'),
('GUGATAN',    'PBT Penggugat',                             0.00,     'PER_PENGGUGAT'),
('GUGATAN',    'PBT Tergugat',                              100000.00,'PER_TERGUGAT'),
('GUGATAN',    'PNBP PBT Tergugat',                         10000.00, 'PER_TERGUGAT'),
('PERMOHONAN', 'PNBP Biaya Pendaftaran',                    30000.00, 'FIXED'),
('PERMOHONAN', 'Biaya Proses dan ATK',                      150000.00,'FIXED'),
('PERMOHONAN', 'Panggilan Pemohon',                         0.00,     'PER_PEMOHON'),
('PERMOHONAN', 'PNBP Panggilan Pertama Pemohon',            10000.00, 'PER_PEMOHON'),
('PERMOHONAN', 'Redaksi',                                   100000.00,'FIXED'),
('PERMOHONAN', 'Materai',                                   100000.00,'FIXED'),
('PNBP',       'PNBP Akta Cerai',                           10000.00, 'FIXED'),
('PNBP',       'PNBP Salinan Putusan',                      500.00,   'FIXED'),
('PNBP',       'PNBP Register Surat Kuasa',                 10000.00, 'FIXED');

-- Perkara contoh
INSERT INTO `perkara` (`category`,`name`,`short_description`,`description_html`,`cost_type`,`process_flow`,`final_product`,`status`) VALUES
('GUGATAN','Gugatan Perceraian','Proses gugatan perceraian bagi pasangan suami istri yang beragama Islam.','<p>Gugatan perceraian diajukan oleh salah satu pihak (Penggugat) terhadap pasangan (Tergugat) ke Pengadilan Agama Tilamuta.</p>','GUGATAN','1. Daftar gugatan\n2. Pembayaran biaya\n3. Pemanggilan pihak\n4. Sidang\n5. Putusan','Putusan Perceraian & Akta Cerai','PUBLISHED'),
('PERMOHONAN','Permohonan Dispensasi Kawin','Permohonan dispensasi kawin bagi calon pengantin di bawah umur.','<p>Permohonan dispensasi kawin diajukan oleh orang tua/wali.</p>','PERMOHONAN','1. Daftar permohonan\n2. Pembayaran\n3. Sidang\n4. Penetapan','Penetapan Dispensasi Kawin','PUBLISHED'),
('HUKUM','Legalisasi Akta Nikah','Layanan legalisasi buku nikah.','<p>Layanan legalisasi akta nikah bagi masyarakat.</p>',NULL,'1. Ajukan buku nikah\n2. Verifikasi\n3. Legalisasi','Buku Nikah Teralegalisi','PUBLISHED');

-- Persyaratan contoh
INSERT INTO `persyaratan` (`perkara_id`,`requirement_text`,`sequence_no`) VALUES
(1,'Surat gugatan (rangkap 5)',1),
(1,'Fotokopi KTP Penggugat',2),
(1,'Fotokopi KK',3),
(1,'Fotokopi Akta Nikah',4),
(1,'Fotokopi KTP Tergugat',5),
(2,'Surat permohonan',1),
(2,'Fotokopi KTP orang tua',2),
(2,'Fotokopi KK',3),
(2,'Fotokopi Akta Kelahiran anak',4),
(3,'Buku Nikah asli',1),
(3,'Fotokopi KTP',2);