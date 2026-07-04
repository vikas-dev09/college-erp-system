<?php
session_start();

/*
|--------------------------------------------------------------------------
| AUREON ERP - Marks Entry Module
|--------------------------------------------------------------------------
| Fixed logic:
| - PUC => Stream first, then Year, then Subject, then Exam Type
| - BCA / MCA => Year, Semester, Subject, Exam Type
| - Saves teacher_id + teacher_name in internal_marks
| - Uses students.id as internal_marks.student_id (Standardized variable name)
| - Uses teachers.id as internal_marks.teacher_id
|--------------------------------------------------------------------------
*/

$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/* -------------------------------------------------------
   Helpers
-------------------------------------------------------- */
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function resolveMaxMarks($examType) {
    $map = [
        'Internal'   => 40,
        'Assignment' => 20,
        'Midterm'    => 50,
        'Unit Test'  => 25,
        'Final'      => 100,
    ];
    return $map[$examType] ?? 40;
}

function resolvePassMarks($max) {
    return (int) round($max * 0.4);
}

function subjectList($course, $stream = null) {
    if ($course === 'PUC') {
        if ($stream === 'Science') {
            return ['Physics', 'Chemistry', 'Mathematics', 'Biology', 'English'];
        }
        if ($stream === 'Commerce') {
            return ['Accountancy', 'Business Studies', 'Economics', 'Statistics', 'English'];
        }
        return [];
    }

    if ($course === 'BCA') {
        return [
            'Programming in C',
            'Data Structures',
            'DBMS',
            'Operating Systems',
            'Java',
            'Web Technology',
            'Computer Networks'
        ];
    }

    if ($course === 'MCA') {
        return [
            'Advanced Java',
            'Cloud Computing',
            'AI Basics',
            'Data Mining',
            'Software Engineering',
            'Cyber Security'
        ];
    }

    return [];
}

function yearsForCourse($course) {
    if ($course === 'PUC') return [1, 2];
    if ($course === 'BCA') return [1, 2, 3];
    if ($course === 'MCA') return [1, 2];
    return [];
}

function semestersForCourseYear($course, $year) {
    $year = (int)$year;

    if ($course === 'BCA') {
        if ($year === 1) return [1, 2];
        if ($year === 2) return [3, 4];
        if ($year === 3) return [5, 6];
    }

    if ($course === 'MCA') {
        if ($year === 1) return [1, 2];
        if ($year === 2) return [3, 4];
    }

    return [];
}

/**
 * Try to resolve current teacher from session.
 * Fallback to first teacher record if session is not set (demo-safe).
 */
// --- SECURE TEACHER IDENTIFICATION (From `users` table) ---
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id']) && !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$teacher_db_id = 0;
$teacher_name = 'Instructor';
$teacher_code = '';

// Check session
if (isset($_SESSION['user_id'])) {
    $teacher_db_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $teacher_db_id = $_SESSION['id'];
}

// Map against the `users` table
if ($teacher_db_id == 0 && isset($_SESSION['email'])) {
    $stmt = $pdo->prepare("SELECT id, full_name, reference_id FROM users WHERE email = :email AND role = 'teacher'");
    $stmt->execute([':email' => $_SESSION['email']]);
    $user_data = $stmt->fetch();
    if ($user_data) {
        $teacher_db_id = $user_data['id'];
        $teacher_name = $user_data['full_name'];
        $teacher_code = $user_data['reference_id'];
    }
} else {
    $stmt = $pdo->prepare("SELECT full_name, reference_id FROM users WHERE id = :id AND role = 'teacher'");
    $stmt->execute([':id' => $teacher_db_id]);
    $user_data = $stmt->fetch();
    if ($user_data) {
        $teacher_name = $user_data['full_name'];
        $teacher_code = $user_data['reference_id'];
    }
}

if ($teacher_db_id == 0) {
    die(json_encode(['success' => false, 'message' => 'Authentication Error: Logged in user is not a valid teacher.']));
}

$currentTeacher = [
    'db_id' => $teacher_db_id,
    'code'  => $teacher_code ?: 'TCH' . $teacher_db_id, // Fallback code if reference_id is empty
    'name'  => $teacher_name,
];
/* -------------------------------------------------------
   One-time schema safety
-------------------------------------------------------- */
try {
    $pdo->query("SELECT stream FROM internal_marks LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE internal_marks ADD COLUMN stream VARCHAR(20) NULL AFTER course");
    } catch (Throwable $ignored) {}
}

try {
    $pdo->query("SELECT teacher_name FROM internal_marks LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE internal_marks ADD COLUMN teacher_name VARCHAR(150) NULL AFTER teacher_id");
    } catch (Throwable $ignored) {}
}

try {
    $pdo->exec("ALTER TABLE internal_marks ADD UNIQUE KEY uniq_marks_entry (student_id, course, year, semester, subject, exam_type)");
} catch (Throwable $ignored) {}

