<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=aureon;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `category` ENUM('General','Academic','Event','Urgent') DEFAULT 'General',
    `image_path` VARCHAR(500) DEFAULT NULL,
    `created_by` VARCHAR(100) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Active','Archived') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $row = $pdo->prepare("SELECT image_path FROM announcements WHERE id=?");
    $row->execute([$id]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['image_path']) && file_exists($r['image_path'])) {
        @unlink($r['image_path']);
    }
    $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
    header("Location: announcements.php?toast=deleted");
    exit;
}

// Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title    = trim($_POST['title'] ?? '');
    $message  = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $imgPath  = null;

    if (!empty($_FILES['image']['name'])) {
        $dir = 'uploads/announcements/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $fname = uniqid('ann_') . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
            $imgPath = $dir . $fname;
        }
    }

    $pdo->prepare("INSERT INTO announcements (title, message, category, image_path, created_by) VALUES (?,?,?,?,?)")
        ->execute([$title, $message, $category, $imgPath, $_SESSION['full_name'] ?? 'Admin']);

    header("Location: announcements.php?toast=posted");
    exit;
}

// Fetch
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$total      = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$active     = $pdo->query("SELECT COUNT(*) FROM announcements WHERE status='Active'")->fetchColumn();
$urgent     = $pdo->query("SELECT COUNT(*) FROM announcements WHERE category='Urgent'")->fetchColumn();
$thisMonth  = $pdo->query("SELECT COUNT(*) FROM announcements WHERE MONTH(created_at)=MONTH(NOW())")->fetchColumn();

$admin_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin';
$initials   = strtoupper(substr($admin_name,0,1) . substr(strrchr($admin_name,' ') ?: $admin_name,1,1));
$toast      = $_GET['toast'] ?? '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements | AUREON ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"/>
<style>
/* ══════════════════════════════════
   ROOT — EXACT ADD TEACHER THEME
══════════════════════════════════ */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
    --violet:#7c3aed;--violet-dark:#6d28d9;--violet-light:#a78bfa;
    --violet-pale:#ede9fe;--violet-glow:rgba(124,58,237,.12);
    --orange:#f97316;--orange-pale:#fff7ed;
    --pink:#ec4899;--pink-pale:#fdf2f8;
    --teal:#14b8a6;--teal-pale:#f0fdfa;
    --blue:#3b82f6;--blue-pale:#eff6ff;
    --green:#10b981;--green-pale:#ecfdf5;
    --red:#ef4444;--red-pale:#fef2f2;
    --yellow:#f59e0b;--yellow-pale:#fffbeb;
    --dark:#1f1635;--text:#334155;--muted:#64748b;--dim:#94a3b8;
    --border:#e2e8f0;--light:#f1f5f9;--white:#ffffff;
    --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);
    --shadow:0 10px 30px rgba(0,0,0,.06);
    --shadow-lg:0 20px 50px rgba(0,0,0,.09);
    --radius:20px;--radius-md:16px;--radius-sm:12px;--radius-xs:8px;
}

html{scroll-behavior:smooth}

body{
    min-height:100vh;
    font-family:'Inter','Segoe UI',sans-serif;
    background:var(--bg);
    color:var(--text);
    font-size:16px;
    display:flex;
}

/* ══════════════════════════════════
   SIDEBAR — IDENTICAL TO ADD TEACHER
══════════════════════════════════ */


/* MENU TAKES FULL SPACE */
.sidebar-menu{
    display:flex;
    flex-direction:column;
    gap:10px;
    flex:1; /* 🔥 THIS PUSHES LOGOUT DOWN */
}

/* BOTTOM SECTION */
.sidebar-bottom{
    margin-top:auto; /* 🔥 FORCE BOTTOM */
}

/* BIG LOGO — same as add teacher */
.sidebar-logo{
    width:68px;height:68px;
    margin-bottom:28px;
    display:flex;align-items:center;justify-content:center;
}

.sidebar-logo img{
    width:100%;height:100%;
    object-fit:contain;
    filter:drop-shadow(0 4px 10px rgba(124,58,237,.25));
}

.sidebar-nav{
    flex:1;display:flex;flex-direction:column;
    align-items:center;gap:6px;width:100%;padding:0 10px;
}

