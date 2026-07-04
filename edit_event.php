<?php
session_start();
$host = 'localhost'; $user = 'root'; $pass = ''; $dbname = 'aureon';
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("DB Error");

// === AUTHENTICATION CHECK (FIXED) ===
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'>
         <h2>🔒 Access Denied</h2><p>Super Admin privileges required.</p>
         <a href='login.php' style='color:#4F46E5;text-decoration:none;font-weight:600'>Go to Login</a></div>");
}

function getColor($t) {
    $m = [
        'Holiday' => '#EF4444', 'Exam' => '#F59E0B', 'Workshop' => '#4F46E5', 
        'Seminar' => '#3B82F6', 'Festival' => '#10B981', 'Sports' => '#8B5CF6', 
        'Cultural' => '#EC4899', 'Deadline' => '#D97706'
    ];
    return $m[$t] ?? '#4F46E5';
}

$id = intval($_GET['id'] ?? 0);
$r = $conn->query("SELECT * FROM events WHERE id = $id");
$e = $r->fetch_assoc();

if (!$e) {
    die("<div style='color:#1F2937;background:#FAF9F6;padding:50px;text-align:center;font-family:sans-serif'><h2>Event Not Found</h2><a href='manage_events.php' style='color:#4F46E5'>Back to Events</a></div>");
}

// === SECURE UPDATE WITH PREPARED STATEMENT ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t  = trim($_POST['title']);
    $d  = trim($_POST['description']);
    $dt = trim($_POST['event_date']);
    $tm = trim($_POST['event_time']);
    $l  = trim($_POST['location']);
    $ty = trim($_POST['event_type']);
    $c  = getColor($ty);

    $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time=?, location=?, event_type=?, event_color=? WHERE id=?");
    
    if ($stmt) {
        $stmt->bind_param("sssssssi", $t, $d, $dt, $tm, $l, $ty, $c, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: manage_events.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event - AUREON ERP</title>
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
        .nav-menu{flex:1;padding:20px 15px;}
        .nav-link{display:flex;align-items:center;gap:12px;padding:14px 18px;color:var(--muted);text-decoration:none;border-radius:12px;margin-bottom:8px;transition:all 0.2s;}
        .nav-link:hover{background:#F3F4F6;color:var(--text);}
        .nav-link.active{background:var(--primary);color:#fff;}
        .back-dashboard{padding:20px 15px;border-top:1px solid var(--border);}
        .btn-back{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;background:#F3F4F6;border:none;color:var(--text);text-decoration:none;border-radius:12px;transition:all 0.2s;}
        .btn-back:hover{background:var(--secondary);color:#fff;}
        .main-content{margin-left:280px;padding:40px;}
        .page-title{font-size:1.75rem;font-weight:700;margin-bottom:30px;color:var(--text);}
        .card{background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:30px;}
        .form-control{background:#F9FAFB;border:2px solid var(--border);color:var(--text);padding:12px 16px;border-radius:10px;}
        .form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,0.1);outline:none;}
        .btn-primary{background:var(--primary);border:none;color:#fff;padding:12px 28px;border-radius:10px;font-weight:600;cursor:pointer;}
        .btn-primary:hover{background:#4338CA;}
        .btn-outline{background:transparent;border:2px solid var(--border);color:var(--text);padding:12px 28px;border-radius:10px;text-decoration:none;}
        .btn-outline:hover{background:#F3F4F6;}
        @media(max-width:991px){.sidebar{transform:translateX(-100%);}.sidebar.active{transform:translateX(0);}.main-content{margin-left:0;padding:20px;}}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-brand"><h2>AUREON ERP</h2></div>
    <nav class="nav-menu">
        <a href="manage_events.php" class="nav-link active"><i class="bi bi-arrow-left"></i> Back to List</a>
    </nav>
    <div class="back-dashboard"><a href="super_admin.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Dashboard</a></div>
</div>
<div class="main-content">
    <h1 class="page-title">Edit Event</h1>
    <div class="card">
        <form method="POST">
            <div class="row g-4">
                <div class="col-md-12">
                    <label style="color:var(--text);font-weight:600">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($e['title']);?>" required>
                </div>
                <div class="col-md-6">
                    <label style="color:var(--text);font-weight:600">Date</label>
                    <input type="date" name="event_date" class="form-control" value="<?php echo htmlspecialchars($e['event_date']);?>" required>
                </div>
                <div class="col-md-6">
                    <label style="color:var(--text);font-weight:600">Time</label>
                    <input type="time" name="event_time" class="form-control" value="<?php echo htmlspecialchars($e['event_time']);?>">
                </div>
                <div class="col-md-6">
                    <label style="color:var(--text);font-weight:600">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($e['location']);?>">
                </div>
                <div class="col-md-6">
                    <label style="color:var(--text);font-weight:600">Type</label>
                    <select name="event_type" class="form-control" required>
                        <?php foreach(['Workshop','Seminar','Sports','Cultural','Holiday','Exam','Festival','Deadline'] as $ty):?>
                        <option value="<?php echo $ty;?>" <?php if($e['event_type']==$ty)echo'selected';?>><?php echo $ty;?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label style="color:var(--text);font-weight:600">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($e['description']);?></textarea>
                </div>
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <a href="manage_events.php" class="btn-outline">Cancel</a>
                        <button type="submit" class="btn-primary">Update Event</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</body>
</html>