/* -------------------------------------------------------
   AJAX: get_students
-------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_students') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $course    = trim($_POST['course'] ?? '');
        $stream    = trim($_POST['stream'] ?? '');
        $year      = (int)($_POST['year'] ?? 0);
        $semester  = (int)($_POST['semester'] ?? 0);
        $subject   = trim($_POST['subject'] ?? '');
        $examType  = trim($_POST['exam_type'] ?? '');

        if ($course === '' || $year <= 0 || $subject === '' || $examType === '') {
            echo json_encode(['success' => false, 'message' => 'Please select all required filters.']);
            exit;
        }

        if ($course === 'PUC' && $stream === '') {
            echo json_encode(['success' => false, 'message' => 'Please select stream for PUC.']);
            exit;
        }

        if (($course === 'BCA' || $course === 'MCA') && $semester <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select semester.']);
            exit;
        }

        if ($course === 'PUC' && $semester <= 0) {
            // PUC term is derived from year to satisfy internal_marks semester field
            $semester = $year;
        }

        $maxMarks  = resolveMaxMarks($examType);
        $passMarks = resolvePassMarks($maxMarks);

        $params = [
            ':course'   => $course,
            ':year'     => $year,
            ':semester' => $semester,
            ':subject'  => $subject,
            ':exam_type'=> $examType,
        ];

        $where = [
            "s.status = 'Active'",
            "s.course = :course",
            "s.year = :year"
        ];

        if ($course === 'PUC') {
            $where[] = "s.stream = :stream";
            $params[':stream'] = $stream;
        }

        $joinCondition = "
            im.student_id = s.id
            AND im.course = :course
            AND im.year = :year
            AND im.semester = :semester
            AND im.subject = :subject
            AND im.exam_type = :exam_type
        ";

        if ($course === 'PUC') {
            $joinCondition .= " AND (im.stream = :stream OR im.stream IS NULL)";
        }

        // ✅ FIX 2: Standardized student_id here instead of student_pk
        $sql = "
            SELECT
                s.id AS student_id,
                s.student_id AS roll_no,
                s.full_name AS student_name,
                COALESCE(im.marks_obtained, '') AS marks_obtained,
                COALESCE(im.remarks, '') AS remarks,
                COALESCE(im.status, '') AS status,
                im.teacher_id,
                im.teacher_name
            FROM students s
            LEFT JOIN internal_marks im ON {$joinCondition}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.student_id ASC
        ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        $locked = false;
        $savedTeacherName = '';
        $savedTeacherId = '';

        foreach ($rows as $r) {
            if (($r['status'] ?? '') === 'submitted') {
                $locked = true;
            }
            if (!$savedTeacherName && !empty($r['teacher_name'])) {
                $savedTeacherName = $r['teacher_name'];
            }
            if (!$savedTeacherId && !empty($r['teacher_id'])) {
                $savedTeacherId = $r['teacher_id'];
            }
        }

        if ($savedTeacherName === '') {
            $savedTeacherName = $currentTeacher['name'];
            $savedTeacherId = $currentTeacher['code'];
        }

        echo json_encode([
            'success' => true,
            'students' => $rows,
            'max_marks' => $maxMarks,
            'pass_marks' => $passMarks,
            'locked' => $locked,
            'saved_teacher_name' => $savedTeacherName,
            'saved_teacher_id' => $savedTeacherId,
            'teacher_name' => $currentTeacher['name'],
            'teacher_code' => $currentTeacher['code'],
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

/* -------------------------------------------------------
   AJAX: save_marks
-------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        if (!$currentTeacher['db_id']) {
            echo json_encode(['success' => false, 'message' => 'Teacher session not found. Please log in again.']);
            exit;
        }

        $course    = trim($_POST['course'] ?? '');
        $stream    = trim($_POST['stream'] ?? '');
        $year      = (int)($_POST['year'] ?? 0);
        $semester  = (int)($_POST['semester'] ?? 0);
        $subject   = trim($_POST['subject'] ?? '');
        $examType  = trim($_POST['exam_type'] ?? '');
        $status    = trim($_POST['status'] ?? 'draft');

        $marksData = json_decode($_POST['marks_data'] ?? '[]', true);

        if (!in_array($status, ['draft', 'submitted'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid submission status.']);
            exit;
        }

        if ($course === '' || $year <= 0 || $subject === '' || $examType === '') {
            echo json_encode(['success' => false, 'message' => 'Please select all required filters.']);
            exit;
        }

        if ($course === 'PUC' && $stream === '') {
            echo json_encode(['success' => false, 'message' => 'Please select stream for PUC.']);
            exit;
        }

        if ($course === 'PUC' && $semester <= 0) {
            $semester = $year;
        }

        if (($course === 'BCA' || $course === 'MCA') && $semester <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select semester.']);
            exit;
        }

        if (!is_array($marksData) || empty($marksData)) {
            echo json_encode(['success' => false, 'message' => 'No student rows found to save.']);
            exit;
        }

        $maxMarks  = resolveMaxMarks($examType);
        $passMarks = resolvePassMarks($maxMarks);

        // If already submitted for same batch, lock it
        $lockCheckSql = "
            SELECT COUNT(*) AS cnt
            FROM internal_marks
            WHERE course = :course
              AND year = :year
              AND semester = :semester
              AND subject = :subject
              AND exam_type = :exam_type
              AND status = 'submitted'
        ";
        $lockParams = [
            ':course' => $course,
            ':year' => $year,
            ':semester' => $semester,
            ':subject' => $subject,
            ':exam_type' => $examType
        ];

        if ($course === 'PUC') {
            $lockCheckSql .= " AND stream = :stream";
            $lockParams[':stream'] = $stream;
        }

        $lockSt = $pdo->prepare($lockCheckSql);
        $lockSt->execute($lockParams);
        $lockRow = $lockSt->fetch();

        if (!empty($lockRow['cnt'])) {
            echo json_encode(['success' => false, 'message' => 'This marks sheet is already submitted and locked.']);
            exit;
        }

        // Validate final submission
        if ($status === 'submitted') {
            foreach ($marksData as $item) {
                $m = trim((string)($item['marks'] ?? ''));
                if ($m === '') {
                    echo json_encode(['success' => false, 'message' => 'Final submit requires marks for every student.']);
                    exit;
                }
                if (!is_numeric($m) || $m < 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid marks value found.']);
                    exit;
                }
                if ($m > $maxMarks) {
                    echo json_encode(['success' => false, 'message' => "Marks cannot exceed {$maxMarks}."]);
                    exit;
                }
            }
        }

        $pdo->beginTransaction();

        $upsertSql = "
            INSERT INTO internal_marks
                (student_id, teacher_id, teacher_name, course, stream, year, semester, subject, exam_type, marks_obtained, max_marks, remarks, status)
            VALUES
                (:student_id, :teacher_id, :teacher_name, :course, :stream, :year, :semester, :subject, :exam_type, :marks_obtained, :max_marks, :remarks, :status)
            ON DUPLICATE KEY UPDATE
                teacher_id = VALUES(teacher_id),
                teacher_name = VALUES(teacher_name),
                stream = VALUES(stream),
                marks_obtained = VALUES(marks_obtained),
                max_marks = VALUES(max_marks),
                remarks = VALUES(remarks),
                status = VALUES(status),
                updated_at = NOW()
        ";

        $st = $pdo->prepare($upsertSql);
        $saved = 0;

        foreach ($marksData as $item) {
            // ✅ FIX 1: Standardized variable fetch from JSON package
            $studentId = (int)($item['student_id'] ?? 0);
            $marksVal  = trim((string)($item['marks'] ?? ''));
            $remarks   = trim((string)($item['remarks'] ?? ''));

            if ($studentId <= 0) {
                throw new Exception("Invalid student row detected.");
            }

            if ($marksVal !== '') {
                if (!is_numeric($marksVal)) {
                    throw new Exception("Invalid marks value for one of the students.");
                }
                if ($marksVal < 0) {
                    throw new Exception("Marks cannot be negative.");
                }
                if ($marksVal > $maxMarks) {
                    throw new Exception("Marks cannot exceed {$maxMarks}.");
                }
            }

            if ($status === 'submitted' && $marksVal === '') {
                throw new Exception("Final submit requires all marks.");
            }

            $st->execute([
                ':student_id'   => $studentId, // ✅ Bound perfectly to match DB Schema Primary Key mapping
                ':teacher_id'   => $currentTeacher['db_id'],
                ':teacher_name' => $currentTeacher['name'],
                ':course'       => $course,
                ':stream'       => $course === 'PUC' ? $stream : null,
                ':year'         => $year,
                ':semester'     => $semester,
                ':subject'      => $subject,
                ':exam_type'    => $examType,
                ':marks_obtained' => ($marksVal === '' ? null : (int)$marksVal),
                ':max_marks'    => $maxMarks,
                ':remarks'      => ($remarks === '' ? null : $remarks),
                ':status'       => $status,
            ]);

            $saved++;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $status === 'submitted'
                ? "Marks submitted successfully and locked."
                : "Draft saved successfully.",
            'locked' => $status === 'submitted',
            'saved' => $saved
        ]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Normal page render
$teacherDisplayName = $currentTeacher['name'] ?: 'Teacher';
$teacherDisplayCode = $currentTeacher['code'] ?: ('ID ' . ($currentTeacher['db_id'] ?? 'N/A'));
$teacherInitial = strtoupper(substr($teacherDisplayName, 0, 1));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUREON ERP - Internal Marks Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --bg:#fff9f0;
            --card:#ffffff;
            --accent:#e89a4a;
            --accent-dark:#d9842b;
            --text:#3f2a1e;
            --muted:#7b5d4d;
            --border:#f1e7d9;
            --success:#16a34a;
            --fail:#dc2626;
            --shadow:0 10px 30px rgba(232,154,74,.10);
            --radius:18px;
        }

        *{ box-sizing:border-box; }
        html,body{ height:100%; }
        body{
            margin:0;
            font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background:var(--bg);
            color:var(--text);
            overflow-x:hidden;
        }

        a{ text-decoration:none; }

        /* Sidebar */
        .sidebar{
            width:280px;
            position:fixed;
            inset:0 auto 0 0;
            background:linear-gradient(180deg,#fffaf4,#fff7ed);
            border-right:1px solid var(--border);
            box-shadow:2px 0 12px rgba(0,0,0,.03);
            z-index:1000;
            display:flex;
            flex-direction:column;
            padding:18px 0;
        }
        .logo-wrap{
            text-align:center;
            padding:10px 18px 22px;
            margin-bottom:8px;
            border-bottom:1px solid var(--border);
        }
        .logo-circle{
            width:110px;height:110px;border-radius:50%;
            margin:0 auto 14px;
            background:linear-gradient(135deg,#fde9d1,var(--accent));
            display:flex;align-items:center;justify-content:center;
            font-size:56px;font-weight:900;color:#fff;
            box-shadow:var(--shadow);
        }
        .brand{
            font-size:1.18rem;
            font-weight:800;
            color:var(--text);
            letter-spacing:.3px;
        }
        .brand-sub{
            color:var(--accent);
            font-size:.85rem;
            font-weight:600;
        }
        .sidebar-nav{
            list-style:none;
            padding:14px 14px 0;
            margin:0;
            display:flex;
            flex-direction:column;
            gap:6px;
            flex:1;
        }
        .sidebar-nav a{
            display:flex;align-items:center;gap:14px;
            padding:13px 16px;
            border-radius:14px;
            color:#6b4d3d;
            font-weight:600;
            transition:.25s ease;
        }
        .sidebar-nav a i{
            width:24px;
            text-align:center;
            color:var(--accent);
        }
        .sidebar-nav a:hover{
            background:rgba(232,154,74,.10);
            transform:translateX(4px);
        }
        .sidebar-nav a.active{
            background:linear-gradient(135deg,var(--accent),#f0b26c);
            color:#fff;
            box-shadow:0 8px 18px rgba(232,154,74,.25);
        }
        .sidebar-nav a.active i{ color:#fff; }
        .logout-btn{
            margin-top:auto;
            padding:12px 18px;
            color:#dc2626 !important;
            font-weight:800;
        }
        .logout-btn:hover{
            background:rgba(220,38,38,.08);
            transform:none;
        }
        .logout-btn i{ color:#dc2626 !important; }

        /* Main */
        .main{
            margin-left:280px;
            min-height:100vh;
            padding:22px 26px 90px;
        }

        /* Top navbar */
        .top-navbar{
            background:rgba(255,255,255,.86);
            backdrop-filter:blur(16px);
            border:1px solid rgba(255,255,255,.7);
            box-shadow:var(--shadow);
            border-radius:20px;
            padding:16px 20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            position:sticky;
            top:16px;
            z-index:900;
        }
        .top-title{
            display:flex;
            align-items:center;
            gap:12px;
            font-weight:800;
            font-size:1.1rem;
        }
        .top-title i{
            color:var(--accent);
            font-size:1.25rem;
        }
        .top-right{
            display:flex;
            align-items:center;
            gap:16px;
        }
        .bell{
            width:44px;height:44px;border-radius:50%;
            background:#fff;
            border:1px solid var(--border);
            display:flex;align-items:center;justify-content:center;
            color:var(--accent);
            position:relative;
            box-shadow:0 6px 16px rgba(0,0,0,.05);
        }
        .bell .dot{
            position:absolute;
            top:6px;right:6px;
            width:10px;height:10px;border-radius:50%;
            background:#ef4444;
        }
        .teacher-box{
            display:flex;
            align-items:center;
            gap:12px;
            padding:6px 12px 6px 8px;
            border-radius:999px;
            background:#fff;
            border:1px solid var(--border);
            box-shadow:0 6px 16px rgba(0,0,0,.04);
        }
        .teacher-avatar{
            width:56px;height:56px;border-radius:50%;
            background:linear-gradient(135deg,#f8b36b,#e89a4a);
            color:#fff;
            display:flex;align-items:center;justify-content:center;
            font-weight:900;
            font-size:1.2rem;
            flex:0 0 auto;
        }
        .teacher-meta{
            display:flex;
            flex-direction:column;
            line-height:1.15;
        }
        .teacher-meta strong{ font-size:.96rem; color:var(--text); }
        .teacher-meta span{ font-size:.78rem; color:var(--muted); }

        /* Cards */
        .soft-card{
            background:var(--card);
            border:1px solid rgba(0,0,0,.03);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            margin-top:20px;
            overflow:hidden;
        }
        .card-head{
            padding:18px 20px;
            border-bottom:1px solid var(--border);
            background:linear-gradient(180deg,#fffdf8,#fffaf3);
        }
        .card-head h3{
            margin:0;
            font-size:1.15rem;
            font-weight:800;
        }
        .card-head p{
            margin:4px 0 0;
            color:var(--muted);
            font-size:.92rem;
        }
        .card-body{
            padding:20px;
        }

        .banner{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
        }
        .banner h1{
            font-size:1.55rem;
            font-weight:900;
            margin:0 0 6px;
        }
        .banner p{
            margin:0;
            color:var(--muted);
        }
        .banner-icon{
            width:70px;height:70px;border-radius:18px;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#fff2e4,#ffd8ad);
            color:var(--accent);
            font-size:1.8rem;
            flex:0 0 auto;
        }

        /* Filter section */
        .filter-grid{
            display:grid;
            grid-template-columns:repeat(6,1fr);
            gap:14px;
        }
        .filter-item{
            min-width:0;
        }
        .filter-item label{
            display:block;
            font-size:.82rem;
            font-weight:800;
            color:#6b4d3d;
            margin-bottom:6px;
        }
        .form-select, .form-control{
            border-radius:14px;
            border:1px solid var(--border);
            box-shadow:none !important;
            padding:12px 14px;
            color:var(--text);
            background:#fff;
        }
        .form-select:focus, .form-control:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 .2rem rgba(232,154,74,.15) !important;
        }

        .btn-accent{
            border:none;
            border-radius:14px;
            padding:12px 18px;
            font-weight:800;
            color:#fff;
            background:linear-gradient(135deg,var(--accent),#f0b26c);
            box-shadow:0 10px 22px rgba(232,154,74,.22);
            transition:.25s;
            width:100%;
        }
        .btn-accent:hover{
            transform:translateY(-1px);
            background:linear-gradient(135deg,var(--accent-dark),var(--accent));
        }
        .btn-accent:disabled{
            opacity:.55;
            cursor:not-allowed;
            transform:none;
        }

        .hidden{ display:none !important; }

        /* Rule notice */
        .rule-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
        }
        .badge-soft{
            border-radius:999px;
            padding:8px 12px;
            font-weight:800;
            font-size:.85rem;
        }
        .badge-warm{
            background:#fff4e6;
            color:#ad6112;
            border:1px solid #f3d2a7;
        }
        .badge-green{
            background:#e9f9ef;
            color:#166534;
            border:1px solid #cdeed7;
        }
        .badge-red{
            background:#ffe8e8;
            color:#991b1b;
            border:1px solid #f6c9c9;
        }
        .badge-gray{
            background:#f2f2f2;
            color:#5b5b5b;
            border:1px solid #e4e4e4;
        }

        /* Stats */
        .stats-grid{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:16px;
        }
        .stat-card{
            background:#fff;
            border:1px solid rgba(0,0,0,.04);
            border-radius:18px;
            box-shadow:var(--shadow);
            padding:18px;
            position:relative;
            overflow:hidden;
        }
        .stat-card:before{
            content:'';
            position:absolute;
            inset:auto -20px -20px auto;
            width:90px;
            height:90px;
            border-radius:50%;
            background:rgba(232,154,74,.08);
        }
        .stat-label{
            color:var(--muted);
            font-weight:700;
            font-size:.85rem;
        }
        .stat-value{
            font-size:1.65rem;
            font-weight:900;
            margin-top:8px;
            color:var(--text);
        }

        /* Loader */
        .loader-box{
            display:none;
            text-align:center;
            padding:30px 10px;
        }
        .loader-spinner{
            width:52px;height:52px;
            border-radius:50%;
            border:5px solid #f6e4cf;
            border-top-color:var(--accent);
            margin:0 auto 12px;
            animation:spin 1s linear infinite;
        }
        @keyframes spin{ to{ transform:rotate(360deg); } }

        /* Table */
        .table-wrap{
            overflow:auto;
            border-radius:18px;
            border:1px solid var(--border);
        }
        table{
            margin:0;
            min-width:950px;
            background:#fff;
        }
        thead th{
            position:sticky;
            top:0;
            z-index:2;
            background:linear-gradient(180deg,#fff6ea,#fff2df);
            color:#8b5b2f;
            font-weight:900;
            font-size:.82rem;
            letter-spacing:.2px;
            text-transform:uppercase;
            border-bottom:1px solid var(--border) !important;
            padding:14px 12px !important;
        }
        tbody td{
            vertical-align:middle;
            border-bottom:1px solid #f5efe7 !important;
            padding:12px !important;
        }
        tbody tr{
            transition:background .18s ease;
        }
        tbody tr:hover{
            background:#fffaf4;
        }
        tbody tr.row-pass{
            background:#f0fff5;
        }
        tbody tr.row-fail{
            background:#fff1f1;
        }
        .marks-input{
            width:120px;
            text-align:center;
            font-weight:900;
            border-radius:12px;
            border:2px solid #e8e0d6;
            padding:9px 10px;
            outline:none;
        }
        .marks-input.pass{
            border-color:var(--success);
            background:#eefcf2;
            color:var(--success);
        }
        .marks-input.fail{
            border-color:var(--fail);
            background:#fff0f0;
            color:var(--fail);
        }
        .remarks-input{
            min-width:220px;
            border-radius:12px;
            border:1px solid #e8e0d6;
            padding:9px 10px;
        }
        .locked-input{
            pointer-events:none !important;
            opacity:.8;
        }

        /* Action buttons */
        .action-row{
            display:flex;
            gap:14px;
            justify-content:flex-end;
            flex-wrap:wrap;
        }
        .btn-outline-accent{
            background:#fff;
            color:var(--accent);
            border:2px solid var(--accent);
            border-radius:14px;
            padding:12px 18px;
            font-weight:800;
            transition:.25s;
        }
        .btn-outline-accent:hover{
            background:var(--accent);
            color:#fff;
            transform:translateY(-1px);
        }

        /* Toasts */
        .toast-holder{
            position:fixed;
            top:92px;
            right:20px;
            z-index:2000;
            display:flex;
            flex-direction:column;
            gap:10px;
        }
        .a-toast{
            min-width:280px;
            max-width:380px;
            background:#fff;
            border:1px solid #eee;
            border-left:5px solid var(--accent);
            border-radius:14px;
            box-shadow:0 14px 40px rgba(0,0,0,.10);
            padding:14px 16px;
            transform:translateX(120%);
            opacity:0;
            transition:.25s ease;
        }
        .a-toast.show{
            transform:translateX(0);
            opacity:1;
        }
        .a-toast.success{ border-left-color:var(--success); }
        .a-toast.error{ border-left-color:var(--fail); }

        /* Mobile */
        @media (max-width: 1200px){
            .filter-grid{ grid-template-columns:repeat(3,1fr); }
            .stats-grid{ grid-template-columns:repeat(2,1fr); }
        }
        @media (max-width: 992px){
            .sidebar{ width:80px; }
            .main{ margin-left:80px; }
            .brand, .brand-sub, .sidebar-nav span{ display:none; }
            .logo-circle{ width:58px; height:58px; font-size:28px; }
        }
        @media (max-width: 768px){
            .main{ padding:14px 12px 84px; margin-left:0; }
            .sidebar{ display:none; }
            .top-navbar{ border-radius:16px; }
            .banner{ flex-direction:column; align-items:flex-start; }
            .filter-grid{ grid-template-columns:1fr; }
            .stats-grid{ grid-template-columns:1fr; }
            .teacher-box{ padding-right:10px; }
            .teacher-meta strong{ font-size:.88rem; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-wrap">
        <div class="logo-circle">A</div>
        <div class="brand">AUREON ERP</div>
        <div class="brand-sub">Teacher Portal</div>
    </div>

    <ul class="sidebar-nav">
        <li><a href="teacher_dash.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
        <li><a href="mark_attendance.php"><i class="fa-solid fa-calendar-check"></i> <span>Attendance</span></a></li>
        <li><a href="marks_entry.php" class="active"><i class="fa-solid fa-pen-to-square"></i> <span>Marks Entry</span></a></li>
        <li><a href="upload_notes"><i class="fa-solid fa-book-open"></i> <span>Library</span></a></li>
        <li><a href="#"><i class="fa-solid fa-user-graduate"></i> <span>Students</span></a></li>
        <li><a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
    </ul>
</aside>

<main class="main">

    <div class="top-navbar">
        <div class="top-title">
            <i class="fa-solid fa-pen-ruler"></i>
            <div>Internal Marks Entry</div>
        </div>

        <div class="top-right">
            <div class="bell" title="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="dot"></span>
            </div>

            <div class="teacher-box">
                <div class="teacher-avatar"><?= h($teacherInitial) ?></div>
                <div class="teacher-meta">
                    <strong><?= h($teacherDisplayName) ?></strong>
                    <span><?= h($teacherDisplayCode) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="soft-card">
        <div class="card-body banner">
            <div>
                <h1>Internal Marks Entry</h1>
                <p>Select the academic filters, load students, enter marks, and save as draft or final submission.</p>
            </div>
            <div class="banner-icon">
                <i class="fa-solid fa-marker"></i>
            </div>
        </div>
    </div>

    <div class="soft-card">
        <div class="card-head">
            <h3><i class="fa-solid fa-sliders me-2" style="color:var(--accent)"></i>Filter Card</h3>
            <p>Follow the course flow to load students</p>
        </div>
        <div class="card-body">
            <div class="filter-grid">

                <div class="filter-item">
                    <label>Course</label>
                    <select id="course" class="form-select">
                        <option value="">Select Course</option>
                        <option value="PUC">PUC</option>
                        <option value="BCA">BCA</option>
                        <option value="MCA">MCA</option>
                    </select>
                </div>

                <div class="filter-item hidden" id="streamWrap">
                    <label>Stream</label>
                    <select id="stream" class="form-select">
                        <option value="">Select Stream</option>
                        <option value="Science">Science</option>
                        <option value="Commerce">Commerce</option>
                    </select>
                </div>

                <div class="filter-item hidden" id="yearWrap">
                    <label>Year</label>
                    <select id="year" class="form-select">
                        <option value="">Select Year</option>
                    </select>
                </div>

                <div class="filter-item hidden" id="semesterWrap">
                    <label>Semester</label>
                    <select id="semester" class="form-select">
                        <option value="">Select Semester</option>
                    </select>
                </div>

                <div class="filter-item hidden" id="subjectWrap">
                    <label>Subject</label>
                    <select id="subject" class="form-select">
                        <option value="">Select Subject</option>
                    </select>
                </div>

                <div class="filter-item hidden" id="examWrap">
                    <label>Exam Type</label>
                    <select id="examType" class="form-select">
                        <option value="">Select Exam Type</option>
                        <option value="Internal">Internal</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Unit Test">Unit Test</option>
                        <option value="Midterm">Midterm</option>
                        <option value="Final">Final</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn-accent" id="loadStudentsBtn" disabled>
                    <i class="fa-solid fa-users me-2"></i>Load Students
                </button>
            </div>
        </div>
    </div>

    <div class="soft-card">
        <div class="card-body">
            <div class="rule-row">
                <div>
                    <div class="fw-bold" style="font-size:1.02rem;">
                        <i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>
                        Mark Rules
                    </div>
                    <div class="text-muted" style="color:var(--muted)!important;">
                        Pass mark is always 40% of maximum marks.
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span class="badge-soft badge-warm" id="maxMarksBadge">Max Marks: 40</span>
                    <span class="badge-soft badge-green" id="passMarksBadge">Pass Marks: 16</span>
                    <span class="badge-soft badge-gray" id="savedByBadge">Teacher: <?= h($teacherDisplayName) ?></span>
                    <span class="badge-soft badge-gray" id="statusBadge">New</span>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-grid" id="statsGrid" style="display:none;">
        <div class="stat-card">
            <div class="stat-label">Total Students</div>
            <div class="stat-value" id="totalStudents">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Average Marks</div>
            <div class="stat-value" id="averageMarks">0.00</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Passed</div>
            <div class="stat-value" id="passedCount">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Failed</div>
            <div class="stat-value" id="failedCount">0</div>
        </div>
    </div>

    <div class="soft-card loader-box" id="loaderBox">
        <div class="card-body">
            <div class="loader-spinner"></div>
            <div class="fw-bold">Loading students...</div>
        </div>
    </div>

    <div class="soft-card" id="tableCard" style="display:none;">
        <div class="card-head d-flex justify-content-between align-items-center">
            <div>
                <h3 style="margin:0;">
                    <i class="fa-solid fa-table me-2" style="color:var(--accent)"></i>
                    Student Marks Table
                </h3>
                <p>Enter marks and remarks for each student</p>
            </div>
            <span class="badge-soft badge-gray" id="submissionStatus">New</span>
        </div>

        <div class="card-body">
            <div class="table-wrap">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Marks Input</th>
                            <th>Remarks Input</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="soft-card" id="actionsCard" style="display:none;">
        <div class="card-body">
            <div class="action-row">
                <button class="btn-outline-accent" id="saveDraftBtn" type="button">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Draft
                </button>
                <button class="btn-accent" id="submitFinalBtn" type="button">
                    <i class="fa-solid fa-paper-plane me-2"></i>Submit Final
                </button>
            </div>
        </div>
    </div>

</main>

<div class="toast-holder" id="toastHolder"></div>

<script>
const currentTeacher = <?= json_encode($currentTeacher, JSON_UNESCAPED_UNICODE) ?>;

const courseEl = document.getElementById('course');
const streamEl = document.getElementById('stream');
const yearEl = document.getElementById('year');
const semesterEl = document.getElementById('semester');
const subjectEl = document.getElementById('subject');
const examEl = document.getElementById('examType');
const loadBtn = document.getElementById('loadStudentsBtn');

const streamWrap = document.getElementById('streamWrap');
const yearWrap = document.getElementById('yearWrap');
const semesterWrap = document.getElementById('semesterWrap');
const subjectWrap = document.getElementById('subjectWrap');
const examWrap = document.getElementById('examWrap');

const loaderBox = document.getElementById('loaderBox');
const tableCard = document.getElementById('tableCard');
const actionsCard = document.getElementById('actionsCard');
const tableBody = document.getElementById('studentTableBody');

const totalStudentsEl = document.getElementById('totalStudents');
const averageMarksEl = document.getElementById('averageMarks');
const passedCountEl = document.getElementById('passedCount');
const failedCountEl = document.getElementById('failedCount');
const maxMarksBadge = document.getElementById('maxMarksBadge');
const passMarksBadge = document.getElementById('passMarksBadge');
const savedByBadge = document.getElementById('savedByBadge');
const statusBadge = document.getElementById('statusBadge');

const saveDraftBtn = document.getElementById('saveDraftBtn');
const submitFinalBtn = document.getElementById('submitFinalBtn');

let currentMaxMarks = 40;
let currentPassMarks = 16;
let isLocked = false;
let loadedCourse = '';
let loadedStream = '';
let loadedYear = '';
let loadedSemester = '';
let loadedSubject = '';
let loadedExamType = '';

function showToast(message, type='success') {
    const holder = document.getElementById('toastHolder');
    const toast = document.createElement('div');
    toast.className = `a-toast ${type}`;
    toast.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:10px;">
            <div style="margin-top:1px;color:${type==='success' ? 'var(--success)' : 'var(--fail)'};">
                <i class="fa-solid ${type==='success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i>
            </div>
            <div style="font-weight:700;color:var(--text);">${message}</div>
        </div>
    `;
    holder.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3800);
}

function showLoader(show) {
    loaderBox.style.display = show ? 'block' : 'none';
}

function resetLowerFields(from) {
    const order = ['stream','year','semester','subject','exam'];
    const idx = order.indexOf(from);
    const toHide = order.slice(idx + 1);

    if (toHide.includes('year')) {
        yearWrap.classList.add('hidden');
        yearEl.innerHTML = '<option value="">Select Year</option>';
        yearEl.value = '';
    }

    if (toHide.includes('semester')) {
        semesterWrap.classList.add('hidden');
        semesterEl.innerHTML = '<option value="">Select Semester</option>';
        semesterEl.value = '';
    }

    if (toHide.includes('subject')) {
        subjectWrap.classList.add('hidden');
        subjectEl.innerHTML = '<option value="">Select Subject</option>';
        subjectEl.value = '';
    }

    if (toHide.includes('exam')) {
        examWrap.classList.add('hidden');
        examEl.value = '';
    }

    if (from === 'stream') {
        yearWrap.classList.add('hidden');
        semesterWrap.classList.add('hidden');
        subjectWrap.classList.add('hidden');
        examWrap.classList.add('hidden');
    }

    if (from === 'year') {
        semesterWrap.classList.add('hidden');
        subjectWrap.classList.add('hidden');
        examWrap.classList.add('hidden');
    }

    if (from === 'semester') {
        subjectWrap.classList.add('hidden');
        examWrap.classList.add('hidden');
    }

    if (from === 'subject') {
        examWrap.classList.add('hidden');
    }

    updateLoadButton();
}

function clearResults() {
    tableCard.style.display = 'none';
    actionsCard.style.display = 'none';
    document.getElementById('statsGrid').style.display = 'none';
    tableBody.innerHTML = '';
    statusBadge.textContent = 'New';
    savedByBadge.textContent = `Teacher: ${currentTeacher.name}`;
    isLocked = false;
    saveDraftBtn.disabled = false;
    submitFinalBtn.disabled = false;
    [courseEl, streamEl, yearEl, semesterEl, subjectEl, examEl].forEach(el => el.disabled = false);
    loadBtn.disabled = true;
}

function setYearOptions(course) {
    const years = course === 'PUC' ? [1,2] : course === 'BCA' ? [1,2,3] : course === 'MCA' ? [1,2] : [];
    yearEl.innerHTML = '<option value="">Select Year</option>';
    years.forEach(y => {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        yearEl.appendChild(opt);
    });
}

function setSemesterOptions(course, year) {
    semesterEl.innerHTML = '<option value="">Select Semester</option>';
    const sems = [];

    if (course === 'BCA') {
        if (+year === 1) sems.push(1,2);
        if (+year === 2) sems.push(3,4);
        if (+year === 3) sems.push(5,6);
    } else if (course === 'MCA') {
        if (+year === 1) sems.push(1,2);
        if (+year === 2) sems.push(3,4);
    }

    sems.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        semesterEl.appendChild(opt);
    });
}

function setSubjectOptions(course, stream) {
    let subjects = [];
    if (course === 'PUC') {
        subjects = stream === 'Science'
            ? ['Physics', 'Chemistry', 'Mathematics', 'Biology', 'English']
            : ['Accountancy', 'Business Studies', 'Economics', 'Statistics', 'English'];
    } else if (course === 'BCA') {
        subjects = ['Programming in C', 'Data Structures', 'DBMS', 'Operating Systems', 'Java', 'Web Technology', 'Computer Networks'];
    } else if (course === 'MCA') {
        subjects = ['Advanced Java', 'Cloud Computing', 'AI Basics', 'Data Mining', 'Software Engineering', 'Cyber Security'];
    }

    subjectEl.innerHTML = '<option value="">Select Subject</option>';
    subjects.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        subjectEl.appendChild(opt);
    });
}

function setExamOptions() {
    examEl.innerHTML = `
        <option value="">Select Exam Type</option>
        <option value="Internal">Internal</option>
        <option value="Assignment">Assignment</option>
        <option value="Unit Test">Unit Test</option>
        <option value="Midterm">Midterm</option>
        <option value="Final">Final</option>
    `;
}

function updateLoadButton() {
    const course = courseEl.value;
    let ready = false;

    if (course === 'PUC') {
        ready = !!courseEl.value && !!streamEl.value && !!yearEl.value && !!subjectEl.value && !!examEl.value;
    } else if (course === 'BCA' || course === 'MCA') {
        ready = !!courseEl.value && !!yearEl.value && !!semesterEl.value && !!subjectEl.value && !!examEl.value;
    }

    loadBtn.disabled = !ready || isLocked;
}

function updatePUCSemesterFromYear() {
    // For PUC we auto-derive semester from year to satisfy DB requirement
    semesterEl.value = yearEl.value || '';
}

courseEl.addEventListener('change', () => {
    clearResults();
    tableBody.innerHTML = '';
    streamEl.value = '';
    yearEl.value = '';
    semesterEl.value = '';
    subjectEl.value = '';
    examEl.value = '';

    resetLowerFields('stream');
    setYearOptions(courseEl.value);

    if (courseEl.value === 'PUC') {
        streamWrap.classList.remove('hidden');
        yearWrap.classList.add('hidden');
        semesterWrap.classList.add('hidden');
        subjectWrap.classList.add('hidden');
        examWrap.classList.add('hidden');
    } else if (courseEl.value === 'BCA' || courseEl.value === 'MCA') {
        streamWrap.classList.add('hidden');
        yearWrap.classList.remove('hidden');
        semesterWrap.classList.add('hidden');
        subjectWrap.classList.add('hidden');
        examWrap.classList.add('hidden');
    } else {
        streamWrap.classList.add('hidden');
        yearWrap.classList.add('hidden');
        semesterWrap.classList.add('hidden');
        subjectWrap.classList.add('hidden');
        examWrap.classList.add('hidden');
    }

    updateLoadButton();
});

streamEl.addEventListener('change', () => {
    clearResults();
    resetLowerFields('stream');

    if (courseEl.value === 'PUC' && streamEl.value) {
        yearWrap.classList.remove('hidden');
        setYearOptions('PUC');
    } else {
        yearWrap.classList.add('hidden');
    }

    updateLoadButton();
});

yearEl.addEventListener('change', () => {
    clearResults();
    resetLowerFields('year');

    const course = courseEl.value;
    const year = yearEl.value;

    if (course === 'PUC') {
        if (year) {
            updatePUCSemesterFromYear();
            subjectWrap.classList.remove('hidden');
            setSubjectOptions('PUC', streamEl.value);
            examWrap.classList.add('hidden');
            semesterWrap.classList.add('hidden');
        }
    } else if (course === 'BCA' || course === 'MCA') {
        if (year) {
            semesterWrap.classList.remove('hidden');
            setSemesterOptions(course, year);
        }
    }

    updateLoadButton();
});

semesterEl.addEventListener('change', () => {
    clearResults();
    resetLowerFields('semester');

    if ((courseEl.value === 'BCA' || courseEl.value === 'MCA') && semesterEl.value) {
        subjectWrap.classList.remove('hidden');
        setSubjectOptions(courseEl.value, null);
        examWrap.classList.add('hidden');
    }
    updateLoadButton();
});

subjectEl.addEventListener('change', () => {
    clearResults();

    if (subjectEl.value) {
        examWrap.classList.remove('hidden');
        setExamOptions();
    } else {
        examWrap.classList.add('hidden');
    }
    updateLoadButton();
});

examEl.addEventListener('change', () => {
    clearResults();
    updateLoadButton();
});

function getFormValues() {
    const course = courseEl.value;
    const stream = course === 'PUC' ? streamEl.value : '';
    const year = yearEl.value;
    const semester = course === 'PUC' ? (yearEl.value || '') : semesterEl.value;
    const subject = subjectEl.value;
    const examType = examEl.value;

    return { course, stream, year, semester, subject, examType };
}

async function loadStudents() {
    const { course, stream, year, semester, subject, examType } = getFormValues();

    if (!course || !year || !subject || !examType || ((course === 'PUC') && !stream) || ((course === 'BCA' || course === 'MCA') && !semester)) {
        showToast('Please complete all required filters.', 'error');
        return;
    }

    showLoader(true);

    try {
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'get_students',
                course,
                stream,
                year,
                semester,
                subject,
                exam_type: examType
            })
        });

        const data = await res.json();

        showLoader(false);

        if (!data.success) {
            showToast(data.message || 'Failed to load students.', 'error');
            return;
        }

        currentMaxMarks = parseInt(data.max_marks, 10) || 40;
        currentPassMarks = parseInt(data.pass_marks, 10) || Math.round(currentMaxMarks * 0.4);

        maxMarksBadge.textContent = `Max Marks: ${currentMaxMarks}`;
        passMarksBadge.textContent = `Pass Marks: ${currentPassMarks}`;
        savedByBadge.textContent = `Teacher: ${data.saved_teacher_name || currentTeacher.name}`;

        isLocked = !!data.locked;
        statusBadge.textContent = isLocked ? 'Submitted & Locked' : 'Draft / Editable';
        statusBadge.className = `badge-soft ${isLocked ? 'badge-red' : 'badge-gray'}`;

        renderStudents(data.students || []);
        updateStats();
        tableCard.style.display = 'block';
        actionsCard.style.display = 'block';
        document.getElementById('statsGrid').style.display = 'grid';

        if (isLocked) {
            lockForm();
            showToast('Submitted & Locked marks sheet loaded.', 'success');
        } else {
            showToast('Students loaded successfully.', 'success');
        }

        loadedCourse = course;
        loadedStream = stream;
        loadedYear = year;
        loadedSemester = semester;
        loadedSubject = subject;
        loadedExamType = examType;
    } catch (err) {
        showLoader(false);
        showToast(err.message || 'Error while loading students.', 'error');
    }
}

function renderStudents(students) {
    tableBody.innerHTML = '';

    if (!students.length) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-5">
                    <div style="color:var(--muted);font-weight:800;">No active students found for selected filters.</div>
                </td>
            </tr>
        `;
        return;
    }

    students.forEach((s, index) => {
        const row = document.createElement('tr');
        
        // ✅ FIX 4: Use standardized student_id dataset
        row.dataset.studentId = s.student_id;
        row.dataset.status = s.status || '';

        const marks = (s.marks_obtained === null || s.marks_obtained === undefined) ? '' : String(s.marks_obtained);
        const remarks = s.remarks || '';
        const isSubmitted = (s.status || '') === 'submitted';

        row.innerHTML = `
            <td style="font-weight:900;">${s.roll_no}</td>
            <td style="font-weight:800;">${s.student_name}</td>
            <td>
                <input type="number"
                       class="marks-input"
                       data-student-id="${s.student_id}"
                       min="0"
                       max="${currentMaxMarks}"
                       step="1"
                       value="${marks}"
                       placeholder="0">
            </td>
            <td>
                <input type="text"
                       class="remarks-input"
                       data-student-id="${s.student_id}"
                       value="${remarks}"
                       placeholder="Enter remarks">
            </td>
        `;

        if (isSubmitted) {
            row.classList.add('row-pass');
        }

        tableBody.appendChild(row);

        const markInput = row.querySelector('.marks-input');
        const remarksInput = row.querySelector('.remarks-input');

        if (isSubmitted) {
            markInput.classList.add('locked-input');
            remarksInput.classList.add('locked-input');
            markInput.disabled = true;
            remarksInput.disabled = true;
        } else {
            attachMarkEvents(markInput);
        }

        // initial coloring
        if (markInput.value !== '') {
            colorMarkInput(markInput);
        }
    });

    updateStats();
}

function attachMarkEvents(input) {
    input.addEventListener('input', () => {
        colorMarkInput(input);
        updateStats();
    });
}

function colorMarkInput(input) {
    const val = input.value.trim();
    input.classList.remove('pass', 'fail');

    const row = input.closest('tr');
    row.classList.remove('row-pass', 'row-fail');

    if (val === '') return;

    const num = Number(val);
    if (isNaN(num) || num < 0) {
        input.classList.add('fail');
        row.classList.add('row-fail');
        return;
    }

    if (num > currentMaxMarks) {
        input.classList.add('fail');
        row.classList.add('row-fail');
        return;
    }

    if (num >= currentPassMarks) {
        input.classList.add('pass');
        row.classList.add('row-pass');
    } else {
        input.classList.add('fail');
        row.classList.add('row-fail');
    }
}

function updateStats() {
    const rows = [...tableBody.querySelectorAll('tr')].filter(r => r.querySelector('.marks-input'));
    const total = rows.length;
    let sum = 0;
    let count = 0;
    let passed = 0;
    let failed = 0;

    rows.forEach(row => {
        const input = row.querySelector('.marks-input');
        const val = input.value.trim();
        if (val === '') return;

        const num = Number(val);
        if (isNaN(num)) return;

        sum += num;
        count++;

        if (num >= currentPassMarks) passed++;
        else failed++;
    });

    totalStudentsEl.textContent = total;
    averageMarksEl.textContent = count ? (sum / count).toFixed(2) : '0.00';
    passedCountEl.textContent = passed;
    failedCountEl.textContent = failed;
}

tableBody.addEventListener('input', (e) => {
    if (e.target.classList.contains('marks-input')) {
        colorMarkInput(e.target);
        updateStats();
    }
});

async function saveMarks(status) {
    if (isLocked) {
        showToast('This sheet is already submitted and locked.', 'error');
        return;
    }

    const { course, stream, year, semester, subject, examType } = getFormValues();

    if (!course || !year || !subject || !examType || ((course === 'PUC') && !stream) || ((course === 'BCA' || course === 'MCA') && !semester)) {
        showToast('Please complete all required filters.', 'error');
        return;
    }

    const rows = [...tableBody.querySelectorAll('tr')].filter(r => r.querySelector('.marks-input'));
    if (!rows.length) {
        showToast('No students loaded.', 'error');
        return;
    }

    const marksData = [];
    let hasEmpty = false;

    rows.forEach(row => {
        const markInput = row.querySelector('.marks-input');
        const remarksInput = row.querySelector('.remarks-input');
        
        // ✅ FIX 6: Fetch normalized studentId dataset variable
        const sid = markInput.dataset.studentId; 
        const marks = markInput.value.trim();
        const remarks = remarksInput.value.trim();

        if (status === 'submitted' && marks === '') {
            hasEmpty = true;
        }

        if (marks !== '') {
            const num = Number(marks);
            if (isNaN(num) || num < 0) {
                hasEmpty = true;
            }
            if (num > currentMaxMarks) {
                hasEmpty = true;
            }
        }

        marksData.push({
            student_id: sid, // ✅ Output correctly for PHP
            marks: marks,
            remarks: remarks
        });
    });

    if (status === 'submitted' && hasEmpty) {
        showToast('Final submission requires valid marks for every student.', 'error');
        return;
    }

    showLoader(true);

    try {
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'save_marks',
                course,
                stream,
                year,
                semester,
                subject,
                exam_type: examType,
                status,
                marks_data: JSON.stringify(marksData)
            })
        });

        const data = await res.json();
        showLoader(false);

        if (!data.success) {
            showToast(data.message || 'Save failed.', 'error');
            return;
        }

        showToast(data.message, 'success');

        if (status === 'submitted') {
            isLocked = true;
            lockForm();
            statusBadge.textContent = 'Submitted & Locked';
            statusBadge.className = 'badge-soft badge-red';
        } else {
            statusBadge.textContent = 'Draft Saved';
            statusBadge.className = 'badge-soft badge-gray';
        }

        updateStats();

    } catch (err) {
        showLoader(false);
        showToast(err.message || 'Save failed.', 'error');
    }
}

function lockForm() {
    [courseEl, streamEl, yearEl, semesterEl, subjectEl, examEl].forEach(el => {
        el.disabled = true;
    });

    loadBtn.disabled = true;
    saveDraftBtn.disabled = true;
    submitFinalBtn.disabled = true;

    document.querySelectorAll('.marks-input, .remarks-input').forEach(inp => {
        inp.disabled = true;
        inp.classList.add('locked-input');
    });
}

loadBtn.addEventListener('click', loadStudents);
saveDraftBtn.addEventListener('click', () => saveMarks('draft'));
submitFinalBtn.addEventListener('click', () => {
    if (confirm('Submit final marks? This will lock the form.')) {
        saveMarks('submitted');
    }
});

// Initial state
clearResults();
setYearOptions('');
</script>
</body>
</html>