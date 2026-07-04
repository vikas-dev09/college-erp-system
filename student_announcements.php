<?php
session_start();

// Database Connection
$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Session Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Student Info
$stmt = $pdo->prepare("SELECT u.full_name, u.student_id, s.photo
FROM users u
JOIN students s ON u.email = s.email
WHERE u.id = ?");
$stmt->execute([$user_id]);$user = $stmt->fetch();

$full_name = $user['full_name'] ?? 'Student';

// Filters
$category_filter = $_GET['category'] ?? '';

// Fetch Announcements
$sql = "SELECT * FROM announcements WHERE status = 'Active'";

if (!empty($category_filter)) {
    $sql .= " AND category = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_filter]);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}

$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count Stats
$total = count($announcements);
$academic = 0;
$general = 0;

foreach ($announcements as $a) {
    if ($a['category'] === 'Academic') $academic++;
    if ($a['category'] === 'General') $general++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg: #fdf4e8;
            --sidebar: #f5f3ff;
            --primary: #8b5cf6;
            --light: #ede9fe;
            --text: #1e293b;
            --muted: #64748b;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { background:var(--bg); color:var(--text); display:flex; height:100vh; overflow:hidden; }

        .sidebar { width:290px; background:var(--sidebar); padding:25px 20px; position:fixed; height:100%; box-shadow:2px 0 15px rgba(0,0,0,0.05); display:flex; flex-direction:column; }
        .logo-circle { width:135px; height:135px; margin:0 auto 15px; border-radius:50%; background:linear-gradient(135deg,#ede9fe,#8b5cf6); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(139,92,246,0.3); }
        .logo-circle i { font-size:62px; color:white; }

.nav-link { 
    display:flex;
    align-items:center;
    gap:14px;
    padding:16px 18px;
    color:var(--muted);
    text-decoration:none;
    border-radius:14px;
    margin-bottom:10px;

    font-size:1.05rem;
    font-weight:500;
    transition:0.3s ease;
    
}   
.nav-link i {
    font-size:1.25rem;
    width:28px;
    text-align:center;
}     .nav-link.active { background:var(--primary); color:white; }

        .main-content { margin-left:290px; flex:1; padding:30px 40px; overflow-y:auto; }

        .top-bar { background:white; padding:20px 25px; border-radius:16px; box-shadow:0 6px 20px rgba(0,0,0,0.07); display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; }

        .card { background:white; border-radius:18px; padding:28px; box-shadow:0 8px 25px rgba(139,92,246,0.08); margin-bottom:25px; }

        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .summary-card { background:linear-gradient(135deg, var(--light), white); padding:22px; border-radius:16px; text-align:center; border:1px solid #e9d5ff; }

        .announcement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }

        .ann-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(139,92,246,0.1);
            transition: all 0.3s ease;
        }
        .ann-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(139,92,246,0.18);
        }

        .ann-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .ann-content {
            padding: 20px;
        }

        .ann-category {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .cat-Academic { background:#dbeafe; color:#1e40af; }
        .cat-General { background:#f3e8ff; color:#6b21a8; }

        .ann-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .ann-message {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ann-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .no-ann {
            text-align: center;
            padding: 100px 20px;
            color: var(--muted);
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div style="text-align:center; margin-bottom:35px;">
            <div class="logo-circle"><i class="fa-solid fa-a"></i></div>
            <div style="font-weight:700; font-size:1.3rem;">AUREON ERP</div>
            <div style="color:var(--primary);">Student Portal</div>
        </div>

        <ul style="list-style:none; flex:1;">
            <li><a href="student_dash.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="student_attendance.php" class="nav-link"><i class="fa-regular fa-calendar-check"></i> Attendance</a></li>
            <li><a href="view_marks.php" class="nav-link"><i class="fa-solid fa-chart-simple"></i> My Marks</a></li>
            <li><a href="student_announcements.php" class="nav-link active"><i class="fa-solid fa-bullhorn"></i> Announcements</a></li>
            <li><a href="view_books.php" class="nav-link"><i class="fa-solid fa-book"></i> Library</a></li>
        </ul>

        <a href="logout.php" class="nav-link" style="color:#ef4444; margin-top:auto;">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
       <div class="top-bar">
    
    <!-- Left Title -->
    <div>
        <h1 style="margin:0; font-size:2rem; font-weight:800; color:#8b5cf6;">
            Announcements
        </h1>
        <p style="margin:4px 0 0; color:#64748b; font-size:0.95rem;">
            Stay updated with latest college news & updates
        </p>
    </div>

    <!-- Right Profile -->
    <div style="display:flex; align-items:center; gap:15px;">
        
<img src="uploads/students/<?= htmlspecialchars($user['photo'] ?? 'default.png') ?>" 
     class="profile-photo"
     style="width:55px;height:55px;border-radius:50%;object-fit:cover;">        <div style="text-align:right;">
            <div style="font-size:1.1rem;font-weight:700;">
                <?= htmlspecialchars($full_name) ?>
            </div>
            <div style="color:#64748b;font-size:0.85rem;">
                Student ID:= <?= htmlspecialchars($user['student_id'] ?? '') ?>
            </div>
        </div>

    </div>
</div>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-card">
                <div style="color:var(--muted);">Total Announcements</div>
                <div style="font-size:2.4rem; font-weight:700; color:var(--primary);"><?= $total ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Academic</div>
                <div style="font-size:2.2rem; font-weight:700;"><?= $academic ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">General</div>
                <div style="font-size:2.2rem; font-weight:700;"><?= $general ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card" style="margin-bottom:25px;">
            <form method="GET" style="display:flex; gap:15px; align-items:center;">
                <select name="category" style="padding:12px 16px; border-radius:10px; border:1px solid #e2e8f0; width:220px;">
                    <option value="">All Categories</option>
                    <option value="Academic" <?= $category_filter=='Academic'?'selected':'' ?>>Academic</option>
                    <option value="General" <?= $category_filter=='General'?'selected':'' ?>>General</option>
                </select>
                <button type="submit" style="background:var(--primary); color:white; border:none; padding:12px 24px; border-radius:10px; cursor:pointer;">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <a href="student_announcements.php" style="color:var(--muted); text-decoration:none;">Clear Filter</a>
            </form>
        </div>

        <!-- Announcements Grid -->
        <div class="card">
            <h2 style="margin-bottom:20px;">Latest Announcements</h2>

            <?php if (count($announcements) > 0): ?>
                <div class="announcement-grid">
                    <?php foreach ($announcements as $ann): ?>
                    <div class="ann-card">
                        <?php if (!empty($ann['image_path'])): ?>
                            <img src="<?= htmlspecialchars($ann['image_path']) ?>" class="ann-image" alt="Announcement">
                        <?php endif; ?>

                        <div class="ann-content">
                            <span class="ann-category cat-<?= htmlspecialchars($ann['category']) ?>">
                                <?= htmlspecialchars($ann['category']) ?>
                            </span>
                            <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
                            <div class="ann-message"><?= nl2br(htmlspecialchars($ann['message'])) ?></div>
                            
                            <div class="ann-footer">
                                <span><i class="fa-solid fa-calendar"></i> <?= date('d M Y', strtotime($ann['created_at'])) ?></span>
                                <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($ann['created_by']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-ann">
                    <i class="fa-solid fa-bullhorn" style="font-size:5rem; opacity:0.1; display:block; margin-bottom:20px;"></i>
                    <h3>No Announcements Yet</h3>
                    <p>Stay tuned! New announcements will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>