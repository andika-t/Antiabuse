# AntiAbuse Platform

Platform berbasis web untuk melaporkan dan menangani kasus perundungan, kekerasan, pelecehan, dan bullying.

## Teknologi
- PHP 7.4+
- MySQL/MariaDB
- HTML5, CSS3, JavaScript

## Fitur
- Login & Registrasi
- Google OAuth Integration
- Role-based Access Control (Admin, Polisi, Psikolog, Pengguna Umum)
- Dashboard berbeda untuk setiap role

## Instalasi

### 1. Database Setup
```bash
# Import database schema
mysql -u root -p < database/schema.sql
```

### 2. Konfigurasi Database
Edit file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'antiabuse_db');
```

### 3. Konfigurasi Google OAuth
1. Buat project di [Google Cloud Console](https://console.cloud.google.com/)
2. Enable Google+ API
3. Buat OAuth 2.0 credentials
4. Edit file `config/config.php`:
```php
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
```

### 4. Setup Web Server
- Pastikan web server (Apache/Nginx) sudah terinstall
- Point document root ke folder **public/** (atau root folder jika menggunakan .htaccess)
- Enable mod_rewrite (untuk Apache)
- File `.htaccess` di root akan otomatis redirect ke folder `public/`

### 5. Default Login
- Username: `admin`
- Password: `admin123`
- **PENTING**: Ganti password setelah login pertama!

## Struktur Folder
```
/
├── public/                 # Frontend - File yang diakses browser
│   ├── index.php          # Redirect otomatis
│   ├── login.php          # Halaman login (inline processing)
│   ├── register.php       # Halaman registrasi (inline processing)
│   ├── auth/
│   │   ├── google_callback.php
│   │   └── logout.php
│   └── .htaccess
├── assets/                 # CSS, JS, Images
│   └── css/
│       └── style.css
├── config/                 # Konfigurasi aplikasi
│   ├── config.php
│   └── database.php
├── database/               # SQL files
│   └── schema.sql
├── dashboard/              # Dashboard per role (akan dibuat)
│   ├── admin/
│   ├── police/
│   ├── psychologist/
│   └── user/
├── .htaccess              # Redirect root ke public/
└── README.md
```

**Konsep Inline Processing:**
- Semua file di `public/` menggunakan inline processing
- Backend processing (PHP) di bagian atas file
- Frontend display (HTML) di bagian bawah file
- Sederhana dan mudah dipahami untuk pemula

## Catatan Keamanan
- Selalu gunakan HTTPS di production
- Ganti default admin password
- Update Google OAuth credentials
- Backup database secara berkala

