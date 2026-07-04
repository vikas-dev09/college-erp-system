<?php
// 1. LOGIN REQUIREMENT & SESSION VALIDATION
session_start();

// Strict login enforcement: Redirection to login.php if session is invalid
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

// 2. DATABASE CONNECTION CONFIGURATION
$db_config = [
    'host' => 'localhost',
    'dbname' => 'aureon',
    'username' => 'root',
    'password' => ''
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database Connection Failure: " . $e->getMessage());
}

$parent_id = $_SESSION['user_id'];

// 3. USER LINKING LOGIC
// Fetch Parent Details
$parent_stmt = $pdo->prepare("SELECT full_name, student_id FROM users WHERE id = ? AND role = 'parent'");
$parent_stmt->execute([$parent_id]);
$parent = $parent_stmt->fetch();

if (!$parent) {
    die("Error: Parent profile not resolved in active database scope.");
}

$parent_name = $parent['full_name'];
$linked_student_string_id = $parent['student_id']; // String version

// Fetch Student Details using linked_student_string_id
$student_stmt = $pdo->prepare("SELECT id, full_name, student_id FROM users WHERE student_id = ? AND role = 'student'");
$student_stmt->execute([$linked_student_string_id]);
$student = $student_stmt->fetch();

if (!$student) {
    die("Error: Linked Student record not verified in active database scope.");
}

$student_numeric_id = $student['student_id']; // Numeric PK reference
$student_name = $student['full_name'];
$student_display_id = $student['student_id']; // String identification


// 4. ATTENDANCE SUMMARY MODULE
$att_stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_classes,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count
    FROM attendance WHERE student_id = ?");
$att_stmt->execute([$student_numeric_id]);
$attendance_data = $att_stmt->fetch();

$total_classes = $attendance_data['total_classes'] ?? 0;
$present_count = $attendance_data['present_count'] ?? 0;
$attendance_percentage = $total_classes > 0 ? round(($present_count / $total_classes) * 100, 1) : 0;


// 5. INTERNAL MARKS SUMMARY MODULE
$marks_stmt = $pdo->prepare("SELECT subject, exam_type, marks_obtained, max_marks FROM internal_marks WHERE student_id = ?");
$marks_stmt->execute([$student_numeric_id]);
$marks_list = $marks_stmt->fetchAll();

$total_obtained = 0;
$total_max = 0;
foreach ($marks_list as $mark) {
    $total_obtained += $mark['marks_obtained'];
    $total_max += $mark['max_marks'];
}
$average_marks_percentage = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 1) : 0;


