<?php
/**
 * ============================================================
 * AUREON ERP — Teacher Requests Page
 * File: teacher_requests.php
 * ============================================================
 * Teacher sees all parent requests assigned to them.
 * Accept / Reject via AJAX.
 * Premium cream/orange glassmorphism UI.
 * ============================================================
 */

session_start();

/* ============================================================
   AUTH CHECK — Teachers only
============================================================ */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: login.php');
    exit;
}

/* ============================================================
   DATABASE
============================================================ */
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
   TEACHER ID FROM SESSION
   Supports both $_SESSION['reference_id'] and $_SESSION['user_id']
============================================================ */
$userId = (int)$_SESSION['user_id'];


/* Fetch teacher's reference_id code and name from users table */
$ts = $pdo->prepare("
    SELECT full_name, reference_id
    FROM   users
    WHERE  id   = ?
      AND  role = 'teacher'
    LIMIT 1
");
$ts->execute([$userId]);
$teacherRow = $ts->fetch();

if (!$teacherRow) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$teacherName = $teacherRow['full_name'];
$teacherId   = $teacherRow['reference_id']; // e.g. TCHBCA99001

/* ============================================================
   AJAX HANDLERS
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    /* ── Fetch all requests for this teacher ── */
    if ($action === 'fetch_requests') {
        $filter = $_POST['filter'] ?? 'All';
        $search = trim($_POST['search'] ?? '');

        $sql = "
            SELECT id, student_name, parent_name, message, status, created_at
            FROM   parent_teacher_requests
            WHERE teacher_id = ?
        ";
        $params = [$teacherId];

        if ($filter !== 'All') {
            $sql    .= " AND status = ? ";
            $params[] = $filter;
        }

        if ($search !== '') {
            $sql    .= " AND (parent_name LIKE ? OR student_name LIKE ?) ";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY created_at DESC";

        $s = $pdo->prepare($sql);
        $s->execute($params);
        $rows = $s->fetchAll();

        /* Stats */
        $statSql = $pdo->prepare("
            SELECT
                COUNT(*)                                         AS total,
                SUM(status = 'Pending')                          AS pending,
                SUM(status = 'Accepted')                         AS accepted,
                SUM(status = 'Rejected')                         AS rejected
            FROM parent_teacher_requests
            WHERE teacher_id = ?
        ");
        $statSql->execute([$teacherId]);
        $stats = $statSql->fetch();

        echo json_encode([
            'ok'    => 1,
            'rows'  => $rows,
            'stats' => $stats,
        ]);
        exit;
    }

    /* ── Accept ── */
    if ($action === 'accept') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = $pdo->prepare("
            UPDATE parent_teacher_requests
            SET    status = 'Accepted'
            WHERE  id     = ?
                AND  teacher_id = ?
        ");
        $ok = $s->execute([$id, $teacherId]);
        echo json_encode(['ok' => $ok ? 1 : 0, 'msg' => $ok ? 'Request accepted.' : 'Failed.']);
        exit;
    }

    /* ── Reject ── */
    if ($action === 'reject') {
        $id = (int)($_POST['id'] ?? 0);
        $s  = $pdo->prepare("
            UPDATE parent_teacher_requests
            SET    status = 'Rejected'
            WHERE  id     = ?
              AND  teacher_id = ?
        ");
        $ok = $s->execute([$id, $teacherId]);
        echo json_encode(['ok' => $ok ? 1 : 0, 'msg' => $ok ? 'Request rejected.' : 'Failed.']);
        exit;
    }

    echo json_encode(['ok' => 0, 'msg' => 'Unknown action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AUREON ERP — Teacher Requests</title>

<!-- Bootstrap 5 -->
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
  rel="stylesheet">

<!-- Bootstrap Icons -->
<link
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
  rel="stylesheet">

<!-- Google Font -->
<link
  href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
  rel="stylesheet">

<style>
/* ============================================================
   THEME VARIABLES
============================================================ */
:root {
    --cream:     #fff9f0;
    --beige:     #fdf4e8;
    --acc:       #e89a4a;
    --acc-l:     #ffe6c7;
    --acc-d:     #c97d30;
    --txt:       #3f2a1e;
    --muted:     #876a57;
    --glass:     rgba(255,255,255,.72);
    --shadow:    0 12px 38px rgba(63,42,30,.08);
    --shadow-h:  0 20px 54px rgba(232,154,74,.22);
    --radius:    26px;
    --radius-sm: 18px;
    --tr:        .28s cubic-bezier(.4,0,.2,1);
    --sw:        268px;
}

/* ── Reset ── */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }
body {
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    background: linear-gradient(145deg, var(--cream) 0%, #f5e8d5 55%, #edddd0 100%);
    color: var(--txt);
    min-height: 100vh;
    display: flex;
}

/* decorative orbs */
body::before, body::after {
    content:''; position:fixed; border-radius:50%;
    filter:blur(90px); opacity:.26; z-index:-1; pointer-events:none;
}
body::before { width:380px;height:380px; background:#f2b977; top:-120px;left:-120px; }
body::after  { width:460px;height:460px; background:#e89a4a; bottom:-150px;right:-150px; }

a { text-decoration:none; color:inherit; }
button { cursor:pointer; font-family:inherit; }
::-webkit-scrollbar { width:6px; }
::-webkit-scrollbar-thumb { background:var(--acc-l); border-radius:10px; }

/* ============================================================
   SIDEBAR
============================================================ */
.sidebar {
    width: var(--sw);
    height: 100vh;
    position: fixed; left:0; top:0;
    background: var(--glass);
    backdrop-filter: blur(22px);
    border-right: 1px solid rgba(255,255,255,.68);
    box-shadow: var(--shadow);
    display: flex; flex-direction: column;
    padding: 26px 18px;
    z-index: 200;
    transition: transform var(--tr);
}
.brand {
    display:flex; align-items:center; gap:14px;
    margin-bottom:38px; padding-bottom:22px;
    border-bottom:1px solid rgba(232,154,74,.14);
}
.brand-icon {
    width:52px;height:52px;border-radius:20px;flex-shrink:0;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:23px;
    box-shadow:0 12px 28px rgba(232,154,74,.32);
}
.brand-name { font-weight:800; font-size:17px; }
.brand-sub  { font-size:11px; color:var(--muted); }

.nav-label {
    font-size:10px;font-weight:700;text-transform:uppercase;
    letter-spacing:.8px;color:var(--muted);
    padding:0 10px 8px; margin-top:14px;
}
.nav-link-item {
    display:flex;align-items:center;gap:13px;
    padding:13px 16px; border-radius:var(--radius-sm);
    color:var(--muted); font-weight:600; font-size:14px;
    transition:var(--tr);
    width:100%;background:none;border:none;
    text-align:left; margin-bottom:4px;
}
.nav-link-item:hover   { background:rgba(255,255,255,.80); color:var(--acc); transform:translateX(4px); }
.nav-link-item.active  { background:linear-gradient(135deg,#fff1de,#ffe4c2); color:var(--acc); box-shadow:0 8px 22px rgba(232,154,74,.16); }
.nav-link-item i       { font-size:18px; width:22px; text-align:center; }

.sidebar-footer {
    margin-top:auto; padding-top:20px;
    border-top:1px solid rgba(232,154,74,.13);
}
.logout-btn {
    display:flex;align-items:center;gap:12px;
    padding:13px 16px;border-radius:var(--radius-sm);
    color:#ef4444;font-weight:700;font-size:14px;
    background:none;border:none;width:100%;transition:var(--tr);
}
.logout-btn:hover { background:#fef2f2; }

/* ============================================================
   MAIN
============================================================ */
.main {
    margin-left: var(--sw);
    flex:1; padding:30px;
    display:flex; flex-direction:column; gap:24px;
}

/* ============================================================
   TOPBAR
============================================================ */
.topbar {
    background: var(--glass);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.82);
    border-radius: var(--radius);
    padding: 18px 26px;
    display:flex; align-items:center; justify-content:space-between;
    box-shadow: var(--shadow);
    position:sticky; top:18px; z-index:100;
    gap:16px; flex-wrap:wrap;
}
.topbar-title { font-size:21px; font-weight:800; margin-bottom:3px; }
.topbar-sub   { font-size:12px; color:var(--muted); }
.topbar-right { display:flex; align-items:center; gap:12px; }
.profile-pill {
    display:flex;align-items:center;gap:11px;
    padding:7px 16px 7px 7px;
    background:rgba(255,255,255,.65);
    border:1px solid rgba(255,255,255,.82);
    border-radius:50px;
    box-shadow:0 6px 18px rgba(63,42,30,.07);
}
.profile-av {
    width:38px;height:38px;border-radius:50%;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-weight:800;font-size:16px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 6px 16px rgba(232,154,74,.24);
}
.profile-name  { font-size:13px;font-weight:700; }
.profile-role  { font-size:11px;color:var(--muted); }
.icon-btn {
    width:42px;height:42px;border-radius:14px;
    background:rgba(255,255,255,.65);
    border:1px solid rgba(255,255,255,.82);
    display:flex;align-items:center;justify-content:center;
    font-size:18px;color:var(--muted);
    transition:var(--tr);
}
.icon-btn:hover { background:#fff; color:var(--acc); transform:translateY(-2px); }

/* ============================================================
   HERO
============================================================ */
.hero {
    position:relative;overflow:hidden;
    background:linear-gradient(135deg,#f0b26f 0%,var(--acc) 50%,var(--acc-d) 100%);
    border-radius:var(--radius);
    padding:42px 46px;
    color:#fff;
    box-shadow:0 22px 58px rgba(232,154,74,.32);
}
.hero::before {
    content:'';position:absolute;
    width:320px;height:320px;background:rgba(255,255,255,.10);
    border-radius:50%;top:-130px;right:-90px;
}
.hero::after {
    content:'';position:absolute;
    width:200px;height:200px;background:rgba(255,255,255,.07);
    border-radius:50%;bottom:-80px;right:160px;
}
.hero-badge {
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 18px;
    background:rgba(255,255,255,.20);
    border:1px solid rgba(255,255,255,.30);
    border-radius:50px;font-size:12px;font-weight:700;
    margin-bottom:14px;backdrop-filter:blur(8px);
}
.hero h1 { font-size:32px;font-weight:800;margin-bottom:10px;position:relative;z-index:1; }
.hero p   { font-size:14px;opacity:.88;max-width:560px;line-height:1.65;position:relative;z-index:1; }
.hero-stats {
    display:flex;flex-wrap:wrap;gap:14px;
    margin-top:26px;position:relative;z-index:1;
}
.hero-stat {
    display:flex;align-items:center;gap:10px;
    padding:12px 20px;
    background:rgba(255,255,255,.18);
    border:1px solid rgba(255,255,255,.26);
    border-radius:18px;backdrop-filter:blur(8px);
    font-weight:700;font-size:15px;
    transition:var(--tr);
}
.hero-stat:hover { background:rgba(255,255,255,.26); transform:translateY(-2px); }
.hero-stat small { font-size:11px;font-weight:500;opacity:.80;display:block; }

/* ============================================================
   FILTER BAR
============================================================ */
.filter-bar {
    display:flex;flex-wrap:wrap;gap:12px;align-items:center;
    background:var(--glass);
    backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,.78);
    border-radius:var(--radius);
    padding:16px 22px;
    box-shadow:var(--shadow);
}
.filter-btn {
    padding:10px 20px;border-radius:14px;
    border:1.5px solid rgba(232,154,74,.20);
    background:rgba(255,255,255,.70);
    color:var(--muted);font-weight:700;font-size:13px;
    transition:var(--tr);
}
.filter-btn:hover       { background:var(--acc-l); color:var(--acc); border-color:rgba(232,154,74,.35); }
.filter-btn.active      { background:linear-gradient(135deg,#fff1de,#ffe4c2); color:var(--acc); border-color:rgba(232,154,74,.35); box-shadow:0 6px 18px rgba(232,154,74,.16); }
.search-input {
    flex:1;min-width:200px;
    padding:11px 18px;border-radius:14px;
    border:1.5px solid rgba(232,154,74,.20);
    background:rgba(255,255,255,.80);
    font-family:inherit;font-size:14px;color:var(--txt);
    outline:none;transition:border-color var(--tr),box-shadow var(--tr);
}
.search-input:focus { border-color:var(--acc); box-shadow:0 0 0 4px rgba(232,154,74,.12); }
.search-input::placeholder { color:#bba898; }
.refresh-btn {
    width:46px;height:46px;border-radius:14px;border:none;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-size:18px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 10px 24px rgba(232,154,74,.26);
    transition:var(--tr);
}
.refresh-btn:hover { transform:scale(1.06) rotate(15deg); }

/* ============================================================
   REQUEST CARDS
============================================================ */
.requests-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap:20px;
}
.req-card {
    background:rgba(255,255,255,.74);
    border:1px solid rgba(232,154,74,.14);
    border-radius:var(--radius);
    padding:22px;
    position:relative;
    transition:var(--tr);
    box-shadow:var(--shadow);
    display:flex;flex-direction:column;gap:14px;
    overflow:hidden;
}
.req-card::before {
    content:'';
    position:absolute;top:0;left:0;right:0;height:4px;
    border-radius:var(--radius) var(--radius) 0 0;
}
.req-card.pending  ::before { background:var(--acc); }
.req-card.accepted ::before { background:#22c55e; }
.req-card.rejected ::before { background:#ef4444; }
.req-card.pending  { border-left:4px solid var(--acc); }
.req-card.accepted { border-left:4px solid #22c55e; }
.req-card.rejected { border-left:4px solid #ef4444; opacity:.82; }
.req-card:hover { transform:translateY(-5px); box-shadow:var(--shadow-h); }

.req-header {
    display:flex;align-items:flex-start;
    justify-content:space-between;gap:12px;
}
.req-av {
    width:48px;height:48px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-weight:800;font-size:19px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 8px 20px rgba(232,154,74,.26);
}
.req-parent-name { font-weight:800;font-size:16px;margin-bottom:3px; }
.req-student {
    display:inline-flex;align-items:center;gap:6px;
    font-size:12px;color:var(--muted);font-weight:600;
}
.status-pill {
    display:inline-flex;align-items:center;gap:7px;
    padding:6px 14px;border-radius:50px;
    font-size:12px;font-weight:800;flex-shrink:0;
}
.status-pill.Pending  { background:var(--acc-l); color:var(--acc); }
.status-pill.Accepted { background:#dcfce7; color:#15803d; }
.status-pill.Rejected { background:#fee2e2; color:#dc2626; }

.req-message {
    background:rgba(255,255,255,.65);
    border:1px solid rgba(232,154,74,.12);
    border-radius:14px;
    padding:14px 16px;
    font-size:13.5px;line-height:1.6;
    color:var(--txt);
    position:relative;
}
.req-message::before {
    content:'\275D';
    position:absolute;top:-8px;left:12px;
    font-size:22px;color:var(--acc-l);
}
.req-time {
    display:flex;align-items:center;gap:7px;
    font-size:12px;color:var(--muted);
}
.req-actions { display:flex;gap:10px; }

.btn-accept {
    flex:1;padding:12px;border-radius:14px;border:none;
    background:linear-gradient(135deg,#4ade80,#22c55e);
    color:#fff;font-weight:800;font-size:13px;
    box-shadow:0 10px 24px rgba(34,197,94,.22);
    transition:var(--tr);
    display:flex;align-items:center;justify-content:center;gap:7px;
}
.btn-accept:hover { transform:translateY(-3px); box-shadow:0 16px 34px rgba(34,197,94,.30); }

.btn-reject {
    flex:1;padding:12px;border-radius:14px;
    border:1.5px solid rgba(239,68,68,.28);
    background:rgba(239,68,68,.07);
    color:#dc2626;font-weight:800;font-size:13px;
    transition:var(--tr);
    display:flex;align-items:center;justify-content:center;gap:7px;
}
.btn-reject:hover { background:#ef4444; color:#fff; border-color:#ef4444; transform:translateY(-3px); }

.btn-accepted-badge {
    width:100%;padding:11px;border-radius:14px;border:none;
    background:#dcfce7;color:#15803d;
    font-weight:800;font-size:13px;
    display:flex;align-items:center;justify-content:center;gap:7px;
}
.btn-rejected-badge {
    width:100%;padding:11px;border-radius:14px;border:none;
    background:#fee2e2;color:#dc2626;
    font-weight:800;font-size:13px;
    display:flex;align-items:center;justify-content:center;gap:7px;
}

/* card shimmer loading */
.card-shimmer {
    background:rgba(255,255,255,.60);
    border-radius:var(--radius);
    padding:22px;
    box-shadow:var(--shadow);
    overflow:hidden;
    position:relative;
}
.card-shimmer::after {
    content:'';
    position:absolute;inset:0;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);
    animation:shimmer 1.4s infinite;
}
@keyframes shimmer { from{transform:translateX(-100%)} to{transform:translateX(100%)} }
.sh-line {
    height:14px;border-radius:8px;
    background:rgba(232,154,74,.14);
    margin-bottom:12px;
}
.sh-circle {
    width:48px;height:48px;border-radius:50%;
    background:rgba(232,154,74,.14);
}

/* ============================================================
   EMPTY STATE
============================================================ */
.empty-state {
    grid-column:1/-1;
    text-align:center;padding:70px 20px;
    color:var(--muted);
}
.empty-state i { font-size:58px;opacity:.28;margin-bottom:16px;display:block; }
.empty-state h4 { font-size:18px;font-weight:800;margin-bottom:8px; }
.empty-state p  { font-size:14px;opacity:.65;max-width:320px;margin:0 auto;line-height:1.55; }

/* ============================================================
   TOAST
============================================================ */
.toast-wrap {
    position:fixed;top:24px;right:24px;z-index:9999;
    display:flex;flex-direction:column;gap:10px;
}
.toast-item {
    display:flex;align-items:center;gap:13px;
    padding:15px 22px;border-radius:20px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(18px);
    box-shadow:0 18px 48px rgba(63,42,30,.13);
    border:1px solid rgba(255,255,255,.90);
    font-weight:700;font-size:14px;
    animation:toastIn .32s cubic-bezier(.34,1.56,.64,1);
    min-width:280px;max-width:360px;
}
.toast-item.ok   { border-left:4px solid #22c55e; }
.toast-item.err  { border-left:4px solid #ef4444; }
.toast-item.warn { border-left:4px solid var(--acc); }
.t-ico {
    width:38px;height:38px;border-radius:13px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:18px;
}
.toast-item.ok   .t-ico { background:#dcfce7;color:#16a34a; }
.toast-item.err  .t-ico { background:#fee2e2;color:#dc2626; }
.toast-item.warn .t-ico { background:var(--acc-l);color:var(--acc); }
.toast-close {
    margin-left:auto;background:none;border:none;
    font-size:16px;color:var(--muted);padding:4px;
}
@keyframes toastIn { from{opacity:0;transform:translateX(28px) scale(.92)} to{opacity:1;transform:translateX(0) scale(1)} }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width:991px) {
    .sidebar { transform:translateX(calc(-1 * var(--sw))); }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; }
    .hero { padding:28px 22px; }
    .hero h1 { font-size:24px; }
    .requests-grid { grid-template-columns:1fr; }
    .mob-show { display:flex !important; }
}
.mob-show { display:none; }

/* spin animation for refresh */
.spinning { animation:spin .6s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">

    <div class="brand">
        <div class="brand-icon">
            <i class="bi bi-person-lines-fill"></i>
        </div>
        <div>
            <div class="brand-name">AUREON ERP</div>
            <div class="brand-sub">Teacher Portal</div>
        </div>
    </div>

    <div class="nav-label">Main Menu</div>

    <a href="teacher_dash.php" class="nav-link-item">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    <a href="teacher_requests.php" class="nav-link-item active">
        <i class="bi bi-inbox-fill"></i> Parent Requests
    </a>
    <a href="teacher_chat.php" class="nav-link-item">
        <i class="bi bi-chat-dots-fill"></i> Chat
    </a>
    <a href="notification.php" class="nav-link-item">
        <i class="bi bi-bell-fill"></i> Notifications
    </a>

    <div class="sidebar-footer">
        <button class="logout-btn" onclick="location.href='logout.php'">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </div>

</aside>

<!-- ============================================================
     MAIN
============================================================ -->
<main class="main">

    <!-- ── Topbar ── -->
    <header class="topbar">
        <div>
            <div class="topbar-title">Parent Requests</div>
            <div class="topbar-sub">
                Logged in as: <strong><?= htmlspecialchars($teacherName) ?></strong>
                &nbsp;|&nbsp; Code: <strong><?= htmlspecialchars($teacherId) ?></strong>
            </div>
        </div>
        <div class="topbar-right">
            <button class="icon-btn mob-show" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <div class="profile-pill">
                <div class="profile-av">
                    <?= strtoupper(substr($teacherName, 0, 1)) ?>
                </div>
                <div>
                    <div class="profile-name"><?= htmlspecialchars($teacherName) ?></div>
                    <div class="profile-role">Teacher</div>
                </div>
            </div>
        </div>
    </header>

    <!-- ── Hero ── -->
    <div class="hero">
        <div class="hero-badge">
            <i class="bi bi-inbox-fill"></i>
            Parent Request Center
        </div>
        <h1>Manage Parent Requests</h1>
        <p>
            View and respond to all parent communication requests assigned to your
            teacher account. Accept requests to start a live chat or reject ones
            that are not relevant.
        </p>
        <div class="hero-stats">
            <div class="hero-stat">
                <i class="bi bi-stack"></i>
                <div>
                    <span id="statTotal">—</span>
                    <small>Total Requests</small>
                </div>
            </div>
            <div class="hero-stat">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    <span id="statPending">—</span>
                    <small>Pending</small>
                </div>
            </div>
            <div class="hero-stat">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <span id="statAccepted">—</span>
                    <small>Accepted</small>
                </div>
            </div>
            <div class="hero-stat">
                <i class="bi bi-x-circle-fill"></i>
                <div>
                    <span id="statRejected">—</span>
                    <small>Rejected</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filter Bar ── -->
    <div class="filter-bar">
        <button class="filter-btn active" data-filter="All"     onclick="setFilter(this,'All')">All</button>
        <button class="filter-btn"        data-filter="Pending"  onclick="setFilter(this,'Pending')">
            <i class="bi bi-hourglass-split me-1"></i> Pending
        </button>
        <button class="filter-btn"        data-filter="Accepted" onclick="setFilter(this,'Accepted')">
            <i class="bi bi-check-circle me-1"></i> Accepted
        </button>
        <button class="filter-btn"        data-filter="Rejected" onclick="setFilter(this,'Rejected')">
            <i class="bi bi-x-circle me-1"></i> Rejected
        </button>

        <input
            type="text"
            class="search-input"
            id="searchInput"
            placeholder="🔍  Search by parent or student name…"
            oninput="debounceLoad()"
        >

        <button class="refresh-btn" onclick="loadRequests(true)" title="Refresh">
            <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
        </button>
    </div>

    <!-- ── Cards grid ── -->
    <div class="requests-grid" id="requestsGrid">
        <!-- shimmer loading skeletons -->
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="card-shimmer">
            <div class="d-flex gap-3 align-items-center mb-3">
                <div class="sh-circle"></div>
                <div class="flex-grow-1">
                    <div class="sh-line" style="width:60%"></div>
                    <div class="sh-line" style="width:40%"></div>
                </div>
            </div>
            <div class="sh-line"></div>
            <div class="sh-line" style="width:80%"></div>
            <div class="sh-line" style="width:50%"></div>
        </div>
        <?php endfor; ?>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ============================================================
   STATE
============================================================ */
let activeFilter = 'All';
let debTimer     = null;
let pollTimer    = null;

/* ============================================================
   HELPERS
============================================================ */
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function post(data) {
    return fetch(window.location.href, {
        method  : 'POST',
        headers : {'Content-Type':'application/x-www-form-urlencoded'},
        body    : new URLSearchParams(data).toString()
    }).then(async r => {
    const text = await r.text();

    try {
        return JSON.parse(text);
    } catch (e) {
        console.error(text);
        throw e;
    }
});
}

function toast(msg, type = 'ok') {
    const icons = {ok:'bi-check-circle-fill', err:'bi-x-circle-fill', warn:'bi-exclamation-triangle-fill'};
    const wrap  = document.getElementById('toastWrap');
    const el    = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.innerHTML = `
        <div class="t-ico"><i class="bi ${icons[type]}"></i></div>
        <div style="flex:1;line-height:1.4">${esc(msg)}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>`;
    wrap.appendChild(el);
    setTimeout(() => {
        el.style.cssText = 'opacity:0;transform:translateX(30px);transition:.35s';
        setTimeout(() => el.remove(), 380);
    }, 4000);
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

/* ============================================================
   FILTER
============================================================ */
function setFilter(btn, filter) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeFilter = filter;
    loadRequests(false);
}

function debounceLoad() {
    clearTimeout(debTimer);
    debTimer = setTimeout(() => loadRequests(false), 380);
}

/* ============================================================
   LOAD REQUESTS
============================================================ */
async function loadRequests(showSpinner) {
    if (showSpinner) {
        const ico = document.getElementById('refreshIcon');
        ico.classList.add('spinning');
        setTimeout(() => ico.classList.remove('spinning'), 900);
    }

    const search = document.getElementById('searchInput').value.trim();
    const d = await post({ action:'fetch_requests', filter:activeFilter, search });

    /* update stats */
    if (d.stats) {
        document.getElementById('statTotal').textContent    = d.stats.total    ?? 0;
        document.getElementById('statPending').textContent  = d.stats.pending   ?? 0;
        document.getElementById('statAccepted').textContent = d.stats.accepted  ?? 0;
        document.getElementById('statRejected').textContent = d.stats.rejected  ?? 0;
    }

    const grid = document.getElementById('requestsGrid');

    if (!d.ok || !d.rows || !d.rows.length) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h4>No Requests Found</h4>
                <p>
                    ${activeFilter !== 'All'
                        ? `No <strong>${esc(activeFilter)}</strong> requests at the moment.`
                        : 'No parent requests have been sent to you yet. They will appear here once parents send a request using your Teacher ID.'}
                </p>
            </div>`;
        return;
    }

    grid.innerHTML = d.rows.map(r => buildCard(r)).join('');
}

/* ============================================================
   BUILD CARD HTML
============================================================ */
function buildCard(r) {
    const statusClass = r.status;
    const statusIcon  = r.status === 'Accepted' ? 'bi-check-circle-fill'
                      : r.status === 'Rejected'  ? 'bi-x-circle-fill'
                      : 'bi-hourglass-split';

    const actionsHtml = r.status === 'Pending'
        ? `<button class="btn-accept" onclick="acceptReq(${r.id}, this)">
               <i class="bi bi-check-circle-fill"></i> Accept
           </button>
           <button class="btn-reject" onclick="rejectReq(${r.id}, this)">
               <i class="bi bi-x-circle"></i> Reject
           </button>`
        : r.status === 'Accepted'
        ? `<div class="btn-accepted-badge">
               <i class="bi bi-check-circle-fill"></i> Request Accepted
           </div>`
        : `<div class="btn-rejected-badge">
               <i class="bi bi-x-circle-fill"></i> Request Rejected
           </div>`;

    return `
    <div class="req-card ${statusClass.toLowerCase()}" id="card-${r.id}">

        <div class="req-header">
            <div class="d-flex align-items-center gap-12">
                <div class="req-av">${esc(r.parent_name.charAt(0).toUpperCase())}</div>
                <div class="ms-2">
                    <div class="req-parent-name">${esc(r.parent_name)}</div>
                    <div class="req-student">
    Student: ${esc(r.student_name)}
</div>
                </div>
            </div>
            <span class="status-pill ${r.status}">
                <i class="bi ${statusIcon}"></i>
                ${esc(r.status)}
            </span>
        </div>

        <div class="req-message">${esc(r.message)}</div>

        <div class="req-time">
            🕒
            ${esc(r.created_at)}
        </div>

        <div class="req-actions">${actionsHtml}</div>

    </div>`;
}

/* ============================================================
   ACCEPT
============================================================ */
async function acceptReq(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Accepting…';

    const d = await post({ action:'accept', id });

    if (!d.ok) {
        toast(d.msg || 'Failed to accept.', 'err');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Accept';
        return;
    }

    toast('Request accepted! Parent can now start chat.', 'ok');

    /* Update card in-place without full reload */
    const card = document.getElementById(`card-${id}`);
    if (card) {
        card.className = 'req-card accepted';
        card.querySelector('.status-pill').className  = 'status-pill Accepted';
        card.querySelector('.status-pill').innerHTML  = '<i class="bi bi-check-circle-fill"></i> Accepted';
        card.querySelector('.req-actions').innerHTML  =
            `<div class="btn-accepted-badge">
                <i class="bi bi-check-circle-fill"></i> Request Accepted
             </div>`;
    }

    /* Refresh stats */
    loadStats();
}

/* ============================================================
   REJECT
============================================================ */
async function rejectReq(id, btn) {
    if (!confirm('Are you sure you want to reject this request?')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Rejecting…';

    const d = await post({ action:'reject', id });

    if (!d.ok) {
        toast(d.msg || 'Failed to reject.', 'err');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle"></i> Reject';
        return;
    }

    toast('Request rejected.', 'warn');

    const card = document.getElementById(`card-${id}`);
    if (card) {
        card.className = 'req-card rejected';
        card.querySelector('.status-pill').className  = 'status-pill Rejected';
        card.querySelector('.status-pill').innerHTML  = '<i class="bi bi-x-circle-fill"></i> Rejected';
        card.querySelector('.req-actions').innerHTML  =
            `<div class="btn-rejected-badge">
                <i class="bi bi-x-circle-fill"></i> Request Rejected
             </div>`;
    }

    loadStats();
}

/* ============================================================
   LOAD STATS ONLY (lightweight)
============================================================ */
async function loadStats() {
    const d = await post({ action:'fetch_requests', filter:'All', search:'' });
    if (d.stats) {
        document.getElementById('statTotal').textContent    = d.stats.total    ?? 0;
        document.getElementById('statPending').textContent  = d.stats.pending   ?? 0;
        document.getElementById('statAccepted').textContent = d.stats.accepted  ?? 0;
        document.getElementById('statRejected').textContent = d.stats.rejected  ?? 0;
    }
}

/* ============================================================
   INIT + AUTO REFRESH EVERY 15s
============================================================ */
loadRequests(false);
pollTimer = setInterval(() => loadRequests(false), 15000);
</script>

</body>
</html>