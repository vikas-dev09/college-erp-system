<?php
/**
 * ============================================================
 * AUREON ERP — View All Students
 * File: view_students.php
 * ============================================================
 * SAME design language as add_student.php
 * Violet / Pink / Orange theme
 * Inter font | White glassmorphism sidebar
 * ============================================================
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=aureon;charset=utf8mb4',
        'root', '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

/* ============================================================
   PARAMS
============================================================ */
$search        = trim($_GET['search'] ?? '');
$filterCourse  = $_GET['course']  ?? 'all';
$filterStatus  = $_GET['status']  ?? 'all';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 10;
$offset        = ($page - 1) * $perPage;

/* ============================================================
   WHERE CLAUSE
============================================================ */
$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = "(first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ? OR course LIKE ? OR email LIKE ?)";
    $like     = "%$search%";
    $params   = array_merge($params, [$like,$like,$like,$like,$like]);
}

if ($filterCourse !== 'all') {
    $where[]  = "course = ?";
    $params[] = $filterCourse;
}

if ($filterStatus !== 'all') {
    $where[]  = "status = ?";
    $params[] = $filterStatus;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ============================================================
   COUNT
============================================================ */
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM students $whereSQL");
$cntStmt->execute($params);
$totalRows  = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

/* ============================================================
   FETCH STUDENTS
============================================================ */
$stmt = $pdo->prepare("
    SELECT * FROM students $whereSQL
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$students = $stmt->fetchAll();

/* ============================================================
   STATS
============================================================ */
$stats = $pdo->query("
    SELECT
        COUNT(*)                 AS total,
        SUM(status='Active')     AS active,
        SUM(status='Inactive')   AS inactive,
        SUM(course='BCA')        AS bca,
        SUM(course='PUC')        AS puc,
        SUM(course='MCA')        AS mca
    FROM students
")->fetch();

/* ============================================================
   DELETE
============================================================ */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM students WHERE id=?")->execute([(int)$_GET['delete']]);
    header("Location: view_students.php?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>AUREON ERP — All Students</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   ROOT — EXACT SAME as add_student.php
============================================================ */
:root{
    --violet:#7c3aed;
    --violet-dark:#6d28d9;
    --violet-light:#a78bfa;
    --violet-pale:#ede9fe;

    --orange:#f97316;
    --orange-pale:#fff7ed;

    --pink:#ec4899;
    --pink-pale:#fdf2f8;

    --success:#16a34a;
    --success-pale:#f0fdf4;

    --error:#dc2626;
    --error-pale:#fef2f2;

    --dark:#1e293b;
    --text:#334155;
    --text-muted:#64748b;

    --border:#e2e8f0;
    --border-light:#f1f5f9;

    --white:#ffffff;

    --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);

    --card-shadow:0 10px 30px rgba(0,0,0,0.06);

    --radius:20px;
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

body {
    background:var(--bg);
    font-family:'Inter',sans-serif;
    color:var(--text);
    min-height:100vh;
    display:flex;
}

a { text-decoration:none; color:inherit; }
button { cursor:pointer; font-family:inherit; }
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-thumb { background:var(--violet-pale); border-radius:8px; }

/* ============================================================
   SIDEBAR — White glassmorphism (SAME as add_student)
============================================================ */
.sidebar {
    width:270px; height:100vh;
    position:fixed; left:0; top:0;
    background:rgba(255,255,255,0.80);
    backdrop-filter:blur(20px);
    -webkit-backdrop-filter:blur(20px);
    border-right:1px solid var(--border);
    display:flex; flex-direction:column;
    padding:28px 18px;
    z-index:200;
    transition:transform .3s ease;
}
.brand {
    display:flex; align-items:center; gap:14px;
    margin-bottom:36px; padding-bottom:24px;
    border-bottom:1px solid var(--border);
}
.brand-icon {
    width:50px; height:50px; border-radius:16px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    display:flex; align-items:center; justify-content:center;
    color:white; font-size:22px;
    box-shadow:0 8px 24px rgba(124,58,237,0.30);
}
.brand-name { font-weight:800; font-size:18px; color:var(--dark); }
.brand-sub  { font-size:11px; color:var(--text-muted); }

.nav-section {
    font-size:10px; font-weight:700;
    text-transform:uppercase; letter-spacing:.8px;
    color:var(--text-muted);
    padding:16px 12px 8px;
}
.nav-link {
    display:flex; align-items:center; gap:12px;
    padding:12px 14px; border-radius:14px;
    color:var(--text-muted); font-weight:600; font-size:14px;
    transition:.2s ease; margin-bottom:4px;
    width:100%; background:none; border:none; text-align:left;
}
.nav-link:hover {
    background:var(--violet-pale);
    color:var(--violet);
    transform:translateX(3px);
}
.nav-link.active {
    background:linear-gradient(135deg,var(--pink-pale),var(--violet-pale));
    color:var(--violet);
    font-weight:700;
    box-shadow:0 4px 14px rgba(124,58,237,0.10);
}
.nav-link i { font-size:17px; width:22px; text-align:center; }

.sb-foot {
    margin-top:auto; padding-top:18px;
    border-top:1px solid var(--border);
}
.logout-link {
    display:flex; align-items:center; gap:12px;
    padding:12px 14px; border-radius:14px;
    color:var(--error); font-weight:700; font-size:14px;
    width:100%; background:none; border:none;
    transition:.2s ease;
}
.logout-link:hover { background:var(--error-pale); }

/* ============================================================
   MAIN
============================================================ */
.main {
    margin-left:270px; flex:1;
    display:flex; flex-direction:column;
    min-height:100vh;
}

/* ── TOPBAR ── */
.topbar {
    background:rgba(255,255,255,0.70);
    backdrop-filter:blur(16px);
    -webkit-backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border);
    padding:16px 28px;
    display:flex; align-items:center;
    justify-content:space-between;
    flex-shrink:0; position:sticky; top:0; z-index:100;
    gap:14px; flex-wrap:wrap;
}
.topbar-left h2 {
    font-size:20px; font-weight:800;
    color:var(--dark); margin-bottom:2px;
}
.topbar-left p { font-size:13px; color:var(--text-muted); }
.topbar-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

/* input style — SAME as add_student .fi */
.fi {
    width:100%; padding:13px 16px;
    border:2px solid var(--border);
    border-radius:14px;
    font-family:'Inter',sans-serif;
    font-size:14px; color:var(--text);
    background:var(--white);
    transition:.2s ease;
}
.fi:focus {
    outline:none;
    border-color:var(--violet);
    box-shadow:0 0 0 4px var(--violet-pale);
}
.fi::placeholder { color:var(--text-muted); }

.search-fi { width:240px; }

/* Button — SAME as add_student */
.btn-main {
    padding:12px 22px; border:none;
    border-radius:14px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white; font-weight:700; font-size:14px;
    display:flex; align-items:center; gap:8px;
    box-shadow:0 8px 24px rgba(124,58,237,0.25);
    transition:.2s ease;
}
.btn-main:hover {
    transform:translateY(-2px);
    box-shadow:0 12px 32px rgba(124,58,237,0.35);
}

.mob-btn {
    background:none; border:none;
    font-size:22px; color:var(--dark);
    display:none;
}

/* ── CONTENT ── */
.content { flex:1; padding:28px; }

/* ── HERO ── */
.hero {
    background:linear-gradient(135deg,var(--violet) 0%,var(--pink) 60%,var(--orange) 100%);
    border-radius:var(--radius);
    padding:36px 40px;
    color:white; margin-bottom:24px;
    display:flex; align-items:center;
    justify-content:space-between;
    flex-wrap:wrap; gap:18px;
    position:relative; overflow:hidden;
    box-shadow:0 14px 40px rgba(124,58,237,0.22);
}
.hero::before {
    content:''; position:absolute;
    width:240px; height:240px;
    background:rgba(255,255,255,0.08);
    border-radius:50%;
    top:-90px; right:-60px;
}
.hero::after {
    content:''; position:absolute;
    width:160px; height:160px;
    background:rgba(255,255,255,0.05);
    border-radius:50%;
    bottom:-60px; right:130px;
}
.hero-txt { position:relative; z-index:1; }
.hero-txt h2 { font-size:26px; font-weight:800; margin-bottom:6px; }
.hero-txt p  { font-size:14px; opacity:.88; max-width:480px; line-height:1.6; }
.stat-pills { display:flex; gap:12px; flex-wrap:wrap; position:relative; z-index:1; }
.stat-pill {
    display:flex; align-items:center; gap:10px;
    padding:12px 18px;
    background:rgba(255,255,255,0.18);
    border:1px solid rgba(255,255,255,0.24);
    border-radius:14px;
    backdrop-filter:blur(8px);
    font-weight:700; font-size:15px;
    transition:.2s ease;
}
.stat-pill:hover {
    background:rgba(255,255,255,0.28);
    transform:translateY(-2px);
}
.stat-pill small { font-size:11px; font-weight:500; opacity:.82; display:block; }

/* ── FILTER BAR ── */
.filter-bar {
    background:var(--white);
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:16px 22px;
    box-shadow:var(--card-shadow);
    margin-bottom:20px;
    display:flex; align-items:center;
    gap:10px; flex-wrap:wrap;
}
.filter-label {
    font-size:13px; font-weight:700;
    color:var(--text-muted);
    display:flex; align-items:center; gap:6px;
}
.filter-label i { color:var(--violet); }

.filter-select {
    padding:10px 14px;
    border:2px solid var(--border);
    border-radius:14px;
    font-family:'Inter',sans-serif;
    font-size:13px; color:var(--text);
    background:var(--white);
    transition:.2s ease;
    cursor:pointer;
}
.filter-select:focus {
    outline:none;
    border-color:var(--violet);
    box-shadow:0 0 0 4px var(--violet-pale);
}

.btn-clear {
    padding:10px 16px;
    border-radius:14px;
    border:none;
    background:var(--error-pale);
    color:var(--error);
    font-weight:700; font-size:13px;
    display:flex; align-items:center; gap:6px;
    transition:.2s ease;
}
.btn-clear:hover { background:#fee2e2; }

.result-text {
    margin-left:auto;
    font-size:13px; color:var(--text-muted);
    font-weight:600;
}

/* ── TABLE CARD ── */
.table-card {
    background:var(--white);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--card-shadow);
    overflow:hidden;
}
.table-responsive { overflow-x:auto; }

.stu-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.stu-table thead th {
    padding:14px 16px;
    font-size:11px; font-weight:800;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:var(--text-muted);
    background:var(--border-light);
    border-bottom:2px solid var(--border);
    white-space:nowrap;
}
.stu-table tbody tr {
    border-bottom:1px solid var(--border);
    transition:.2s ease;
}
.stu-table tbody tr:last-child { border-bottom:none; }
.stu-table tbody tr:hover { background:var(--violet-pale); }
.stu-table td { padding:14px 16px; vertical-align:middle; }

/* Photo */
.stu-photo {
    width:42px; height:42px; border-radius:14px;
    object-fit:cover;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
.stu-initials {
    width:42px; height:42px; border-radius:14px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white; font-weight:800; font-size:15px;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 4px 12px rgba(124,58,237,0.20);
}

/* ID chip */
.id-chip {
    display:inline-block;
    padding:4px 10px; border-radius:8px;
    background:var(--violet-pale);
    color:var(--violet);
    font-weight:800; font-size:12px;
    white-space:nowrap;
}

/* Name */
.stu-name { font-weight:700; color:var(--dark); }
.stu-email { font-size:12px; color:var(--text-muted); margin-top:2px; }

/* Course chip */
.course-chip {
    display:inline-block;
    padding:4px 10px; border-radius:8px;
    background:var(--orange-pale);
    color:var(--orange);
    font-weight:700; font-size:12px;
}

/* Status */
.status-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:5px 12px; border-radius:50px;
    font-size:12px; font-weight:800;
}
.st-active   { background:var(--success-pale); color:var(--success); }
.st-inactive { background:var(--error-pale);   color:var(--error);   }

/* Action btns */
.act-row { display:flex; gap:6px; flex-wrap:nowrap; }

.btn-act {
    padding:8px 14px; border-radius:10px;
    font-weight:700; font-size:12px;
    display:flex; align-items:center; gap:5px;
    transition:.2s ease; border:none;
}
.btn-act.view {
    background:var(--violet-pale); color:var(--violet);
}
.btn-act.view:hover { background:var(--violet); color:white; }

.btn-act.edit {
    background:var(--orange-pale); color:var(--orange);
}
.btn-act.edit:hover { background:var(--orange); color:white; }

.btn-act.del {
    background:var(--error-pale); color:var(--error);
}
.btn-act.del:hover { background:var(--error); color:white; }

/* ── EMPTY ── */
.empty-state {
    padding:80px 20px; text-align:center;
    color:var(--text-muted);
}
.empty-icon {
    width:90px; height:90px; border-radius:50%;
    margin:0 auto 20px;
    background:linear-gradient(135deg,var(--violet-pale),var(--pink-pale));
    display:flex; align-items:center; justify-content:center;
    font-size:40px; color:var(--violet);
    box-shadow:0 10px 28px rgba(124,58,237,0.10);
}
.empty-state h3 { font-size:20px; font-weight:800; color:var(--dark); margin-bottom:8px; }
.empty-state p  { font-size:14px; max-width:320px; margin:0 auto; line-height:1.6; }

.btn-empty {
    display:inline-flex; align-items:center; gap:8px;
    margin-top:20px; padding:13px 24px;
    border-radius:14px; border:none;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white; font-weight:700; font-size:14px;
    box-shadow:0 8px 24px rgba(124,58,237,0.25);
    transition:.2s ease;
}
.btn-empty:hover { transform:translateY(-2px); }

/* ── PAGINATION ── */
.pag-wrap {
    padding:16px 22px;
    background:var(--border-light);
    border-top:1px solid var(--border);
    display:flex; align-items:center;
    justify-content:space-between;
    gap:12px; flex-wrap:wrap;
}
.pag-info { font-size:13px; color:var(--text-muted); font-weight:600; }
.pag-btns { display:flex; gap:6px; align-items:center; }

.pg {
    min-width:36px; height:36px;
    border-radius:10px; border:none;
    background:var(--white);
    border:2px solid var(--border);
    color:var(--text); font-weight:700; font-size:13px;
    display:flex; align-items:center; justify-content:center;
    transition:.2s ease; padding:0 10px;
}
.pg:hover {
    border-color:var(--violet);
    color:var(--violet);
    background:var(--violet-pale);
}
.pg.cur {
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white; border-color:transparent;
    box-shadow:0 4px 14px rgba(124,58,237,0.25);
}
.pg.off { opacity:.35; cursor:not-allowed; pointer-events:none; }

/* ── TOAST ── */
.toast-wrap {
    position:fixed; top:20px; right:20px; z-index:9999;
    display:flex; flex-direction:column; gap:8px;
}
.t-toast {
    display:flex; align-items:center; gap:12px;
    padding:14px 20px; border-radius:16px;
    background:var(--white);
    box-shadow:0 14px 40px rgba(0,0,0,0.10);
    border:1px solid var(--border);
    font-weight:700; font-size:14px;
    animation:toastIn .3s cubic-bezier(.34,1.56,.64,1);
    min-width:260px;
}
.t-toast.ok  { border-left:4px solid var(--success); }
.t-toast.err { border-left:4px solid var(--error);   }
.t-toast .t-i {
    width:36px; height:36px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:17px; flex-shrink:0;
}
.t-toast.ok  .t-i { background:var(--success-pale); color:var(--success); }
.t-toast.err .t-i { background:var(--error-pale);   color:var(--error);   }
.t-toast .t-x {
    margin-left:auto; background:none; border:none;
    font-size:14px; color:var(--text-muted); padding:4px;
}
@keyframes toastIn {
    from{opacity:0;transform:translateX(24px) scale(.92)}
    to{opacity:1;transform:translateX(0) scale(1)}
}

/* ── MODAL ── */
.modal-v .modal-content {
    background:var(--white);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:0 20px 60px rgba(0,0,0,0.12);
}
.modal-v .modal-header { border-bottom:1px solid var(--border); padding:20px 24px; }
.modal-v .modal-footer { border-top:1px solid var(--border); padding:16px 24px; }

/* ── RESPONSIVE ── */
@media(max-width:991px){
    .sidebar { transform:translateX(-270px); }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; }
    .mob-btn { display:block !important; }
}
@media(max-width:640px){
    .content { padding:16px; }
    .hero { padding:22px 20px; }
    .hero-txt h2 { font-size:20px; }
    .stat-pills { gap:8px; }
    .topbar { padding:12px 16px; }
    .search-fi { width:100%; }
    .act-row { flex-direction:column; gap:4px; }
}
</style>
</head>
<body>

<div class="toast-wrap" id="toastWrap"></div>

<!-- Delete modal -->
<div class="modal fade modal-v" id="delModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" style="color:var(--error)">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4" style="font-size:14px;color:var(--text)">
                Are you sure you want to permanently delete student
                <strong id="delName"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer gap-2">
                <button class="btn-act view" data-bs-dismiss="modal" style="padding:10px 20px">
                    Cancel
                </button>
                <a id="delBtn" href="#" class="btn-act del" style="padding:10px 20px;text-decoration:none">
                    <i class="bi bi-trash3-fill"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <div>
            <div class="brand-name">AUREON ERP</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>

    <div class="nav-section">MAIN MENU</div>
    <a href="super_admin.php"     class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="view_students.php" class="nav-link active"><i class="bi bi-people-fill"></i> All Students</a>
    <a href="add_student.php"   class="nav-link"><i class="bi bi-person-plus-fill"></i> Add Student</a>

    <div class="nav-section">ACADEMICS</div>
    <a href="teacher_requests.php" class="nav-link"><i class="bi bi-inbox-fill"></i> Requests</a>
    <a href="notifications.php"    class="nav-link"><i class="bi bi-bell-fill"></i> Notifications</a>

    <div class="sb-foot">
        <button class="logout-link" onclick="location.href='logout.php'">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </div>
</aside>

<!-- ============================================================
     MAIN
============================================================ -->
<main class="main">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="mob-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-left">
                <h2>All Students</h2>
                <p>Manage and view all registered students</p>
            </div>
        </div>

        <div class="topbar-right">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="text" name="search" class="fi search-fi"
                       placeholder="🔍 Search students…"
                       value="<?= htmlspecialchars($search) ?>">
                <?php if ($filterCourse !== 'all'): ?>
                    <input type="hidden" name="course" value="<?= htmlspecialchars($filterCourse) ?>">
                <?php endif; ?>
                <?php if ($filterStatus !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <?php endif; ?>
                <button type="submit" class="btn-main">
                    <i class="bi bi-search"></i> Search
                </button>
            </form>
            <a href="add_student.php" class="btn-main">
                <i class="bi bi-person-plus-fill"></i> Add Student
            </a>
        </div>
    </header>

    <div class="content">

        <?php if (isset($_GET['deleted'])): ?>
        <script>document.addEventListener('DOMContentLoaded',()=>toast('Student deleted successfully.','ok'));</script>
        <?php endif; ?>

        <!-- HERO -->
        <div class="hero">
            <div class="hero-txt">
                <h2>Student Management</h2>
                <p>Complete registry of all enrolled students. Search, filter, and manage records from one place.</p>
            </div>
            <div class="stat-pills">
                <div class="stat-pill">
                    <i class="bi bi-people-fill"></i>
                    <div><span><?= number_format((int)$stats['total']) ?></span><small>Total</small></div>
                </div>
                <div class="stat-pill">
                    <i class="bi bi-check-circle-fill"></i>
                    <div><span><?= number_format((int)$stats['active']) ?></span><small>Active</small></div>
                </div>
                <div class="stat-pill">
                    <i class="bi bi-book-half"></i>
                    <div><span><?= number_format((int)$stats['bca']) ?></span><small>BCA</small></div>
                </div>
                <div class="stat-pill">
                    <i class="bi bi-mortarboard-fill"></i>
                    <div><span><?= number_format((int)$stats['puc']) ?></span><small>PUC</small></div>
                </div>
                <div class="stat-pill">
                    <i class="bi bi-cpu-fill"></i>
                    <div><span><?= number_format((int)$stats['mca']) ?></span><small>MCA</small></div>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <form method="GET" class="filter-bar">
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <span class="filter-label"><i class="bi bi-funnel-fill"></i> Filter:</span>

            <select name="course" class="filter-select" onchange="this.form.submit()">
                <option value="all"   <?= $filterCourse==='all'?'selected':'' ?>>All Courses</option>
                <option value="BCA"   <?= $filterCourse==='BCA'?'selected':'' ?>>BCA</option>
                <option value="MCA"   <?= $filterCourse==='MCA'?'selected':'' ?>>MCA</option>
                <option value="PUC"   <?= $filterCourse==='PUC'?'selected':'' ?>>PUC</option>
                <option value="B.Com" <?= $filterCourse==='B.Com'?'selected':'' ?>>B.Com</option>
                <option value="B.Sc"  <?= $filterCourse==='B.Sc'?'selected':'' ?>>B.Sc</option>
            </select>

            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="all"      <?= $filterStatus==='all'?'selected':'' ?>>All Status</option>
                <option value="Active"   <?= $filterStatus==='Active'?'selected':'' ?>>Active</option>
                <option value="Inactive" <?= $filterStatus==='Inactive'?'selected':'' ?>>Inactive</option>
            </select>

            <?php if ($search || $filterCourse!=='all' || $filterStatus!=='all'): ?>
            <a href="view_students.php" class="btn-clear">
                <i class="bi bi-x-circle-fill"></i> Clear
            </a>
            <?php endif; ?>

            <span class="result-text">
                <?= number_format($offset+1) ?>–<?= number_format(min($offset+$perPage,$totalRows)) ?>
                of <?= number_format($totalRows) ?> student<?= $totalRows!==1?'s':'' ?>
            </span>
        </form>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="stu-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Photo</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Course</th>
                            <th>Stream</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="12">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-person-slash"></i></div>
                                <h3>No Students Found</h3>
                                <p>
                                    <?= $search || $filterCourse!=='all' || $filterStatus!=='all'
                                        ? 'Try adjusting your search or filters.'
                                        : 'No students have been added yet.' ?>
                                </p>
                                <?php if (!$search && $filterCourse==='all' && $filterStatus==='all'): ?>
                                <a href="add_student.php" class="btn-empty">
                                    <i class="bi bi-person-plus-fill"></i> Add First Student
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php else: foreach ($students as $i => $s):
                        $fullName  = trim(htmlspecialchars(($s['first_name']??'').' '.($s['last_name']??'')));
                        $isActive  = ($s['status'] ?? '') === 'Active';
                        $stCls     = $isActive ? 'st-active' : 'st-inactive';
                        $stIco     = $isActive ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                        $photoFile = $s['photo'] ?? '';
                        $photoSrc  = $photoFile ? 'uploads/students/'.htmlspecialchars($photoFile) : null;
                        $initials  = strtoupper(substr($s['first_name']??'S',0,1).substr($s['last_name']??'',0,1));
                        $joined    = $s['created_at'] ? date('d M Y', strtotime($s['created_at'])) : '—';
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);font-weight:600"><?= $offset+$i+1 ?></td>

                        <td>
                            <?php if ($photoSrc): ?>
                                <img src="<?= $photoSrc ?>" class="stu-photo" alt="<?= $fullName ?>"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="stu-initials" style="display:none"><?= $initials ?></div>
                            <?php else: ?>
                                <div class="stu-initials"><?= $initials ?></div>
                            <?php endif; ?>
                        </td>

                        <td><span class="id-chip"><?= htmlspecialchars($s['student_id'] ?? '—') ?></span></td>

                        <td>
                            <div class="stu-name"><?= $fullName ?: '—' ?></div>
                            <?php if (!empty($s['email'])): ?>
                                <div class="stu-email"><?= htmlspecialchars($s['email']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td><span class="course-chip"><?= htmlspecialchars($s['course'] ?? '—') ?></span></td>

                        <td style="color:var(--text);font-weight:500"><?= htmlspecialchars($s['stream'] ?? '—') ?></td>

                        <td style="font-weight:600;white-space:nowrap"><?= htmlspecialchars($s['year'] ?? '—') ?></td>

                        <td>
                            <?php
                                $gen = $s['gender'] ?? '';
                                $gIcon = strtolower($gen)==='male' ? '♂' : (strtolower($gen)==='female' ? '♀' : '—');
                            ?>
                            <span style="font-size:16px"><?= $gIcon ?></span>
                            <?php if ($gen): ?>
                                <span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($gen) ?></span>
                            <?php endif; ?>
                        </td>

                        <td style="white-space:nowrap"><?= htmlspecialchars($s['phone'] ?? '—') ?></td>

                        <td>
                            <span class="status-badge <?= $stCls ?>">
                                <i class="bi <?= $stIco ?>"></i>
                                <?= htmlspecialchars($s['status'] ?? 'Unknown') ?>
                            </span>
                        </td>

                        <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= $joined ?></td>

                        <td>
                            <div class="act-row">
                                <a href="student_profile.php?id=<?= $s['id'] ?>" class="btn-act view">
                                    <i class="bi bi-eye-fill"></i> View
                                </a>
                                <a href="edit_student.php?id=<?= $s['id'] ?>" class="btn-act edit">
                                    <i class="bi bi-pencil-fill"></i> Edit
                                </a>
                                <button class="btn-act del"
                                        onclick="confirmDel(<?= $s['id'] ?>,'<?= addslashes($fullName) ?>')">
                                    <i class="bi bi-trash3-fill"></i> Del
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>

                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <div class="pag-wrap">
                <div class="pag-info">
                    Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                    &nbsp;·&nbsp; <?= number_format($totalRows) ?> records
                </div>
                <div class="pag-btns">
                    <?php
                        $bq = array_filter([
                            'search'=>$search,
                            'course'=>$filterCourse!=='all'?$filterCourse:null,
                            'status'=>$filterStatus!=='all'?$filterStatus:null,
                        ]);
                        $base = '?'.http_build_query($bq);
                        $amp  = (strpos($base,'?')!==false && strlen($base)>1) ? '&' : '?';
                    ?>
                    <a href="<?= $base.$amp ?>page=1"           class="pg <?= $page<=1?'off':'' ?>"><i class="bi bi-chevron-double-left"></i></a>
                    <a href="<?= $base.$amp ?>page=<?= max(1,$page-1) ?>" class="pg <?= $page<=1?'off':'' ?>"><i class="bi bi-chevron-left"></i></a>

                    <?php
                        $start = max(1,min($page-2,$totalPages-4));
                        $end   = min($totalPages,$start+4);
                        for($p=$start;$p<=$end;$p++):
                    ?>
                    <a href="<?= $base.$amp ?>page=<?= $p ?>" class="pg <?= $p===$page?'cur':'' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <a href="<?= $base.$amp ?>page=<?= min($totalPages,$page+1) ?>" class="pg <?= $page>=$totalPages?'off':'' ?>"><i class="bi bi-chevron-right"></i></a>
                    <a href="<?= $base.$amp ?>page=<?= $totalPages ?>"               class="pg <?= $page>=$totalPages?'off':'' ?>"><i class="bi bi-chevron-double-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Sidebar mobile */
const mb=document.querySelector('.mob-btn');
function sync(){mb.style.display=window.innerWidth<992?'block':'none'}
sync();window.addEventListener('resize',sync);
document.addEventListener('click',e=>{
    const s=document.getElementById('sidebar');
    if(window.innerWidth<992&&s.classList.contains('open')&&!s.contains(e.target)&&e.target!==mb)s.classList.remove('open');
});

/* Toast */
function toast(msg,type='ok'){
    const ic={ok:'bi-check-circle-fill',err:'bi-x-circle-fill'};
    const w=document.getElementById('toastWrap');
    const d=document.createElement('div');
    d.className=`t-toast ${type}`;
    d.innerHTML=`<div class="t-i"><i class="bi ${ic[type]}"></i></div><div style="flex:1">${msg}</div><button class="t-x" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>`;
    w.appendChild(d);
    setTimeout(()=>{d.style.cssText='opacity:0;transform:translateX(22px);transition:.3s';setTimeout(()=>d.remove(),350)},4000);
}

/* Delete confirm */
function confirmDel(id,name){
    document.getElementById('delName').textContent=name;
    document.getElementById('delBtn').href='view_students.php?delete='+id;
    new bootstrap.Modal(document.getElementById('delModal')).show();
}
</script>
</body>
</html>