<?php
ob_start();
session_start();

// ======== DATABASE CONFIG ========
$db_host = 'localhost';
$db_name = 'aureon';
$db_user = 'root';
$db_pass = '';

// ======== HELPERS ========
function json_out($status, $message, $redirect = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'   => $status,
        'message'  => $message,
        'redirect' => $redirect
    ]);
    exit;
}

function get_dashboard($role) {
    return match($role) {
        'student' => 'student_dash.php',
        'parent'  => 'parent_dash.php',
        'teacher' => 'teacher_dash.php',
        'admin'   => 'super_admin.php',
        default   => 'login.php'
    };
}

// ======== DB CONNECTION ========
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    if($_SERVER['REQUEST_METHOD'] === 'POST')
        json_out('error', 'Database connection failed');
}

// ======== ALREADY LOGGED IN ========
if(isset($_SESSION['user_id'])) {
    header("Location: " . get_dashboard($_SESSION['role']));
    exit;
}

// ======== LOGIN ATTEMPT LIMITER ========
if(!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if(!isset($_SESSION['lockout_time']))   $_SESSION['lockout_time'] = 0;

// ======== HANDLE POST ========
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $action = $_POST['action'];

    // --- LOGIN ---
    if($action === 'login') {

        // Check lockout
        if($_SESSION['login_attempts'] >= 5) {
            $remaining = 300 - (time() - $_SESSION['lockout_time']);
            if($remaining > 0) {
                json_out('error', 'Too many attempts. Try again in ' . ceil($remaining/60) . ' min.');
            } else {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['lockout_time'] = 0;
            }
        }

        $role     = trim($_POST['role'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if(empty($role)) json_out('error', 'Please select a role');

        $user = null;

        // STUDENT
        if($role === 'student') {
            if(empty($username) || empty($name) || empty($password))
                json_out('error', 'All fields are required');

            $stmt = $pdo->prepare("SELECT id, full_name FROM users
                WHERE role='student' AND student_id=? AND full_name=? AND dob=? AND status='Active'
                LIMIT 1");
            $stmt->execute([$username, $name, $password]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$user) json_out('error', 'Invalid Student ID, Name or Date of Birth');
            $user['role'] = 'student';
        }

        // PARENT
        elseif($role === 'parent') {
            if(empty($name) || empty($password))
                json_out('error', 'All fields are required');

            $stmt = $pdo->prepare("SELECT id, parent_name as full_name FROM users
                WHERE parent_name=? AND dob=? AND status='Active'
                LIMIT 1");
            $stmt->execute([$name, $password]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$user) json_out('error', 'Invalid Parent Name or Date of Birth');
            $user['role'] = 'parent';
        }

        // TEACHER
        elseif($role === 'teacher') {
            if(empty($username) || empty($password))
                json_out('error', 'Email and password required');

            $stmt = $pdo->prepare("SELECT id, full_name, password FROM users
                WHERE role='teacher' AND email=? AND status='Active'
                LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$user || !password_verify($password, $user['password']))
                json_out('error', 'Invalid email or password');
            $user['role'] = 'teacher';
        }

        // ADMIN
        elseif($role === 'admin') {
            if(empty($username) || empty($password))
                json_out('error', 'Email and password required');

            $stmt = $pdo->prepare("SELECT id, full_name, password FROM users
                WHERE role='admin' AND email=? AND status='Active'
                LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$user || !password_verify($password, $user['password']))
                json_out('error', 'Invalid email or password');
            $user['role'] = 'admin';
        }
        else {
            json_out('error', 'Invalid role');
        }

        // SUCCESS
        $_SESSION['login_attempts'] = 0;
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['name']      = $user['full_name'];

        json_out('success', 'Welcome back, ' . $user['full_name'] . '!',
                 get_dashboard($user['role']));
    }

    // --- FORGOT PASSWORD ---
    if($action === 'forgot') {
        $email = trim($_POST['email'] ?? '');
        if(empty($email)) json_out('error', 'Please enter your email');

        $stmt = $pdo->prepare("SELECT id, full_name FROM users
            WHERE email=? AND (role='teacher' OR role='admin') AND status='Active'
            LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user) {
            json_out('success', 'Password reset link sent to your email');
        } else {
            json_out('error', 'No account found with this email');
        }
    }

    // Increment attempts on failed login
    $_SESSION['login_attempts']++;
    if($_SESSION['login_attempts'] >= 5)
        $_SESSION['lockout_time'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AUREON ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
       /* =========================================================
   C/* =========================================================
   AUREON ERP LOGIN - MEDIUM SIZE RESPONSIVE CSS
========================================================= */

/* ===== RESET ===== */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

:root{

    --bg-1:#f8fafc;
    --bg-2:#eef2ff;
    --surface:#ffffff;

    --primary:#6366f1;
    --primary-light:#818cf8;
    --primary-glow:rgba(99,102,241,.18);

    --text:#0f172a;
    --text-light:#64748b;
    --text-dim:#94a3b8;

    --border:#e2e8f0;

    --success:#10b981;
    --danger:#ef4444;
    --warning:#f59e0b;
}

/* ===== BODY ===== */
body{
    min-height:100vh;

    font-family:'Inter',sans-serif;

    display:flex;
    align-items:center;
    justify-content:center;

    padding:24px;

    overflow-x:hidden;

    background:
    linear-gradient(
        135deg,
        #f8fafc 0%,
        #eef2ff 45%,
        #ffffff 100%
    );

    position:relative;
}

/* ===== BACKGROUND ===== */
body::before{
    content:'';

    position:fixed;
    inset:0;

    background:
    radial-gradient(circle at 20% 20%, rgba(99,102,241,.12), transparent 28%),
    radial-gradient(circle at 80% 30%, rgba(236,72,153,.10), transparent 26%),
    radial-gradient(circle at 50% 80%, rgba(16,185,129,.08), transparent 30%);

    z-index:-2;
}

/* ===== FLOATING ICONS ===== */
.bg-animations{
    position:fixed;
    inset:0;
    overflow:hidden;
    pointer-events:none;
    z-index:-1;
}

.anim-icon{
    position:absolute;
    font-size:34px;
    color:rgba(99,102,241,.08);
    animation:floatIcon linear infinite;
}

.icon-book{
    left:8%;
    top:12%;
    animation-duration:15s;
}

.icon-chat{
    right:10%;
    top:18%;
    animation-duration:18s;
}

.icon-pen{
    left:15%;
    bottom:12%;
    animation-duration:20s;
}

.icon-grad{
    right:18%;
    bottom:8%;
    animation-duration:22s;
}

@keyframes floatIcon{
    0%{
        transform:translateY(0px);
    }
    50%{
        transform:translateY(-22px);
    }
    100%{
        transform:translateY(0px);
    }
}

/* ===== CURSOR ===== */
.cursor-dot{
    width:8px;
    height:8px;

    background:var(--primary);

    border-radius:50%;

    position:fixed;
    top:0;
    left:0;

    transform:translate(-50%,-50%);

    pointer-events:none;
    z-index:99999;
}

.cursor-ring{
    width:40px;
    height:40px;

    border:2px solid rgba(99,102,241,.35);

    border-radius:50%;

    position:fixed;
    top:0;
    left:0;

    transform:translate(-50%,-50%);

    pointer-events:none;

    transition:.25s ease;

    z-index:99998;
}

body.cursor-hover .cursor-ring{
    width:58px;
    height:58px;
}

/* ===== PAGE ===== */
.page{
    width:100%;
    max-width:980px;

    min-height:620px;

    display:grid;
    grid-template-columns:0.95fr 1fr;

    background:rgba(255,255,255,.88);

    backdrop-filter:blur(16px);

    border-radius:28px;

    overflow:hidden;

    border:1px solid rgba(255,255,255,.45);

    box-shadow:
    0 18px 45px rgba(15,23,42,.10);
}

/* ===== LEFT PANEL ===== */
.left-panel{
    position:relative;

    padding:34px;

    display:flex;
    flex-direction:column;
    justify-content:center;

    background:
    linear-gradient(
        135deg,
        #6366f1 0%,
        #8b5cf6 100%
    );

    color:#fff;
}

.left-content{
    position:relative;
    z-index:2;
}

/* ===== LOGO ===== */
.aureon-logo{
    width:90px;
    height:90px;

    margin-bottom:22px;

    border-radius:26px;

    background:rgba(255,255,255,.14);

    border:1px solid rgba(255,255,255,.18);

    display:flex;
    align-items:center;
    justify-content:center;

    backdrop-filter:blur(10px);
}

.logo-inner{
    position:relative;
}

.logo-letter{
    font-size:52px;
    font-weight:900;
    color:#fff;
}

.cap-wrap{
    position:absolute;
    top:-14px;
    right:-18px;

    font-size:18px;

    transform:rotate(-15deg);
}

/* ===== TITLES ===== */
.brand-title{
    font-size:34px;
    font-weight:900;

    margin-bottom:10px;
}

.brand-sub{
    font-size:15px;
    line-height:1.7;

    color:rgba(255,255,255,.88);

    margin-bottom:32px;
}

/* ===== ROLE GRID ===== */
.role-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}

.role-card{
    position:relative;

    padding:18px 14px;

    border-radius:20px;

    background:rgba(255,255,255,.10);

    border:1px solid rgba(255,255,255,.16);

    backdrop-filter:blur(10px);

    text-align:center;

    cursor:pointer;

    transition:.3s ease;
}

.role-card:hover{
    transform:translateY(-4px);
    background:rgba(255,255,255,.16);
}

.role-card.active{
    background:#fff;
    color:var(--primary);
}

.role-icon{
    width:46px;
    height:46px;

    margin:0 auto 12px;

    border-radius:14px;

    display:flex;
    align-items:center;
    justify-content:center;

    background:rgba(255,255,255,.14);

    font-size:18px;
}

.role-card.active .role-icon{
    background:var(--primary-glow);
}

.role-label{
    font-size:14px;
    font-weight:700;
}

.role-check{
    position:absolute;

    top:10px;
    right:10px;

    width:22px;
    height:22px;

    border-radius:50%;

    background:var(--primary);

    color:#fff;

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:10px;

    opacity:0;
    transform:scale(0);

    transition:.3s ease;
}

.role-card.active .role-check{
    opacity:1;
    transform:scale(1);
}

/* ===== FOOTER ===== */
.left-footer{
    position:absolute;

    bottom:28px;
    left:34px;

    display:flex;
    align-items:center;
    gap:14px;

    font-size:13px;

    color:rgba(255,255,255,.86);
}

.ssl-badge{
    padding:10px 14px;

    border-radius:12px;

    background:rgba(255,255,255,.14);

    border:1px solid rgba(255,255,255,.16);

    display:flex;
    align-items:center;
    gap:8px;
}

/* ===== RIGHT PANEL ===== */
.right-panel{
    display:flex;
    align-items:center;
    justify-content:center;

    padding:36px 30px;

    background:rgba(255,255,255,.72);
}

.form-container{
    width:100%;
    max-width:400px;
}

/* ===== MOBILE BRAND ===== */
.mobile-brand{
    display:none;
}

/* ===== MOBILE ROLES ===== */
.mobile-roles{
    display:none;
}

/* ===== NAVIGATION ===== */
.back-row{
    display:flex;
    justify-content:space-between;
    align-items:center;

    margin-bottom:30px;
}

.back-btn,
.dash-btn{
    text-decoration:none;

    padding:11px 18px;

    border-radius:14px;

    font-size:14px;
    font-weight:700;

    display:flex;
    align-items:center;
    gap:10px;

    transition:.25s ease;
}

.back-btn{
    background:#fff;
    border:1px solid var(--border);

    color:var(--text-light);
}

.back-btn:hover{
    transform:translateY(-2px);
}

.dash-btn{
    background:var(--primary);
    color:#fff;

    box-shadow:
    0 12px 24px var(--primary-glow);
}

.dash-btn:hover{
    transform:translateY(-2px);
}

/* ===== HEADER ===== */
.form-header{
    margin-bottom:30px;
}

.greeting{
    font-size:14px;
    font-weight:600;

    color:var(--text-light);

    margin-bottom:6px;
}

.form-header h2{
    font-size:30px;
    font-weight:900;
}

.emoji{
    margin-right:6px;
}

/* ===== FIELDS ===== */
.field{
    margin-bottom:20px;
}

.field.hidden{
    display:none;
}

.input-wrap{
    position:relative;
}

.form-input{
    width:100%;
    height:58px;

    padding:
    22px 50px 8px 50px;

    border-radius:16px;

    border:1px solid var(--border);

    background:rgba(255,255,255,.95);

    font-size:14px;
    font-weight:600;

    color:var(--text);

    outline:none;

    transition:.25s ease;
}

.form-input::placeholder{
    color:transparent;
}

.form-input:focus{
    border-color:var(--primary);

    box-shadow:
    0 0 0 5px var(--primary-glow);
}

.float-label{
    position:absolute;

    left:50px;
    top:50%;

    transform:translateY(-50%);

    color:var(--text-dim);

    font-size:14px;

    pointer-events:none;

    transition:.25s ease;
}

.form-input:focus ~ .float-label,
.form-input:not(:placeholder-shown) ~ .float-label{
    top:-9px;
    left:16px;

    transform:none;

    font-size:11px;
    font-weight:700;

    color:var(--primary);

    background:#fff;

    padding:4px 10px;

    border-radius:999px;
}

.field-icon{
    position:absolute;

    left:18px;
    top:50%;

    transform:translateY(-50%);

    color:var(--text-dim);

    font-size:16px;
}

.eye-toggle{
    position:absolute;

    right:10px;
    top:50%;

    transform:translateY(-50%);

    width:38px;
    height:38px;

    border:none;

    border-radius:12px;

    background:transparent;

    cursor:pointer;

    color:var(--text-dim);
}

.eye-toggle:hover{
    background:#f1f5f9;
}

/* ===== OPTIONS ===== */
.options-row{
    display:flex;
    justify-content:space-between;
    align-items:center;

    margin-bottom:24px;
}

.remember{
    display:flex;
    align-items:center;
    gap:10px;

    font-size:13px;
    font-weight:600;

    color:var(--text-light);
}

.remember input{
    accent-color:var(--primary);
}

.forgot-btn{
    border:none;
    background:none;

    color:var(--primary);

    font-size:13px;
    font-weight:700;

    cursor:pointer;

    opacity:0;
    pointer-events:none;
}

.forgot-btn.visible{
    opacity:1;
    pointer-events:auto;
}

/* ===== BUTTON ===== */
.submit-btn{
    width:100%;
    height:58px;

    border:none;

    border-radius:16px;

    background:
    linear-gradient(
        135deg,
        var(--primary),
        var(--primary-light)
    );

    color:#fff;

    font-size:15px;
    font-weight:800;

    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;

    cursor:pointer;

    transition:.3s ease;

    box-shadow:
    0 16px 28px var(--primary-glow);
}

.submit-btn:hover{
    transform:translateY(-3px);
}

.submit-btn.loading{
    opacity:.9;
    pointer-events:none;
}

/* ===== SPINNER ===== */
.spinner{
    width:18px;
    height:18px;

    border:2px solid rgba(255,255,255,.4);
    border-top-color:#fff;

    border-radius:50%;

    animation:spin .7s linear infinite;
}

@keyframes spin{
    to{
        transform:rotate(360deg);
    }
}

/* ===== ATTEMPT INFO ===== */
.attempt-info{
    margin-top:16px;

    text-align:center;

    font-size:13px;
    font-weight:700;

    color:var(--text-light);
}

.attempt-info.warn{
    color:var(--warning);
}

.attempt-info.danger{
    color:var(--danger);
}

/* ===== TOAST ===== */
.toast{
    position:fixed;

    top:24px;
    right:24px;

    min-width:300px;

    padding:16px 20px;

    border-radius:18px;

    background:#fff;

    border:1px solid var(--border);

    box-shadow:
    0 18px 40px rgba(0,0,0,.12);

    display:flex;
    align-items:center;
    gap:12px;

    z-index:99999;

    transform:translateX(130%);
    transition:.4s ease;
}

.toast.show{
    transform:translateX(0);
}

.toast.success{
    border-left:5px solid var(--success);
}

.toast.error{
    border-left:5px solid var(--danger);
}

/* ===== MODAL ===== */
.modal-overlay{
    position:fixed;
    inset:0;

    background:rgba(15,23,42,.45);

    backdrop-filter:blur(8px);

    display:flex;
    align-items:center;
    justify-content:center;

    opacity:0;
    visibility:hidden;

    transition:.3s ease;

    z-index:9999;
}

.modal-overlay.open{
    opacity:1;
    visibility:visible;
}

.modal{
    width:100%;
    max-width:420px;

    padding:34px;

    border-radius:26px;

    background:#fff;

    position:relative;

    transform:translateY(20px) scale(.95);

    transition:.35s ease;

    box-shadow:
    0 25px 50px rgba(0,0,0,.16);
}

.modal-overlay.open .modal{
    transform:none;
}

.modal h3{
    font-size:26px;
    font-weight:900;

    margin-bottom:10px;
}

.modal p{
    color:var(--text-light);

    line-height:1.7;

    margin-bottom:22px;
}

.modal-input{
    width:100%;
    height:56px;

    padding:0 16px 0 48px;

    border-radius:16px;

    border:1px solid var(--border);

    font-size:14px;
    font-weight:600;

    outline:none;
}

.modal-input:focus{
    border-color:var(--primary);

    box-shadow:
    0 0 0 5px var(--primary-glow);
}

.modal-submit{
    width:100%;
    height:56px;

    margin-top:18px;

    border:none;

    border-radius:16px;

    background:var(--primary);

    color:#fff;

    font-size:15px;
    font-weight:800;

    cursor:pointer;
}

.modal-close{
    position:absolute;

    top:16px;
    right:16px;

    width:40px;
    height:40px;

    border:none;

    border-radius:12px;

    background:#f1f5f9;

    cursor:pointer;
}

/* ===== RESPONSIVE ===== */
@media(max-width:980px){

    .page{
        grid-template-columns:1fr;
        min-height:auto;
    }

    .left-panel{
        display:none;
    }

    .right-panel{
        padding:34px 24px;
    }

    .mobile-brand{
        display:flex;
        flex-direction:column;
        align-items:center;

        margin-bottom:28px;
    }

    .mobile-roles{
        display:grid;
        grid-template-columns:repeat(4,1fr);

        gap:10px;

        margin-bottom:28px;
    }

    .mobile-role{
        padding:12px 8px;

        border-radius:14px;

        background:#fff;

        border:1px solid var(--border);

        text-align:center;

        font-size:11px;
        font-weight:700;

        color:var(--text-light);

        transition:.25s ease;
    }

    .mobile-role i{
        display:block;
        margin-bottom:6px;
        font-size:16px;
    }

    .mobile-role.active{
        background:var(--primary-glow);
        border-color:var(--primary);
        color:var(--primary);
    }
}

@media(max-width:600px){

    body{
        padding:14px;
    }

    .right-panel{
        padding:22px 18px;
    }

    .form-header h2{
        font-size:26px;
    }

    .mobile-roles{
        grid-template-columns:1fr 1fr;
    }

    .back-row{
        flex-direction:column;
        gap:12px;
    }

    .back-btn,
    .dash-btn{
        width:100%;
        justify-content:center;
    }

    .toast{
        right:12px;
        left:12px;
        min-width:auto;
    }

    .modal{
        margin:18px;
        padding:28px 22px;
    }

    .cursor-dot,
    .cursor-ring{
        display:none;
    }
}

    </style>
</head>
<body>

<!-- Custom Interactive Cursor Elements -->
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- Animated Floating Background Icons -->
<div class="bg-animations">
    <i class="fa-solid fa-book anim-icon icon-book"></i>
    <i class="fa-solid fa-comments anim-icon icon-chat"></i>
    <i class="fa-solid fa-pen-fancy anim-icon icon-pen"></i>
    <i class="fa-solid fa-graduation-cap anim-icon icon-grad"></i>
</div>

<div class="page">

    <!-- ============ LEFT PANEL ============ -->
    <div class="left-panel" id="leftPanelBG">
        <div class="left-content">
            
            <!-- CUSTOM CSS AUREON LOGO -->
            <div class="aureon-logo">
                <div class="logo-inner">
                    <span class="logo-letter">A</span>
                    <div class="cap-wrap">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
            </div>

            <h1 class="brand-title">AUREON ERP</h1>
            <p class="brand-sub">Enterprise Resource Planning for Education</p>

            <div class="role-grid">
                <div class="role-card active" data-role="student" onclick="selectRole('student')">
                    <div class="role-check"><i class="fa-solid fa-check"></i></div>
                    <div class="role-icon"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="role-label">Student</div>
                </div>
                <div class="role-card" data-role="parent" onclick="selectRole('parent')">
                    <div class="role-check"><i class="fa-solid fa-check"></i></div>
                    <div class="role-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="role-label">Parent</div>
                </div>
                <div class="role-card" data-role="teacher" onclick="selectRole('teacher')">
                    <div class="role-check"><i class="fa-solid fa-check"></i></div>
                    <div class="role-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div class="role-label">Teacher</div>
                </div>
                <div class="role-card" data-role="admin" onclick="selectRole('admin')">
                    <div class="role-check"><i class="fa-solid fa-check"></i></div>
                    <div class="role-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="role-label">Admin</div>
                </div>
            </div>
        </div>

        <div class="left-footer">
            <p>© 2025 AUREON ERP</p>
            <div class="ssl-badge">
                <i class="fa-solid fa-lock" style="color: #fff;"></i>
                256-bit SSL Encrypted
            </div>
        </div>
    </div>


    <!-- ============ RIGHT PANEL ============ -->
    <div class="right-panel">
        <div class="form-container">

            <!-- Mobile Brand -->
            <div class="mobile-brand">
                <div class="mobile-logo-wrap">
                    <div class="aureon-logo">
                        <div class="logo-inner">
                            <span class="logo-letter">A</span>
                            <div class="cap-wrap">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <span style="font-size:22px;font-weight:800;color:var(--text);">AUREON ERP</span>
            </div>

            <!-- Mobile Roles -->
            <div class="mobile-roles">
                <div class="mobile-role active" data-role="student" onclick="selectRole('student')">
                    <i class="fa-solid fa-user-graduate"></i>Student
                </div>
                <div class="mobile-role" data-role="parent" onclick="selectRole('parent')">
                    <i class="fa-solid fa-users"></i>Parent
                </div>
                <div class="mobile-role" data-role="teacher" onclick="selectRole('teacher')">
                    <i class="fa-solid fa-chalkboard-user"></i>Teacher
                </div>
                <div class="mobile-role" data-role="admin" onclick="selectRole('admin')">
                    <i class="fa-solid fa-shield-halved"></i>Admin
                </div>
            </div>

            <!-- Navigation Row -->
            <div class="back-row">
                <a href="index.php" class="back-btn interactive-el">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <a href="#" class="dash-btn interactive-el" id="dashBtn">
                    <i class="fa-solid fa-grip"></i> Dashboard
                </a>
            </div>

            <!-- Form Header -->
            <div class="form-header">
                <div class="greeting" id="greetText">Welcome back</div>
                <h2 id="formTitle"><span class="emoji">🎓</span> Student Login</h2>
            </div>

            <!-- Form -->
            <form id="loginForm" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="role" id="roleInput" value="student">

                <!-- Identity -->
                <div class="field" id="identityField">
                    <div class="input-wrap">
                        <input type="text" class="form-input interactive-el" id="usernameInput" name="username" placeholder="x" required autocomplete="off">
                        <span class="float-label" id="identityLabel">Student ID</span>
                        <i class="fa-regular fa-id-badge field-icon" id="identityIcon"></i>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="field" id="nameField">
                    <div class="input-wrap">
                        <input type="text" class="form-input interactive-el" id="nameInput" name="full_name" placeholder="x" required autocomplete="off">
                        <span class="float-label" id="nameLabel">Full Name</span>
                        <i class="fa-regular fa-user field-icon"></i>
                    </div>
                </div>

                <!-- Password / DOB -->
                <div class="field" id="credField">
                    <div class="input-wrap">
                        <input type="date" class="form-input interactive-el" id="passInput" name="password" placeholder="x" required>
                        <span class="float-label" id="credLabel">Date of Birth</span>
                        <i class="fa-regular fa-calendar field-icon" id="credIcon"></i>
                        <button type="button" class="eye-toggle interactive-el" id="eyeBtn" onclick="toggleEye()">
                            <i class="fa-regular fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Options -->
                <div class="options-row">
                    <label class="remember interactive-el">
                        <input type="checkbox" name="remember" class="interactive-el"> Remember me
                    </label>
                    <button type="button" class="forgot-btn interactive-el" id="forgotBtn" onclick="openForgot()">
                        Forgot Password?
                    </button>
                </div>

                <!-- Submit -->
                <button type="submit" class="submit-btn interactive-el" id="submitBtn">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Sign In</span>
                </button>

                <!-- Attempts -->
                <div class="attempt-info" id="attemptInfo"></div>
            </form>
        </div>
    </div>

</div>

<!-- ============ FORGOT PASSWORD MODAL ============ -->
<div class="modal-overlay" id="forgotModal">
    <div class="modal" style="position:relative">
        <button class="modal-close interactive-el" onclick="closeForgot()"><i class="fa-solid fa-xmark"></i></button>
        <h3>Reset Password</h3>
        <p>Enter your registered email address. We'll send a password reset link.</p>
        <form id="forgotForm">
            <input type="hidden" name="action" value="forgot">
            <div style="position:relative">
                <i class="fa-regular fa-envelope" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:16px"></i>
                <input type="email" class="modal-input interactive-el" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="modal-submit interactive-el">Send Reset Link</button>
        </form>
    </div>
</div>


<script>
    // ============ ELEMENTS ============
    const form          = document.getElementById('loginForm');
    const roleInput     = document.getElementById('roleInput');
    const greetText     = document.getElementById('greetText');
    const formTitle     = document.getElementById('formTitle');
    const identityField = document.getElementById('identityField');
    const identityLabel = document.getElementById('identityLabel');
    const identityIcon  = document.getElementById('identityIcon');
    const usernameInput = document.getElementById('usernameInput');
    const nameField     = document.getElementById('nameField');
    const nameLabel     = document.getElementById('nameLabel');
    const nameInput     = document.getElementById('nameInput');
    const credField     = document.getElementById('credField');
    const credLabel     = document.getElementById('credLabel');
    const credIcon      = document.getElementById('credIcon');
    const passInput     = document.getElementById('passInput');
    const eyeBtn        = document.getElementById('eyeBtn');
    const eyeIcon       = document.getElementById('eyeIcon');
    const forgotBtn     = document.getElementById('forgotBtn');
    const submitBtn     = document.getElementById('submitBtn');
    const attemptInfo   = document.getElementById('attemptInfo');
    const dashBtn       = document.getElementById('dashBtn');
    const roleCards     = document.querySelectorAll('.role-card');
    const mobileRoles   = document.querySelectorAll('.mobile-role');
    const leftPanelBG   = document.getElementById('leftPanelBG');

    let attempts = 0;

    // Config handles titles, inputs, AND dynamic colors per role
    const config = {
        student: {
            title: '<span class="emoji">🎓</span> Student Login',
            greeting: 'Welcome back',
            color: '#6366f1', // Indigo
            gradient: 'linear-gradient(135deg, #6366f1 0%, #a78bfa 100%)',
            identity: { label:'Student ID', icon:'fa-regular fa-id-badge', show:true },
            name: { label:'Full Name', show:true, required:true },
            cred: { label:'Date of Birth', icon:'fa-regular fa-calendar', type:'date' },
            showEye: false, showForgot: false, dash: 'student_dash.php'
        },
        parent: {
            title: '<span class="emoji">👨‍👩‍👧</span> Parent Login',
            greeting: 'Welcome back',
            color: '#f43f5e', // Rose/Pink
            gradient: 'linear-gradient(135deg, #f43f5e 0%, #fb7185 100%)',
            identity: { label:'Student ID', icon:'fa-regular fa-id-badge', show:true },
            name: { label:'Parent Full Name', show:true, required:true },
            cred: { label:'Date of Birth', icon:'fa-regular fa-calendar', type:'date' },
            showEye: false, showForgot: false, dash: 'parent_dash.php'
        },
        teacher: {
            title: '<span class="emoji">👨‍🏫</span> Teacher Login',
            greeting: 'Sign in to your account',
            color: '#10b981', // Emerald/Teal
            gradient: 'linear-gradient(135deg, #10b981 0%, #34d399 100%)',
            identity: { label:'Email Address', icon:'fa-regular fa-envelope', show:true },
            name: { label:'', show:false, required:false },
            cred: { label:'Password', icon:'fa-solid fa-lock', type:'password' },
            showEye: true, showForgot: true, dash: 'teacher_dash.php'
        },
        admin: {
            title: '<span class="emoji">🛡️</span> Admin Login',
            greeting: 'Secure admin access',
            color: '#f59e0b', // Amber/Orange
            gradient: 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)',
            identity: { label:'Email or Admin ID', icon:'fa-solid fa-shield-halved', show:true },
            name: { label:'', show:false, required:false },
            cred: { label:'Password', icon:'fa-solid fa-lock', type:'password' },
            showEye: true, showForgot: false, dash: 'super_admin_dash.php'
        }
    };

    // ============ CUSTOM INTERACTIVE CURSOR ============
    const cursorDot = document.getElementById('cursorDot');
    const cursorRing = document.getElementById('cursorRing');
    let dotX = 0, dotY = 0, ringX = 0, ringY = 0;

    window.addEventListener('mousemove', function(e) {
        dotX = e.clientX;
        dotY = e.clientY;
        cursorDot.style.left = dotX + 'px';
        cursorDot.style.top = dotY + 'px';
    });

    function animateCursor() {
        ringX += (dotX - ringX) * 0.15;
        ringY += (dotY - ringY) * 0.15;
        cursorRing.style.left = ringX + 'px';
        cursorRing.style.top = ringY + 'px';
        requestAnimationFrame(animateCursor);
    }
    animateCursor();

    // Add expansion hover effect to buttons, links, and inputs
    const interactives = document.querySelectorAll('a, button, input, .role-card, label, .interactive-el');
    interactives.forEach(el => {
        el.addEventListener('mouseenter', () => document.body.classList.add('cursor-hover'));
        el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-hover'));
    });


    // ============ ROLE SELECT ============
    function selectRole(role){
        const c = config[role];
        roleInput.value = role;

        // Apply Dynamic Colors using CSS Variables
        document.documentElement.style.setProperty('--primary', c.color);
        // Create a glow color based on the hex (roughly 15% opacity)
        document.documentElement.style.setProperty('--primary-glow', c.color + '26'); 
        leftPanelBG.style.background = c.gradient;

        // Update desktop cards
        roleCards.forEach(card => card.classList.toggle('active', card.dataset.role === role));
        // Update mobile cards
        mobileRoles.forEach(btn => btn.classList.toggle('active', btn.dataset.role === role));

        // Header
        formTitle.innerHTML = c.title;
        greetText.textContent = c.greeting;

        // Identity field
        identityLabel.textContent = c.identity.label;
        identityIcon.className = c.identity.icon + ' field-icon';
        usernameInput.value = '';

        // Name field
        if(c.name.show){
            nameField.classList.remove('hidden');
            nameLabel.textContent = c.name.label;
            nameInput.required = true;
        } else {
            nameField.classList.add('hidden');
            nameInput.required = false;
        }
        nameInput.value = '';

        // Credential field
        credLabel.textContent = c.cred.label;
        credIcon.className = c.cred.icon + ' field-icon';
        passInput.type = c.cred.type;
        passInput.value = '';

        // Eye toggle
        eyeBtn.style.display = c.showEye ? 'block' : 'none';
        forgotBtn.classList.toggle('visible', c.showForgot);
        dashBtn.href = c.dash;

        if(!c.showEye){ eyeIcon.className = 'fa-regular fa-eye'; }
    }

    // ============ EYE TOGGLE ============
    function toggleEye(){
        if(passInput.type === 'password'){
            passInput.type = 'text';
            eyeIcon.className = 'fa-regular fa-eye-slash';
        } else {
            passInput.type = 'password';
            eyeIcon.className = 'fa-regular fa-eye';
        }
    }

    // ============ TOAST ============
    let toastEl = null;
    function showToast(msg, type){
        if(!toastEl){
            toastEl = document.createElement('div');
            document.body.appendChild(toastEl);
        }
        const icon = type === 'success' ? '✅' : '❌';
        toastEl.innerHTML = `<span style="font-size:18px">${icon}</span> ${msg}`;
        toastEl.className = `toast ${type} show`;
        setTimeout(() => toastEl.classList.remove('show'), 4500);
    }

    // ============ FORM SUBMIT ============
    form.addEventListener('submit', async function(e){
        e.preventDefault();

        const oldHTML = submitBtn.innerHTML;
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<div class="spinner"></div><span>Authenticating...</span>';

        fetch(window.location.href, {
            method:'POST',
            body: new FormData(form)
        })
        .then(async r => {
            const raw = await r.text();
            try { return JSON.parse(raw); }
            catch(e) { throw new Error('Server error'); }
        })
        .then(data => {
            if(data.status === 'success'){
                showToast(data.message, 'success');
                submitBtn.innerHTML = '<i class="fa-solid fa-check"></i><span>Success!</span>';
                attempts = 0; updateAttempts();
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                showToast(data.message, 'error');
                attempts++; updateAttempts();
                submitBtn.classList.remove('loading');
                submitBtn.innerHTML = oldHTML;
            }
        })
        .catch(err => {
            showToast(err.message, 'error');
            attempts++; updateAttempts();
            submitBtn.classList.remove('loading');
            submitBtn.innerHTML = oldHTML;
        });
    });

    // ============ ATTEMPT COUNTER ============
    function updateAttempts(){
        if(attempts === 0){
            attemptInfo.textContent = '';
            attemptInfo.className = 'attempt-info';
        } else if(attempts < 3){
            attemptInfo.textContent = `${attempts}/5 failed attempts`;
            attemptInfo.className = 'attempt-info';
        } else if(attempts < 5){
            attemptInfo.textContent = `⚠️ ${attempts}/5 attempts — account will lock`;
            attemptInfo.className = 'attempt-info warn';
        } else {
            attemptInfo.textContent = '🔒 Too many attempts. Please wait.';
            attemptInfo.className = 'attempt-info danger';
        }
    }

    // ============ FORGOT PASSWORD ============
    function openForgot(){ document.getElementById('forgotModal').classList.add('open'); }
    function closeForgot(){ document.getElementById('forgotModal').classList.remove('open'); }

    document.getElementById('forgotForm').addEventListener('submit', function(e){
        e.preventDefault();
        const btn = this.querySelector('.modal-submit');
        const oldText = btn.textContent;
        btn.textContent = 'Sending...';

        fetch(window.location.href, { method:'POST', body: new FormData(this) })
        .then(async r => {
            const raw = await r.text();
            try { return JSON.parse(raw); } catch(e) { throw new Error('Server error'); }
        })
        .then(data => {
            showToast(data.message, data.status);
            btn.textContent = oldText;
            if(data.status === 'success') closeForgot();
        })
        .catch(err => { showToast(err.message, 'error'); btn.textContent = oldText; });
    });

    document.getElementById('forgotModal').addEventListener('click', function(e){
        if(e.target === this) closeForgot();
    });

    // ============ INIT ============
    selectRole('student');
</script>

</body>
</html>