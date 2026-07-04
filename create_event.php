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

// === AUTHENTICATION CHECK - Fixed ===
// === AUTHENTICATION CHECK ===
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'>
         <h2>🔒 Access Denied</h2>
         <p>Admin privileges required.</p>
         <a href='login.php' style='color:#4F46E5;text-decoration:none;font-weight:600'>Go to Login</a>
         </div>");
}

// === COLOR MAPPER ===
function getEventColor($type) {
    $map = [
        'Holiday'  => '#EF4444',
        'Exam'     => '#F59E0B',
        'Workshop' => '#4F46E5',
        'Seminar'  => '#3B82F6',
        'Festival' => '#10B981',
        'Sports'   => '#8B5CF6',
        'Cultural' => '#EC4899',
        'Deadline' => '#D97706'
    ];
    return $map[$type] ?? '#4F46E5';
}

// === FORM TOKEN ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['event_form_token'] = bin2hex(random_bytes(32));
}

// === DEFAULT MESSAGE ===
$message = '';

// === FORM HANDLING ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['form_token']) ||
        !isset($_SESSION['event_form_token']) ||
        $_POST['form_token'] !== $_SESSION['event_form_token']
    ) {
        $message = "<div class='alert alert-danger'>✗ Invalid form submission. Please refresh and try again.</div>";
    } else {
        unset($_SESSION['event_form_token']);

        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $date = trim($_POST['event_date'] ?? '');
        $time = trim($_POST['event_time'] ?? '');
        $loc  = trim($_POST['location'] ?? '');
        $type = trim($_POST['event_type'] ?? '');
        $color = getEventColor($type);
        $by = $_SESSION['user_id'] ?? 'Super Admin';

        // Validation
        if (empty($title) || empty($date) || empty($type)) {
            $message = "<div class='alert alert-danger'>✗ Please fill all required fields.</div>";
        } elseif (!in_array($type, ['Workshop', 'Seminar', 'Sports', 'Cultural', 'Holiday', 'Exam', 'Festival', 'Deadline'])) {
            $message = "<div class='alert alert-danger'>✗ Invalid event type selected.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, event_time, location, event_type, event_color, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param("ssssssss", $title, $desc, $date, $time, $loc, $type, $color, $by);

                if ($stmt->execute()) {
                    $_SESSION['event_success'] = "✓ Event created successfully! It will now appear in the calendar.";
                    header("Location: create_event.php");
                    exit();
                } else {
                    $message = "<div class='alert alert-danger'>✗ Error: " . htmlspecialchars($stmt->error) . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='alert alert-danger'>✗ Database error: " . htmlspecialchars($conn->error) . "</div>";
            }
        }

        if (!isset($_SESSION['event_form_token'])) {
            $_SESSION['event_form_token'] = bin2hex(random_bytes(32));
        }
    }
}

// === SUCCESS MESSAGE ===
if (isset($_SESSION['event_success'])) {
    $message = "<div class='alert alert-success'>" . $_SESSION['event_success'] . "</div>";
    unset($_SESSION['event_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FAF9F6;
            --card: #FFFFFF;
            --text: #1F2937;
            --text-muted: #6B7280;
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --secondary: #D97706;
            --border: #E5E7EB;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            --radius: 16px;
        }
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        body {
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
        }

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
        }

        .sidebar-brand {
            padding: 30px 25px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-brand h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .sidebar-brand small {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-menu {
            flex: 1;
            padding: 20px 15px;
            overflow-y: auto;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            text-decoration: none;
            color: var(--text-muted);
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #F3F4F6;
            color: var(--text);
        }

        .nav-link.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .nav-link i { font-size: 1.1rem; }

        .back-dashboard {
            padding: 20px 15px;
            border-top: 1px solid var(--border);
        }

        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: #F3F4F6;
            border: none;
            color: var(--text);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .btn-back:hover {
            background: var(--secondary);
            color: #fff;
        }

        .main-content {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: var(--text);
        }

        .breadcrumb {
            margin: 8px 0 0;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .breadcrumb a {
            color: var(--text-muted);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 25px;
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .form-label {
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control {
            background: #F9FAFB;
            border: 2px solid var(--border);
            color: var(--text);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            color: #fff;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.25);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-outline:hover {
            background: #F3F4F6;
            border-color: #D1D5DB;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            border: none;
        }

        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .alert-danger {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .badge-type {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h2>AUREON ERP</h2>
        <small>Super Admin Panel</small>
    </div>

    <nav class="nav-menu">
        <a href="create_event.php" class="nav-link active">
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
        <a href="college_calendar.php" class="nav-link">
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

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">Create New Event</h1>
        <nav class="breadcrumb">
            <a href="super_admin.php">Dashboard</a>
            <span style="margin: 0 8px;">/</span>
            <span>Events</span>
            <span style="margin: 0 8px;">/</span>
            <span style="color: var(--text);">Create</span>
        </nav>
    </div>

    <?php echo $message; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="d-flex align-items-center mb-4">
                    <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 16px; color: #fff;">
                        <i class="bi bi-calendar-plus fs-5"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-weight: 600;">Event Details</h4>
                        <small style="color: var(--text-muted);">Fill in the event information</small>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['event_form_token'] ?? ''); ?>">

                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="Enter event title">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Event Date *</label>
                            <input type="date" name="event_date" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Event Time</label>
                            <input type="time" name="event_time" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g., Main Auditorium">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Event Type *</label>
                            <select name="event_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="Workshop">Workshop</option>
                                <option value="Seminar">Seminar</option>
                                <option value="Sports">Sports</option>
                                <option value="Cultural">Cultural</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Exam">Exam</option>
                                <option value="Festival">Festival</option>
                                <option value="Deadline">Deadline</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Event description..."></textarea>
                        </div>

                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <a href="manage_events.php" class="btn-outline">Cancel</a>
                                <button type="submit" class="btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>Create Event
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <h5 style="margin: 0 0 16px; font-weight: 600;">
                    <i class="bi bi-lightbulb me-2" style="color: var(--secondary);"></i>Quick Tips
                </h5>
                <ul style="list-style: none; padding: 0; margin: 0; color: var(--text-muted); font-size: 0.9rem;">
                    <li style="margin-bottom: 12px; display: flex; gap: 8px;">
                        <i class="bi bi-check-circle" style="color: #10B981;"></i>
                        <span>Events auto-appear in calendar</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; gap: 8px;">
                        <i class="bi bi-check-circle" style="color: #10B981;"></i>
                        <span>Color-coded by event type</span>
                    </li>
                    <li style="margin-bottom: 12px; display: flex; gap: 8px;">
                        <i class="bi bi-check-circle" style="color: #10B981;"></i>
                        <span>Students see events immediately</span>
                    </li>
                </ul>

                <div style="margin-top: 20px; padding: 16px; background: #F3F4F6; border-radius: 12px;">
                    <small style="display: block; margin-bottom: 10px; font-weight: 600; color: var(--text);">Color Coding:</small>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <span class="badge-type" style="background: #EF4444; color: #fff;">Holiday</span>
                        <span class="badge-type" style="background: #F59E0B; color: #fff;">Exam</span>
                        <span class="badge-type" style="background: #4F46E5; color: #fff;">Workshop</span>
                        <span class="badge-type" style="background: #10B981; color: #fff;">Festival</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>