<?php
session_start();

/* =========================================================
   1. AUTHENTICATION
========================================================= */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    header('Location: login.php');
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];

/* =========================================================
   2. DATABASE
========================================================= */
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=aureon;charset=utf8mb4",
        "root", "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed.");
}

/* =========================================================
   3. LANGUAGE
========================================================= */
$supportedLangs = ['en', 'kn'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLangs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'en';

$T = [
    'en' => [
        'app_name' => 'AUREON ERP',
        'parent_portal' => 'Parent Portal',
        'dashboard' => 'Dashboard',
        'request_teacher' => 'Request Teacher',
        'my_requests' => 'My Requests',
        'my_chats' => 'My Chats',
        'notifications' => 'Notifications',
        'logout' => 'Logout',
        'parent' => 'Parent',
        'student' => 'Student',
        'request_history' => 'Parent Request History',
        'request_history_desc' => 'Track all teacher communication requests and conversation status.',
        'total_requests' => 'Total Requests',
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'open' => 'Open',
        'closed' => 'Closed',
        'all' => 'All',
        'search_placeholder' => 'Search by teacher ID or student name...',
        'teacher_id' => 'Teacher ID',
        'student_name' => 'Student',
        'request_date' => 'Request Date',
        'last_updated' => 'Last Updated',
        'conversation' => 'Conversation',
        'open_chat' => 'Open Chat',
        'waiting_response' => 'Waiting for teacher response',
        'request_rejected' => 'Request Rejected',
        'conversation_closed' => 'Conversation Closed',
        'no_requests' => 'No Requests Yet',
        'no_requests_desc' => 'You have not sent any communication requests to teachers.',
        'no_search_results' => 'No matching requests found',
        'no_search_desc' => 'Try changing your filters or search terms.',
        'send_first_request' => 'Send Your First Request',
        'failed_load' => 'Failed to load requests',
        'message' => 'Message',
        'status' => 'Status',
        'view_chat' => 'View Chat',
        'just_now' => 'Just now',
        'min_ago' => 'min ago',
        'hour_ago' => 'hour ago',
        'hours_ago' => 'hours ago',
        'day_ago' => 'day ago',
        'days_ago' => 'days ago',
    ],
    'kn' => [
        'app_name' => 'ಔರಿಯನ್ ಇಆರ್‌ಪಿ',
        'parent_portal' => 'ಪೋಷಕರ ಪೋರ್ಟಲ್',
        'dashboard' => 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್',
        'request_teacher' => 'ಶಿಕ್ಷಕ ವಿನಂತಿ',
        'my_requests' => 'ನನ್ನ ವಿನಂತಿಗಳು',
        'my_chats' => 'ನನ್ನ ಚಾಟ್‌ಗಳು',
        'notifications' => 'ಅಧಿಸೂಚನೆಗಳು',
        'logout' => 'ಲಾಗ್‌ಔಟ್',
        'parent' => 'ಪೋಷಕ',
        'student' => 'ವಿದ್ಯಾರ್ಥಿ',
        'request_history' => 'ಪೋಷಕ ವಿನಂತಿ ಇತಿಹಾಸ',
        'request_history_desc' => 'ಎಲ್ಲಾ ಶಿಕ್ಷಕ ಸಂವಹನ ವಿನಂತಿಗಳು ಮತ್ತು ಸಂಭಾಷಣೆ ಸ್ಥಿತಿಯನ್ನು ಟ್ರ್ಯಾಕ್ ಮಾಡಿ.',
        'total_requests' => 'ಒಟ್ಟು ವಿನಂತಿಗಳು',
        'pending' => 'ಬಾಕಿ',
        'accepted' => 'ಸ್ವೀಕರಿಸಲಾಗಿದೆ',
        'rejected' => 'ತಿರಸ್ಕರಿಸಲಾಗಿದೆ',
        'open' => 'ತೆರೆದಿದೆ',
        'closed' => 'ಮುಚ್ಚಲಾಗಿದೆ',
        'all' => 'ಎಲ್ಲಾ',
        'search_placeholder' => 'ಶಿಕ್ಷಕ ID ಅಥವಾ ವಿದ್ಯಾರ್ಥಿ ಹೆಸರಿನಿಂದ ಹುಡುಕಿ...',
        'teacher_id' => 'ಶಿಕ್ಷಕ ID',
        'student_name' => 'ವಿದ್ಯಾರ್ಥಿ',
        'request_date' => 'ವಿನಂತಿ ದಿನಾಂಕ',
        'last_updated' => 'ಕೊನೆಯ ನವೀಕರಣ',
        'conversation' => 'ಸಂಭಾಷಣೆ',
        'open_chat' => 'ಚಾಟ್ ತೆರೆಯಿರಿ',
        'waiting_response' => 'ಶಿಕ್ಷಕರ ಪ್ರತಿಕ್ರಿಯೆಗಾಗಿ ಕಾಯಲಾಗುತ್ತಿದೆ',
        'request_rejected' => 'ವಿನಂತಿ ತಿರಸ್ಕರಿಸಲಾಗಿದೆ',
        'conversation_closed' => 'ಸಂಭಾಷಣೆ ಮುಚ್ಚಲಾಗಿದೆ',
        'no_requests' => 'ಯಾವುದೇ ವಿನಂತಿಗಳಿಲ್ಲ',
        'no_requests_desc' => 'ನೀವು ಶಿಕ್ಷಕರಿಗೆ ಯಾವುದೇ ಸಂವಹನ ವಿನಂತಿಗಳನ್ನು ಕಳುಹಿಸಿಲ್ಲ.',
        'no_search_results' => 'ಹೊಂದಾಣಿಕೆಯಾಗುವ ವಿನಂತಿಗಳಿಲ್ಲ',
        'no_search_desc' => 'ನಿಮ್ಮ ಫಿಲ್ಟರ್ ಅಥವಾ ಹುಡುಕಾಟ ಪದಗಳನ್ನು ಬದಲಾಯಿಸಲು ಪ್ರಯತ್ನಿಸಿ.',
        'send_first_request' => 'ನಿಮ್ಮ ಮೊದಲ ವಿನಂತಿ ಕಳುಹಿಸಿ',
        'failed_load' => 'ವಿನಂತಿಗಳನ್ನು ಲೋಡ್ ಮಾಡಲು ವಿಫಲವಾಗಿದೆ',
        'message' => 'ಸಂದೇಶ',
        'status' => 'ಸ್ಥಿತಿ',
        'view_chat' => 'ಚಾಟ್ ನೋಡಿ',
        'just_now' => 'ಈಗ',
        'min_ago' => 'ನಿಮಿಷಗಳ ಹಿಂದೆ',
        'hour_ago' => 'ಗಂಟೆ ಹಿಂದೆ',
        'hours_ago' => 'ಗಂಟೆಗಳ ಹಿಂದೆ',
        'day_ago' => 'ದಿನ ಹಿಂದೆ',
        'days_ago' => 'ದಿನಗಳ ಹಿಂದೆ',
    ]
];

function t($key) {
    global $T, $lang;
    return $T[$lang][$key] ?? $T['en'][$key] ?? $key;
}

/* =========================================================
   4. FETCH PARENT
========================================================= */
$stmt = $pdo->prepare("SELECT id, full_name, student_id FROM users WHERE id = ? AND role = 'parent' LIMIT 1");
$stmt->execute([$loggedInUserId]);
$parentData = $stmt->fetch();

if (!$parentData) {
    die("Parent account not configured.");
}

$parentName = $parentData['full_name'];
$parentStudentId = $parentData['student_id'] ?? '';

/* Student name */
$studentName = '';
if (!empty($parentStudentId)) {
    $s = $pdo->prepare("SELECT full_name FROM users WHERE student_id = ? AND role = 'student' LIMIT 1");
    $s->execute([$parentStudentId]);
    $studentRow = $s->fetch();
    if ($studentRow) $studentName = $studentRow['full_name'];
}

/* =========================================================
   5. AJAX HANDLER
========================================================= */
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            /* ----- FETCH ALL REQUESTS ----- */
            case 'fetch_requests':
                $filter = $_POST['filter'] ?? 'all';
                $search = trim($_POST['search'] ?? '');

                $sql = "
                    SELECT r.id, r.student_name, r.teacher_id AS teacher_code,
                           r.message, r.status, r.created_at, r.conversation_status,
                           u.full_name AS teacher_name,
                           (SELECT MAX(created_at) FROM teacher_parent_chat c 
                            WHERE c.request_id = r.id) AS last_activity,
                           (SELECT COUNT(*) FROM teacher_parent_chat c
                            WHERE c.request_id = r.id) AS message_count
                    FROM parent_teacher_requests r
                    LEFT JOIN users u ON u.reference_id = r.teacher_id AND u.role = 'teacher'
                    WHERE r.parent_name = ?
                ";
                $params = [$parentName];

                if ($filter !== 'all' && in_array($filter, ['Pending', 'Accepted', 'Rejected'])) {
                    $sql .= " AND r.status = ?";
                    $params[] = $filter;
                }

                if ($search !== '') {
                    $sql .= " AND (r.teacher_id LIKE ? OR r.student_name LIKE ? OR u.full_name LIKE ?)";
                    $like = "%$search%";
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }

                $sql .= " ORDER BY r.created_at DESC LIMIT 100";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $requests = $stmt->fetchAll();

                /* Stats */
                $statsStmt = $pdo->prepare("
                    SELECT status, COUNT(*) AS cnt
                    FROM parent_teacher_requests
                    WHERE parent_name = ?
                    GROUP BY status
                ");
                $statsStmt->execute([$parentName]);
                $stats = ['Pending' => 0, 'Accepted' => 0, 'Rejected' => 0, 'Total' => 0];
                foreach ($statsStmt->fetchAll() as $row) {
                    if (isset($stats[$row['status']])) {
                        $stats[$row['status']] = (int)$row['cnt'];
                    }
                    $stats['Total'] += (int)$row['cnt'];
                }

                echo json_encode([
                    'success' => true,
                    'requests' => $requests,
                    'stats' => $stats
                ]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$parentInitial = strtoupper(substr(trim($parentName), 0, 1));
$studentDisplay = $studentName ?: ($parentStudentId ?: '—');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('app_name')); ?> • <?php echo htmlspecialchars(t('my_requests')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-glass: rgba(255, 255, 255, 0.65);
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --accent: #6366f1;
            --indigo: #4f46e5;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --text-dark: #0f172a;
            --text-medium: #334155;
            --text-muted: #64748b;
            --border: rgba(148, 163, 184, 0.2);
            --border-light: rgba(255, 255, 255, 0.1);
            --shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.08);
            --shadow: 0 14px 40px rgba(37, 99, 235, 0.12);
            --shadow-lg: 0 20px 60px rgba(37, 99, 235, 0.2);
            --radius: 22px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
            margin: 0;
            background: 
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.12), transparent 35%),
                radial-gradient(circle at bottom right, rgba(99, 102, 241, 0.1), transparent 35%),
                linear-gradient(135deg, #eef2ff 0%, #e0e7ff 50%, #f0f9ff 100%);
        }

        body::before {
            content: '';
            position: fixed;
            top: -100px; right: -100px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15), transparent 70%);
            border-radius: 50%;
            z-index: 0;
            filter: blur(40px);
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -150px; left: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.12), transparent 70%);
            border-radius: 50%;
            z-index: 0;
            filter: blur(50px);
        }

        .layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

        /* ========== Sidebar ========== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(99, 102, 241, 0.2);
            position: fixed;
            inset: 0 auto 0 0;
            padding: 24px 18px;
            box-shadow: 8px 0 40px rgba(15, 23, 42, 0.25);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        .brand-card {
            display: flex; align-items: center; gap: 12px;
            padding: 16px;
            border-radius: var(--radius);
            background: rgba(99, 102, 241, 0.15);
            margin-bottom: 22px;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }
        .brand-icon {
            width: 48px; height: 48px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid; place-items: center;
            font-size: 1.2rem;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }
        .brand-title { font-weight: 800; font-size: 1.05rem; color: white; }
        .brand-sub { font-size: 0.78rem; color: #94a3b8; }

        .parent-mini-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(99, 102, 241, 0.15));
            padding: 14px;
            border-radius: 16px;
            margin-bottom: 16px;
            text-align: center;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        .parent-mini-card .pmc-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #a5b4fc;
            margin-bottom: 4px;
            letter-spacing: 0.6px;
        }
        .parent-mini-card .pmc-name {
            font-weight: 800;
            color: white;
            font-size: 0.92rem;
        }
        .parent-mini-card .pmc-student {
            font-size: 0.72rem;
            color: #cbd5e1;
            margin-top: 4px;
        }

        .nav-link-premium {
            display: flex; align-items: center; gap: 12px;
            padding: 13px 16px;
            border-radius: 14px;
            color: #cbd5e1;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.25s ease;
            margin-bottom: 6px;
            font-size: 0.92rem;
        }
        .nav-link-premium:hover {
            background: rgba(99, 102, 241, 0.15);
            transform: translateX(4px);
            color: white;
        }
        .nav-link-premium.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(99, 102, 241, 0.2));
            color: white;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
            border: 1px solid rgba(99, 102, 241, 0.4);
        }

        .logout-btn {
            margin-top: auto;
            display: flex; align-items: center; gap: 12px;
            padding: 13px 16px;
            border-radius: 14px;
            color: #fca5a5;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.25s ease;
            font-size: 0.92rem;
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            transform: translateX(4px);
            color: #fecaca;
        }

        /* ========== Main ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 14px 22px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-title {
            font-size: 1.35rem; font-weight: 800;
            display: flex; align-items: center; gap: 12px;
            color: var(--text-dark);
        }
        .page-title i { 
            color: var(--primary); 
            background: rgba(37, 99, 235, 0.1);
            padding: 8px;
            border-radius: 12px;
            font-size: 1.2rem;
        }
        .topbar-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

        .lang-switcher {
            display: flex;
            background: white;
            border-radius: 999px;
            padding: 4px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }
        .lang-switcher a {
            padding: 6px 14px;
            border-radius: 999px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.78rem;
            font-weight: 800;
            transition: all 0.2s ease;
        }
        .lang-switcher a.active {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
        }

        .profile-chip {
            display: flex; align-items: center; gap: 12px;
            padding: 6px 14px 6px 6px;
            background: white;
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid; place-items: center;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .profile-chip .name { font-weight: 700; font-size: 0.88rem; color: var(--text-dark); line-height: 1.2; }
        .profile-chip .student-info { font-size: 0.7rem; color: var(--text-muted); }

        /* ========== Hero ========== */
        .hero-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 26px 30px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .hero-icon {
            width: 68px; height: 68px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid;
            place-items: center;
            font-size: 1.8rem;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.3);
            flex-shrink: 0;
        }
        .hero-content { flex: 1; min-width: 220px; }
        .hero-content h2 {
            font-weight: 800;
            font-size: 1.45rem;
            margin: 0 0 4px;
            color: var(--text-dark);
        }
        .hero-content p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.92rem;
        }

        /* ========== Stats Grid ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            border-left: 4px solid transparent;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }
        .stat-card.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), var(--bg-glass));
        }
        .stat-card.active.stat-total { border-left-color: var(--primary); }
        .stat-card.active.stat-pending { border-left-color: var(--warning); }
        .stat-card.active.stat-accepted { border-left-color: var(--success); }
        .stat-card.active.stat-rejected { border-left-color: var(--danger); }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
        }
        .stat-total .stat-icon { background: linear-gradient(135deg, var(--primary), var(--accent)); }
        .stat-pending .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-accepted .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-rejected .stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-info h3 {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
            line-height: 1;
            color: var(--text-dark);
        }
        .stat-info p {
            color: var(--text-muted);
            margin: 4px 0 0;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* ========== Filter Bar ========== */
        .filter-bar {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 240px;
        }
        .search-wrap i {
            position: absolute;
            top: 50%; left: 14px;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .search-input {
            width: 100%;
            padding: 11px 16px 11px 42px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
            color: var(--text-dark);
        }
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .refresh-btn {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        }

        /* ========== Request Cards ========== */
        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .request-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-left: 4px solid transparent;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s ease;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.1);
        }
        .request-card.status-Pending { border-left-color: var(--warning); }
        .request-card.status-Accepted { border-left-color: var(--success); }
        .request-card.status-Rejected { border-left-color: var(--danger); }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .card-top-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 0;
        }
        .teacher-avatar {
            width: 56px; height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid; place-items: center;
            font-weight: 800;
            font-size: 1.3rem;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
        }
        .teacher-meta { flex: 1; min-width: 0; }
        .teacher-id-pill {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-dark);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.3px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .teacher-name-display {
            font-weight: 800;
            color: var(--text-dark);
            font-size: 1.02rem;
            margin-bottom: 4px;
        }
        .student-info-line {
            color: var(--text-muted);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .student-info-line strong {
            color: var(--text-medium);
            font-weight: 700;
        }
        .request-date {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: right;
            white-space: nowrap;
        }
        .request-date strong {
            display: block;
            color: var(--text-medium);
            font-weight: 700;
            margin-top: 2px;
            font-size: 0.85rem;
        }

        .card-message {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            margin: 14px 0;
            color: var(--text-medium);
            line-height: 1.6;
            font-size: 0.92rem;
            position: relative;
            padding-left: 44px;
        }
        .card-message::before {
            content: '\F303';
            font-family: 'bootstrap-icons';
            position: absolute;
            top: 14px; left: 16px;
            color: var(--primary);
            font-size: 1.1rem;
            opacity: 0.6;
        }

        .card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }
        .card-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .status-badge {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.3px;
        }
        .status-Pending { background: var(--warning-light); color: #92400e; }
        .status-Accepted { background: var(--success-light); color: #065f46; }
        .status-Rejected { background: var(--danger-light); color: #991b1b; }

        .conv-badge {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.3px;
        }
        .conv-Open {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(99, 102, 241, 0.1));
            color: var(--primary-dark);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }
        .conv-Closed {
            background: rgba(100, 116, 139, 0.12);
            color: #475569;
            border: 1px solid rgba(100, 116, 139, 0.2);
        }
        .conv-Open::before, .conv-Closed::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .conv-Open::before { background: var(--success); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); animation: pulse-green 2s infinite; }
        .conv-Closed::before { background: #94a3b8; }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }
        .btn-action {
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-open-chat {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .btn-open-chat:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
            color: white;
        }
        .btn-waiting {
            background: var(--warning-light);
            color: #92400e;
            cursor: not-allowed;
            border: 1px dashed var(--warning);
        }
        .btn-rejected {
            background: var(--danger-light);
            color: #991b1b;
            cursor: not-allowed;
            border: 1px dashed var(--danger);
        }
        .btn-closed {
            background: rgba(100, 116, 139, 0.12);
            color: #475569;
            cursor: not-allowed;
            border: 1px dashed #94a3b8;
        }

        /* ========== Empty State ========== */
        .empty-state {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 70px 30px;
            text-align: center;
            box-shadow: var(--shadow);
        }
        .empty-icon {
            width: 110px;
            height: 110px;
            border-radius: 28px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid;
            place-items: center;
            margin: 0 auto 22px;
            box-shadow: 0 20px 50px rgba(37, 99, 235, 0.3);
            font-size: 2.8rem;
        }
        .empty-state h3 {
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 10px;
            font-size: 1.4rem;
        }
        .empty-state p {
            color: var(--text-muted);
            max-width: 380px;
            margin: 0 auto 20px;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        .empty-state .empty-action {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
            transition: all 0.2s ease;
        }
        .empty-state .empty-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.4);
            color: white;
        }

        /* ========== Loading ========== */
        .loading-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .loading-state i {
            font-size: 2.5rem;
            color: var(--primary);
            opacity: 0.5;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ========== Toast ========== */
        .toast-container {
            position: fixed;
            top: 24px; right: 24px;
            z-index: 9999;
        }
        .custom-toast {
            background: white;
            border-radius: 14px;
            padding: 14px 18px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.15);
            margin-bottom: 12px;
            display: flex; align-items: center; gap: 12px;
            min-width: 280px;
            border-left: 4px solid var(--primary);
            animation: slideInRight 0.3s ease;
        }
        .custom-toast.success { border-color: var(--success); }
        .custom-toast.error { border-color: var(--danger); }
        .custom-toast .toast-icon { font-size: 1.3rem; }
        .custom-toast.success .toast-icon { color: var(--success); }
        .custom-toast.error .toast-icon { color: var(--danger); }
        .custom-toast .toast-content { font-weight: 600; font-size: 0.9rem; color: var(--text-dark); }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeOut { to { opacity: 0; transform: translateX(40px); } }

        /* ========== Mobile ========== */
        .mobile-toggle {
            display: none;
            background: white;
            border: 1px solid var(--border);
            width: 42px; height: 42px;
            border-radius: 12px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            color: var(--text-dark);
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .mobile-toggle { display: block; }
            .hero-card { padding: 20px; }
            .hero-content h2 { font-size: 1.2rem; }
        }
        @media (max-width: 600px) {
            .topbar { padding: 12px; }
            .topbar-actions { gap: 8px; }
            .lang-switcher a { padding: 5px 10px; font-size: 0.72rem; }
            .profile-chip .name, .profile-chip .student-info { display: none; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-icon { width: 42px; height: 42px; font-size: 1.1rem; }
            .stat-info h3 { font-size: 1.4rem; }
            .request-card { padding: 16px; }
            .teacher-avatar { width: 48px; height: 48px; font-size: 1.1rem; }
            .request-date { text-align: left; }
            .card-bottom { flex-direction: column; align-items: stretch; }
            .card-actions { width: 100%; }
            .btn-action { flex: 1; justify-content: center; }
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.3); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    </style>
</head>
<body>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-card">
            <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div>
                <div class="brand-title"><?php echo htmlspecialchars(t('app_name')); ?></div>
                <div class="brand-sub"><?php echo htmlspecialchars(t('parent_portal')); ?></div>
            </div>
        </div>

        <div class="parent-mini-card">
            <div class="pmc-label"><?php echo htmlspecialchars(t('parent')); ?></div>
            <div class="pmc-name"><?php echo htmlspecialchars($parentName); ?></div>
            <?php if (!empty($studentDisplay) && $studentDisplay !== '—'): ?>
                <div class="pmc-student">
                    <i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($studentDisplay); ?>
                </div>
            <?php endif; ?>
        </div>

        <nav>
            <a href="parent_dash.php" class="nav-link-premium">
                <i class="bi bi-grid-1x2-fill"></i> <?php echo htmlspecialchars(t('dashboard')); ?>
            </a>
            
            </a>
            <a href="parent_requests.php" class="nav-link-premium active">
                <i class="bi bi-list-check"></i> <?php echo htmlspecialchars(t('my_requests')); ?>
            </a>
            <a href="parent_chat.php" class="nav-link-premium">
                <i class="bi bi-chat-dots-fill"></i> <?php echo htmlspecialchars(t('my_chats')); ?>
            </a>
            
        </nav>

        <a href="logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> <?php echo htmlspecialchars(t('logout')); ?>
        </a>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
                    <i class="bi bi-list"></i>
                </button>
                <div class="page-title">
                    <i class="bi bi-list-check"></i>
                    <?php echo htmlspecialchars(t('my_requests')); ?>
                </div>
            </div>
            <div class="topbar-actions">
                <div class="lang-switcher">
                    <a href="?lang=en" class="<?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
                    <a href="?lang=kn" class="<?php echo $lang === 'kn' ? 'active' : ''; ?>">ಕನ್</a>
                </div>
                <div class="profile-chip">
                    <div class="avatar"><?php echo htmlspecialchars($parentInitial); ?></div>
                    <div>
                        <div class="name"><?php echo htmlspecialchars($parentName); ?></div>
                        <div class="student-info">
                            <?php echo htmlspecialchars(t('student')); ?>: <?php echo htmlspecialchars($studentDisplay); ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <div class="hero-card">
            <div class="hero-icon">
                <i class="bi bi-clipboard2-check-fill"></i>
            </div>
            <div class="hero-content">
                <h2><?php echo htmlspecialchars(t('request_history')); ?></h2>
                <p><?php echo htmlspecialchars(t('request_history_desc')); ?></p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-total active" data-filter="all" onclick="setFilter('all', this)">
                <div class="stat-icon"><i class="bi bi-collection-fill"></i></div>
                <div class="stat-info">
                    <h3 id="statTotal">0</h3>
                    <p><?php echo htmlspecialchars(t('total_requests')); ?></p>
                </div>
            </div>
            <div class="stat-card stat-pending" data-filter="Pending" onclick="setFilter('Pending', this)">
                <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                <div class="stat-info">
                    <h3 id="statPending">0</h3>
                    <p><?php echo htmlspecialchars(t('pending')); ?></p>
                </div>
            </div>
            <div class="stat-card stat-accepted" data-filter="Accepted" onclick="setFilter('Accepted', this)">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-info">
                    <h3 id="statAccepted">0</h3>
                    <p><?php echo htmlspecialchars(t('accepted')); ?></p>
                </div>
            </div>
            <div class="stat-card stat-rejected" data-filter="Rejected" onclick="setFilter('Rejected', this)">
                <div class="stat-icon"><i class="bi bi-x-circle-fill"></i></div>
                <div class="stat-info">
                    <h3 id="statRejected">0</h3>
                    <p><?php echo htmlspecialchars(t('rejected')); ?></p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" 
                       class="search-input" 
                       id="searchInput" 
                       placeholder="<?php echo htmlspecialchars(t('search_placeholder')); ?>">
            </div>
            <button class="refresh-btn" onclick="loadRequests()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>

        <!-- Requests Container -->
        <div id="requestsContainer">
            <div class="loading-state">
                <i class="bi bi-arrow-clockwise"></i>
            </div>
        </div>
    </main>
</div>

<!-- Toast -->
<div class="toast-container" id="toastContainer"></div>

<script>
const T = <?php echo json_encode($T[$lang]); ?>;
let currentFilter = 'all';
let searchTimer = null;
let refreshInterval = null;

/* ========== AJAX ========== */
async function api(action, data = {}) {
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', action);
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    try {
        const r = await fetch('', { method: 'POST', body: fd });
        return await r.json();
    } catch (err) {
        return { success: false, message: T.failed_load };
    }
}

/* ========== Toast ========== */
function showToast(message, type = 'success') {
    const t = document.createElement('div');
    t.className = `custom-toast ${type}`;
    const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill';
    t.innerHTML = `
        <i class="bi bi-${icon} toast-icon"></i>
        <div class="toast-content">${escapeHtml(message)}</div>
    `;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => {
        t.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => t.remove(), 300);
    }, 3000);
}

/* ========== Helpers ========== */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function timeAgo(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return T.just_now;
    if (diff < 3600) return Math.floor(diff/60) + ' ' + T.min_ago;
    if (diff < 7200) return '1 ' + T.hour_ago;
    if (diff < 86400) return Math.floor(diff/3600) + ' ' + T.hours_ago;
    if (diff < 172800) return '1 ' + T.day_ago;
    if (diff < 604800) return Math.floor(diff/86400) + ' ' + T.days_ago;
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString([], { 
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

/* ========== Filter ========== */
function setFilter(filter, el) {
    currentFilter = filter;
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    loadRequests();
}

document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadRequests, 300);
});

/* ========== Load Requests ========== */
async function loadRequests() {
    const search = document.getElementById('searchInput').value.trim();
    const out = await api('fetch_requests', { 
        filter: currentFilter, 
        search: search 
    });

    if (!out.success) {
        showToast(T.failed_load, 'error');
        return;
    }

    /* Update stats */
    document.getElementById('statTotal').textContent = out.stats.Total;
    document.getElementById('statPending').textContent = out.stats.Pending;
    document.getElementById('statAccepted').textContent = out.stats.Accepted;
    document.getElementById('statRejected').textContent = out.stats.Rejected;

    const container = document.getElementById('requestsContainer');

    if (!out.requests || out.requests.length === 0) {
        const isFiltering = currentFilter !== 'all' || search !== '';
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-${isFiltering ? 'search' : 'inbox'}"></i>
                </div>
                <h3>${escapeHtml(isFiltering ? T.no_search_results : T.no_requests)}</h3>
                <p>${escapeHtml(isFiltering ? T.no_search_desc : T.no_requests_desc)}</p>
                ${!isFiltering ? `
                    <a href="parent_request.php" class="empty-action">
                        <i class="bi bi-send-plus-fill"></i>
                        ${escapeHtml(T.send_first_request)}
                    </a>
                ` : ''}
            </div>`;
        return;
    }

    container.innerHTML = `<div class="requests-list">${out.requests.map(r => renderCard(r)).join('')}</div>`;
}

/* ========== Render Card ========== */
function renderCard(r) {
    const teacherName = r.teacher_name || 'Teacher';
    const initial = teacherName.charAt(0).toUpperCase();
    const lastActivity = r.last_activity || r.created_at;
    const convStatus = r.conversation_status || 'Active';
    
    /* Determine display conversation status */
   let convDisplay = 'Closed';

if (r.status === 'Accepted' && convStatus === 'Open') {
    convDisplay = 'Open';
}
else if (r.status === 'Accepted' && convStatus === 'Closed') {
    convDisplay = 'Closed';
}
    /* Action button */
    let actionBtn = '';
    if (r.status === 'Accepted' && convStatus === 'Open') {
        actionBtn = `
            <a href="parent_chat.php?request_id=${r.id}" class="btn-action btn-open-chat">
                <i class="bi bi-chat-dots-fill"></i> ${escapeHtml(T.open_chat)}
            </a>`;
    } else if (r.status === 'Accepted' && convStatus === 'Closed') {
        actionBtn = `
            <a href="parent_chat.php?request_id=${r.id}" class="btn-action btn-open-chat" style="opacity:0.8;">
                <i class="bi bi-eye-fill"></i> ${escapeHtml(T.view_chat)}
            </a>`;
    } else if (r.status === 'Pending') {
        actionBtn = `
            <button class="btn-action btn-waiting" disabled>
                <i class="bi bi-clock-history"></i> ${escapeHtml(T.waiting_response)}
            </button>`;
    } else if (r.status === 'Rejected') {
        actionBtn = `
            <button class="btn-action btn-rejected" disabled>
                <i class="bi bi-x-circle-fill"></i> ${escapeHtml(T.request_rejected)}
            </button>`;
    }

    return `
        <div class="request-card status-${r.status}">
            <div class="card-top">
                <div class="card-top-left">
                    <div class="teacher-avatar">${escapeHtml(initial)}</div>
                    <div class="teacher-meta">
                        <div class="teacher-id-pill">
                            <i class="bi bi-hash"></i>${escapeHtml(r.teacher_code)}
                        </div>
                        <div class="teacher-name-display">${escapeHtml(teacherName)}</div>
                        <div class="student-info-line">
                            <i class="bi bi-person-fill"></i>
                            ${escapeHtml(T.student_name)}: <strong>${escapeHtml(r.student_name)}</strong>
                        </div>
                    </div>
                </div>
                <div class="request-date">
                    ${escapeHtml(T.request_date)}
                    <strong>${formatDate(r.created_at)}</strong>
                </div>
            </div>

            <div class="card-message">${escapeHtml(r.message)}</div>

            <div class="card-bottom">
                <div class="card-badges">
                    <span class="status-badge status-${r.status}">
                        <i class="bi bi-${r.status === 'Pending' ? 'clock-fill' : r.status === 'Accepted' ? 'check-circle-fill' : 'x-circle-fill'}"></i>
                        ${escapeHtml(T[r.status.toLowerCase()] || r.status)}
                    </span>
                    ${r.status === 'Accepted' ? `
                        <span class="conv-badge conv-${convDisplay}">
                            ${escapeHtml(T.conversation)}: ${escapeHtml(T[convDisplay.toLowerCase()] || convDisplay)}
                        </span>
                    ` : ''}
                </div>
                <div class="card-actions">
                    ${actionBtn}
                </div>
            </div>
        </div>`;
}

/* ========== Init ========== */
loadRequests();
refreshInterval = setInterval(loadRequests, 8000);

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) loadRequests();
});
</script>
</body>
</html>