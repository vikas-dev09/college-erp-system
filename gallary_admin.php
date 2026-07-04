<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ── Session Security ──────────────────────────────────────────
if (!isset($_SESSION['last_regenerated']) || time() - $_SESSION['last_regenerated'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regenerated'] = time();
}

// ── CSRF Token ────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("CSRF validation failed.");
    }
}

// ── Database ──────────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '', 'aureon');
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ── Helpers ───────────────────────────────────────────────────
$admin_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin';
$initials   = strtoupper(
    substr($admin_name, 0, 1) .
    substr(strrchr($admin_name, ' ') ?: $admin_name, 1, 1)
);

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Flash Toast ───────────────────────────────────────────────
$toast_msg  = '';
$toast_type = 'success';
if (isset($_SESSION['toast_msg'])) {
    $toast_msg  = $_SESSION['toast_msg'];
    $toast_type = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// ── UPLOAD ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();

    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category']    ?? '');
    $event_date  = trim($_POST['event_date']  ?? '');

    if (empty($title)) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'Title is required.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'Please select an image file.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    $tmp  = $_FILES['image']['tmp_name'];
    $size = $_FILES['image']['size'];
    $orig = $_FILES['image']['name'];

    // Size check 10MB
    if ($size > 10 * 1024 * 1024) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'File too large. Max 10MB allowed.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    // MIME check using finfo
    $finfo       = new finfo(FILEINFO_MIME_TYPE);
    $mime        = $finfo->file($tmp);
    $allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMime)) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'Invalid file type. Only JPG, PNG, WEBP, GIF allowed.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    // Content validation
    if (getimagesize($tmp) === false) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'Uploaded file is not a valid image.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    // Extension check
    $ext      = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowExt)) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'File extension not allowed.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    // Save file
    $uploadDir = 'uploads/gallery/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $safeName   = uniqid('gal_', true) . '.' . $ext;
    $destPath   = $uploadDir . $safeName;
    $humanSize  = $size >= 1048576
        ? round($size / 1048576, 1) . ' MB'
        : round($size / 1024, 1) . ' KB';

    if (!move_uploaded_file($tmp, $destPath)) {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'File upload failed. Check folder permissions.';
        header("Location: " . basename($_SERVER['PHP_SELF']));
        exit;
    }

    // Insert to DB — matching your exact table columns
    $stmt = $conn->prepare(
        "INSERT INTO gallery (category, title, description, image_name, image_path, image_size, event_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $eventDateVal = !empty($event_date) ? $event_date : null;
    $stmt->bind_param("sssssss", $category, $title, $description, $safeName, $destPath, $humanSize, $eventDateVal);

    if ($stmt->execute()) {
        $_SESSION['toast_type'] = 'success';
        $_SESSION['toast_msg']  = 'Image "' . $title . '" uploaded successfully!';
    } else {
        @unlink($destPath);
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verify_csrf();

    $id          = (int)($_POST['edit_id']          ?? 0);
    $title       = trim($_POST['edit_title']        ?? '');
    $description = trim($_POST['edit_description']  ?? '');
    $category    = trim($_POST['edit_category']     ?? '');
    $event_date  = trim($_POST['edit_event_date']   ?? '');

    if ($id > 0 && !empty($title)) {
        $stmt = $conn->prepare(
            "UPDATE gallery SET title=?, description=?, category=?, event_date=? WHERE id=?"
        );
        $eventDateVal = !empty($event_date) ? $event_date : null;
        $stmt->bind_param("ssssi", $title, $description, $category, $eventDateVal, $id);
        if ($stmt->execute()) {
            $_SESSION['toast_type'] = 'success';
            $_SESSION['toast_msg']  = 'Image details updated successfully.';
        } else {
            $_SESSION['toast_type'] = 'error';
            $_SESSION['toast_msg']  = 'Update failed: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['toast_type'] = 'error';
        $_SESSION['toast_msg']  = 'Title is required.';
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();

    $id = (int)($_POST['delete_id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT image_path FROM gallery WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if (!empty($row['image_path']) && file_exists($row['image_path'])) {
                @unlink($row['image_path']);
            }
            $del = $conn->prepare("DELETE FROM gallery WHERE id=?");
            $del->bind_param("i", $id);
            if ($del->execute()) {
                $_SESSION['toast_type'] = 'success';
                $_SESSION['toast_msg']  = 'Image deleted successfully.';
            } else {
                $_SESSION['toast_type'] = 'error';
                $_SESSION['toast_msg']  = 'Delete failed: ' . $del->error;
            }
            $del->close();
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// ── FETCH DATA ────────────────────────────────────────────────
$search    = trim($_GET['search']   ?? '');
$catFilter = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM gallery WHERE 1=1";
if (!empty($search))    $sql .= " AND (title LIKE '%" . $conn->real_escape_string($search) . "%' OR description LIKE '%" . $conn->real_escape_string($search) . "%' OR category LIKE '%" . $conn->real_escape_string($search) . "%')";
if (!empty($catFilter)) $sql .= " AND category = '" . $conn->real_escape_string($catFilter) . "'";
$sql .= " ORDER BY added_on DESC";

$result  = $conn->query($sql);
$images  = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$total   = count($images);

$catRes     = $conn->query("SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

$today = 0;
foreach ($images as $img) {
    if (date('Y-m-d', strtotime($img['added_on'])) === date('Y-m-d')) $today++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gallery Management | AUREON ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
/* ══════════════════════ RESET + ROOT ══════════════════════ */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
    --violet:#7c3aed;--violet-dark:#6d28d9;--violet-light:#a78bfa;
    --violet-pale:#ede9fe;--violet-glow:rgba(124,58,237,.12);
    --orange:#f97316;--orange-pale:#fff7ed;
    --pink:#ec4899;--pink-pale:#fdf2f8;
    --teal:#14b8a6;--teal-pale:#f0fdfa;
    --blue:#3b82f6;--blue-pale:#eff6ff;
    --green:#10b981;--green-pale:#ecfdf5;
    --red:#ef4444;--red-pale:#fef2f2;
    --dark:#1f1635;--text:#334155;--muted:#64748b;--dim:#94a3b8;
    --border:#e2e8f0;--light:#f1f5f9;--white:#fff;
    --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);
    --shadow:0 10px 30px rgba(0,0,0,.06);
    --radius:20px;--radius-md:16px;--radius-sm:12px;--radius-xs:8px;
}
html{scroll-behavior:smooth}
body{
    min-height:100vh;
    font-family:'Inter','Segoe UI',sans-serif;
    background:var(--bg);color:var(--text);
    font-size:16px;display:flex;
}

/* ══════════════════════ SIDEBAR ══════════════════════ */
.sidebar{
    width:220px;
    background:rgba(255,255,255,.55);
    backdrop-filter:blur(14px);
    border-right:1px solid rgba(255,255,255,.7);
    display:flex;flex-direction:column;align-items:center;
    position:fixed;top:0;bottom:0;z-index:100;
    padding:20px 0;transition:all .3s;
}
.sidebar-logo{
    width:68px;height:68px;margin-bottom:28px;
    display:flex;align-items:center;justify-content:center;
}
.sidebar-logo img{
    width:100%;height:100%;object-fit:contain;
    filter:drop-shadow(0 4px 10px rgba(124,58,237,.25));
}
.sidebar-nav{
    flex:1;display:flex;flex-direction:column;
    align-items:center;gap:6px;width:100%;padding:0 10px;
}
.s-item{
    width:100%;
      text-decoration: none;
    height:auto;
    flex-direction:row;
    justify-content:flex-start;
    gap:14px;
    padding:14px 18px;
    font-size:15px;
    border-radius:12px;
}
.s-item i{font-size:20px}
.s-item:hover{background:var(--violet-pale);color:var(--violet);transform:scale(1.08)}
.s-item.active{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;box-shadow:0 8px 24px rgba(124,58,237,.3);
}
.s-item .tip{
    position:absolute;left:76px;top:50%;
    transform:translateY(-50%);
    background:var(--dark);color:white;
    padding:6px 12px;border-radius:8px;
    font-size:13px;font-weight:600;
    white-space:nowrap;opacity:0;pointer-events:none;
    transition:all .2s;z-index:200;
}
.s-item .tip::before{
    content:'';position:absolute;left:-4px;top:50%;
    transform:translateY(-50%);
    border:4px solid transparent;border-right-color:var(--dark);
}
.s-item:hover .tip{opacity:1;left:80px}
.sidebar-bottom{padding:10px}
.s-logout{
    width:100%;
    display:flex;
    align-items:center;
    justify-content:flex-start;
    gap:12px;

    padding:14px 18px;
    border-radius:12px;
    border:none;

    background:#fef2f2;
    color:#ef4444;

    font-size:16px;
    font-weight:600;
    cursor:pointer;
}

/* icon */
.s-logout i{
    font-size:20px;
}

/* hover */
.s-logout:hover{
    background:#ef4444;
    color:white;
}

/* ══════════════════════ MAIN ══════════════════════ */
.main{margin-left:220px;flex:1;min-height:100vh;display:flex;flex-direction:column}

/* Top Header */
.top-header{
    position:sticky;top:0;z-index:50;
    background:rgba(255,255,255,.82);backdrop-filter:blur(14px);
    border-bottom:1px solid var(--light);
    padding:16px 40px;
    display:flex;align-items:center;justify-content:space-between;
}
.header-brand{display:flex;align-items:center;gap:12px}
.header-icon{
    width:42px;height:42px;border-radius:12px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    display:flex;align-items:center;justify-content:center;
    color:white;font-size:17px;
}
.header-title{font-size:18px;font-weight:700;color:var(--dark)}
.header-title span{color:var(--muted);font-weight:500;font-size:14px;margin-left:6px}
.header-right{display:flex;align-items:center;gap:12px}
.profile-avatar{
    width:44px;height:44px;border-radius:12px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;display:flex;align-items:center;justify-content:center;
    font-size:15px;font-weight:700;
}
.profile-info{text-align:right}
.profile-info .pname{font-size:15px;font-weight:600;color:var(--dark)}
.profile-info .prole{font-size:12px;color:var(--muted)}

/* Page Content */
.page-content{padding:36px 42px;flex:1}

/* Page Title */
.page-title{
    display:flex;align-items:center;
    justify-content:space-between;
    margin-bottom:32px;flex-wrap:wrap;gap:14px;
}
.page-title-left{display:flex;align-items:center;gap:14px}
.page-title-icon{
    width:54px;height:54px;border-radius:14px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    display:flex;align-items:center;justify-content:center;
    color:white;font-size:22px;
    box-shadow:0 8px 20px rgba(124,58,237,.2);
}
.page-title h1{font-size:28px;font-weight:800;color:var(--dark)}
.page-title p{font-size:15px;color:var(--muted);margin-top:3px}
.breadcrumb{font-size:14px;color:var(--dim);display:flex;align-items:center;gap:6px}
.breadcrumb a{color:var(--muted);text-decoration:none}
.breadcrumb a:hover{color:var(--violet)}

/* ══════════════════════ STATS ══════════════════════ */
.stats-grid{
    display:grid;grid-template-columns:repeat(4,1fr);
    gap:16px;margin-bottom:28px;
}
.stat-card{
    background:var(--white);border-radius:var(--radius-md);
    padding:22px;box-shadow:var(--shadow);
    transition:all .3s;position:relative;overflow:hidden;
}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 20px 50px rgba(0,0,0,.08)}
.stat-icon{
    width:46px;height:46px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;margin-bottom:14px;
}
.stat-value{font-size:30px;font-weight:800;color:var(--dark)}
.stat-label{font-size:13px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:4px}

/* ══════════════════════ CONTROLS BAR ══════════════════════ */
.controls-bar{
    display:flex;align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;gap:14px;margin-bottom:24px;
}
.search-box{
    display:flex;align-items:center;gap:10px;
    background:var(--white);
    border:1.5px solid var(--border);
    border-radius:30px;padding:10px 20px;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
    transition:all .25s;
}
.search-box:focus-within{border-color:var(--violet);box-shadow:0 0 0 4px var(--violet-glow)}
.search-box i{color:var(--muted);font-size:15px}
.search-box input{
    border:none;background:transparent;outline:none;
    font-size:15px;width:240px;color:var(--dark);font-family:inherit;
}
.search-box input::placeholder{color:var(--dim)}
.controls-right{display:flex;gap:10px;flex-wrap:wrap;align-items:center}

/* ══════════════════════ BUTTONS ══════════════════════ */
.btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:11px 22px;border:none;border-radius:var(--radius-sm);
    font-size:14px;font-weight:700;cursor:pointer;
    font-family:inherit;transition:all .25s;text-decoration:none;
    white-space:nowrap;
}
.btn-violet{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;box-shadow:0 4px 14px rgba(124,58,237,.2);
}
.btn-violet:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(124,58,237,.3)}
.btn-teal{background:linear-gradient(135deg,var(--teal),#0d9488);color:white}
.btn-teal:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(20,184,166,.3)}
.btn-red{background:linear-gradient(135deg,var(--red),#dc2626);color:white}
.btn-red:hover{transform:translateY(-2px)}
.btn-outline{background:var(--white);color:var(--muted);border:1.5px solid var(--border)}
.btn-outline:hover{background:var(--light);color:var(--dark)}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;box-shadow:none!important}

/* ══════════════════════ GALLERY CARD ══════════════════════ */
.gallery-card{
    background:var(--white);border-radius:var(--radius);
    padding:28px;box-shadow:var(--shadow);
}
.gallery-card-head{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:24px;flex-wrap:wrap;gap:10px;
}
.gallery-card-head h3{
    font-size:18px;font-weight:700;color:var(--dark);
    display:flex;align-items:center;gap:10px;
}
.gallery-card-head h3 i{color:var(--violet)}

/* Category filter pills */
.cat-pills{
    display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;
}
.cat-pill{
    padding:7px 18px;border-radius:20px;
    border:1.5px solid var(--border);
    background:var(--white);color:var(--muted);
    font-size:13px;font-weight:600;cursor:pointer;
    transition:all .22s;text-decoration:none;display:inline-block;
}
.cat-pill:hover{border-color:var(--violet);color:var(--violet)}
.cat-pill.active{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;border-color:transparent;
    box-shadow:0 4px 14px rgba(124,58,237,.25);
}

/* Gallery Grid */
.gallery-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
    gap:20px;
}
.gal-item{
    border:1.5px solid var(--border);border-radius:var(--radius-md);
    overflow:hidden;transition:all .3s;background:var(--white);
}
.gal-item:hover{transform:translateY(-5px);box-shadow:0 18px 45px rgba(0,0,0,.09)}
.gal-img-wrap{position:relative;overflow:hidden;cursor:zoom-in}
.gal-img-wrap img{
    width:100%;height:190px;object-fit:cover;
    display:block;transition:transform .4s;
}
.gal-item:hover .gal-img-wrap img{transform:scale(1.06)}
.gal-img-overlay{
    position:absolute;inset:0;
    background:linear-gradient(to bottom,transparent 50%,rgba(15,23,42,.65));
    opacity:0;transition:opacity .3s;
    display:flex;align-items:flex-end;padding:12px;
}
.gal-item:hover .gal-img-overlay{opacity:1}
.gal-size{font-size:11px;color:rgba(255,255,255,.85);font-weight:600}
.gal-body{padding:14px 16px}
.gal-cat{
    display:inline-flex;align-items:center;gap:4px;
    padding:3px 10px;border-radius:20px;
    background:var(--violet-pale);color:var(--violet);
    font-size:11px;font-weight:700;margin-bottom:8px;
}
.gal-title{font-size:15px;font-weight:700;color:var(--dark);margin-bottom:4px;line-height:1.3}
.gal-desc{font-size:13px;color:var(--muted);margin-bottom:8px;line-height:1.5}
.gal-meta{
    display:flex;align-items:center;justify-content:space-between;
    font-size:12px;color:var(--dim);padding-top:8px;
    border-top:1px solid var(--light);
}
.gal-actions{display:flex;gap:6px;margin-top:12px}
.icon-btn{
    width:34px;height:34px;border:none;border-radius:8px;
    cursor:pointer;font-size:14px;
    display:flex;align-items:center;justify-content:center;
    transition:all .22s;
}
.ib-edit{background:var(--violet-pale);color:var(--violet)}
.ib-edit:hover{background:var(--violet);color:white}
.ib-del{background:var(--red-pale);color:var(--red)}
.ib-del:hover{background:var(--red);color:white}

/* Empty State */
.empty-state{
    text-align:center;padding:80px 20px;
    grid-column:1/-1;
}
.empty-state i{font-size:64px;opacity:.15;margin-bottom:18px;display:block;color:var(--violet)}
.empty-state h3{font-size:20px;font-weight:700;color:var(--dark);margin-bottom:8px}
.empty-state p{font-size:15px;color:var(--muted);margin-bottom:22px}

/* ══════════════════════ MODALS ══════════════════════ */
.modal-overlay{
    display:none;position:fixed;inset:0;
    background:rgba(15,23,42,.45);backdrop-filter:blur(6px);
    z-index:1000;align-items:center;justify-content:center;padding:20px;
}
.modal-overlay.open{display:flex}
.modal{
    background:var(--white);border-radius:var(--radius);
    padding:36px;width:100%;max-width:600px;
    max-height:92vh;overflow-y:auto;
    box-shadow:0 30px 70px rgba(0,0,0,.15);
    animation:mIn .3s ease;
}
@keyframes mIn{
    from{opacity:0;transform:scale(.96) translateY(16px)}
    to{opacity:1;transform:scale(1) translateY(0)}
}
.modal-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:26px;
}
.modal-header h3{font-size:20px;font-weight:800;color:var(--dark)}
.modal-close{
    width:38px;height:38px;border:none;border-radius:10px;
    background:var(--light);color:var(--muted);cursor:pointer;
    font-size:17px;display:flex;align-items:center;justify-content:center;
    transition:all .2s;
}
.modal-close:hover{background:var(--border);color:var(--dark)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.fg{display:flex;flex-direction:column;gap:7px}
.fg.full{grid-column:1/-1}
.fg label{
    font-size:14px;font-weight:700;color:var(--muted);
    display:flex;align-items:center;gap:4px;
}
.fg label .req{color:var(--red)}
.fi{
    width:100%;height:50px;
    background:var(--white);border:1.5px solid var(--border);
    border-radius:var(--radius-sm);
    padding:0 16px;font-size:15px;color:var(--dark);
    font-family:inherit;outline:none;transition:all .25s;
    -webkit-appearance:none;
}
.fi:focus{border-color:var(--violet);box-shadow:0 0 0 4px var(--violet-glow)}
.fi::placeholder{color:var(--dim)}
textarea.fi{height:90px;padding:14px 16px;resize:vertical}

/* Upload Zone */
.upload-zone{
    border:2px dashed var(--border);border-radius:var(--radius-sm);
    padding:28px;text-align:center;cursor:pointer;
    transition:all .25s;
}
.upload-zone:hover{border-color:var(--violet-light);background:var(--violet-pale)}
.upload-zone.has-file{border-color:var(--green);background:var(--green-pale)}
.upload-zone input{display:none}
.upload-zone i{font-size:34px;color:var(--dim);margin-bottom:10px;display:block}
.upload-zone p{font-size:14px;color:var(--muted)}
.upload-zone .chosen{font-size:14px;color:var(--violet);font-weight:700;margin-top:8px}
.upload-preview{
    width:100%;max-height:170px;object-fit:cover;
    border-radius:var(--radius-xs);margin-top:14px;display:none;
}
.modal-footer{
    display:flex;justify-content:flex-end;gap:12px;
    margin-top:26px;padding-top:22px;border-top:1px solid var(--light);
}

/* ══════════════════════ LIGHTBOX ══════════════════════ */
.lightbox{
    display:none;position:fixed;inset:0;
    background:rgba(0,0,0,.88);z-index:2000;
    align-items:center;justify-content:center;
}
.lightbox.open{display:flex}
.lightbox img{
    max-width:90vw;max-height:90vh;
    object-fit:contain;border-radius:10px;
    box-shadow:0 20px 60px rgba(0,0,0,.4);
}
.lb-close{
    position:absolute;top:20px;right:20px;
    width:46px;height:46px;border:none;border-radius:50%;
    background:rgba(255,255,255,.15);color:white;
    font-size:20px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    transition:all .2s;
}
.lb-close:hover{background:rgba(255,255,255,.3)}

/* ══════════════════════ TOAST ══════════════════════ */
.toast{
    position:fixed;top:24px;right:24px;z-index:9999;
    min-width:300px;padding:16px 20px;
    border-radius:var(--radius-sm);
    background:var(--white);font-size:15px;font-weight:600;
    display:flex;align-items:center;gap:10px;
    box-shadow:0 15px 40px rgba(0,0,0,.12);
    border-left:4px solid var(--green);
    animation:toastIn .35s ease;
}
.toast.error{border-left-color:var(--red)}
@keyframes toastIn{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:translateX(0)}}

/* ══════════════════════ MOBILE ══════════════════════ */
.mobile-header{
    display:none;position:fixed;top:0;left:0;right:0;
    height:60px;background:rgba(255,255,255,.92);
    backdrop-filter:blur(12px);border-bottom:1px solid var(--light);
    z-index:90;padding:0 16px;
    align-items:center;justify-content:space-between;
}
.mobile-header .mb{display:flex;align-items:center;gap:8px;font-weight:800;font-size:17px;color:var(--violet)}
.mobile-header .mb img{height:30px}
.hamburger{
    width:40px;height:40px;border:none;background:var(--violet-pale);
    border-radius:10px;color:var(--violet);font-size:18px;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:95}

@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){
    .sidebar{transform:translateX(-100%);width:260px;padding:20px;background:rgba(255,255,255,.97);align-items:stretch}
    .sidebar.open{transform:translateX(0)}
    .sidebar .s-item{width:100%;height:auto;flex-direction:row;justify-content:flex-start;gap:12px;padding:13px 14px;font-size:14px}
    .sidebar .s-item .tip{display:none}
    .sidebar .s-logout{width:100%;height:auto;padding:13px;font-size:14px;border-radius:10px;flex-direction:row;gap:12px;justify-content:flex-start}
    .main{margin-left:0}
    .mobile-header{display:flex}
    .overlay.show{display:block}
    .page-content{padding:80px 16px 32px}
    .top-header{display:none}
    .form-grid{grid-template-columns:1fr}
    .page-title{flex-direction:column;align-items:flex-start}
    .controls-bar{flex-direction:column;align-items:flex-start}
    .search-box input{width:180px}
    .gallery-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr))}
}
@media(max-width:540px){
    .stats-grid{grid-template-columns:1fr 1fr}
    .modal{padding:22px}
    body{font-size:15px}
}
/* ===============================
   AUREON ERP LOGO
================================= */

