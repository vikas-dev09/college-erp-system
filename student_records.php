<?php
session_start();

// Redirect to login if not logged in or not a student
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

// ═══════════════════════════════════════
// DATABASE CONNECTION
// ═══════════════════════════════════════
$host = "localhost";
$db   = "aureon"; 
$user = "root";   
$pass = "";       

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// ═══════════════════════════════════════
// FETCH REAL STUDENT DETAILS FROM 'users' TABLE
// ═══════════════════════════════════════
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT student_id, full_name FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userData) {
    $studentName = $userData['full_name'];
    $studentId = $userData['student_id'];
} else {
    // Fallback if user is somehow deleted or not found
    $studentName = "Unknown Student";
    $studentId = "N/A";
}

// Mocking course and year for now
$course = 'BCA';
$academicYear = '2023-2026';

// Generate Avatar Letter
$avatarLetter = strtoupper(substr(trim($studentName), 0, 1));
if ($avatarLetter === '') {
    $avatarLetter = 'S';
}

$uploadMessage = '';
$uploadStatus = '';

// ═══════════════════════════════════════
// HANDLE FILE UPLOAD
// ═══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $docName = trim($_POST['document_name'] ?? '');
    $docNickname = trim($_POST['nickname'] ?? '');
    $docType = $_POST['document_type'] ?? 'other';
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['document_file']['tmp_name'];
        $fileName = $_FILES['document_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Generate a unique file name to prevent overwriting
        $newFileName = $studentId . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadDir = 'uploads/';
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $destPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Save to Database
            $stmt = $pdo->prepare("
                INSERT INTO student_documents 
                (student_id, student_name, document_name, nickname, document_type, file_name, file_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            try {
                $stmt->execute([$studentId, $studentName, $docName, $docNickname, $docType, $fileName, $destPath]);
                $uploadMessage = 'Document uploaded successfully!';
                $uploadStatus = 'success';
            } catch (PDOException $e) {
                $uploadMessage = 'Database error: ' . $e->getMessage();
                $uploadStatus = 'error';
            }
        } else {
            $uploadMessage = 'Error moving uploaded file.';
            $uploadStatus = 'error';
        }
    } else {
        $uploadMessage = 'Please select a valid file.';
        $uploadStatus = 'error';
    }
}

