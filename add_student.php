<?php
ob_start();
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db_host = 'localhost';
$db_name = 'aureon';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) UNIQUE NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `dob` DATE NOT NULL,
    `gender` ENUM('Male','Female','Other') NOT NULL,
    `religion` VARCHAR(50) DEFAULT NULL,
    `category` VARCHAR(50) DEFAULT NULL,
    `blood_group` VARCHAR(10) DEFAULT NULL,
    `phone` VARCHAR(15) UNIQUE NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `address` TEXT NOT NULL,
    `guardian_phone` VARCHAR(15) NOT NULL,
    `father_name` VARCHAR(100) NOT NULL,
    `mother_name` VARCHAR(100) NOT NULL,
    `admission_type` ENUM('New','Transfer') NOT NULL,
    `course` VARCHAR(50) NOT NULL,
    `stream` VARCHAR(50) DEFAULT NULL,
    `year` INT NOT NULL,
    `open_elective` VARCHAR(100) DEFAULT NULL,
    `previous_college` VARCHAR(200) DEFAULT NULL,
    `previous_course` VARCHAR(100) DEFAULT NULL,
    `transfer_reason` TEXT DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$success_msg = '';
$errors = [];
$old = [];

function generateStudentId($pdo) {
    $prefix = 'AUR' . date('y');
    $stmt = $pdo->query("SELECT student_id FROM students WHERE student_id LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $num = $last ? intval(substr($last, 5)) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $admission_type   = trim($_POST['admission_type'] ?? '');
    $course           = trim($_POST['course'] ?? '');
    $stream           = trim($_POST['stream'] ?? '');
    $year             = intval($_POST['year'] ?? 0);
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $dob              = trim($_POST['dob'] ?? '');
    $gender           = trim($_POST['gender'] ?? '');
    $religion         = trim($_POST['religion'] ?? '');
    $category         = trim($_POST['category'] ?? '');
    $blood_group      = trim($_POST['blood_group'] ?? '');
    $password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
    $phone            = trim($_POST['phone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $guardian_phone   = trim($_POST['guardian_phone'] ?? '');
    $father_name      = trim($_POST['father_name'] ?? '');
    $mother_name      = trim($_POST['mother_name'] ?? '');
    $open_elective    = trim($_POST['open_elective'] ?? '');
    $previous_college = trim($_POST['previous_college'] ?? '');
    $previous_course  = trim($_POST['previous_course'] ?? '');
    $transfer_reason  = trim($_POST['transfer_reason'] ?? '');

    if(empty($admission_type)) $errors['admission_type'] = 'Admission Type is required';
    if(empty($course))         $errors['course'] = 'Course is required';
    if($course === 'PUC' && empty($stream)) $errors['stream'] = 'Stream is required for PUC';

    $valid_years = match($course) {
        'PUC' => [1,2], 'BCA' => [1,2,3], 'MCA' => [1,2], default => [1,2,3]
    };
    if(!in_array($year, $valid_years)) $errors['year'] = 'Invalid year for selected course';

    if(empty($first_name))     $errors['first_name'] = 'First Name is required';
    if(empty($last_name))      $errors['last_name'] = 'Last Name is required';
    if(empty($dob))            $errors['dob'] = 'Date of Birth is required';
    if(empty($gender))         $errors['gender'] = 'Gender is required';
    if(empty($phone))          $errors['phone'] = 'Phone is required';
    elseif(!preg_match('/^\d{10}$/', $phone)) $errors['phone'] = 'Phone must be exactly 10 digits';
    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email format';
    if(empty($address))        $errors['address'] = 'Address is required';
    if(empty($guardian_phone)) $errors['guardian_phone'] = 'Guardian Phone is required';
    elseif(!preg_match('/^\d{10}$/', $guardian_phone)) $errors['guardian_phone'] = 'Must be exactly 10 digits';
    if(empty($father_name))    $errors['father_name'] = "Father's Name is required";
    if(empty($mother_name))    $errors['mother_name'] = "Mother's Name is required";
    if(empty($password)) {
    $errors['password'] = 'Password is required';
}
elseif(strlen($password) < 10) {
    $errors['password'] = 'Minimum 10 characters required';
}
elseif(!preg_match('/[A-Z]/', $password)) {
    $errors['password'] = 'At least 1 uppercase letter required';
}
elseif(!preg_match('/[0-9]/', $password)) {
    $errors['password'] = 'At least 1 number required';
}

if($password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match';
}

    if(empty($errors['phone'])) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE phone = ?");
        $stmt->execute([$phone]);
        if($stmt->fetch()) $errors['phone'] = 'This phone number is already registered';
    }

    if(!empty($email) && empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->fetch()) $errors['email'] = 'This email is already registered';
    }

    $photo_name = null;
    if(!empty($_FILES['photo']['name'])) {
        $allowed_ext = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if(!in_array($ext, $allowed_ext)) $errors['photo'] = 'Only JPG, JPEG, PNG or WEBP allowed';
        elseif($_FILES['photo']['size'] > 2 * 1024 * 1024) $errors['photo'] = 'Photo must be less than 2MB';
    }

    if(empty($errors)) {
        $student_id = generateStudentId($pdo);

        if(!empty($_FILES['photo']['name'])) {
            if(!is_dir('uploads/students')) mkdir('uploads/students', 0755, true);
            $photo_name = $student_id . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/students/' . $photo_name);
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO students
    (student_id, first_name, last_name, dob, gender, religion, category, blood_group,
     phone, email, address, guardian_phone, father_name, mother_name,
     admission_type, course, stream, year, open_elective,
     previous_college, previous_course, transfer_reason, photo, password)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

      $stmt->execute([
    $student_id, $first_name, $last_name, $dob, $gender,
    $religion ?: null, $category ?: null, $blood_group ?: null,
    $phone, $email ?: null, $address, $guardian_phone,
    $father_name, $mother_name, $admission_type, $course,
    $stream ?: null, $year, $open_elective ?: null,
    $previous_college ?: null, $previous_course ?: null,
    $transfer_reason ?: null, $photo_name,
    $hashed_password
]);
      $success_msg = "Student registered successfully!<br>
                <strong>Student ID:</strong> {$student_id}<br>";

$old = [];
    }
}

function old($key, $default = '') {
    global $old;
    return htmlspecialchars($old[$key] ?? $default);
}

function err($key) {
    global $errors;
    return isset($errors[$key])
        ? '<span class="field-error"><i class="fa-solid fa-circle-exclamation"></i> '.$errors[$key].'</span>'
        : '';
}

function errClass($key) {
    global $errors;
    return isset($errors[$key]) ? 'has-error' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student | AUREON ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}

        :root{
            --violet:#7c3aed;
            --violet-dark:#6d28d9;
            --violet-light:#a78bfa;
            --violet-pale:#ede9fe;
            --violet-glow:rgba(124,58,237,0.12);
            --orange:#f97316;
            --orange-pale:#fff7ed;
            --pink:#ec4899;
            --pink-pale:#fdf2f8;
            --success:#16a34a;
            --success-pale:#f0fdf4;
            --error:#dc2626;
            --error-pale:#fef2f2;
            --warning:#d97706;
            --dark:#1e293b;
            --text:#334155;
            --text-muted:#64748b;
            --text-dim:#94a3b8;
            --border:#e2e8f0;
            --border-light:#f1f5f9;
            --white:#ffffff;
            --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);
            --card-shadow:0 10px 30px rgba(0,0,0,0.06);
            --card-shadow-hover:0 20px 50px rgba(124,58,237,0.1);
            --radius:20px;
            --radius-md:14px;
            --radius-sm:12px;
            --radius-xs:8px;
        }

        html{scroll-behavior:smooth}

        body{
            min-height:100vh;
            font-family:'Inter','Segoe UI',sans-serif;
            background:var(--bg);
            color:var(--text);
            display:flex;
        }

        /* ═══════════════════════════════════
           SIDEBAR — Glassmorphic Light
        ═══════════════════════════════════ */
        .sidebar{
            width:260px;
            background:rgba(255,255,255,0.55);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            border-right:1px solid rgba(255,255,255,0.7);
            display:flex;
            flex-direction:column;
            align-items:center;
            position:fixed;
            top:0;bottom:0;
            z-index:100;
            padding:20px 0;
            transition:all 0.3s ease;
        }

        .sidebar-logo{
            width:48px;height:48px;
            margin-bottom:60px;
        }

        .sidebar-logo img{
            width:100%;height:100%;
            object-fit:contain;
            filter:drop-shadow(0 4px 10px rgba(124,58,237,0.2));
        }

        .sidebar-nav{
            flex:1;
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:6px;
            width:100%;
            padding:0 10px;
        }

        .s-item{
            width:56px;height:56px;
            border-radius:16px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap:3px;
            font-size:5px;
            font-weight:100;
            color:var(--text-muted);
            text-decoration:none;
            transition:all 0.25s ease;
            position:relative;
        }

        .s-item i{font-size:18px;transition:all 0.25s}

        .s-item:hover{
            background:var(--violet-pale);
            color:var(--violet);
            transform:scale(1.05);
        }

        .s-item.active{
            background:linear-gradient(135deg,var(--violet),var(--pink));
            color:white;
            box-shadow:0 8px 20px rgba(124,58,237,0.25);
        }

        .s-item .tooltip{
            position:absolute;
            left:70px;top:50%;
            transform:translateY(-50%);
            background:var(--dark);
            color:white;
            padding:6px 12px;
            border-radius:8px;
            font-size:12px;
            font-weight:600;
            white-space:nowrap;
            opacity:0;
            pointer-events:none;
            transition:all 0.2s;
            z-index:200;
        }

        .s-item:hover .tooltip{
            opacity:1;
            left:74px;
        }

        .sidebar-bottom{
            padding:10px;
        }

      .s-logout {
    width: 100%;
    height: auto;
    border-radius: 14px;
    border: none;
    background: var(--pink-pale);   /* 👈 light pink */
    color: var(--pink);             /* 👈 pink text/icon */

    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    font-size: 20px;
    cursor: pointer;
    transition: all 0.25s ease;
}
       .s-logout:hover {
    background: var(--error-pale);  /* 👈 light red */
    color: var(--error);            /* 👈 red text/icon */
    transform: scale(1.05);
}
        /* ═══════════════════════════════════
           MAIN CONTENT
        ═══════════════════════════════════ */
        .main{
            margin-left:260px;
            flex:1;
            padding:32px 44px;
            min-height:100vh;
        }

        /* Top Bar */
        .top-bar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:28px;
        }

        .top-bar h1{
            font-size:26px;
            font-weight:800;
            color:var(--dark);
            display:flex;
            align-items:center;
            gap:12px;
        }

        .top-bar h1 .icon-circle{
            width:42px;height:42px;
            border-radius:14px;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            display:flex;
            align-items:center;
            justify-content:center;
            color:white;
            font-size:16px;
        }

        .breadcrumb{
            font-size:13px;
            color:var(--text-dim);
            display:flex;
            align-items:center;
            gap:6px;
        }

        .breadcrumb a{
            color:var(--text-muted);
            text-decoration:none;
        }

        .breadcrumb a:hover{color:var(--violet)}

        /* ═══════════════════════════════════
           ALERTS
        ═══════════════════════════════════ */
        .alert{
            padding:18px 24px;
            border-radius:var(--radius-md);
            margin-bottom:24px;
            font-size:14px;
            font-weight:500;
            display:flex;
            align-items:flex-start;
            gap:12px;
            animation:slideAlert 0.4s ease;
        }

        .alert i{font-size:20px;margin-top:1px;flex-shrink:0}

        .alert-success{
            background:var(--success-pale);
            border:1px solid rgba(22,163,74,0.15);
            color:var(--success);
        }

        .alert-error{
            background:var(--error-pale);
            border:1px solid rgba(220,38,38,0.15);
            color:var(--error);
        }

        @keyframes slideAlert{
            from{opacity:0;transform:translateY(-10px)}
            to{opacity:1;transform:translateY(0)}
        }

        /* ═══════════════════════════════════
           FORM CARD
        ═══════════════════════════════════ */
        .form-card{
            background:var(--white);
            border-radius:var(--radius);
            box-shadow:var(--card-shadow);
            overflow:hidden;
        }

        .section{
            padding:30px 36px;
            border-bottom:1px solid var(--border-light);
        }

        .section:last-of-type{border-bottom:none}

        .section-title{
            font-size:16px;
            font-weight:700;
            color:var(--dark);
            margin-bottom:22px;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .section-title i{
            width:32px;height:32px;
            border-radius:10px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:14px;
        }

        .section-title:nth-of-type(1) i,
        .st-violet i{background:var(--violet-pale);color:var(--violet)}
        .st-pink i{background:var(--pink-pale);color:var(--pink)}
        .st-orange i{background:var(--orange-pale);color:var(--orange)}

        /* Grid */
        .grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:20px;
        }

        .grid-2{grid-template-columns:repeat(2,1fr)}

        /* Form Group */
        .fg{
            display:flex;
            flex-direction:column;
            gap:7px;
        }

        .fg.full{grid-column:1/-1}

        .fg label{
            font-size:13px;
            font-weight:600;
            color:var(--text-muted);
            display:flex;
            align-items:center;
            gap:4px;
        }

        .fg label .req{
            color:var(--error);
            font-size:15px;
        }

        /* Inputs */
        .fi{
            width:100%;
            height:48px;
            background:var(--white);
            border:1px solid var(--border);
            border-radius:var(--radius-sm);
            padding:0 16px;
            font-size:14px;
            color:var(--dark);
            font-family:'Inter',sans-serif;
            outline:none;
            transition:all 0.25s ease;
            -webkit-appearance:none;
        }

        .fi:focus{
            border-color:var(--violet);
            box-shadow:0 0 0 4px rgba(124,58,237,0.1);
        }

        .fi.has-error{
            border-color:var(--error) !important;
            box-shadow:0 0 0 4px rgba(220,38,38,0.08) !important;
        }

        .fi::placeholder{color:var(--text-dim)}

        select.fi{
            cursor:pointer;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat:no-repeat;
            background-position:right 14px center;
            padding-right:36px;
        }

        textarea.fi{
            height:100px;
            padding:14px 16px;
            resize:vertical;
        }

        /* Field Error */
        .field-error{
            font-size:12px;
            color:var(--error);
            display:flex;
            align-items:center;
            gap:4px;
            animation:errFade 0.3s ease;
        }

        .field-error i{font-size:11px}

        @keyframes errFade{
            from{opacity:0;transform:translateY(-4px)}
            to{opacity:1;transform:translateY(0)}
        }

        /* ═══════════════════════════════════
           CONDITIONAL SECTIONS
        ═══════════════════════════════════ */
        .conditional{
            transition:all 0.4s ease;
            opacity:1;max-height:600px;overflow:hidden;
        }

        .conditional.hidden{
            opacity:0;max-height:0;
            padding-top:0 !important;
            padding-bottom:0 !important;
            margin:0;border:none !important;
        }

        /* ═══════════════════════════════════
           PHOTO UPLOAD
        ═══════════════════════════════════ */
        .photo-area{
            display:flex;
            align-items:center;
            gap:24px;
        }

        .photo-box{
            width:110px;height:110px;
            border-radius:var(--radius);
            background:var(--border-light);
            border:2px dashed var(--border);
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            flex-shrink:0;
            transition:all 0.3s;
        }

        .photo-box:hover{
            border-color:var(--violet-light);
        }

        .photo-box img{
            width:100%;height:100%;
            object-fit:cover;
        }

        .photo-box i{
            font-size:36px;
            color:var(--text-dim);
        }

        .photo-meta{flex:1}

        .photo-meta p{
            font-size:12px;
            color:var(--text-dim);
            margin-top:8px;
        }

        .upload-trigger{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:11px 20px;
            background:var(--violet-pale);
            border:1px solid rgba(124,58,237,0.15);
            border-radius:var(--radius-sm);
            color:var(--violet);
            font-size:13px;
            font-weight:600;
            cursor:pointer;
            transition:all 0.25s;
        }

        .upload-trigger:hover{
            background:rgba(124,58,237,0.15);
            border-color:var(--violet-light);
            transform:translateY(-1px);
        }

        /* ═══════════════════════════════════
           FORM ACTIONS
        ═══════════════════════════════════ */
        .form-actions{
            display:flex;
            align-items:center;
            justify-content:flex-end;
            gap:14px;
            padding:24px 36px;
            background:var(--border-light);
            border-top:1px solid var(--border);
        }

        .btn{
            padding:13px 28px;
            border:none;
            border-radius:var(--radius-sm);
            font-size:14px;
            font-weight:700;
            font-family:'Inter',sans-serif;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:8px;
            transition:all 0.3s ease;
        }

        .btn-submit{
            background:linear-gradient(135deg,var(--violet),var(--pink));
            color:white;
            box-shadow:0 6px 20px rgba(124,58,237,0.2);
        }

        .btn-submit:hover{
            transform:translateY(-2px);
            box-shadow:0 12px 35px rgba(124,58,237,0.3);
        }

        .btn-reset{
            background:linear-gradient(135deg,#dc2626,#b91c1c);
            color:white;
            box-shadow:0 6px 20px rgba(220,38,38,0.15);
        }

        .btn-reset:hover{
            transform:translateY(-2px);
            box-shadow:0 12px 35px rgba(220,38,38,0.25);
        }

        /* ═══════════════════════════════════
           MOBILE
        ═══════════════════════════════════ */
        .mobile-header{
            display:none;
            position:fixed;
            top:0;left:0;right:0;
            height:60px;
            background:rgba(255,255,255,0.9);
            backdrop-filter:blur(12px);
            border-bottom:1px solid var(--border-light);
            z-index:90;
            padding:0 16px;
            align-items:center;
            justify-content:space-between;
        }

        .mobile-header .brand{
            display:flex;align-items:center;gap:8px;
            font-weight:700;font-size:16px;color:var(--violet);
        }

        .mobile-header .brand img{height:30px}

        .hamburger{
            width:40px;height:40px;border:none;
            background:var(--violet-pale);
            border-radius:10px;
            color:var(--violet);font-size:18px;
            cursor:pointer;
            display:flex;align-items:center;justify-content:center;
        }

        .overlay{
            display:none;position:fixed;inset:0;
            background:rgba(0,0,0,0.3);z-index:95;
        }

        @media(max-width:900px){
            .sidebar{
                transform:translateX(-100%);
                width:260px;
                padding:20px;
                background:rgba(255,255,255,0.95);
                flex-direction:column;
                align-items:stretch;
            }

            .sidebar.open{transform:translateX(0)}
            .sidebar .s-item{
                width:100%;height:auto;
                flex-direction:row;
                justify-content:flex-start;
                gap:12px;
                padding:14px;
                font-size:14px;
            }

            .sidebar .s-item .tooltip{display:none}
            .sidebar .s-logout{width:100%;height:auto;padding:14px;font-size:14px;gap:10px;flex-direction:row}

            .main{margin-left:0;padding:80px 16px 32px}
            .mobile-header{display:flex}
            .overlay.show{display:block}
            .grid,.grid-2{grid-template-columns:1fr}
            .section{padding:24px 20px}
            .form-actions{
                padding:20px;
                flex-direction:column;
            }
            .btn{width:100%;justify-content:center}
            .photo-area{flex-direction:column;align-items:flex-start}
            .top-bar{flex-direction:column;align-items:flex-start;gap:8px}
        }
        /* Show text beside icon */
.s-item {
    flex-direction: row;
    justify-content: flex-start;
    gap: 12px;
    padding: 14px;
    width: 100%;
    height: auto;
    font-size: 14px;
}

/* Text label */
.s-item .label {
    font-size: 15px;
    font-weight: 600;
}

/* Hide tooltip (not needed now) */
.s-item .tooltip {
    display: none;
}
    </style>
</head>
<body>

<!-- Mobile Header -->
<div class="mobile-header">
    <div class="brand">
        <img src="logo.png" alt="AUREON">
        AUREON ERP
    </div>
    <button class="hamburger" onclick="toggleSidebar()">
        <i class="fa-solid fa-bars"></i>
    </button>
</div>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>


<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <!-- ================= LOGO START ================= -->

<div class="sidebar-logo">

    <div class="aureon-logo">
        <span class="logo-letter">A</span>
        <i class="fa-solid fa-graduation-cap logo-cap"></i>
    </div>

    <h2>AUREON ERP</h2>

</div>

<style>

/* ===============================
   AUREON ERP LOGO
================================= */

.sidebar-logo{
    width:100%;
    height:auto;
    margin-bottom:24px;


    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}

/* Logo Box */
.aureon-logo{
    width:82px;
    height:82px;

    border-radius:22px;

    background:
    linear-gradient(
        135deg,
        #ede9fe,
        #fdf2f8
    );

    display:flex;
    align-items:center;
    justify-content:center;

    position:relative;

    box-shadow:
    0 10px 25px rgba(124,58,237,0.12),
    inset 0 1px 0 rgba(255,255,255,0.8);

    border:1px solid rgba(255,255,255,0.9);

    transition:0.35s ease;
}

/* Hover */
.aureon-logo:hover{
    transform:
    translateY(-4px)
    scale(1.04);

    box-shadow:
    0 18px 40px rgba(124,58,237,0.18);
}

/* A Letter */
.logo-letter{
    font-size:52px;
    font-weight:900;

    font-family:'Inter',sans-serif;

    color:#7c3aed;

    line-height:1;

    text-shadow:
    0 4px 10px rgba(124,58,237,0.12);
}

/* Graduation Cap */
.logo-cap{
    position:absolute;

    top:10px;
    right:10px;

    font-size:18px;

    color:#f97316;

    transform:rotate(-15deg);

    filter:
    drop-shadow(0 4px 8px rgba(0,0,0,0.15));
}

/* Text */
.sidebar-logo h2{
    margin-top:14px;

    font-size:18px;
    font-weight:800;

    letter-spacing:0.5px;

    background:
    linear-gradient(
        135deg,
        #7c3aed,
        #ec4899
    );

    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}

</style>

<!-- ================= LOGO END ================= -->
    </div>

<nav class="sidebar-nav">

    <a href="add_student.php" class="s-item active">
        <i class="fa-solid fa-user-plus"></i>
        <span class="label">Add Students</span>
    </a>

    <a href="view_students.php" class="s-item">
        <i class="fa-solid fa-users"></i>
        <span class="label">All Students</span>
    </a>

    <a href="fee_receipt.php" class="s-item">
        <i class="fa-solid fa-indian-rupee-sign"></i>
        <span class="label">Fees</span>
    </a>

</nav>
    <div class="sidebar-bottom">
       <a href="logout.php" class="s-logout">
    <i class="fa-solid fa-right-from-bracket"></i>
    <span class="label">Logout</span>
</a>
    </div>
</aside>


<!-- ═══════════ MAIN ═══════════ -->
<main class="main">

    <div class="top-bar">
        <h1>
            <span class="icon-circle"><i class="fa-solid fa-user-plus"></i></span>
            Add New Student
        </h1>
        <div class="breadcrumb">
            <a href="super_admin.php"><i class="fa-solid fa-house" style="font-size:12px"></i></a>
            <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
            <a href="super_admin.php">Dashboard</a>
            <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
            <span style="color:var(--violet)">Add Student</span>
        </div>
    </div>


    <?php if($success_msg): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <div><?= $success_msg ?></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($errors) && empty($success_msg)): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>Please fix the highlighted errors and try again.</div>
    </div>
    <?php endif; ?>


    <!-- ═══════════ FORM ═══════════ -->
    <form method="POST" enctype="multipart/form-data" id="studentForm" class="form-card">

        <!-- Admission Details -->
        <div class="section">
            <div class="section-title st-violet">
                <i class="fa-solid fa-file-circle-plus"></i> Admission Details
            </div>
            <div class="grid">
                <div class="fg">
                    <label>Admission Type <span class="req">*</span></label>
                    <select name="admission_type" class="fi <?= errClass('admission_type') ?>" id="admissionType" required>
                        <option value="">Select Type</option>
                        <option value="New" <?= old('admission_type')==='New'?'selected':'' ?>>New</option>
                        <option value="Transfer" <?= old('admission_type')==='Transfer'?'selected':'' ?>>Transfer</option>
                    </select>
                    <?= err('admission_type') ?>
                </div>
                <div class="fg">
                    <label>Course <span class="req">*</span></label>
                    <select name="course" class="fi <?= errClass('course') ?>" id="courseSelect" required>
                        <option value="">Select Course</option>
                        <option value="PUC" <?= old('course')==='PUC'?'selected':'' ?>>PUC</option>
                        <option value="BCA" <?= old('course')==='BCA'?'selected':'' ?>>BCA</option>
                        <option value="MCA" <?= old('course')==='MCA'?'selected':'' ?>>MCA</option>
                    </select>
                    <?= err('course') ?>
                </div>
                <div class="fg" id="streamGroup">
                    <label>Stream <span class="req" id="streamReq">*</span></label>
                    <select name="stream" class="fi <?= errClass('stream') ?>" id="streamSelect">
                        <option value="">Select Stream</option>
                        <option value="Science" <?= old('stream')==='Science'?'selected':'' ?>>Science</option>
                        <option value="Commerce" <?= old('stream')==='Commerce'?'selected':'' ?>>Commerce</option>
                    </select>
                    <?= err('stream') ?>
                </div>
                <div class="fg">
                    <label>Year <span class="req">*</span></label>
                    <select name="year" class="fi <?= errClass('year') ?>" id="yearSelect" required>
                        <option value="">Select Year</option>
                        <option value="1" <?= old('year')==='1'?'selected':'' ?>>1st Year</option>
                        <option value="2" <?= old('year')==='2'?'selected':'' ?>>2nd Year</option>
                        <option value="3" <?= old('year')==='3'?'selected':'' ?>>3rd Year</option>
                    </select>
                    <?= err('year') ?>
                </div>
            </div>
        </div>

        <!-- Personal Details -->
        <div class="section">
            <div class="section-title st-pink">
                <i class="fa-solid fa-user"></i> Personal Details
            </div>
            <div class="grid">
                <div class="fg">
                    <label>First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" class="fi <?= errClass('first_name') ?>" placeholder="Enter first name" value="<?= old('first_name') ?>" required>
                    <?= err('first_name') ?>
                </div>
                <div class="fg">
                    <label>Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" class="fi <?= errClass('last_name') ?>" placeholder="Enter last name" value="<?= old('last_name') ?>" required>
                    <?= err('last_name') ?>
                </div>
                <div class="fg">
                    <label>Date of Birth <span class="req">*</span></label>
                    <input type="date" name="dob" class="fi <?= errClass('dob') ?>" value="<?= old('dob') ?>" required>
                    <?= err('dob') ?>
                </div>
                <div class="fg">
                    <label>Gender <span class="req">*</span></label>
                    <select name="gender" class="fi <?= errClass('gender') ?>" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= old('gender')==='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= old('gender')==='Female'?'selected':'' ?>>Female</option>
                        <option value="Other" <?= old('gender')==='Other'?'selected':'' ?>>Other</option>
                    </select>
                    <?= err('gender') ?>
                </div>
                <div class="fg">
                    <label>Religion</label>
                    <input type="text" name="religion" class="fi" placeholder="Enter religion" value="<?= old('religion') ?>">
                </div>
                <div class="fg">
                    <label>Category</label>
                    <select name="category" class="fi">
                        <option value="">Select Category</option>
                        <option value="General" <?= old('category')==='General'?'selected':'' ?>>General</option>
                        <option value="OBC" <?= old('category')==='OBC'?'selected':'' ?>>OBC</option>
                        <option value="SC" <?= old('category')==='SC'?'selected':'' ?>>SC</option>
                        <option value="ST" <?= old('category')==='ST'?'selected':'' ?>>ST</option>
                    </select>
                </div>
                <div class="fg">
                    <label>Blood Group</label>
                    <select name="blood_group" class="fi">
                        <option value="">Select</option>
                        <?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                        <option value="<?=$bg?>" <?= old('blood_group')===$bg?'selected':'' ?>><?=$bg?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

              <div class="fg">
    <label>Password <span class="req">*</span></label>
    <div style="position:relative;">
        <input type="password" name="password" id="password"
       onkeyup="checkPassword(); checkMatch();"
       class="fi <?= errClass('password') ?>" required>

        <i class="fa-solid fa-eye"
           onclick="togglePassword('password', this)"
           style="position:absolute; right:14px; top:50%; transform:translateY(-50%); cursor:pointer;">
        </i>
    </div>
    <?= err('password') ?>
