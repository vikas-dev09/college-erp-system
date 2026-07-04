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
        'parent_chat' => 'Parent Chat',
        'dashboard' => 'Dashboard',
        'request_teacher' => 'Request Teacher',
        'my_chats' => 'My Chats',
        'notifications' => 'Notifications',
        'logout' => 'Logout',
        'chat_with_teachers' => 'Chat with Teachers',
        'accepted_teachers' => 'Accepted Teachers',
        'only_accepted_chats' => 'Only your accepted teachers appear here',
        'search_teacher' => 'Search teacher...',
        'select_teacher' => 'Select a Teacher',
        'select_teacher_to_chat' => 'Select a teacher to start conversation',
        'no_accepted_teachers' => 'No accepted teachers yet',
        'send_request_first' => 'Send a teacher request and wait for acceptance.',
        'no_messages_yet' => 'No messages yet — say hello!',
        'type_message' => 'Type your message...',
        'teacher' => 'Teacher',
        'teacher_id' => 'Teacher ID',
        'student' => 'Student',
        'student_id' => 'Student ID',
        'active' => 'Active',
        'ended' => 'Ended',
        'online' => 'Online',
        'conversation_ended' => 'This conversation has been ended by the teacher.',
        'send' => 'Send',
        'failed_to_load' => 'Failed to load',
        'failed_to_send' => 'Failed to send',
        'cannot_send' => 'Cannot send message in ended conversation',
        'parent' => 'Parent',
        'access_denied' => 'Access denied',
        'language' => 'Language',
        'live' => 'Live',
    ],
    'kn' => [
        'app_name' => 'ಔರಿಯನ್ ಇಆರ್‌ಪಿ',
        'parent_portal' => 'ಪೋಷಕರ ಪೋರ್ಟಲ್',
        'parent_chat' => 'ಪೋಷಕ ಚಾಟ್',
        'dashboard' => 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್',
        'request_teacher' => 'ಶಿಕ್ಷಕ ವಿನಂತಿ',
        'my_chats' => 'ನನ್ನ ಚಾಟ್‌ಗಳು',
        'notifications' => 'ಅಧಿಸೂಚನೆಗಳು',
        'logout' => 'ಲಾಗ್‌ಔಟ್',
        'chat_with_teachers' => 'ಶಿಕ್ಷಕರೊಂದಿಗೆ ಚಾಟ್',
        'accepted_teachers' => 'ಸ್ವೀಕರಿಸಿದ ಶಿಕ್ಷಕರು',
        'only_accepted_chats' => 'ನಿಮ್ಮ ಸ್ವೀಕರಿಸಿದ ಶಿಕ್ಷಕರು ಮಾತ್ರ ಇಲ್ಲಿ ಕಾಣಿಸುತ್ತಾರೆ',
        'search_teacher' => 'ಶಿಕ್ಷಕರನ್ನು ಹುಡುಕಿ...',
        'select_teacher' => 'ಶಿಕ್ಷಕರನ್ನು ಆಯ್ಕೆಮಾಡಿ',
        'select_teacher_to_chat' => 'ಸಂಭಾಷಣೆ ಪ್ರಾರಂಭಿಸಲು ಶಿಕ್ಷಕರನ್ನು ಆಯ್ಕೆಮಾಡಿ',
        'no_accepted_teachers' => 'ಯಾವುದೇ ಸ್ವೀಕರಿಸಿದ ಶಿಕ್ಷಕರಿಲ್ಲ',
        'send_request_first' => 'ಶಿಕ್ಷಕರ ವಿನಂತಿ ಕಳುಹಿಸಿ ಮತ್ತು ಸ್ವೀಕಾರಕ್ಕಾಗಿ ಕಾಯಿರಿ.',
        'no_messages_yet' => 'ಯಾವುದೇ ಸಂದೇಶಗಳಿಲ್ಲ — ಹಲೋ ಎನ್ನಿ!',
        'type_message' => 'ನಿಮ್ಮ ಸಂದೇಶವನ್ನು ಬರೆಯಿರಿ...',
        'teacher' => 'ಶಿಕ್ಷಕ',
        'teacher_id' => 'ಶಿಕ್ಷಕ ಐಡಿ',
        'student' => 'ವಿದ್ಯಾರ್ಥಿ',
        'student_id' => 'ವಿದ್ಯಾರ್ಥಿ ಐಡಿ',
        'active' => 'ಸಕ್ರಿಯ',
        'ended' => 'ಮುಗಿದಿದೆ',
        'online' => 'ಆನ್‌ಲೈನ್',
        'conversation_ended' => 'ಈ ಸಂಭಾಷಣೆಯನ್ನು ಶಿಕ್ಷಕರು ಮುಗಿಸಿದ್ದಾರೆ.',
        'send' => 'ಕಳುಹಿಸಿ',
        'failed_to_load' => 'ಲೋಡ್ ವಿಫಲವಾಗಿದೆ',
        'failed_to_send' => 'ಕಳುಹಿಸಲು ವಿಫಲವಾಗಿದೆ',
        'cannot_send' => 'ಮುಗಿದ ಸಂಭಾಷಣೆಯಲ್ಲಿ ಸಂದೇಶ ಕಳುಹಿಸಲು ಸಾಧ್ಯವಿಲ್ಲ',
        'parent' => 'ಪೋಷಕ',
        'access_denied' => 'ಪ್ರವೇಶ ನಿರಾಕರಿಸಲಾಗಿದೆ',
        'language' => 'ಭಾಷೆ',
        'live' => 'ಲೈವ್',
    ]
];

