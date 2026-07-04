<?php
session_start();

// Redirect if not logged in or not teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$name = $_SESSION['name'] ?? 'teacher';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"/>
    
    <style>
        :root {
            --cream: #fff9f0;
            --soft-beige: #fdf4e8;
            --light-orange: #ffe6c7;
            --accent: #e89a4a;
            --text: #3f2a1e;
            --muted: #8c6f4e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--cream);
            color: var(--text);
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ==================== SIDEBAR ==================== */
        .sidebar {
            width: 290px;
            background: #fffaf0;
            border-right: 1px solid #f0e4d0;
            display: flex;
            flex-direction: column;
            padding: 30px 0;
            position: fixed;
            height: 100vh;
            box-shadow: 4px 0 25px rgba(232, 154, 74, 0.08);
            z-index: 1000;
        }

        .sidebar .brand {
            padding: 0 28px 40px;
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar .logo {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #ffe6c7, #ffbe7a);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 3.5rem;
            font-weight: 800;
            color: #3f2a1e;
            box-shadow: 0 12px 35px rgba(232, 154, 74, 0.25);
            /* To use real image logo, replace above with: 
               background: url('your-logo.png') center/cover no-repeat; */
        }

        .sidebar .brand h4 {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--accent);
            margin: 0;
        }

        .sidebar .brand small {
            color: #a38a6e;
            font-size: 0.88rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: 0 18px;
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

        .nav-item:hover {
            background: var(--light-orange);
            color: var(--accent);
            transform: translateX(6px);
        }

        .nav-item.active {
            background: var(--light-orange);
            color: var(--accent);
            font-weight: 600;
        }

        .nav-item i {
            font-size: 1.3rem;
            width: 28px;
        }

        .permission-note {
            margin: 20px 18px;
            padding: 16px 20px;
            background: var(--light-orange);
            border-radius: 16px;
            font-size: 0.85rem;
            color: #6b5a44;
            border-left: 5px solid var(--accent);
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

        .logout-btn:hover {
            background: var(--light-orange);
            color: #c15c4e;
        }

        /* ==================== MAIN CONTENT ==================== */
        .main {
            margin-left: 290px;
            flex: 1;
            padding: 35px 40px;
        }

        .top-bar {
            background: rgba(255, 250, 240, 0.98);
            backdrop-filter: blur(12px);
            padding: 18px 32px;
            margin: -35px -40px 35px -40px;
            border-bottom: 1px solid #f0e4d0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .result-hero {
            background: linear-gradient(135deg, #fff9f0, #ffe6c7);
            color: #3f2a1e;
            padding: 42px 36px;
            border-radius: 28px;
            margin-bottom: 32px;
            box-shadow: 0 12px 35px rgba(232, 154, 74, 0.12);
        }

        .kpi-card {
            background: white;
            padding: 28px 22px;
            border-radius: 22px;
            box-shadow: 0 8px 25px rgba(232, 154, 74, 0.08);
            text-align: center;
            transition: all 0.4s ease;
            height: 100%;
        }

        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(232, 154, 74, 0.15);
        }

        .kpi-card .label {
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .kpi-card h3 {
            font-size: 2.35rem;
            font-weight: 800;
            color: #3f2a1e;
            margin: 8px 0;
        }

        .content-card {
            background: white;
            padding: 32px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(232, 154, 74, 0.07);
            margin-bottom: 28px;
        }

        .list-item {
            background: var(--soft-beige);
            padding: 18px 22px;
            border-radius: 18px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .list-item:hover {
            background: white;
            transform: translateX(8px);
            box-shadow: 0 8px 20px rgba(232, 154, 74, 0.1);
        }

        .chip {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .chip-success {
            background: #d4e9d4;
            color: #2e7d32;
        }

        @media (max-width: 992px) {
            .sidebar { width: 260px; }
            .main { margin-left: 260px; }
        }

        @media (max-width: 768px) {
            .sidebar { width: 85px; }
            .sidebar .brand h4,
            .nav-item span,
            .logout-btn span,
            .permission-note { display: none; }
            .main { margin-left: 85px; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="brand">
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
            <a href="teacher_dash.php" class="nav-item active">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item">
                <i class="bi bi-book-fill"></i>
                <span>My Subjects</span>
            </a>
            <a href="mark_attendance.php" class="nav-item">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>
            <a href="#" class="nav-item">
                <i class="bi bi-pencil-square"></i>
                <span>Marks Entry</span>
            </a>
            <a href="upload_notes.php" class="nav-item">
                <i class="bi bi-cloud-arrow-up"></i>
                <span>Study Materials</span>
            </a>
            <a href="#" class="nav-item">
                <i class="bi bi-megaphone-fill"></i>
                <span>Notices</span>
            </a>
            <!-- Admin Permission Option -->
            <a href="#" class="nav-item">
                <i class="bi bi-shield-lock"></i>
                <span>Admin Permissions</span>
            </a>
        </div>

        <!-- Permission Note -->
        <div class="permission-note">
            <i class="bi bi-info-circle me-2"></i>
            All menu access is controlled by Admin
        </div>

        <!-- Logout -->
        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-mortarboard-fill fs-4" style="color:#e89a4a;"></i>
                <h5 class="mb-0 fw-semibold" style="color:#3f2a1e;">Teacher Portal</h5>
            </div>
            
            <div class="d-flex align-items-center gap-4">
                <a href="notifiaction.php" class="text-muted fs-5">
                    <i class="bi bi-bell-fill"></i>
                </a>
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" 
                         style="width: 46px; height: 46px; background: #ffbe7a; color: #3f2a1e; font-size: 1.2rem;">
                        <?php echo strtoupper(substr($name, 0, 2)); ?>
                    </div>
                    <div>
                        <span class="fw-semibold d-block" style="color:#3f2a1e;"><?php echo htmlspecialchars($name); ?></span>
                        <small style="color:#8c6f4e;">Science Department</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Welcome Hero -->
        <div class="result-hero">
            <h2 class="display-6 fw-bold mb-3">Welcome back, <?php echo htmlspecialchars($name); ?>! 👋</h2>
            <p class="fs-5" style="opacity: 0.9;">You have <strong>3 classes</strong> today. Wishing you a calm and productive day!</p>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="label">My Subjects</div>
                    <h3 id="counter-subjects">0</h3>
                    <small class="text-muted">Across 4 courses</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="label">Today’s Classes</div>
                    <h3 id="counter-classes">0</h3>
                    <small class="text-muted">1 Lab session</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="label">Total Students</div>
                    <h3 id="counter-students">0</h3>
                    <small class="text-muted">Under your guidance</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-card">
                    <div class="label">Pending Attendance</div>
                    <h3 id="counter-pending" style="color:#e89a4a;">0</h3>
                    <small class="text-muted">Yesterday</small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <!-- Quick Actions with Clickable Links -->
<div class="row g-4 mb-5">
    <div class="col-md-3 col-sm-6">
        <a href="mark_attendance.php" class="text-decoration-none">
            <div class="kpi-card text-start">
                <i class="bi bi-calendar-check fs-2 mb-3" style="color:#2e7d32;"></i>
                <h6 class="fw-semibold">Take Attendance</h6>
                <p class="small text-muted">Mark today's classes</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6">
        <a href="marks_entry.php" class="text-decoration-none">
            <div class="kpi-card text-start">
                <i class="bi bi-pencil-square fs-2 mb-3" style="color:#e89a4a;"></i>
                <h6 class="fw-semibold">Enter Marks</h6>
                <p class="small text-muted">Internal assessments</p>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-sm-6">
        <a href="upload_notes.php" class="text-decoration-none">
            <div class="kpi-card text-start">
                <i class="bi bi-cloud-arrow-up fs-2 mb-3" style="color:#e89a4a;"></i>
                <h6 class="fw-semibold">Upload Materials</h6>
                <p class="small text-muted">Notes & resources</p>
            </div>
        </a>
    </div>

    <div class="col-md-3 col-sm-6">
 <a href="teacher_chat.php" class="text-decoration-none">
    <div class="kpi-card text-start">
        <i class="fa-solid fa-comments fs-2 mb-3" style="color:#e89a4a;"></i>
        <h6 class="fw-semibold">Parent Chat</h6>
        <p class="small text-muted">Chat with parents in real time</p>
    </div>
</a>
</div>

</div>

        <!-- Assigned Subjects -->
        <div class="content-card">
            <h4 class="fw-bold mb-2"><i class="bi bi-book me-2" style="color:#e89a4a;"></i>Assigned Subjects</h4>
            <p class="text-muted small mb-4">Subjects assigned by the Administration</p>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Type</th>
                            <th class="text-center">Students</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Physics</strong></td>
                            <td>PUC Science</td>
                            <td>1st Year</td>
                            <td>Theory</td>
                            <td class="text-center">120</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Open</button></td>
                        </tr>
                        <tr>
                            <td><strong>Physics Lab</strong></td>
                            <td>PUC Science</td>
                            <td>1st Year</td>
                            <td>Practical</td>
                            <td class="text-center">120</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Open</button></td>
                        </tr>
                        <tr>
                            <td><strong>Mathematics</strong></td>
                            <td>PUC Science</td>
                            <td>2nd Year</td>
                            <td>Theory</td>
                            <td class="text-center">110</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Open</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Timetable & Notices -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="content-card">
                    <h4><i class="bi bi-calendar-week me-2" style="color:#e89a4a;"></i>Today's Timetable</h4>
                    <hr>
                    <div class="list-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Physics</strong>
                                <div class="small text-muted mt-1">09:00 AM - 10:00 AM | Room 204</div>
                            </div>
                            <span class="chip chip-success">Up Next</span>
                        </div>
                    </div>
                    <div class="list-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Physics Lab</strong>
                                <div class="small text-muted mt-1">10:30 AM - 12:30 PM | Lab 2</div>
                            </div>
                            <span class="chip">Later</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="content-card">
                    <h4><i class="bi bi-megaphone-fill me-2" style="color:#e89a4a;"></i>College Notices</h4>
                    <hr>
                    <div class="list-item">
                        <strong>Midterm Exam Schedule Released</strong>
                        <div class="small text-muted mt-1">Exams starting from 15th April 2026</div>
                    </div>
                    <div class="list-item">
                        <strong>Staff Meeting</strong>
                        <div class="small text-muted mt-1">This Friday at 2:00 PM, Conference Hall</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animated Counters
function animateCounter(id, target, duration = 1500) {
    const el = document.getElementById(id);
    if (!el) return;
    
    let start = 0;
    const increment = target / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            el.textContent = target;
            clearInterval(timer);
        } else {
            el.textContent = Math.floor(current);
        }
    }, 16);
}

window.addEventListener('load', () => {
    animateCounter('counter-subjects', 6);
    animateCounter('counter-classes', 3);
    animateCounter('counter-students', 420);
    animateCounter('counter-pending', 1);
});
</script>














<!-- ================= FLOATING AI CHATBOT ================= -->

<style>

.erp-ai-chatbot{
    position:fixed;
    right:30px;
    bottom:30px;
    z-index:99999;
}

/* Glow Ring */
.erp-ai-chatbot::before{
    content:'';
    position:absolute;
    inset:-10px;
    border-radius:50%;
    background:radial-gradient(circle,
        rgba(232,154,74,0.7),
        rgba(232,154,74,0.2),
        transparent 70%);
    animation:pulseGlow 2s infinite;
    z-index:-1;
}

@keyframes pulseGlow{
    0%{
        transform:scale(1);
        opacity:0.8;
    }
    50%{
        transform:scale(1.2);
        opacity:0.3;
    }
    100%{
        transform:scale(1);
        opacity:0.8;
    }
}

/* Main Button */
.erp-chat-btn{
    width:78px;
    height:78px;
    border:none;
    border-radius:50%;
    cursor:pointer;
    position:relative;

    background:
    linear-gradient(135deg,#ffbe7a,#e89a4a);

    box-shadow:
    0 10px 30px rgba(232,154,74,0.45),
    0 0 25px rgba(232,154,74,0.55);

    transition:0.35s ease;

    display:flex;
    align-items:center;
    justify-content:center;

    overflow:hidden;
}

/* Shine Effect */
.erp-chat-btn::after{
    content:'';
    position:absolute;
    width:180%;
    height:180%;
    background:rgba(255,255,255,0.18);
    transform:rotate(35deg);
    left:-130%;
    top:-40%;
    transition:0.8s;
}

.erp-chat-btn:hover::after{
    left:120%;
}

/* Hover */
.erp-chat-btn:hover{
    transform:translateY(-6px) scale(1.08);
    box-shadow:
    0 18px 40px rgba(232,154,74,0.55),
    0 0 45px rgba(232,154,74,0.65);
}

/* Icon */
.erp-chat-btn i{
    font-size:2rem;
    color:white;
    z-index:2;
}

/* Notification Dot */
.chat-alert-dot{
    position:absolute;
    top:8px;
    right:8px;
    width:16px;
    height:16px;
    background:#ff3b30;
    border-radius:50%;
    border:3px solid white;
    animation:blinkDot 1s infinite;
}

@keyframes blinkDot{
    0%{opacity:1;}
    50%{opacity:0.3;}
    100%{opacity:1;}
}

/* Floating Text */
.ai-tooltip{
    position:absolute;
    right:95px;
    top:50%;
    transform:translateY(-50%);
    background:white;
    color:#3f2a1e;
    padding:10px 16px;
    border-radius:14px;
    font-size:0.9rem;
    font-weight:600;
    white-space:nowrap;

    box-shadow:0 10px 25px rgba(0,0,0,0.08);

    opacity:0;
    pointer-events:none;

    transition:0.3s;
}

.erp-ai-chatbot:hover .ai-tooltip{
    opacity:1;
    right:105px;
}

/* Mobile */
@media(max-width:768px){

    .erp-chat-btn{
        width:68px;
        height:68px;
    }

    .erp-chat-btn i{
        font-size:1.7rem;
    }

    .ai-tooltip{
        display:none;
    }
}

</style>

<!-- CHATBOT BUTTON -->
<div class="erp-ai-chatbot">

    <div class="ai-tooltip">
        AUREON AI Assistant
    </div>

    <button class="erp-chat-btn"
        onclick="window.location.href='teacher_requests.php'">

        <i class="bi bi-robot"></i>

        <span class="chat-alert-dot"></span>

    </button>

</div>

</body>
</html>