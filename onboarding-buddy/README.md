# Onboarding Buddy System

Aplikasi web manajemen program Onboarding Buddy untuk PT X.

## Tech Stack
- PHP 7.4+ / 8.x
- MySQL 5.7+
- Bootstrap 5.3
- Apache/Nginx

## Instalasi

### 1. Setup Database
```bash
mysql -u root -p < database/schema.sql
```

### 2. Konfigurasi
Edit `config/config.php`:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_URL` — sesuaikan dengan URL server Anda
- `MAIL_*` — konfigurasi email (PHPMailer)

### 3. Deploy
Upload ke direktori `public_html/onboarding-buddy/` pada shared hosting,
atau ke `/var/www/html/onboarding-buddy/` pada VPS.

### 4. Akun Default
| Email               | Password   | Role  |
|---------------------|------------|-------|
| admin@ptx.co.id     | password   | Admin |
| hr@ptx.co.id        | password   | HR    |

> **Ganti password default segera setelah login pertama!**

## Struktur Direktori

```
onboarding-buddy/
├── admin/
│   ├── users.php       # Kelola user
│   ├── divisi.php      # Kelola divisi
│   └── checklist.php   # Redirect ke checklist.php
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── uploads/        # (buat folder ini, chmod 755)
├── auth/
│   ├── login_process.php
│   ├── logout.php
│   └── ganti_password.php
├── config/
│   ├── config.php      # Konfigurasi utama
│   ├── database.php    # Koneksi PDO
│   ├── session.php     # Manajemen sesi
│   └── helpers.php     # Helper functions
├── database/
│   └── schema.sql      # DDL + seed data
├── includes/
│   ├── header.php
│   └── footer.php
├── .htaccess
├── index.php
├── login.php
├── dashboard.php       # Dashboard role-based
├── checklist.php       # Checklist onboarding
├── buddy.php           # Buddy assignment
├── checkin.php         # Check-in jadwal
├── progress.php        # Progress tracking
├── feedback.php        # Feedback & survei
├── notifikasi.php      # Notifikasi in-app
├── laporan.php         # Laporan & ekspor CSV
└── profil.php          # Profil pengguna
```

## Fitur per Role

| Fitur                  | Karyawan Baru | Buddy | HR | Manajer | Admin |
|------------------------|:---:|:---:|:---:|:---:|:---:|
| Dashboard personalisasi| ✅ | ✅ | ✅ | ✅ | ✅ |
| Checklist onboarding   | ✅ | 👁 | ✅ | - | ✅ |
| Buddy assignment       | 👁 | 👁 | ✅ | - | ✅ |
| Check-in scheduling    | 👁 | ✅ | ✅ | - | - |
| Progress notes         | 👁 | ✅ | ✅ | 👁 | - |
| Feedback               | ✅ | ✅ | 👁 | 👁 | - |
| Laporan & ekspor CSV   | - | - | ✅ | ✅ | - |
| Kelola user            | - | - | ✅ | - | ✅ |
| Kelola divisi          | - | - | ✅ | - | ✅ |
| Notifikasi in-app      | ✅ | ✅ | ✅ | ✅ | ✅ |

## Keamanan
- Password di-hash dengan `bcrypt` (PASSWORD_BCRYPT)
- CSRF token pada setiap form POST
- Role-based access control
- Input sanitization dengan `htmlspecialchars`
- PDO prepared statements (SQL injection protection)
- Session httponly cookies
- `.htaccess` blokir akses langsung ke `config/`
