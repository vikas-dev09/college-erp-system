<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db_host = 'localhost';
$db_name = 'aureon';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// Auto-create table
$pdo->exec("CREATE TABLE IF NOT EXISTS `library_resources` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `title`          VARCHAR(255) NOT NULL,
    `author`         VARCHAR(255) DEFAULT NULL,
    `published_year` YEAR        DEFAULT NULL,
    `level`          ENUM('PUC','UG','MCA','General') NOT NULL DEFAULT 'General',
    `year_sem`       VARCHAR(50)  DEFAULT NULL,
    `subject_group`  VARCHAR(100) DEFAULT NULL,
    `subject`        VARCHAR(100) DEFAULT NULL,
    `type`           ENUM('Book','Question Paper','Lab Manual','Project','Story/Moral','Journal','Article') NOT NULL DEFAULT 'Book',
    `file_name`      VARCHAR(255) DEFAULT NULL,
    `file_size`      VARCHAR(50)  DEFAULT NULL,
    `file_path`      VARCHAR(500) DEFAULT NULL,
    `added_on`       DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$admin_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin';
$initials   = strtoupper(substr($admin_name,0,1) . substr(strrchr($admin_name,' ') ?: $admin_name,1,1));

$search     = trim($_GET['search'] ?? '');
$filter     = $_GET['filter'] ?? 'all';
$alert_msg  = '';
$alert_type = '';
$open_modal = false;

// DELETE
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del = (int)$_GET['delete'];
    $st  = $pdo->prepare("SELECT file_path FROM library_resources WHERE id=?");
    $st->execute([$del]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ($row['file_path'] && file_exists($row['file_path'])) unlink($row['file_path']);
        $pdo->prepare("DELETE FROM library_resources WHERE id=?")->execute([$del]);
    }
    header("Location: library.php?filter=".urlencode($filter)."&search=".urlencode($search)."&deleted=1");
    exit;
}
if (isset($_GET['deleted'])) { $alert_msg = "Resource deleted successfully."; $alert_type = "success"; }

// ADD RESOURCE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    $errors = [];
    $title         = trim($_POST['title']          ?? '');
    $author        = trim($_POST['author']         ?? '');
    $pub_year      = trim($_POST['published_year'] ?? '');
    $level         = trim($_POST['level']          ?? '');
    $year_sem      = trim($_POST['year_sem']       ?? '');
    $subject_group = trim($_POST['subject_group']  ?? '');
    $subject       = trim($_POST['subject']        ?? '');
    $type          = trim($_POST['type']           ?? '');

    if (empty($title))         $errors[] = "Title is required.";
    if (empty($level))         $errors[] = "Level is required.";
    if (empty($year_sem))      $errors[] = "Year / Semester is required.";
    if (empty($subject_group)) $errors[] = "Subject Group is required.";
    if (empty($subject))       $errors[] = "Subject is required.";
    if (empty($type))          $errors[] = "Type is required.";

    $file_name = $file_size = $file_path = null;

    if (!empty($_FILES['resource_file']['name'])) {
        $max   = ($type === 'Question Paper') ? 50*1024*1024 : 10*1024*1024;
        $allow = ['pdf','doc','docx','jpg','jpeg','png'];
        $ext   = strtolower(pathinfo($_FILES['resource_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,$allow))            $errors[] = "Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.";
        elseif ($_FILES['resource_file']['size'] > $max) $errors[] = "File too large. Max: ".($type==='Question Paper'?'50MB':'10MB').".";

        if (empty($errors)) {
            $dir = 'uploads/library/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $safe = uniqid('lib_',true).'.'.$ext;
            $dest = $dir.$safe;
            if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $dest)) {
                $file_name = $_FILES['resource_file']['name'];
                $file_size = round($_FILES['resource_file']['size']/1024, 1).' KB';
                $file_path = $dest;
            } else { $errors[] = "Upload failed."; }
        }
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO library_resources
            (title,author,published_year,level,year_sem,subject_group,subject,type,file_name,file_size,file_path)
            VALUES(?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$title,$author,$pub_year,$level,$year_sem,$subject_group,$subject,$type,$file_name,$file_size,$file_path]);
        $alert_msg  = "Resource \"$title\" added successfully!";
        $alert_type = "success";
    } else {
        $alert_msg  = implode(' ', $errors);
        $alert_type = "error";
        $open_modal = true;
    }
}

