<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$name = $_SESSION['name'] ?? 'Teacher';
$teacher_id = $_SESSION['user_id'];

// Database Connection
$host = 'localhost';
$db   = 'aureon';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("<div class='alert alert-danger m-4'>Database Connection Failed: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Handle File Upload
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $course = $_POST['course'] ?? '';
    $year = $_POST['year'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $title = $_POST['title'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if ($course && $year && $subject && $_FILES['file']['error'] === 0) {
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png'];

        if (in_array($ext, $allowed) && $file['size'] <= 10*1024*1024) {
            $uploadDir = 'uploads/notes/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $newName = 'material_' . time() . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $pdo->prepare("INSERT INTO study_materials 
                    (teacher_id, course, year, subject, title, file_name, file_path, file_size, remarks) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$teacher_id, $course, $year, $subject, $title, $file['name'], $dest, $file['size'], $remarks]);
                $message = "<div class='alert alert-success alert-dismissible fade show' id='successAlert'>
                    ✅ Study material uploaded successfully!
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to save file on server.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Invalid file type or size exceeds 10MB.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please fill all required fields and select a file.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials | AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --cream: #fff9f0;
            --light-orange: #ffe6c7;
            --accent: #e89a4a;
            --text: #3f2a1e;
        }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--cream);
            color: var(--text);
        }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar {
            width: 290px;
            background: #fffaf0;
            border-right: 1px solid #f0e4d0;
            position: fixed;
            height: 100vh;
            box-shadow: 4px 0 25px rgba(232,154,74,0.08);
            z-index: 1000;
        }
        .sidebar .logo {
            width: 120px; height: 120px;
            background: linear-gradient(135deg, #ffe6c7, #ffbe7a);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 3.5rem;
            font-weight: 800;
            color: #3f2a1e;
        }
        .nav-item {
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #6b5a44;
            font-size: 1.05rem;
            margin-bottom: 8px;
            border-radius: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .nav-item:hover, .nav-item.active {
            background: var(--light-orange);
            color: var(--accent);
            transform: translateX(6px);
        }
        .logout-btn {
            margin-top: auto;
            padding: 16px 24px;
            color: #b56a5e;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 1.05rem;
            border-radius: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
        .main { margin-left: 290px; flex: 1; padding: 35px 40px; }
        .content-card { 
            background: white; 
            padding: 32px; 
            border-radius: 24px; 
            box-shadow: 0 10px 30px rgba(232,154,74,0.07); 
            transition: all 0.3s ease;
        }
        .content-card:hover {
            box-shadow: 0 15px 40px rgba(232,154,74,0.12);
            transform: translateY(-4px);
        }
        .upload-zone {
            border: 2px dashed #ffe6c7;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            background: #fdf4e8;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-zone:hover {
            background: var(--light-orange);
            border-color: #e89a4a;
            transform: scale(1.02);
        }
        .reason-textarea { display: none; margin-top: 6px; }
        .alert {
            animation: slideDown 0.4s ease forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="brand text-center">
            <div class="logo">
    <span class="logo-text">A</span>
    <i class="bi bi-mortarboard-fill logo-cap"></i>
</div>

<style>
/* ================= LOGO ================= */

.logo{
    width:120px;
    height:120px;

    margin:0 auto 18px;

    border-radius:28px;

    background:
    linear-gradient(135deg,#ffe6c7,#ffbe7a);

    display:flex;
    align-items:center;
    justify-content:center;

    position:relative;

    box-shadow:
    0 12px 35px rgba(232,154,74,0.25);

    transition:0.4s ease;
}

/* Hover Effect */
.logo:hover{
    transform:translateY(-5px) scale(1.03);

    box-shadow:
    0 18px 45px rgba(232,154,74,0.35);
}

/* Letter A */
.logo-text{
    font-size:4.8rem;
    font-weight:900;

    color:#3f2a1e;

    font-family:'Segoe UI',sans-serif;

    line-height:1;

    text-shadow:
    0 4px 10px rgba(0,0,0,0.08);
}

/* Graduation Cap */
.logo-cap{
    position:absolute;

    top:16px;
    right:20px;

    font-size:1.7rem;

    color:#3f2a1e;

    transform:rotate(-12deg);

    filter:drop-shadow(0 4px 8px rgba(0,0,0,0.15));
}
</style>
            <h4>AUREON ERP</h4>
            <small>Teacher Portal</small>
        </div>

        <div class="sidebar-nav">
            <a href="teacher_dash.php" class="nav-item"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
            <a href="mark_attendance.php" class="nav-item"><i class="bi bi-calendar-check"></i><span>Mark Attendance</span></a>
            <a href="#" class="nav-item"><i class="bi bi-eye"></i><span>View Attendance</span></a>
            <a href="study_materials.php" class="nav-item active"><i class="bi bi-cloud-arrow-up"></i><span>Study Materials</span></a>
            <a href="#" class="nav-item"><i class="bi bi-book-fill"></i><span>My Subjects</span></a>
            <a href="#" class="nav-item"><i class="bi bi-pencil-square"></i><span>Marks Entry</span></a>
            <a href="#" class="nav-item"><i class="bi bi-megaphone-fill"></i><span>Notices</span></a>
            <a href="#" class="nav-item"><i class="bi bi-shield-lock"></i><span>Admin Permissions</span></a>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main">
        <div class="top-bar bg-white border-bottom py-3 px-4 mb-4">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-cloud-arrow-up-fill fs-4" style="color:#e89a4a;"></i>
                <h5 class="mb-0 fw-semibold">Study Materials</h5>
            </div>
            <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
        </div>

        <div class="content-card">
            <?php if ($message) echo $message; ?>

            <h4 class="fw-bold mb-4"><i class="bi bi-cloud-arrow-up me-2" style="color:#e89a4a;"></i>Upload Study Material</h4>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Course</label>
                        <select class="form-select" name="course" required>
                            <option value="">Select Course</option>
                            <option value="PUC">PUC</option>
                            <option value="BCA">BCA</option>
                            <option value="MCA">MCA</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Year</label>
                        <select class="form-select" name="year" required>
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Subject</label>
                        <select class="form-select" name="subject" required>
                            <option value="">Select Subject</option>
                            <option value="Physics">Physics</option>
                            <option value="Chemistry">Chemistry</option>
                            <option value="Biology">Biology</option>
                            <option value="Mathematics">Mathematics</option>
                            <option value="English">English</option>
                            <option value="Programming in C">Programming in C</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">Date</label>
                        <input type="date" class="form-control" name="attendance_date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted">Title / Topic</label>
                    <input type="text" class="form-control" name="title" placeholder="e.g. Unit 1 - Introduction to Physics" required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted">Upload File</label>
                    <input type="file" class="form-control" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" required id="fileInput">
                    <small class="text-muted">Max 10MB • PDF, DOC, DOCX, PPT, PPTX, JPG, PNG</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-muted">Remarks (Optional)</label>
                    <textarea class="form-control" name="remarks" rows="3" placeholder="Important notes for students..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <button type="button" class="btn btn-secondary px-4" onclick="history.back()">Cancel</button>
                    <button type="submit" class="btn px-5" id="uploadBtn" style="background:#e89a4a; color:white; border:none;">
                        <i class="bi bi-cloud-arrow-up"></i> <span id="btnText">Upload Material</span>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const form = document.getElementById('uploadForm');
const btn = document.getElementById('uploadBtn');
const btnText = document.getElementById('btnText');

form.addEventListener('submit', function() {
    btn.disabled = true;
    btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Uploading...';
});

 // Auto hide success message after 4 seconds
setTimeout(() => {
    const alert = document.querySelector('.alert-success');
    if (alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }
}, 4000);

// Reset form after successful upload (if you want auto reset)
<?php if (strpos($message, 'success') !== false): ?>
    form.reset();
<?php endif; ?>
</script>

</body>
</html>