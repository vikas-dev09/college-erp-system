<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database config
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'aureon';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'>
        Database Connection Failed
    </div>");
}

// ================= ROLE CHECK =================
// Only allow ADMIN (based on your DB)
$allowed_roles = ['admin'];

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], $allowed_roles)
) {
    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'>
        <h2>🔒 Access Denied</h2>
        <p>Admin privileges required.</p>
        <p style='font-size:0.8rem; color:gray'>
            Current Role: " . htmlspecialchars($_SESSION['role'] ?? 'None') . "
        </p>
        <a href='login.php' style='color:#4F46E5;text-decoration:none;font-weight:600'>
            Go to Login
        </a>
    </div>");
}

// ================= COLOR MAP =================
function getColor($t) {
    $map = [
        'Holiday'  => '#EF4444',
        'Exam'     => '#F59E0B',
        'Deadline' => '#D97706',
        'Festival' => '#10B981',
        'Workshop' => '#4F46E5'
    ];
    return $map[$t] ?? '#10B981';
}

// ================= ADD ENTRY =================
if (isset($_POST['add'])) {

    $t    = $conn->real_escape_string($_POST['title']);
    $d    = $conn->real_escape_string($_POST['calendar_date']);
    $ty   = $conn->real_escape_string($_POST['type']);
    $desc = $conn->real_escape_string($_POST['description']);
    $c    = getColor($ty);

    $sql = "INSERT INTO academic_calendar 
            (title, calendar_date, type, description, color)
            VALUES ('$t', '$d', '$ty', '$desc', '$c')";

    $conn->query($sql);

    header("Location: calendar_admin.php");
    exit();
}


// === DELETE ENTRY ===
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM academic_calendar WHERE id = $id");
    
    header("Location: calendar_admin.php");
    exit();
}

// === FETCH DATA ===
$entries = $conn->query("SELECT * FROM academic_calendar ORDER BY calendar_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Calendar - AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#FAF9F6;--card:#fff;--text:#1F2937;--muted:#6B7280;--primary:#4F46E5;--secondary:#D97706;--border:#E5E7EB;--shadow:0 4px 20px rgba(0,0,0,0.05);}
        *{font-family:'Inter',sans-serif;}
        body{background:var(--bg);color:var(--text);margin:0;min-height:100vh;}
        .sidebar{position:fixed;left:0;top:0;width:280px;height:100vh;background:var(--card);border-right:1px solid var(--border);z-index:1000;display:flex;flex-direction:column;}
        .sidebar-brand{padding:30px 25px;border-bottom:1px solid var(--border);}
        .sidebar-brand h2{margin:0;font-size:1.5rem;font-weight:700;color:var(--primary);}
        .sidebar-brand small{color:var(--muted);font-size:0.75rem;text-transform:uppercase;}
        .nav-menu{flex:1;padding:20px 15px;}
        .nav-link{display:flex;align-items:center;gap:12px;padding:14px 18px;color:var(--muted);text-decoration:none;border-radius:12px;margin-bottom:8px;}
        .nav-link:hover{background:#F3F4F6;color:var(--text);}
        .nav-link.active{background:var(--primary);color:#fff;}
        .back-dashboard{padding:20px 15px;border-top:1px solid var(--border);}
        .btn-back{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;background:#F3F4F6;border:none;color:var(--text);text-decoration:none;border-radius:12px;}
        .btn-back:hover{background:var(--secondary);color:#fff;}
        .main-content{margin-left:280px;padding:40px;}
        .page-title{font-size:1.75rem;font-weight:700;margin-bottom:30px;color:var(--text);}
        .card{background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:25px;}
        .form-control{background:#F9FAFB;border:2px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;}
        .form-control:focus{border-color:var(--primary);outline:none;}
        .btn-primary{background:var(--primary);border:none;color:#fff;padding:10px 20px;border-radius:8px;font-weight:600;width:100%;}
        .btn-primary:hover{background:#4338CA;}
        .table{width:100%;border-collapse:collapse;}
        .table th{background:#F9FAFB;color:var(--text);padding:12px;text-align:left;font-size:0.85rem;text-transform:uppercase;}
        .table td{padding:12px;border-bottom:1px solid var(--border);color:var(--muted);}
        .badge-type{padding:4px 10px;border-radius:6px;font-size:0.75rem;font-weight:600;color:#fff;}
        @media(max-width:991px){.sidebar{transform:translateX(-100%);}.sidebar.active{transform:translateX(0);}.main-content{margin-left:0;padding:20px;}}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><h2>AUREON ERP</h2><small>Admin Portal</small></div>
    <nav class="nav-menu">
        <a href="create_event.php" class="nav-link"><i class="bi bi-calendar-plus"></i> Create Event</a>
        <a href="manage_events.php" class="nav-link"><i class="bi bi-calendar-event"></i> Manage Events</a>
        <a href="calendar_admin.php" class="nav-link active"><i class="bi bi-calendar-check"></i> Academic Calendar</a>
        <a href="college_calendar.php" class="nav-link"><i class="bi bi-calendar3"></i> View Calendar</a>
    </nav>
    <div class="back-dashboard"><a href="super_admin.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Dashboard</a></div>
</div>
<div class="main-content">
    <h1 class="page-title">Academic Calendar</h1>
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card">
                <h5 style="margin:0 0 20px;font-weight:600">Add Entry</h5>
                <form method="POST">
                    <div class="mb-3"><input type="text" name="title" class="form-control" placeholder="Title" required></div>
                    <div class="mb-3"><input type="date" name="calendar_date" class="form-control" required></div>
                    <div class="mb-3">
                        <select name="type" class="form-control">
                            <option value="Holiday">Holiday</option>
                            <option value="Exam">Exam</option>
                            <option value="Deadline">Deadline</option>
                            <option value="Festival">Festival</option>
                            <option value="Workshop">Workshop</option>
                        </select>
                    </div>
                    <div class="mb-3"><textarea name="description" class="form-control" rows="3" placeholder="Description"></textarea></div>
                    <button type="submit" name="add" class="btn-primary">Add Entry</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <h5 style="margin:0 0 20px;font-weight:600">Calendar Entries</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Title</th><th>Date</th><th>Type</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if($entries && $entries->num_rows > 0): ?>
                                <?php while($row = $entries->fetch_assoc()): ?>
                                <tr>
                                    <td><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $row['color'];?>;margin-right:8px;"></span><?php echo htmlspecialchars($row['title']);?></td>
                                    <td><?php echo date('M d, Y',strtotime($row['calendar_date']));?></td>
                                    <td><span class="badge-type" style="background:<?php echo $row['color'];?>"><?php echo $row['type'];?></span></td>
                                    <td><a href="?delete=<?php echo $row['id'];?>" onclick="return confirm('Delete this event?')" style="color:#EF4444;text-decoration:none"><i class="bi bi-trash"></i></a></td>
                                </tr>
                                <?php endwhile;?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; padding: 20px;">No events found in the calendar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>