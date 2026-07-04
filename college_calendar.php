<?php
session_start();

// === DATABASE CONFIGURATION ===
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'aureon';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'>Database Connection Failed</div>");
}

// Fetch all events and academic calendar entries
$events = [];

// Fetch Events
$result = $conn->query("SELECT * FROM events ORDER BY event_date");
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => 'evt_' . $row['id'],
        'title' => $row['title'],
        'start' => $row['event_date'],
        'color' => $row['event_color'],
        'className' => 'event-' . strtolower($row['event_type']),
        'extendedProps' => [
            'type' => $row['event_type'],
            'location' => $row['location'],
            'time' => $row['event_time'],
            'description' => $row['description']
        ]
    ];
}

// Fetch Academic Calendar
$academic = $conn->query("SELECT * FROM academic_calendar ORDER BY calendar_date");
while ($row = $academic->fetch_assoc()) {
    $events[] = [
        'id' => 'acad_' . $row['id'],
        'title' => $row['title'],
        'start' => $row['calendar_date'],
        'color' => $row['color'],
        'className' => 'event-' . strtolower($row['type']),
        'extendedProps' => [
            'type' => $row['type'],
            'location' => '',
            'time' => '',
            'description' => $row['description']
        ]
    ];
}

// Get statistics
$stats = [
    'holidays' => $conn->query("SELECT COUNT(*) as count FROM academic_calendar WHERE type = 'Holiday'")->fetch_assoc()['count'],
    'festivals' => $conn->query("SELECT COUNT(*) as count FROM academic_calendar WHERE type = 'Festival'")->fetch_assoc()['count'],
    'events' => $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'],
    'upcoming' => $conn->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()")->fetch_assoc()['count']
];