</div>

<div class="fg">
    <label>Confirm Password <span class="req">*</span></label>
    <div style="position:relative;">
       <input type="password" name="confirm_password" id="confirm_password"
       onkeyup="checkMatch();"
       class="fi <?= errClass('confirm_password') ?>" required>

        <i class="fa-solid fa-eye"
           onclick="togglePassword('confirm_password', this)"
           style="position:absolute; right:14px; top:50%; transform:translateY(-50%); cursor:pointer;">
        </i>
    </div>
    <?= err('confirm_password') ?>
</div>
<p id="matchText" style="font-size:12px; margin-top:6px;"></p>
<div style="font-size:12px; margin-top:6px;">
    <p id="length" style="color:red;">• Minimum 10 characters</p>
    <p id="upper" style="color:red;">• At least 1 uppercase letter</p>
    <p id="number" style="color:red;">• At least 1 number</p>
</div>
            </div>
        </div>

        <!-- Contact Details -->
        <div class="section">
            <div class="section-title st-orange">
                <i class="fa-solid fa-address-book"></i> Contact Details
            </div>
            <div class="grid">
                <div class="fg">
                    <label>Phone <span class="req">*</span></label>
                    <input type="tel" name="phone" class="fi phone-input <?= errClass('phone') ?>" placeholder="10-digit number" maxlength="10" value="<?= old('phone') ?>" required>
                    <?= err('phone') ?>
                </div>
                <div class="fg">
                    <label>Email</label>
                    <input type="email" name="email" class="fi <?= errClass('email') ?>" placeholder="student@email.com" value="<?= old('email') ?>">
                    <?= err('email') ?>
                </div>
                <div class="fg">
                    <label>Guardian Phone <span class="req">*</span></label>
                    <input type="tel" name="guardian_phone" class="fi phone-input <?= errClass('guardian_phone') ?>" placeholder="10-digit number" maxlength="10" value="<?= old('guardian_phone') ?>" required>
                    <?= err('guardian_phone') ?>
                </div>
                <div class="fg full">
                    <label>Address <span class="req">*</span></label>
                    <textarea name="address" class="fi <?= errClass('address') ?>" placeholder="Enter full address" required><?= old('address') ?></textarea>
                    <?= err('address') ?>
                </div>
            </div>
        </div>

        <!-- Family Details -->
        <div class="section">
            <div class="section-title st-violet">
                <i class="fa-solid fa-people-roof"></i> Family Details
            </div>
            <div class="grid-2">
                <div class="fg">
                    <label>Father's Name <span class="req">*</span></label>
                    <input type="text" name="father_name" class="fi <?= errClass('father_name') ?>" placeholder="Enter father's name" value="<?= old('father_name') ?>" required>
                    <?= err('father_name') ?>
                </div>
                <div class="fg">
                    <label>Mother's Name <span class="req">*</span></label>
                    <input type="text" name="mother_name" class="fi <?= errClass('mother_name') ?>" placeholder="Enter mother's name" value="<?= old('mother_name') ?>" required>
                    <?= err('mother_name') ?>
                </div>
            </div>
        </div>

        <!-- Open Elective (BCA / MCA only) -->
        <div class="section conditional hidden" id="electiveSection">
            <div class="section-title st-pink">
                <i class="fa-solid fa-book-open"></i> Open Elective
            </div>
            <div class="grid">
                <div class="fg">
                    <label>Open Elective</label>
                    <select name="open_elective" class="fi" id="electiveSelect">
                        <option value="">Select Elective</option>
                        <option value="Kannada" <?= old('open_elective')==='Kannada'?'selected':'' ?>>Kannada</option>
                        <option value="Hindi" <?= old('open_elective')==='Hindi'?'selected':'' ?>>Hindi</option>
                        <option value="Sanskrit" <?= old('open_elective')==='Sanskrit'?'selected':'' ?>>Sanskrit</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Transfer Details -->
        <div class="section conditional hidden" id="transferSection">
            <div class="section-title st-orange">
                <i class="fa-solid fa-right-left"></i> Previous Education
            </div>
            <div class="grid">
                <div class="fg">
                    <label>Previous College</label>
                    <input type="text" name="previous_college" class="fi" placeholder="Enter previous college" value="<?= old('previous_college') ?>">
                </div>
                <div class="fg">
                    <label>Previous Course</label>
                    <input type="text" name="previous_course" class="fi" placeholder="Enter previous course" value="<?= old('previous_course') ?>">
                </div>
                <div class="fg full">
                    <label>Transfer Reason</label>
                    <textarea name="transfer_reason" class="fi" placeholder="Reason for transfer"><?= old('transfer_reason') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Photo Upload -->
        <div class="section">
            <div class="section-title st-violet">
                <i class="fa-solid fa-camera"></i> Upload Photo
            </div>
            <div class="photo-area">
                <div class="photo-box" id="photoPreview">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="photo-meta">
                    <label class="upload-trigger" for="photoInput">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Choose Photo
                    </label>
                    <input type="file" name="photo" id="photoInput" accept=".jpg,.jpeg,.png,.webp" hidden>
                    <p>JPG, JPEG, PNG or WEBP • Max 2MB</p>
                    <?= err('photo') ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="button" class="btn btn-reset" onclick="resetForm()">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </button>
            <button type="submit" class="btn btn-submit">
                <i class="fa-solid fa-plus"></i> Add Student
            </button>
        </div>

    </form>

