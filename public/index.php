<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/includes/functions.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirectByRole(getUserRole());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AntiAbuse - Platform Perlindungan Anti Kekerasan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(180deg, #ffffff 0%, #ffe0e6 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: #2b2b2b;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Header */
        .landing-header {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 1400px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px 40px;
            box-shadow: 0 8px 32px rgba(253, 121, 121, 0.15), 0 4px 16px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .landing-header:hover {
            box-shadow: 0 12px 40px rgba(253, 121, 121, 0.2), 0 6px 20px rgba(0, 0, 0, 0.12);
            transform: translateX(-50%) translateY(-2px);
        }

        .landing-header .logo {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .landing-nav {
            display: flex;
            gap: 40px;
            align-items: center;
        }

        .landing-nav a {
            color: #2b2b2b;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .landing-nav a:hover {
            color: #D34E4E;
        }

        .landing-nav a.active {
            color: #D34E4E;
        }

        .landing-nav a.active::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #D34E4E;
            border-radius: 50%;
        }

        /* Main Content */
        .landing-main {
            margin-top: 140px;
            padding: 100px 40px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            z-index: 1;
        }

        /* Hero Section */
        .hero-section {
            text-align: center;
            margin-bottom: 120px;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-headline {
            font-size: 72px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 32px;
            color: #2b2b2b;
            letter-spacing: -1px;
        }

        .hero-headline .highlight {
            display: inline-block;
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            color: white;
            padding: 12px 28px;
            border-radius: 30px;
            margin-left: 12px;
            font-size: 64px;
            box-shadow: 0 6px 24px rgba(211, 78, 78, 0.35);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 6px 24px rgba(211, 78, 78, 0.35);
            }
            50% {
                box-shadow: 0 8px 32px rgba(211, 78, 78, 0.45);
            }
        }

        .hero-description {
            font-size: 22px;
            color: #666;
            max-width: 700px;
            margin: 0 auto 60px;
            line-height: 1.8;
            font-weight: 400;
        }

        /* Single CTA Button */
        .hero-cta {
            display: flex;
            justify-content: center;
            margin-top: 50px;
        }

        .btn-mulai {
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            color: white;
            padding: 20px 56px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 8px 24px rgba(211, 78, 78, 0.4);
            border: none;
            cursor: pointer;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-mulai::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-mulai:hover::before {
            left: 100%;
        }

        .btn-mulai:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(211, 78, 78, 0.5);
        }

        .btn-mulai:active {
            transform: translateY(-2px);
        }

        /* Features Section */
        .features-section {
            margin-top: 140px;
            animation: fadeInUp 1s ease 0.2s both;
        }

        .section-title {
            text-align: center;
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 80px;
            color: #2b2b2b;
            letter-spacing: -0.5px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
            margin-bottom: 80px;
        }

        .feature-card {
            background: linear-gradient(135deg, #ffffff 0%, rgba(249, 223, 223, 0.3) 100%);
            border-radius: 24px;
            padding: 48px 36px;
            border: 1px solid rgba(253, 121, 121, 0.2);
            box-shadow: 0 4px 20px rgba(253, 121, 121, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #D34E4E 0%, #FD7979 100%);
        }

        .feature-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 12px 40px rgba(253, 121, 121, 0.25);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #D34E4E 0%, #FD7979 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            box-shadow: 0 6px 20px rgba(211, 78, 78, 0.3);
            transition: transform 0.3s;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-icon svg {
            width: 40px;
            height: 40px;
            stroke: white;
            fill: white;
        }

        .feature-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #2b2b2b;
            letter-spacing: -0.3px;
        }

        .feature-description {
            font-size: 16px;
            color: #666;
            line-height: 1.8;
        }

        /* Footer */
        .landing-footer {
            text-align: center;
            padding: 80px 40px;
            color: #666;
            font-size: 14px;
            margin-top: 120px;
            border-top: 1px solid rgba(253, 121, 121, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .landing-header {
                width: 95%;
                top: 10px;
                padding: 16px 20px;
                flex-wrap: wrap;
            }

            .landing-nav {
                gap: 20px;
                font-size: 14px;
            }

            .landing-main {
                padding: 60px 20px;
                margin-top: 100px;
            }

            .hero-headline {
                font-size: 42px;
            }

            .hero-headline .highlight {
                font-size: 36px;
                padding: 10px 20px;
                display: block;
                margin: 20px 0 0 0;
            }

            .hero-description {
                font-size: 18px;
            }

            .btn-mulai {
                padding: 18px 48px;
                font-size: 18px;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .section-title {
                font-size: 36px;
                margin-bottom: 50px;
            }

            .feature-card {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="landing-header">
        <div class="logo">AntiAbuse</div>
        <nav class="landing-nav">
            <a href="#features" class="active">Fitur</a>
            <a href="#about">Tentang</a>
            <a href="#contact">Kontak</a>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="landing-main">
        <!-- Hero Section -->
        <section class="hero-section">
            <h1 class="hero-headline">
                Platform perlindungan yang bekerja seperti
                <span class="highlight">Sistem Terpadu</span>
            </h1>
            <p class="hero-description">
                Platform komprehensif untuk melaporkan, mencegah, dan menangani kasus kekerasan. 
                Dari pelaporan hingga edukasi, semua dalam satu sistem yang mudah digunakan.
            </p>
            <div class="hero-cta">
                <a href="<?= BASE_URL ?>/login.php" class="btn-mulai">
                    MULAI
                </a>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section" id="features">
            <h2 class="section-title">Fitur Utama</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <?= icon('create-report', '', 40, 40) ?>
                    </div>
                    <h3 class="feature-title">Laporan Beridentitas/Anonim</h3>
                    <p class="feature-description">
                        Laporkan kasus kekerasan dengan identitas lengkap atau secara anonim. 
                        Sistem akan memproses laporan Anda dengan cepat dan aman.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <?php echo icon('panic', '', 40, 40); ?>
                    </div>
                    <h3 class="feature-title">Panic Button</h3>
                    <p class="feature-description">
                        Tombol darurat yang dapat mengirimkan notifikasi langsung ke pihak berwenang 
                        dengan lokasi GPS Anda untuk bantuan cepat.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <?php echo icon('forum', '', 40, 40); ?>
                    </div>
                    <h3 class="feature-title">Forum Diskusi</h3>
                    <p class="feature-description">
                        Komunitas aman untuk berbagi pengalaman, mendapatkan dukungan, 
                        dan saling menguatkan dalam menghadapi kekerasan.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <?php echo icon('education', '', 40, 40); ?>
                    </div>
                    <h3 class="feature-title">Konten Edukasi</h3>
                    <p class="feature-description">
                        Akses konten edukatif dari psikolog terpercaya tentang pencegahan 
                        dan penanganan kekerasan dalam berbagai format.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="landing-footer">
        <p>&copy; 2024 AntiAbuse. Semua hak dilindungi.</p>
    </footer>
</body>
</html>