// Get upcoming events
$upcoming_query = $conn->query("
    SELECT title, event_date, event_time, location, event_type, event_color, description, 'event' as source 
    FROM events 
    WHERE event_date >= CURDATE() 
    UNION 
    SELECT title, calendar_date, '' as event_time, '' as location, type, color, description, 'calendar' as source 
    FROM academic_calendar 
    WHERE calendar_date >= CURDATE() 
    ORDER BY event_date 
    LIMIT 5
");
$upcoming_events = [];
while ($row = $upcoming_query->fetch_assoc()) {
    $upcoming_events[] = $row;
}

$events_json = json_encode($events);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Calendar 2026 - AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'/>
    <style>
        :root {
            --bg: #FAF9F6;
            --card: #FFFFFF;
            --text: #111827;
            --text-secondary: #4B5563;
            --text-muted: #6B7280;
            --primary: #4F46E5;
            --primary-light: #EEF2FF;
            --secondary: #F59E0B;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #FBBF24;
            --info: #3B82F6;
            --purple: #8B5CF6;
            --pink: #EC4899;
            --border: #E5E7EB;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            --shadow-hover: 0 8px 32px rgba(0, 0, 0, 0.1);
            --radius: 20px;
            --radius-sm: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--card);
            border-right: 1px solid var(--border);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }
        
        .sidebar-brand {
            padding: 32px 28px;
            border-bottom: 1px solid var(--border);
        }
        
        .sidebar-brand h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        
        .sidebar-brand small {
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }
        
        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            overflow-y: auto;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--radius-sm);
            margin-bottom: 6px;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .nav-link:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: translateX(4px);
        }
        
        .nav-link.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.25);
        }
        
        .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .back-dashboard {
            padding: 24px 16px;
            border-top: 1px solid var(--border);
        }
        
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: #F3F4F6;
            border: none;
            color: var(--text);
            text-decoration: none;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .btn-back:hover {
            background: var(--secondary);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.25);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--text);
            letter-spacing: -0.5px;
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 6px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card.holiday::before { background: var(--danger); }
        .stat-card.festival::before { background: var(--secondary); }
        .stat-card.event::before { background: var(--primary); }
        .stat-card.upcoming::before { background: var(--success); }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.4rem;
        }
        
        .stat-card.holiday .stat-icon { background: #FEF2F2; color: var(--danger); }
        .stat-card.festival .stat-icon { background: #FFFBEB; color: var(--secondary); }
        .stat-card.event .stat-icon { background: var(--primary-light); color: var(--primary); }
        .stat-card.upcoming .stat-icon { background: #ECFDF5; color: var(--success); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 6px;
        }
        
        /* Main Grid */
        .calendar-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
        }
        
        @media (max-width: 1200px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 28px;
            transition: var(--transition);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }
        
        /* Legend */
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #F9FAFB;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .legend-item:hover {
            background: #F3F4F6;
            border-color: var(--border);
        }
        
        .legend-item.active {
            background: var(--card);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.1);
        }
        
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        /* Calendar */
        #calendar {
            background: transparent;
        }
        
        .fc-theme-standard .fc-scrollgrid {
            border-color: var(--border);
        }
        
        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: var(--border);
            color: var(--text-secondary);
        }
        
        .fc-theme-standard th {
            background: #F9FAFB;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 0;
        }
        
        .fc-day-today {
            background: var(--primary-light) !important;
        }
        
        .fc-day-today .fc-daygrid-day-number {
            background: var(--primary);
            color: #fff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 2px;
        }
        
        .fc-button-primary {
            background: var(--primary) !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important;
            border-radius: 10px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            transition: var(--transition) !important;
        }
        
        .fc-button-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3) !important;
        }
        
        .fc-button-primary:active {
            transform: translateY(0);
        }
        
        .fc-event {
            border: none !important;
            border-radius: 8px !important;
            padding: 4px 8px !important;
            cursor: pointer !important;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 2px 4px !important;
            transition: var(--transition);
        }
        
        .fc-event:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }
        
        /* Tooltip */
        .fc-event-tooltip {
            position: fixed;
            background: #1F2937;
            color: #fff;
            padding: 16px 20px;
            border-radius: 14px;
            font-size: 0.85rem;
            z-index: 9999;
            max-width: 320px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            pointer-events: none;
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .fc-event-tooltip.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        
        .fc-event-tooltip .tooltip-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1rem;
            line-height: 1.4;
        }
        
        .fc-event-tooltip .tooltip-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .fc-event-tooltip .tooltip-detail {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 6px;
            opacity: 0.85;
        }
        
        .fc-event-tooltip .tooltip-detail i {
            width: 16px;
            text-align: center;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .fc-event-tooltip .tooltip-desc {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            opacity: 0.75;
            line-height: 1.5;
        }
        
        /* Upcoming Events */
        .upcoming-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .upcoming-item {
            display: flex;
            gap: 14px;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 14px;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .upcoming-item:hover {
            background: #F3F4F6;
            transform: translateX(4px);
        }
        
        .upcoming-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 56px;
            padding: 8px 12px;
            background: var(--card);
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .upcoming-date .day {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        
        .upcoming-date .month {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-top: 4px;
        }
        
        .upcoming-content {
            flex: 1;
        }
        
        .upcoming-title {
            font-weight: 600;
            color: var(--text);
            margin: 0 0 4px;
            font-size: 0.95rem;
        }
        
        .upcoming-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .upcoming-type {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #fff;
        }
        
        /* Modal */
        .modal-content {
            background: var(--card);
            border: none;
            border-radius: var(--radius);
            color: var(--text);
            box-shadow: var(--shadow-hover);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 24px 28px;
        }
        
        .modal-body {
            padding: 28px;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 20px 28px;
        }
        
        .badge-type {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .mobile-toggle {
                display: flex;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--primary);
                border: none;
                color: #fff;
                width: 48px;
                height: 48px;
                border-radius: 12px;
                cursor: pointer;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 16px rgba(79, 70, 229, 0.3);
                transition: var(--transition);
            }
            
            .mobile-toggle:hover {
                transform: scale(1.05);
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 992px) {
            .mobile-toggle {
                display: none;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #D1D5DB;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #9CA3AF;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>

<!-- Mobile Toggle -->
<button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>AUREON ERP</h2>
        <small>Super Admin Panel</small>
    </div>
    
    <nav class="nav-menu">
        <a href="create_event.php" class="nav-link">
            <i class="bi bi-calendar-plus"></i>
            <span>Create Event</span>
        </a>
        <a href="manage_events.php" class="nav-link">
            <i class="bi bi-calendar-event"></i>
            <span>Manage Events</span>
        </a>
        <a href="calendar_admin.php" class="nav-link">
            <i class="bi bi-calendar-check"></i>
            <span>Academic Calendar</span>
        </a>
        <a href="college_calendar.php" class="nav-link active">
            <i class="bi bi-calendar3"></i>
            <span>View Calendar</span>
        </a>
    </nav>
    
    <div class="back-dashboard">
        <a href="super_admin.php" class="btn-back">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">College Calendar 2026</h1>
        <p class="page-subtitle">View all academic events, holidays, and important dates</p>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card holiday animate-in">
            <div class="stat-icon">
                <i class="bi bi-flag-fill"></i>
            </div>
            <div class="stat-value"><?php echo $stats['holidays']; ?></div>
            <div class="stat-label">National Holidays</div>
        </div>
        
        <div class="stat-card festival animate-in">
            <div class="stat-icon">
                <i class="bi bi-stars"></i>
            </div>
            <div class="stat-value"><?php echo $stats['festivals']; ?></div>
            <div class="stat-label">Festivals</div>
        </div>
        
        <div class="stat-card event animate-in">
            <div class="stat-icon">
                <i class="bi bi-calendar-event"></i>
            </div>
            <div class="stat-value"><?php echo $stats['events']; ?></div>
            <div class="stat-label">College Events</div>
        </div>
        
        <div class="stat-card upcoming animate-in">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?php echo $stats['upcoming']; ?></div>
            <div class="stat-label">Upcoming</div>
        </div>
    </div>
    
    <!-- Calendar Grid -->
    <div class="calendar-grid">
        <!-- Calendar Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Academic Calendar</h3>
            </div>
            
            <!-- Legend -->
            <div class="legend" id="legend">
                <div class="legend-item active" data-filter="all">
                    <div class="legend-dot" style="background: #9CA3AF;"></div>
                    <span>All</span>
                </div>
                <div class="legend-item active" data-filter="holiday">
                    <div class="legend-dot" style="background: #EF4444;"></div>
                    <span>Holidays</span>
                </div>
                <div class="legend-item active" data-filter="festival">
                    <div class="legend-dot" style="background: #F59E0B;"></div>
                    <span>Festivals</span>
                </div>
                <div class="legend-item active" data-filter="workshop">
                    <div class="legend-dot" style="background: #4F46E5;"></div>
                    <span>Events</span>
                </div>
                <div class="legend-item active" data-filter="exam">
                    <div class="legend-dot" style="background: #F59E0B;"></div>
                    <span>Exams</span>
                </div>
            </div>
            
            <!-- Calendar -->
            <div id="calendar"></div>
        </div>
        
        <!-- Upcoming Events Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upcoming Events</h3>
                <a href="manage_events.php" style="color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 500;">
                    View All
                </a>
            </div>
            
            <div class="upcoming-list">
                <?php if (empty($upcoming_events)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                        <i class="bi bi-calendar-x" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p style="margin: 0; font-weight: 500;">No upcoming events</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <div class="upcoming-item">
                            <div class="upcoming-date">
                                <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                            </div>
                            <div class="upcoming-content">
                                <h5 class="upcoming-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <div class="upcoming-meta">
                                    <span class="upcoming-type" style="background: <?php echo $event['event_color']; ?>;">
                                        <?php echo $event['event_type']; ?>
                                    </span>
                                    <?php if ($event['event_time']): ?>
                                        <span><i class="bi bi-clock me-1"></i><?php echo $event['event_time']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tooltip Element -->
<div class="fc-event-tooltip" id="eventTooltip">
    <div class="tooltip-title" id="tooltipTitle"></div>
    <div class="tooltip-type" id="tooltipType"></div>
    <div class="tooltip-detail" id="tooltipDate">
        <i class="bi bi-calendar"></i>
        <span></span>
    </div>
    <div class="tooltip-detail" id="tooltipTime">
        <i class="bi bi-clock"></i>
        <span></span>
    </div>
    <div class="tooltip-detail" id="tooltipLocation">
        <i class="bi bi-geo-alt"></i>
        <span></span>
    </div>
    <div class="tooltip-desc" id="tooltipDesc"></div>
</div>

<!-- Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <span id="modalType" class="badge-type mb-3"></span>
                <p id="modalDate" class="text-muted mb-3" style="font-size: 0.95rem;"></p>
                <p id="modalLocation" class="mb-3"></p>
                <div>
                    <h6 class="mb-2" style="font-weight: 600; color: var(--text);">Description</h6>
                    <p id="modalDesc" class="mb-0" style="color: var(--text-secondary); line-height: 1.6;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border: 1px solid var(--border);">Close</button>
            </div>
        </div>
    </div>
</div>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tooltip = document.getElementById('eventTooltip');
    var calendarEl = document.getElementById('calendar');
    var activeFilters = ['holiday', 'festival', 'workshop', 'exam'];
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        initialDate: '2026-01-01',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: <?php echo $events_json; ?>,
        eventDidMount: function(info) {
            // Check if event should be visible based on filters
            var eventType = info.event.extendedProps.type.toLowerCase();
            var shouldShow = activeFilters.includes('all') || 
                           activeFilters.includes(eventType) ||
                           (eventType === 'seminar' && activeFilters.includes('workshop')) ||
                           (eventType === 'sports' && activeFilters.includes('workshop')) ||
                           (eventType === 'cultural' && activeFilters.includes('workshop'));
            
            if (!shouldShow) {
                info.el.style.display = 'none';
            }
            
            // Add hover listeners for tooltip
            info.el.addEventListener('mouseenter', function(e) {
                if (info.el.style.display === 'none') return;
                
                var props = info.event.extendedProps;
                
                // Set tooltip content
                document.getElementById('tooltipTitle').textContent = info.event.title;
                
                // Type badge
                var typeBadge = document.getElementById('tooltipType');
                typeBadge.textContent = props.type;
                typeBadge.style.background = info.event.backgroundColor;
                
                // Date
                document.querySelector('#tooltipDate span').textContent = 
                    info.event.start.toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                
                // Time
                var timeEl = document.getElementById('tooltipTime');
                if (props.time) {
                    timeEl.style.display = 'flex';
                    timeEl.querySelector('span').textContent = props.time;
                } else {
                    timeEl.style.display = 'none';
                }
                
                // Location
                var locEl = document.getElementById('tooltipLocation');
                if (props.location) {
                    locEl.style.display = 'flex';
                    locEl.querySelector('span').textContent = props.location;
                } else {
                    locEl.style.display = 'none';
                }
                
                // Description
                document.getElementById('tooltipDesc').textContent = 
                    props.description || 'No description available.';
                
                // Position tooltip
                var rect = info.el.getBoundingClientRect();
                var tooltipRect = tooltip.getBoundingClientRect();
                
                var left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
                var top = rect.bottom + 12;
                
                // Keep within viewport
                left = Math.max(16, Math.min(left, window.innerWidth - tooltipRect.width - 16));
                
                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
                tooltip.classList.add('show');
            });
            
            info.el.addEventListener('mouseleave', function() {
                tooltip.classList.remove('show');
            });
        },
        eventClick: function(info) {
            tooltip.classList.remove('show');
            
            var props = info.event.extendedProps;
            
            document.getElementById('modalTitle').textContent = info.event.title;
            
            var typeBadge = document.getElementById('modalType');
            typeBadge.textContent = props.type;
            typeBadge.style.background = info.event.backgroundColor;
            
            document.getElementById('modalDate').innerHTML = 
                '<i class="bi bi-calendar me-2"></i>' +
                info.event.start.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
            
            var locText = props.location ? 
                '<i class="bi bi-geo-alt me-2"></i>' + props.location : '';
            document.getElementById('modalLocation').innerHTML = locText;
            
            document.getElementById('modalDesc').textContent = 
                props.description || 'No description available.';
            
            new bootstrap.Modal(document.getElementById('eventModal')).show();
        },
        height: 'auto',
        aspectRatio: 1.5,
        dayMaxEvents: 3
    });
    
    calendar.render();
    
    // Legend filter functionality
    document.querySelectorAll('.legend-item').forEach(function(item) {
        item.addEventListener('click', function() {
            var filter = this.dataset.filter;
            
            if (filter === 'all') {
                // Toggle all
                var isActive = this.classList.contains('active');
                document.querySelectorAll('.legend-item').forEach(function(i) {
                    i.classList.toggle('active', !isActive);
                });
                activeFilters = !isActive ? ['all', 'holiday', 'festival', 'workshop', 'exam'] : [];
            } else {
                this.classList.toggle('active');
                
                if (this.classList.contains('active')) {
                    if (!activeFilters.includes(filter)) {
                        activeFilters.push(filter);
                    }
                } else {
                    activeFilters = activeFilters.filter(function(f) { return f !== filter; });
                }
                
                // Update 'all' button
                var allItem = document.querySelector('[data-filter="all"]');
                allItem.classList.toggle('active', activeFilters.length >= 4);
            }
            
            // Re-render events
            calendar.getEvents().forEach(function(event) {
                var eventType = event.extendedProps.type.toLowerCase();
                var shouldShow = activeFilters.includes('all') || 
                               activeFilters.includes(eventType) ||
                               (eventType === 'seminar' && activeFilters.includes('workshop')) ||
                               (eventType === 'sports' && activeFilters.includes('workshop')) ||
                               (eventType === 'cultural' && activeFilters.includes('workshop'));
                
                event.setProp('display', shouldShow ? 'auto' : 'none');
            });
        });
    });
});
</script>

</body>
</html>