// 6. FEES LEDGER SUMMARY MODULE
$fee_stmt = $pdo->prepare("
    SELECT receipt_id, amount, payment_type, payment_mode, created_at 
    FROM receipts 
    WHERE student_id = ? 
    ORDER BY created_at DESC
");
$fee_stmt->execute([$linked_student_string_id]);
$fee_history = $fee_stmt->fetchAll();

$total_fee_paid = 0;
foreach ($fee_history as $fee) {
    $total_fee_paid += $fee['amount'];
}
$latest_payment = !empty($fee_history) ? $fee_history[0] : null;
$fee_status = !empty($fee_history) ? "Paid" : "No records";


// FETCH ALL TEACHERS
$teacher_stmt = $pdo->prepare("
SELECT reference_id, full_name
FROM users
WHERE role = 'teacher'
AND status = 'Active'
");

$teacher_stmt->execute();
$teachers = $teacher_stmt->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_teacher_request'])) {

    $teacher_id = trim($_POST['teacher_id']);
    $message = trim($_POST['message']);

    if (!empty($teacher_id) && !empty($message)) {

        $insert = $pdo->prepare("
           INSERT INTO parent_teacher_requests
(
    parent_name,
    student_id,
    student_name,
    teacher_id,
    message
)
            VALUES
            (?, ?, ?, ?, ?)
        ");

       $insert->execute([
    $parent_name,
    $student_display_id,
    $student_name,
    $teacher_id,
    $message
]);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUREON ERP - Modern Parent Portal</title>
    <!-- Tailwind CSS Engine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Fonts Setup -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        .premium-bg {
            background: linear-gradient(135deg, #f1f5f9 0%, #e0e7ff 50%, #dbeafe 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body class="premium-bg text-slate-800 min-h-screen flex flex-col relative overflow-x-hidden antialiased">

    <!-- Decorative Floating Gradient Background Orbs -->
    <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-indigo-300/20 rounded-full blur-[120px] pointer-events-none z-0"></div>
    <div class="absolute bottom-[20%] right-[-10%] w-[600px] h-[600px] bg-blue-300/20 rounded-full blur-[150px] pointer-events-none z-0"></div>

    <!-- MAIN HEADER -->
    <header class="sticky top-0 z-40 w-full bg-white/70 backdrop-blur-md border-b border-white/40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Brand Logotype -->
            <div class="flex items-center space-x-3 justify-start">
                <div class="aureon-brand-logo">

    <span class="brand-a">A</span>

    <svg class="brand-cap" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm0 13L5.74 12.59 12 9.18l6.26 3.41L12 16zm-7 1.27V20c0 1.1 3.13 2 7 2s7-.9 7-2v-2.73l-7 3.82-7-3.82z"/>
    </svg>

</div>

<style>

/* ===============================
   AUREON HEADER LOGO
================================= */

.aureon-brand-logo{

    width:58px;
    height:58px;

    border-radius:20px;

    position:relative;

    display:flex;
    align-items:center;
    justify-content:center;

    background:
    linear-gradient(
        135deg,
        #4f46e5 0%,
        #7c3aed 50%,
        #3b82f6 100%
    );

    box-shadow:
    0 10px 30px rgba(99,102,241,0.28),
    inset 0 1px 2px rgba(255,255,255,0.25);

    overflow:hidden;

    transition:all 0.35s ease;
}

/* Hover */
.aureon-brand-logo:hover{

    transform:
    translateY(-3px)
    scale(1.05);

    box-shadow:
    0 18px 40px rgba(99,102,241,0.35);
}

/* Big A */
.brand-a{

    font-size:36px;

    font-weight:900;

    color:#ffffff;

    line-height:1;

    font-family:'Plus Jakarta Sans',sans-serif;

    text-shadow:
    0 5px 12px rgba(0,0,0,0.18);
}

/* Graduation Cap */
.brand-cap{

    position:absolute;

    top:8px;
    right:7px;

    width:18px;
    height:18px;

    color:#ffffff;

    transform:rotate(-12deg);

    filter:
    drop-shadow(0 3px 6px rgba(0,0,0,0.25));
}

</style>
                <div>
                    <span class="text-xl font-extrabold tracking-tight text-slate-900">AUREON <span class="text-indigo-600 font-bold">ERP</span></span>
                    <p class="text-[10px] text-slate-400 font-bold tracking-wider uppercase" data-i18n="parent_portal">PARENT PORTAL</p>
                </div>
            </div>

            <!-- Profile Connection Layout System -->
            <div class="flex items-center space-x-3 justify-start">
                <div class="flex items-center space-x-2">
                    <span class="text-[10px] text-slate-400 uppercase font-extrabold tracking-wider" data-i18n="parent">PARENT</span>
                    <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($parent_name); ?></span>
                </div>
                <span class="text-indigo-400 font-semibold animate-pulse">➔</span>
                <div class="flex items-center space-x-2">
                    <span class="text-[10px] text-slate-400 uppercase font-extrabold tracking-wider" data-i18n="student">STUDENT</span>
                    <span class="text-sm font-extrabold text-indigo-600"><?php echo htmlspecialchars($student_name); ?></span>
                    <span class="text-[11px] bg-indigo-100 text-indigo-700 font-mono px-2.5 py-0.5 rounded-full font-bold"><?php echo htmlspecialchars($student_display_id); ?></span>
                </div>
            </div>

            <!-- Dynamic Multi-Language Switcher Interface -->
            <div class="flex items-center space-x-4">
                <div class="inline-flex bg-slate-200/60 p-1 rounded-2xl border border-white/50 backdrop-blur-sm">
                    <button onclick="switchLanguage('en')" id="lang-en" class="px-4 py-1.5 text-xs font-bold rounded-xl transition-all duration-300 bg-white shadow-md text-indigo-600">
                        English
                    </button>
                    <button onclick="switchLanguage('kn')" id="lang-kn" class="px-4 py-1.5 text-xs font-bold rounded-xl transition-all duration-300 text-slate-600 hover:text-slate-900">
                        ಕನ್ನಡ
                    </button>
                </div>

                <!-- Signout Action -->
                <a href="logout.php" class="p-2.5 text-slate-400 hover:text-rose-500 transition-all rounded-xl hover:bg-rose-50 border border-transparent hover:border-rose-100" title="Logout">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                </a>
            </div>
        </div>
    </header>

    <!-- MOBILE SYSTEM SUBHEADER -->
    <div class="block lg:hidden bg-indigo-50/90 border-b border-indigo-100/60 p-4 backdrop-blur-md">
        <p class="text-[10px] text-slate-400 uppercase font-extrabold tracking-widest" data-i18n="overview_for">OVERVIEW FOR</p>
        <div class="flex flex-wrap items-center text-sm mt-1 gap-2">
            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($parent_name); ?></span> 
            <span class="text-slate-400">/</span> 
            <span class="font-extrabold text-indigo-600"><?php echo htmlspecialchars($student_name); ?></span>
            <span class="text-xs bg-indigo-100 text-indigo-600 font-mono px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($student_display_id); ?></span>
        </div>
    </div>

    <!-- DYNAMIC CONTAINER -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 z-10 space-y-8">
        
        <!-- HOLIDAY ANNOUNCEMENT BANNER -->
        <div class="relative overflow-hidden bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-3xl p-6 shadow-xl shadow-orange-500/10 flex flex-col md:flex-row items-center justify-between border border-white/20 gap-4">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-white/20 via-transparent to-transparent pointer-events-none"></div>
            <div class="flex items-center space-x-4">
                <div class="p-3.5 bg-white/10 backdrop-blur-md rounded-2xl animate-pulse">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m12.728 12.728l.707-.707M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <span class="text-[11px] font-extrabold bg-white/20 px-3 py-1 rounded-full uppercase tracking-widest text-amber-100" data-i18n="upcoming_holiday">UPCOMING HOLIDAY</span>
                    <h2 class="text-2xl font-black mt-2 tracking-tight" data-i18n="holiday_name">Independence Day – 15 Aug</h2>
                    <p class="text-sm text-orange-100/90 font-medium" data-i18n="holiday_desc">School Holiday declared for national celebration.</p>
                </div>
            </div>
            <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl px-5 py-3 text-center self-stretch md:self-auto flex flex-col justify-center min-w-[120px]">
                <span class="text-[10px] text-orange-200 font-extrabold uppercase tracking-widest" data-i18n="august">August</span>
                <span class="text-3xl font-black tracking-tight text-white">15</span>
            </div>
        </div>

        <!-- DASHBOARD GRID: METRIC SUMMARIES & INTERACTION -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- ATTENDANCE MODULE CARD -->
            <div class="glass-card rounded-3xl p-6 hover:-translate-y-2 hover:shadow-2xl hover:shadow-indigo-100 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <div class="p-3 bg-blue-100 text-blue-600 rounded-2xl group-hover:bg-blue-600 group-hover:text-white transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <span class="text-[10px] font-extrabold text-blue-600 tracking-wider uppercase bg-blue-50 px-3 py-1 rounded-full border border-blue-100" data-i18n="attendance">Attendance</span>
                    </div>
                    <p class="text-sm text-slate-400 font-semibold" data-i18n="present_percentage">Present Percentage</p>
                    <span class="text-4xl font-extrabold tracking-tight text-slate-900"><?php echo $attendance_percentage; ?>%</span>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <div><span data-i18n="total_classes">Total Classes</span>: <span class="font-extrabold text-slate-700"><?php echo $total_classes; ?></span></div>
                        <div><span data-i18n="present">Present</span>: <span class="font-extrabold text-emerald-600"><?php echo $present_count; ?></span></div>
                    </div>
                    <div class="w-full bg-slate-100 h-2.5 rounded-full mt-3 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full transition-all duration-1000" style="width: <?php echo $attendance_percentage; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- ACADEMIC INTERNAL MARKS CARD -->
            <div class="glass-card rounded-3xl p-6 hover:-translate-y-2 hover:shadow-2xl hover:shadow-indigo-100 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <div class="p-3 bg-indigo-100 text-indigo-600 rounded-2xl group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <span class="text-[10px] font-extrabold text-indigo-600 tracking-wider uppercase bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100" data-i18n="internal_marks">Internal Marks</span>
                    </div>
                    <p class="text-sm text-slate-400 font-semibold" data-i18n="average_marks">Average Score</p>
                    <span class="text-4xl font-extrabold tracking-tight text-slate-900"><?php echo $average_marks_percentage; ?>%</span>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <p class="text-[11px] leading-relaxed text-slate-400 font-medium" data-i18n="average_performance">Aggregate average score across core academic subjects.</p>
                    <div class="w-full bg-slate-100 h-2.5 rounded-full mt-3.5 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full rounded-full transition-all duration-1000" style="width: <?php echo $average_marks_percentage; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- FEES LEDGER CARD -->
            <div class="glass-card rounded-3xl p-6 hover:-translate-y-2 hover:shadow-2xl hover:shadow-indigo-100 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <div class="p-3 bg-emerald-100 text-emerald-600 rounded-2xl group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <span class="text-[10px] font-extrabold tracking-wider uppercase px-3 py-1 rounded-full border <?php echo $fee_status === 'Paid' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'; ?>" data-i18n="fee_status">
                            <?php echo $fee_status; ?>
                        </span>
                    </div>
                    <p class="text-sm text-slate-400 font-semibold" data-i18n="total_paid">Total Paid</p>
                    <span class="text-3xl font-extrabold tracking-tight text-slate-900">₹<?php echo number_format($total_fee_paid, 2); ?></span>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[11px] font-medium text-slate-400" data-i18n="latest">Latest</span>
                    <span class="text-xs font-mono font-bold text-slate-700"><?php echo $latest_payment ? $latest_payment['created_at'] : 'N/A'; ?></span>
                </div>
            </div>

            <!-- COMMUNICATE WITH TEACHER CARD (CTA CARD) -->
            <div class="bg-gradient-to-r from-indigo-500 to-blue-600 rounded-3xl p-6 hover:-translate-y-2 hover:shadow-2xl hover:shadow-indigo-500/30 transition-all duration-300 text-white flex flex-col justify-between shadow-xl relative overflow-hidden group">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,_var(--tw-gradient-stops))] from-white/10 via-transparent to-transparent pointer-events-none"></div>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/10 backdrop-blur-md rounded-2xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                            </svg>
                        </div>
                        <span class="text-[9px] font-extrabold tracking-widest uppercase bg-white/20 px-2.5 py-1 rounded-full text-indigo-100" data-i18n="comms_tag">DIRECT REACH</span>
                    </div>
                    <h3 class="text-xl font-extrabold tracking-tight" data-i18n="comms_title">Communicate with Teacher</h3>
                    <p class="text-xs text-indigo-100/90 leading-relaxed mt-2" data-i18n="comms_desc">Send direct message or request meeting with subject teachers.</p>
                </div>
                <button onclick="toggleTeacherModal()" class="w-full mt-6 bg-white text-indigo-600 font-extrabold text-xs py-3 rounded-2xl shadow-md hover:bg-slate-50 active:scale-95 transition-all" data-i18n="start_conversation">
                    Start Conversation
                </button>
            </div>

        </div>

        <!-- TWO COLUMN DETAILED SYSTEM GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- RECENT INTERNAL MARKS LIST -->
            <div class="glass-card rounded-3xl shadow-lg shadow-indigo-100/50 overflow-hidden flex flex-col border border-white/30">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-white/40">
                    <h3 class="font-bold text-slate-900 tracking-tight" data-i18n="recent_marks_title">Recent Internal Marks</h3>
                    <span class="text-[11px] font-extrabold text-slate-400 bg-slate-100/80 px-3 py-1 rounded-full uppercase tracking-wider border border-slate-200/50" data-i18n="subject_wise">Subject Wise</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-extrabold tracking-wider text-slate-400 uppercase">
                                <th class="py-4 px-6" data-i18n="th_subject">Subject</th>
                                <th class="py-4 px-6" data-i18n="th_exam_type">Exam Type</th>
                                <th class="py-4 px-6 text-center" data-i18n="th_marks">Marks Obtained</th>
                                <th class="py-4 px-6 text-right" data-i18n="th_percentage">Percentage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/60 text-sm text-slate-600">
                            <?php if (empty($marks_list)): ?>
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-400 font-semibold" data-i18n="no_records">No academic performance records logged.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($marks_list as $row): 
                                    $pct = $row['max_marks'] > 0 ? round(($row['marks_obtained'] / $row['max_marks']) * 100, 1) : 0;
                                ?>
                                    <tr class="hover:bg-white/40 transition-colors">
                                        <td class="py-4.5 px-6 font-bold text-slate-800"><?php echo htmlspecialchars($row['subject']); ?></td>
                                        <td class="py-4.5 px-6">
                                            <span class="bg-indigo-50 text-indigo-600 text-xs px-2.5 py-1 rounded-full font-bold border border-indigo-100/60"><?php echo htmlspecialchars($row['exam_type']); ?></span>
                                        </td>
                                        <td class="py-4.5 px-6 text-center font-mono font-bold text-slate-700"><?php echo $row['marks_obtained']; ?> / <?php echo $row['max_marks']; ?></td>
                                        <td class="py-4.5 px-6 text-right font-black text-base <?php echo $pct >= 75 ? 'text-emerald-600' : ($pct >= 40 ? 'text-amber-500' : 'text-rose-500'); ?>">
                                            <?php echo $pct; ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FEE TRANSACTION ARCHIVES -->
            <div class="glass-card rounded-3xl shadow-lg shadow-indigo-100/50 overflow-hidden flex flex-col border border-white/30">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-white/40">
                    <h3 class="font-bold text-slate-900 tracking-tight" data-i18n="fee_history_title">Fee Payment History</h3>
                    <span class="text-[11px] font-extrabold text-slate-400 bg-slate-100/80 px-3 py-1 rounded-full uppercase tracking-wider border border-slate-200/50" data-i18n="receipts">Receipts</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-extrabold tracking-wider text-slate-400 uppercase">
                                <th class="py-4 px-6" data-i18n="th_receipt_no">Receipt No</th>
                                <th class="py-4 px-6" data-i18n="th_date">Date</th>
                                <th class="py-4 px-6" data-i18n="th_method">Payment Type</th>
                                <th class="py-4 px-6 text-right" data-i18n="th_amount">Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/60 text-sm text-slate-600">
                            <?php if (empty($fee_history)): ?>
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-400 font-semibold" data-i18n="no_records">No documented fee transactions available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fee_history as $fee): ?>
                                    <tr class="hover:bg-white/40 transition-colors">
                                        <td class="py-4.5 px-6 font-mono text-xs font-extrabold text-slate-700"><?php echo htmlspecialchars($fee['receipt_id']); ?></td>
                                        <td class="py-4.5 px-6 text-slate-500 font-semibold"><?php echo htmlspecialchars($fee['created_at']); ?></td>
                                        <td class="py-4.5 px-6">
                                            <span class="bg-slate-100 text-slate-700 text-xs px-2.5 py-1 rounded-full font-bold border border-slate-200"><?php echo htmlspecialchars($fee['payment_type']); ?></span>
                                        </td>
                                        <td class="py-4.5 px-6 text-right font-extrabold text-slate-900">₹<?php echo number_format($fee['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- FLOATING COMMUNICATION: COMMUNICATE WITH TEACHER MODAL -->
    <div id="teacher-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end sm:items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Modal Overlay Backdrop blur-sm -->
            <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="toggleTeacherModal()"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Glassmorphic Modal Window Box -->
            <div class="inline-block align-bottom bg-white/95 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-white/50">
                <div class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center space-x-2.5">
                            <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                            </span>
                            <h3 class="text-lg font-extrabold text-slate-900" data-i18n="start_conversation">Start Conversation</h3>
                        </div>
                        <button onclick="toggleTeacherModal()" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-full transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Client Message Delivery Form -->
                    <form method="POST" id="teacher-form" class="mt-6 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2" data-i18n="select_teacher">Select Subject Teacher</label>
                            <select name="teacher_id" id="teacher-selection" required class="w-full bg-slate-50 border border-slate-200 text-sm font-semibold rounded-2xl px-4 py-3.5">
    <option value="" disabled selected>Choose a teacher...</option>

    <?php foreach ($teachers as $t): ?>
        <option value="<?php echo htmlspecialchars($t['reference_id']); ?>">
            <?php echo htmlspecialchars($t['full_name']); ?>
        </option>
    <?php endforeach; ?>
</select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2" data-i18n="message_label">Your Message</label>
                            <textarea name="message" id="teacher-msg" required rows="4" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-2xl px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 focus:bg-white transition-all resize-none" placeholder="Draft your query or dynamic meeting requirement details..."></textarea>
                        </div>
                        
                        <div class="pt-4 flex items-center justify-end space-x-3 border-t border-slate-100 mt-6">
<a href="parent_chat.php" class="px-5 py-3 text-xs font-bold rounded-2xl border border-indigo-300 text-indigo-600 hover:bg-indigo-50 transition-all">
    Chatway
</a>                            <button type="submit" name="send_teacher_request" name="send_teacher_request" name="send_teacher_request" name="send_teacher_request" class="px-6 py-3 text-xs font-bold rounded-2xl bg-gradient-to-r from-indigo-500 to-blue-600 text-white hover:from-indigo-600 hover:to-blue-700 transition-all shadow-md shadow-indigo-100" data-i18n="send_message">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- AI CHAT MODULE POPUP ENGINE UI -->
     
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end">
        <div id="chat-window" class="hidden w-80 sm:w-96 h-[460px] bg-white/95 rounded-3xl shadow-2xl border border-slate-200/80 flex flex-col overflow-hidden mb-4 transform transition-all duration-300 scale-95 origin-bottom-right">
            <div class="bg-gradient-to-r from-indigo-600 via-indigo-500 to-blue-600 p-4 text-white flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="font-extrabold text-sm tracking-wide">AUREON AI Assistant</span>
                </div>
                <button onclick="toggleChat()" class="text-white/80 hover:text-white transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <div id="chat-body" class="flex-grow p-4 overflow-y-auto space-y-3 bg-slate-50/70 text-xs leading-relaxed">
                <div id="quick-actions" class="flex flex-wrap gap-2 mb-3">

    <button onclick="quickAction('attendance')" class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold">
        📊 Attendance
    </button>

    <button onclick="quickAction('teacher')" class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">
        💬 Chat Teacher
    </button>

    <button onclick="quickAction('fees')" class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold">
        💰 Fee Status
    </button>

</div>
                <div class="bg-indigo-50 border border-indigo-100 text-indigo-800 p-3.5 rounded-2xl rounded-tl-none max-w-[85%] font-medium">
                    Hello! I am your AUREON AI virtual assistant. How can I assist you with your child's academic or financial records today?
                </div>
            </div>
            
            <form id="chat-form" onsubmit="sendChatMessage(event)" class="p-3.5 border-t border-slate-100 bg-white flex items-center space-x-2">
                <input type="text" id="chat-input" placeholder="Type an query message..." autocomplete="off" class="w-full bg-slate-50 border border-slate-200 text-xs rounded-xl px-4 py-3 focus:outline-none focus:border-indigo-500 focus:bg-white transition-all">
                <button type="submit" name="send_teacher_request" name="send_teacher_request" name="send_teacher_request" class="bg-indigo-600 text-white p-3 rounded-xl hover:bg-indigo-700 transition-colors shadow-sm flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                </button>
            </form>
        </div>

        <!-- Master Chat Button -->
        <button onclick="toggleChat()" class="bg-gradient-to-tr from-indigo-600 via-indigo-500 to-blue-600 hover:shadow-indigo-500/20 text-white p-4 rounded-full shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
        </button>
    </div>

    <!-- FOOTER SIGNATURE -->
    <footer class="bg-white/40 border-t border-slate-200/50 py-8 mt-12 z-10 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-xs text-slate-400 font-extrabold tracking-widest uppercase">&copy; <?php echo date('Y'); ?> AUREON ERP Systems. All Rights Reserved. </p>
        </div>
    </footer>

    <!-- SUCCESS TOAST POPUP FOR FRONTEND ACTIONS -->
    <div id="success-toast" class="fixed top-24 left-1/2 transform -translate-x-1/2 bg-slate-900/90 text-white text-xs font-bold px-6 py-3 rounded-full shadow-xl backdrop-blur-md z-50 hidden border border-white/10 transition-all duration-300">
        Message successfully transmitted!
    </div>

    <!-- LOCALIZATION & INTERACTIVE SCRIPTS -->
    <script>
        const translations = {
            en: {
                parent_portal: "PARENT PORTAL", parent: "Parent", student: "Student",
                overview_for: "Overview For", upcoming_holiday: "UPCOMING HOLIDAY",
                holiday_name: "Independence Day – 15 Aug", holiday_desc: "School Holiday declared for national celebration.",
                august: "August", attendance: "Attendance", present_percentage: "Present Percentage",
                total_classes: "Total Classes", present: "Present", internal_marks: "Internal Marks",
                average_marks: "Average Score", average_performance: "Aggregate average score across core academic subjects.",
                fee_status: "Fee Status", total_paid: "Total Paid", latest: "Latest",
                comms_tag: "DIRECT REACH", comms_title: "Communicate with Teacher",
                comms_desc: "Send direct message or request meeting with subject teachers.",
                start_conversation: "Start Conversation", recent_marks_title: "Recent Internal Marks",
                subject_wise: "Subject Wise", th_subject: "Subject", th_exam_type: "Exam Type",
                th_marks: "Marks Obtained", th_percentage: "Percentage", fee_history_title: "Fee Payment History",
                receipts: "Receipts", th_receipt_no: "Receipt No", th_date: "Date",
                th_method: "Payment Type", th_amount: "Amount Paid", no_records: "No documented records.",
                select_teacher: "Select Subject Teacher", choose_subject: "Choose a subject specialist...",
                message_label: "Your Message", close: "Close", send_message: "Send Message",
                toast_success: "Communication processed successfully!"
            },
            kn: {
                parent_portal: "ಪೋಷಕರ ಪೋರ್ಟಲ್", parent: "ಪೋಷಕರು", student: "ವಿದ್ಯಾರ್ಥಿ",
                overview_for: "ಅವಲೋಕನ", upcoming_holiday: "ಮುಂಬರುವ ರಜೆ",
                holiday_name: "ಸ್ವಾತಂತ್ರ್ಯ ದಿನಾಚರಣೆ - ಆಗಸ್ಟ್ 15", holiday_desc: "ರಾಷ್ಟ್ರೀಯ ಆಚರಣೆಗಾಗಿ ಶಾಲಾ ರಜಾದಿನವನ್ನು ಘೋಷಿಸಲಾಗಿದೆ.",
                august: "ಆಗಸ್ಟ್", attendance: "ಹಾಜರಾತಿ", present_percentage: "ಹಾಜರಾತಿ ಶೇಕಡಾವಾರು",
                total_classes: "ಒಟ್ಟು ತರಗತಿಗಳು", present: "ಹಾಜರಾದ ದಿನ", internal_marks: "ಆಂತರಿಕ ಅಂಕಗಳು",
                average_marks: "ಸರಾಸರಿ ಅಂಕ", average_performance: "ಮುಖ್ಯ ಆಂತರಿಕ ಪರೀಕ್ಷೆಗಳ ಒಟ್ಟು ಸರಾಸರಿ ಅಂಕಗಳು.",
                fee_status: "ಶುಲ್ಕದ ಸ್ಥಿತಿ", total_paid: "ಪಾವತಿಸಿದ ಒಟ್ಟು ಮೊತ್ತ", latest: "ಇತ್ತೀಚಿನ",
                comms_tag: "ನೇರ ಸಂಪರ್ಕ", comms_title: "ಶಿಕ್ಷಕರೊಂದಿಗೆ ಸಂವಹನ ನಡೆಸಿ",
                comms_desc: "ವಿಷಯ ಶಿಕ್ಷಕರಿಗೆ ನೇರ ಸಂದೇಶ ಕಳುಹಿಸಿ ಅಥವಾ ಸಭೆಗಾಗಿ ವಿನಂತಿಸಿ.",
                start_conversation: "ಸಂಭಾಷಣೆ ಪ್ರಾರಂಭಿಸಿ", recent_marks_title: "ಇತ್ತೀಚಿನ ಆಂತರಿಕ ಅಂಕಗಳು",
                subject_wise: "ವಿಷಯವಾರು ವಿವರ", th_subject: "ವಿಷಯ", th_exam_type: "ಪರೀಕ್ಷೆಯ ವಿಧಾನ",
                th_marks: "ಪಡೆದ ಅಂಕಗಳು", th_percentage: "ಶೇಕಡಾವಾರು", fee_history_title: "ಶುಲ್ಕ ಪಾವತಿ ಇತಿಹಾಸ",
                receipts: "ರಶೀದಿಗಳು", th_receipt_no: "ರಶೀದಿ ಸಂಖ್ಯೆ", th_date: "ದಿನಾಂಕ",
                th_method: "ಪಾವತಿ ವಿಧಾನ", th_amount: "ಪಾವತಿಸಿದ ಮೊತ್ತ", no_records: "ಯಾವುದೇ ದಾಖಲೆಗಳು ಲಭ್ಯವಿಲ್ಲ.",
                select_teacher: "ವಿಷಯ ಶಿಕ್ಷಕರನ್ನು ಆಯ್ಕೆ ಮಾಡಿ", choose_subject: "ವಿಷಯ ತಜ್ಞರನ್ನು ಆಯ್ಕೆ ಮಾಡಿ...",
                message_label: "ನಿಮ್ಮ ಸಂದೇಶ", close: "ಮುಚ್ಚಿ", send_message: "ಸಂದೇಶ ಕಳುಹಿಸಿ",
                toast_success: "ಸಂವಹನ ಯಶಸ್ವಿಯಾಗಿ ತಲುಪಿಸಲಾಗಿದೆ!"
            }
        };

        let currentLang = 'en';

        function switchLanguage(lang) {
            currentLang = lang;
            document.querySelectorAll('[data-i18n]').forEach(element => {
                const key = element.getAttribute('data-i18n');
                if (translations[lang][key]) {
                    element.innerText = translations[lang][key];
                }
            });

            // Handle Input Placeholders specifically
            const msgInput = document.getElementById('teacher-msg');
            if (lang === 'kn') {
                msgInput.placeholder = "ನಿಮ್ಮ ವಿನಂತಿ ಅಥವಾ ಪ್ರಶ್ನೆಗಳನ್ನು ಇಲ್ಲಿ ಬರೆಯಿರಿ...";
            } else {
                msgInput.placeholder = "Draft your query or dynamic meeting requirement details...";
            }

            // Update button UI styles
            const btnEn = document.getElementById('lang-en');
            const btnKn = document.getElementById('lang-kn');
            if (lang === 'kn') {
                btnKn.className = "px-4 py-1.5 text-xs font-bold rounded-xl transition-all duration-300 bg-white shadow-md text-indigo-600";
                btnEn.className = "px-4 py-1.5 text-xs font-bold rounded-xl transition-all duration-300 text-slate-600 hover:text-slate-900";
            } else {
                btnEn.className = "px-4 py-1.5 text-xs font-bold rounded-xl transition-all duration-300 bg-white shadow-md text-indigo-600";
                btnKn.className = "px-4 py-1.5 text-xs font-bold rounded-xl transition-all duration-300 text-slate-600 hover:text-slate-900";
            }
        }

        // Teacher Dialog Overlay Controls
        function toggleTeacherModal() {
            const modal = document.getElementById('teacher-modal');
            modal.classList.toggle('hidden');
        }

        function triggerToast(message) {
            const toast = document.getElementById('success-toast');
            toast.innerText = message;
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Mock ajax submission
       

        // Chat Engine Controls
        function toggleChat() {
            const chatWin = document.getElementById('chat-window');
            chatWin.classList.toggle('hidden');
        }

        function sendChatMessage(event) {
            event.preventDefault();
            const input = document.getElementById('chat-input');
            const val = input.value.trim();
            if (!val) return;

            const body = document.getElementById('chat-body');

            // Append User Message
            const userMsg = document.createElement('div');
            userMsg.className = "bg-slate-900 text-white p-3 rounded-2xl rounded-tr-none max-w-[85%] ml-auto text-right font-semibold";
            userMsg.innerText = val;
            body.appendChild(userMsg);
            input.value = '';
            body.scrollTop = body.scrollHeight;

            // Delayed Mock AI Response
            setTimeout(() => {
                const aiMsg = document.createElement('div');
                aiMsg.className = "bg-indigo-50 border border-indigo-100 text-indigo-800 p-3.5 rounded-2xl rounded-tl-none max-w-[85%] font-medium";
                aiMsg.innerText = "I have scanned your student profiles. Attendance trends look solid. Please reach out to Dr. Shastri directly if you wish to set up an offline meeting.";
                body.appendChild(aiMsg);
                body.scrollTop = body.scrollHeight;
            }, 800);
        }
    </script>
</body>
</html>