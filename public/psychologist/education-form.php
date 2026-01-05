<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

requireLogin();
if (getUserRole() != 'psychologist') {
    redirectTo(BASE_URL . '/login.php', 'Akses ditolak', 'error');
    exit();
}

$contentId = (int)($_GET['id'] ?? 0);
$isEdit = $contentId > 0;
$content = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $category = $_POST['category'] ?? '';
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        
        // Validation
        if (empty($title) || empty($video_url) || empty($category)) {
            $error = 'Judul, Link Video, dan Kategori harus diisi!';
        } else {
            if ($isEdit) {
                // Update existing content
                $stmt = $conn->prepare("
                    UPDATE education_content 
                    SET title = ?, description = ?, video_url = ?, category = ?, thumbnail = ?, updated_at = NOW()
                    WHERE id = ? AND author_id = ? AND author_role = 'psychologist'
                ");
                $stmt->execute([$title, $description, $video_url, $category, $thumbnail, $contentId, $_SESSION['user_id']]);
                
                if ($stmt->rowCount() > 0) {
                    header('Location: ' . BASE_URL . '/psychologist/education.php?success=updated');
                    exit();
                } else {
                    $error = 'Konten tidak ditemukan atau Anda tidak memiliki akses';
                }
            } else {
                // Create new content
                $stmt = $conn->prepare("
                    INSERT INTO education_content (title, description, video_url, category, thumbnail, author_id, author_role, is_published, published_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'psychologist', TRUE, NOW())
                ");
                $stmt->execute([$title, $description, $video_url, $category, $thumbnail, $_SESSION['user_id']]);
                
                header('Location: ' . BASE_URL . '/psychologist/education.php?success=created');
                exit();
            }
        }
    } catch(PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Load content for editing
if ($isEdit) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT * FROM education_content
            WHERE id = ? AND author_id = ? AND author_role = 'psychologist'
        ");
        $stmt->execute([$contentId, $_SESSION['user_id']]);
        $content = $stmt->fetch();
        
        if (!$content) {
            header('Location: ' . BASE_URL . '/psychologist/education.php?error=not_found');
            exit();
        }
    } catch(PDOException $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$categoryLabels = [
    'bullying' => 'Perundungan',
    'violence' => 'Kekerasan',
    'harassment' => 'Pelecehan',
    'abuse' => 'Abuse',
    'prevention' => 'Pencegahan',
    'support' => 'Dukungan',
    'legal' => 'Hukum',
    'mental_health' => 'Kesehatan Mental'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Buat'; ?> Konten Edukasi - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--glass-border);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--color-text);
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(211, 78, 78, 0.3);
            border-radius: 8px;
            background: #ffffff;
            color: var(--color-text);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #D34E4E;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(211, 78, 78, 0.15);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn-submit {
            padding: 12px 24px;
            background: #D34E4E !important;
            color: white !important;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3);
        }
        
        .btn-submit:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-cancel {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.6);
            color: var(--color-text);
            border: 1px solid rgba(253, 121, 121, 0.2);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.8);
        }
        
        .help-text {
            font-size: 12px;
            color: var(--color-text-light);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="form-container">
                <div style="margin-bottom: 20px;">
                    <a href="education.php" style="color: var(--color-primary); text-decoration: none; font-size: 14px;">
                        ← Kembali ke Konten Saya
                    </a>
                </div>
                
                <div class="form-card">
                    <h2 style="margin-top: 0; color: var(--color-text);"><?php echo $isEdit ? 'Edit' : 'Buat'; ?> Konten Edukasi</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error" style="margin-bottom: 20px; padding: 15px; background: rgba(239, 83, 80, 0.1); border: 1px solid rgba(239, 83, 80, 0.3); border-radius: 10px; color: #EF5350;">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="title">Judul Video *</label>
                            <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($content['title'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Deskripsi (Opsional)</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($content['description'] ?? ''); ?></textarea>
                            <div class="help-text">Tambahkan deskripsi singkat tentang konten video ini.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="video_url">Link Video *</label>
                            <input type="url" id="video_url" name="video_url" required value="<?php echo htmlspecialchars($content['video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=... atau https://vimeo.com/...">
                            <div class="help-text">Masukkan link video dari YouTube atau Vimeo.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Kategori *</label>
                            <select id="category" name="category" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categoryLabels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($content['category']) && $content['category'] == $key) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="thumbnail">Thumbnail URL (Opsional)</label>
                            <input type="url" id="thumbnail" name="thumbnail" value="<?php echo htmlspecialchars($content['thumbnail'] ?? ''); ?>" placeholder="https://...">
                            <div class="help-text">URL gambar thumbnail. Jika kosong, akan menggunakan thumbnail default dari video.</div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-submit"><?php echo $isEdit ? 'Update' : 'Publish'; ?> Konten</button>
                            <a href="education.php" class="btn-cancel">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

