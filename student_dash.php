<?php
session_start();

$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'student'");
$stmt->execute(['id' => $user_id]);

$login_user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT * FROM students WHERE email = :email");
$stmt2->execute(['email' => $login_user['email']]);

$user = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$user) die("Student not found.");

$joined_date = date("M j, Y", strtotime($user['created_at']));
$attendance = 87;

// Calendar Logic
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year  = isset($_GET['year'])  ? intval($_GET['year'])  : date('Y');

$first_day = mktime(0,0,0,$month,1,$year);
$days_in_month = date('t', $first_day);
$start_day = date('w', $first_day); // 0 = Sunday

// Fetch events for this month
$start_date = "$year-$month-01";
$end_date   = "$year-$month-$days_in_month";
$event_stmt = $pdo->prepare("SELECT * FROM academic_calendar WHERE calendar_date BETWEEN :start AND :end ORDER BY calendar_date ASC");
$event_stmt->execute(['start' => $start_date, 'end' => $end_date]);
$events = $event_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group events by date
$events_by_date = [];
foreach ($events as $ev) {
    $events_by_date[date('j', strtotime($ev['calendar_date']))][] = $ev;
}

// Notifications
$notif_stmt = $pdo->prepare("SELECT * FROM academic_calendar WHERE calendar_date >= CURDATE() ORDER BY calendar_date ASC LIMIT 5");
$notif_stmt->execute();
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Month navigation
$prev_month = $month - 1; $prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #fdf4e8;
            --sidebar: #f5f3ff;
            --accent: #8b5cf6;
            --accent-light: #ede9fe;
            --text: #1e293b;
            --muted: #64748b;
            --white: #ffffff;
            --mint: #10b981;
            --sky: #3b82f6;
            --peach: #f97316;
            --rose: #ef4444;
            --shadow: 0 8px 24px rgba(0,0,0,0.08);
            --radius: 20px;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { background:var(--bg); color:var(--text); display:flex; height:100vh; overflow:hidden; }

        /* Sidebar */
        .sidebar {
            width: 300px; background: var(--sidebar); padding: 25px 20px;
            position: fixed; height: 100%; box-shadow: 4px 0 15px rgba(139,92,246,0.08);
            display:flex; flex-direction:column; z-index:100;
        }
        .logo { text-align:center; margin-bottom:35px; }
        .logo-circle {
            width: 140px; height: 140px; margin:0 auto 15px;
            background: linear-gradient(135deg, #ede9fe, #8b5cf6);
            border-radius: 50%; display:flex; align-items:center; justify-content:center;
            box-shadow: 0 10px 25px rgba(139,92,246,0.25); overflow:hidden; border:6px solid white;
        }
        .logo-circle img { width:100%; height:100%; object-fit:cover; }
        .logo-circle .big-a { font-size:72px; font-weight:900; color:white; }
        .brand { font-size:1.4rem; font-weight:700; }
        .brand-sub { font-size:0.85rem; color:var(--accent); font-weight:500; }

        .nav-menu { list-style:none; flex:1; }
        .nav-link {
            display:flex; align-items:center; gap:14px; padding:14px 18px;
            color:var(--muted); text-decoration:none; border-radius:14px; margin-bottom:6px;
            font-weight:500; transition:all 0.3s ease;
        }
        .nav-link i { font-size:1.3rem; width:28px; text-align:center; }
        .nav-link:hover { background:rgba(139,92,246,0.1); color:var(--accent); }
        .nav-link.active { background:var(--accent); color:white; box-shadow:0 6px 15px rgba(139,92,246,0.3); }

        .logout-btn { color:var(--rose); font-weight:700; padding:14px 18px; border-radius:14px; text-decoration:none; display:flex; align-items:center; gap:14px; margin-top:auto; }
        .logout-btn:hover { background:rgba(239,68,68,0.1); }

        /* Main Content */
        .main-content { margin-left:300px; flex:1; padding:30px 40px; overflow-y:auto; }

        /* Top Bar */
        .top-bar {
            background:var(--white); padding:18px 25px; border-radius:var(--radius);
            box-shadow:var(--shadow); display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;
        }
        .welcome { display:flex; align-items:center; gap:15px; font-size:1.5rem; font-weight:600; }
        .welcome i { font-size:2rem; color:var(--accent); }
        .right-top { display:flex; align-items:center; gap:25px; }

        .notif-bell { position:relative; font-size:1.5rem; cursor:pointer; color:var(--muted); padding:10px; border-radius:50%; }
        .notif-bell:hover { background:var(--accent-light); color:var(--accent); }
        .notif-count { position:absolute; top:4px; right:6px; background:var(--rose); color:white; font-size:0.65rem; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .notif-dropdown {
            position:absolute; top:65px; right:10px; width:360px; background:white; border-radius:16px;
            box-shadow:0 15px 35px rgba(0,0,0,0.15); display:none; z-index:100; overflow:hidden;
        }
        .notif-dropdown.show { display:block; animation:drop 0.3s ease; }
        .notif-header { padding:15px; background:var(--accent-light); font-weight:600; color:var(--accent); }
        .notif-item { padding:14px 18px; border-bottom:1px solid #f1f5f9; font-size:0.9rem; }
        .notif-time { font-size:0.75rem; color:#94a3b8; margin-top:4px; }

        .profile-section { display:flex; align-items:center; gap:15px; }
        .profile-photo {
            width: 70px; height: 70px; border-radius:50%; border:4px solid var(--accent-light);
            object-fit:cover; background:#ddd;
        }

        /* KPI */
        .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:30px; }
        .kpi-card { background:var(--white); padding:22px; border-radius:var(--radius); box-shadow:var(--shadow); }
        .kpi-label { font-size:0.85rem; color:var(--muted); }
        .kpi-value { font-size:1.4rem; font-weight:700; margin-top:8px; }

        /* Quick Actions */
        .quick-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:22px; margin-bottom:35px; }
        .action-card {
            background:var(--white); padding:28px 20px; border-radius:var(--radius); text-align:center;
            cursor:pointer; transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1); box-shadow:var(--shadow);
        }
        .action-card:hover { transform: translateY(-12px) scale(1.04); box-shadow: 0 20px 30px -10px rgba(139,92,246,0.35); }
        .action-icon {
            width:70px; height:70px; margin:0 auto 15px; background:var(--accent-light);
            color:var(--accent); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:2rem;
        }

        /* Advanced Grid */
        .advanced-grid { display:grid; grid-template-columns:2fr 1fr 1fr; gap:25px; margin-bottom:30px; }
        .card { background:var(--white); border-radius:var(--radius); padding:25px; box-shadow:var(--shadow); }

        .section-title { font-size:1.2rem; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:10px; }

        /* Calendar */
        .calendar-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .calendar-nav { display:flex; gap:10px; }
        .calendar-nav a { background:var(--accent-light); color:var(--accent); padding:6px 12px; border-radius:8px; text-decoration:none; }
        .calendar-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; text-align:center; font-size:0.95rem; }
        .cal-day { color:var(--muted); font-weight:500; padding:8px 0; }
        .cal-date { padding:14px 0; border-radius:12px; position:relative; cursor:pointer; transition:all 0.2s; }
        .cal-date:hover { background:#f8fafc; }
        .cal-date.today { background:var(--accent); color:white; font-weight:600; }
        .cal-date.has-event::after {
            content:''; position:absolute; bottom:6px; left:50%; transform:translateX(-50%);
            width:6px; height:6px; border-radius:50%;
        }
        .event-tooltip {
            position:absolute; top:55px; left:50%; transform:translateX(-50%);
            background:white; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.15);
            padding:15px; width:220px; display:none; z-index:50; text-align:left;
        }
        .event-tooltip.show { display:block; }
        .event-item { padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:0.85rem; }
        .event-item:last-child { border:none; }

        /* Attendance */
        .attendance-circle {
            width: 170px; height: 170px; border-radius:50%; background:conic-gradient(#8b5cf6 0deg 315deg, #e2e8f0 315deg 360deg);
            display:flex; align-items:center; justify-content:center; margin:0 auto;
        }
        .attendance-inner {
            width: 135px; height: 135px; background:white; border-radius:50%;
            display:flex; flex-direction:column; align-items:center; justify-content:center;
        }
        .attendance-inner span { font-size:2.8rem; font-weight:700; color:var(--accent); }

        /* Documents */
        .doc-item {
            display:flex; align-items:center; gap:15px; padding:14px; background:var(--accent-light);
            border-radius:14px; margin-bottom:12px; transition:all 0.3s;
        }
        .doc-item:hover { transform:translateX(8px); background:#f3e8ff; }
        .doc-icon { width:45px; height:45px; background:white; border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--accent); box-shadow:0 2px 6px rgba(0,0,0,0.05); }

        /* Modals & ID Card */
        .id-card {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color:white; width:450px; border-radius:24px; padding:25px; box-shadow:0 20px 40px rgba(0,0,0,0.3);
            text-align:center; position:relative; overflow:hidden;
        }
        .college { font-size: 1.2rem; font-weight: 700; text-align: center; margin-bottom: 15px; letter-spacing: 1px; }
        .id-card h2 { margin-top: 10px; }
        .id-photo{ width:140px; height:170px; margin:20px auto; border:5px solid white; border-radius:12px; overflow:hidden; background:#ddd; }
        .id-photo img{ width:100%; height:100%; object-fit:cover; }
        .id-info { text-align:left; background:rgba(255,255,255,0.15); padding:20px; border-radius:16px; font-size:1.05rem; }
        .id-info div { margin:10px 0; }
        
        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:none; align-items:center; justify-content:center; z-index:1000; }
        .modal.show { display:flex; }

        @keyframes drop { from{opacity:0; transform:translateY(-20px);} to{opacity:1; transform:translateY(0);} }

        @media (max-width: 1200px) {
            .advanced-grid { grid-template-columns:1fr; }
            .kpi-grid, .quick-grid { grid-template-columns:repeat(2,1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { width:70px; }
            .brand, .brand-sub, .nav-link span { display:none; }
            .main-content { margin-left:70px; }
        }
        
        /* Floating Chatbot Button */
        .chatbot-fab {
            position: fixed; bottom: 25px; right: 25px; width: 65px; height: 65px; background: #8b5cf6;
            color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 26px; text-decoration: none; box-shadow: 0 10px 25px rgba(0,0,0,0.25); z-index: 9999;
            animation: floatUpDown 3s ease-in-out infinite;
        }
        .chatbot-fab:hover { transform: scale(1.15); background: #7c3aed; }
        @keyframes floatUpDown { 0% { transform: translateY(0); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0); } }
        .pulse-ring {
            position: absolute; width: 65px; height: 65px; border-radius: 50%; background: rgba(139, 92, 246, 0.4);
            animation: pulse 1.8s infinite; z-index: -1;
        }
        @keyframes pulse { 0% { transform: scale(1); opacity: 0.6; } 70% { transform: scale(1.8); opacity: 0; } 100% { opacity: 0; } }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo">
     <div class="logo">



    <div class="logo-circle">



        <span class="big-a">A</span>



        <i class="fa-solid fa-graduation-cap logo-cap"></i>



    </div>



    






</div>



<style>



/* ===============================

   STUDENT SIDEBAR LOGO

================================= */



.logo{

    text-align:center;

    margin-bottom:35px;

}



/* Main Circle */

.logo-circle{

    width:140px;

    height:140px;



    margin:0 auto 18px;



    position:relative;



    border-radius:50%;



    background:

    linear-gradient(

        135deg,

        #ede9fe 0%,

        #c4b5fd 45%,

        #8b5cf6 100%

    );



    display:flex;

    align-items:center;

    justify-content:center;



    border:6px solid rgba(255,255,255,0.95);



    box-shadow:

    0 18px 40px rgba(139,92,246,0.28),

    inset 0 2px 10px rgba(255,255,255,0.5);



    overflow:hidden;



    transition:all 0.4s ease;

}



/* Hover */

.logo-circle:hover{

    transform:

    translateY(-6px)

    scale(1.04);



    box-shadow:

    0 24px 50px rgba(139,92,246,0.35);

}



/* Big A */

.big-a{

    font-size:72px;



    font-weight:900;



    color:white;



    line-height:1;



    font-family:'Inter',sans-serif;



    text-shadow:

    0 8px 18px rgba(0,0,0,0.18);

}



/* Graduation Cap */

.logo-cap{

    position:absolute;



    top:24px;

    right:26px;



    font-size:24px;



    color:#ffffff;



    transform:rotate(-15deg);



    filter:

    drop-shadow(0 5px 10px rgba(0,0,0,0.18));

}



/* Brand */

.brand{

    font-size:1.45rem;



    font-weight:800;



    letter-spacing:0.5px;



    background:

    linear-gradient(

        135deg,

        #7c3aed,

        #8b5cf6

    );



    -webkit-background-clip:text;

    -webkit-text-fill-color:transparent;

}



/* Sub Text */

.brand-sub{

    font-size:0.88rem;



    color:#8b5cf6;



    font-weight:600;



    margin-top:4px;



    letter-spacing:0.4px;

}



</style>
            <div class="brand">AUREON ERP</div>
            <div class="brand-sub">Student Portal</div>
        </div>

        <ul class="nav-menu">
            <li><a href="#" class="nav-link active"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_profile.php" class="nav-link"><i class="fa-regular fa-calendar"></i> <span> My Profile</span></a></li>
            <li><a href="view_marks.php" class="nav-link"><i class="fa-solid fa-chart-simple"></i> <span>My Marks</span></a></li>
            <li><a href="view_books.php" class="nav-link"><i class="fa-solid fa-book-open"></i> <span>Library</span></a></li>
            <li><a href="student_events.php" class="nav-link"><i class="fa-solid fa-trophy"></i> <span>Sports</span></a></li>
            <li><a href="AI_news.php" class="nav-link"><i class="fa-solid fa-earth-asia"></i> <span>Start AI News</span></a></li>
        </ul>

        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main-content">

        <div class="top-bar">
            <div class="welcome">
                <i class="fa-solid fa-mortarboard"></i>
                <div>Welcome, <strong><?= htmlspecialchars($user['full_name']) ?></strong>!</div>
            </div>

            <div class="right-top">
                <div style="position:relative;" onclick="toggleNotif()">
                    <div class="notif-bell"><i class="fa-regular fa-bell"></i></div>
                    <div class="notif-count"><?= count($notifications) ?></div>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">Upcoming Events & Alerts</div>
                        <?php foreach($notifications as $n): ?>
                        <a href="student_announcements.php?id=<?= $n['id'] ?>" style="text-decoration:none;color:inherit;">
    <div class="notif-item">
        <?= htmlspecialchars($n['title']) ?> (<?= date('d M Y', strtotime($n['calendar_date'])) ?>)
        <div class="notif-time"><?= ucfirst($n['type']) ?></div>
    </div>
</a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="profile-section">
                    <div style="text-align:right;">
                        <strong><?= htmlspecialchars($user['student_id']) ?></strong><br>
                        <small style="color:var(--muted);">Student</small>
                    </div>
                    <img src="uploads/students/<?= $user['photo'] ?>" class="profile-photo">
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card"><div class="kpi-label">Student ID</div><div class="kpi-value"><?= htmlspecialchars($user['student_id']) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Email</div><div class="kpi-value" style="font-size:1.1rem;"><?= htmlspecialchars($user['email']) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Joined On</div><div class="kpi-value"><?= $joined_date ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Full Name</div><div class="kpi-value"><?= htmlspecialchars($user['full_name']) ?></div></div>
        </div>

        <div class="quick-grid">
            <div class="action-card" onclick="location.href='student_profile.php'">
                <div class="action-icon"><i class="fa-regular fa-calendar-check"></i></div>
                <h4>My Profile</h4>
            </div>
            <div class="action-card" onclick="location.href='view_marks.php'">
                <div class="action-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <h4>Check Marks</h4>
            </div>
            <div class="action-card" onclick="location.href='view_books.php'">
                <div class="action-icon"><i class="fa-solid fa-book"></i></div>
                <h4>Library Books</h4>
            </div>
            <div class="action-card" onclick="location.href='student_events.php'">
                <div class="action-icon"><i class="fa-solid fa-trophy"></i></div>
                <h4>Sports & Events</h4>
            </div>
        </div>

        <div class="advanced-grid">

            <div class="card">
                <div class="section-title"><i class="fa-regular fa-calendar"></i> Academic Calendar</div>
                <div class="calendar-header">
                    <h3><?= date('F Y', $first_day) ?></h3>
                    <div class="calendar-nav">
                        <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                </div>

                <div class="calendar-grid">
                    <div class="cal-day">S</div><div class="cal-day">M</div><div class="cal-day">T</div>
                    <div class="cal-day">W</div><div class="cal-day">T</div><div class="cal-day">F</div><div class="cal-day">S</div>

                    <?php for ($i = 0; $i < $start_day; $i++): ?>
                        <div></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $days_in_month; $day++): 
                        $date = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $today = date('Y-n-j') == "$year-$month-$day";
                        $has_event = isset($events_by_date[$day]);
                    ?>
                        <div class="cal-date <?= $today ? 'today' : '' ?> <?= $has_event ? 'has-event' : '' ?>"
                             style="<?= $has_event ? 'border:2px solid ' . $events_by_date[$day][0]['color'] : '' ?>"
                             onclick="showEventPopup(<?= $day ?>)">
                            <?= $day ?>
                            <?php if ($has_event): ?>
                            <div class="event-tooltip" id="tooltip-<?= $day ?>">
                                <?php foreach ($events_by_date[$day] as $ev): ?>
                                <div class="event-item" style="border-left:4px solid <?= $ev['color'] ?>">
                                    <strong><?= htmlspecialchars($ev['title']) ?></strong><br>
                                    <small><?= date('d M Y', strtotime($ev['calendar_date'])) ?> • <?= $ev['type'] ?></small><br>
                                    <small style="color:var(--muted);"><?= htmlspecialchars($ev['description']) ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="card" style="text-align:center;">
                <div class="section-title"><i class="fa-solid fa-chart-pie"></i> Attendance</div>
                <div class="attendance-circle">
                    <div class="attendance-inner">
                        <span><?= $attendance ?>%</span>
                        <small style="color:var(--mint); font-weight:600;">Excellent</small>
                    </div>
                </div>
                <p style="margin-top:15px; color:var(--muted);">Eligible for all exams</p>
                <button onclick="location.href='student_attendance.php'" style="margin-top:15px; background: var(--accent); color: white; border: none; padding: 10px 18px; border-radius: 12px; cursor: pointer; font-weight: 600;">
                    View Full Attendance Graph
                </button>
            </div>

            <div class="card">
                <div class="section-title"><i class="fa-solid fa-folder-open"></i> Profile & Documents</div>
                
                <div class="doc-item">
                    <div class="doc-icon"><i class="fa-solid fa-id-card"></i></div>
                    <span>Student ID Card</span>
                    <span style="margin-left:auto; color:var(--accent); cursor:pointer;" onclick="showIDCard()">View</span>
                </div>
                
                <div class="doc-item">
                    <div class="doc-icon"><i class="fa-solid fa-user-graduate"></i></div>
                    <span>Secure Digital Vault</span>
                    <span style="margin-left:auto; color:var(--accent); cursor:pointer;" onclick="showVaultModal()">Unlock</span>
                </div>
                
                <div class="doc-item">
                    <div class="doc-icon"><i class="fa-solid fa-receipt"></i></div>
                    <span>Fee Receipt</span>
                    <span style="margin-left:auto; color:var(--accent); cursor:pointer;" onclick="window.location.href='student_fee.php'">View</span>
                </div>
            </div>

        </div> </main>


    <div class="modal" id="vaultModal">
        <div class="id-card" style="max-width:450px; text-align:left; padding:30px;">
            <h2 style="text-align:center; margin-bottom: 20px;"><i class="fa-solid fa-shield-halved"></i> Digital Vault</h2>
            
            <div id="otp-step-1">
                <p style="margin-bottom:10px; font-size:0.9rem; color:white;">Verify your identity to access secure records.</p>
                <input type="email" id="vaultEmail" value="<?= htmlspecialchars($user['email']) ?>" readonly style="width:100%;padding:12px;margin-bottom:15px;border:none;border-radius:10px;color:#333;background:#f8fafc;">
                
                <button onclick="sendOTP()" style="width:100%;padding:12px;background:white;color:#1e3a8a;border:none;border-radius:10px;font-weight:bold;cursor:pointer;">
                    Send OTP to Email
                </button>
            </div>

            <div id="otp-step-2" style="display:none;">
                <p style="margin-bottom:10px; font-size:0.9rem; color:white;">Enter the 6-digit OTP sent to your email.</p>
                <input type="text" id="vaultOTP" placeholder="Enter OTP (e.g., 123456)" style="width:100%;padding:12px;margin-bottom:15px;border:none;border-radius:10px;color:#333;text-align:center;letter-spacing:2px;font-weight:bold;">
                
                <button onclick="verifyOTP()" style="width:100%;padding:12px;background:#10b981;color:white;border:none;border-radius:10px;font-weight:bold;cursor:pointer;">
                    Verify & Unlock
                </button>
            </div>

            <button onclick="hideVaultModal()" style="width:100%;padding:12px;margin-top:15px;background:#ef4444;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:bold;">
                Cancel
            </button>
        </div>
    </div>

    <div class="modal" id="idModal">
        <div class="id-card">
            <div class="college">HIMALAYA PUC COLLEGE, ANKOLA</div>
            <h2><?= htmlspecialchars($user['full_name']) ?></h2>
            <div class="id-photo">
                <img src="uploads/students/<?= $user['photo'] ?>" alt="Student Photo">
            </div>
            <div class="id-info">
                <div><strong>Student ID :</strong> <?= htmlspecialchars($user['student_id']) ?></div>
                <div><strong>Gender :</strong> <?= htmlspecialchars($user['gender'] ?? 'N/A') ?></div>
                <div><strong>Date of Birth :</strong> <?= htmlspecialchars($user['dob'] ?? 'N/A') ?></div>
                <div><strong>Course :</strong> PUC</div>
                <div><strong>Valid Till :</strong> <?= date('d M Y', strtotime('+3 years')) ?></div>
            </div>
            <button onclick="hideIDCard()" style="margin-top:15px;background:white;color:#1e3a8a;width:100%;padding:12px;border-radius:12px;border:none;font-weight:600;">Close</button>
        </div>
    </div>


    <a href="chatbot.php" class="chatbot-fab">
        <i class="fa-solid fa-robot"></i>
        <span class="pulse-ring"></span>
    </a>

    <script>
        // --- VAULT OTP VERIFICATION LOGIC ---
        function showVaultModal() {
            document.getElementById('vaultModal').classList.add('show');
            document.getElementById('otp-step-1').style.display = 'block';
            document.getElementById('otp-step-2').style.display = 'none';
            document.getElementById('vaultOTP').value = '';
        }

        function hideVaultModal() {
            document.getElementById('vaultModal').classList.remove('show');
        }

        function sendOTP() {
            const email = document.getElementById('vaultEmail').value;
            const btn = document.querySelector('#otp-step-1 button');
            
            if(email === "") {
                alert("Error: No email address found.");
                return;
            }
            
            // Change button to show it's loading
            btn.innerText = "Sending via Brevo...";
            btn.disabled = true;
            
            fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('otp-step-1').style.display = 'none';
                    document.getElementById('otp-step-2').style.display = 'block';
                } else {
                    alert(data.message || "Failed to send OTP.");
                    btn.innerText = "Send OTP to Email";
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert("Server error. Check console.");
                btn.innerText = "Send OTP to Email";
                btn.disabled = false;
            });
        }

        function verifyOTP() {
            const otp = document.getElementById('vaultOTP').value.trim();
            const btn = document.querySelector('#otp-step-2 button');
            
            if(otp === "") {
                alert("Please enter the OTP.");
                return;
            }

            btn.innerText = "Verifying...";
            btn.disabled = true;

            fetch('verify_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ otp: otp })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    window.location.href = "student_records.php";
                } else {
                    alert(data.message);
                    btn.innerText = "Verify & Unlock";
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert("Server error.");
                btn.innerText = "Verify & Unlock";
                btn.disabled = false;
            });
        }

        // --- GENERAL UI LOGIC ---
        function toggleNotif() {
            document.getElementById('notifDropdown').classList.toggle('show');
        }

        function showEventPopup(day) {
            const tooltip = document.getElementById('tooltip-' + day);
            if (tooltip) {
                document.querySelectorAll('.event-tooltip.show').forEach(el => el.classList.remove('show'));
                tooltip.classList.add('show');
            }
        }

        function showIDCard() { document.getElementById('idModal').classList.add('show'); }
        function hideIDCard() { document.getElementById('idModal').classList.remove('show'); }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.cal-date') && !e.target.closest('.event-tooltip')) {
                document.querySelectorAll('.event-tooltip.show').forEach(el => el.classList.remove('show'));
            }
            if (!e.target.closest('.notif-bell')) {
                document.getElementById('notifDropdown').classList.remove('show');
            }
        });
    </script>
</body>
</html>