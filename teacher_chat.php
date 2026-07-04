<?php
/**
 * ============================================================
 * AUREON ERP — Teacher Chat Page
 * File: teacher_chat.php
 * ============================================================
 * Shows only ACCEPTED parents on left panel.
 * Right panel shows live chat with selected parent.
 * Messages stored in teacher_parent_chat table.
 * End conversation supported.
 * ============================================================
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);
/* ============================================================
   AUTH CHECK
============================================================ */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

/* ============================================================
   DATABASE CONNECTION
============================================================ */
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=aureon;charset=utf8mb4',
        'root', '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

/* ============================================================
   FETCH TEACHER DETAILS FROM users TABLE
============================================================ */
$stmt = $pdo->prepare("
    SELECT full_name, reference_id
    FROM   users
    WHERE  id   = ?
      AND  role = 'teacher'
    LIMIT 1
");
$stmt->execute([$userId]);
$teacherRow = $stmt->fetch();

if (!$teacherRow) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$teacherName  = $teacherRow['full_name'];
$teacherCode  = $teacherRow['reference_id']; // e.g. TCHBCA99001

/* ============================================================
   ENSURE TABLE EXISTS (run once)
============================================================ */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS teacher_parent_chat (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        request_id   INT          NOT NULL,
        sender_role  VARCHAR(20)  NOT NULL,
        sender_name  VARCHAR(100) NOT NULL,
        teacher_id   VARCHAR(100) NOT NULL,
        student_id   VARCHAR(100) NOT NULL,
        message      TEXT         NOT NULL,
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    )
");

/* Add conversation_status column if not exists */
try {
    $pdo->exec("
        ALTER TABLE parent_teacher_requests
        ADD COLUMN conversation_status VARCHAR(20) DEFAULT 'Active'
    ");
} catch (PDOException $e) {
    // Column already exists — ignore
}

/* ============================================================
   AJAX HANDLERS
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    /* ── Fetch accepted parents list ── */
    if ($action === 'fetch_accepted') {
        $s = $pdo->prepare("
            SELECT
                r.id,
                r.parent_name,
                r.student_name,
                r.conversation_status,
                r.created_at,
                (
                    SELECT COUNT(*)
                    FROM   teacher_parent_chat c
                    WHERE  c.request_id   = r.id
                ) AS msg_count,
                (
                    SELECT c2.message
                    FROM   teacher_parent_chat c2
                    WHERE  c2.request_id = r.id
                    ORDER  BY c2.created_at DESC
                    LIMIT  1
                ) AS last_message,
                (
                    SELECT c3.created_at
                    FROM   teacher_parent_chat c3
                    WHERE  c3.request_id = r.id
                    ORDER  BY c3.created_at DESC
                    LIMIT  1
                ) AS last_msg_time
            FROM   parent_teacher_requests r
            WHERE  r.teacher_id = ?
              AND  r.status     = 'Accepted'
            ORDER  BY r.created_at DESC
        ");
        $s->execute([$teacherCode]);
        echo json_encode(['ok' => 1, 'parents' => $s->fetchAll()]);
        exit;
    }

    /* ── Fetch messages for a specific request ── */
    if ($action === 'fetch_messages') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $afterId   = (int)($_POST['after_id']   ?? 0);

        if (!$requestId) {
            echo json_encode(['ok' => 0, 'msg' => 'Invalid request']);
            exit;
        }

        /* Verify this request belongs to this teacher */
        $chk = $pdo->prepare("
            SELECT id, parent_name, student_name, conversation_status
            FROM   parent_teacher_requests
            WHERE  id         = ?
              AND  teacher_id = ?
              AND  status     = 'Accepted'
            LIMIT 1
        ");
        $chk->execute([$requestId, $teacherCode]);
        $req = $chk->fetch();

        if (!$req) {
            echo json_encode(['ok' => 0, 'msg' => 'Access denied']);
            exit;
        }

        $s = $pdo->prepare("
            SELECT
                id,
                sender_role,
                sender_name,
                message,
                DATE_FORMAT(created_at, '%d %b %Y, %h:%i %p') AS fmt_time,
                created_at
            FROM  teacher_parent_chat
            WHERE request_id = ?
              AND id         > ?
            ORDER BY id ASC
        ");
        $s->execute([$requestId, $afterId]);

        echo json_encode([
            'ok'           => 1,
            'messages'     => $s->fetchAll(),
            'conv_status'  => $req['conversation_status'],
            'parent_name'  => $req['parent_name'],
            'student_name' => $req['student_name'],
        ]);
        exit;
    }

    /* ── Send message ── */
    if ($action === 'send_message') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $message   = trim($_POST['message']     ?? '');

        if (!$requestId || $message === '') {
            echo json_encode(['ok' => 0, 'msg' => 'Empty message']);
            exit;
        }

        /* Verify ownership + conversation still Active */
        $chk = $pdo->prepare("
            SELECT id, student_name, conversation_status
            FROM   parent_teacher_requests
            WHERE  id         = ?
              AND  teacher_id = ?
              AND  status     = 'Accepted'
            LIMIT 1
        ");
        $chk->execute([$requestId, $teacherCode]);
        $req = $chk->fetch();

        if (!$req) {
            echo json_encode(['ok' => 0, 'msg' => 'Access denied']);
            exit;
        }

        if (($req['conversation_status'] ?? 'Active') === 'Ended') {
            echo json_encode(['ok' => 0, 'msg' => 'Conversation has ended']);
            exit;
        }

        $ins = $pdo->prepare("
            INSERT INTO teacher_parent_chat
                (request_id, sender_role, sender_name, teacher_id, student_id, message)
            VALUES
                (?, 'teacher', ?, ?, ?, ?)
        ");
        $ins->execute([
            $requestId,
            $teacherName,
            $teacherCode,
            $req['student_name'],
            $message,
        ]);

        echo json_encode(['ok' => 1, 'new_id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    /* ── End conversation ── */
    if ($action === 'end_conversation') {
        $requestId = (int)($_POST['request_id'] ?? 0);

        if (!$requestId) {
            echo json_encode(['ok' => 0, 'msg' => 'Invalid request']);
            exit;
        }

        $s = $pdo->prepare("
            UPDATE parent_teacher_requests
            SET    conversation_status = 'Ended'
            WHERE  id         = ?
              AND  teacher_id = ?
        ");
        $ok = $s->execute([$requestId, $teacherCode]);

        echo json_encode([
            'ok'  => $ok ? 1 : 0,
            'msg' => $ok ? 'Conversation ended successfully.' : 'Failed.',
        ]);
        exit;
    }

    echo json_encode(['ok' => 0, 'msg' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AUREON ERP — Teacher Chat</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ============================================================
   THEME
============================================================ */
:root {
    --cream:     #fff9f0;
    --beige:     #fdf4e8;
    --acc:       #e89a4a;
    --acc-l:     #ffe6c7;
    --acc-d:     #c97d30;
    --txt:       #3f2a1e;
    --muted:     #876a57;
    --glass:     rgba(255,255,255,.72);
    --shadow:    0 12px 38px rgba(63,42,30,.08);
    --shadow-h:  0 20px 54px rgba(232,154,74,.20);
    --radius:    26px;
    --radius-sm: 18px;
    --tr:        .26s cubic-bezier(.4,0,.2,1);
    --sw:        268px;
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { height:100%; }

body {
    font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    background: linear-gradient(145deg, var(--cream) 0%, #f5e8d5 55%, #edddd0 100%);
    color: var(--txt);
    height: 100vh;
    display: flex;
    overflow: hidden;
}

body::before, body::after {
    content:''; position:fixed; border-radius:50%;
    filter:blur(90px); opacity:.24; z-index:-1; pointer-events:none;
}
body::before { width:360px;height:360px;background:#f2b977;top:-110px;left:-110px; }
body::after  { width:440px;height:440px;background:#e89a4a;bottom:-140px;right:-140px; }

a { text-decoration:none; color:inherit; }
button { cursor:pointer; font-family:inherit; }
::-webkit-scrollbar { width:5px; height:5px; }
::-webkit-scrollbar-thumb { background:var(--acc-l); border-radius:10px; }

/* ============================================================
   SIDEBAR
============================================================ */
.sidebar {
    width: var(--sw);
    height: 100vh;
    background: var(--glass);
    backdrop-filter: blur(22px);
    border-right: 1px solid rgba(255,255,255,.68);
    box-shadow: var(--shadow);
    display: flex; flex-direction: column;
    padding: 22px 16px;
    flex-shrink: 0;
    z-index: 50;
    transition: transform var(--tr);
}
.brand {
    display:flex; align-items:center; gap:13px;
    margin-bottom:32px; padding-bottom:20px;
    border-bottom:1px solid rgba(232,154,74,.14);
}
.brand-icon {
    width:48px;height:48px;border-radius:18px;flex-shrink:0;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:21px;
    box-shadow:0 10px 26px rgba(232,154,74,.30);
}
.brand-name { font-weight:800;font-size:16px; }
.brand-sub  { font-size:11px;color:var(--muted); }
.nav-label  {
    font-size:10px;font-weight:700;text-transform:uppercase;
    letter-spacing:.8px;color:var(--muted);
    padding:0 10px 8px;margin-top:12px;
}
.nav-link-item {
    display:flex;align-items:center;gap:12px;
    padding:12px 15px;border-radius:var(--radius-sm);
    color:var(--muted);font-weight:600;font-size:14px;
    transition:var(--tr);margin-bottom:4px;
    width:100%;background:none;border:none;text-align:left;
}
.nav-link-item:hover  { background:rgba(255,255,255,.80);color:var(--acc);transform:translateX(4px); }
.nav-link-item.active { background:linear-gradient(135deg,#fff1de,#ffe4c2);color:var(--acc);box-shadow:0 8px 20px rgba(232,154,74,.14); }
.nav-link-item i { font-size:17px;width:20px;text-align:center; }
.sidebar-foot {
    margin-top:auto;padding-top:18px;
    border-top:1px solid rgba(232,154,74,.12);
}
.logout-btn {
    display:flex;align-items:center;gap:12px;
    padding:12px 15px;border-radius:var(--radius-sm);
    color:#ef4444;font-weight:700;font-size:14px;
    background:none;border:none;width:100%;transition:var(--tr);
}
.logout-btn:hover { background:#fef2f2; }

/* ============================================================
   CHAT SHELL
============================================================ */
.chat-shell {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100vh;
}

/* ── Topbar ── */
.topbar {
    background: rgba(255,255,255,.72);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(232,154,74,.12);
    padding: 14px 24px;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
    box-shadow: 0 4px 18px rgba(63,42,30,.06);
    gap: 14px; flex-wrap: wrap;
}
.topbar-title { font-size:19px;font-weight:800; }
.topbar-sub   { font-size:12px;color:var(--muted);margin-top:2px; }
.profile-pill {
    display:flex;align-items:center;gap:10px;
    padding:6px 14px 6px 6px;
    background:rgba(255,255,255,.65);
    border:1px solid rgba(255,255,255,.82);
    border-radius:50px;
    box-shadow:0 5px 16px rgba(63,42,30,.07);
}
.profile-av {
    width:36px;height:36px;border-radius:50%;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-weight:800;font-size:15px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 5px 14px rgba(232,154,74,.22);
}
.profile-name { font-size:13px;font-weight:700; }
.profile-role { font-size:11px;color:var(--muted); }

/* ── Three-panel body ── */
.chat-body {
    flex: 1;
    display: flex;
    overflow: hidden;
}

/* ============================================================
   LEFT PANEL — Parent list
============================================================ */
.left-panel {
    width: 310px;
    flex-shrink: 0;
    border-right: 1px solid rgba(232,154,74,.14);
    display: flex;
    flex-direction: column;
    background: rgba(255,255,255,.55);
    backdrop-filter: blur(14px);
    overflow: hidden;
}
.left-head {
    padding: 16px;
    border-bottom: 1px solid rgba(232,154,74,.12);
    background: rgba(255,255,255,.60);
    flex-shrink: 0;
}
.left-head-title {
    font-size:14px;font-weight:800;margin-bottom:10px;
    display:flex;align-items:center;gap:8px;color:var(--txt);
}
.left-head-title i { color:var(--acc);font-size:16px; }
.search-box {
    width:100%;padding:10px 14px;border-radius:14px;
    border:1.5px solid rgba(232,154,74,.18);
    background:rgba(255,255,255,.80);
    font-family:inherit;font-size:13px;color:var(--txt);
    outline:none;transition:border-color var(--tr);
}
.search-box:focus { border-color:var(--acc); }
.search-box::placeholder { color:#bba898; }

.parent-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.parent-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 18px;
    cursor: pointer;
    transition: var(--tr);
    margin-bottom: 6px;
    position: relative;
    border: 1.5px solid transparent;
}
.parent-item:hover {
    background: rgba(255,255,255,.82);
    transform: translateX(3px);
}
.parent-item.active {
    background: linear-gradient(135deg,#fff1de,#ffe4c2);
    border-color: rgba(232,154,74,.28);
    box-shadow: 0 8px 20px rgba(232,154,74,.14);
}
.parent-item.ended { opacity:.62; }
.p-av {
    width:44px;height:44px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-weight:800;font-size:17px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 7px 18px rgba(232,154,74,.24);
    position:relative;
}
.online-ring {
    position:absolute;bottom:0;right:0;
    width:13px;height:13px;border-radius:50%;
    background:#22c55e;border:2.5px solid white;
}
.ended-ring {
    position:absolute;bottom:0;right:0;
    width:13px;height:13px;border-radius:50%;
    background:#94a3b8;border:2.5px solid white;
}
.p-name  { font-weight:700;font-size:14px;margin-bottom:2px; }
.p-student { font-size:12px;color:var(--muted); }
.p-last  {
    font-size:12px;color:var(--muted);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    max-width:150px;margin-top:2px;
}
.ended-badge {
    font-size:10px;font-weight:800;padding:3px 8px;
    border-radius:8px;background:#f1f5f9;color:#64748b;
    margin-left:auto;flex-shrink:0;
}
.active-badge {
    font-size:10px;font-weight:800;padding:3px 8px;
    border-radius:8px;background:var(--acc-l);color:var(--acc);
    margin-left:auto;flex-shrink:0;
}

/* empty left panel */
.left-empty {
    flex:1;display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:30px;text-align:center;color:var(--muted);
}
.left-empty i { font-size:46px;opacity:.28;margin-bottom:12px; }
.left-empty p { font-size:13px;font-weight:600;opacity:.55;line-height:1.5; }

/* ============================================================
   RIGHT PANEL — Chat area
============================================================ */
.right-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: rgba(255,255,255,.38);
}

/* Chat header */
.chat-header {
    padding: 14px 20px;
    background: rgba(255,255,255,.68);
    border-bottom: 1px solid rgba(232,154,74,.12);
    display: flex; align-items: center; gap: 14px;
    flex-shrink: 0;
    box-shadow: 0 3px 12px rgba(63,42,30,.05);
}
.chat-header-av {
    width:46px;height:46px;border-radius:50%;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-weight:800;font-size:19px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 7px 18px rgba(232,154,74,.24);
    flex-shrink:0;
}
.chat-header-name  { font-weight:800;font-size:16px; }
.chat-header-meta  { font-size:12px;color:var(--muted);margin-top:2px; }
.online-dot {
    width:10px;height:10px;border-radius:50%;
    background:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.18);
    flex-shrink:0;
}
.conv-ended-dot {
    width:10px;height:10px;border-radius:50%;
    background:#94a3b8;flex-shrink:0;
}

/* End conversation btn */
.btn-end-conv {
    margin-left:auto;
    padding:10px 18px;border-radius:14px;border:none;
    background:rgba(239,68,68,.10);
    border:1.5px solid rgba(239,68,68,.25);
    color:#dc2626;font-weight:800;font-size:13px;
    display:flex;align-items:center;gap:8px;
    transition:var(--tr);
}
.btn-end-conv:hover { background:#ef4444;color:#fff;border-color:#ef4444; }
.btn-end-conv:disabled { opacity:.45;cursor:not-allowed; }

/* Messages area */
.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background:
        radial-gradient(ellipse at 15% 20%, rgba(255,230,199,.55) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 80%, rgba(232,154,74,.12) 0%, transparent 55%);
}

/* Bubbles */
.bubble-wrap {
    display:flex;
    flex-direction:column;
    max-width:72%;
    animation:fadeUp .22s ease;
}
.bubble-wrap.teacher { align-self:flex-end; align-items:flex-end; }
.bubble-wrap.parent  { align-self:flex-start; align-items:flex-start; }

@keyframes fadeUp {
    from { opacity:0; transform:translateY(8px); }
    to   { opacity:1; transform:translateY(0); }
}

.bubble {
    padding:12px 16px;border-radius:20px;
    font-size:14px;line-height:1.55;
    max-width:100%;word-break:break-word;
}
.bubble.teacher {
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;
    border-bottom-right-radius:5px;
    box-shadow:0 8px 22px rgba(232,154,74,.22);
}
.bubble.parent {
    background:rgba(255,255,255,.88);
    border:1.5px solid rgba(232,154,74,.14);
    color:var(--txt);
    border-bottom-left-radius:5px;
    box-shadow:0 6px 16px rgba(63,42,30,.06);
}
.bubble-meta {
    font-size:11px;color:var(--muted);
    margin-top:5px;
    display:flex;align-items:center;gap:5px;
}
.bubble-wrap.teacher .bubble-meta { justify-content:flex-end; }

/* Typing dots */
.typing-dots {
    display:flex;gap:5px;align-items:center;
    padding:12px 16px;
    background:rgba(255,255,255,.88);
    border:1.5px solid rgba(232,154,74,.14);
    border-radius:20px;border-bottom-left-radius:5px;
    width:fit-content;
    box-shadow:0 6px 16px rgba(63,42,30,.06);
}
.td { width:7px;height:7px;border-radius:50%;background:var(--muted);animation:tdB 1.1s infinite; }
.td:nth-child(2){animation-delay:.17s}
.td:nth-child(3){animation-delay:.34s}
@keyframes tdB { 0%,80%,100%{transform:translateY(0);opacity:.35}40%{transform:translateY(-5px);opacity:1} }

/* Date divider */
.date-divider {
    display:flex;align-items:center;gap:12px;
    font-size:12px;font-weight:700;color:var(--muted);
    margin:10px 0;
}
.date-divider::before,.date-divider::after {
    content:'';flex:1;height:1px;
    background:rgba(232,154,74,.18);
}

/* Conversation ended banner */
.conv-ended-banner {
    display:flex;align-items:center;justify-content:center;gap:10px;
    padding:14px 20px;margin:10px 0;
    background:rgba(241,245,249,.90);
    border:1px solid rgba(148,163,184,.28);
    border-radius:16px;
    font-size:13px;font-weight:700;color:#64748b;
}

/* ── Empty right panel ── */
.right-empty {
    flex:1;display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    gap:16px;color:var(--muted);
    background:
        radial-gradient(ellipse at 20% 20%,rgba(255,230,199,.45) 0%,transparent 55%);
}
.right-empty i { font-size:62px;opacity:.22; }
.right-empty h3 { font-size:20px;font-weight:800;opacity:.50; }
.right-empty p  { font-size:14px;opacity:.40;max-width:300px;text-align:center;line-height:1.6; }

/* ── Composer ── */
.composer {
    padding:14px 18px;
    background:rgba(255,255,255,.72);
    border-top:1px solid rgba(232,154,74,.14);
    display:flex;gap:10px;align-items:flex-end;
    flex-shrink:0;
}
.compose-input {
    flex:1;
    padding:13px 16px;
    border-radius:20px;
    border:1.5px solid rgba(232,154,74,.20);
    background:rgba(255,255,255,.88);
    font-family:inherit;font-size:14px;color:var(--txt);
    outline:none;resize:none;line-height:1.45;
    transition:border-color var(--tr),box-shadow var(--tr);
    max-height:120px;
}
.compose-input:focus {
    border-color:var(--acc);
    box-shadow:0 0 0 4px rgba(232,154,74,.12);
}
.compose-input::placeholder { color:#bba898; }
.compose-input:disabled { opacity:.55;cursor:not-allowed; }

.btn-send {
    width:50px;height:50px;border-radius:50%;border:none;
    background:linear-gradient(135deg,#f2b977,var(--acc));
    color:#fff;font-size:19px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 10px 26px rgba(232,154,74,.28);
    transition:var(--tr);
}
.btn-send:hover { transform:scale(1.08);box-shadow:0 14px 32px rgba(232,154,74,.36); }
.btn-send:disabled { opacity:.45;cursor:not-allowed;transform:none; }

/* ============================================================
   TOAST
============================================================ */
.toast-wrap {
    position:fixed;top:22px;right:22px;z-index:9999;
    display:flex;flex-direction:column;gap:10px;
}
.toast-item {
    display:flex;align-items:center;gap:13px;
    padding:14px 20px;border-radius:18px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(18px);
    box-shadow:0 16px 44px rgba(63,42,30,.13);
    border:1px solid rgba(255,255,255,.90);
    font-weight:700;font-size:14px;
    animation:toastIn .3s cubic-bezier(.34,1.56,.64,1);
    min-width:270px;max-width:350px;
}
.toast-item.ok   { border-left:4px solid #22c55e; }
.toast-item.err  { border-left:4px solid #ef4444; }
.toast-item.warn { border-left:4px solid var(--acc); }
.t-ico {
    width:36px;height:36px;border-radius:12px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:17px;
}
.toast-item.ok   .t-ico { background:#dcfce7;color:#16a34a; }
.toast-item.err  .t-ico { background:#fee2e2;color:#dc2626; }
.toast-item.warn .t-ico { background:var(--acc-l);color:var(--acc); }
.toast-close {
    margin-left:auto;background:none;border:none;
    font-size:15px;color:var(--muted);padding:4px;
}
@keyframes toastIn {
    from { opacity:0;transform:translateX(26px) scale(.90); }
    to   { opacity:1;transform:translateX(0) scale(1); }
}

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width:991px) {
    .sidebar { position:fixed;left:0;top:0;height:100vh;z-index:200;transform:translateX(calc(-1 * var(--sw))); }
    .sidebar.open { transform:translateX(0); }
    .left-panel { width:260px; }
}
@media (max-width:700px) {
    .left-panel { display:none; }
    .left-panel.mob-open { display:flex;position:fixed;left:0;top:0;height:100vh;z-index:100;width:280px; }
}
</style>
</head>
<body>

<!-- Toast container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-chat-dots-fill"></i></div>
        <div>
            <div class="brand-name">AUREON ERP</div>
            <div class="brand-sub">Teacher Portal</div>
        </div>
    </div>

    <div class="nav-label">Navigation</div>

    <a href="teacher_dash.php" class="nav-link-item">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    <a href="teacher_requests.php" class="nav-link-item">
        <i class="bi bi-inbox-fill"></i> Parent Requests
    </a>
    <a href="teacher_chat.php" class="nav-link-item active">
        <i class="bi bi-chat-left-dots-fill"></i> Chat
    </a>
    <a href="notifiaction.php" class="nav-link-item">
        <i class="bi bi-bell-fill"></i> Notifications
    </a>

    <div class="sidebar-foot">
        <button class="logout-btn" onclick="location.href='logout.php'">
            <i class="bi bi-box-arrow-right"></i> Logout
        </button>
    </div>
</aside>

<!-- ============================================================
     CHAT SHELL
============================================================ -->
<div class="chat-shell">

    <!-- Topbar -->
    <header class="topbar">
        <div class="d-flex align-items-center gap-14">
            <button class="btn p-0 me-2 d-lg-none"
                    style="background:none;border:none;font-size:22px;color:var(--txt)"
                    onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <div class="topbar-title">Parent–Teacher Chat</div>
                <div class="topbar-sub">
                    Only accepted parents appear below
                    &nbsp;·&nbsp; Teacher: <strong><?= htmlspecialchars($teacherName) ?></strong>
                    &nbsp;·&nbsp; Code: <strong><?= htmlspecialchars($teacherCode) ?></strong>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="profile-pill">
                <div class="profile-av"><?= strtoupper(substr($teacherName, 0, 1)) ?></div>
                <div>
                    <div class="profile-name"><?= htmlspecialchars($teacherName) ?></div>
                    <div class="profile-role">Teacher</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Body: left + right -->
    <div class="chat-body">

        <!-- ── LEFT PANEL ── -->
        <div class="left-panel" id="leftPanel">

            <div class="left-head">
                <div class="left-head-title">
                    <i class="bi bi-people-fill"></i>
                    Accepted Parents
                </div>
                <input
                    type="text"
                    class="search-box"
                    id="parentSearch"
                    placeholder="🔍  Search parent or student…"
                    oninput="filterParents()"
                >
            </div>

            <!-- Parent list -->
            <div class="parent-scroll" id="parentScroll">
                <!-- loaded via JS -->
                <div class="left-empty">
                    <i class="bi bi-hourglass"></i>
                    <p>Loading accepted parents…</p>
                </div>
            </div>

        </div>

        <!-- ── RIGHT PANEL ── -->
        <div class="right-panel" id="rightPanel">

            <!-- Default empty state -->
            <div class="right-empty" id="rightEmpty">
                <i class="bi bi-chat-square-dots"></i>
                <h3>Select a Parent</h3>
                <p>
                    Choose an accepted parent from the left panel
                    to view and start the conversation.
                </p>
            </div>

            <!-- Chat UI (hidden until parent selected) -->
            <div id="chatUI" style="display:none;flex:1;flex-direction:column;overflow:hidden;display:none;">

                <!-- Chat header -->
                <div class="chat-header" id="chatHeader">
                    <div class="chat-header-av" id="chatAv">P</div>
                    <div class="flex-grow-1">
                        <div class="chat-header-name" id="chatParentName">—</div>
                        <div class="chat-header-meta" id="chatStudentMeta">Student: —</div>
                    </div>
                    <span id="chatStatusDot" class="online-dot" title="Active"></span>
                    <button
                        class="btn-end-conv"
                        id="endConvBtn"
                        onclick="endConversation()"
                    >
                        <i class="bi bi-telephone-x-fill"></i>
                        End Conversation
                    </button>
                </div>

                <!-- Messages -->
                <div class="messages-area" id="messagesArea">
                    <!-- messages rendered here -->
                </div>

                <!-- Composer -->
                <div class="composer">
                    <textarea
                        class="compose-input"
                        id="composeInput"
                        rows="1"
                        placeholder="Type your message… (Enter to send)"
                        disabled
                        onkeypress="handleEnter(event)"
                        oninput="autoResize(this)"
                    ></textarea>
                    <button class="btn-send" id="sendBtn" onclick="sendMessage()" disabled>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>

            </div>

        </div><!-- /right-panel -->

    </div><!-- /chat-body -->
</div><!-- /chat-shell -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ============================================================
   STATE
============================================================ */
let allParents   = [];          // full accepted list
let activeReqId  = null;        // currently open request id
let lastMsgId    = 0;           // for incremental fetch
let pollTimer    = null;        // setInterval handle
let convEnded    = false;       // is conversation ended?

/* ============================================================
   HELPERS
============================================================ */
function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function post(data) {
    return fetch(window.location.href, {
        method  : 'POST',
        headers : {'Content-Type':'application/x-www-form-urlencoded'},
        body    : new URLSearchParams(data).toString()
    }).then(async r => {
        const text = await r.text();

        try {
            return JSON.parse(text);
        } catch (e) {
            console.error(text);
            throw e;
        }
    });
}

function toast(msg, type = 'ok') {
    const icons = {
        ok   : 'bi-check-circle-fill',
        err  : 'bi-x-circle-fill',
        warn : 'bi-exclamation-triangle-fill'
    };
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.innerHTML = `
        <div class="t-ico"><i class="bi ${icons[type] ?? icons.ok}"></i></div>
        <div style="flex:1;line-height:1.4">${esc(msg)}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>`;
    wrap.appendChild(el);
    setTimeout(() => {
        el.style.cssText = 'opacity:0;transform:translateX(26px);transition:.34s';
        setTimeout(() => el.remove(), 380);
    }, 4200);
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function scrollToBottom() {
    const area = document.getElementById('messagesArea');
    area.scrollTop = area.scrollHeight;
}

/* ============================================================
   LOAD ACCEPTED PARENTS
============================================================ */
async function loadParents() {
    const d = await post({ action: 'fetch_accepted' });
    const scroll = document.getElementById('parentScroll');

    if (!d.ok || !d.parents.length) {
        allParents = [];
        scroll.innerHTML = `
            <div class="left-empty">
                <i class="bi bi-person-slash"></i>
                <p>No accepted parents yet.<br>Accept requests from teacher_requests.php first.</p>
            </div>`;
        return;
    }

    allParents = d.parents;
    renderParents(d.parents);
}

function renderParents(list) {
    const scroll = document.getElementById('parentScroll');

    if (!list.length) {
        scroll.innerHTML = `
            <div class="left-empty">
                <i class="bi bi-search"></i>
                <p>No results found.</p>
            </div>`;
        return;
    }

    scroll.innerHTML = list.map(p => {
        const ended   = (p.conversation_status === 'Ended');
        const isActive = activeReqId && Number(activeReqId) === Number(p.id);
        const lastMsg  = p.last_message
            ? (p.last_message.length > 34 ? p.last_message.slice(0, 34) + '…' : p.last_message)
            : 'No messages yet';

        const ringHtml = ended
            ? `<span class="ended-ring"></span>`
            : `<span class="online-ring"></span>`;

        const badgeHtml = ended
            ? `<span class="ended-badge">Ended</span>`
            : `<span class="active-badge">Active</span>`;

        return `
        <div
            class="parent-item ${isActive ? 'active' : ''} ${ended ? 'ended' : ''}"
            id="pitem-${p.id}"
            onclick="selectParent(${p.id},'${esc(p.parent_name)}','${esc(p.student_name)}','${esc(p.conversation_status)}')"
        >
            <div class="p-av">
                ${esc(p.parent_name.charAt(0).toUpperCase())}
                ${ringHtml}
            </div>
            <div style="flex:1;min-width:0">
                <div class="p-name">${esc(p.parent_name)}</div>
                <div class="p-student">
                    <i class="bi bi-person-fill" style="color:var(--acc)"></i>
                    ${esc(p.student_name)}
                </div>
                <div class="p-last">${esc(lastMsg)}</div>
            </div>
            ${badgeHtml}
        </div>`;
    }).join('');
}

function filterParents() {
    const q = document.getElementById('parentSearch').value.toLowerCase();
    const filtered = allParents.filter(p =>
        p.parent_name.toLowerCase().includes(q) ||
        p.student_name.toLowerCase().includes(q)
    );
    renderParents(filtered);
}

/* ============================================================
   SELECT PARENT → open chat
============================================================ */
function selectParent(reqId, parentName, studentName, convStatus) {
    activeReqId = reqId;
    lastMsgId   = 0;
    convEnded   = (convStatus === 'Ended');

    /* Update UI highlight */
    document.querySelectorAll('.parent-item').forEach(el => el.classList.remove('active'));
    const pItem = document.getElementById(`pitem-${reqId}`);
    if (pItem) pItem.classList.add('active');

    /* Populate chat header */
    document.getElementById('chatAv').textContent          = parentName.charAt(0).toUpperCase();
    document.getElementById('chatParentName').textContent  = parentName;
    document.getElementById('chatStudentMeta').textContent = `Student: ${studentName}`;

    /* Status dot + button */
    const dot  = document.getElementById('chatStatusDot');
    const btn  = document.getElementById('endConvBtn');
    const inp  = document.getElementById('composeInput');
    const send = document.getElementById('sendBtn');

    if (convEnded) {
        dot.className = 'conv-ended-dot';
        dot.title     = 'Conversation ended';
        btn.disabled  = true;
        inp.disabled  = true;
        inp.placeholder = 'This conversation has ended.';
        send.disabled = true;
    } else {
        dot.className = 'online-dot';
        dot.title     = 'Active';
        btn.disabled  = false;
        inp.disabled  = false;
        inp.placeholder = 'Type your message… (Enter to send)';
        send.disabled = false;
    }

    /* Show chat UI */
    document.getElementById('rightEmpty').style.display = 'none';
    const chatUI = document.getElementById('chatUI');
    chatUI.style.display = 'flex';
    chatUI.style.flexDirection = 'column';
    chatUI.style.flex = '1';
    chatUI.style.overflow = 'hidden';

    /* Clear messages and load fresh */
    document.getElementById('messagesArea').innerHTML = '';

    if (pollTimer) clearInterval(pollTimer);
    fetchMessages(true);
    pollTimer = setInterval(() => fetchMessages(false), 3500);
}

/* ============================================================
   FETCH MESSAGES
============================================================ */
async function fetchMessages(forceScroll) {
    if (!activeReqId) return;

    const d = await post({
        action     : 'fetch_messages',
        request_id : activeReqId,
        after_id   : lastMsgId
    });

    if (!d.ok) return;

    /* Update conv status if changed (e.g. ended from another session) */
    if (d.conv_status === 'Ended' && !convEnded) {
        convEnded = true;
        document.getElementById('chatStatusDot').className = 'conv-ended-dot';
        document.getElementById('endConvBtn').disabled     = true;
        document.getElementById('composeInput').disabled   = true;
        document.getElementById('sendBtn').disabled        = true;

        const area = document.getElementById('messagesArea');
        appendEndedBanner(area);
        scrollToBottom();
    }

    if (!d.messages.length) return;

    const area  = document.getElementById('messagesArea');
    const isNearBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;

    d.messages.forEach(m => {
        appendBubble(area, m);
        lastMsgId = Math.max(lastMsgId, Number(m.id));
    });

    if (forceScroll || isNearBottom) {
        scrollToBottom();
    }

    /* Refresh parent list snippet (last message preview) */
    loadParents();
}

function appendBubble(area, m) {
    const wrap = document.createElement('div');
    wrap.className = `bubble-wrap ${m.sender_role}`;
    const seen = m.sender_role === 'teacher' ? `<i class="bi bi-check2-all"></i>` : '';
    wrap.innerHTML = `
        <div class="bubble ${m.sender_role}">${esc(m.message)}</div>
        <div class="bubble-meta">
            <i class="bi bi-clock"></i>
            ${esc(m.fmt_time)}
            ${seen}
        </div>`;
    area.appendChild(wrap);
}

function appendEndedBanner(area) {
    const div = document.createElement('div');
    div.className = 'conv-ended-banner';
    div.innerHTML = `<i class="bi bi-telephone-x-fill"></i> Conversation has ended — no further messages can be sent.`;
    area.appendChild(div);
}

/* ============================================================
   SEND MESSAGE
============================================================ */
async function sendMessage() {
    if (!activeReqId || convEnded) return;

    const inp = document.getElementById('composeInput');
    const msg = inp.value.trim();
    if (!msg) return;

    inp.value = '';
    inp.style.height = 'auto';
    document.getElementById('sendBtn').disabled = true;

    const d = await post({
        action     : 'send_message',
        request_id : activeReqId,
        message    : msg
    });

    document.getElementById('sendBtn').disabled = false;

    if (!d.ok) {
        toast(d.msg || 'Failed to send. Please try again.', 'err');
        return;
    }

    /* Immediately render the new message without waiting for poll */
    const area = document.getElementById('messagesArea');
    const now  = new Date();
    const fmt  = now.toLocaleString('en-GB', {
        day:'2-digit', month:'short', year:'numeric',
        hour:'2-digit', minute:'2-digit', hour12:true
    });

    const wrap = document.createElement('div');
    wrap.className = 'bubble-wrap teacher';
    wrap.innerHTML = `
        <div class="bubble teacher">${esc(msg)}</div>
        <div class="bubble-meta" style="justify-content:flex-end">
            <i class="bi bi-clock"></i> ${esc(fmt)}
            <i class="bi bi-check2-all"></i>
        </div>`;
    area.appendChild(wrap);
    scrollToBottom();
    lastMsgId = Math.max(lastMsgId, Number(d.new_id));
}

/* ============================================================
   END CONVERSATION
============================================================ */
async function endConversation() {
    if (!activeReqId) return;
    if (!confirm('Are you sure you want to end this conversation? The parent will not be able to send further messages.')) return;

    const btn = document.getElementById('endConvBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Ending…';

    const d = await post({ action: 'end_conversation', request_id: activeReqId });

    if (!d.ok) {
        toast(d.msg || 'Failed to end conversation.', 'err');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-telephone-x-fill"></i> End Conversation';
        return;
    }

    toast('Conversation ended successfully.', 'warn');
    convEnded = true;

    /* Update UI */
    document.getElementById('chatStatusDot').className   = 'conv-ended-dot';
    document.getElementById('composeInput').disabled     = true;
    document.getElementById('composeInput').placeholder  = 'This conversation has ended.';
    document.getElementById('sendBtn').disabled          = true;
    btn.innerHTML = '<i class="bi bi-telephone-x-fill"></i> Ended';

    const area = document.getElementById('messagesArea');
    appendEndedBanner(area);
    scrollToBottom();

    /* Refresh parent list */
    loadParents();
}

/* ============================================================
   INIT
============================================================ */
loadParents();

/* Refresh parent list silently every 15 seconds */
setInterval(loadParents, 15000);
</script>

</body>
</html>