// ═══════════════════════════════════════
// FETCH USER DOCUMENTS
// ═══════════════════════════════════════
$stmt = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$studentId]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$documentCount = count($documents);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record - Digital Vault</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --primary-light: #ede9fe;
            --bg-soft: #fdf4e8;
            --white: #ffffff;
            --dark: #1f1635;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --blue: #3b82f6;
            --shadow: 0 10px 30px rgba(31, 22, 53, 0.08);
            --shadow-purple: 0 18px 45px rgba(139, 92, 246, 0.22);
            --sidebar-width: 260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-soft); color: var(--dark); min-height: 100vh; overflow-x: hidden; }
        .erp-layout { display: flex; min-height: 100vh; }

        /* ===== SIDEBAR ===== */
       .sidebar { 
    width: var(--sidebar-width); 
    height: 100vh; 
    position: fixed; 
    left: 0; 
    top: 0; 
    background: var(--white); 
    border-right: 1px solid var(--border); 
    padding: 24px 18px; 
    z-index: 200; 
    display: flex;       /* Added */
    flex-direction: column; /* Added */
}
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 36px; padding: 0 6px; }
        .brand-icon { width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--white); display: grid; place-items: center; font-size: 20px; box-shadow: 0 10px 25px rgba(139, 92, 246, 0.35); }
        .brand-text h2 { font-size: 20px; font-weight: 800; color: var(--dark); line-height: 1.1; }
        .brand-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }
       .sidebar-nav { 
    display: flex; 
    flex-direction: column; 
    gap: 8px; 
    flex-grow: 1; /* This makes the nav take up all available space */
}
        .nav-item { text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 600; padding: 13px 15px; border-radius: 14px; display: flex; align-items: center; gap: 12px; transition: 0.3s ease; }
        .nav-item i { width: 20px; font-size: 16px; }
        .nav-item:hover, .nav-item.active { background: var(--primary-light); color: var(--primary-dark); }
       .nav-item.logout { 
    margin-top: auto; /* This magic line pushes it to the bottom */
    color: var(--danger); 
}
        .nav-item.logout:hover { background: #fef2f2; color: var(--danger); }

        /* ===== MAIN CONTENT ===== */
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); min-height: 100vh; padding: 24px; transition: 0.3s ease; }
        .topbar { background: var(--white); border-radius: 20px; padding: 16px 22px; box-shadow: var(--shadow); display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .topbar-left { display: flex; align-items: center; gap: 16px; }
        .menu-toggle { display: none; width: 42px; height: 42px; border: none; border-radius: 12px; background: var(--primary-light); color: var(--primary-dark); font-size: 18px; cursor: pointer; }
        .welcome-box h1 { font-size: 21px; font-weight: 800; color: var(--dark); }
        .welcome-box p { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .top-icon { width: 44px; height: 44px; border: none; border-radius: 14px; background: #f8fafc; color: var(--primary-dark); display: grid; place-items: center; font-size: 17px; cursor: pointer; position: relative; transition: 0.3s ease; }
        .top-icon:hover { background: var(--primary); color: var(--white); transform: translateY(-2px); }
        .notification-dot { position: absolute; top: 8px; right: 9px; width: 8px; height: 8px; background: var(--danger); border-radius: 50%; border: 2px solid var(--white); }
        .profile-avatar { width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--white); display: grid; place-items: center; font-size: 16px; font-weight: 800; box-shadow: 0 10px 25px rgba(139, 92, 246, 0.28); }

        /* ===== PAGE HEADER ===== */
        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 30px; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 12px; }
        .page-header h2 i { color: var(--primary); }
        .page-header p { color: var(--text-muted); margin-top: 8px; font-size: 15px; }

        /* ===== VAULT CARD ===== */
        .vault-area { max-width: 640px; }
        .vault-card { width: 100%; min-height: 245px; border: 1px solid var(--border); background: var(--white); border-radius: 22px; box-shadow: var(--shadow); padding: 34px 28px; cursor: pointer; text-align: left; position: relative; overflow: hidden; transition: 0.3s ease; }
        .vault-card::before { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at top right, rgba(139, 92, 246, 0.18), transparent 42%); opacity: 0; transition: 0.3s ease; }
        .vault-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-purple); border-color: #c4b5fd; }
        .vault-card:hover::before { opacity: 1; }
        .vault-card-content { position: relative; z-index: 2; }
        .vault-icon { width: 76px; height: 76px; border-radius: 22px; background: linear-gradient(135deg, var(--primary-light), #f5f3ff); color: var(--primary); display: grid; place-items: center; font-size: 34px; margin-bottom: 22px; transition: 0.3s ease; }
        .vault-card:hover .vault-icon { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--white); transform: scale(1.06); }
        .vault-card h3 { font-size: 23px; font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        .vault-card p { font-size: 14px; color: var(--text-muted); line-height: 1.6; max-width: 460px; }
        .vault-arrow { position: absolute; right: 24px; bottom: 24px; width: 42px; height: 42px; border-radius: 14px; background: var(--primary-light); color: var(--primary-dark); display: grid; place-items: center; transition: 0.3s ease; }
        .vault-card:hover .vault-arrow { background: var(--primary); color: var(--white); transform: translateX(4px); }

        /* ===== MODAL ===== */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.66); backdrop-filter: blur(5px); z-index: 999; display: flex; justify-content: center; align-items: center; padding: 22px; opacity: 0; visibility: hidden; transition: 0.3s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-box { width: min(1020px, 100%); max-height: 92vh; background: var(--white); border-radius: 24px; box-shadow: 0 28px 70px rgba(0, 0, 0, 0.26); overflow: hidden; transform: scale(0.9) translateY(18px); transition: 0.3s ease; }
        .modal-overlay.active .modal-box { transform: scale(1) translateY(0); }
        .modal-header { padding: 20px 24px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--white); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 19px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .close-btn { width: 40px; height: 40px; border: none; border-radius: 12px; background: rgba(255, 255, 255, 0.16); color: var(--white); cursor: pointer; font-size: 18px; transition: 0.3s ease; }
        .close-btn:hover { background: rgba(255, 255, 255, 0.28); transform: rotate(90deg); }
        .modal-body { padding: 24px; max-height: calc(92vh - 82px); overflow-y: auto; background: #fffaf3; }

        /* ===== MODAL SECTIONS ===== */
        .vault-intro { background: var(--white); border: 1px solid var(--border); border-radius: 18px; padding: 18px 20px; box-shadow: 0 6px 18px rgba(31, 22, 53, 0.05); margin-bottom: 18px; display: flex; align-items: center; gap: 14px; }
        .vault-intro-icon { width: 52px; height: 52px; border-radius: 16px; background: var(--primary-light); color: var(--primary-dark); display: grid; place-items: center; font-size: 22px; flex-shrink: 0; }
        .vault-intro h4 { font-size: 17px; font-weight: 800; margin-bottom: 4px; }
        .vault-intro p { font-size: 13px; color: var(--text-muted); margin-bottom: 4px; }
        .vault-intro .student-meta { font-size: 12px; font-weight: 600; color: var(--primary-dark); background: var(--primary-light); padding: 4px 10px; border-radius: 8px; display: inline-block; margin-top: 4px; }

        .modal-section { background: var(--white); border: 1px solid var(--border); border-radius: 18px; padding: 20px; box-shadow: 0 6px 18px rgba(31, 22, 53, 0.05); margin-bottom: 18px; }
        .section-title { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; color: var(--dark); margin-bottom: 16px; }
        .section-title i { color: var(--primary); }
        .section-divider { height: 2px; background: linear-gradient(90deg, var(--primary-light), transparent); border-radius: 2px; margin-bottom: 18px; }

        /* ===== UPLOAD FORM ===== */
        .upload-form { display: grid; gap: 16px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 700; color: var(--dark); display: flex; align-items: center; gap: 6px; }
        .form-group label i { color: var(--primary); font-size: 12px; }
        .form-control { width: 100%; border: 1.5px solid var(--border); background: #ffffff; border-radius: 14px; padding: 13px 14px; outline: none; color: var(--dark); font-size: 14px; transition: 0.3s ease; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12); }
        .form-control::placeholder { color: #cbd5e1; }
        .file-input-wrapper { position: relative; }
        .file-input-wrapper input[type="file"] { padding: 12px 14px; }
        .file-input-wrapper input[type="file"]::file-selector-button { background: var(--primary-light); color: var(--primary-dark); border: none; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; margin-right: 12px; transition: 0.3s ease; }
        .file-input-wrapper input[type="file"]::file-selector-button:hover { background: var(--primary); color: var(--white); }
        .submit-btn { justify-self: start; border: none; border-radius: 14px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--white); padding: 14px 28px; font-size: 14px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 9px; transition: 0.3s ease; }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(139, 92, 246, 0.28); }
        .form-message { padding: 12px 16px; border-radius: 14px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .form-message.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .form-message.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        /* ===== DOCUMENT CARDS ===== */
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .document-card { background: var(--white); border: 1.5px solid var(--border); border-radius: 18px; padding: 20px; transition: 0.3s ease; position: relative; overflow: hidden; }
        .document-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), #c4b5fd); opacity: 0; transition: 0.3s ease; }
        .document-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(139, 92, 246, 0.14); border-color: #c4b5fd; }
        .document-card:hover::before { opacity: 1; }
        .doc-card-header { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 16px; }
        .doc-card-icon { width: 48px; height: 48px; border-radius: 14px; background: var(--primary-light); color: var(--primary-dark); display: grid; place-items: center; font-size: 20px; flex-shrink: 0; transition: 0.3s ease; }
        .document-card:hover .doc-card-icon { background: var(--primary); color: var(--white); }
        .doc-card-info { flex: 1; overflow: hidden; }
        .doc-card-info h4 { font-size: 15px; font-weight: 800; color: var(--dark); margin-bottom: 4px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .doc-card-nickname { display: inline-flex; align-items: center; gap: 5px; background: #f1f5f9; color: var(--text-muted); padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .doc-card-meta { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; padding-top: 12px; border-top: 1px solid #f1f5f9; }
        .meta-item { font-size: 12px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .meta-item i { color: var(--primary); font-size: 11px; }
        .type-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; text-transform: capitalize; }
        .type-pill.marksheet { background: #ede9fe; color: #7c3aed; }
        .type-pill.aadhaar { background: #dbeafe; color: #2563eb; }
        .type-pill.certificate { background: #dcfce7; color: #16a34a; }
        .type-pill.idcard { background: #fef3c7; color: #d97706; }
        .type-pill.other { background: #f1f5f9; color: #64748b; }
        .doc-card-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-btn { border: none; border-radius: 12px; padding: 10px 14px; font-size: 12px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s ease; flex: 1; justify-content: center; min-width: 70px; text-decoration: none; }
        .action-btn:hover { transform: translateY(-2px); }
        .action-btn.view { background: var(--primary-light); color: var(--primary-dark); }
        .action-btn.view:hover { background: var(--primary); color: var(--white); }
        .action-btn.download { background: #eff6ff; color: var(--blue); }
        .action-btn.download:hover { background: var(--blue); color: var(--white); }

        /* ===== EMPTY STATE ===== */
        .empty-state { padding: 40px 20px; text-align: center; color: var(--text-muted); border: 2px dashed var(--border); border-radius: 18px; background: #ffffff; }
        .empty-icon { width: 70px; height: 70px; border-radius: 20px; background: var(--primary-light); color: var(--primary); display: grid; place-items: center; font-size: 28px; margin: 0 auto 14px; }
        .empty-state h4 { font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
        .empty-state p { font-size: 13px; }

        /* ===== DOCUMENT COUNT ===== */
        .doc-count-badge { background: var(--primary); color: var(--white); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 800; margin-left: 8px; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); box-shadow: none; }
            .sidebar.active { transform: translateX(0); box-shadow: 120px 0 0 rgba(15, 23, 42, 0.42); }
            .main-content { margin-left: 0; width: 100%; }
            .menu-toggle { display: grid; place-items: center; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 16px; }
            .topbar { padding: 14px; border-radius: 18px; }
            .welcome-box h1 { font-size: 17px; }
            .welcome-box p { font-size: 12px; }
            .page-header h2 { font-size: 24px; }
            .vault-card { min-height: 220px; padding: 28px 22px; }
            .modal-overlay { padding: 10px; align-items: flex-end; }
            .modal-box { width: 100%; border-radius: 22px 22px 0 0; max-height: 94vh; }
            .modal-body { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .documents-grid { grid-template-columns: 1fr; }
            .vault-intro { flex-direction: column; text-align: center; }
            .doc-card-actions { flex-direction: column; }
            .action-btn { flex: unset; }
        }
        @media (max-width: 480px) {
            .brand-text h2 { font-size: 18px; }
            .page-header h2 { font-size: 22px; }
            .vault-card h3 { font-size: 20px; }
            .vault-icon { width: 66px; height: 66px; font-size: 30px; }
            .topbar-actions { gap: 8px; }
        }
        .modal-body::-webkit-scrollbar { width: 6px; }
        .modal-body::-webkit-scrollbar-track { background: transparent; }
        .modal-body::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 10px; }
        .modal-body::-webkit-scrollbar-thumb:hover { background: #a1a1aa; }
    </style>
</head>
<body>

<div class="erp-layout">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="brand-text">
                <h2>AUREON</h2>
                <span>ERP System</span>
            </div>
        </div>
       <nav class="sidebar-nav">
    <a href="student_dash.php" class="nav-item">
        <i class="fa-solid fa-house"></i> Dashboard
    </a>
    <a href="student_records.php" class="nav-item active">
        <i class="fa-solid fa-vault"></i> Digital Vault
    </a>
    
    
    <a href="logout.php" class="nav-item logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
    </a>
</nav>
    </aside>

    <main class="main-content">
        
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('active')">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="welcome-box">
                    <h1>Welcome, <?= htmlspecialchars($studentName) ?></h1>
                    <p>Student ID: <?= htmlspecialchars($studentId) ?> | Your secure academic document vault</p>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="top-icon">
                    <i class="fa-regular fa-bell"></i>
                    <span class="notification-dot"></span>
                </button>
                <div class="profile-avatar">
                    <?= htmlspecialchars($avatarLetter) ?>
                </div>
            </div>
        </header>

        <div class="page-header">
            <h2><i class="fa-solid fa-shield-halved"></i> Digital Vault</h2>
            <p>Securely store and manage your academic documents and personal records.</p>
        </div>

        <div class="vault-area">
            <div class="vault-card" onclick="openVaultModal()">
                <div class="vault-card-content">
                    <div class="vault-icon">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <h3>My Documents Vault</h3>
                    <p>Access your uploaded certificates, marksheets, ID proofs, and securely upload new documents to your profile.</p>
                </div>
                <div class="vault-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>
        </div>

        <div class="modal-section" style="margin-top: 30px;">
            <div class="section-title">
                <i class="fa-solid fa-folder-tree"></i> My Uploaded Documents
                <span class="doc-count-badge"><?= $documentCount ?></span>
            </div>
            <div class="section-divider"></div>
            
            <?php if ($documentCount > 0): ?>
                <div class="documents-grid">
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-card">
                            <div class="doc-card-header">
                                <div class="doc-card-icon">
                                    <?php 
                                        // Assign different icons based on document type
                                        $iconClass = 'fa-file-lines';
                                        if($doc['document_type'] === 'marksheet') $iconClass = 'fa-file-signature';
                                        if($doc['document_type'] === 'certificate') $iconClass = 'fa-certificate';
                                        if($doc['document_type'] === 'idcard' || $doc['document_type'] === 'aadhaar') $iconClass = 'fa-id-card';
                                    ?>
                                    <i class="fa-solid <?= $iconClass ?>"></i>
                                </div>
                                <div class="doc-card-info">
                                    <h4><?= htmlspecialchars($doc['document_name']) ?></h4>
                                    <?php if (!empty($doc['nickname'])): ?>
                                        <span class="doc-card-nickname">
                                            <i class="fa-solid fa-tag" style="font-size:10px;"></i> <?= htmlspecialchars($doc['nickname']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="doc-card-meta">
                                <div class="meta-item">
                                    <i class="fa-regular fa-calendar"></i> 
                                    <?= date('d M Y', strtotime($doc['uploaded_at'])) ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fa-solid fa-user-check"></i> 
                                    <?= htmlspecialchars($doc['student_name']) ?>
                                </div>
                                <span class="type-pill <?= htmlspecialchars($doc['document_type']) ?>">
                                    <?= htmlspecialchars($doc['document_type']) ?>
                                </span>
                            </div>
                            
                            <div class="doc-card-actions">
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="action-btn view">
                                    <i class="fa-regular fa-eye"></i> View
                                </a>
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" download="<?= htmlspecialchars($doc['file_name']) ?>" class="action-btn download">
                                    <i class="fa-solid fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-box-open"></i></div>
                    <h4>No Documents Found</h4>
                    <p>You haven't uploaded any documents to your vault yet. Click the Vault card above to add your first document.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<div class="modal-overlay" id="vaultModal" <?= !empty($uploadMessage) ? 'style="opacity: 1; visibility: visible;"' : '' ?>>
    <div class="modal-box" <?= !empty($uploadMessage) ? 'style="transform: scale(1) translateY(0);"' : '' ?>>
        <div class="modal-header">
            <h3><i class="fa-solid fa-vault"></i> Secure Document Vault</h3>
            <button class="close-btn" onclick="closeVaultModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="modal-body">
            
            <div class="vault-intro">
                <div class="vault-intro-icon">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <div>
                    <h4><?= htmlspecialchars($studentName) ?></h4>
                    <p>Student ID: <?= htmlspecialchars($studentId) ?></p>
                    <div class="student-meta">
                        <i class="fa-solid fa-book" style="margin-right:4px;"></i> <?= htmlspecialchars($course) ?> | 
                        <i class="fa-regular fa-calendar" style="margin-left:4px; margin-right:4px;"></i> <?= htmlspecialchars($academicYear) ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($uploadMessage)): ?>
                <div class="form-message <?= $uploadStatus ?>" style="margin-bottom: 18px;">
                    <i class="fa-solid <?= $uploadStatus === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                    <?= htmlspecialchars($uploadMessage) ?>
                </div>
            <?php endif; ?>

            <div class="modal-section">
                <div class="section-title">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload New Document
                </div>
                <div class="section-divider"></div>
                
                <form class="upload-form" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fa-regular fa-file-lines"></i> Document Name</label>
                            <input type="text" name="document_name" class="form-control" placeholder="e.g. 10th Marksheet" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-tag"></i> Nickname (Optional)</label>
                            <input type="text" name="nickname" class="form-control" placeholder="e.g. SSC Result">
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-layer-group"></i> Document Type</label>
                            <select name="document_type" class="form-control" required>
                                <option value="marksheet">Marksheet / Transcript</option>
                                <option value="certificate">Certificate</option>
                                <option value="idcard">ID Card</option>
                                <option value="aadhaar">Government ID</option>
                                <option value="other">Other Document</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-paperclip"></i> Select File</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="upload_document" class="submit-btn">
                        <i class="fa-solid fa-upload"></i> Upload to Vault
                    </button>
                </form>
            </div>
            
        </div>
    </div>
</div>

<script>
    function openVaultModal() {
        document.getElementById('vaultModal').classList.add('active');
        document.body.style.overflow = 'hidden'; 
    }

    function closeVaultModal() {
        document.getElementById('vaultModal').classList.remove('active');
        document.body.style.overflow = '';
        
        // Remove URL query parameters if they exist after form submission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.pathname);
        }
    }

    // Close modal if clicked outside the box
    document.getElementById('vaultModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeVaultModal();
        }
    });
</script>

</body>
</html>