<?php
session_start();

// 1. Database Connection
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

// 2. Session Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 3. Fetch User from users table
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Student not found.");
}

// 4. 🔥 CRITICAL FIX: Get correct student ID from students table
$student_stmt = $pdo->prepare("SELECT id, student_id, full_name FROM students WHERE email = ?");
$student_stmt->execute([$user['email']]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    // Fallback if email doesn't match
    $student_stmt = $pdo->prepare("SELECT id, student_id FROM students WHERE student_id = ?");
    $student_stmt->execute([$user['student_id'] ?? '']);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$student) {
    die("Student master record not found in students table.");
}

// ✅ This is the CORRECT ID to use for internal_marks
$correct_student_id = $student['id'];   // e.g., 26, 10, 7 etc.

$full_name = $student['full_name'] ?? $user['full_name'] ?? 'Student';

// 5. Fetch Marks using correct student_id
$marks_stmt = $pdo->prepare("SELECT * FROM internal_marks 
                             WHERE student_id = :sid 
                             ORDER BY subject, exam_type DESC");
$marks_stmt->execute(['sid' => $correct_student_id]);
$marks_data = $marks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Summary
$total_obtained = 0;
$total_max = 0;
$count = count($marks_data);

foreach ($marks_data as $mark) {
    $total_obtained += (int)$mark['marks_obtained'];
    $total_max += (int)$mark['max_marks'];
}

$overall_percentage = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Marks - AUREON ERP</title>
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

        .sidebar { width:290px; background:var(--sidebar); padding:25px 20px; position:fixed; height:100%; box-shadow:2px 0 15px rgba(0,0,0,0.05); }
        .logo-circle { width:135px; height:135px; margin:0 auto 15px; border-radius:50%; background:linear-gradient(135deg,#ede9fe,#8b5cf6); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(139,92,246,0.3); }
        .logo-circle i { font-size:62px; color:white; }

        .nav-link { display:flex; align-items:center; gap:12px; padding:14px 16px; color:var(--muted); text-decoration:none; border-radius:12px; margin-bottom:6px; transition:0.3s; }
        .nav-link.active { background:var(--primary); color:white; }

        .main-content { margin-left:290px; flex:1; padding:30px 40px; overflow-y:auto; }

        .top-bar { background:white; padding:20px 25px; border-radius:16px; box-shadow:0 6px 20px rgba(0,0,0,0.07); display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; }

        .card { background:white; border-radius:18px; padding:28px; box-shadow:0 8px 25px rgba(139,92,246,0.08); margin-bottom:25px; }

        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px,1fr)); gap:22px; margin-bottom:30px; }
        .summary-card { background:linear-gradient(135deg, var(--light), white); padding:24px; border-radius:16px; text-align:center; border:1px solid #e9d5ff; }
        .summary-value { font-size:2.2rem; font-weight:700; color:var(--primary); }

        table { width:100%; border-collapse:collapse; }
        th { background:var(--light); padding:16px 12px; text-align:left; color:var(--primary); font-weight:600; }
        td { padding:16px 12px; border-bottom:1px solid #f1f5f9; }
        tr:hover { background:#faf5ff; }

        .badge { padding:6px 14px; border-radius:30px; font-size:0.82rem; font-weight:600; }
        .badge-submitted { background:#d1fae5; color:#166534; }
        .badge-pending { background:#fef3c7; color:#92400e; }

        .no-results { text-align:center; padding:100px 20px; color:var(--muted); }
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
            <li><a href="student_profile.php" class="nav-link"><i class="fa-solid fa-user"></i> Profile</a></li>
            <li><a href="view_marks.php" class="nav-link active"><i class="fa-solid fa-chart-simple"></i> My Marks</a></li>
            <li><a href="student_attendance.php" class="nav-link"><i class="fa-regular fa-calendar-check"></i> Attendance</a></li>
            <li><a href="view_books.php" class="nav-link"><i class="fa-solid fa-book"></i> Library</a></li>
        </ul>

        <a href="logout.php" class="nav-link" style="color:#ef4444; margin-top:auto;">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1 style="margin:0; font-size:1.75rem;">My Academic Performance</h1>
            <div style="display:flex; align-items:center; gap:15px;">
                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong><br>
                    <small>ID: <?= htmlspecialchars($student['student_id'] ?? 'N/A') ?></small>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($full_name) ?>&background=8b5cf6&color=fff" 
                     style="width:55px; height:55px; border-radius:50%; border:3px solid var(--primary);">
            </div>
        </div>

        <?php if (count($marks_data) > 0): ?>
        <div class="summary-grid">
            <div class="summary-card">
                <div style="color:var(--muted);">Total Obtained</div>
                <div class="summary-value"><?= $total_obtained ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Max Marks</div>
                <div class="summary-value"><?= $total_max ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Overall %</div>
                <div class="summary-value"><?= $overall_percentage ?>%</div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Subjects</div>
                <div class="summary-value"><?= $count ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-bottom:20px;">Internal Marks Record</h2>

            <?php if (count($marks_data) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Exam Type</th>
                        <th>Obtained</th>
                        <th>Max</th>
                        <th>Percentage</th>
                        <th>Remarks</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marks_data as $mark): 
                        $perc = $mark['max_marks'] > 0 ? round(($mark['marks_obtained'] / $mark['max_marks']) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($mark['subject']) ?></strong></td>
                        <td><?= htmlspecialchars($mark['exam_type']) ?></td>
                        <td><strong><?= $mark['marks_obtained'] ?></strong></td>
                        <td><?= $mark['max_marks'] ?></td>
                        <td style="font-weight:700; color:<?= $perc >= 40 ? '#10b981' : '#ef4444' ?>;">
                            <?= $perc ?>%
                        </td>
                        <td><?= htmlspecialchars($mark['remarks'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= strtolower($mark['status']) == 'submitted' ? 'badge-submitted' : 'badge-pending' ?>">
                                <?= ucfirst($mark['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-results">
                <i class="fa-regular fa-folder-open" style="font-size:5.5rem; opacity:0.15; display:block; margin-bottom:20px;"></i>
                <h3>No Marks Uploaded Yet</h3>
                <p>Your teachers have not published any internal marks.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>