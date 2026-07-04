<?php
session_start();

// --- 1. DATABASE CONNECTION ---
$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed.");
}

// --- 2. SECURE STUDENT IDENTIFICATION ---
$session_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$session_id) {
    if (isset($_SESSION['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $_SESSION['email']]);
        $u = $stmt->fetch();
        if ($u) {
            $session_id = $u['id'];
            $_SESSION['user_id'] = $session_id;
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// --- 3. FETCH STUDENT INFO ---
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$session_id]);
$user = $user_stmt->fetch();

if (!$user) {
    die("Error: User record not found.");
}

// ✅ CRITICAL FIX: Map to the student_id string (e.g., AUR260001) instead of the numeric ID
$student_id = $user['student_id']; 

$full_name = $user['full_name'] ?? 'Student';
$course = $user['course'] ?? 'N/A';

// --- 4. FETCH ALL ATTENDANCE RECORDS ---
$sql = "SELECT a.*, t.full_name AS teacher_name 
        FROM attendance a 
        LEFT JOIN users t ON a.teacher_id = t.id
        WHERE a.student_id = :sid
        ORDER BY a.attendance_date DESC, a.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':sid' => $student_id]);
$records = $stmt->fetchAll();

// --- 5. CALCULATE STATS FOR CHART ---
$total = count($records);
$present = 0;
$absent = 0;
$late = 0;

foreach ($records as $r) {
    if ($r['status'] == 'Present') $present++;
    elseif ($r['status'] == 'Absent') $absent++;
    elseif ($r['status'] == 'Late') $late++;
}

$percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg: #fdf4e8;
            --sidebar-bg: #f5f3ff;
            --primary: #8b5cf6;
            --primary-light: #ede9fe;
            --text-dark: #1e293b;
            --muted: #64748b;
            --card: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius-lg: 20px;
            --shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body { background-color: var(--bg); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* ========= SIDEBAR ========= */
        .sidebar {
            width: 300px; background: var(--sidebar-bg); padding: 25px 20px; position: fixed; height: 100%;
            box-shadow: 4px 0 15px rgba(139,92,246,0.08); display: flex; flex-direction: column; z-index: 100;
        }

        .logo { text-align: center; margin-bottom: 35px; }
        
        .logo-circle {
            width: 140px; height: 140px; margin: 0 auto 18px; position: relative; border-radius: 50%;
            background: linear-gradient(135deg, #ede9fe 0%, #c4b5fd 45%, #8b5cf6 100%);
            display: flex; align-items: center; justify-content: center; border: 6px solid rgba(255,255,255,0.95);
            box-shadow: 0 18px 40px rgba(139,92,246,0.28), inset 0 2px 10px rgba(255,255,255,0.5);
        }
        .big-a { font-size: 72px; font-weight: 900; color: white; line-height: 1; text-shadow: 0 8px 18px rgba(0,0,0,0.18); }
        .logo-cap { position: absolute; top: 24px; right: 26px; font-size: 24px; color: #ffffff; transform: rotate(-15deg); filter: drop-shadow(0 5px 10px rgba(0,0,0,0.18)); }
        .brand { font-size: 1.45rem; font-weight: 800; letter-spacing: 0.5px; background: linear-gradient(135deg, #7c3aed, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .brand-sub { font-size: 0.88rem; color: #8b5cf6; font-weight: 600; margin-top: 4px; letter-spacing: 0.4px; }

        .nav-menu { list-style: none; flex: 1; }
        .nav-link {
            display: flex; align-items: center; gap: 14px; padding: 14px 18px; color: var(--muted);
            text-decoration: none; border-radius: 14px; margin-bottom: 6px; font-weight: 500; transition: all 0.3s ease;
        }
        .nav-link i { font-size: 1.3rem; width: 28px; text-align: center; }
        .nav-link:hover { background: rgba(139,92,246,0.1); color: var(--primary); }
        .nav-link.active { background: var(--primary); color: white; box-shadow: 0 6px 15px rgba(139,92,246,0.3); }

        .logout-btn { color: var(--danger); font-weight: 700; padding: 14px 18px; border-radius: 14px; text-decoration: none; display: flex; align-items: center; gap: 14px; margin-top: auto; }
        .logout-btn:hover { background: rgba(239,68,68,0.1); }

        /* ========= MAIN CONTENT ========= */
        .main { margin-left: 300px; flex: 1; padding: 30px 40px; overflow-y: auto; }

        /* TOP BAR */
        .top-bar {
            background: var(--card); padding: 18px 25px; border-radius: var(--radius-lg); box-shadow: var(--shadow);
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;
        }
        .welcome-title h1 { font-size: 1.5rem; font-weight: 600; }
        .welcome-title span { color: var(--primary); }
        .profile-chip { display: flex; align-items: center; gap: 15px; text-align: right; }
        .profile-chip strong { font-size: 1rem; color: var(--text-dark); display:block; }
        .profile-chip small { color: var(--muted); font-size: 0.85rem; }
        .avatar { width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary), #c4b5fd); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 22px; border-radius: var(--radius-lg); box-shadow: var(--shadow); transition: transform 0.3s; display: flex; align-items: center; gap: 15px; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: white; flex-shrink: 0; }
        .details h4 { font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; }
        .details h2 { font-size: 1.6rem; font-weight: 800; color: var(--text-dark); }

        /* TABLE & GRAPH CARDS */
        .content-card { background: var(--card); padding: 25px; border-radius: var(--radius-lg); box-shadow: var(--shadow); margin-bottom: 30px; }
        .content-card h3 { font-size: 1.2rem; margin-bottom: 20px; display:flex; align-items:center; gap:8px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 14px; border-bottom: 2px solid #f1f5f9; text-align: left; }
        td { padding: 14px; border-bottom: 1px solid #f8fafc; font-size: 0.95rem; }
        tr:hover { background: #fcfcfc; }
        
        .badge-status { padding: 6px 14px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .badge-Present { background: #d1fae5; color: var(--success); }
        .badge-Absent { background: #fee2e2; color: var(--danger); }
        .badge-Late { background: #fef3c7; color: #d97706; }
        
        .time-badge { font-family: monospace; font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; margin-left: 8px; font-weight: 600; }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo">
            <div class="logo-circle">
                <span class="big-a">A</span>
                <i class="fa-solid fa-graduation-cap logo-cap"></i>
            </div>
            <div class="brand">AUREON ERP</div>
            <div class="brand-sub">Student Portal</div>
        </div>

        <ul class="nav-menu">
            <li><a href="student_dash.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="#" class="nav-link active"><i class="fa-regular fa-calendar-check"></i> My Attendance</a></li>
            <li><a href="view_marks.php" class="nav-link"><i class="fa-solid fa-chart-simple"></i> My Marks</a></li>
            <li><a href="view_books.php" class="nav-link"><i class="fa-solid fa-book-open"></i> Library</a></li>
            <li><a href="student_events.php" class="nav-link"><i class="fa-solid fa-trophy"></i> Sports</a></li>
            <li><a href="student_profile.php" class="nav-link"><i class="fa-solid fa-id-card"></i> Profile</a></li>
        </ul>

        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </aside>

    <main class="main">

        <header class="top-bar">
            <div class="welcome-title">
                <h1><span>Complete Attendance Record,</span> <?= htmlspecialchars($full_name) ?>!</h1>
            </div>
            <div class="profile-chip">
                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>ID: <?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></small>
                </div>
                <div class="avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="icon-box" style="background: var(--primary);"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="details"><h4>Total Classes</h4><h2><?= $total ?></h2></div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background: var(--primary-light); color: var(--primary);"><i class="fa-solid fa-percent"></i></div>
                <div class="details"><h4>Overall Ratio</h4><h2><?= $percentage ?>%</h2></div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background: var(--success);"><i class="fa-solid fa-check"></i></div>
                <div class="details"><h4>Present</h4><h2><?= $present ?></h2></div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background: var(--danger);"><i class="fa-solid fa-xmark"></i></div>
                <div class="details"><h4>Absent</h4><h2><?= $absent ?></h2></div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background: var(--warning);"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div class="details"><h4>Late</h4><h2><?= $late ?></h2></div>
            </div>
        </section>

        <section class="content-card">
            <h3><i class="fa-solid fa-chart-pie" style="color:var(--primary);"></i> Attendance Summary Graph</h3>
            <div style="max-width: 600px; margin: 0 auto;">
                <canvas id="attendanceChart" height="150"></canvas>
            </div>
        </section>

        <section class="content-card">
            <h3><i class="fa-solid fa-list-ul" style="color:var(--primary);"></i> Complete History</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time Marked</th>
                            <th>Subject</th>
                            <th>Teacher Name</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($records) > 0): ?>
                            <?php foreach($records as $rec): ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);">
                                    <?= date('d M Y', strtotime($rec['attendance_date'])) ?>
                                    <span class="time-badge">
                                        <?php if (!empty($rec['created_at'])): ?>
                                            <i class="fa-regular fa-clock" style="margin-right:3px;"></i><?= date('h:i A', strtotime($rec['created_at'])) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($rec['subject']) ?></td>
                                <td><strong><?= htmlspecialchars($rec['teacher_name'] ?? 'Unknown Instructor') ?></strong></td>
                                <td>
                                    <span class="badge-status badge-<?= $rec['status'] ?>">
                                        <i class="fa-solid <?= $rec['status'] == 'Present' ? 'fa-check' : ($rec['status'] == 'Absent' ? 'fa-xmark' : 'fa-clock') ?>" style="margin-right:4px;"></i>
                                        <?= $rec['status'] ?>
                                    </span>
                                </td>
                                <td style="color:var(--muted); font-style:italic;">
                                    <?= htmlspecialchars($rec['reason'] ?: '-') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:40px; color:var(--muted);">
                                    <i class="fa-solid fa-folder-open" style="font-size:2.5rem; opacity:0.3; display:block; margin-bottom:10px;"></i>
                                    No attendance records found yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    label: 'Total Days',
                    data: [<?= $present ?>, <?= $absent ?>, <?= $late ?>],
                    backgroundColor: [
                        '#10b981', // Success (Green)
                        '#ef4444', // Danger (Red)
                        '#f59e0b'  // Warning (Orange)
                    ],
                    borderRadius: 6 // Rounds the top of the bars slightly
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false } // Hide the legend since labels are on the X axis
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 } // Ensure the Y axis counts by whole numbers, not decimals
                    }
                }
            }
        });
    </script>

</body>
</html>