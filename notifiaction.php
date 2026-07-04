<?php
/**
 * ============================================================
 * AUREON ERP — TEACHER NOTIFICATION CENTER
 * File: notifications.php
 * ============================================================
 * Tech:
 * PHP + MySQL + Bootstrap 5.3 + Bootstrap Icons
 * ============================================================
 */

session_start();

/* ============================================================
   SESSION CHECK
============================================================ */
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'teacher'
) {
    header("Location: login.php");
    exit;
}

/* ============================================================
   DATABASE CONNECTION
============================================================ */
try {

    $pdo = new PDO(
        "mysql:host=localhost;dbname=aureon;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch(PDOException $e){
    die("Database connection failed.");
}

/* ============================================================
   TEACHER INFO
============================================================ */
$stmt = $pdo->prepare("
    SELECT full_name
    FROM users
    WHERE id = ?
    AND role = 'teacher'
");

$stmt->execute([$_SESSION['user_id']]);

$teacher = $stmt->fetch();

$teacher_name = $teacher['full_name'] ?? 'Teacher';

/* ============================================================
   FILTER
============================================================ */
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? 'All');

/* ============================================================
   FETCH NOTIFICATIONS
============================================================ */
$sql = "
SELECT *
FROM announcements
WHERE status='Active'
";

$params = [];

/* Search */
if($search !== ''){

    $sql .= "
    AND (
        title LIKE ?
        OR message LIKE ?
        OR category LIKE ?
    )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

/* Filter */
if($filter !== '' && $filter !== 'All'){

    $sql .= " AND category = ? ";
    $params[] = $filter;
}

$sql .= " ORDER BY id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$notifications = $stmt->fetchAll();

$total_notifications = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>AUREON ERP — Notifications</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>

/* ============================================================
   ROOT THEME
============================================================ */
:root{

    --bg:#f8f3ec;
    --sidebar:#fffaf3;
    --card:#ffffff;
    --accent:#e89a4a;
    --accent-light:#fff0df;

    --text:#2d2d2d;
    --muted:#8a8a8a;

    --border:#f1e7d9;

    --shadow:
    0 10px 35px rgba(0,0,0,0.06);

    --shadow-hover:
    0 16px 40px rgba(232,154,74,0.18);

    --radius:28px;
}

/* ============================================================
   BASE
============================================================ */
body{
    background:var(--bg);
    font-family:'Plus Jakarta Sans',sans-serif;
    color:var(--text);
    overflow-x:hidden;
}

a{
    text-decoration:none;
}

::-webkit-scrollbar{
    width:8px;
}

::-webkit-scrollbar-thumb{
    background:var(--accent);
    border-radius:20px;
}

/* ============================================================
   SIDEBAR
============================================================ */
.sidebar{
    width:280px;
    height:100vh;
    position:fixed;
    left:0;
    top:0;
    background:rgba(255,255,255,0.65);
    backdrop-filter:blur(18px);
    border-right:1px solid rgba(255,255,255,0.4);
    padding:28px 22px;
    display:flex;
    flex-direction:column;
    z-index:1000;
}

.logo{
    display:flex;
    align-items:center;
    gap:14px;
    margin-bottom:45px;
}

.logo-icon{
    width:56px;
    height:56px;
    border-radius:22px;
    background:linear-gradient(135deg,#f0b26f,#e89a4a);
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    font-size:24px;
    box-shadow:0 12px 24px rgba(232,154,74,0.3);
}

.logo h4{
    margin:0;
    font-weight:800;
    font-size:22px;
}

.logo small{
    color:var(--muted);
}

.menu{
    display:flex;
    flex-direction:column;
    gap:12px;
}

.menu a{
    display:flex;
    align-items:center;
    gap:14px;
    padding:16px 18px;
    border-radius:22px;
    color:var(--muted);
    font-weight:600;
    transition:all .3s ease;
}

.menu a:hover{
    background:white;
    color:var(--accent);
    transform:translateX(4px);
}

.menu a.active{
    background:linear-gradient(135deg,#fff1de,#ffe4c2);
    color:var(--accent);
    box-shadow:0 10px 30px rgba(232,154,74,0.16);
}

.menu i{
    font-size:20px;
}

.logout{
    margin-top:auto;
}

/* ============================================================
   MAIN
============================================================ */
.main{
    margin-left:280px;
    padding:28px;
}

/* ============================================================
   TOPBAR
============================================================ */
.topbar{
    background:rgba(255,255,255,0.62);
    backdrop-filter:blur(18px);
    border-radius:30px;
    padding:20px 26px;
    box-shadow:var(--shadow);
    display:flex;
    justify-content:space-between;
    align-items:center;
    position:sticky;
    top:18px;
    z-index:500;
    margin-bottom:28px;
}

.top-title{
    display:flex;
    align-items:center;
    gap:14px;
}

.top-title i{
    width:52px;
    height:52px;
    border-radius:20px;
    background:linear-gradient(135deg,#f3b56d,#e89a4a);
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    font-size:22px;
    box-shadow:0 12px 24px rgba(232,154,74,0.25);
}

.top-title h2{
    margin:0;
    font-weight:800;
}

.top-title p{
    margin:0;
    color:var(--muted);
    font-size:14px;
}

.profile{
    display:flex;
    align-items:center;
    gap:14px;
}

.avatar{
    width:54px;
    height:54px;
    border-radius:50%;
    background:linear-gradient(135deg,#f0b26f,#e89a4a);
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    font-weight:700;
    font-size:20px;
    box-shadow:0 10px 24px rgba(232,154,74,0.25);
}

/* ============================================================
   HERO
============================================================ */
.hero{
    position:relative;
    overflow:hidden;
    border-radius:36px;
    background:
    linear-gradient(135deg,
    #f5c389 0%,
    #ecab5f 45%,
    #e89a4a 100%);
    padding:48px;
    color:white;
    box-shadow:0 18px 50px rgba(232,154,74,0.28);
    margin-bottom:28px;
}

.hero::before{
    content:'';
    position:absolute;
    width:260px;
    height:260px;
    background:rgba(255,255,255,0.12);
    border-radius:50%;
    top:-80px;
    right:-60px;
}

.hero::after{
    content:'';
    position:absolute;
    width:180px;
    height:180px;
    background:rgba(255,255,255,0.08);
    border-radius:50%;
    bottom:-60px;
    right:120px;
}

.hero h1{
    font-weight:800;
    font-size:42px;
    position:relative;
    z-index:2;
}

.hero p{
    color:rgba(255,255,255,0.9);
    max-width:600px;
    position:relative;
    z-index:2;
}

.hero-count{
    margin-top:28px;
    display:inline-flex;
    align-items:center;
    gap:12px;
    padding:16px 24px;
    border-radius:24px;
    background:rgba(255,255,255,0.16);
    backdrop-filter:blur(12px);
    font-weight:700;
    position:relative;
    z-index:2;
}

.bell-animate{
    animation:ring 2s infinite;
}

@keyframes ring{
    0%,100%{transform:rotate(0deg);}
    20%{transform:rotate(14deg);}
    40%{transform:rotate(-10deg);}
    60%{transform:rotate(8deg);}
}

/* ============================================================
   FILTERS
============================================================ */
.filters{
    background:rgba(255,255,255,0.65);
    backdrop-filter:blur(16px);
    border-radius:30px;
    padding:20px;
    box-shadow:var(--shadow);
    margin-bottom:28px;
}

.form-control,
.form-select{
    border:none;
    border-radius:18px;
    padding:14px 18px;
    background:#fff;
    box-shadow:none !important;
}

.form-control:focus,
.form-select:focus{
    border:2px solid var(--accent);
}

/* ============================================================
   CARDS
============================================================ */
.notification-card{
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(18px);
    border-radius:32px;
    overflow:hidden;
    box-shadow:var(--shadow);
    transition:all .35s ease;
    height:100%;
    border:1px solid rgba(255,255,255,0.45);
    animation:fadeUp .5s ease;
}

.notification-card:hover{
    transform:translateY(-8px);
    box-shadow:var(--shadow-hover);
}

.notification-image{
    width:100%;
    height:230px;
    object-fit:cover;
}

.notification-body{
    padding:24px;
}

.category-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 16px;
    border-radius:999px;
    background:var(--accent-light);
    color:var(--accent);
    font-size:13px;
    font-weight:700;
    margin-bottom:16px;
}

.notification-title{
    font-size:22px;
    font-weight:800;
    margin-bottom:12px;
}

.notification-message{
    color:var(--muted);
    line-height:1.7;
    margin-bottom:22px;
}

.meta{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:10px;
}

.meta small{
    color:var(--muted);
}

.read-btn{
    padding:12px 20px;
    border:none;
    border-radius:18px;
    background:linear-gradient(135deg,#f0b26f,#e89a4a);
    color:white;
    font-weight:700;
    transition:.3s;
}

.read-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 28px rgba(232,154,74,0.28);
}

/* ============================================================
   EMPTY
============================================================ */
.empty{
    background:white;
    border-radius:32px;
    padding:80px 30px;
    text-align:center;
    box-shadow:var(--shadow);
}

.empty i{
    font-size:70px;
    color:var(--accent);
    margin-bottom:20px;
}

.empty h3{
    font-weight:800;
    margin-bottom:12px;
}

.empty p{
    color:var(--muted);
}

/* ============================================================
   FOOTER
============================================================ */
.footer{
    margin-top:50px;
    text-align:center;
    color:var(--muted);
    padding-bottom:20px;
}

/* ============================================================
   ANIMATIONS
============================================================ */
@keyframes fadeUp{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* ============================================================
   RESPONSIVE
============================================================ */
@media(max-width:991px){

    .sidebar{
        position:relative;
        width:100%;
        height:auto;
        border-right:none;
    }

    .main{
        margin-left:0;
    }

    .hero{
        padding:34px;
    }

    .hero h1{
        font-size:30px;
    }
}

</style>
</head>

<body>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<div class="sidebar">

    <div class="logo">

        <div class="logo-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>

        <div>
            <h4>AUREON ERP</h4>
            <small>Teacher Portal</small>
        </div>

    </div>

    <div class="menu">

        <a href="teacher_dash.php">
            <i class="bi bi-grid-1x2-fill"></i>
            Dashboard
        </a>

        <a href="notifications.php" class="active">
            <i class="bi bi-bell-fill"></i>
            Notifications
        </a>

    </div>

    <div class="logout">

        <div class="menu">

            <a href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

    </div>

</div>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">

        <div class="top-title">

            <i class="bi bi-bell-fill bell-animate"></i>

            <div>
                <h2>Notification Center</h2>
                <p>Manage and view latest ERP announcements</p>
            </div>

        </div>

        <div class="profile">

            <div class="text-end">
                <strong><?php echo htmlspecialchars($teacher_name); ?></strong>
                <div class="text-muted small">Teacher</div>
            </div>

            <div class="avatar">
                <?php echo strtoupper(substr($teacher_name,0,1)); ?>
            </div>

        </div>

    </div>

    <!-- HERO -->
    <div class="hero">

        <h1>
            Stay Updated Instantly
        </h1>

        <p>
            Access academic alerts, events, announcements,
            ERP notifications, and important institutional updates
            in one beautiful notification center.
        </p>

        <div class="hero-count">

            <i class="bi bi-bell-fill bell-animate"></i>

            <?php echo $total_notifications; ?>
            Active Notifications

        </div>

    </div>

    <!-- FILTERS -->
    <div class="filters">

        <form method="GET">

            <div class="row g-3">

                <div class="col-lg-8">

                    <input
                    type="text"
                    class="form-control"
                    name="search"
                    placeholder="Search notifications..."
                    value="<?php echo htmlspecialchars($search); ?>">

                </div>

                <div class="col-lg-4">

                    <select
                    class="form-select"
                    name="filter"
                    onchange="this.form.submit()">

                        <option value="All"
                        <?php if($filter==='All') echo 'selected'; ?>>
                            All Categories
                        </option>

                        <option value="Academic"
                        <?php if($filter==='Academic') echo 'selected'; ?>>
                            Academic
                        </option>

                        <option value="General"
                        <?php if($filter==='General') echo 'selected'; ?>>
                            General
                        </option>

                        <option value="Events"
                        <?php if($filter==='Events') echo 'selected'; ?>>
                            Events
                        </option>

                    </select>

                </div>

            </div>

        </form>

    </div>

    <!-- NOTIFICATIONS -->
    <?php if(empty($notifications)): ?>

        <div class="empty">

            <i class="bi bi-bell-slash-fill"></i>

            <h3>No Notifications Found</h3>

            <p>
                There are currently no active notifications
                matching your search criteria.
            </p>

        </div>

    <?php else: ?>

    <div class="row g-4">

        <?php foreach($notifications as $n): ?>

        <div class="col-xl-4 col-md-6">

            <div class="notification-card">

                <?php if(!empty($n['image_path'])): ?>

                    <img
                    src="<?php echo htmlspecialchars($n['image_path']); ?>"
                    class="notification-image"
                    alt="Notification">

                <?php else: ?>

                    <div class="notification-image d-flex align-items-center justify-content-center"
                    style="
                    background:
                    linear-gradient(135deg,#f4c48f,#e89a4a);
                    color:white;
                    font-size:60px;">
                        <i class="bi bi-image-fill"></i>
                    </div>

                <?php endif; ?>

                <div class="notification-body">

                    <div class="category-badge">

                        <i class="bi bi-tag-fill"></i>

                        <?php echo htmlspecialchars($n['category']); ?>

                    </div>

                    <div class="notification-title">

                        <?php echo htmlspecialchars($n['title']); ?>

                    </div>

                    <div class="notification-message">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                mb_strimwidth(
                                    $n['message'],
                                    0,
                                    180,
                                    '...'
                                )
                            )
                        );
                        ?>

                    </div>

                    <div class="meta">

                        <div>

                            <small>
                                <i class="bi bi-calendar-event"></i>

                                <?php
                                echo date(
                                    'd M Y',
                                    strtotime($n['created_at'])
                                );
                                ?>
                            </small>

                            <br>

                            <small>
                                <i class="bi bi-person-fill"></i>

                                <?php echo htmlspecialchars($n['created_by']); ?>
                            </small>

                        </div>

                        <button class="read-btn">
                            Read More
                        </button>

                    </div>

                </div>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

    <?php endif; ?>

    <!-- FOOTER -->
    <div class="footer">

        © <?php echo date('Y'); ?>
        AUREON ERP — Premium Teacher Notification Center

    </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>