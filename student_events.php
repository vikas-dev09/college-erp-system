<?php
session_start();

// Database Connection
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
    die("Connection failed: " . $e->getMessage());
}

// Session Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role = 'student'");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Student not found.");
}

$student_id = $user['student_id'] ?? null;
$full_name = $user['full_name'];

// Fetch Upcoming Events (event_date >= today)
$today = date('Y-m-d');
$upcoming_events = $pdo->prepare("SELECT * FROM events WHERE event_date >= ? ORDER BY event_date ASC");
$upcoming_events->execute([$today]);
$events = $upcoming_events->fetchAll();

// Get all events for calendar view (last 90 days + future)
$start_date = date('Y-m-d', strtotime('-90 days'));
$calendar_events = $pdo->query("SELECT * FROM events WHERE event_date >= '$start_date' OR event_date IS NULL ORDER BY event_date ASC")->fetchAll();

// Group calendar events by date
$events_by_date = [];
foreach ($calendar_events as $ev) {
    $date_key = $ev['event_date'];
    if ($date_key) {
        if (!isset($events_by_date[$date_key])) {
            $events_by_date[$date_key] = [];
        }
        $events_by_date[$date_key][] = $ev;
    }
}

// Handle AJAX Apply Event Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'apply_event') {
            $event_id = (int)$_POST['event_id'];
            
            // Verify event exists
            $check_event = $pdo->prepare("SELECT id, event_date, title FROM events WHERE id = ?");
            $check_event->execute([$event_id]);
            $event_check = $check_event->fetch();
            
            if (!$event_check) {
                echo json_encode(['success' => false, 'message' => 'Event not found']);
                exit;
            }
            
            // Check if already applied
            $check_apply = $pdo->prepare("SELECT id FROM event_applications WHERE event_id = ? AND student_id = ?");
            $check_apply->execute([$event_id, $student_id]);
            
            if ($check_apply->fetch()) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'You have already applied for this event!',
                    'already_applied' => true
                ]);
                exit;
            }
            
            // Insert application
            $insert = $pdo->prepare("INSERT INTO event_applications (event_id, student_id, status, applied_at) VALUES (?, ?, 'pending', NOW())");
            $insert->execute([$event_id, $student_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Successfully applied for "' . htmlspecialchars($event_check['title']) . '"!'
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch Student's Applied Events
$applied_events_query = "
    SELECT ea.*, e.title, e.event_date, e.event_time, e.location, e.event_type, e.event_color, e.created_by 
    FROM event_applications ea
    JOIN events e ON ea.event_id = e.id
    WHERE ea.student_id = ?
    ORDER BY ea.applied_at DESC
";
$applied_stmt = $pdo->prepare($applied_events_query);
$applied_stmt->execute([$student_id]);
$applied_events = $applied_stmt->fetchAll();

$applied_event_ids = array_column($applied_events, 'event_id');

// Current month/year for calendar
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events & Calendar - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #fdf4e8;
            --sidebar-bg: #f5f3ff;
            --accent-color: #8b5cf6;
            --light-accent: #ede9fe;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --white: #ffffff;
            --danger: #e11d48;
            --success: #10b981;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-glow: 0 10px 25px -5px rgba(139, 92, 246, 0.4);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* Sidebar */
        .sidebar {
            width: 290px; background-color: var(--sidebar-bg); display: flex; flex-direction: column;
            padding: 24px; position: fixed; height: 100%; box-shadow: 2px 0 15px rgba(0,0,0,0.02); z-index: 100;
        }
        .logo-container { text-align: center; margin-bottom: 40px; }
        .logo-circle {
            width: 130px; height: 130px; border-radius: 50%;
            background: linear-gradient(135deg, var(--light-accent), var(--accent-color));
            margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;
            box-shadow: var(--shadow-md); overflow: hidden;
        }
        .logo-circle .big-a { font-size: 60px; color: var(--white); font-weight: bold; }
        .brand-name { font-weight: 700; font-size: 1.25rem; color: var(--text-dark); letter-spacing: 0.5px; }
        .brand-sub { font-size: 0.85rem; color: var(--accent-color); font-weight: 500; margin-top: 4px; }
        .nav-menu { list-style: none; flex: 1; }
        .nav-item { margin-bottom: 6px; }
        .nav-link {
            display: flex; align-items: center; padding: 14px 16px; text-decoration: none;
            color: var(--text-muted); border-radius: 12px; font-weight: 500; transition: all 0.25s ease;
        }
        .nav-link i { width: 24px; margin-right: 12px; font-size: 1.1rem; }
        .nav-link:hover { background: rgba(139, 92, 246, 0.1); color: var(--accent-color); }
        .nav-link.active { background-color: var(--accent-color); color: var(--white); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); }
        .logout-btn { color: var(--danger); font-weight: 700; margin-top: auto; }
        .logout-btn:hover { background: rgba(239, 68, 68, 0.1); }

        /* Main Content */
        .main-content { flex: 1; margin-left: 290px; padding: 30px 40px; overflow-y: auto; height: 100vh; }

        /* Top Bar */
        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--white); padding: 18px 25px; border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 30px;
        }
        .welcome-text { display: flex; align-items: center; gap: 15px; font-size: 1.45rem; font-weight: 600; }
        .welcome-text i { font-size: 2rem; color: var(--accent-color); background: var(--light-accent); padding: 12px; border-radius: 50%; }
        .profile-section { display: flex; align-items: center; gap: 15px; }
        .profile-photo { width: 58px; height: 58px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-color); }

        /* Alerts */
        .alert {
            padding: 14px 20px; border-radius: 14px; margin-bottom: 25px;
            display: flex; align-items: center; gap: 12px; font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #d1fae5; color: #059669; }
        .alert-error { background: #fee2e2; color: #ef4444; }

        /* Section Title */
        .section-title {
            font-size: 1.3rem; font-weight: 700; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }

        /* Two Column Layout */
        .events-layout {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px;
        }

        /* Events Grid */
        .events-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px; margin-bottom: 35px;
        }
        .event-card {
            background: var(--white); border-radius: 20px; padding: 25px;
            box-shadow: var(--shadow-sm); transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid transparent; position: relative; overflow: hidden;
        }
        .event-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: var(--shadow-glow); border-color: var(--light-accent); }
        
        .event-header {
            display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;
            flex-wrap: wrap; gap: 10px;
        }
        .event-type-badge {
            padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        }
        .badge-workshop { background: #dbeafe; color: #1e40af; }
        .badge-seminar { background: #ede9fe; color: #6d28d9; }
        .badge-sports { background: #dcfce7; color: #166534; }
        .badge-cultural { background: #fef3c7; color: #92400e; }
        .badge-other { background: #fce7f3; color: #9d174d; }
        .badge-today { background: var(--accent-color); color: white; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }

        .event-title { font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin-bottom: 10px; line-height: 1.4; }
        .event-info { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
        .event-info i { color: var(--accent-color); width: 18px; }
        .event-created-by { font-size: 0.85rem; color: var(--text-muted); margin-top: 10px; display: flex; align-items: center; gap: 8px; }
        .event-created-by i { color: var(--muted); }
        .event-description { font-size: 0.9rem; color: var(--text-muted); margin-top: 12px; line-height: 1.5; font-style: italic; border-top: 1px solid #f1f5f9; padding-top: 12px; }
        
        .btn-apply {
            margin-top: 15px; width: 100%; padding: 12px; border: none;
            background: linear-gradient(135deg, var(--accent-color), #7c3aed);
            color: white; border-radius: 12px; font-weight: 600; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-apply:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3); }
        .btn-apply:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-apply.loading { opacity: 0.7; cursor: wait; }

        /* Calendar Section */
        .calendar-card {
            background: var(--white); border-radius: 20px; padding: 25px;
            box-shadow: var(--shadow-sm); position: sticky; top: 30px;
        }
        .calendar-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
        }
        .calendar-header h2 { font-size: 1.2rem; font-weight: 700; }
        .calendar-nav { display: flex; gap: 8px; }
        .calendar-nav a {
            background: var(--light-accent); color: var(--accent-color);
            padding: 8px 14px; border-radius: 10px; text-decoration: none; font-weight: 600;
            transition: all 0.3s;
        }
        .calendar-nav a:hover { background: var(--accent-color); color: white; }
        
        .calendar-grid {
            display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center;
        }
        .cal-day-name {
            font-size: 0.85rem; font-weight: 600; color: var(--text-muted); padding: 8px 0;
        }
        .cal-date {
            padding: 14px 6px; border-radius: 12px; font-size: 0.95rem; font-weight: 500;
            position: relative; cursor: pointer; transition: all 0.2s;
        }
        .cal-date:hover { background: #f8fafc; }
        .cal-date.today { background: var(--accent-color); color: white; font-weight: 700; }
        .cal-date.has-event { font-weight: 700; }
        .event-dot {
            position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%);
            width: 5px; height: 5px; border-radius: 50%;
        }
        .cal-date.empty { cursor: default; }
        .cal-date.other-month { color: #cbd5e1; }

        /* Toast Notifications */
        .toast-holder {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
        }
        .toast {
            background: var(--white); border-left: 4px solid var(--accent-color);
            padding: 16px 20px; border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex; align-items: center; gap: 12px;
            animation: slideIn 0.3s ease;
        }
        .toast.success { border-left-color: var(--success); }
        .toast.error { border-left-color: var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        @media (max-width: 1024px) {
            .events-layout { grid-template-columns: 1fr; }
            .calendar-card { position: static; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .brand-name, .sidebar .brand-sub, .nav-link span { display: none; }
            .main-content { margin-left: 70px; padding: 20px; }
            .events-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-container">
            <div class="logo-circle"><span class="big-a">A</span></div>
            <div class="brand-name">AUREON ERP</div>
            <div class="brand-sub">Student Portal</div>
        </div>

        <ul class="nav-menu">
            <li class="nav-item"><a href="student_dash.php" class="nav-link"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li class="nav-item"><a href="student_profile.php" class="nav-link"><i class="fa-regular fa-calendar"></i> <span>My Profile</span></a></li>
            <li class="nav-item"><a href="view_marks.php" class="nav-link"><i class="fa-solid fa-chart-simple"></i> <span>My Marks</span></a></li>
            <li class="nav-item"><a href="view_books.php" class="nav-link"><i class="fa-solid fa-book-open"></i> <span>Library</span></a></li>
            <li class="nav-item"><a href="student_events.php" class="nav-link active"><i class="fa-solid fa-trophy"></i> <span>Events</span></a></li>
        </ul>

        <a href="logout.php" class="nav-link logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Top Bar -->
        <header class="top-bar">
            <div class="welcome-text">
                <i class="fa-solid fa-calendar-star"></i>
                <span>Events & Activities</span>
            </div>
            <div class="profile-section">
                <div style="text-align: right;">
                    <strong><?= htmlspecialchars($student_id) ?></strong><br>
                    <small style="color: var(--text-muted);">Student</small>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($full_name) ?>&background=8b5cf6&color=fff" alt="Profile" class="profile-photo">
            </div>
        </header>

        <!-- Success/Error Alerts -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_msg']) ?>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_msg']) ?>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- Two Column Layout -->
        <div class="events-layout">
            
            <!-- Left Column: Upcoming Events -->
            <div class="left-column">
                
                <?php if (count($events) > 0): ?>
                <h2 class="section-title"><i class="fa-solid fa-calendar-check"></i> Upcoming Events</h2>
                <div class="events-grid">
                    
                    <?php foreach ($events as $event): 
                        $is_today = date('Y-m-d') == $event['event_date'];
                        $already_applied = in_array($event['id'], $applied_event_ids);
                        
                        // Badge type
                        $badge_class = 'badge-' . strtolower($event['event_type']);
                        if (!in_array($badge_class, ['badge-workshop', 'badge-seminar', 'badge-sports', 'badge-cultural'])) {
                            $badge_class = 'badge-other';
                        }
                        
                        // Format date
                        $formatted_date = date('d F Y', strtotime($event['event_date']));
                    ?>
                    <div class="event-card" data-event-id="<?= $event['id'] ?>">
                        <div class="event-header">
                            <span class="event-type-badge <?= $badge_class ?>"><?= htmlspecialchars($event['event_type']) ?></span>
                            <?php if ($is_today): ?>
                                <span class="event-type-badge badge-today"><i class="fa-solid fa-clock"></i> Today!</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                        
                        <div class="event-info">
                            <i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($formatted_date) ?>
                        </div>
                        
                        <div class="event-info">
                            <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($event['event_time'] ?: 'TBD') ?>
                        </div>
                        
                        <div class="event-info">
                            <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($event['location'] ?: 'TBA') ?>
                        </div>
                        
                        <div class="event-created-by">
                            <i class="fa-solid fa-user-shield"></i> Organized by: <strong><?= htmlspecialchars($event['created_by'] ?: 'Admin') ?></strong>
                        </div>
                        
                        <?php if ($event['description']): ?>
                            <div class="event-description"><?= htmlspecialchars($event['description']) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="apply-form" data-event-id="<?= $event['id'] ?>">
                            <input type="hidden" name="action" value="apply_event">
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            
                            <?php if ($already_applied): ?>
                                <button type="button" class="btn-apply" disabled>
                                    <i class="fa-solid fa-check"></i> Applied ✔
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn-apply submit-btn">
                                    <i class="fa-solid fa-paper-plane"></i> Apply Now
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    
                </div>
                <?php else: ?>
                    <div class="alert" style="background: #f8fafc; color: var(--text-muted);">
                        <i class="fa-solid fa-info-circle"></i> No upcoming events scheduled at the moment. Check back later!
                    </div>
                <?php endif; ?>
                
                <!-- Applied Events List -->
                <?php if (count($applied_events) > 0): ?>
                    <h2 class="section-title" style="margin-top: 40px;"><i class="fa-solid fa-file-signature"></i> My Applications</h2>
                    <div class="card" style="background: white; border-radius: 20px; padding: 25px; box-shadow: var(--shadow-sm);">
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applied_events as $app): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($app['title']) ?></strong></td>
                                    <td><?= date('d M Y', strtotime($app['event_date'])) ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?= $app['status'] == 'approved' ? '#d1fae5; color: #059669;' : ($app['status'] == 'rejected' ? '#fee2e2; color: #ef4444;' : '#fef3c7; color: #92400e;') ?>">
                                            <?= ucfirst($app['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y H:i', strtotime($app['applied_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Right Column: Calendar View -->
            <div class="right-column">
                <div class="calendar-card">
                    <div class="calendar-header">
                        <h2><?= date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)) ?></h2>
                        <div class="calendar-nav">
                            <?php 
                            $prev_month = $current_month - 1; $prev_year = $current_year;
                            if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                            $next_month = $current_month + 1; $next_year = $current_year;
                            if ($next_month > 12) { $next_month = 1; $next_year++; }
                            ?>
                            <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>">&lt;</a>
                            <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>">&gt;</a>
                        </div>
                    </div>
                    
                    <div class="calendar-grid">
                        <div class="cal-day-name">Sun</div>
                        <div class="cal-day-name">Mon</div>
                        <div class="cal-day-name">Tue</div>
                        <div class="cal-day-name">Wed</div>
                        <div class="cal-day-name">Thu</div>
                        <div class="cal-day-name">Fri</div>
                        <div class="cal-day-name">Sat</div>
                        
                        <?php
                        $first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
                        $days_in_month = date('t', $first_day);
                        $start_day = date('w', $first_day); // 0 = Sunday
                        
                        // Empty cells before first day
                        for ($i = 0; $i < $start_day; $i++): ?>
                            <div class="cal-date empty"></div>
                        <?php endfor; ?>
                        
                        <?php for ($day = 1; $day <= $days_in_month; $day++): 
                            $date_key = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                            $is_today = date('Y-m-d') == $date_key;
                            $has_events = isset($events_by_date[$date_key]) && count($events_by_date[$date_key]) > 0;
                        ?>
                            <div class="cal-date <?= $is_today ? 'today' : '' ?> <?= $has_events ? 'has-event' : '' ?>" onclick="showDateEvents('<?= $date_key ?>')">
                                <?= $day ?>
                                <?php if ($has_events): 
                                    $first_event = $events_by_date[$date_key][0];
                                ?>
                                    <div class="event-dot" style="background: <?= htmlspecialchars($first_event['event_color'] ?: '#8b5cf6') ?>"></div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Date Events Tooltip -->
                    <div id="date-events-tooltip" style="margin-top: 20px; padding: 15px; background: var(--light-accent); border-radius: 12px; display: none;">
                        <h4 style="margin-bottom: 10px; color: var(--accent-color);">Events on <?= isset($selected_date) ? date('d M Y', strtotime($selected_date)) : '' ?></h4>
                        <div id="date-events-list"></div>
                    </div>
                    
                </div>
            </div>
            
        </div>
        
    </main>

    <!-- Toast Container -->
    <div class="toast-holder" id="toastHolder"></div>

    <script>
        // Toast Notification Function
        function showToast(message, type = 'success') {
            const holder = document.getElementById('toastHolder');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}" style="font-size: 1.2rem;"></i>
                <span style="font-weight: 600; color: #1e293b;">${message}</span>
            `;
            holder.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Form Submission with AJAX
        document.querySelectorAll('.apply-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const eventId = this.dataset.eventId;
                const btn = this.querySelector('.submit-btn');
                const originalText = btn.innerHTML;
                
                if (btn.disabled) return;
                
                btn.classList.add('loading');
                btn.disabled = true;
                
                try {
                    const formData = new FormData(this);
                    formData.append('action', 'apply_event');
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message, 'success');
                        // Update button state
                        btn.innerHTML = '<i class="fa-solid fa-check"></i> Applied ✔';
                        btn.disabled = true;
                        btn.style.background = '#10b981';
                    } else {
                        showToast(result.message, 'error');
                        btn.classList.remove('loading');
                        btn.disabled = false;
                    }
                } catch (error) {
                    showToast('Network error. Please try again.', 'error');
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            });
        });

        // Show events for clicked date
        function showDateEvents(dateKey) {
            <?php 
            $events_json = [];
            foreach ($events_by_date as $date => $dates_events) {
                $events_json[$date] = array_map(function($e) {
                    return [
                        'title' => $e['title'],
                        'color' => $e['event_color'] ?: '#8b5cf6',
                        'time' => $e['event_time']
                    ];
                }, $dates_events);
            }
            ?>
            const eventsByDate = <?= json_encode($events_json) ?>;
            const tooltip = document.getElementById('date-events-tooltip');
            const list = document.getElementById('date-events-list');
            
            if (eventsByDate[dateKey] && eventsByDate[dateKey].length > 0) {
                tooltip.style.display = 'block';
                tooltip.querySelector('h4').textContent = 'Events on ' + dateKey.replace(/-/g, '/');
                
                list.innerHTML = eventsByDate[dateKey].map(evt => `
                    <div style="padding: 8px; margin-bottom: 6px; background: white; border-radius: 8px; border-left: 3px solid ${evt.color};">
                        <strong>${evt.title}</strong>
                        ${evt.time ? `<br><small style="color: #64748b;">${evt.time}</small>` : ''}
                    </div>
                `).join('');
            } else {
                tooltip.style.display = 'none';
            }
        }
    </script>
</body>
</html>