// COUNTS
$totalCount = $pdo->query("SELECT COUNT(*) FROM library_resources")->fetchColumn();
$bookCount  = $pdo->query("SELECT COUNT(*) FROM library_resources WHERE type='Book'")->fetchColumn();
$qpCount    = $pdo->query("SELECT COUNT(*) FROM library_resources WHERE type='Question Paper'")->fetchColumn();
$pucCount   = $pdo->query("SELECT COUNT(*) FROM library_resources WHERE level='PUC'")->fetchColumn();
$ugCount    = $pdo->query("SELECT COUNT(*) FROM library_resources WHERE level='UG'")->fetchColumn();
$mcaCount   = $pdo->query("SELECT COUNT(*) FROM library_resources WHERE level='MCA'")->fetchColumn();

// FETCH
$where = []; $params = [];
if (!empty($search)) {
    $where[] = "(title LIKE ? OR author LIKE ? OR subject LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params,[$s,$s,$s]);
}
$fmap = [
    'books'           => "type='Book'",
    'question_papers' => "type='Question Paper'",
    'projects'        => "type='Project'",
    'labs'            => "type='Lab Manual'",
    'journals'        => "type='Journal'",
    'stories'         => "type='Story/Moral'",
    'puc'             => "level='PUC'",
    'ug'              => "level='UG'",
    'mca'             => "level='MCA'",
];
if (isset($fmap[$filter])) $where[] = $fmap[$filter];
$sql = "SELECT * FROM library_resources".($where ? " WHERE ".implode(" AND ",$where) : "")." ORDER BY added_on DESC";
$st  = $pdo->prepare($sql); $st->execute($params);
$resources = $st->fetchAll(PDO::FETCH_ASSOC);