/* MENU ITEMS FIX */
.menu-item {
    width: 100%;
    padding: 14px 16px;

    display: flex;
    align-items: center;
    gap: 14px;

    border-radius: 12px;

    font-size: 14px;
    font-weight: 600;

    color: var(--muted);
    text-decoration: none;

    transition: all .25s ease;
}

.menu-item i {
    font-size: 18px;
}

/* HOVER */
.menu-item:hover {
    background: var(--violet-pale);
    color: var(--violet);
    transform: scale(1.05);
}

/* ACTIVE */
.menu-item.active {
    background: linear-gradient(135deg, var(--violet), var(--pink));
    color: #fff;
    box-shadow: 0 8px 24px rgba(124,58,237,.25);
}

/* LOGOUT BUTTON */
.menu-item.logout {
    background: var(--red-pale);
    color: var(--red);
}

/* LOGOUT HOVER */
.menu-item.logout:hover {
    background: rgba(239,68,68,0.15);
    color: #dc2626;
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(239,68,68,.2);
}
/* ══════════════════════════════════
   MAIN
══════════════════════════════════ */
.main{margin-left:220px;flex:1;min-height:100vh;display:flex;flex-direction:column}

/* TOP HEADER — identical to add teacher */
.top-header{
    position:sticky;top:0;z-index:50;
    background:rgba(255,255,255,.82);
    backdrop-filter:blur(14px);
    border-bottom:1px solid var(--light);
    padding:16px 40px;
    display:flex;align-items:center;justify-content:space-between;
}

.header-brand{display:flex;align-items:center;gap:12px}

.header-icon{
    width:44px;height:44px;border-radius:12px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    display:flex;align-items:center;justify-content:center;
    color:white;font-size:17px;
}

.header-title{font-size:18px;font-weight:700;color:var(--dark)}
.header-title span{color:var(--muted);font-weight:500;font-size:14px;margin-left:6px}

.header-right{display:flex;align-items:center;gap:12px}

.profile-avatar{
    width:44px;height:44px;border-radius:12px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;display:flex;align-items:center;justify-content:center;
    font-size:15px;font-weight:700;
}

.profile-info{text-align:right}
.profile-info .pname{font-size:15px;font-weight:600;color:var(--dark)}
.profile-info .prole{font-size:12px;color:var(--muted)}

/* ══════════════════════════════════
   PAGE CONTENT
══════════════════════════════════ */
.page-content{padding:36px 42px;flex:1}

/* PAGE TITLE — same as add teacher */
.page-title{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:32px;flex-wrap:wrap;gap:14px;
}

.page-title-left{display:flex;align-items:center;gap:14px}

.page-title-icon{
    width:56px;height:56px;border-radius:14px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    display:flex;align-items:center;justify-content:center;
    color:white;font-size:22px;
    box-shadow:0 8px 20px rgba(124,58,237,.2);
}

.page-title h1{font-size:28px;font-weight:800;color:var(--dark)}
.page-title p{font-size:15px;color:var(--muted);margin-top:3px}

.breadcrumb{
    font-size:14px;color:var(--dim);
    display:flex;align-items:center;gap:6px;
}
.breadcrumb a{color:var(--muted);text-decoration:none}
.breadcrumb a:hover{color:var(--violet)}

/* ══════════════════════════════════
   STATS GRID
══════════════════════════════════ */
.stats-grid{
    display:grid;grid-template-columns:repeat(4,1fr);
    gap:16px;margin-bottom:32px;
}

.stat-card{
    background:var(--white);border-radius:var(--radius-md);
    padding:22px;box-shadow:var(--shadow);
    transition:all .3s;position:relative;overflow:hidden;
}

.stat-card::after{
    content:'';position:absolute;
    bottom:-24px;right:-24px;
    width:80px;height:80px;border-radius:50%;opacity:.08;
}

.stat-card:nth-child(1)::after{background:var(--violet)}
.stat-card:nth-child(2)::after{background:var(--green)}
.stat-card:nth-child(3)::after{background:var(--red)}
.stat-card:nth-child(4)::after{background:var(--yellow)}

.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}

.stat-icon{
    width:46px;height:46px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:19px;color:white;margin-bottom:14px;
}

