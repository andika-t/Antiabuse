<?php
// ========== BACKEND PROCESSING ==========
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/config/database.php';

// Check if user is logged in and is general user
requireLogin();
if (!canCreateReport()) {
    redirectTo(BASE_URL . '/user/index.php', 'Anda tidak memiliki izin untuk membuat laporan', 'error');
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$maxStep = 5;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_report'])) {
    require_once __DIR__ . '/../../app/classes/Report.php';
    
    $report = new Report();
    $result = $report->create();
    
    if (isset($result['error'])) {
        $error = $result['error'];
    }
    // If success, redirect is handled in Report::create()
}

// Get draft data from session if exists
$draft = $_SESSION['report_draft'] ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Laporan - AntiAbuse</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-dashboard.css'); ?>">
    <style>
        .wizard-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .wizard-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .wizard-header h1 {
            font-size: 32px;
            color: var(--color-text);
            margin-bottom: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--gradient-soft);
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        .step-indicators {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .step-indicator {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-indicator::before {
            content: '';
            display: block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            border: 2px solid rgba(253, 121, 121, 0.3);
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--color-text);
            transition: all 0.3s ease;
        }
        
        .step-indicator.active::before {
            background: var(--gradient-soft);
            border-color: var(--color-primary);
            color: white;
        }
        
        .step-indicator.completed::before {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
            content: '✓';
        }
        
        .step-indicator span {
            display: block;
            font-size: 12px;
            color: var(--color-text);
            margin-top: 5px;
        }
        
        .wizard-form {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 40px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--glass-border);
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .identity-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .identity-card {
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 20px;
            padding: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .identity-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .identity-card.selected {
            border-color: var(--color-primary);
            background: rgba(253, 121, 121, 0.1);
            box-shadow: var(--shadow-medium);
        }
        
        .identity-card input[type="radio"] {
            display: none;
        }
        
        .identity-card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .identity-card h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--color-text);
        }
        
        .identity-card p {
            font-size: 14px;
            color: var(--color-text-light);
            line-height: 1.6;
        }
        
        .report-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .report-type-card {
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(253, 121, 121, 0.2);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .report-type-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-3px);
        }
        
        .report-type-card.selected {
            border-color: var(--color-primary);
            background: rgba(253, 121, 121, 0.1);
        }
        
        .report-type-card input[type="radio"] {
            display: none;
        }
        
        .report-type-card-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text);
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(211, 78, 78, 0.3);
            border-radius: 12px;
            background: #ffffff;
            backdrop-filter: blur(10px);
            font-size: 14px;
            color: var(--color-text);
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #D34E4E;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(211, 78, 78, 0.15);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .char-counter {
            font-size: 12px;
            color: var(--color-text-light);
            text-align: right;
            margin-top: 5px;
        }
        
        .error-message {
            color: #d32f2f;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .form-group input.error,
        .form-group textarea.error {
            border-color: #d32f2f !important;
            background: rgba(211, 47, 47, 0.05);
        }
        
        .file-upload-area {
            border: 2px dashed rgba(253, 121, 121, 0.3);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover {
            border-color: var(--color-primary);
            background: rgba(255, 255, 255, 0.6);
        }
        
        .file-upload-area.dragover {
            border-color: var(--color-primary);
            background: rgba(253, 121, 121, 0.1);
        }
        
        .file-list {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.6);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .file-item-name {
            flex: 1;
            font-size: 14px;
            color: var(--color-text);
        }
        
        .file-item-remove {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .review-section {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .review-section h4 {
            margin-bottom: 15px;
            color: var(--color-text);
        }
        
        .review-section p {
            color: var(--color-text-light);
            line-height: 1.6;
        }
        
        .wizard-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            gap: 15px;
        }
        
        .wizard-actions-left {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .wizard-actions-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #D34E4E !important;
            color: white !important;
            box-shadow: 0 4px 20px rgba(211, 78, 78, 0.3) !important;
        }
        
        .btn-primary:hover {
            background: #c04545 !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(211, 78, 78, 0.4) !important;
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.6);
            color: var(--color-text);
            border: 2px solid rgba(253, 121, 121, 0.2);
        }
        
        .btn-secondary:hover {
            border-color: var(--color-primary);
            background: rgba(255, 255, 255, 0.8);
        }
        
        .btn-cancel {
            background: rgba(239, 83, 80, 0.1);
            color: #EF5350;
            border: 2px solid rgba(239, 83, 80, 0.3);
        }
        
        .btn-cancel:hover {
            background: rgba(239, 83, 80, 0.2);
            border-color: #EF5350;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #d32f2f;
        }
        
        .alert-success {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid rgba(0, 255, 0, 0.3);
            color: #2e7d32;
        }
        
        @media (max-width: 768px) {
            .identity-cards {
                grid-template-columns: 1fr;
            }
            
            .report-type-grid {
                grid-template-columns: 1fr;
            }
            
            .wizard-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1>Buat Laporan</h1>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($step / $maxStep) * 100; ?>%"></div>
            </div>
            <div class="step-indicators">
                <?php for ($i = 1; $i <= $maxStep; $i++): ?>
                    <div class="step-indicator <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>">
                        <span>Step <?php echo $i; ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="wizard-form">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form id="reportForm" method="POST" enctype="multipart/form-data" action="">
                <!-- Step 1: Pilih Identitas -->
                <div class="step-content <?php echo $step == 1 ? 'active' : ''; ?>" data-step="1">
                    <h2 style="margin-bottom: 20px; color: var(--color-text);">Pilih Identitas</h2>
                    <div class="identity-cards">
                        <label class="identity-card <?php echo (isset($draft['is_anonymous']) && $draft['is_anonymous'] == '0') ? 'selected' : ''; ?>">
                            <input type="radio" name="is_anonymous" value="0" <?php echo (isset($draft['is_anonymous']) && $draft['is_anonymous'] == '0') ? 'checked' : ''; ?>>
                            <div class="identity-card-icon"><?php echo icon('users', '', 48, 48); ?></div>
                            <h3>Laporan Beridentitas</h3>
                            <p>Laporan Anda akan terhubung dengan akun Anda. Admin dapat menghubungi Anda untuk follow-up.</p>
                        </label>
                        <label class="identity-card <?php echo (isset($draft['is_anonymous']) && $draft['is_anonymous'] == '1') ? 'selected' : ''; ?>">
                            <input type="radio" name="is_anonymous" value="1" <?php echo (isset($draft['is_anonymous']) && $draft['is_anonymous'] == '1') ? 'checked' : 'checked'; ?>>
                            <div class="identity-card-icon"><?php echo icon('users', '', 48, 48); ?></div>
                            <h3>Laporan Anonim</h3>
                            <p>Laporan Anda akan tetap rahasia. Identitas Anda tidak akan terlihat oleh siapapun.</p>
                        </label>
                    </div>
                </div>
                
                <!-- Step 2: Tipe Laporan -->
                <div class="step-content <?php echo $step == 2 ? 'active' : ''; ?>" data-step="2">
                    <h2 style="margin-bottom: 20px; color: var(--color-text);">Tipe & Kategori Laporan</h2>
                    <div class="report-type-grid">
                        <label class="report-type-card <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'bullying') ? 'selected' : ''; ?>">
                            <input type="radio" name="report_type" value="bullying" <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'bullying') ? 'checked' : ''; ?>>
                            <div class="report-type-card-icon"><?php echo icon('users', '', 32, 32); ?></div>
                            <strong>Perundungan</strong>
                        </label>
                        <label class="report-type-card <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'violence') ? 'selected' : ''; ?>">
                            <input type="radio" name="report_type" value="violence" <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'violence') ? 'checked' : ''; ?>>
                            <div class="report-type-card-icon"><?php echo icon('panic', '', 32, 32); ?></div>
                            <strong>Kekerasan</strong>
                        </label>
                        <label class="report-type-card <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'harassment') ? 'selected' : ''; ?>">
                            <input type="radio" name="report_type" value="harassment" <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'harassment') ? 'checked' : ''; ?>>
                            <div class="report-type-card-icon"><?php echo icon('reports', '', 32, 32); ?></div>
                            <strong>Pelecehan</strong>
                        </label>
                        <label class="report-type-card <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'abuse') ? 'selected' : ''; ?>">
                            <input type="radio" name="report_type" value="abuse" <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'abuse') ? 'checked' : ''; ?>>
                            <div class="report-type-card-icon"><?php echo icon('panic', '', 32, 32); ?></div>
                            <strong>Abuse</strong>
                        </label>
                        <label class="report-type-card <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'other') ? 'selected' : ''; ?>">
                            <input type="radio" name="report_type" value="other" <?php echo (isset($draft['report_type']) && $draft['report_type'] == 'other') ? 'checked' : ''; ?>>
                            <div class="report-type-card-icon"><?php echo icon('reports', '', 32, 32); ?></div>
                            <strong>Lainnya</strong>
                        </label>
                    </div>
                    <div id="otherTypeInput" class="form-group" style="display: none;">
                        <label>Spesifikasi Tipe Laporan</label>
                        <input type="text" name="report_type_other" placeholder="Jelaskan tipe laporan lainnya" value="<?php echo htmlspecialchars($draft['report_type_other'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- Step 3: Detail Kejadian -->
                <div class="step-content <?php echo $step == 3 ? 'active' : ''; ?>" data-step="3">
                    <h2 style="margin-bottom: 20px; color: var(--color-text);">Detail Kejadian</h2>
                    <div class="form-group">
                        <label>Judul Laporan *</label>
                        <input type="text" name="title" placeholder="Ringkasan singkat kejadian" maxlength="255" value="<?php echo htmlspecialchars($draft['title'] ?? ''); ?>">
                        <div class="char-counter"><span id="titleCounter">0</span>/255 karakter</div>
                        <div class="error-message" id="titleError" style="display: none; color: #d32f2f; font-size: 12px; margin-top: 5px;"></div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi Kejadian *</label>
                        <textarea name="description" placeholder="Ceritakan detail kejadian yang Anda alami..." maxlength="2000"><?php echo htmlspecialchars($draft['description'] ?? ''); ?></textarea>
                        <div class="char-counter"><span id="descCounter">0</span>/2000 karakter (min 50)</div>
                        <div class="error-message" id="descriptionError" style="display: none; color: #d32f2f; font-size: 12px; margin-top: 5px;"></div>
                    </div>
                    <div class="form-group">
                        <label>Lokasi Kejadian</label>
                        <input type="text" name="location" placeholder="Contoh: Sekolah ABC, Jalan XYZ" value="<?php echo htmlspecialchars($draft['location'] ?? ''); ?>">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Tanggal Kejadian</label>
                            <input type="date" name="incident_date" value="<?php echo htmlspecialchars($draft['incident_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Waktu Kejadian</label>
                            <input type="time" name="incident_time" value="<?php echo htmlspecialchars($draft['incident_time'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Step 4: Informasi Tambahan -->
                <div class="step-content <?php echo $step == 4 ? 'active' : ''; ?>" data-step="4">
                    <h2 style="margin-bottom: 20px; color: var(--color-text);">Informasi Tambahan (Opsional)</h2>
                    <div class="form-group">
                        <label>Upload Bukti (Maks 3 file, 5MB per file)</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <p>Klik atau drag & drop file di sini</p>
                            <p style="font-size: 12px; margin-top: 10px; color: var(--color-text-light);">
                                Format: JPG, PNG, PDF, DOC, DOCX
                            </p>
                        </div>
                        <input type="file" name="attachments[]" id="fileInput" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display: none;">
                        <div class="file-list" id="fileList"></div>
                    </div>
                    <div class="form-group">
                        <label>Prioritas</label>
                        <select name="priority">
                            <option value="low" <?php echo (isset($draft['priority']) && $draft['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo (isset($draft['priority']) && $draft['priority'] == 'medium' || !isset($draft['priority'])) ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo (isset($draft['priority']) && $draft['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo (isset($draft['priority']) && $draft['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Informasi Tambahan</label>
                        <textarea name="additional_info" placeholder="Informasi lain yang ingin ditambahkan..." maxlength="500"><?php echo htmlspecialchars($draft['additional_info'] ?? ''); ?></textarea>
                        <div class="char-counter"><span id="additionalCounter">0</span>/500 karakter</div>
                    </div>
                </div>
                
                <!-- Step 5: Review & Submit -->
                <div class="step-content <?php echo $step == 5 ? 'active' : ''; ?>" data-step="5">
                    <h2 style="margin-bottom: 20px; color: var(--color-text);">Review & Submit</h2>
                    
                    <!-- Hidden inputs to preserve form data -->
                    <input type="hidden" name="is_anonymous" id="hidden_is_anonymous">
                    <input type="hidden" name="report_type" id="hidden_report_type">
                    <input type="hidden" name="report_type_other" id="hidden_report_type_other">
                    <input type="hidden" name="title" id="hidden_title">
                    <input type="hidden" name="description" id="hidden_description">
                    <input type="hidden" name="location" id="hidden_location">
                    <input type="hidden" name="incident_date" id="hidden_incident_date">
                    <input type="hidden" name="incident_time" id="hidden_incident_time">
                    <input type="hidden" name="priority" id="hidden_priority">
                    <input type="hidden" name="additional_info" id="hidden_additional_info">
                    
                    <div id="reviewContent">
                        <!-- Review content will be populated by JavaScript -->
                    </div>
                    <div class="form-group" style="margin-top: 30px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" name="confirm" required style="width: auto; margin-right: 10px;">
                            <span>Saya telah memverifikasi informasi di atas</span>
                        </label>
                    </div>
                </div>
                
                <div class="wizard-actions">
                    <div class="wizard-actions-left">
                        <?php if ($step > 1): ?>
                            <button type="button" class="btn btn-secondary" onclick="goToStep(<?php echo $step - 1; ?>)">Kembali</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-cancel" onclick="cancelReport()">Batal</button>
                    </div>
                    <div class="wizard-actions-right">
                        <?php if ($step < $maxStep): ?>
                            <button type="button" class="btn btn-primary" onclick="goToStep(<?php echo $step + 1; ?>)">Selanjutnya</button>
                        <?php else: ?>
                            <button type="submit" name="submit_report" class="btn btn-primary" id="submitBtn" disabled>Kirim Laporan</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentStep = <?php echo $step; ?>;
        let selectedFiles = [];
        
        // Character counters
        document.querySelector('input[name="title"]')?.addEventListener('input', function() {
            document.getElementById('titleCounter').textContent = this.value.length;
            // Clear error when user types
            const titleError = document.getElementById('titleError');
            if (titleError && titleError.style.display === 'block') {
                titleError.style.display = 'none';
                this.style.borderColor = 'rgba(253, 121, 121, 0.2)';
                this.classList.remove('error');
            }
        });
        
        document.querySelector('textarea[name="description"]')?.addEventListener('input', function() {
            document.getElementById('descCounter').textContent = this.value.length;
            // Clear error when user types
            const descriptionError = document.getElementById('descriptionError');
            if (descriptionError && descriptionError.style.display === 'block') {
                descriptionError.style.display = 'none';
                this.style.borderColor = 'rgba(253, 121, 121, 0.2)';
                this.classList.remove('error');
            }
        });
        
        document.querySelector('textarea[name="additional_info"]')?.addEventListener('input', function() {
            document.getElementById('additionalCounter').textContent = this.value.length;
        });
        
        // Initialize counters
        if (document.querySelector('input[name="title"]')) {
            document.getElementById('titleCounter').textContent = document.querySelector('input[name="title"]').value.length;
        }
        if (document.querySelector('textarea[name="description"]')) {
            document.getElementById('descCounter').textContent = document.querySelector('textarea[name="description"]').value.length;
        }
        if (document.querySelector('textarea[name="additional_info"]')) {
            document.getElementById('additionalCounter').textContent = document.querySelector('textarea[name="additional_info"]').value.length;
        }
        
        // Identity card selection
        document.querySelectorAll('.identity-card input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.identity-card').forEach(card => {
                    card.classList.remove('selected');
                });
                this.closest('.identity-card').classList.add('selected');
            });
        });
        
        // Report type selection
        document.querySelectorAll('.report-type-card input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.report-type-card').forEach(card => {
                    card.classList.remove('selected');
                });
                this.closest('.report-type-card').classList.add('selected');
                
                // Simpan langsung ke draft saat dipilih
                saveDraft();
                
                // Show/hide other type input
                const otherInput = document.getElementById('otherTypeInput');
                if (this.value === 'other') {
                    otherInput.style.display = 'block';
                    otherInput.querySelector('input').required = true;
                } else {
                    otherInput.style.display = 'none';
                    otherInput.querySelector('input').required = false;
                }
            });
        });
        
        // File upload
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        
        if (fileUploadArea && fileInput) {
            fileUploadArea.addEventListener('click', () => fileInput.click());
            
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });
            
            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('dragover');
            });
            
            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });
            
            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });
        }
        
        function handleFiles(files) {
            if (selectedFiles.length + files.length > 3) {
                alert('Maksimal 3 file!');
                return;
            }
            
            Array.from(files).forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File ${file.name} terlalu besar! Maksimal 5MB.`);
                    return;
                }
                
                selectedFiles.push(file);
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <span class="file-item-name">${file.name} (${(file.size / 1024).toFixed(2)} KB)</span>
                    <button type="button" class="file-item-remove" onclick="removeFile('${file.name}')">Hapus</button>
                `;
                fileList.appendChild(fileItem);
            });
            
            updateFileInput();
        }
        
        function removeFile(fileName) {
            selectedFiles = selectedFiles.filter(f => f.name !== fileName);
            updateFileList();
            updateFileInput();
        }
        
        function updateFileList() {
            fileList.innerHTML = '';
            selectedFiles.forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <span class="file-item-name">${file.name} (${(file.size / 1024).toFixed(2)} KB)</span>
                    <button type="button" class="file-item-remove" onclick="removeFile('${file.name}')">Hapus</button>
                `;
                fileList.appendChild(fileItem);
            });
        }
        
        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }
        
        // Clear draft from sessionStorage
        function clearDraft() {
            sessionStorage.removeItem('report_draft');
            // Draft will be cleared from PHP session when user navigates away
        }
        
        // Cancel and go to dashboard
        function cancelReport() {
            if (confirm('Apakah Anda yakin ingin membatalkan pembuatan laporan? Semua data yang sudah diisi akan hilang.')) {
                clearDraft();
                window.location.href = '<?php echo BASE_URL; ?>/user/index.php';
            }
        }
        
        // Step navigation
        function goToStep(step) {
            // Only validate if going forward (to next step), not backward
            const isGoingForward = step > currentStep;
            
            if (isGoingForward) {
                // Validate current step before proceeding to next step
                if (!validateStep(currentStep)) {
                    return;
                }
            }
            
            // Save draft to session (always save, even when going back)
            saveDraft();
            
            // Navigate to step
            window.location.href = '?step=' + step;
        }
        
        function validateStep(step) {
            if (step === 1) {
                const selected = document.querySelector('input[name="is_anonymous"]:checked');
                if (!selected) {
                    alert('Pilih identitas laporan!');
                    return false;
                }
            } else if (step === 2) {
                const selected = document.querySelector('input[name="report_type"]:checked');
                if (!selected) {
                    alert('Pilih tipe laporan!');
                    return false;
                }
                if (selected.value === 'other') {
                    const otherInput = document.querySelector('input[name="report_type_other"]');
                    if (!otherInput.value.trim()) {
                        alert('Spesifikasi tipe laporan lainnya harus diisi!');
                        return false;
                    }
                }
            } else if (step === 3) {
                const title = document.querySelector('input[name="title"]');
                const description = document.querySelector('textarea[name="description"]');
                const titleError = document.getElementById('titleError');
                const descriptionError = document.getElementById('descriptionError');
                let hasError = false;
                
                // Clear previous errors
                if (titleError) {
                    titleError.style.display = 'none';
                    titleError.textContent = '';
                    title.style.borderColor = 'rgba(253, 121, 121, 0.2)';
                    title.classList.remove('error');
                }
                if (descriptionError) {
                    descriptionError.style.display = 'none';
                    descriptionError.textContent = '';
                    description.style.borderColor = 'rgba(253, 121, 121, 0.2)';
                    description.classList.remove('error');
                }
                
                // Validate title
                if (!title.value.trim()) {
                    if (titleError) {
                        titleError.textContent = 'Judul laporan harus diisi!';
                        titleError.style.display = 'block';
                        title.style.borderColor = '#d32f2f';
                        title.classList.add('error');
                    }
                    hasError = true;
                } else if (title.value.length < 10) {
                    if (titleError) {
                        titleError.textContent = 'Judul laporan minimal 10 karakter!';
                        titleError.style.display = 'block';
                        title.style.borderColor = '#d32f2f';
                        title.classList.add('error');
                    }
                    hasError = true;
                } else if (title.value.length > 255) {
                    if (titleError) {
                        titleError.textContent = 'Judul laporan maksimal 255 karakter!';
                        titleError.style.display = 'block';
                        title.style.borderColor = '#d32f2f';
                        title.classList.add('error');
                    }
                    hasError = true;
                } else {
                    // Remove error if valid
                    title.classList.remove('error');
                }
                
                // Validate description
                if (!description.value.trim()) {
                    if (descriptionError) {
                        descriptionError.textContent = 'Deskripsi kejadian harus diisi!';
                        descriptionError.style.display = 'block';
                        description.style.borderColor = '#d32f2f';
                        description.classList.add('error');
                    }
                    hasError = true;
                } else if (description.value.length < 50) {
                    if (descriptionError) {
                        descriptionError.textContent = 'Deskripsi kejadian minimal 50 karakter!';
                        descriptionError.style.display = 'block';
                        description.style.borderColor = '#d32f2f';
                        description.classList.add('error');
                    }
                    hasError = true;
                } else if (description.value.length > 2000) {
                    if (descriptionError) {
                        descriptionError.textContent = 'Deskripsi kejadian maksimal 2000 karakter!';
                        descriptionError.style.display = 'block';
                        description.style.borderColor = '#d32f2f';
                        description.classList.add('error');
                    }
                    hasError = true;
                } else {
                    // Remove error if valid
                    description.classList.remove('error');
                }
                
                if (hasError) {
                    // Scroll to first error
                    const firstError = titleError && titleError.style.display === 'block' ? title : description;
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                    return false;
                }
            }
            return true;
        }
        
        function saveDraft() {
            const form = document.getElementById('reportForm');
            
            // Load existing draft first to preserve values from previous steps
            const existingDraftStr = sessionStorage.getItem('report_draft');
            const draft = existingDraftStr ? JSON.parse(existingDraftStr) : {};
            
            // Get radio button values FIRST (before FormData processing)
            // This ensures we always get the correct values from radio buttons if they're available
            const isAnonymousRadio = form.querySelector('input[name="is_anonymous"]:checked');
            const reportTypeRadio = form.querySelector('input[name="report_type"]:checked');
            
            // Update draft with radio button values if found
            if (isAnonymousRadio) {
                draft.is_anonymous = isAnonymousRadio.value;
            }
            if (reportTypeRadio) {
                draft.report_type = reportTypeRadio.value;
            }
            
            // Preserve critical values from draft before processing FormData
            const preservedReportType = draft.report_type || '';
            const preservedIsAnonymous = draft.is_anonymous || '';
            const preservedReportTypeOther = draft.report_type_other || '';
            
            const formData = new FormData(form);
            
            // Get all form fields (including hidden ones)
            // But don't overwrite report_type and is_anonymous if they're empty in FormData
            for (let [key, value] of formData.entries()) {
                if (key !== 'attachments[]' && key !== 'submit_report' && key !== 'confirm') {
                    // Don't overwrite report_type and is_anonymous if FormData value is empty
                    if (key === 'report_type' && !value && preservedReportType) {
                        // Keep existing value
                        continue;
                    }
                    if (key === 'is_anonymous' && !value && preservedIsAnonymous) {
                        // Keep existing value
                        continue;
                    }
                    if (key === 'report_type_other' && !value && preservedReportTypeOther) {
                        // Keep existing value
                        continue;
                    }
                    draft[key] = value;
                }
            }
            
            // Also get values from visible form fields that might not be in FormData
            const titleInput = form.querySelector('input[name="title"]');
            const descriptionInput = form.querySelector('textarea[name="description"]');
            const locationInput = form.querySelector('input[name="location"]');
            const incidentDateInput = form.querySelector('input[name="incident_date"]');
            const incidentTimeInput = form.querySelector('input[name="incident_time"]');
            const prioritySelect = form.querySelector('select[name="priority"]');
            const additionalInfoInput = form.querySelector('textarea[name="additional_info"]');
            const reportTypeOtherInput = form.querySelector('input[name="report_type_other"]');
            
            if (titleInput && titleInput.value) draft.title = titleInput.value;
            if (descriptionInput && descriptionInput.value) draft.description = descriptionInput.value;
            if (locationInput && locationInput.value) draft.location = locationInput.value;
            if (incidentDateInput && incidentDateInput.value) draft.incident_date = incidentDateInput.value;
            if (incidentTimeInput && incidentTimeInput.value) draft.incident_time = incidentTimeInput.value;
            if (prioritySelect && prioritySelect.value) draft.priority = prioritySelect.value;
            if (additionalInfoInput && additionalInfoInput.value) draft.additional_info = additionalInfoInput.value;
            if (reportTypeOtherInput && reportTypeOtherInput.value) draft.report_type_other = reportTypeOtherInput.value;
            
            // Ensure report_type and is_anonymous are preserved if not found in FormData
            if (!draft.report_type && preservedReportType) {
                draft.report_type = preservedReportType;
            }
            if (!draft.is_anonymous && preservedIsAnonymous) {
                draft.is_anonymous = preservedIsAnonymous;
            }
            
            // Save to sessionStorage
            sessionStorage.setItem('report_draft', JSON.stringify(draft));
        }
        
        // Load draft from sessionStorage and populate form
        function loadDraftToForm() {
            const draftStr = sessionStorage.getItem('report_draft');
            if (!draftStr) return;
            
            try {
                const draft = JSON.parse(draftStr);
                const form = document.getElementById('reportForm');
                
                // Load all form fields from draft
                Object.keys(draft).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'radio' || field.type === 'checkbox') {
                            if (field.value === draft[key] || (field.value === String(draft[key]))) {
                                field.checked = true;
                            }
                        } else {
                            field.value = draft[key];
                        }
                    }
                });
                
                // Trigger change events to update UI
                document.querySelectorAll('input[type="radio"]').forEach(radio => {
                    if (radio.checked) {
                        radio.dispatchEvent(new Event('change'));
                    }
                });
            } catch (e) {
                console.error('Error loading draft:', e);
            }
        }
        
        // Load draft on page load
        window.addEventListener('DOMContentLoaded', function() {
            // Load draft to form first
            loadDraftToForm();
            
            // Review step
            if (currentStep === 5) {
                // Save draft one more time to ensure all data is captured
                // (in case user navigated directly to step 5 or refreshed)
                saveDraft();
                
                // Populate hidden inputs from draft before updating review
                // Use setTimeout to ensure DOM is fully ready
                setTimeout(function() {
                    populateHiddenInputs();
                    // Wait a bit more then update review
                    setTimeout(function() {
                        updateReview();
                    }, 100);
                }, 100);
                
                // Enable submit button when checkbox is checked
                document.querySelector('input[name="confirm"]')?.addEventListener('change', function() {
                    document.getElementById('submitBtn').disabled = !this.checked;
                });
            }
        });
        
        // Populate hidden inputs from draft/sessionStorage
        function populateHiddenInputs() {
            const draftStr = sessionStorage.getItem('report_draft');
            if (!draftStr) {
                return;
            }
            
            try {
                const draft = JSON.parse(draftStr);
                
                // Get values directly from draft (primary source)
                // Also try to get from radio buttons if available (for report_type and is_anonymous)
                const form = document.getElementById('reportForm');
                const reportTypeRadio = form ? form.querySelector('input[name="report_type"]:checked') : null;
                const isAnonymousRadio = form ? form.querySelector('input[name="is_anonymous"]:checked') : null;
                
                const isAnonymous = (isAnonymousRadio ? isAnonymousRadio.value : '') || draft.is_anonymous || '';
                const reportType = (reportTypeRadio ? reportTypeRadio.value : '') || draft.report_type || '';
                const reportTypeOther = draft.report_type_other || '';
                const title = draft.title || '';
                const description = draft.description || '';
                const location = draft.location || '';
                const incidentDate = draft.incident_date || '';
                const incidentTime = draft.incident_time || '';
                const priority = draft.priority || 'medium';
                const additionalInfo = draft.additional_info || '';
                
                // Set hidden inputs
                const hiddenIsAnonymous = document.getElementById('hidden_is_anonymous');
                const hiddenReportType = document.getElementById('hidden_report_type');
                const hiddenReportTypeOther = document.getElementById('hidden_report_type_other');
                const hiddenTitle = document.getElementById('hidden_title');
                const hiddenDescription = document.getElementById('hidden_description');
                const hiddenLocation = document.getElementById('hidden_location');
                const hiddenIncidentDate = document.getElementById('hidden_incident_date');
                const hiddenIncidentTime = document.getElementById('hidden_incident_time');
                const hiddenPriority = document.getElementById('hidden_priority');
                const hiddenAdditionalInfo = document.getElementById('hidden_additional_info');
                
                if (hiddenIsAnonymous) hiddenIsAnonymous.value = String(isAnonymous);
                if (hiddenReportType) hiddenReportType.value = String(reportType);
                if (hiddenReportTypeOther) hiddenReportTypeOther.value = String(reportTypeOther);
                if (hiddenTitle) hiddenTitle.value = String(title);
                if (hiddenDescription) hiddenDescription.value = String(description);
                if (hiddenLocation) hiddenLocation.value = String(location);
                if (hiddenIncidentDate) hiddenIncidentDate.value = String(incidentDate);
                if (hiddenIncidentTime) hiddenIncidentTime.value = String(incidentTime);
                if (hiddenPriority) hiddenPriority.value = String(priority);
                if (hiddenAdditionalInfo) hiddenAdditionalInfo.value = String(additionalInfo);
            } catch (e) {
                console.error('Error populating hidden inputs:', e);
            }
        }
        
        function updateReview() {
            // Get draft from sessionStorage (primary source)
            const draftStr = sessionStorage.getItem('report_draft');
            let draft = {};
            if (draftStr) {
                try {
                    draft = JSON.parse(draftStr);
                } catch (e) {
                    console.error('Error parsing draft:', e);
                }
            }
            
            // Get values from hidden inputs (populated from draft) or draft directly
            const hiddenIsAnonymous = document.getElementById('hidden_is_anonymous');
            const hiddenReportType = document.getElementById('hidden_report_type');
            const hiddenReportTypeOther = document.getElementById('hidden_report_type_other');
            const hiddenTitle = document.getElementById('hidden_title');
            const hiddenDescription = document.getElementById('hidden_description');
            const hiddenLocation = document.getElementById('hidden_location');
            const hiddenIncidentDate = document.getElementById('hidden_incident_date');
            const hiddenIncidentTime = document.getElementById('hidden_incident_time');
            const hiddenPriority = document.getElementById('hidden_priority');
            const hiddenAdditionalInfo = document.getElementById('hidden_additional_info');
            
            // Priority: hidden inputs > draft > default
            const isAnonymous = (hiddenIsAnonymous?.value) || draft.is_anonymous || '';
            const reportType = (hiddenReportType?.value) || draft.report_type || '';
            const reportTypeOther = (hiddenReportTypeOther?.value) || draft.report_type_other || '';
            const title = (hiddenTitle?.value) || draft.title || '';
            const description = (hiddenDescription?.value) || draft.description || '';
            const location = (hiddenLocation?.value) || draft.location || '';
            const incidentDate = (hiddenIncidentDate?.value) || draft.incident_date || '';
            const incidentTime = (hiddenIncidentTime?.value) || draft.incident_time || '';
            const priority = (hiddenPriority?.value) || draft.priority || 'medium';
            const additionalInfo = (hiddenAdditionalInfo?.value) || draft.additional_info || '';
            
            const reportTypeLabels = {
                'bullying': 'Perundungan',
                'violence': 'Kekerasan',
                'harassment': 'Pelecehan',
                'abuse': 'Abuse',
                'other': 'Lainnya'
            };
            
            let html = `
                <div class="review-section">
                    <h4>Identitas</h4>
                    <p>${isAnonymous == '1' || isAnonymous == 1 ? 'Laporan Anonim' : 'Laporan Beridentitas'}</p>
                </div>
                <div class="review-section">
                    <h4>Tipe Laporan</h4>
                    <p>${reportTypeLabels[reportType] || reportType || '-'}${(reportType === 'other' && reportTypeOther) ? ' - ' + reportTypeOther : ''}</p>
                </div>
                <div class="review-section">
                    <h4>Judul</h4>
                    <p>${title || '-'}</p>
                </div>
                <div class="review-section">
                    <h4>Deskripsi</h4>
                    <p>${description || '-'}</p>
                </div>
                <div class="review-section">
                    <h4>Lokasi & Waktu</h4>
                    <p>${location || 'Tidak diisi'}</p>
                    <p>${incidentDate || 'Tidak diisi'} ${incidentTime || ''}</p>
                </div>
                <div class="review-section">
                    <h4>Prioritas</h4>
                    <p>${priority ? priority.charAt(0).toUpperCase() + priority.slice(1) : 'Medium'}</p>
                </div>
            `;
            
            if (additionalInfo) {
                html += `
                    <div class="review-section">
                        <h4>Informasi Tambahan</h4>
                        <p>${additionalInfo}</p>
                    </div>
                `;
            }
            
            if (selectedFiles.length > 0) {
                html += '<div class="review-section"><h4>Bukti</h4>';
                selectedFiles.forEach(file => {
                    html += `<p>${file.name}</p>`;
                });
                html += '</div>';
            }
            
            document.getElementById('reviewContent').innerHTML = html;
        }
    </script>
</body>
</html>

