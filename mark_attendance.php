<?php
session_start();

/**
 * AUREON ERP - Teacher Attendance Page
 * Cream-Orange Theme | Fixed Student Table Architecture
 */

// --- 1. DATABASE CONNECTION ---
$host = 'localhost';
$db   = 'aureon';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed.");
}

// --- 2. SECURE TEACHER IDENTIFICATION (From `users` table) ---
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id']) && !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$teacher_id = 0;
$teacher_name = 'Instructor';

if (isset($_SESSION['user_id'])) {
    $teacher_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $teacher_id = $_SESSION['id'];
}

if ($teacher_id == 0 && isset($_SESSION['email'])) {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = :email");
    $stmt->execute([':email' => $_SESSION['email']]);
    $user_data = $stmt->fetch();
    if ($user_data) {
        $teacher_id = $user_data['id'];
        $teacher_name = $user_data['full_name'];
    }
} else {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :id");
    $stmt->execute([':id' => $teacher_id]);
    $user_data = $stmt->fetch();
    if ($user_data) $teacher_name = $user_data['full_name'];
}

if ($teacher_id == 0) {
    die("Authentication Error: Teacher ID not found.");
}

// --- 3. AJAX HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(0); 
    ob_start(); 
    header('Content-Type: application/json');

    try {
        // --- LOAD STUDENTS (FIXED TO USE `students` TABLE) ---
        if ($_POST['action'] === 'load_students') {
            $course = trim($_POST['course'] ?? '');
            $year   = trim($_POST['year'] ?? '');
            $stream = trim($_POST['stream'] ?? '');

            if (empty($course)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Please select a Course to load students.']);
                exit;
            }

            // Proper Query architecture targeting the `students` table
            $sql = "
                SELECT
                    id AS pk,
                    student_id,
                    CONCAT(first_name, ' ', last_name) AS full_name
                FROM students
                WHERE status = 'Active'
                  AND course = :course
            ";

            $params = [':course' => $course];

            if (!empty($stream)) {
                $sql .= " AND stream = :stream";
                $params[':stream'] = $stream;
            }

            if (!empty($year)) {
                $sql .= " AND year = :year";
                $params[':year'] = $year;
            }

            $sql .= " ORDER BY first_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll();

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'students' => $students,
                'count' => count($students)
            ]);
            exit;
        }

        // --- SAVE ATTENDANCE ---
        if ($_POST['action'] === 'save_attendance') {
            $course    = $_POST['course'] ?? '';
            $year      = $_POST['year'] ?? ''; 
            $stream    = $_POST['stream'] ?? ''; 
            if ($stream === '') $stream = null;

            $subject   = $_POST['subject'] ?? '';
           $date = $_POST['date'] ?? date('Y-m-d');

if ($date > date('Y-m-d')) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Future attendance is not allowed.'
    ]);
    exit;
}
            $rows      = json_decode($_POST['attendance_data'] ?? '[]', true);

            if (empty($rows)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No attendance records to save.']);
                exit;
            }

            $pdo->beginTransaction();
            $check_stmt = $pdo->prepare("SELECT id FROM attendance 
WHERE student_id = ? AND attendance_date = ? AND subject = ?");

            $update_stmt = $pdo->prepare("UPDATE attendance SET status = ?, reason = ?, teacher_id = ?, course = ?, `year` = ?, stream = ? WHERE id = ?");
            $insert_stmt = $pdo->prepare("INSERT INTO attendance (student_id, teacher_id, course, `year`, stream, subject, attendance_date, status, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($rows as $r) {
                $reason_text = isset($r['reason']) ? trim($r['reason']) : '';
                if ($reason_text === '') $reason_text = null;
                
                $student_code = $r['student_id'];

$check_stmt->execute([$student_code, $date, $subject]);
                $existing = $check_stmt->fetch();

                if ($existing) {
                   $update_stmt->execute([
    $r['status'],
    $reason_text,
    intval($teacher_id),
    $course,
    $year,
    $stream,
    $existing['id']
]);
                } else {
                  $insert_stmt->execute([
    $student_code, intval($teacher_id), $course, intval($year), $stream, $subject, $date, $r['status'], $reason_text
                    ]);
                }
            }

            $pdo->commit();
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Attendance saved successfully!']);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        exit;
    }
}

// --- 4. FETCH TEACHER'S RECENT RECORDS & STATS ---
$records_stmt = $pdo->prepare("SELECT * FROM attendance WHERE teacher_id = :tid ORDER BY attendance_date DESC, created_at DESC");
$records_stmt->execute([':tid' => $teacher_id]);
$all_records = $records_stmt->fetchAll();