.stat-card:nth-child(1) .stat-icon{background:linear-gradient(135deg,var(--violet),var(--violet-dark))}
.stat-card:nth-child(2) .stat-icon{background:linear-gradient(135deg,var(--green),#059669)}
.stat-card:nth-child(3) .stat-icon{background:linear-gradient(135deg,var(--red),#dc2626)}
.stat-card:nth-child(4) .stat-icon{background:linear-gradient(135deg,var(--yellow),#d97706)}

.stat-value{font-size:32px;font-weight:800;color:var(--dark)}
.stat-label{font-size:13px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:4px}

/* ══════════════════════════════════
   TWO COLUMN LAYOUT
══════════════════════════════════ */
.layout{
    display:grid;
    grid-template-columns:400px 1fr;
    gap:28px;
    align-items:start;
}

/* ══════════════════════════════════
   FORM CARD — same style as add teacher
══════════════════════════════════ */
.form-card{
    background:var(--white);border-radius:var(--radius);
    box-shadow:var(--shadow);overflow:hidden;
    position:sticky;top:90px;
}

.card-head{
    padding:22px 28px;border-bottom:1px solid var(--light);
    display:flex;align-items:center;gap:10px;
}

.card-head-icon{
    width:36px;height:36px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;font-size:15px;
}

.ch-violet .card-head-icon{background:var(--violet-pale);color:var(--violet)}

.card-head h3{font-size:18px;font-weight:700;color:var(--dark)}

.card-body{padding:24px 28px;}

/* FORM GROUPS — same as add teacher */
.fg{display:flex;flex-direction:column;gap:8px;margin-bottom:20px}
.fg:last-child{margin-bottom:0}

.fg label{
    font-size:14px;font-weight:700;color:var(--muted);
    display:flex;align-items:center;gap:4px;
}
.fg label .req{color:var(--red)}

/* INPUTS — same height, radius, focus as add teacher */
.fi{
    width:100%;height:50px;
    background:var(--white);
    border:1.5px solid var(--border);
    border-radius:var(--radius-sm);
    padding:0 16px;
    font-size:15px;color:var(--dark);
    font-family:'Inter',sans-serif;outline:none;
    transition:all .25s;-webkit-appearance:none;
}
.fi:focus{border-color:var(--violet);box-shadow:0 0 0 4px var(--violet-glow)}
.fi::placeholder{color:var(--dim)}

textarea.fi{height:130px;padding:14px 16px;resize:vertical}

select.fi{
    cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;
}

/* UPLOAD ZONE */
.upload-zone{
    border:2px dashed var(--border);border-radius:var(--radius-sm);
    padding:22px;text-align:center;cursor:pointer;transition:all .25s;
}
.upload-zone:hover{border-color:var(--violet-light);background:var(--violet-pale)}
.upload-zone.has-file{border-color:var(--green);background:var(--green-pale)}
.upload-zone input{display:none}
.upload-zone i{font-size:30px;color:var(--dim);margin-bottom:8px;display:block}
.upload-zone p{font-size:14px;color:var(--muted);font-weight:600}
.upload-zone .chosen{font-size:14px;color:var(--violet);font-weight:700;margin-top:8px}
.preview-img{
    width:100%;max-height:150px;object-fit:cover;
    border-radius:var(--radius-xs);margin-top:12px;display:none;
}

/* CARD ACTIONS */
.card-actions{
    padding:20px 28px;
    background:var(--light);
    border-top:1px solid var(--border);
    display:flex;gap:12px;
}

/* BUTTONS — same as add teacher */
.btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:13px 26px;border:none;border-radius:var(--radius-sm);
    font-size:15px;font-weight:700;cursor:pointer;
    font-family:'Inter',sans-serif;transition:all .3s;
    text-decoration:none;white-space:nowrap;
}

.btn-submit{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;box-shadow:0 6px 20px rgba(124,58,237,.2);flex:1;
    justify-content:center;
}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 12px 35px rgba(124,58,237,.3)}

.btn-reset{
    background:var(--light);color:var(--muted);
    border:1.5px solid var(--border);
}
.btn-reset:hover{background:var(--border);color:var(--dark)}

/* ══════════════════════════════════
   FEED / NOTICE CARDS
══════════════════════════════════ */
.feed-head{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:20px;
}

.feed-head h3{
    font-size:20px;font-weight:700;color:var(--dark);
    display:flex;align-items:center;gap:10px;
}
.feed-head h3 i{color:var(--violet)}

.feed-count{
    background:var(--white);border:1px solid var(--border);
    padding:7px 16px;border-radius:20px;
    font-size:14px;font-weight:700;color:var(--muted);
    box-shadow:var(--shadow);
}

/* Notice Cards */
.notice-card{
    background:var(--white);border-radius:var(--radius);
    box-shadow:var(--shadow);
    border-left:5px solid var(--violet);
    margin-bottom:18px;overflow:hidden;
    transition:all .35s cubic-bezier(0.4,0,0.2,1);
    position:relative;
}

.notice-card.academic{border-left-color:var(--blue)}
.notice-card.event{border-left-color:var(--teal)}
.notice-card.urgent{border-left-color:var(--yellow)}
.notice-card.general{border-left-color:var(--violet)}

.notice-card:hover{
    transform:translateY(-5px);
    box-shadow:var(--shadow-lg);
}

.nc-body{padding:22px 26px}

.nc-top{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:12px;gap:10px;
}

.cat-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 14px;border-radius:20px;
    font-size:12px;font-weight:800;
    text-transform:uppercase;letter-spacing:.5px;
}

.cat-general{background:var(--violet-pale);color:var(--violet)}
.cat-academic{background:var(--blue-pale);color:var(--blue)}
.cat-event{background:var(--teal-pale);color:var(--teal)}
.cat-urgent{background:var(--yellow-pale);color:var(--yellow)}

.nc-time{font-size:13px;color:var(--dim);font-weight:600}
.nc-title{font-size:20px;font-weight:800;color:var(--dark);margin-bottom:10px;line-height:1.3}
.nc-text{font-size:15px;color:var(--muted);line-height:1.7;margin-bottom:14px}

.nc-img{
    width:100%;max-height:240px;object-fit:cover;
    border-radius:var(--radius-xs);margin-bottom:14px;
    border:1px solid var(--border);
}

.nc-meta{
    display:flex;align-items:center;gap:12px;
    font-size:13px;color:var(--dim);font-weight:600;
    padding-top:12px;border-top:1px solid var(--light);
}
.nc-meta i{color:var(--violet)}

.nc-del{
    position:absolute;top:18px;right:18px;
    width:36px;height:36px;border-radius:10px;border:none;
    background:var(--red-pale);color:var(--red);
    font-size:15px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    opacity:0;transform:translateY(-6px);
    transition:all .25s;
}
.notice-card:hover .nc-del{opacity:1;transform:translateY(0)}
.nc-del:hover{background:var(--red);color:white;transform:scale(1.1)}

/* Empty State */
.empty-state{
    background:var(--white);border-radius:var(--radius);
    box-shadow:var(--shadow);padding:80px 20px;
    text-align:center;color:var(--muted);
}
.empty-state i{font-size:60px;opacity:.2;margin-bottom:18px;display:block;color:var(--violet)}
.empty-state h3{font-size:20px;font-weight:700;color:var(--dark);margin-bottom:8px}

/* ══════════════════════════════════
   ALERT
══════════════════════════════════ */
.alert{
    padding:16px 22px;border-radius:var(--radius-sm);
    margin-bottom:24px;font-size:15px;font-weight:600;
    display:flex;align-items:center;gap:12px;
    animation:fadeDown .35s ease;
}
.alert i{font-size:20px}
.alert-success{background:var(--green-pale);border:1px solid rgba(16,185,129,.2);color:var(--green)}
.alert-deleted{background:var(--red-pale);border:1px solid rgba(239,68,68,.2);color:var(--red)}
@keyframes fadeDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

/* ══════════════════════════════════
   MOBILE
══════════════════════════════════ */
.mobile-header{
    display:none;position:fixed;top:0;left:0;right:0;
    height:60px;background:rgba(255,255,255,.92);
    backdrop-filter:blur(12px);border-bottom:1px solid var(--light);
    z-index:90;padding:0 16px;align-items:center;justify-content:space-between;
}
.mobile-header .mb{display:flex;align-items:center;gap:8px;font-weight:800;font-size:17px;color:var(--violet)}
.mobile-header .mb img{height:30px}
.hamburger{
    width:40px;height:40px;border:none;background:var(--violet-pale);
    border-radius:10px;color:var(--violet);font-size:18px;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:95}

@media(max-width:1200px){.layout{grid-template-columns:1fr;}}
@media(max-width:1000px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){
    .sidebar{transform:translateX(-100%);width:260px;padding:20px;background:rgba(255,255,255,.97);align-items:stretch}
    .sidebar.open{transform:translateX(0)}
    .sidebar .s-item{width:100%;height:auto;flex-direction:row;justify-content:flex-start;gap:12px;padding:13px 14px;font-size:14px}
    .sidebar .s-item .tip{display:none}
    .sidebar .s-logout{width:100%;height:auto;padding:13px;font-size:14px;border-radius:10px;flex-direction:row;gap:12px;justify-content:flex-start}
    .main{margin-left:0}
    .mobile-header{display:flex}
    .overlay.show{display:block}
    .page-content{padding:80px 16px 32px}
    .top-header{display:none}
    .layout{grid-template-columns:1fr}
    .page-title{flex-direction:column;align-items:flex-start}
    .form-card{position:static}
    .card-actions{flex-direction:column}
    .btn{width:100%;justify-content:center}
}
@media(max-width:600px){
    .stats-grid{grid-template-columns:1fr 1fr}
    body{font-size:15px}
}
</style>
</head>
<body>

<!-- Mobile Header -->
<div class="mobile-header">
    <div class="mb"><img src="logo.png" alt="AUREON"> AUREON ERP</div>
    <button class="hamburger" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
</div>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>


<!-- ══════ SIDEBAR — identical to add teacher ══════ -->
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

.sidebar{
    width:240px;
    background:#fff;
    border-right:1px solid #eee;
    position:fixed;
    top:0;
    bottom:0;
    padding:20px;

    display:flex;
    flex-direction:column;
}.sidebar-logo{
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

    <nav class="sidebar-menu">

        <a href="super_admin.php" class="menu-item active">
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>

        <a href="add_student.php" class="menu-item">
            <i class="fa-solid fa-user-graduate"></i>
            <span>Students</span>
        </a>

        <a href="announcements.php" class="menu-item">
            <i class="fa-solid fa-bullhorn"></i>
            <span>Announcements</span>
        </a>



    </nav>


    <div class="sidebar-bottom">
        <a href="logout.php" class="menu-item logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
</aside>


<!-- ══════ MAIN ══════ -->
<main class="main">

    <!-- TOP HEADER -->
    <div class="top-header">
        <div class="header-brand">
            <div class="header-icon"><i class="fa-solid fa-bullhorn"></i></div>
            <div class="header-title">
                AUREON ERP <span>| ANNOUNCEMENTS</span>
            </div>
        </div>
        <div class="header-right">
            <div class="profile-info">
                <div class="pname"><?= h($admin_name) ?></div>
                <div class="prole">Super Admin</div>
            </div>
            <div class="profile-avatar"><?= h($initials) ?></div>
        </div>
    </div>

    <div class="page-content">

        <!-- PAGE TITLE -->
        <div class="page-title">
            <div class="page-title-left">
                <div class="page-title-icon"><i class="fa-solid fa-bullhorn"></i></div>
                <div>
                    <h1>Announcements</h1>
                    <p>Post and manage official institute notices</p>
                </div>
            </div>
            <div class="breadcrumb">
                <a href="super_admin.php"><i class="fa-solid fa-house" style="font-size:12px"></i></a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <a href="super_admin.php">Dashboard</a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <span style="color:var(--violet)">Announcements</span>
            </div>
        </div>

        <!-- ALERT -->
        <?php if($toast === 'posted'): ?>
        <div class="alert alert-success" id="alertBox">
            <i class="fa-solid fa-circle-check"></i>
            Announcement published successfully!
        </div>
        <?php elseif($toast === 'deleted'): ?>
        <div class="alert alert-deleted" id="alertBox">
            <i class="fa-solid fa-trash"></i>
            Announcement deleted.
        </div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-bullhorn"></i></div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-value"><?= $active ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-value"><?= $urgent ?></div>
                <div class="stat-label">Urgent</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="stat-value"><?= $thisMonth ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>


        <!-- TWO COLUMN LAYOUT -->
        <div class="layout">

            <!-- LEFT: FORM CARD -->
            <div class="form-card">
                <div class="card-head ch-violet">
                    <div class="card-head-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <h3>Post New Announcement</h3>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="card-body">

                        <div class="fg">
                            <label>Category <span class="req">*</span></label>
                            <select name="category" class="fi">
                                <option value="General">General Notice</option>
                                <option value="Academic">Academic</option>
                                <option value="Event">Event</option>
                                <option value="Urgent">Urgent ⚡</option>
                            </select>
                        </div>

                        <div class="fg">
                            <label>Title <span class="req">*</span></label>
                            <input type="text" name="title" class="fi"
                                   placeholder="Enter announcement title" required>
                        </div>

                        <div class="fg">
                            <label>Message <span class="req">*</span></label>
                            <textarea name="message" class="fi"
                                      placeholder="Write your announcement here..." required></textarea>
                        </div>

                        <div class="fg">
                            <label>Attach Image (Optional)</label>
                            <div class="upload-zone" id="uploadZone"
                                 onclick="document.getElementById('imgInput').click()">
                                <input type="file" id="imgInput" name="image"
                                       accept="image/*">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Click to upload image</p>
                                <div class="chosen" id="imgChosen">No file chosen</div>
                                <img class="preview-img" id="imgPreview" alt="Preview">
                            </div>
                        </div>

                    </div>

                    <div class="card-actions">
                        <button type="reset" class="btn btn-reset"
                                onclick="resetForm()">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </button>
                        <button type="submit" name="post_announcement"
                                class="btn btn-submit">
                            <i class="fa-solid fa-paper-plane"></i> Publish
                        </button>
                    </div>
                </form>
            </div>


            <!-- RIGHT: FEED -->
            <div>
                <div class="feed-head">
                    <h3>
                        <i class="fa-solid fa-list-ul"></i> Recent Notices
                    </h3>
                    <span class="feed-count"><?= count($announcements) ?> Posts</span>
                </div>

                <?php if(empty($announcements)): ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell"></i>
                    <h3>No announcements yet</h3>
                    <p>Post your first announcement using the form</p>
                </div>
                <?php endif; ?>

                <?php foreach($announcements as $ann):
                    $catClass = strtolower($ann['category']);
                    $catBadge = 'cat-' . $catClass;
                    $catIcon = match($ann['category']) {
                        'Urgent'   => 'fa-bolt',
                        'Academic' => 'fa-graduation-cap',
                        'Event'    => 'fa-calendar-days',
                        default    => 'fa-bullhorn',
                    };
                ?>
                <div class="notice-card <?= $catClass ?>">

                    <!-- Delete button (appears on hover) -->
                    <button class="nc-del"
                        onclick="if(confirm('Delete this announcement?'))location.href='?delete=<?= $ann['id'] ?>'"
                        title="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>

                    <div class="nc-body">
                        <div class="nc-top">
                            <span class="cat-badge <?= $catBadge ?>">
                                <i class="fa-solid <?= $catIcon ?>"></i>
                                <?= h($ann['category']) ?>
                            </span>
                            <span class="nc-time">
                                <?= date('d M Y • h:i A', strtotime($ann['created_at'])) ?>
                            </span>
                        </div>

                        <h3 class="nc-title"><?= h($ann['title']) ?></h3>
                        <p class="nc-text"><?= nl2br(h($ann['message'])) ?></p>

                        <?php if(!empty($ann['image_path']) && file_exists($ann['image_path'])): ?>
                        <img src="<?= h($ann['image_path']) ?>"
                             class="nc-img" alt="Attachment">
                        <?php endif; ?>

                        <div class="nc-meta">
                            <span>
                                <i class="fa-solid fa-user"></i>
                                <?= h($ann['created_by']) ?>
                            </span>
                            <span>
                                <i class="fa-solid fa-circle-dot"></i>
                                <?= h($ann['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>

        </div>
    </div>
</main>


<script>
// Sidebar
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

// File preview
document.getElementById('imgInput').addEventListener('change', function(){
    const file = this.files[0];
    const zone = document.getElementById('uploadZone');
    const prev = document.getElementById('imgPreview');
    const chosen = document.getElementById('imgChosen');

    if(!file){
        chosen.textContent = 'No file chosen';
        prev.style.display = 'none';
        zone.classList.remove('has-file');
        return;
    }

    chosen.textContent = file.name;
    zone.classList.add('has-file');

    const reader = new FileReader();
    reader.onload = e => {
        prev.src = e.target.result;
        prev.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// Reset form
function resetForm(){
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imgChosen').textContent = 'No file chosen';
    document.getElementById('uploadZone').classList.remove('has-file');
}

// Auto-hide alert
const alertBox = document.getElementById('alertBox');
if(alertBox){
    setTimeout(() => {
        alertBox.style.transition = 'opacity .5s';
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
    }, 3500);
}
</script>

</body>
</html>