</main>


<script>
    // Sidebar toggle
    function toggleSidebar(){
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }

    // Elements
    const admissionType   = document.getElementById('admissionType');
    const courseSelect    = document.getElementById('courseSelect');
    const streamGroup    = document.getElementById('streamGroup');
    const streamSelect   = document.getElementById('streamSelect');
    const streamReq      = document.getElementById('streamReq');
    const yearSelect     = document.getElementById('yearSelect');
    const yearOpts       = yearSelect.querySelectorAll('option');
    const electiveSection= document.getElementById('electiveSection');
    const transferSection= document.getElementById('transferSection');

    // Course logic
    function updateCourse(){
        const course = courseSelect.value;

        // Stream: PUC only
        if(course === 'PUC'){
            streamGroup.style.display = 'flex';
            streamSelect.required = true;
            streamReq.style.display = 'inline';
        } else {
            streamGroup.style.display = 'none';
            streamSelect.required = false;
            streamSelect.value = '';
            streamReq.style.display = 'none';
        }

        // Year options
        const yearMap = { 'PUC': [1,2], 'BCA': [1,2,3], 'MCA': [1,2] };
        const allowed = yearMap[course] || [1,2,3];

        yearOpts.forEach(opt => {
            if(opt.value === '') return;
            const val = parseInt(opt.value);
            opt.disabled = !allowed.includes(val);
            opt.style.display = allowed.includes(val) ? '' : 'none';
            if(opt.disabled && opt.selected) yearSelect.value = '';
        });

        // Elective: BCA/MCA only
        if(course === 'BCA' || course === 'MCA'){
            electiveSection.classList.remove('hidden');
        } else {
            electiveSection.classList.add('hidden');
            document.getElementById('electiveSelect').value = '';
        }
    }

    courseSelect.addEventListener('change', updateCourse);

    // Admission type
    admissionType.addEventListener('change', function(){
        transferSection.classList.toggle('hidden', this.value !== 'Transfer');
    });

    // Photo preview
    document.getElementById('photoInput').addEventListener('change', function(e){
        const file = e.target.files[0];
        if(!file) return;

        if(file.size > 2 * 1024 * 1024){
            alert('Photo must be less than 2MB');
            this.value = '';
            return;
        }

        const ok = ['image/jpeg','image/jpg','image/png','image/webp'];
        if(!ok.includes(file.type)){
            alert('Only JPG, JPEG, PNG or WEBP allowed');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = ev => {
            document.getElementById('photoPreview').innerHTML =
                '<img src="' + ev.target.result + '">';
        };
        reader.readAsDataURL(file);
    });

    // Phone inputs
    document.querySelectorAll('.phone-input').forEach(input => {
        input.addEventListener('input', function(){
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    });

    // Reset
    function resetForm(){
        if(!confirm('Reset all fields?')) return;
        document.getElementById('studentForm').reset();
        document.getElementById('photoPreview').innerHTML = '<i class="fa-solid fa-user"></i>';
        transferSection.classList.add('hidden');
        electiveSection.classList.add('hidden');
        document.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
        document.querySelectorAll('.field-error').forEach(el => el.remove());
        updateCourse();
    }

    // Scroll to alert
    const alertEl = document.querySelector('.alert');
    if(alertEl) alertEl.scrollIntoView({behavior:'smooth', block:'center'});

    // Init
    updateCourse();
    if(admissionType.value === 'Transfer') transferSection.classList.remove('hidden');

    // 👁 Show / Hide Password
function togglePassword(id, icon) {
    let input = document.getElementById(id);

    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    }
}

// 🔒 Password validation (LIVE)
function checkPassword() {
    let pass = document.getElementById("password").value;

    let length = document.getElementById("length");
    let upper = document.getElementById("upper");
    let number = document.getElementById("number");

    // 10 characters
    if (pass.length >= 10) {
        length.style.color = "green";
    } else {
        length.style.color = "red";
    }

    // uppercase
    if (/[A-Z]/.test(pass)) {
        upper.style.color = "green";
    } else {
        upper.style.color = "red";
    }

    // number
    if (/[0-9]/.test(pass)) {
        number.style.color = "green";
    } else {
        number.style.color = "red";
    }
}

// ✅ Confirm password match
function checkMatch() {
    let pass = document.getElementById("password").value;
    let confirm = document.getElementById("confirm_password").value;
    let text = document.getElementById("matchText");

    if (confirm === "") {
        text.innerHTML = "";
    }
    else if (pass === confirm) {
        text.innerHTML = "✔ Passwords match";
        text.style.color = "green";
    } else {
        text.innerHTML = "❌ Passwords do not match";
        text.style.color = "red";
    }
}
</script>

</body>
</html>