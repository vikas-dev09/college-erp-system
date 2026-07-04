<?php
session_start();

// 1. Database Connection
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

// 2. Session Check
// For testing: $_SESSION['user_id'] = 6; $_SESSION['role'] = 'student';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 3. Fetch Student Info (only basic fields from users table)
$stmt = $pdo->prepare("SELECT id, full_name, email, student_id FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Student not found.");

// 4. Filter Logic
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterSubject = isset($_GET['subject']) ? $_GET['subject'] : '';
$filterCourse = isset($_GET['course']) ? $_GET['course'] : '';
$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query - ONLY using study_materials table columns
$sql = "SELECT id, teacher_id, course, year, semester, subject, title, file_path, remarks, status, uploaded_at, file_name, file_size 
        FROM study_materials 
        WHERE status = 'published'";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (title LIKE :search OR subject LIKE :search OR remarks LIKE :search)";
    $params['search'] = "%$searchTerm%";
}
if (!empty($filterSubject)) {
    $sql .= " AND subject = :subject";
    $params['subject'] = $filterSubject;
}
if (!empty($filterCourse)) {
    $sql .= " AND course = :course";
    $params['course'] = $filterCourse;
}

switch ($sortOrder) {
    case 'oldest': $sql .= " ORDER BY uploaded_at ASC"; break;
    case 'subject': $sql .= " ORDER BY subject ASC"; break;
    case 'title': $sql .= " ORDER BY title ASC"; break;
    default: $sql .= " ORDER BY uploaded_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Get unique subjects & courses
$subjects = $pdo->query("SELECT DISTINCT subject FROM study_materials WHERE status='published' ORDER BY subject")->fetchAll(PDO::FETCH_COLUMN);
$courses = $pdo->query("SELECT DISTINCT course FROM study_materials WHERE status='published' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);

$totalMaterials = count($materials);

// Helpers
function formatFileSize($bytes) {
    if (!$bytes) return 'N/A';
    if ($bytes >= 1048576) return round($bytes/1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes/1024, 2) . ' KB';
    return $bytes . ' B';
}

function getFileIcon($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => ['fa-file-pdf', '#ef4444'],
        'doc' => ['fa-file-word', '#3b82f6'],
        'docx' => ['fa-file-word', '#3b82f6'],
        'ppt' => ['fa-file-powerpoint', '#f97316'],
        'pptx' => ['fa-file-powerpoint', '#f97316'],
        'xls' => ['fa-file-excel', '#10b981'],
        'xlsx' => ['fa-file-excel', '#10b981'],
        'zip' => ['fa-file-zipper', '#8b5cf6'],
        'jpg' => ['fa-file-image', '#ec4899'],
        'png' => ['fa-file-image', '#ec4899'],
    ];
    return $icons[$ext] ?? ['fa-file-lines', '#64748b'];
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff/86400) . ' days ago';
    return date('M d, Y', $time);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Books - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-glow: 0 10px 25px -5px rgba(139, 92, 246, 0.4);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-color); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* Sidebar */
        .sidebar {
            width: 290px; background: var(--sidebar-bg);
            display: flex; flex-direction: column; padding: 20px;
            position: fixed; height: 100%;
            box-shadow: 2px 0 10px rgba(0,0,0,0.02); z-index: 10;
        }
        .logo-container { text-align: center; margin-bottom: 30px; }
        .logo-circle {
            width: 130px; height: 130px; border-radius: 50%;
            background: linear-gradient(135deg, var(--light-accent), var(--accent-color));
            margin: 0 auto 15px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: var(--shadow-md);
        }
        .logo-circle i { font-size: 60px; color: white; font-weight: bold; }
        .brand-name { font-weight: 700; font-size: 1.2rem; }
        .brand-sub { font-size: 0.85rem; color: var(--accent-color); font-weight: 500; }
        .nav-menu { list-style: none; flex: 1; }
        .nav-item { margin-bottom: 8px; }
        .nav-link {
            display: flex; align-items: center; padding: 14px 16px;
            text-decoration: none; color: var(--text-muted);
            border-radius: 12px; font-weight: 500; transition: all 0.3s;
        }
        .nav-link i { width: 24px; margin-right: 12px; font-size: 1.1rem; }
        .nav-link:hover { background: rgba(139,92,246,0.1); color: var(--accent-color); }
        .nav-link.active {
            background: var(--accent-color); color: white;
            box-shadow: 0 4px 12px rgba(139,92,246,0.3);
        }
        .logout-btn { color: var(--danger); font-weight: 700; }

        /* Main */
        .main-content {
            flex: 1; margin-left: 290px;
            padding: 30px 40px;
            overflow-y: auto; height: 100vh;
        }
        .top-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
        }
        .welcome-text { display: flex; align-items: center; gap: 12px; font-size: 1.5rem; font-weight: 600; }
        .welcome-text i {
            color: var(--accent-color); background: var(--light-accent);
            padding: 12px; border-radius: 50%;
        }
        .profile-section {
            display: flex; align-items: center; gap: 15px;
            background: white; padding: 8px 20px 8px 8px;
            border-radius: 50px; box-shadow: var(--shadow-sm);
        }
        .student-id-tag { font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        .profile-photo {
            width: 60px; height: 60px; border-radius: 50%;
            border: 3px solid var(--accent-color);
        }

        .page-header { margin-bottom: 25px; }
        .page-title { font-size: 1.8rem; font-weight: 700; }
        .page-subtitle { color: var(--text-muted); margin-top: 4px; }

        /* Filter */
        .filter-bar {
            background: white; border-radius: 16px; padding: 20px;
            box-shadow: var(--shadow-sm); margin-bottom: 25px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 12px; align-items: center;
        }
        .filter-bar input, .filter-bar select {
            padding: 12px 14px; border: 1.5px solid #e2e8f0;
            border-radius: 10px; font-size: 0.9rem; outline: none;
            font-family: inherit; background: white;
        }
        .filter-bar input:focus, .filter-bar select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
        }
        .filter-btn {
            padding: 12px 20px; background: var(--accent-color);
            color: white; border: none; border-radius: 10px;
            cursor: pointer; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
        }
        .filter-btn:hover { background: #7c3aed; transform: translateY(-2px); }

        /* Stats */
        .stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 20px; margin-bottom: 25px;
        }
        .stat-card {
            background: white; border-radius: 16px; padding: 20px;
            box-shadow: var(--shadow-sm);
            display: flex; align-items: center; gap: 15px;
        }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            background: var(--light-accent); color: var(--accent-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .stat-card h4 { font-size: 0.85rem; color: var(--text-muted); }
        .stat-card p { font-size: 1.4rem; font-weight: 700; }

        /* Books grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .book-card {
            background: white; border-radius: 16px; padding: 22px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1.5px solid transparent;
            position: relative; overflow: hidden;
        }
        .book-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-glow);
            border-color: var(--light-accent);
        }
        .book-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--accent-color), #a78bfa);
        }
        .book-header {
            display: flex; align-items: flex-start; gap: 14px;
            margin-bottom: 15px;
        }
        .book-icon {
            width: 55px; height: 55px; border-radius: 12px;
            background: var(--light-accent);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; flex-shrink: 0;
        }
        .book-info h3 {
            font-size: 1.1rem; font-weight: 700;
            margin-bottom: 4px; text-transform: capitalize;
        }
        .subject-tag {
            display: inline-block; background: var(--light-accent);
            color: var(--accent-color); padding: 3px 10px;
            border-radius: 12px; font-size: 0.75rem; font-weight: 600;
        }
        .book-meta {
            margin: 12px 0; padding: 12px;
            background: #faf9ff; border-radius: 10px; font-size: 0.85rem;
        }
        .book-meta div {
            display: flex; justify-content: space-between;
            padding: 4px 0; color: var(--text-muted);
        }
        .book-meta div strong { color: var(--text-dark); }
        .book-remarks {
            font-size: 0.85rem; color: var(--text-muted);
            font-style: italic; padding: 8px 0;
            border-top: 1px dashed #e2e8f0;
            border-bottom: 1px dashed #e2e8f0; margin: 10px 0;
        }
        .book-actions {
            display: flex; gap: 10px; margin-top: 12px;
        }
        .btn {
            flex: 1; padding: 10px; border-radius: 10px;
            font-weight: 600; font-size: 0.85rem;
            text-decoration: none; text-align: center;
            border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            gap: 6px; transition: all 0.2s;
        }
        .btn-view { background: var(--light-accent); color: var(--accent-color); }
        .btn-view:hover { background: var(--accent-color); color: white; }
        .btn-download { background: var(--accent-color); color: white; }
        .btn-download:hover { background: #7c3aed; transform: translateY(-2px); }
        .upload-time {
            position: absolute; top: 15px; right: 15px;
            font-size: 0.7rem; color: var(--text-muted);
            background: #f1f5f9; padding: 3px 8px; border-radius: 10px;
        }

        .no-result {
            background: white; border-radius: 16px;
            padding: 60px 20px; text-align: center;
            box-shadow: var(--shadow-sm);
        }
        .no-result-icon {
            width: 100px; height: 100px; border-radius: 50%;
            background: var(--light-accent); margin: 0 auto 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem; color: var(--accent-color);
        }
        .no-result h2 { font-size: 1.5rem; margin-bottom: 10px; }
        .no-result p { color: var(--text-muted); }

        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo-container">
        <div class="logo-circle"><i class="fa-solid fa-a"></i></div>
        <div class="brand-name">AUREON ERP</div>
        <div class="brand-sub">Student Portal</div>
    </div>
    <ul class="nav-menu">
        <li class="nav-item"><a href="student_dash.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li class="nav-item"><a href="student_profile.php" class="nav-link"><i class="fa-regular fa-calendar"></i> My Profile</a></li>
        <li class="nav-item"><a href="view_marks.php" class="nav-link"><i class="fa-regular fa-file-lines"></i> My Marks</a></li>
        <li class="nav-item"><a href="view_books.php" class="nav-link active"><i class="fa-solid fa-book-open"></i> Library</a></li>
        <li class="nav-item"><a href="student_events.php" class="nav-link"><i class="fa-solid fa-trophy"></i> Sports</a></li>
    </ul>
    <div class="nav-item">
        <a href="logout.php" class="nav-link logout-btn">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<main class="main-content">

    <header class="top-bar">
        <div class="welcome-text">
            <i class="fa-solid fa-mortarboard"></i>
            <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</span>
        </div>
        <div class="profile-section">
            <div class="student-id-tag"><?php echo htmlspecialchars($user['student_id']); ?></div>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=8b5cf6&color=fff&size=128" class="profile-photo">
        </div>
    </header>

    <div class="page-header">
        <div class="page-title">📚 Digital Library</div>
        <div class="page-subtitle">Browse and download study materials published by your teachers</div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-book"></i></div>
            <div><h4>Total Books</h4><p><?php echo $totalMaterials; ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#10b981;"><i class="fa-solid fa-flask"></i></div>
            <div><h4>Subjects</h4><p><?php echo count($subjects); ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="fa-solid fa-graduation-cap"></i></div>
            <div><h4>Courses</h4><p><?php echo count($courses); ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fa-solid fa-cloud-arrow-down"></i></div>
            <div><h4>Available</h4><p>Free</p></div>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Search by title, subject or remarks..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        <select name="subject">
            <option value="">All Subjects</option>
            <?php foreach ($subjects as $sub): ?>
                <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo $filterSubject == $sub ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sub); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="course">
            <option value="">All Courses</option>
            <?php foreach ($courses as $crs): ?>
                <option value="<?php echo htmlspecialchars($crs); ?>" <?php echo $filterCourse == $crs ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($crs); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="sort">
            <option value="newest" <?php echo $sortOrder=='newest'?'selected':''; ?>>Newest First</option>
            <option value="oldest" <?php echo $sortOrder=='oldest'?'selected':''; ?>>Oldest First</option>
            <option value="subject" <?php echo $sortOrder=='subject'?'selected':''; ?>>By Subject</option>
            <option value="title" <?php echo $sortOrder=='title'?'selected':''; ?>>By Title</option>
        </select>
        <button type="submit" class="filter-btn">
            <i class="fa-solid fa-filter"></i> Apply
        </button>
    </form>

    <?php if ($totalMaterials > 0): ?>
        <div class="books-grid">
            <?php foreach ($materials as $book): 
                $iconData = getFileIcon($book['file_path']);
                $fileName = !empty($book['file_name']) ? $book['file_name'] : basename($book['file_path']);
            ?>
                <div class="book-card">
                    <div class="upload-time"><?php echo timeAgo($book['uploaded_at']); ?></div>
                    
                    <div class="book-header">
                        <div class="book-icon">
                            <i class="fa-solid <?php echo $iconData[0]; ?>" style="color: <?php echo $iconData[1]; ?>"></i>
                        </div>
                        <div class="book-info">
                            <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                            <span class="subject-tag"><?php echo htmlspecialchars($book['subject']); ?></span>
                        </div>
                    </div>

                    <div class="book-meta">
                        <div><span>Course:</span> <strong><?php echo htmlspecialchars($book['course']); ?></strong></div>
                        <div><span>Year:</span> <strong>Year <?php echo $book['year']; ?></strong></div>
                        <div><span>Semester:</span> <strong><?php echo !empty($book['semester']) ? 'Sem ' . $book['semester'] : 'N/A'; ?></strong></div>
                        <div><span>File Size:</span> <strong><?php echo formatFileSize($book['file_size']); ?></strong></div>
                        <div><span>Uploaded:</span> <strong><?php echo date('M d, Y', strtotime($book['uploaded_at'])); ?></strong></div>
                    </div>

                    <?php if (!empty($book['remarks'])): ?>
                        <div class="book-remarks">
                            💬 <?php echo htmlspecialchars($book['remarks']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="book-actions">
                        <a href="<?php echo htmlspecialchars($book['file_path']); ?>" target="_blank" class="btn btn-view">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                        <a href="<?php echo htmlspecialchars($book['file_path']); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="btn btn-download">
                            <i class="fa-solid fa-download"></i> Download
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-result">
            <div class="no-result-icon"><i class="fa-solid fa-book-open"></i></div>
            <h2>No Books Available</h2>
            <p><?php echo !empty($searchTerm) || !empty($filterSubject) || !empty($filterCourse) 
                ? 'No materials match your search criteria. Try changing the filters.' 
                : 'No study materials have been published yet. Please check back later.'; ?></p>
            <?php if (!empty($searchTerm) || !empty($filterSubject) || !empty($filterCourse)): ?>
                <a href="view_books.php" style="display:inline-block;margin-top:20px;padding:10px 24px;background:var(--accent-color);color:white;text-decoration:none;border-radius:10px;font-weight:600;">
                    Clear Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</main>

</body>
</html>