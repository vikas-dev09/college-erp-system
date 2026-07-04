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

$pdo->exec("CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` VARCHAR(20) UNIQUE NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `subjects` TEXT NOT NULL,
    `dob` DATE NOT NULL,
    `gender` ENUM('Male','Female','Other') NOT NULL,
    `religion` VARCHAR(50) DEFAULT NULL,
    `category` VARCHAR(50) DEFAULT NULL,
    `blood_group` VARCHAR(10) DEFAULT NULL,
    `phone` VARCHAR(15) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `address` TEXT DEFAULT NULL,
    `qualification` VARCHAR(200) NOT NULL,
    `specialization` VARCHAR(200) DEFAULT NULL,
    `experience` INT DEFAULT 0,
    `previous_institution` VARCHAR(200) DEFAULT NULL,
    `photo` VARCHAR(255) DEFAULT NULL,
    `resume` VARCHAR(255) DEFAULT NULL,
    `is_admin` ENUM('Yes','No') DEFAULT 'No',
    `password` VARCHAR(255) NOT NULL,
    `status` ENUM('Active','Inactive') DEFAULT 'Active',
    `joining_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$success_msg = '';
$errors = [];
$old = [];

$admin_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin';
$initials = strtoupper(substr($admin_name, 0, 1) . substr(strrchr($admin_name, ' ') ?: $admin_name, 1, 1));

function generateTeacherId($pdo, $department, $dob) {
    $dept_codes = [
        'Science' => 'SC',
        'Commerce' => 'CM',
        'Computer Science' => 'BCA',
        'Mathematics' => 'MCA'
    ];
    $dept_code = $dept_codes[$department] ?? 'OT';
    $year = date('y', strtotime($dob));
    $prefix = 'TCH' . $dept_code . $year;

    $stmt = $pdo->query("SELECT teacher_id FROM teachers WHERE teacher_id LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $num = $last ? intval(substr($last, strlen($prefix))) + 1 : 1;
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

$auto_teacher_id = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $department     = trim($_POST['department'] ?? '');
    $dob            = trim($_POST['dob'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $designation    = trim($_POST['designation'] ?? '');
    $subjects       = isset($_POST['subjects']) ? implode(',', $_POST['subjects']) : '';
    $gender         = trim($_POST['gender'] ?? '');
    $religion       = trim($_POST['religion'] ?? '');
    $category       = trim($_POST['category'] ?? '');
    $blood_group    = trim($_POST['blood_group'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $qualification  = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experience     = intval($_POST['experience'] ?? 0);
    $previous_inst  = trim($_POST['previous_institution'] ?? '');
    $is_admin       = trim($_POST['is_admin'] ?? 'No');
    $password       = trim($_POST['password'] ?? '');
    $confirm_pass   = trim($_POST['confirm_password'] ?? '');
    $status         = trim($_POST['status'] ?? 'Active');
    $joining_date   = trim($_POST['joining_date'] ?? '');

    // DOB validation
    if(!empty($dob)) {
        $age = date_diff(date_create($dob), date_create('now'))->y;
        if($age < 20) $errors['dob'] = 'Teacher must be at least 20 years old';
        if(strtotime($dob) > strtotime('now')) $errors['dob'] = 'Date of Birth cannot be future';
    }

    if(empty($first_name))    $errors['first_name'] = 'First Name is required';
    if(empty($last_name))     $errors['last_name'] = 'Last Name is required';
    if(empty($designation))   $errors['designation'] = 'Designation is required';
    if(empty($department))    $errors['department'] = 'Department is required';
    if(empty($subjects))      $errors['subjects'] = 'Select at least one subject';
    if(empty($dob))           $errors['dob'] = 'Date of Birth is required';
    if(empty($gender))        $errors['gender'] = 'Gender is required';
    if(empty($phone))         $errors['phone'] = 'Phone is required';
    elseif(!preg_match('/^\d{10}$/', $phone)) $errors['phone'] = 'Phone must be 10 digits';
    if(empty($email))         $errors['email'] = 'Email is required';
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email format';
    if(empty($qualification)) $errors['qualification'] = 'Qualification is required';
    if(empty($password))      $errors['password'] = 'Password is required';
    elseif(strlen($password) < 8) $errors['password'] = 'Minimum 8 characters';
    if($password !== $confirm_pass) $errors['confirm_password'] = 'Passwords do not match';
    if(empty($joining_date))  $errors['joining_date'] = 'Joining Date is required';

    if(empty($errors['phone'])) {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE phone = ?");
        $stmt->execute([$phone]);
        if($stmt->fetch()) $errors['phone'] = 'This phone number is already registered';
    }

    if(empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->fetch()) $errors['email'] = 'This email is already registered';
    }

    $photo_name = null;
    if(!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if(!in_array($ext, $allowed)) $errors['photo'] = 'Only JPG, JPEG, PNG or WEBP';
        elseif($_FILES['photo']['size'] > 2 * 1024 * 1024) $errors['photo'] = 'Max 2MB';
    }

    $resume_name = null;
    if(!empty($_FILES['resume']['name'])) {
        $rext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        $rallowed = ['pdf','doc','docx'];
        if(!in_array($rext, $rallowed)) $errors['resume'] = 'Only PDF or DOC/DOCX';
        elseif($_FILES['resume']['size'] > 5 * 1024 * 1024) $errors['resume'] = 'Max 5MB';
    }

    if(empty($errors)) {
        $auto_teacher_id = generateTeacherId($pdo, $department, $dob);

        if(!is_dir('uploads/teachers')) mkdir('uploads/teachers', 0755, true);

        if(!empty($_FILES['photo']['name'])) {
            $photo_name = $auto_teacher_id . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/teachers/' . $photo_name);
        }

        if(!empty($_FILES['resume']['name'])) {
            $resume_name = $auto_teacher_id . '_resume.' . $rext;
            move_uploaded_file($_FILES['resume']['tmp_name'], 'uploads/teachers/' . $resume_name);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $full_name = $first_name . ' ' . $last_name;

        $stmt = $pdo->prepare("INSERT INTO teachers
            (teacher_id, first_name, last_name, designation, department, subjects, dob, gender,
             religion, category, blood_group, phone, email, address,
             qualification, specialization, experience, previous_institution,
             photo, resume, is_admin, password, status, joining_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->execute([
            $auto_teacher_id, $first_name, $last_name, $designation, $department, $subjects,
            $dob, $gender, $religion ?: null, $category ?: null, $blood_group ?: null,
            $phone, $email, $address ?: null, $qualification,
            $specialization ?: null, $experience, $previous_inst ?: null,
            $photo_name, $resume_name, $is_admin, $hashed_password, $status, $joining_date
        ]);

        $role = ($is_admin === 'Yes') ? 'admin' : 'teacher';
        $stmt2 = $pdo->prepare("INSERT INTO users (role, full_name, email, password, status) VALUES (?, ?, ?, ?, 'Active')");
        $stmt2->execute([$role, $full_name, $email, $hashed_password]);

        $success_msg = "Teacher registered successfully!<br>
                        <strong>Teacher ID:</strong> {$auto_teacher_id}<br>
                        <strong>Name:</strong> {$full_name}<br>
                        <strong>Email:</strong> {$email}";
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

function isChecked($subject) {
    global $old;
    return isset($old['subjects']) && in_array($subject, $old['subjects']) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Teacher | AUREON ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}

        :root{
            --violet:#7c3aed;--violet-dark:#6d28d9;--violet-light:#a78bfa;
            --violet-pale:#ede9fe;--violet-glow:rgba(124,58,237,0.12);
            --orange:#f97316;--orange-pale:#fff7ed;
            --pink:#ec4899;--pink-pale:#fdf2f8;
            --teal:#14b8a6;--teal-pale:#f0fdfa;
            --blue:#3b82f6;--blue-pale:#eff6ff;
            --green:#10b981;--green-pale:#ecfdf5;
            --red:#ef4444;--red-pale:#fef2f2;
            --dark:#1f1635;--text:#334155;--text-muted:#64748b;--text-dim:#94a3b8;
            --border:#e2e8f0;--border-light:#f1f5f9;--white:#ffffff;
            --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);
            --card-shadow:0 10px 30px rgba(0,0,0,0.06);
            --radius:20px;--radius-md:16px;--radius-sm:12px;--radius-xs:8px;
        }

        html{scroll-behavior:smooth}

        body{
            min-height:100vh;
            font-family:'Inter','Segoe UI',sans-serif;
            background:var(--bg);
            color:var(--text);
            display:flex;
            font-size:15px;
        }

        /* ═══════════ SIDEBAR ═══════════ */
        .sidebar{
            width:120px;
            background:rgba(255,255,255,0.6);
            backdrop-filter:blur(16px);
            border-right:1px solid rgba(255,255,255,0.8);
            display:flex;flex-direction:column;align-items:center;
            position:fixed;top:0;bottom:0;z-index:100;
            padding:18px 0;transition:all 0.3s;
        }

        .sidebar-logo{width:36px;height:36px;margin-bottom:22px}
        .sidebar-logo img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 3px 8px rgba(124,58,237,0.2))}

        .sidebar-nav{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;width:100%;padding:0 10px}

        .s-item{
            width:52px;height:52px;border-radius:14px;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            gap:2px;font-size:9px;font-weight:600;
            color:var(--text-muted);text-decoration:none;
            transition:all 0.25s;position:relative;
        }

        .s-item i{font-size:18px;transition:all 0.25s}
        .s-item:hover{background:var(--violet-pale);color:var(--violet);transform:scale(1.08)}

        .s-item.active{
            background:linear-gradient(135deg,var(--violet),var(--pink));
            color:white;box-shadow:0 8px 24px rgba(124,58,237,0.3);
        }

        .s-item .tip{
            position:absolute;left:66px;top:50%;transform:translateY(-50%);
            background:var(--dark);color:white;padding:6px 12px;
            border-radius:8px;font-size:12px;font-weight:600;
            white-space:nowrap;opacity:0;pointer-events:none;
            transition:all 0.2s;z-index:200;
        }

        .s-item .tip::before{content:'';position:absolute;left:-4px;top:50%;transform:translateY(-50%);border:4px solid transparent;border-right-color:var(--dark)}
        .s-item:hover .tip{opacity:1;left:70px}

        .sidebar-bottom{padding:10px}

        .s-logout{
            width:46px;height:46px;border-radius:12px;border:none;
            background:var(--red-pale);color:var(--red);font-size:17px;
            cursor:pointer;transition:all 0.25s;
            display:flex;align-items:center;justify-content:center;
        }

        .s-logout:hover{background:rgba(239,68,68,0.15);transform:scale(1.08)}

        /* ═══════════ MAIN ═══════════ */
        .main{margin-left:130px;flex:1;min-height:100vh}
        .sidebar-logo h2{
    white-space:nowrap;
    font-size:16px;
    text-align:center;
}

        .top-header{
            position:sticky;top:0;z-index:50;
            background:rgba(255,255,255,0.75);
            backdrop-filter:blur(14px);
            border-bottom:1px solid var(--border-light);
            padding:14px 36px;
            display:flex;align-items:center;justify-content:space-between;
        }

        .header-brand{display:flex;align-items:center;gap:12px}

        .header-icon{
            width:38px;height:38px;border-radius:12px;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            display:flex;align-items:center;justify-content:center;
            color:white;font-size:15px;
        }

        .header-title{font-size:17px;font-weight:700;color:var(--dark)}
        .header-title span{color:var(--text-muted);font-weight:500;margin-left:6px;font-size:14px}

        .header-profile{display:flex;align-items:center;gap:12px}

        .profile-avatar{
            width:40px;height:40px;border-radius:12px;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            color:white;display:flex;align-items:center;justify-content:center;
            font-size:15px;font-weight:700;
        }

        .profile-info{text-align:right}
        .profile-info .name{font-size:15px;font-weight:600;color:var(--dark)}
        .profile-info .role{font-size:12px;color:var(--text-muted);font-weight:500}

        .page-content{padding:32px 36px}

        /* Page Title */
        .page-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}

        .page-title-left{display:flex;align-items:center;gap:14px}

        .page-title-icon{
            width:50px;height:50px;border-radius:15px;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            display:flex;align-items:center;justify-content:center;
            color:white;font-size:22px;
            box-shadow:0 8px 20px rgba(124,58,237,0.2);
        }

        .page-title h1{font-size:26px;font-weight:800;color:var(--dark)}
        .page-title p{font-size:14px;color:var(--text-muted);margin-top:2px}

        .breadcrumb{font-size:14px;color:var(--text-dim);display:flex;align-items:center;gap:6px}
        .breadcrumb a{color:var(--text-muted);text-decoration:none}
        .breadcrumb a:hover{color:var(--violet)}

        /* ═══════════ ALERTS ═══════════ */
        .alert{
            padding:20px 24px;border-radius:var(--radius-sm);
            margin-bottom:24px;font-size:15px;font-weight:500;
            display:flex;align-items:flex-start;gap:12px;
            animation:slideDown 0.4s ease;
        }

        .alert i{font-size:22px;margin-top:1px;flex-shrink:0}

        .alert-success{background:var(--green-pale);border:1px solid rgba(16,185,129,0.15);color:var(--green)}
        .alert-error{background:var(--red-pale);border:1px solid rgba(239,68,68,0.15);color:var(--red)}

        .success-actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}

        .success-actions a{
            padding:9px 18px;border-radius:8px;font-size:14px;
            font-weight:600;text-decoration:none;transition:all 0.2s;
        }

        .sa-primary{background:var(--violet-pale);color:var(--violet)}
        .sa-primary:hover{background:rgba(124,58,237,0.15)}
        .sa-secondary{background:var(--border-light);color:var(--text)}
        .sa-secondary:hover{background:var(--border)}

        @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

        /* ═══════════ FORM CARD ═══════════ */
        .form-card{background:var(--white);border-radius:var(--radius);box-shadow:var(--card-shadow);overflow:hidden}

        .section{padding:30px 36px;border-bottom:1px solid var(--border-light)}
        .section:last-of-type{border-bottom:none}

        .section-title{
            font-size:17px;font-weight:700;color:var(--dark);
            margin-bottom:24px;display:flex;align-items:center;gap:10px;
        }

        .st-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px}

        .st-violet .st-icon{background:var(--violet-pale);color:var(--violet)}
        .st-pink .st-icon{background:var(--pink-pale);color:var(--pink)}
        .st-blue .st-icon{background:var(--blue-pale);color:var(--blue)}
        .st-orange .st-icon{background:var(--orange-pale);color:var(--orange)}
        .st-teal .st-icon{background:var(--teal-pale);color:var(--teal)}
        .st-green .st-icon{background:var(--green-pale);color:var(--green)}

        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
        .grid-2{grid-template-columns:repeat(2,1fr)}

        .fg{display:flex;flex-direction:column;gap:8px}
        .fg.full{grid-column:1/-1}

        .fg label{font-size:14px;font-weight:600;color:var(--text-muted);display:flex;align-items:center;gap:4px}
        .fg label .req{color:var(--red);font-size:16px}

        .fi{
            width:100%;height:52px;background:var(--white);
            border:1px solid var(--border);border-radius:var(--radius-sm);
            padding:0 18px;font-size:15px;color:var(--dark);
            font-family:'Inter',sans-serif;outline:none;
            transition:all 0.25s;-webkit-appearance:none;
        }

        .fi:focus{border-color:var(--violet);box-shadow:0 0 0 4px rgba(124,58,237,0.1)}

        .fi.has-error{border-color:var(--red) !important;box-shadow:0 0 0 4px rgba(239,68,68,0.08) !important}

        .fi::placeholder{color:var(--text-dim)}

        .fi.readonly{
            background:var(--border-light);
            color:var(--violet);
            font-weight:700;
            font-size:16px;
            letter-spacing:1px;
            cursor:not-allowed;
        }

        select.fi{
            cursor:pointer;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 16px center;padding-right:40px;
        }

        textarea.fi{height:110px;padding:16px 18px;resize:vertical}

        .field-error{
            font-size:13px;color:var(--red);
            display:flex;align-items:center;gap:4px;
            animation:errFade 0.3s ease;
        }

        .field-error i{font-size:12px}
        @keyframes errFade{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

        .id-badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--violet);margin-top:2px}
        .id-badge i{font-size:11px}

        /* Subjects Checkboxes */
        .subjects-grid{
            display:grid;grid-template-columns:repeat(4,1fr);gap:12px;
            margin-top:8px;
        }

        .subject-item{
            display:flex;align-items:center;gap:6px;
            font-size:14px;color:var(--text-muted);
        }

        .subject-item input{width:16px;height:16px;accent-color:var(--violet)}

        /* Password strength */
        .pass-strength{height:5px;border-radius:3px;background:var(--border-light);margin-top:4px;overflow:hidden}
        .pass-strength-bar{height:100%;border-radius:3px;width:0;transition:all 0.3s}
        .pass-hint{font-size:12px;color:var(--text-dim);margin-top:3px}

        /* ═══════════ UPLOADS ═══════════ */
        .upload-area{display:flex;align-items:center;gap:24px}

        .upload-preview{
            width:100px;height:100px;border-radius:var(--radius-md);
            background:var(--border-light);border:2px dashed var(--border);
            display:flex;align-items:center;justify-content:center;
            overflow:hidden;flex-shrink:0;transition:all 0.3s;
        }

        .upload-preview:hover{border-color:var(--violet-light)}
        .upload-preview img{width:100%;height:100%;object-fit:cover}
        .upload-preview i{font-size:32px;color:var(--text-dim)}

        .upload-meta{flex:1}
        .upload-meta p{font-size:13px;color:var(--text-dim);margin-top:8px}

        .upload-btn{
            display:inline-flex;align-items:center;gap:8px;
            padding:11px 20px;background:var(--violet-pale);
            border:1px solid rgba(124,58,237,0.15);border-radius:var(--radius-sm);
            color:var(--violet);font-size:14px;font-weight:600;
            cursor:pointer;transition:all 0.25s;
        }

        .upload-btn:hover{background:rgba(124,58,237,0.15);border-color:var(--violet-light);transform:translateY(-1px)}

        .file-name{font-size:13px;color:var(--text-muted);margin-top:6px;display:flex;align-items:center;gap:4px}
        .file-name i{color:var(--green)}

        /* ═══════════ FORM ACTIONS ═══════════ */
        .form-actions{
            display:flex;align-items:center;justify-content:flex-end;
            gap:14px;padding:24px 36px;
            background:var(--border-light);border-top:1px solid var(--border);
        }

        .btn{
            padding:14px 30px;border:none;border-radius:var(--radius-sm);
            font-size:15px;font-weight:700;font-family:'Inter',sans-serif;
            cursor:pointer;display:inline-flex;align-items:center;gap:8px;
            transition:all 0.3s ease;text-decoration:none;
        }

        .btn-submit{
            background:linear-gradient(135deg,var(--violet),var(--pink));
            color:white;box-shadow:0 6px 20px rgba(124,58,237,0.2);
        }

        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 12px 35px rgba(124,58,237,0.3)}

        .btn-reset{
            background:linear-gradient(135deg,#dc2626,#b91c1c);
            color:white;box-shadow:0 6px 20px rgba(220,38,38,0.15);
        }

        .btn-reset:hover{transform:translateY(-2px);box-shadow:0 12px 35px rgba(220,38,38,0.25)}

        .btn-cancel{background:var(--border-light);color:var(--text-muted);border:1px solid var(--border)}
        .btn-cancel:hover{background:var(--border);color:var(--text)}

        /* ═══════════ MOBILE ═══════════ */
        .mobile-header{
            display:none;position:fixed;top:0;left:0;right:0;
            height:58px;background:rgba(255,255,255,0.9);
            backdrop-filter:blur(12px);border-bottom:1px solid var(--border-light);
            z-index:90;padding:0 16px;
            align-items:center;justify-content:space-between;
        }

        .mobile-header .mb{display:flex;align-items:center;gap:8px;font-weight:700;font-size:16px;color:var(--violet)}
        .mobile-header .mb img{height:26px}

        .hamburger{
            width:40px;height:40px;border:none;background:var(--violet-pale);
            border-radius:10px;color:var(--violet);font-size:18px;
            cursor:pointer;display:flex;align-items:center;justify-content:center;
        }

        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.25);z-index:95}

        @media(max-width:1100px){.grid{grid-template-columns:repeat(2,1fr)}}

        @media(max-width:900px){
            .sidebar{transform:translateX(-100%);width:240px;padding:20px;background:rgba(255,255,255,0.95);align-items:stretch}
            .sidebar.open{transform:translateX(0)}
            .sidebar .s-item{width:100%;height:auto;flex-direction:row;justify-content:flex-start;gap:12px;padding:13px 14px;font-size:14px}
            .sidebar .s-item .tip{display:none}
            .sidebar .s-logout{width:100%;height:auto;padding:13px;font-size:14px}
            .main{margin-left:0}
            .mobile-header{display:flex}
            .overlay.show{display:block}
            .page-content{padding:80px 16px 32px}
            .top-header{display:none}
            .grid,.grid-2{grid-template-columns:1fr}
            .section{padding:24px 20px}
            .form-actions{padding:20px;flex-direction:column}
            .btn{width:100%;justify-content:center}
            .upload-area{flex-direction:column;align-items:flex-start}
            .page-title{flex-direction:column;align-items:flex-start;gap:12px}
            .subjects-grid{grid-template-columns:repeat(2,1fr)}
        }
        /* AUREON ERP LOGO */

.sidebar-logo{
    width:100%;
    padding:0 18px;
    margin-bottom:24px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}

.aureon-logo{
    width:82px;
    height:82px;
    border-radius:22px;
    background:linear-gradient(135deg,#ede9fe,#fdf2f8);
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

.aureon-logo:hover{
    transform:translateY(-4px) scale(1.04);
    box-shadow:0 18px 40px rgba(124,58,237,0.18);
}

.logo-letter{
    width:100%;
    height:100%;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:42px;
    font-weight:900;
    font-family:'Inter',sans-serif;

    color:#7c3aed;
    line-height:1;

    text-shadow:0 4px 10px rgba(124,58,237,0.12);
}
.logo-cap{
    position:absolute;
    top:8px;
    right:8px;
    font-size:14px;
    color:#f97316;
    transform:rotate(-15deg);
    filter:drop-shadow(0 4px 8px rgba(0,0,0,0.15));
}

.sidebar-logo h2{
    margin-top:14px;
    font-size:18px;
    font-weight:800;
    letter-spacing:0.5px;
    background:linear-gradient(135deg,#7c3aed,#ec4899);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}
    </style>
</head>
<body>

<div class="mobile-header">
    <div class="mb"><img src="logo.png" alt="AUREON"> AUREON ERP</div>
    <button class="hamburger" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
</div>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-logo">
        <div class="aureon-logo">
            <span class="logo-letter">A</span>
            <i class="fa-solid fa-graduation-cap logo-cap"></i>
        </div>

        <h2>AUREON ERP</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="super_admin.php" class="s-item"><i class="fa-solid fa-grid-2"></i><span class="tip">Dashboard</span></a>
        <a href="add_student.php" class="s-item"><i class="fa-solid fa-user-plus"></i><span class="tip">Students</span></a>
        <a href="add_teacher.php" class="s-item active"><i class="fa-solid fa-chalkboard-user"></i><span class="tip">Teachers</span></a>
        <a href="fee_receipt.php" class="s-item"><i class="fa-solid fa-indian-rupee-sign"></i><span class="tip">Fees</span></a>
        <a href="libarary.php" class="s-item"><i class="fa-solid fa-book"></i><span class="tip">Library</span></a>
        <a href="gallary_admin.php" class="s-item"><i class="fa-solid fa-images"></i><span class="tip">Gallery</span></a>
    </nav>
    <div class="sidebar-bottom">
        <button class="s-logout" onclick="if(confirm('Logout?'))location='logout.php'" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </button>
    </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main class="main">

    <div class="top-header">
        <div class="header-brand">
            <div class="header-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="header-title">AUREON ERP <span>| SUPER ADMIN PANEL</span></div>
        </div>
        <div class="header-profile">
            <div class="profile-info">
                <div class="name"><?= htmlspecialchars($admin_name) ?></div>
                <div class="role">Super Admin</div>
            </div>
            <div class="profile-avatar"><?= $initials ?></div>
        </div>
    </div>

    <div class="page-content">

        <div class="page-title">
            <div class="page-title-left">
                <div class="page-title-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div>
                    <h1>Add Teacher</h1>
                    <p>Register new faculty member</p>
                </div>
            </div>
            <div class="breadcrumb">
                <a href="super_admin.php"><i class="fa-solid fa-house" style="font-size:12px"></i></a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <a href="super_admin.php">Dashboard</a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <span style="color:var(--violet)">Add Teacher</span>
            </div>
        </div>

        <?php if($success_msg): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <?= $success_msg ?>
                <div class="success-actions">
                    <a href="add_teacher.php" class="sa-primary"><i class="fa-solid fa-plus"></i> Add Another</a>
                    <a href="#" class="sa-secondary"><i class="fa-solid fa-list"></i> View Teachers</a>
                    <a href="super_admin.php" class="sa-secondary"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($errors) && empty($success_msg)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>Please fix the highlighted errors and try again.</div>
        </div>
        <?php endif; ?>


        <form method="POST" enctype="multipart/form-data" id="teacherForm" class="form-card">

            <!-- Basic Info -->
            <div class="section">
                <div class="section-title st-violet">
                    <span class="st-icon"><i class="fa-solid fa-user"></i></span> Basic Information
                </div>
                <div class="grid">
                    <div class="fg" id="teacherIdGroup">
                        <label>Teacher ID</label>
                        <input type="text" name="teacher_id" class="fi readonly" id="teacherId" value="<?= $auto_teacher_id ?>" readonly>
                        <span class="id-badge"><i class="fa-solid fa-bolt"></i> Auto-generated based on department and DOB</span>
                    </div>
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
                        <input type="date" name="dob" class="fi <?= errClass('dob') ?>" id="dobInput" value="<?= old('dob') ?>" required>
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
                        <select name="religion" class="fi">
                            <option value="">Select</option>
                            <?php foreach(['Hindu','Muslim','Christian','Sikh','Buddhist','Jain','Other'] as $r): ?>
                            <option value="<?=$r?>" <?= old('religion')===$r?'selected':'' ?>><?=$r?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Category</label>
                        <select name="category" class="fi">
                            <option value="">Select</option>
                            <?php foreach(['General','OBC','SC','ST','EWS','Other'] as $c): ?>
                            <option value="<?=$c?>" <?= old('category')===$c?'selected':'' ?>><?=$c?></option>
                            <?php endforeach; ?>
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
                </div>
            </div>

            <!-- Professional -->
            <div class="section">
                <div class="section-title st-blue">
                    <span class="st-icon"><i class="fa-solid fa-briefcase"></i></span> Professional Details
                </div>
                <div class="grid">
                    <div class="fg">
                        <label>Designation <span class="req">*</span></label>
                        <select name="designation" class="fi <?= errClass('designation') ?>" required>
                            <option value="">Select</option>
                            <?php foreach(['Professor','Associate Professor','Assistant Professor','Lecturer','HOD','Assistant Professor'] as $d): ?>
                            <option value="<?=$d?>" <?= old('designation')===$d?'selected':'' ?>><?=$d?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= err('designation') ?>
                    </div>
                    <div class="fg">
                        <label>Department <span class="req">*</span></label>
                        <select name="department" class="fi <?= errClass('department') ?>" id="deptSelect" required>
                            <option value="">Select</option>
                            <?php foreach(['Science','Commerce','Computer Science','Mathematics'] as $dep): ?>
                            <option value="<?=$dep?>" <?= old('department')===$dep?'selected':'' ?>><?=$dep?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= err('department') ?>
                    </div>
                    <div class="fg full">
                        <label>Subjects <span class="req">*</span></label>
                        <div class="subjects-grid">
                            <?php $subjects = ['Hindi','Kannada','Physics','Chemistry','Biology','Mathematics','DS','Python','Java','DBMS','OS','Web Technology','Other']; ?>
                            <?php foreach($subjects as $sub): ?>
                            <label class="subject-item">
                                <input type="checkbox" name="subjects[]" value="<?=$sub?>" <?= isChecked($sub) ?>>
                                <?=$sub?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?= err('subjects') ?>
                    </div>
                    <div class="fg">
                        <label>Qualification <span class="req">*</span></label>
                        <select name="qualification" class="fi <?= errClass('qualification') ?>" required>
                            <option value="">Select</option>
                            <?php $quals = ['B.Sc','M.Sc','B.Com','M.Com','BCA','MCA','B.Tech','M.Tech','Ph.D','Other']; ?>
                            <?php foreach($quals as $q): ?>
                            <option value="<?=$q?>" <?= old('qualification')===$q?'selected':'' ?>><?=$q?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= err('qualification') ?>
                    </div>
                    <div class="fg">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="fi" placeholder="Subject specialization" value="<?= old('specialization') ?>">
                    </div>
                    <div class="fg">
                        <label>Experience (Years)</label>
                        <input type="number" name="experience" class="fi" placeholder="0" min="0" value="<?= old('experience','0') ?>">
                    </div>
                    <div class="fg">
                        <label>Previous Institution</label>
                        <input type="text" name="previous_institution" class="fi" placeholder="Enter previous institution" value="<?= old('previous_institution') ?>">
                    </div>
                    <div class="fg">
                        <label>Joining Date <span class="req">*</span></label>
                        <input type="date" name="joining_date" class="fi <?= errClass('joining_date') ?>" value="<?= old('joining_date') ?>" required>
                        <?= err('joining_date') ?>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="section">
                <div class="section-title st-orange">
                    <span class="st-icon"><i class="fa-solid fa-address-book"></i></span> Contact Details
                </div>
                <div class="grid">
                    <div class="fg">
                        <label>Phone <span class="req">*</span></label>
                        <input type="tel" name="phone" class="fi phone-input <?= errClass('phone') ?>" placeholder="10-digit number" maxlength="10" value="<?= old('phone') ?>" required>
                        <?= err('phone') ?>
                    </div>
                    <div class="fg">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" class="fi <?= errClass('email') ?>" placeholder="teacher@email.com" value="<?= old('email') ?>" required>
                        <?= err('email') ?>
                    </div>
                    <div class="fg full">
                        <label>Address</label>
                        <textarea name="address" class="fi" placeholder="Enter full address"><?= old('address') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Account -->
            <div class="section">
                <div class="section-title st-teal">
                    <span class="st-icon"><i class="fa-solid fa-lock"></i></span> Account & Security
                </div>
                <div class="grid">
                    <div class="fg">
                        <label>Password <span class="req">*</span></label>
                        <div style="position:relative">
                            <input type="password" name="password" class="fi <?= errClass('password') ?>" id="passInput" placeholder="Min 8 characters" minlength="8" required style="padding-right:48px">
                            <button type="button" onclick="togglePass('passInput','eye1')" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:16px;padding:4px">
                                <i class="fa-regular fa-eye" id="eye1"></i>
                            </button>
                        </div>
                        <div class="pass-strength"><div class="pass-strength-bar" id="passBar"></div></div>
                        <div class="pass-hint" id="passHint"></div>
                        <?= err('password') ?>
                    </div>
                    <div class="fg">
                        <label>Confirm Password <span class="req">*</span></label>
                        <div style="position:relative">
                            <input type="password" name="confirm_password" class="fi <?= errClass('confirm_password') ?>" id="confirmInput" placeholder="Re-enter password" required style="padding-right:48px">
                            <button type="button" onclick="togglePass('confirmInput','eye2')" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:16px;padding:4px">
                                <i class="fa-regular fa-eye" id="eye2"></i>
                            </button>
                        </div>
                        <?= err('confirm_password') ?>
                    </div>
                    <div class="fg">
                        <label>Is Admin?</label>
                        <select name="is_admin" class="fi">
                            <option value="No" <?= old('is_admin')==='No'?'selected':'' ?>>No</option>
                            <option value="Yes" <?= old('is_admin')==='Yes'?'selected':'' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status" class="fi">
                            <option value="Active" <?= old('status')==='Active'?'selected':'' ?>>Active</option>
                            <option value="Inactive" <?= old('status')==='Inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Uploads -->
            <div class="section">
                <div class="section-title st-pink">
                    <span class="st-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span> Uploads
                </div>
                <div class="grid-2">
                    <div class="fg">
                        <label>Profile Photo</label>
                        <div class="upload-area">
                            <div class="upload-preview" id="photoPreview"><i class="fa-solid fa-user"></i></div>
                            <div class="upload-meta">
                                <label class="upload-btn" for="photoInput"><i class="fa-solid fa-camera"></i> Choose Photo</label>
                                <input type="file" name="photo" id="photoInput" accept=".jpg,.jpeg,.png,.webp" hidden>
                                <p>JPG, PNG, WEBP • Max 2MB</p>
                                <?= err('photo') ?>
                            </div>
                        </div>
                    </div>
                    <div class="fg">
                        <label>Resume / CV</label>
                        <div class="upload-area">
                            <div class="upload-preview" id="resumePreview"><i class="fa-solid fa-file-lines"></i></div>
                            <div class="upload-meta">
                                <label class="upload-btn" for="resumeInput"><i class="fa-solid fa-file-arrow-up"></i> Choose File</label>
                                <input type="file" name="resume" id="resumeInput" accept=".pdf,.doc,.docx" hidden>
                                <p>PDF, DOC, DOCX • Max 5MB</p>
                                <div class="file-name" id="resumeName" style="display:none">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <span id="resumeFileName"></span>
                                </div>
                                <?= err('resume') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="super_admin.php" class="btn btn-cancel"><i class="fa-solid fa-xmark"></i> Cancel</a>
                <button type="button" class="btn btn-reset" onclick="resetForm()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                <button type="submit" class="btn btn-submit"><i class="fa-solid fa-plus"></i> Add Teacher</button>
            </div>

        </form>

    </div>
</main>

<script>
    function toggleSidebar(){
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }

    function togglePass(inputId, eyeId){
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        if(input.type === 'password'){
            input.type = 'text';
            eye.className = 'fa-regular fa-eye-slash';
        } else {
            input.type = 'password';
            eye.className = 'fa-regular fa-eye';
        }
    }

    // Auto-generate Teacher ID when department and DOB are selected
    function generateTeacherIdFromUI(){
        const dept = document.getElementById('deptSelect').value;
        const dob = document.getElementById('dobInput').value;

        if(!dept || !dob) return;

        const deptCodes = {
            'Science': 'SC',
            'Commerce': 'CM',
            'Computer Science': 'BCA',
            'Mathematics': 'MCA'
        };
        const deptCode = deptCodes[dept] || 'OT';
        const year = new Date(dob).getFullYear().toString().slice(-2);
        const prefix = 'TCH' + deptCode + year;

        document.getElementById('teacherId').value = prefix + '001';
    }

    document.getElementById('deptSelect').addEventListener('change', generateTeacherIdFromUI);
    document.getElementById('dobInput').addEventListener('change', generateTeacherIdFromUI);

    document.getElementById('passInput').addEventListener('input', function(){
        const val = this.value;
        const bar = document.getElementById('passBar');
        const hint = document.getElementById('passHint');
        let strength = 0;

        if(val.length >= 8) strength++;
        if(val.length >= 12) strength++;
        if(/[A-Z]/.test(val)) strength++;
        if(/[0-9]/.test(val)) strength++;
        if(/[^A-Za-z0-9]/.test(val)) strength++;

        const colors = ['var(--red)','var(--orange)','var(--orange)','var(--green)','var(--green)'];
        const labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];
        const widths = ['20%','40%','60%','80%','100%'];

        const idx = Math.min(strength, 4);
        bar.style.width = val.length ? widths[idx] : '0';
        bar.style.background = val.length ? colors[idx] : 'transparent';
        hint.textContent = val.length ? labels[idx] : '';
        hint.style.color = val.length ? colors[idx] : 'transparent';
    });

    document.querySelectorAll('.phone-input').forEach(input => {
        input.addEventListener('input', function(){
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    });

    document.getElementById('photoInput').addEventListener('change', function(e){
        const file = e.target.files[0];
        if(!file) return;
        if(file.size > 2*1024*1024){ alert('Max 2MB'); this.value=''; return; }
        const reader = new FileReader();
        reader.onload = ev => {
            document.getElementById('photoPreview').innerHTML = '<img src="'+ev.target.result+'">';
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('resumeInput').addEventListener('change', function(e){
        const file = e.target.files[0];
        if(!file) return;
        if(file.size > 5*1024*1024){ alert('Max 5MB'); this.value=''; return; }
        document.getElementById('resumeName').style.display = 'flex';
        document.getElementById('resumeFileName').textContent = file.name;
        document.getElementById('resumePreview').innerHTML = '<i class="fa-solid fa-file-circle-check" style="color:var(--green)"></i>';
    });

    function resetForm(){
        if(!confirm('Reset all fields?')) return;
        document.getElementById('teacherForm').reset();
        document.getElementById('photoPreview').innerHTML = '<i class="fa-solid fa-user"></i>';
        document.getElementById('resumePreview').innerHTML = '<i class="fa-solid fa-file-lines"></i>';
        document.getElementById('resumeName').style.display = 'none';
        document.getElementById('passBar').style.width = '0';
        document.getElementById('passHint').textContent = '';
        document.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
        document.querySelectorAll('.field-error').forEach(el => el.remove());
    }

    const alertEl = document.querySelector('.alert');
    if(alertEl) alertEl.scrollIntoView({behavior:'smooth', block:'center'});

    // Initial generation if values are present
    window.addEventListener('load', function(){
        if(document.getElementById('deptSelect').value && document.getElementById('dobInput').value){
            generateTeacherIdFromUI();
        }
    });
</script>

</body>
</html>