.sidebar-logo{
    width:100%;
    padding:0 18px;
    margin-bottom:24px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}

.aureon-logo{
    width:82px;
    height:82px;
    border-radius:22px;
    background:linear-gradient(135deg,#ede9fe,#fdf2f8);
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    box-shadow:0 10px 25px rgba(124,58,237,.12);
    border:1px solid rgba(255,255,255,.9);
    transition:.35s ease;
}

.aureon-logo:hover{
    transform:translateY(-4px) scale(1.04);
}

.logo-letter{
    font-size:52px;
    font-weight:900;
    color:#7c3aed;
    line-height:1;
}

.logo-cap{
    position:absolute;
    top:10px;
    right:10px;
    font-size:18px;
    color:#f97316;
    transform:rotate(-15deg);
}

.sidebar-logo h2{
    margin-top:14px;
    font-size:18px;
    font-weight:800;
    background:linear-gradient(135deg,#7c3aed,#ec4899);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}
</style>
</head>
<body>

<!-- Mobile Header -->
<div class="mobile-header">
    <div class="mb"><img src="logo.png" alt="AUREON"> AUREON ERP</div>
    <button class="hamburger" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
</div>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>


<!-- ══════════ SIDEBAR ══════════ -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-logo">

        <div class="aureon-logo">
            <span class="logo-letter">A</span>
            <i class="fa-solid fa-graduation-cap logo-cap"></i>
        </div>

        <h2>AUREON ERP</h2>

    </div>

    <nav class="sidebar-nav">

    <a href="super_admin.php" class="s-item active">
    <i class="fa-solid fa-house"></i>
    <span>Dashboard</span>
</a>

<a href="add_student.php" class="s-item">
    <i class="fa-solid fa-user-graduate"></i>
    <span>Students</span>
</a>

<a href="gallery_view.php" class="s-item">
    <i class="fa-solid fa-images"></i>
    <span>Gallery</span>
</a>


</nav>
    <div class="sidebar-bottom">
       <button class="s-logout"
    onclick="if(confirm('Are you sure you want to logout?')) location.href='logout.php'">
    <i class="fa-solid fa-right-from-bracket"></i>
    <span>Logout</span>
</button>
    </div>
</aside>


<!-- ══════════ MAIN ══════════ -->
<main class="main">

    <!-- Top Header -->
    <div class="top-header">
        <div class="header-brand">
            <div class="header-icon"><i class="fa-solid fa-images"></i></div>
            <div class="header-title">AUREON ERP <span>| GALLERY MANAGEMENT</span></div>
        </div>
        <div class="header-right">
            <div class="profile-info">
                <div class="pname"><?= esc($admin_name) ?></div>
                <div class="prole">Super Admin</div>
            </div>
            <div class="profile-avatar"><?= esc($initials) ?></div>
        </div>
    </div>

    <div class="page-content">

        <!-- Page Title -->
        <div class="page-title">
            <div class="page-title-left">
                <div class="page-title-icon"><i class="fa-solid fa-images"></i></div>
                <div>
                    <h1>Gallery Management</h1>
                    <p>Upload and manage college event photos</p>
                </div>
            </div>
            <div class="breadcrumb">
                <a href="super_admin.php"><i class="fa-solid fa-house" style="font-size:12px"></i></a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <a href="super_admin.php">Dashboard</a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <span style="color:var(--violet)">Gallery</span>
            </div>
        </div>


        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,var(--violet),var(--violet-dark))">
                    <i class="fa-solid fa-images"></i>
                </div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">Total Images</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,var(--blue),#2563eb)">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div class="stat-value"><?= count($categories) ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,var(--green),#059669)">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?= $today ?></div>
                <div class="stat-label">Added Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,var(--orange),#ea580c)">
                    <i class="fa-solid fa-eye"></i>
                </div>
                <div class="stat-value"><?= count($images) ?></div>
                <div class="stat-label">Showing Now</div>
            </div>
        </div>


        <!-- Controls Bar -->
        <div class="controls-bar">
            <form method="GET" action="">
                <?php if($catFilter): ?>
                <input type="hidden" name="category" value="<?= esc($catFilter) ?>">
                <?php endif; ?>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search"
                           placeholder="Search images, categories…"
                           value="<?= esc($search) ?>">
                </div>
            </form>
            <div class="controls-right">
                <button class="btn btn-violet" onclick="openUploadModal()">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Image
                </button>
                <a href="super_admin.php" class="btn btn-outline">
                    <i class="fa-solid fa-house"></i> Dashboard
                </a>
            </div>
        </div>


        <!-- Gallery Card -->
        <div class="gallery-card">
            <div class="gallery-card-head">
                <h3><i class="fa-solid fa-images"></i> Photo Gallery</h3>
                <span style="font-size:14px;color:var(--muted)"><?= count($images) ?> image(s) found</span>
            </div>

            <!-- Category Filter Pills -->
            <?php if(!empty($categories)): ?>
            <div class="cat-pills">
                <a href="?<?= $search?'search='.urlencode($search):'' ?>"
                   class="cat-pill <?= empty($catFilter)?'active':'' ?>">
                    All
                </a>
                <?php foreach($categories as $c): ?>
                <a href="?category=<?= urlencode($c['category']) ?><?= $search?'&search='.urlencode($search):'' ?>"
                   class="cat-pill <?= $catFilter===$c['category']?'active':'' ?>">
                    <?= esc($c['category']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Gallery Grid -->
            <div class="gallery-grid">
                <?php if(empty($images)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-images"></i>
                    <h3>No Images Found</h3>
                    <p>
                        <?= (!empty($search)||!empty($catFilter))
                            ? 'No images match your search or filter.'
                            : 'Start by uploading your first school event photo.' ?>
                    </p>
                    <button class="btn btn-violet" onclick="openUploadModal()">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload First Image
                    </button>
                </div>
                <?php else: ?>
                <?php foreach($images as $img): ?>
                <div class="gal-item">
                    <div class="gal-img-wrap"
                         onclick="openLightbox('<?= esc($img['image_path']) ?>')">
                        <img src="<?= esc($img['image_path']) ?>"
                             alt="<?= esc($img['title']) ?>"
                             loading="lazy">
                        <div class="gal-img-overlay">
                            <span class="gal-size"><?= esc($img['image_size'] ?? '') ?></span>
                        </div>
                    </div>
                    <div class="gal-body">
                        <?php if(!empty($img['category'])): ?>
                        <div class="gal-cat">
                            <i class="fa-solid fa-tag"></i>
                            <?= esc($img['category']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="gal-title"><?= esc($img['title']) ?></div>
                        <?php if(!empty($img['description'])): ?>
                        <div class="gal-desc">
                            <?= esc(mb_substr($img['description'],0,80)) ?><?= mb_strlen($img['description'])>80?'…':'' ?>
                        </div>
                        <?php endif; ?>
                        <div class="gal-meta">
                            <span><?= esc($img['image_size'] ?? '—') ?></span>
                            <span><?= date('d M Y', strtotime($img['added_on'])) ?></span>
                        </div>
                        <div class="gal-actions">
                            <button class="icon-btn ib-edit"
                                title="Edit"
                                onclick="openEditModal(
                                    <?= (int)$img['id'] ?>,
                                    '<?= esc($img['title']) ?>',
                                    '<?= esc($img['description'] ?? '') ?>',
                                    '<?= esc($img['category'] ?? '') ?>',
                                    '<?= esc($img['event_date'] ?? '') ?>'
                                )">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="icon-btn ib-del"
                                title="Delete"
                                onclick="confirmDelete(<?= (int)$img['id'] ?>, '<?= esc($img['title']) ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /gallery-card -->

    </div><!-- /page-content -->
</main>


<!-- ══════════ UPLOAD MODAL ══════════ -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <i class="fa-solid fa-cloud-arrow-up"
                   style="color:var(--violet);margin-right:8px"></i>
                Upload New Image
            </h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="action"     value="upload">
            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

            <div class="form-grid">
                <div class="fg full">
                    <label>Title <span class="req">*</span></label>
                    <input type="text" name="title" class="fi"
                           id="up_title" placeholder="Enter image title" required>
                </div>

                <div class="fg">
                    <label>Category</label>
                    <input type="text" name="category" class="fi"
                           placeholder="e.g. Sports Day, Annual Day"
                           list="catSuggestions">
                    <datalist id="catSuggestions">
                        <?php foreach($categories as $c): ?>
                        <option value="<?= esc($c['category']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="fg">
                    <label>Event Date</label>
                    <input type="date" name="event_date" class="fi"
                           max="<?= date('Y-m-d') ?>">
                </div>

                <div class="fg full">
                    <label>Description</label>
                    <textarea name="description" class="fi"
                              placeholder="Brief description (optional)"></textarea>
                </div>

                <div class="fg full">
                    <label>Image File <span class="req">*</span></label>
                    <div class="upload-zone"
                         id="uploadZone"
                         onclick="document.getElementById('imgInput').click()">
                        <input type="file" id="imgInput" name="image"
                               accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                               required>
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Click to select — JPG, PNG, WEBP • Max 10MB</p>
                        <div class="chosen" id="imgChosen">No file chosen</div>
                        <img class="upload-preview" id="imgPreview" alt="Preview">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline"
                        onclick="closeModal('uploadModal')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-violet"
                        id="uploadBtn" disabled>
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Image
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════ EDIT MODAL ══════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <i class="fa-solid fa-pen"
                   style="color:var(--violet);margin-right:8px"></i>
                Edit Image Details
            </h3>
            <button class="modal-close" onclick="closeModal('editModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action"     value="edit">
            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
            <input type="hidden" name="edit_id"    id="edit_id">

            <div class="form-grid">
                <div class="fg full">
                    <label>Title <span class="req">*</span></label>
                    <input type="text" name="edit_title" class="fi"
                           id="edit_title" placeholder="Image title" required>
                </div>
                <div class="fg">
                    <label>Category</label>
                    <input type="text" name="edit_category" class="fi"
                           id="edit_category" placeholder="Category"
                           list="catSuggestions">
                </div>
                <div class="fg">
                    <label>Event Date</label>
                    <input type="date" name="edit_event_date" class="fi"
                           id="edit_event_date" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="fg full">
                    <label>Description</label>
                    <textarea name="edit_description" class="fi"
                              id="edit_description"
                              placeholder="Description"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline"
                        onclick="closeModal('editModal')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-teal">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════ DELETE FORM (hidden) ══════════ -->
<form method="POST" id="deleteForm" style="display:none">
    <input type="hidden" name="action"     value="delete">
    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
    <input type="hidden" name="delete_id"  id="delete_id">
</form>


<!-- ══════════ LIGHTBOX ══════════ -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lb-close" onclick="closeLightbox()">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img id="lbImg" src="" alt="Full Preview">
</div>


<!-- ══════════ TOAST ══════════ -->
<?php if($toast_msg): ?>
<div class="toast <?= $toast_type==='error'?'error':'' ?>" id="toastBox">
    <i class="fa-solid fa-<?= $toast_type==='success'?'circle-check':'triangle-exclamation' ?>"
       style="font-size:20px;color:<?= $toast_type==='success'?'var(--green)':'var(--red)' ?>"></i>
    <?= esc($toast_msg) ?>
</div>
<?php endif; ?>


<script>
// ── Sidebar ──────────────────────────────────────────────────
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

// ── Modal Open/Close ──────────────────────────────────────────
function openUploadModal(){
    document.getElementById('uploadModal').classList.add('open');
    setTimeout(()=>document.getElementById('up_title').focus(), 200);
}

function openEditModal(id, title, desc, cat, evDate){
    document.getElementById('edit_id').value          = id;
    document.getElementById('edit_title').value       = title;
    document.getElementById('edit_description').value = desc;
    document.getElementById('edit_category').value    = cat;
    document.getElementById('edit_event_date').value  = evDate;
    document.getElementById('editModal').classList.add('open');
    setTimeout(()=>document.getElementById('edit_title').focus(), 200);
}

function closeModal(id){
    document.getElementById(id).classList.remove('open');
}

// Close modals on backdrop click
document.querySelectorAll('.modal-overlay').forEach(el=>{
    el.addEventListener('click', function(e){
        if(e.target===this) this.classList.remove('open');
    });
});

// ── Delete Confirmation ───────────────────────────────────────
function confirmDelete(id, title){
    if(!confirm('Delete image:\n"' + title + '"\n\nThis cannot be undone.')) return;
    document.getElementById('delete_id').value = id;
    document.getElementById('deleteForm').submit();
}

// ── File Input Handler ────────────────────────────────────────
document.getElementById('imgInput').addEventListener('change', function(){
    const file = this.files[0];
    const btn  = document.getElementById('uploadBtn');
    const zone = document.getElementById('uploadZone');
    const prev = document.getElementById('imgPreview');
    const chosen = document.getElementById('imgChosen');

    if(!file){
        chosen.textContent = 'No file chosen';
        prev.style.display = 'none';
        zone.classList.remove('has-file');
        btn.disabled = true;
        return;
    }

    // Client-side size check
    if(file.size > 10*1024*1024){
        alert('File is too large. Maximum allowed is 10MB.');
        this.value = '';
        btn.disabled = true;
        zone.classList.remove('has-file');
        return;
    }

    chosen.textContent = file.name + ' (' + (file.size >= 1048576
        ? (file.size/1048576).toFixed(1)+' MB'
        : (file.size/1024).toFixed(1)+' KB') + ')';
    zone.classList.add('has-file');
    btn.disabled = false;

    // Live preview
    const reader = new FileReader();
    reader.onload = e => {
        prev.src = e.target.result;
        prev.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// ── Lightbox ──────────────────────────────────────────────────
function openLightbox(src){
    document.getElementById('lbImg').src = src;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox(){
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
// Close lightbox on Escape
document.addEventListener('keydown', e=>{
    if(e.key === 'Escape'){
        closeLightbox();
        document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open'));
    }
});

// ── Toast Auto-hide ───────────────────────────────────────────
const toast = document.getElementById('toastBox');
if(toast){
    setTimeout(()=>{
        toast.style.transition = 'opacity .5s, transform .5s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(24px)';
        setTimeout(()=>toast.remove(), 500);
    }, 3500);
}
</script>

</body>
</html>