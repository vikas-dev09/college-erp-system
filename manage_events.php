<?php
// Ensure session starts before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost'; $user = 'root'; $pass = ''; $dbname = 'aureon';
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("DB Error");

// === AUTHENTICATION CHECK (FIXED) ===
// Changed check from 'super admin' to 'admin' to match your database record
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'>
         <h2>🔒 Access Denied</h2><p>Administrative privileges required.</p>
         <p style='font-size:0.8rem; color:gray'>Current Role: " . (isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'None') . "</p>
         <a href='login.php' style='color:#4F46E5;text-decoration:none;font-weight:600'>Go to Login</a></div>");
}

// Delete Logic
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM events WHERE id = $id");
    header("Location: manage_events.php");
    exit();
}

// Search/Filter Logic
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$sql = "SELECT * FROM events WHERE 1=1";
if ($search) $sql .= " AND title LIKE '%$search%'";
if ($type) $sql .= " AND event_type = '$type'";
$sql .= " ORDER BY event_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events - AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #FAF9F6; --card: #FFFFFF; --text: #1F2937; --text-muted: #6B7280;
            --primary: #4F46E5; --primary-hover: #4338CA; --secondary: #D97706;
            --border: #E5E7EB; --shadow: 0 4px 20px rgba(0, 0, 0, 0.05); --radius: 16px;
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); margin: 0; min-height: 100vh; }
        .sidebar {
            position: fixed; left: 0; top: 0; width: 280px; height: 100vh;
            background: var(--card); border-right: 1px solid var(--border);
            z-index: 1000; display: flex; flex-direction: column; transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 30px 25px; border-bottom: 1px solid var(--border); }
        .sidebar-brand h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .sidebar-brand small { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; }
        .nav-menu { flex: 1; padding: 20px 15px; }
        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 14px 18px;
            color: var(--text-muted); text-decoration: none; border-radius: 12px;
            margin-bottom: 8px; transition: all 0.2s ease; font-weight: 500;
        }
        .nav-link:hover { background: #F3F4F6; color: var(--text); }
        .nav-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }
        .back-dashboard { padding: 20px 15px; border-top: 1px solid var(--border); }
        .btn-back {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 12px; background: #F3F4F6; border: none;
            color: var(--text); text-decoration: none; border-radius: 12px;
            transition: all 0.2s ease; font-weight: 500;
        }
        .btn-back:hover { background: var(--secondary); color: #fff; }
        .main-content { margin-left: 280px; padding: 40px; }
        .page-title { font-size: 1.75rem; font-weight: 700; margin-bottom: 8px; color: var(--text); }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow); padding: 25px; margin-bottom: 25px;
        }
        .form-control {
            background: #F9FAFB; border: 2px solid var(--border); color: var(--text);
            padding: 10px 14px; border-radius: 8px; font-size: 0.9rem;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); outline: none; }
        .btn-primary {
            background: var(--primary); border: none; color: #fff;
            padding: 10px 20px; border-radius: 8px; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: #F9FAFB; color: var(--text); font-weight: 600;
            padding: 14px; text-align: left; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .table td { padding: 14px; border-bottom: 1px solid var(--border); color: var(--text-muted); }
        .table tr:hover td { background: #F9FAFB; }
        .color-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; color: #fff; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 6px; display: inline-flex;
            align-items: center; justify-content: center; text-decoration: none;
            background: #F3F4F6; color: var(--text); margin-right: 6px; transition: all 0.2s ease;
        }
        .action-btn:hover { background: var(--primary); color: #fff; }
        .action-btn.delete:hover { background: #EF4444; }
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle {
                display: block; position: fixed; top: 20px; left: 20px; z-index: 1001;
                background: var(--primary); border: none; color: #fff;
                width: 45px; height: 45px; border-radius: 10px; cursor: pointer;
            }
        }
    </style>
</head>
<body>

<button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
    <i class="bi bi-list"></i>
</button>

<div class="sidebar">
    <div class="sidebar-brand"><h2>AUREON ERP</h2><small>Admin Panel</small></div>
    <nav class="nav-menu">
        <a href="create_event.php" class="nav-link"><i class="bi bi-calendar-plus"></i> Create Event</a>
        <a href="manage_events.php" class="nav-link active"><i class="bi bi-calendar-event"></i> Manage Events</a>
        <a href="calendar_admin.php" class="nav-link"><i class="bi bi-calendar-check"></i> Academic Calendar</a>
        <a href="college_calendar.php" class="nav-link"><i class="bi bi-calendar3"></i> View Calendar</a>
    </nav>
    <div class="back-dashboard">
        <a href="super_admin.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

<div class="main-content">
    <h1 class="page-title">Manage Events</h1>
    
    <div class="card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <form class="d-flex gap-2 flex-wrap" method="GET">
                <input type="text" name="search" class="form-control" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>" style="width: 250px;">
                <select name="type" class="form-control" onchange="this.form.submit()" style="width: auto;">
                    <option value="">All Types</option>
                    <option value="Workshop" <?php if($type=='Workshop')echo'selected';?>>Workshop</option>
                    <option value="Exam" <?php if($type=='Exam')echo'selected';?>>Exam</option>
                    <option value="Sports" <?php if($type=='Sports')echo'selected';?>>Sports</option>
                    <option value="Cultural" <?php if($type=='Cultural')echo'selected';?>>Cultural</option>
                </select>
                <button type="submit" class="btn-primary"><i class="bi bi-search"></i></button>
                <?php if($search || $type): ?>
                    <a href="manage_events.php" class="action-btn"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
            <a href="create_event.php" class="btn-primary"><i class="bi bi-plus-lg"></i> New Event</a>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="color-dot" style="background:<?php echo $row['event_color'];?>"></span>
                                <strong style="color:var(--text)"><?php echo htmlspecialchars($row['title']);?></strong>
                                <div style="font-size:0.85rem;opacity:0.7">
                                    <?php 
                                        $desc = htmlspecialchars($row['description']);
                                        echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div style="color:var(--text)"><?php echo date('M d, Y',strtotime($row['event_date']));?></div>
                                <small><?php echo !empty($row['event_time']) ? date('h:i A', strtotime($row['event_time'])) : 'All Day';?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['location']) ?: 'Not specified';?></td>
                            <td>
                                <span class="badge-type" style="background:<?php echo $row['event_color'];?>">
                                    <?php echo $row['event_type'];?>
                                </span>
                            </td>
                            <td>
                                <a href="edit_event.php?id=<?php echo $row['id'];?>" class="action-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="?delete=<?php echo $row['id'];?>" class="action-btn delete" onclick="return confirm('Delete this event?')" title="Delete"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 40px 20px;">
                                <i class="bi bi-calendar-x" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 10px; display: block;"></i>
                                <?php echo ($search || $type) ? 'No events match your search criteria.' : 'There are no events currently scheduled.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>