$badgeColor = [
    'Book'          => ['#ede9fe','#7c3aed'],
    'Question Paper'=> ['#fef2f2','#ef4444'],
    'Lab Manual'    => ['#f0fdfa','#14b8a6'],
    'Project'       => ['#fff7ed','#f97316'],
    'Story/Moral'   => ['#fdf2f8','#ec4899'],
    'Journal'       => ['#eff6ff','#3b82f6'],
    'Article'       => ['#ecfdf5','#10b981'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Library Management | AUREON ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
/* ══════════════════════════════════════════
   ROOT — exact Add Teacher theme
══════════════════════════════════════════ */
*{margin:0;padding:0;box-sizing:border-box}
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
    --border:#e2e8f0;--light:#f1f5f9;--white:#ffffff;
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

/* ══════════════════════════════════════════
   SIDEBAR — identical to Add Teacher
══════════════════════════════════════════ */
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
}
.sidebar-logo img{
    width:100%;height:100%;object-fit:contain;
    filter:drop-shadow(0 4px 10px rgba(124,58,237,.2));
}
.sidebar-nav{
    flex:1;display:flex;flex-direction:column;
    align-items:center;gap:6px;width:100%;padding:0 10px;
}
.s-item{
    width:100%;
    height:auto;
    border-radius:16px;
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px 16px;
    font-size:14px;
    font-weight:600;
    color:var(--muted);
    text-decoration:none;
    transition:all .25s;
    position:relative;
}
.s-item i{font-size:20px;transition:all .25s}
.s-item:hover{background:var(--violet-pale);color:var(--violet);transform:scale(1.08)}
/* 🔴 Logout Special Style */
.logout-item {
    color: var(--red);
}

.logout-item:hover {
    background: var(--red-pale);
    color: var(--red);
    transform: scale(1.08);
}

.logout-item.active {
    background: linear-gradient(135deg, var(--red), #dc2626);
    color: white;
}
.s-item.active{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;box-shadow:0 8px 24px rgba(124,58,237,.3);
}

.sidebar-bottom{padding:10px}
.s-logout{
    width:52px;height:52px;border-radius:12px;border:none;
    background:var(--red-pale);color:var(--red);
    font-size:20px;cursor:pointer;transition:all .25s;
    display:flex;align-items:center;justify-content:center;
}
.s-logout:hover{background:rgba(239,68,68,.15);transform:scale(1.08)}

/* ══════════════════════════════════════════
   MAIN
══════════════════════════════════════════ */
.main{margin-left:220px;flex:1;min-height:100vh;display:flex;flex-direction:column}

/* ── TOP HEADER ── */
.top-header{
    position:sticky;top:0;z-index:50;
    background:rgba(255,255,255,.8);
    backdrop-filter:blur(14px);
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

/* ── PAGE CONTENT ── */
.page-content{padding:36px 42px;flex:1}

/* ── PAGE TITLE ── */
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
.breadcrumb{
    font-size:14px;color:var(--dim);
    display:flex;align-items:center;gap:6px;
}
.breadcrumb a{color:var(--muted);text-decoration:none}
.breadcrumb a:hover{color:var(--violet)}

/* ── ALERTS ── */
.alert{
    padding:18px 22px;border-radius:var(--radius-sm);
    margin-bottom:28px;font-size:15px;font-weight:600;
    display:flex;align-items:center;gap:12px;
    animation:slideDown .35s ease;
}
.alert i{font-size:20px}
.alert-success{background:var(--green-pale);border:1px solid rgba(16,185,129,.2);color:var(--green)}
.alert-error  {background:var(--red-pale);border:1px solid rgba(239,68,68,.2);color:var(--red)}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

/* ── STATS GRID ── */
.stats-grid{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:16px;margin-bottom:28px;
}
.stat-card{
    background:var(--white);border-radius:var(--radius-md);
    padding:22px;box-shadow:var(--shadow);
    transition:all .3s;cursor:default;position:relative;overflow:hidden;
}
.stat-card::after{
    content:'';position:absolute;
    bottom:-24px;right:-24px;
    width:80px;height:80px;border-radius:50%;
    opacity:.08;
}
.stat-card:nth-child(1)::after{background:var(--violet)}
.stat-card:nth-child(2)::after{background:var(--blue)}
.stat-card:nth-child(3)::after{background:var(--red)}
.stat-card:nth-child(4)::after{background:var(--orange)}
.stat-card:nth-child(5)::after{background:var(--teal)}
.stat-card:nth-child(6)::after{background:var(--green)}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 20px 50px rgba(0,0,0,.08)}
.stat-icon{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;color:white;margin-bottom:14px;
}
.stat-card:nth-child(1) .stat-icon{background:linear-gradient(135deg,var(--violet),var(--violet-dark))}
.stat-card:nth-child(2) .stat-icon{background:linear-gradient(135deg,var(--blue),#2563eb)}
.stat-card:nth-child(3) .stat-icon{background:linear-gradient(135deg,var(--red),#dc2626)}
.stat-card:nth-child(4) .stat-icon{background:linear-gradient(135deg,var(--orange),#ea580c)}
.stat-card:nth-child(5) .stat-icon{background:linear-gradient(135deg,var(--teal),#0d9488)}
.stat-card:nth-child(6) .stat-icon{background:linear-gradient(135deg,var(--green),#059669)}
.stat-value{font-size:30px;font-weight:800;color:var(--dark)}
.stat-label{font-size:13px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:4px}

/* ── CONTROLS BAR ── */
.controls-bar{
    display:flex;align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;gap:14px;
    margin-bottom:20px;
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
    font-size:15px;width:260px;color:var(--dark);font-family:inherit;
}
.search-box input::placeholder{color:var(--dim)}
.controls-right{display:flex;gap:10px;flex-wrap:wrap}

/* ── BTN ── */
.btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:11px 22px;border:none;border-radius:var(--radius-sm);
    font-size:14px;font-weight:700;cursor:pointer;
    font-family:inherit;transition:all .25s;text-decoration:none;
}
.btn-violet{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;box-shadow:0 4px 14px rgba(124,58,237,.2);
}
.btn-violet:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(124,58,237,.3)}
.btn-outline{
    background:var(--white);color:var(--muted);
    border:1.5px solid var(--border);
}
.btn-outline:hover{background:var(--light);color:var(--dark)}
.btn-danger{
    background:linear-gradient(135deg,var(--red),#dc2626);
    color:white;box-shadow:0 4px 14px rgba(239,68,68,.15);
}
.btn-danger:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(239,68,68,.25)}

/* ── FILTER TABS ── */
.filter-tabs{
    display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;
}
.ftab{
    padding:9px 20px;border-radius:20px;
    border:1.5px solid var(--border);
    background:var(--white);color:var(--muted);
    font-size:14px;font-weight:600;
    cursor:pointer;transition:all .22s;
    text-decoration:none;display:inline-block;
}
.ftab:hover{border-color:var(--violet);color:var(--violet)}
.ftab.active{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;border-color:transparent;
    box-shadow:0 4px 14px rgba(124,58,237,.25);
}

/* ── TABLE CARD ── */
.table-card{
    background:var(--white);border-radius:var(--radius);
    padding:28px;box-shadow:var(--shadow);
}
.table-card-head{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:20px;flex-wrap:wrap;gap:10px;
}
.table-card-head h3{font-size:18px;font-weight:700;color:var(--dark)}
.table-card-head span{font-size:14px;color:var(--muted)}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:960px}
thead th{
    text-align:left;font-size:12px;font-weight:700;
    color:var(--muted);text-transform:uppercase;letter-spacing:.5px;
    padding:13px 16px;background:var(--light);
    border-bottom:2px solid var(--border);
}
thead th:first-child{border-radius:var(--radius-sm) 0 0 var(--radius-sm)}
thead th:last-child{border-radius:0 var(--radius-sm) var(--radius-sm) 0}
tbody td{
    padding:15px 16px;border-bottom:1px solid var(--light);
    font-size:15px;vertical-align:middle;
}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:#fafbfe}
.title-main{font-weight:700;color:var(--dark);font-size:15px}
.title-sub{font-size:12px;color:var(--dim);margin-top:2px}
.badge{
    display:inline-flex;align-items:center;gap:4px;
    padding:5px 12px;border-radius:20px;
    font-size:12px;font-weight:700;
}
.file-link{
    display:flex;align-items:center;gap:8px;font-size:14px;
}
.file-link a{color:var(--violet);font-weight:600}
.file-link a:hover{text-decoration:underline}
.file-size{font-size:12px;color:var(--dim)}
.del-btn{
    width:36px;height:36px;border:none;border-radius:8px;
    background:var(--red-pale);color:var(--red);
    cursor:pointer;font-size:15px;
    display:flex;align-items:center;justify-content:center;
    transition:all .22s;
}
.del-btn:hover{background:var(--red);color:white;transform:scale(1.1)}

/* Empty State */
.empty-state{
    text-align:center;padding:70px 20px;color:var(--muted);
}
.empty-state i{font-size:60px;opacity:.2;margin-bottom:18px;display:block}
.empty-state h3{font-size:20px;font-weight:700;color:var(--dark);margin-bottom:8px}
.empty-state p{font-size:15px;margin-bottom:24px}

/* ══════════════════════════════════════════
   MODAL
══════════════════════════════════════════ */
.modal-overlay{
    display:none;position:fixed;inset:0;
    background:rgba(15,23,42,.45);
    backdrop-filter:blur(6px);
    z-index:1000;align-items:center;justify-content:center;padding:20px;
}
.modal-overlay.open{display:flex}
.modal{
    background:var(--white);border-radius:var(--radius);
    padding:36px;width:100%;max-width:640px;
    max-height:92vh;overflow-y:auto;
    box-shadow:0 30px 70px rgba(0,0,0,.15);
    animation:mIn .3s ease;
}
@keyframes mIn{from{opacity:0;transform:scale(.96) translateY(16px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:26px;
}
.modal-header h3{font-size:20px;font-weight:800;color:var(--dark)}
.modal-close{
    width:38px;height:38px;border:none;border-radius:10px;
    background:var(--light);color:var(--muted);
    cursor:pointer;font-size:17px;
    display:flex;align-items:center;justify-content:center;
    transition:all .2s;
}
.modal-close:hover{background:var(--border);color:var(--dark)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.fg{display:flex;flex-direction:column;gap:7px}
.fg.full{grid-column:1/-1}
.fg label{font-size:14px;font-weight:700;color:var(--muted);display:flex;align-items:center;gap:4px}
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
select.fi{
    cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;
}
.upload-zone{
    border:2px dashed var(--border);border-radius:var(--radius-sm);
    padding:24px;text-align:center;cursor:pointer;transition:all .22s;
}
.upload-zone:hover{border-color:var(--violet-light)}
.upload-zone input{display:none}
.upload-zone i{font-size:32px;color:var(--dim);margin-bottom:8px;display:block}
.upload-zone p{font-size:14px;color:var(--muted)}
.upload-zone .chosen{font-size:14px;color:var(--violet);font-weight:600;margin-top:8px}
.size-note{font-size:12px;color:var(--dim);margin-top:6px}
.modal-footer{display:flex;justify-content:flex-end;gap:12px;margin-top:26px;padding-top:22px;border-top:1px solid var(--light)}

/* ══════════════════════════════════════════
   MOBILE
══════════════════════════════════════════ */
.mobile-header{
    display:none;position:fixed;top:0;left:0;right:0;
    height:60px;background:rgba(255,255,255,.92);
    backdrop-filter:blur(12px);border-bottom:1px solid var(--light);
    z-index:90;padding:0 16px;align-items:center;justify-content:space-between;
}
.mobile-header .mb{display:flex;align-items:center;gap:8px;font-weight:800;font-size:17px;color:var(--violet)}
.mobile-header .mb img{height:30px}
.hamburger{
    width:40px;height:40px;border:none;background:var(--violet-pale);
    border-radius:10px;color:var(--violet);font-size:18px;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:95}

@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){
    .sidebar{transform:translateX(-100%);width:260px;padding:20px;background:rgba(255,255,255,.97);align-items:stretch}
    .sidebar.open{transform:translateX(0)}
    .sidebar .s-item{width:100%;height:auto;flex-direction:row;justify-content:flex-start;gap:12px;padding:13px 14px;font-size:14px}
    .sidebar .s-item .tip{display:none}
    .sidebar .s-logout{width:100%;height:auto;padding:13px;font-size:14px;border-radius:10px;flex-direction:row;justify-content:flex-start;gap:12px}
    .main{margin-left:0}
    .mobile-header{display:flex}
    .overlay.show{display:block}
    .page-content{padding:80px 16px 32px}
    .top-header{display:none}
    .stats-grid{grid-template-columns:repeat(2,1fr)}
    .form-grid{grid-template-columns:1fr}
    .page-title{flex-direction:column;align-items:flex-start;gap:10px}
    .controls-bar{flex-direction:column;align-items:flex-start}
    .search-box input{width:180px}
}
@media(max-width:540px){
    .stats-grid{grid-template-columns:1fr 1fr}
    .modal{padding:20px}
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


<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><img src="logo.png" alt="AUREON ERP"></div>

<nav class="sidebar-nav">
    <a href="super_admin.php" class="s-item">
        <i class="fa-solid fa-grid-2"></i>
        <span>Dashboard</span>
        
    </a>

    <a href="add_student.php" class="s-item">
        <i class="fa-solid fa-user-plus"></i>
        <span>Students</span>
        
    </a>

   

    <a href="library.php" class="s-item active">
        <i class="fa-solid fa-book"></i>
        <span>Library</span>
        
    </a>

    

   
</nav>

   <a href="logout.php" class="s-item logout-item"
   onclick="return confirm('Logout?')">
    <i class="fa-solid fa-right-from-bracket"></i>
    <span>Logout</span>
    
</a>
</aside>


<!-- ═══════════════ MAIN ═══════════════ -->
<main class="main">

    <!-- Top Header -->
    <div class="top-header">
        <div class="header-brand">
            <div class="header-icon"><i class="fa-solid fa-book"></i></div>
            <div class="header-title">
                AUREON ERP <span>| LIBRARY MANAGEMENT</span>
            </div>
        </div>
        <div class="header-right">
            <div class="profile-info">
                <div class="pname"><?= htmlspecialchars($admin_name) ?></div>
                <div class="prole">Super Admin</div>
            </div>
            <div class="profile-avatar"><?= $initials ?></div>
        </div>
    </div>

    <div class="page-content">

        <!-- Page Title -->
        <div class="page-title">
            <div class="page-title-left">
                <div class="page-title-icon"><i class="fa-solid fa-book-open"></i></div>
                <div>
                    <h1>Library Management</h1>
                    <p>Manage all academic resources</p>
                </div>
            </div>
            <div class="breadcrumb">
                <a href="super_admin.php"><i class="fa-solid fa-house" style="font-size:12px"></i></a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <a href="super_admin.php">Dashboard</a>
                <i class="fa-solid fa-chevron-right" style="font-size:9px"></i>
                <span style="color:var(--violet)">Library</span>
            </div>
        </div>


        <!-- Alert -->
        <?php if($alert_msg): ?>
        <div class="alert alert-<?= $alert_type ?>" id="alertBox">
            <i class="fa-solid fa-<?= $alert_type==='success'?'circle-check':'triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($alert_msg) ?>
        </div>
        <?php endif; ?>


        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
                <div class="stat-value"><?= number_format($totalCount) ?></div>
                <div class="stat-label">Total Resources</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-book"></i></div>
                <div class="stat-value"><?= number_format($bookCount) ?></div>
                <div class="stat-label">Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div class="stat-value"><?= number_format($qpCount) ?></div>
                <div class="stat-label">Question Papers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div class="stat-value"><?= number_format($pucCount) ?></div>
                <div class="stat-label">PUC</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div class="stat-value"><?= number_format($ugCount) ?></div>
                <div class="stat-label">UG / BCA</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-laptop-code"></i></div>
                <div class="stat-value"><?= number_format($mcaCount) ?></div>
                <div class="stat-label">MCA / PG</div>
            </div>
        </div>


        <!-- Controls Bar -->
        <div class="controls-bar">
            <form method="GET" action="">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Search title, author, subject…" value="<?= htmlspecialchars($search) ?>">
                </div>
            </form>
            <div class="controls-right">
                <button class="btn btn-violet" onclick="openModal()">
                    <i class="fa-solid fa-plus"></i> Add Resource
                </button>
                <a href="super_admin.php" class="btn btn-outline">
                    <i class="fa-solid fa-house"></i> Dashboard
                </a>
            </div>
        </div>


        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <?php
            $tabs = [
                'all'             => 'All Resources',
                'books'           => 'Books',
                'question_papers' => 'Question Papers',
                'projects'        => 'Projects',
                'labs'            => 'Lab Manuals',
                'journals'        => 'Journals',
                'stories'         => 'Stories',
                'puc'             => 'PUC',
                'ug'              => 'UG / BCA',
                'mca'             => 'MCA',
            ];
            foreach($tabs as $k => $lab):
                $a    = ($filter===$k) ? 'active' : '';
                $href = '?filter='.$k.(!empty($search)?'&search='.urlencode($search):'');
            ?>
            <a href="<?= $href ?>" class="ftab <?= $a ?>"><?= $lab ?></a>
            <?php endforeach; ?>
        </div>


        <!-- Table Card -->
        <div class="table-card">
            <div class="table-card-head">
                <h3><i class="fa-solid fa-table-list" style="color:var(--violet)"></i> Resource Directory</h3>
                <span><?= count($resources) ?> resource(s)</span>
            </div>

            <div class="table-wrap">
                <?php if(empty($resources)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-book-open"></i>
                    <h3>No Resources Found</h3>
                    <p><?= !empty($search) ? 'No results for "'.$search.'".' : 'Start by adding your first resource.' ?></p>
                    <button class="btn btn-violet" onclick="openModal()">
                        <i class="fa-solid fa-plus"></i> Add First Resource
                    </button>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Level</th>
                            <th>Year / Sem</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>File</th>
                            <th>Author</th>
                            <th>Pub Year</th>
                            <th>Added On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($resources as $i => $r):
                        $bc = $badgeColor[$r['type']] ?? ['#f1f5f9','#64748b'];
                    ?>
                    <tr>
                        <td style="color:var(--dim);font-weight:600"><?= $i+1 ?></td>
                        <td>
                            <div class="title-main"><?= htmlspecialchars($r['title']) ?></div>
                            <div class="title-sub">ID: <?= $r['id'] ?></div>
                        </td>
                        <td><?= htmlspecialchars($r['level']) ?></td>
                        <td><?= htmlspecialchars($r['year_sem'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['subject'] ?? '—') ?></td>
                        <td>
                            <span class="badge" style="background:<?= $bc[0] ?>;color:<?= $bc[1] ?>">
                                <?= htmlspecialchars($r['type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if($r['file_path']): ?>
                            <div class="file-link">
                                <i class="fa-solid fa-file-pdf" style="color:var(--red)"></i>
                                <div>
                                    <a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank" download>
                                        <?= htmlspecialchars(substr($r['file_name'] ?? 'File', 0, 20)) ?>
                                    </a>
                                    <div class="file-size"><?= htmlspecialchars($r['file_size'] ?? '') ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--dim);font-size:13px">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['author'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['published_year'] ?? '—') ?></td>
                        <td><?= date('d M Y', strtotime($r['added_on'])) ?></td>
                        <td>
                            <button class="del-btn"
                                onclick="doDelete(<?= $r['id'] ?>, '<?= addslashes($r['title']) ?>')"
                                title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /page-content -->
</main>


<!-- ═══════════════ ADD RESOURCE MODAL ═══════════════ -->
<div class="modal-overlay <?= $open_modal?'open':'' ?>" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus" style="color:var(--violet)"></i> Add New Resource</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="add_resource" value="1">
            <div class="form-grid">

                <div class="fg full">
                    <label>Title <span class="req">*</span></label>
                    <input type="text" name="title" class="fi" placeholder="Enter resource title" required>
                </div>

                <div class="fg">
                    <label>Author / Publisher</label>
                    <input type="text" name="author" class="fi" placeholder="Author name">
                </div>

                <div class="fg">
                    <label>Published Year</label>
                    <input type="number" name="published_year" class="fi" placeholder="e.g. 2024" min="1900" max="<?= date('Y') ?>">
                </div>

                <div class="fg">
                    <label>Level <span class="req">*</span></label>
                    <select name="level" id="levelSelect" class="fi" required onchange="updateYearSem()">
                        <option value="">-- Select Level --</option>
                        <option value="PUC">PUC</option>
                        <option value="UG">UG / BCA</option>
                        <option value="MCA">MCA / PG</option>
                        <option value="General">General</option>
                    </select>
                </div>

                <div class="fg">
                    <label>Year / Semester <span class="req">*</span></label>
                    <select name="year_sem" id="yearSemSelect" class="fi" required>
                        <option value="">-- Select Level First --</option>
                    </select>
                </div>

                <div class="fg">
                    <label>Subject Group <span class="req">*</span></label>
                    <select name="subject_group" id="subjectGroup" class="fi" required onchange="updateSubjects()">
                        <option value="">-- Select Group --</option>
                        <option value="Science">Science</option>
                        <option value="Commerce">Commerce</option>
                        <option value="Computer">Computer</option>
                        <option value="Language">Language</option>
                        <option value="General">General</option>
                    </select>
                </div>

                <div class="fg">
                    <label>Subject <span class="req">*</span></label>
                    <select name="subject" id="subjectSelect" class="fi" required>
                        <option value="">-- Select Group First --</option>
                    </select>
                </div>

                <div class="fg full">
                    <label>Resource Type <span class="req">*</span></label>
                    <select name="type" id="typeSelect" class="fi" required onchange="updateSizeInfo()">
                        <option value="">-- Select Type --</option>
                        <option value="Book">Book</option>
                        <option value="Question Paper">Question Paper</option>
                        <option value="Lab Manual">Lab Manual</option>
                        <option value="Project">Project</option>
                        <option value="Story/Moral">Story / Moral</option>
                        <option value="Journal">Journal</option>
                        <option value="Article">Article</option>
                    </select>
                </div>

                <div class="fg full">
                    <label>Upload File</label>
                    <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                        <input type="file" id="fileInput" name="resource_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Click to upload file — PDF, DOC, JPG, PNG</p>
                        <div class="chosen" id="fileChosen">No file chosen</div>
                        <div class="size-note" id="sizeNote">Max size: 10MB</div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-violet">
                    <i class="fa-solid fa-floppy-disk"></i> Save Resource
                </button>
            </div>
        </form>
    </div>
</div>


<script>
// Sidebar
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

// Modal
function openModal()  { document.getElementById('modalOverlay').classList.add('open') }
function closeModal() { document.getElementById('modalOverlay').classList.remove('open') }
document.getElementById('modalOverlay').addEventListener('click', function(e){
    if(e.target === this) closeModal();
});
<?php if($open_modal): ?>openModal();<?php endif; ?>

// Alert auto-hide
const ab = document.getElementById('alertBox');
if(ab) setTimeout(()=>{ ab.style.opacity='0'; ab.style.transition='opacity .5s'; setTimeout(()=>ab.remove(), 500); }, 3500);

// Year/Sem
const yearMap = {
    PUC: ['Year 1','Year 2'],
    UG:  ['Semester 1','Semester 2','Semester 3','Semester 4','Semester 5','Semester 6'],
    MCA: ['Semester 1','Semester 2','Semester 3','Semester 4'],
    General:['N/A']
};
function updateYearSem(){
    const lv  = document.getElementById('levelSelect').value;
    const sel = document.getElementById('yearSemSelect');
    sel.innerHTML = '<option value="">-- Select --</option>';
    (yearMap[lv]||[]).forEach(o=>{
        const op=document.createElement('option');
        op.value=op.textContent=o; sel.appendChild(op);
    });
}

// Subjects
const subMap = {
    Science:  ['Physics','Chemistry','Mathematics','Biology','Statistics'],
    Commerce: ['Accountancy','Business Studies','Economics','Statistics'],
    Computer: ['Data Structures','Python','Java','DBMS','Computer Networks','OS','Web Technology'],
    Language: ['Hindi','Kannada','Sanskrit','English'],
    General:  ['General Studies','Others']
};
function updateSubjects(){
    const g   = document.getElementById('subjectGroup').value;
    const sel = document.getElementById('subjectSelect');
    sel.innerHTML = '<option value="">-- Select Subject --</option>';
    (subMap[g]||[]).forEach(s=>{
        const op=document.createElement('option');
        op.value=op.textContent=s; sel.appendChild(op);
    });
}

// File size note
function updateSizeInfo(){
    const t = document.getElementById('typeSelect').value;
    document.getElementById('sizeNote').textContent =
        t==='Question Paper' ? 'Max size: 50MB' : 'Max size: 10MB';
}

// File chosen display
document.getElementById('fileInput').addEventListener('change',function(){
    document.getElementById('fileChosen').textContent = this.files[0]?.name || 'No file chosen';
});

// Delete
function doDelete(id, title){
    if(!confirm('Delete "'+title+'"?\n\nThis action cannot be undone.')) return;
    const url = new URL(window.location.href);
    url.searchParams.set('delete', id);
    url.searchParams.set('filter', '<?= $filter ?>');
    url.searchParams.set('search', '<?= $search ?>');
    window.location.href = url.toString();
}
</script>

</body>
</html>