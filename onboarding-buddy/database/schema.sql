-- ============================================================
-- Onboarding Buddy System - Database Schema
-- PT X | MySQL
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Tabel: divisi
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `divisi` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `deskripsi` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('karyawan_baru','buddy','hr','manajer','admin') NOT NULL DEFAULT 'karyawan_baru',
  `divisi_id` INT UNSIGNED,
  `tanggal_bergabung` DATE,
  `foto` VARCHAR(255),
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `reset_token` VARCHAR(100),
  `reset_token_exp` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`divisi_id`) REFERENCES `divisi`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: buddy_assignments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `buddy_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `karyawan_baru_id` INT UNSIGNED NOT NULL,
  `buddy_id` INT UNSIGNED NOT NULL,
  `hr_id` INT UNSIGNED NOT NULL,
  `tanggal_mulai` DATE NOT NULL,
  `tanggal_selesai` DATE,
  `status` ENUM('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif',
  `catatan` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`karyawan_baru_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`buddy_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`hr_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: checklist_templates (master checklist per divisi)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `checklist_templates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `divisi_id` INT UNSIGNED,
  `judul` VARCHAR(200) NOT NULL,
  `deskripsi` TEXT,
  `minggu_ke` TINYINT UNSIGNED DEFAULT 1 COMMENT '1=minggu pertama, 2=minggu kedua, dst',
  `urutan` SMALLINT UNSIGNED DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`divisi_id`) REFERENCES `divisi`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: checklist_items (progres checklist per karyawan baru)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `checklist_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `template_id` INT UNSIGNED NOT NULL,
  `status` ENUM('belum','sedang','selesai') NOT NULL DEFAULT 'belum',
  `catatan` TEXT,
  `selesai_pada` DATETIME,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `buddy_assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `checklist_templates`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_assignment_template` (`assignment_id`, `template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: check_ins (jadwal & catatan check-in)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `check_ins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `jadwal` DATETIME NOT NULL,
  `status` ENUM('terjadwal','selesai','dilewati') NOT NULL DEFAULT 'terjadwal',
  `catatan_buddy` TEXT,
  `catatan_karyawan` TEXT,
  `selesai_pada` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `buddy_assignments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: progress_notes (catatan progres adaptasi)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `progress_notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `ditulis_oleh` INT UNSIGNED NOT NULL,
  `isi` TEXT NOT NULL,
  `tingkat_adaptasi` TINYINT UNSIGNED COMMENT '1-5 skala adaptasi',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `buddy_assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ditulis_oleh`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: feedback (survei kepuasan onboarding)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `dari_user_id` INT UNSIGNED NOT NULL,
  `tipe` ENUM('karyawan_ke_buddy','buddy_ke_karyawan','karyawan_ke_program') NOT NULL,
  `skor_keseluruhan` TINYINT UNSIGNED COMMENT '1-5',
  `skor_komunikasi` TINYINT UNSIGNED COMMENT '1-5',
  `skor_keterlibatan` TINYINT UNSIGNED COMMENT '1-5',
  `komentar` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `buddy_assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`dari_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: notifications (in-app)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `judul` VARCHAR(200) NOT NULL,
  `pesan` TEXT NOT NULL,
  `tipe` ENUM('reminder','info','tugas','feedback') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `link` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: reminder_settings (konfigurasi reminder)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reminder_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `frekuensi` ENUM('harian','mingguan','2_mingguan') NOT NULL DEFAULT 'mingguan',
  `hari_check_in` TINYINT UNSIGNED COMMENT '0=Minggu, 1=Senin, ..., 6=Sabtu',
  `jam_check_in` TIME DEFAULT '09:00:00',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `buddy_assignments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Seed: Divisi
-- ------------------------------------------------------------
INSERT INTO `divisi` (`nama`, `deskripsi`) VALUES
('Human Resources', 'Divisi HR dan rekrutmen'),
('Teknologi Informasi', 'Divisi IT dan pengembangan sistem'),
('Keuangan', 'Divisi keuangan dan akuntansi'),
('Operasional', 'Divisi operasional dan logistik'),
('Pemasaran', 'Divisi marketing dan komunikasi');

-- ------------------------------------------------------------
-- Seed: Akun default (password: password)
-- ------------------------------------------------------------
INSERT INTO `users` (`nama`, `email`, `password`, `role`, `divisi_id`) VALUES
('Administrator', 'admin@ptx.co.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
('HR Manager', 'hr@ptx.co.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr', 1);

-- ------------------------------------------------------------
-- Seed: Checklist templates umum
-- ------------------------------------------------------------
INSERT INTO `checklist_templates` (`divisi_id`, `judul`, `deskripsi`, `minggu_ke`, `urutan`) VALUES
(NULL, 'Orientasi perusahaan & budaya kerja', 'Pengenalan visi, misi, dan nilai perusahaan', 1, 1),
(NULL, 'Perkenalan dengan tim & atasan', 'Sesi perkenalan formal dengan anggota tim', 1, 2),
(NULL, 'Setup akun & akses sistem', 'Email, sistem internal, dan alat kerja', 1, 3),
(NULL, 'Pembacaan SOP & kebijakan HR', 'Baca dan pahami SOP yang relevan', 1, 4),
(NULL, 'Sesi check-in pertama dengan buddy', 'Pertemuan awal untuk memetakan kebutuhan', 1, 5),
(NULL, 'Review target minggu pertama', 'Evaluasi pencapaian awal bersama buddy', 2, 1),
(NULL, 'Pemahaman alur kerja divisi', 'Pelajari proses dan prosedur divisi', 2, 2),
(NULL, 'Partisipasi meeting tim', 'Ikut serta dalam rapat mingguan tim', 2, 3),
(NULL, 'Check-in progress adaptasi', 'Diskusi perkembangan adaptasi minggu ini', 3, 1),
(NULL, 'Evaluasi 30 hari onboarding', 'Penilaian dan umpan balik setelah 30 hari', 4, 1);

SET FOREIGN_KEY_CHECKS = 1;