$total_marked = count($all_records);
$present = 0;
$absent = 0;
$late = 0;
$records_by_month = [];

foreach ($all_records as $r) {
    if ($r['status'] == 'Present') $present++;
    elseif ($r['status'] == 'Absent') $absent++;
    elseif ($r['status'] == 'Late') $late++;

    $key = date('M Y', strtotime($r['attendance_date']));
    if (!isset($records_by_month[$key])) {
        $records_by_month[$key] = ['P' => 0, 'A' => 0, 'L' => 0];
    }
    
    if ($r['status'] == 'Present') $records_by_month[$key]['P']++;
    elseif ($r['status'] == 'Absent') $records_by_month[$key]['A']++;
    elseif ($r['status'] == 'Late') $records_by_month[$key]['L']++;
}

$percentage = $total_marked > 0 ? round(($present / $total_marked) * 100, 1) : 0;

$chart_months = [];
$chart_present = [];
$chart_absent = [];
$chart_late = [];

$limited_months = array_slice($records_by_month, 0, 6, true);
$limited_months = array_reverse($limited_months, true);

foreach ($limited_months as $key => $val) {
    $chart_months[] = $key;
    $chart_present[] = $val['P'];
    $chart_absent[] = $val['A'];
    $chart_late[] = $val['L'];
}

$chart_months_json = json_encode($chart_months);
$chart_present_json = json_encode($chart_present);
$chart_absent_json = json_encode($chart_absent);
$chart_late_json = json_encode($chart_late);