function t($key) {
    global $T, $lang;
    return $T[$lang][$key] ?? $T['en'][$key] ?? $key;
}

/* =========================================================
   4. FETCH PARENT DETAILS
========================================================= */
$stmt = $pdo->prepare("SELECT id, full_name, student_id FROM users WHERE id = ? AND role = 'parent' LIMIT 1");
$stmt->execute([$loggedInUserId]);
$parentData = $stmt->fetch();

if (!$parentData) {
    die("Parent account not configured properly.");
}

$parentName = $parentData['full_name'];
$parentStudentId = $parentData['student_id'] ?? '';

/* Fetch student name (if exists in users table) */
$studentName = '';
if (!empty($parentStudentId)) {
    $stmt2 = $pdo->prepare("SELECT full_name FROM users WHERE student_id = ? AND role = 'student' LIMIT 1");
    $stmt2->execute([$parentStudentId]);
    $studentRow = $stmt2->fetch();
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

            /* ----- FETCH ACCEPTED TEACHERS ----- */
            case 'fetch_teachers':
                $search = trim($_POST['search'] ?? '');

                $sql = "
                    SELECT r.id AS request_id, r.student_name, r.parent_name,
                           r.teacher_id AS teacher_code, r.status, r.created_at, 
                           r.conversation_status,
                           u.full_name AS teacher_name,
                           (SELECT COUNT(*) FROM teacher_parent_chat c
                            WHERE c.request_id = r.id) AS message_count,
                           (SELECT message FROM teacher_parent_chat c
                            WHERE c.request_id = r.id
                            ORDER BY c.id DESC LIMIT 1) AS last_message,
                           (SELECT sender_role FROM teacher_parent_chat c
                            WHERE c.request_id = r.id
                            ORDER BY c.id DESC LIMIT 1) AS last_sender_role,
                           (SELECT created_at FROM teacher_parent_chat c
                            WHERE c.request_id = r.id
                            ORDER BY c.id DESC LIMIT 1) AS last_message_time
                    FROM parent_teacher_requests r
                   LEFT JOIN users u 
    ON u.reference_id = r.teacher_id AND u.role = 'teacher'
                    WHERE r.parent_name = ? AND r.status = 'Accepted'
                ";
                $params = [$parentName];

                if ($search !== '') {
                    $sql .= " AND (u.full_name LIKE ? OR r.teacher_id LIKE ? OR r.student_name LIKE ?)";
                    $like = "%$search%";
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }

                $sql .= " ORDER BY COALESCE(last_message_time, r.created_at) DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                echo json_encode([
                    'success' => true,
                    'teachers' => $stmt->fetchAll()
                ]);
                break;

            /* ----- FETCH MESSAGES ----- */
            case 'fetch_messages':
                $requestId = (int)($_POST['request_id'] ?? 0);
                $lastId = (int)($_POST['last_id'] ?? 0);

                if ($requestId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid request']);
                    exit;
                }

                /* Security: only owner parent can access */
                $check = $pdo->prepare("
                    SELECT r.id, r.student_name, r.parent_name, r.teacher_id,
                           r.status, r.conversation_status,
                           u.full_name AS teacher_name
                    FROM parent_teacher_requests r
                    LEFT JOIN users u ON u.reference_id = r.teacher_id AND u.role = 'teacher'
                    WHERE r.id = ? AND r.parent_name = ? AND r.status = 'Accepted'
                    LIMIT 1
                ");
                $check->execute([$requestId, $parentName]);
                $reqInfo = $check->fetch();

                if (!$reqInfo) {
                    echo json_encode(['success' => false, 'message' => t('access_denied')]);
                    exit;
                }

                $sql = "
                    SELECT id, sender_role, sender_name, message, created_at
                    FROM teacher_parent_chat
                    WHERE request_id = ?
                ";
                $params = [$requestId];

                if ($lastId > 0) {
                    $sql .= " AND id > ?";
                    $params[] = $lastId;
                }

                $sql .= " ORDER BY id ASC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                echo json_encode([
                    'success' => true,
                    'messages' => $stmt->fetchAll(),
                    'request_info' => $reqInfo
                ]);
                break;

            /* ----- SEND MESSAGE ----- */
            case 'send_message':
                $requestId = (int)($_POST['request_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');

                if ($requestId <= 0 || $message === '') {
                    echo json_encode(['success' => false, 'message' => 'Invalid data']);
                    exit;
                }

                /* Security check */
                $check = $pdo->prepare("
                    SELECT id, student_name, parent_name, teacher_id, conversation_status
                    FROM parent_teacher_requests
                    WHERE id = ? AND parent_name = ? AND status = 'Accepted'
                    LIMIT 1
                ");
                $check->execute([$requestId, $parentName]);
                $reqInfo = $check->fetch();

                if (!$reqInfo) {
                    echo json_encode(['success' => false, 'message' => t('access_denied')]);
                    exit;
                }

                if (($reqInfo['conversation_status'] ?? 'Active') === 'Ended') {
                    echo json_encode([
                        'success' => false,
                        'message' => t('cannot_send')
                    ]);
                    exit;
                }

                $insert = $pdo->prepare("
                    INSERT INTO teacher_parent_chat
                    (request_id, sender_role, sender_name, teacher_id, student_id, message, created_at)
                    VALUES (?, 'parent', ?, ?, ?, ?, NOW())
                ");
                $ok = $insert->execute([
                    $requestId,
                    $parentName,
                    $reqInfo['teacher_id'],
                    $reqInfo['student_name'],
                    $message
                ]);

                if ($ok) {
                    echo json_encode([
                        'success' => true,
                        'message_id' => $pdo->lastInsertId()
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => t('failed_to_send')]);
                }
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
    <title><?php echo htmlspecialchars(t('app_name')); ?> • <?php echo htmlspecialchars(t('parent_chat')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-glass: rgba(255, 255, 255, 0.65);
            --bg-glass-dark: rgba(15, 23, 42, 0.88);
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --accent: #6366f1;
            --indigo: #4f46e5;
            --text-dark: #0f172a;
            --text-medium: #334155;
            --text-muted: #64748b;
            --text-white: #f8fafc;
            --border: rgba(148, 163, 184, 0.2);
            --border-light: rgba(255, 255, 255, 0.1);
            --shadow-sm: 0 4px 12px rgba(15, 23, 42, 0.08);
            --shadow: 0 14px 40px rgba(37, 99, 235, 0.12);
            --shadow-lg: 0 20px 60px rgba(37, 99, 235, 0.2);
            --radius: 22px;
            --radius-lg: 28px;
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

        /* Floating decoration */
        body::before {
            content: '';
            position: fixed;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15), transparent 70%);
            border-radius: 50%;
            z-index: 0;
            filter: blur(40px);
        }
        body::after {
            content: '';
            position: fixed;
            bottom: -150px;
            left: -100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.12), transparent 70%);
            border-radius: 50%;
            z-index: 0;
            filter: blur(50px);
        }

        .layout { display: flex; min-height: 100vh; position: relative; z-index: 1; }

        /* ============ Sidebar (dark blue glass) ============ */
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

        /* ============ Main ============ */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ============ Topbar (white+blue glass) ============ */
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

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
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

        /* ============ Chat Layout ============ */
        .chat-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 18px;
            flex: 1;
            min-height: 0;
        }

        /* ============ Teachers Panel ============ */
        .teachers-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), transparent);
        }
        .panel-header h3 {
            margin: 0 0 4px;
            font-weight: 800;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dark);
        }
        .panel-header h3 i { color: var(--primary); }
        .panel-header p { margin: 0; color: var(--text-muted); font-size: 0.8rem; }

        .panel-search {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.4);
        }
        .search-input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
            font-family: inherit;
            font-size: 0.88rem;
            outline: none;
            transition: all 0.2s ease;
            color: var(--text-dark);
        }
        .search-input:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .search-wrap { position: relative; }
        .search-wrap i {
            position: absolute;
            top: 50%; left: 14px;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .teachers-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
            min-height: 0;
        }
        .teacher-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            border: 1.5px solid transparent;
            position: relative;
            background: rgba(255, 255, 255, 0.6);
        }
        .teacher-item:hover { 
            background: rgba(255, 255, 255, 0.9); 
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        }
        .teacher-item.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(99, 102, 241, 0.08));
            border-color: var(--primary);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.15);
        }
        .teacher-avatar {
            width: 48px; height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid; place-items: center;
            font-weight: 800; font-size: 1.05rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            position: relative;
        }
        .teacher-avatar::after {
            content: '';
            position: absolute;
            bottom: -2px; right: -2px;
            width: 12px; height: 12px;
            background: #10b981;
            border: 2px solid white;
            border-radius: 50%;
        }
        .teacher-info {
            flex: 1;
            min-width: 0;
        }
        .teacher-name {
            font-weight: 800;
            font-size: 0.92rem;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-dark);
        }
        .teacher-meta {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .teacher-id-tag {
            font-size: 0.66rem;
            color: var(--primary-dark);
            background: rgba(37, 99, 235, 0.1);
            padding: 2px 7px;
            border-radius: 999px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .teacher-preview {
            font-size: 0.76rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .teacher-time {
            font-size: 0.68rem;
            color: var(--text-muted);
            margin-top: 3px;
            opacity: 0.8;
        }
        .badge-status {
            position: absolute;
            top: 8px; right: 8px;
            font-size: 0.6rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            letter-spacing: 0.5px;
        }
        .badge-active { color: #047857; background: #d1fae5; }
        .badge-ended { color: #b91c1c; background: #fee2e2; }

        /* ============ Chat Panel ============ */
        .chat-panel {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        .chat-header {
            padding: 16px 22px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(255, 255, 255, 0.4));
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .chat-header-avatar {
            width: 52px; height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid; place-items: center;
            font-weight: 800;
            font-size: 1.2rem;
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }
        .chat-header-avatar::after {
            content: '';
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 13px;
            height: 13px;
            background: #10b981;
            border: 2px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6);
            animation: pulse-green 2s infinite;
        }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .chat-header-info { flex: 1; min-width: 0; }
        .chat-header-info h4 {
            margin: 0;
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--text-dark);
        }
        .chat-header-info p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .online-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #10b981;
            font-weight: 700;
            font-size: 0.76rem;
        }
        .online-status::before {
            content: '';
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
        }
        .header-id-pill {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-dark);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .conv-status-pill {
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
        }
        .conv-status-active { background: #d1fae5; color: #047857; }
        .conv-status-ended { background: #fee2e2; color: #b91c1c; }

        /* ============ Messages ============ */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.5), rgba(240, 249, 255, 0.4));
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 0;
        }

        .message {
            max-width: 72%;
            padding: 12px 16px;
            border-radius: 18px;
            animation: msgFade 0.25s ease;
            word-wrap: break-word;
        }
        @keyframes msgFade {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.received {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-light);
            border-bottom-left-radius: 6px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            color: var(--text-dark);
        }
        .message.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border-bottom-right-radius: 6px;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.28);
        }
        .message-sender {
            font-size: 0.7rem;
            font-weight: 800;
            margin-bottom: 4px;
            opacity: 0.85;
        }
        .message.received .message-sender { color: var(--primary); }
        .message-text {
            font-size: 0.92rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .message-time {
            font-size: 0.68rem;
            opacity: 0.7;
            margin-top: 5px;
            text-align: right;
        }
        .message.sent .message-time { color: rgba(255,255,255,0.85); }
        .message.received .message-time { color: var(--text-muted); }

        .message.system {
            align-self: center;
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-muted);
            font-size: 0.76rem;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.15);
        }
        .message.system .message-time,
        .message.system .message-sender { display: none; }

        /* ============ Input ============ */
        .chat-input-area {
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-input {
            flex: 1;
            padding: 13px 18px;
            border: 1.5px solid var(--border);
            border-radius: 24px;
            background: white;
            font-family: inherit;
            font-size: 0.92rem;
            outline: none;
            transition: all 0.2s ease;
            resize: none;
            max-height: 120px;
            min-height: 46px;
            color: var(--text-dark);
        }
        .chat-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .chat-input:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.7;
        }
        .send-btn {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .send-btn:hover { 
            transform: translateY(-2px) scale(1.05); 
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.45);
        }
        .send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .ended-banner {
            background: linear-gradient(135deg, #fee2e2, #fef2f2);
            color: #b91c1c;
            padding: 16px 20px;
            margin: 0;
            text-align: center;
            font-size: 0.88rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-top: 1px solid rgba(239, 68, 68, 0.15);
        }

        /* ============ Empty State ============ */
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, rgba(238, 242, 255, 0.4), transparent);
        }
        .empty-state-icon {
            width: 100px;
            height: 100px;
            border-radius: 24px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            display: grid;
            place-items: center;
            margin-bottom: 20px;
            box-shadow: 0 20px 50px rgba(37, 99, 235, 0.3);
            font-size: 2.5rem;
        }
        .empty-state h3 {
            color: var(--text-dark);
            margin-bottom: 10px;
            font-weight: 800;
            font-size: 1.3rem;
        }
        .empty-state p {
            max-width: 340px;
            line-height: 1.6;
            font-size: 0.92rem;
            color: var(--text-muted);
        }

        /* ============ Toast ============ */
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
        .custom-toast.success { border-color: #10b981; }
        .custom-toast.error { border-color: #ef4444; }
        .custom-toast .toast-icon { font-size: 1.3rem; }
        .custom-toast.success .toast-icon { color: #10b981; }
        .custom-toast.error .toast-icon { color: #ef4444; }
        .custom-toast.info .toast-icon { color: var(--primary); }
        .custom-toast .toast-content { font-weight: 600; font-size: 0.9rem; color: var(--text-dark); }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeOut { to { opacity: 0; transform: translateX(40px); } }

        /* ============ Mobile ============ */
        .mobile-toggle, .mobile-back {
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

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .mobile-toggle { display: block; }
            .chat-layout { grid-template-columns: 1fr; }
            .chat-panel { display: none; }
            .chat-panel.show { display: flex; position: fixed; inset: 0; z-index: 999; border-radius: 0; }
            .teachers-panel.hidden { display: none; }
            .mobile-back { display: block; }
        }
        @media (max-width: 600px) {
            .topbar { padding: 12px; }
            .topbar-actions { gap: 8px; }
            .lang-switcher a { padding: 5px 10px; font-size: 0.72rem; }
            .profile-chip .name, .profile-chip .student-info { display: none; }
            .message { max-width: 85%; }
            .empty-state-icon { width: 80px; height: 80px; font-size: 2rem; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
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
            <a href="parent_dashboard.php" class="nav-link-premium">
                <i class="bi bi-grid-1x2-fill"></i> <?php echo htmlspecialchars(t('dashboard')); ?>
            </a>
            <a href="notification_parent.php" class="nav-link-premium">
                <i class="bi bi-envelope-paper-heart-fill"></i> <?php echo htmlspecialchars(t('request_teacher')); ?>
            </a>
            <a href="parent_chat.php" class="nav-link-premium active">
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
                    <i class="bi bi-chat-dots-fill"></i>
                    <?php echo htmlspecialchars(t('chat_with_teachers')); ?>
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

        <div class="chat-layout">
            <!-- Teachers Panel -->
            <div class="teachers-panel" id="teachersPanel">
                <div class="panel-header">
                    <h3><i class="bi bi-people-fill"></i> <?php echo htmlspecialchars(t('accepted_teachers')); ?></h3>
                    <p><?php echo htmlspecialchars(t('only_accepted_chats')); ?></p>
                </div>
                <div class="panel-search">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" 
                               class="search-input" 
                               id="searchInput" 
                               placeholder="<?php echo htmlspecialchars(t('search_teacher')); ?>">
                    </div>
                </div>
                <div class="teachers-list" id="teachersList">
                    <div style="text-align:center; padding:30px 15px; color: var(--text-muted);">
                        <i class="bi bi-hourglass-split" style="font-size:2rem; opacity:0.4;"></i>
                    </div>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="chat-panel" id="chatPanel">
                <div class="empty-state" id="emptyChat">
                    <div class="empty-state-icon">
                        <i class="bi bi-chat-square-dots-fill"></i>
                    </div>
                    <h3><?php echo htmlspecialchars(t('select_teacher')); ?></h3>
                    <p><?php echo htmlspecialchars(t('select_teacher_to_chat')); ?></p>
                </div>

                <div id="chatActive" style="display:none; flex-direction:column; height:100%; flex:1; min-height:0;">
                    <div class="chat-header">
                        <button class="mobile-back" onclick="backToList()">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <div class="chat-header-avatar" id="chatHeaderAvatar">T</div>
                        <div class="chat-header-info">
                            <h4 id="chatHeaderName"><?php echo htmlspecialchars(t('teacher')); ?></h4>
                            <p>
                                <span class="online-status"><?php echo htmlspecialchars(t('online')); ?></span>
                                <span class="header-id-pill" id="chatHeaderId"></span>
                                <span id="chatStatusBadge"></span>
                            </p>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages"></div>

                    <div id="chatInputContainer">
                        <div class="chat-input-area">
                            <textarea id="messageInput" 
                                      class="chat-input" 
                                      placeholder="<?php echo htmlspecialchars(t('type_message')); ?>" 
                                      rows="1"
                                      maxlength="1000"></textarea>
                            <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Toast -->
<div class="toast-container" id="toastContainer"></div>

<script>
const T = <?php echo json_encode($T[$lang]); ?>;

let currentRequestId = null;
let currentTeacherInfo = null;
let lastMessageId = 0;
let pollInterval = null;
let teachersRefreshInterval = null;
let searchTimer = null;

/* ===================== AJAX ===================== */
async function api(action, data = {}) {
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', action);
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    try {
        const r = await fetch('', { method: 'POST', body: fd });
        return await r.json();
    } catch (err) {
        return { success: false, message: T.failed_to_load };
    }
}

/* ===================== TOAST ===================== */
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

/* ===================== HELPERS ===================== */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatTime(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatPreviewTime(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    const diff = (now - d) / 1000;
    if (diff < 604800) return d.toLocaleDateString([], { weekday: 'short' });
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

/* ===================== LOAD TEACHERS ===================== */
async function loadTeachers() {
    const search = document.getElementById('searchInput').value.trim();
    const out = await api('fetch_teachers', { search: search });

    if (!out.success) {
        showToast(T.failed_to_load, 'error');
        return;
    }

    const list = document.getElementById('teachersList');

    if (!out.teachers || out.teachers.length === 0) {
        list.innerHTML = `
            <div style="text-align:center; padding:50px 20px; color: var(--text-muted);">
                <div style="width:80px; height:80px; border-radius:20px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; display: grid; place-items: center; margin: 0 auto 18px; font-size: 2rem; box-shadow: 0 12px 30px rgba(37, 99, 235, 0.25);">
                    <i class="bi bi-inbox"></i>
                </div>
                <h4 style="font-size:1rem; margin-bottom:6px; font-weight:800; color: var(--text-dark);">${escapeHtml(T.no_accepted_teachers)}</h4>
                <p style="font-size:0.83rem;">${escapeHtml(T.send_request_first)}</p>
            </div>`;
        return;
    }

    list.innerHTML = out.teachers.map(t => {
        const teacherName = t.teacher_name || 'Teacher';
        const initial = teacherName.charAt(0).toUpperCase();
        const isActive = currentRequestId == t.request_id;
        const isEnded = t.conversation_status === 'Ended';
        
        let previewText = '';
        if (t.last_message) {
            const prefix = t.last_sender_role === 'parent' ? 'You: ' : '';
            previewText = prefix + escapeHtml(t.last_message.substring(0, 35));
        } else {
            previewText = '—';
        }
        
        const previewTime = t.last_message_time ? formatPreviewTime(t.last_message_time) : formatPreviewTime(t.created_at);

        const badge = isEnded 
            ? `<span class="badge-status badge-ended">${escapeHtml(T.ended)}</span>` 
            : `<span class="badge-status badge-active">${escapeHtml(T.live)}</span>`;

        return `
            <div class="teacher-item ${isActive ? 'active' : ''}" 
                 onclick="openChat(${t.request_id}, ${JSON.stringify(teacherName).replace(/"/g,'&quot;')}, ${JSON.stringify(t.teacher_code).replace(/"/g,'&quot;')}, '${t.conversation_status || 'Active'}')">
                ${badge}
                <div class="teacher-avatar">${escapeHtml(initial)}</div>
                <div class="teacher-info">
                    <div class="teacher-name">${escapeHtml(teacherName)}</div>
                    <div class="teacher-meta">
                        <span class="teacher-id-tag">${escapeHtml(t.teacher_code)}</span>
                    </div>
                    <div class="teacher-preview">${previewText}</div>
                    <div class="teacher-time">${previewTime}</div>
                </div>
            </div>`;
    }).join('');
}

/* ===================== OPEN CHAT ===================== */
async function openChat(requestId, teacherName, teacherCode, convStatus) {
    currentRequestId = requestId;
    currentTeacherInfo = { teacherName, teacherCode, convStatus };
    lastMessageId = 0;

    document.getElementById('emptyChat').style.display = 'none';
    document.getElementById('chatActive').style.display = 'flex';
    document.getElementById('chatHeaderName').textContent = teacherName;
    document.getElementById('chatHeaderId').textContent = teacherCode;
    document.getElementById('chatHeaderAvatar').textContent = teacherName.charAt(0).toUpperCase();

    /* Mobile view */
    document.getElementById('teachersPanel').classList.add('hidden');
    document.getElementById('chatPanel').classList.add('show');

    /* Active state */
    document.querySelectorAll('.teacher-item').forEach(el => el.classList.remove('active'));
    if (event && event.currentTarget) event.currentTarget.classList.add('active');

    updateChatUI(convStatus);
    await loadMessages();

    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(pollNewMessages, 3000);

    const input = document.getElementById('messageInput');
    if (input && !input.disabled) input.focus();
}

/* ===================== UPDATE CHAT UI ===================== */
function updateChatUI(convStatus) {
    const statusBadge = document.getElementById('chatStatusBadge');
    const inputContainer = document.getElementById('chatInputContainer');

    if (convStatus === 'Ended') {
        statusBadge.innerHTML = `<span class="conv-status-pill conv-status-ended">${escapeHtml(T.ended)}</span>`;
        inputContainer.innerHTML = `
            <div class="ended-banner">
                <i class="bi bi-lock-fill"></i>
                ${escapeHtml(T.conversation_ended)}
            </div>
        `;
    } else {
        statusBadge.innerHTML = `<span class="conv-status-pill conv-status-active">${escapeHtml(T.active)}</span>`;
        inputContainer.innerHTML = `
            <div class="chat-input-area">
                <textarea id="messageInput" 
                          class="chat-input" 
                          placeholder="${escapeHtml(T.type_message)}" 
                          rows="1"
                          maxlength="1000"></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        `;

        setTimeout(() => {
            const input = document.getElementById('messageInput');
            if (input) {
                input.addEventListener('keydown', handleInputKey);
                input.addEventListener('input', autoResize);
                input.focus();
            }
        }, 100);
    }
}

function handleInputKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function autoResize() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
}

/* ===================== BACK (mobile) ===================== */
function backToList() {
    document.getElementById('teachersPanel').classList.remove('hidden');
    document.getElementById('chatPanel').classList.remove('show');
}

/* ===================== LOAD MESSAGES ===================== */
async function loadMessages() {
    if (!currentRequestId) return;

    const out = await api('fetch_messages', { request_id: currentRequestId });
    if (!out.success) {
        showToast(out.message || T.failed_to_load, 'error');
        return;
    }

    const container = document.getElementById('chatMessages');
    container.innerHTML = '';
    lastMessageId = 0;

    if (!out.messages || out.messages.length === 0) {
        container.innerHTML = `
            <div style="margin:auto; text-align:center; color:var(--text-muted);">
                <div style="width:70px; height:70px; border-radius:20px; background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(99, 102, 241, 0.1)); color: var(--primary); display: grid; place-items: center; margin: 0 auto 14px; font-size: 1.8rem;">
                    <i class="bi bi-chat-dots"></i>
                </div>
                <p style="font-size:0.9rem; font-weight: 600;">${escapeHtml(T.no_messages_yet)}</p>
            </div>`;
        return;
    }

    out.messages.forEach(m => {
        lastMessageId = Math.max(lastMessageId, parseInt(m.id));
        appendMessage(m);
    });

    /* Update conv status */
    if (out.request_info && currentTeacherInfo) {
        const newStatus = out.request_info.conversation_status || 'Active';
        if (newStatus !== currentTeacherInfo.convStatus) {
            currentTeacherInfo.convStatus = newStatus;
            updateChatUI(newStatus);
        }
    }

    scrollToBottom();
}

/* ===================== APPEND MESSAGE ===================== */
function appendMessage(m) {
    const container = document.getElementById('chatMessages');

    const placeholder = container.querySelector('div[style*="margin:auto"]');
    if (placeholder) placeholder.remove();

    if (m.sender_role === 'system') {
        const div = document.createElement('div');
        div.className = 'message system';
        div.innerHTML = `<div class="message-text">${escapeHtml(m.message)}</div>`;
        container.appendChild(div);
        return;
    }

    const isMine = m.sender_role === 'parent';
    const div = document.createElement('div');
    div.className = 'message ' + (isMine ? 'sent' : 'received');
    div.innerHTML = `
        ${!isMine ? `<div class="message-sender">${escapeHtml(m.sender_name)}</div>` : ''}
        <div class="message-text">${escapeHtml(m.message)}</div>
        <div class="message-time">${formatTime(m.created_at)}</div>
    `;
    container.appendChild(div);
}

function scrollToBottom() {
    const c = document.getElementById('chatMessages');
    c.scrollTop = c.scrollHeight;
}

/* ===================== SEND MESSAGE ===================== */
async function sendMessage() {
    const input = document.getElementById('messageInput');
    if (!input) return;

    const msg = input.value.trim();
    if (!msg || !currentRequestId) return;

    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    input.disabled = true;

    const out = await api('send_message', {
        request_id: currentRequestId,
        message: msg
    });

    sendBtn.disabled = false;
    input.disabled = false;

    if (out.success) {
        input.value = '';
        input.style.height = 'auto';
        appendMessage({
            id: out.message_id,
            sender_role: 'parent',
            sender_name: <?php echo json_encode($parentName); ?>,
            message: msg,
            created_at: new Date().toISOString()
        });
        lastMessageId = parseInt(out.message_id) || lastMessageId;
        scrollToBottom();
        input.focus();

        setTimeout(loadTeachers, 500);
    } else {
        showToast(out.message || T.failed_to_send, 'error');
    }
}

/* ===================== POLL NEW MESSAGES ===================== */
async function pollNewMessages() {
    if (!currentRequestId) return;

    const out = await api('fetch_messages', {
        request_id: currentRequestId,
        last_id: lastMessageId
    });

    if (!out.success) return;

    let hasNew = false;
    if (out.messages && out.messages.length > 0) {
        out.messages.forEach(m => {
            const isMine = m.sender_role === 'parent';
            const isSystem = m.sender_role === 'system';

            if (!isMine || isSystem) {
                lastMessageId = Math.max(lastMessageId, parseInt(m.id));
                appendMessage(m);
                if (!isSystem) hasNew = true;
            } else {
                lastMessageId = Math.max(lastMessageId, parseInt(m.id));
            }
        });

        if (hasNew) scrollToBottom();
    }

    /* Check status change */
    if (out.request_info && currentTeacherInfo) {
        const newStatus = out.request_info.conversation_status || 'Active';
        if (newStatus !== currentTeacherInfo.convStatus) {
            currentTeacherInfo.convStatus = newStatus;
            updateChatUI(newStatus);
        }
    }
}

/* ===================== SEARCH ===================== */
document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadTeachers, 300);
});

/* ===================== INIT ===================== */
const initialInput = document.getElementById('messageInput');
if (initialInput) {
    initialInput.addEventListener('keydown', handleInputKey);
    initialInput.addEventListener('input', autoResize);
}

loadTeachers();
teachersRefreshInterval = setInterval(loadTeachers, 8000);
</script>
</body>
</html>