$recent_records = array_slice($all_records, 0, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #fff9f0;
            --sidebar-bg: #fffaf0;
            --accent: #e89a4a;
            --accent-light: #ffe6c7;
            --accent-hover: #d4833b;
            --text: #3f2a1e;
            --muted: #8c6f4e;
            --card: #ffffff;
            --border: rgba(0,0,0,0.05);
            --shadow: 0 10px 30px rgba(63, 42, 30, 0.06);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 290px; background: var(--sidebar-bg); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; padding: 25px 20px; position: fixed; height: 100vh; z-index: 100;
            box-shadow: 2px 0 15px rgba(0,0,0,0.03);
        }

        .logo {
            width: 120px; height: 120px; margin: 0 auto 18px; border-radius: 28px;
            background: linear-gradient(135deg, #ffe6c7, #ffbe7a);
            display: flex; align-items: center; justify-content: center; position: relative;
            box-shadow: 0 12px 35px rgba(232,154,74,0.25); transition: 0.4s ease;
        }
        .logo:hover { transform: translateY(-5px) scale(1.03); box-shadow: 0 18px 45px rgba(232,154,74,0.35); }
        .logo-text { font-size: 4.8rem; font-weight: 900; color: #3f2a1e; line-height: 1; text-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .logo-cap { position: absolute; top: 16px; right: 20px; font-size: 1.7rem; color: #3f2a1e; transform: rotate(-12deg); filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15)); }
        
        .brand-title { font-size: 1.25rem; font-weight: 800; color: var(--text); letter-spacing: 0.5px; text-align: center; }
        .brand-sub { font-size: 0.85rem; color: var(--accent); font-weight: 600; margin-bottom: 30px; text-align: center; }

        .nav-menu { list-style: none; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 13px 16px; border-radius: var(--radius-sm);
            color: var(--muted); text-decoration: none; font-weight: 600; font-size: 0.95rem;
            transition: all 0.25s ease; cursor: pointer; border: none; background: transparent; width: 100%;
        }
        .nav-item:hover { background: rgba(232,154,74,0.08); color: var(--accent); transform: translateX(4px); }
        .nav-item.active { background: var(--accent-light); color: var(--accent); }
        .nav-item i { width: 24px; font-size: 1.15rem; text-align: center; }

        .info-card { background: linear-gradient(135deg, #fff5e6, #fffaf0); border: 1px solid var(--accent-light); border-radius: var(--radius-md); padding: 16px; margin-bottom: 15px; text-align: center; }
        .info-card p { font-size: 0.8rem; color: var(--muted); margin: 0; }
        .logout-btn { color: #dc2626; font-weight: 700; margin-top: auto; }
        .logout-btn:hover { background: rgba(220,38,38,0.08); color: #dc2626; }

        /* --- MAIN --- */
        .main { flex: 1; margin-left: 290px; padding: 25px 35px; min-width: 0; }

        /* --- TOP BAR --- */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .topbar-left h2 { font-size: 1.4rem; font-weight: 800; color: var(--text); }
        .topbar-left span { color: var(--muted); font-size: 0.9rem; }
        .topbar-right { display: flex; align-items: center; gap: 18px; }
        
        .notif-icon {
            width: 44px; height: 44px; border-radius: 50%; background: var(--card); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center; color: var(--accent); cursor: pointer;
            box-shadow: var(--shadow); font-size: 1.1rem; transition: 0.3s;
        }
        .notif-icon:hover { transform: scale(1.05); background: var(--accent); color: white; }
        
        .profile-chip {
            display: flex; align-items: center; gap: 12px; background: var(--card); padding: 6px 16px 6px 6px;
            border-radius: 50px; box-shadow: var(--shadow); border: 1px solid var(--border);
        }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #f59e0b); color: white;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem;
        }
        .profile-chip div { line-height: 1.2; }
        .profile-chip strong { font-size: 0.9rem; color: var(--text); }
        .profile-chip small { font-size: 0.75rem; color: var(--muted); }

        /* --- HEADER CARD --- */
        .welcome-card {
            background: linear-gradient(135deg, var(--accent-light), #fff5e6); border-radius: 30px; padding: 35px 40px; margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 30px rgba(232,154,74,0.12); border: 1px solid rgba(232,154,74,0.15);
        }
        .welcome-card h1 { font-size: 1.8rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .welcome-card p { color: var(--muted); font-size: 1.05rem; }
        .welcome-icon { font-size: 3rem; opacity: 0.25; color: var(--accent); }

        /* --- FILTER CARD --- */
        .filter-card {
            background: var(--card); border-radius: var(--radius-lg); padding: 25px 30px; box-shadow: var(--shadow); margin-bottom: 25px; border: 1px solid var(--border);
        }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 18px; align-items: end; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 700; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control {
            width: 100%; padding: 12px 14px; border-radius: var(--radius-sm); border: 1.5px solid #f0e6d6;
            background: #fdfbf7; color: var(--text); font-size: 0.95rem; outline: none; transition: 0.2s;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(232,154,74,0.12); }
        .btn-load {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover)); color: white; border: none;
            padding: 12px 24px; border-radius: var(--radius-sm); font-weight: 700; cursor: pointer;
            box-shadow: 0 6px 15px rgba(232,154,74,0.25); transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; height: fit-content;
        }
        .btn-load:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(232,154,74,0.3); }

        /* --- TABLE CARD --- */
        .table-card { background: var(--card); border-radius: var(--radius-lg); padding: 25px 30px; box-shadow: var(--shadow); border: 1px solid var(--border); display: none; }
        .table-card.show { display: block; animation: fadeUp 0.4s ease; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(10px);} to { opacity:1; transform: translateY(0);} }

        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-header h3 { font-size: 1.15rem; font-weight: 700; }
        .badge-count { background: var(--accent-light); color: var(--accent); padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; }

        .table-wrap { overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: linear-gradient(180deg, #fff8f0, #fff5e6); color: var(--accent); font-weight: 700; padding: 14px 16px; text-align: left; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--accent-light); }
        td { padding: 14px 16px; border-bottom: 1px solid #f5efe6; vertical-align: middle; }
        tr:hover { background: #fffbf5; }
        .student-name { font-weight: 700; color: var(--text); }
        .roll-no { font-family: monospace; color: var(--muted); font-weight: 600; background: #fdf6ed; padding: 4px 10px; border-radius: 6px; display: inline-block; }

        /* --- RADIO BUTTONS (P/A/L) --- */
        .radio-group { display: flex; gap: 10px; }
        .radio-group input { display: none; }
        .radio-group label {
            width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.85rem; cursor: pointer; border: 2px solid transparent; transition: 0.25s;
            background: #f5f5f4; color: #a8a29e;
        }
        .radio-group input:checked + label.present { background: #dcfce7; color: #166534; border-color: #86efac; box-shadow: 0 4px 10px rgba(22,101,52,0.15); }
        .radio-group input:checked + label.absent { background: #fee2e2; color: #991b1b; border-color: #fca5a5; box-shadow: 0 4px 10px rgba(153,27,27,0.15); }
        .radio-group input:checked + label.late { background: #fef9c3; color: #854d0e; border-color: #fde047; box-shadow: 0 4px 10px rgba(133,77,14,0.15); }
        .radio-group label:hover { transform: scale(1.08); }

        .remarks-input { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1.5px solid #f0e6d6; background: #fdfbf7; font-size: 0.9rem; outline: none; transition: 0.2s; }
        .remarks-input:focus { border-color: var(--accent); }

        .btn-save { background: linear-gradient(135deg, var(--accent), var(--accent-hover)); color: white; border: none; padding: 14px 32px; border-radius: var(--radius-sm); font-weight: 700; font-size: 1rem; cursor: pointer; box-shadow: 0 8px 20px rgba(232,154,74,0.25); transition: 0.3s; display: inline-flex; align-items: center; gap: 10px; margin-top: 20px; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(232,154,74,0.3); }

        /* --- TOAST --- */
        .toast-container { position: fixed; top: 25px; right: 25px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { background: var(--card); border-left: 4px solid var(--accent); padding: 16px 20px; border-radius: 14px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px; min-width: 320px; animation: slideIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275); font-weight: 600; }
        .toast.success { border-left-color: #10b981; }
        .toast.error { border-left-color: #ef4444; }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* --- LOADER --- */
        .loader { display: none; text-align: center; padding: 40px; color: var(--muted); }
        .spinner { width: 40px; height: 40px; border: 4px solid var(--accent-light); border-top-color: var(--accent); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 10px;">
            <div class="logo">
                <span class="logo-text">A</span>
                <i class="fa-solid fa-graduation-cap logo-cap"></i>
            </div>
            <div class="brand-title">AUREON</div>
            <div class="brand-sub">Teacher Portal</div>
        </div>

        <nav class="nav-menu">
            <a class="nav-item" href="teacher_dashboard.php"><i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span></a>
            <a class="nav-item" href="#"><i class="fa-solid fa-book-open"></i> <span>My Subjects</span></a>
            <a class="nav-item active" href="teacher_attendance.php"><i class="fa-solid fa-clipboard-user"></i> <span>Attendance</span></a>
            <a class="nav-item" href="marks_entry.php"><i class="fa-solid fa-pen-to-square"></i> <span>Marks Entry</span></a>
            <a class="nav-item" href="#"><i class="fa-solid fa-file-lines"></i> <span>Study Materials</span></a>
            <a class="nav-item" href="#"><i class="fa-solid fa-bullhorn"></i> <span>Notices</span></a>
        </nav>

        <div class="info-card">
            <p><i class="fa-solid fa-lock" style="margin-right: 6px;"></i>Controlled by Admin</p>
        </div>
        <a href="logout.php" class="nav-item logout-btn"><i class="fa-solid fa-power-off"></i> <span>Logout</span></a>
    </aside>

    <main class="main">

        <div class="topbar">
            <div class="topbar-left">
                <h2>Teacher Portal</h2>
                <span>Academic Session 2025-26</span>
            </div>
            <div class="topbar-right">
                <div class="notif-icon"><i class="fa-regular fa-bell"></i></div>
                <div class="profile-chip">
                    <div class="avatar"><?= strtoupper(substr($teacher_name, 0, 1)) ?></div>
                    <div>
                        <strong><?= htmlspecialchars($teacher_name) ?></strong><br>
                        <small>ID: <?= htmlspecialchars($teacher_id) ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="welcome-card">
            <div>
                <h1>Take Attendance 👋</h1>
                <p>Select class details and mark today's attendance for your students.</p>
            </div>
            <div class="welcome-icon"><i class="fa-solid fa-clipboard-check"></i></div>
        </div>

        <div class="filter-card">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Course</label>
                    <select id="course" class="form-control" onchange="toggleStream()">
                        <option value="">Select Course</option>
                        <option value="PUC">PUC</option>
                        <option value="BCA">BCA</option>
                        <option value="MCA">MCA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Year</label>
                    <select id="year" class="form-control">
                        <option value="">Select Year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                    </select>
                </div>

                <div class="form-group" id="streamWrap" style="display:none;">
                    <label>Stream</label>
                    <select id="stream" class="form-control">
                        <option value="">Select Stream</option>
                        <option value="Science">Science</option>
                        <option value="Commerce">Commerce</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <select id="subject" class="form-control">
                        <option value="">Select Subject</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Physics">Physics</option>
                        <option value="Chemistry">Chemistry</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="English">English</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="attDate" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <button class="btn-load" id="loadBtn" onclick="loadStudents()">
                        <i class="fa-solid fa-users"></i> Load List
                    </button>
                </div>
            </div>
        </div>

        <div class="loader" id="loader">
            <div class="spinner"></div>
            <p>Loading active students...</p>
        </div>

        <div class="table-card" id="tableCard">
            <div class="table-header">
                <h3><i class="fa-solid fa-list-check" style="color: var(--accent); margin-right: 8px;"></i>Student Attendance List</h3>
                <span class="badge-count" id="studentCount">Total Students: 0</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th style="text-align:center;">Status (P / A / L)</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                    </tbody>
                </table>
            </div>
            <div style="text-align: right;">
                <button class="btn-save" id="saveBtn" onclick="saveAttendance()">
                    <i class="fa-solid fa-floppy-disk"></i> Save Attendance
                </button>
            </div>
        </div>

    </main>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function toggleStream() {
            const course = document.getElementById('course').value;
            const streamWrap = document.getElementById('streamWrap');
            if (course === 'PUC') {
                streamWrap.style.display = 'block';
            } else {
                streamWrap.style.display = 'none';
                document.getElementById('stream').value = '';
            }
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}" style="color: ${type==='success'?'#10b981':'#ef4444'}; font-size: 1.2rem;"></i><span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(120%)'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        async function loadStudents() {
            const course = document.getElementById('course').value;
            const year = document.getElementById('year').value;
            const stream = document.getElementById('stream').value;
            const subject = document.getElementById('subject').value;
            
            if (!course || !subject || !year) {
                showToast('Please select Course, Year, and Subject.', 'error');
                return;
            }

            document.getElementById('loader').style.display = 'block';
            document.getElementById('tableCard').classList.remove('show');

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'load_students', course, year, stream })
                });
                
                const responseText = await res.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch(e) {
                    console.error("Server HTML Error:", responseText);
                    document.getElementById('loader').style.display = 'none';
                    showToast('Server error check console logs.', 'error');
                    return;
                }

                document.getElementById('loader').style.display = 'none';

                if (!data.success) {
                    showToast(data.message, 'error');
                    return;
                }

                const tbody = document.getElementById('studentTableBody');
                tbody.innerHTML = '';

                if (data.students.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 30px; color: var(--muted);">No active students found for this class.</td></tr>';
                } else {
                    data.students.forEach((s) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>
                                <span class="roll-no">${s.student_id || 'N/A'}</span>
                                <input type="hidden" class="student-id" value="${s.student_id}">
                            </td>
                            <td class="student-name">${s.full_name}</td>
                            <td>
                                <div class="radio-group" style="justify-content: center;">
                                    <input type="radio" name="status_${s.pk}" id="p_${s.pk}" value="Present" checked>
                                    <label for="p_${s.pk}" class="present" title="Present">P</label>

                                    <input type="radio" name="status_${s.pk}" id="a_${s.pk}" value="Absent">
                                    <label for="a_${s.pk}" class="absent" title="Absent">A</label>

                                    <input type="radio" name="status_${s.pk}" id="l_${s.pk}" value="Late">
                                    <label for="l_${s.pk}" class="late" title="Late">L</label>
                                </div>
                            </td>
                            <td><input type="text" class="remarks-input" id="reason_${s.pk}" placeholder="Optional remark"></td>
                        `;
                        tbody.appendChild(row);
                    });
                }
                document.getElementById('studentCount').textContent = `Total Students: ${data.count}`;
                document.getElementById('tableCard').classList.add('show');

            } catch (err) {
                document.getElementById('loader').style.display = 'none';
                showToast('Failed to load students. Network Error.', 'error');
            }
        }

        async function saveAttendance() {
    const course = document.getElementById('course').value;
    const year = document.getElementById('year').value;
    const stream = document.getElementById('stream').value;
    const subject = document.getElementById('subject').value;
    const date = document.getElementById('attDate').value;

    const rows = [];

    document.querySelectorAll('#studentTableBody tr').forEach(tr => {
        const studentId = tr.querySelector('.student-id')?.value;
        if (!studentId) return;

        const status = tr.querySelector(`input[name="status_${studentId}"]:checked`)?.value || 'Present';
        const reason = tr.querySelector(`#reason_${studentId}`)?.value || '';

        rows.push({
            student_id: studentId,
            status,
            reason
        });
    });

    if (rows.length === 0) {
        showToast('No students to save.', 'error');
        return;
    }

    const btn = document.getElementById('saveBtn');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    try {
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'save_attendance',
                course,
                year,
                stream,
                subject,
                date,
                attendance_data: JSON.stringify(rows)
            })
        });

        const text = await res.text();

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            showToast('Server error. Check console.', 'error');
            console.log(text);
            return;
        }

        showToast(data.message, data.success ? 'success' : 'error');

    } catch (err) {
        showToast('Network error while saving.', 'error');
        console.error(err);
    } finally {
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Attendance';
        btn.disabled = false;
    }
}
    </script>
</body>
</html>