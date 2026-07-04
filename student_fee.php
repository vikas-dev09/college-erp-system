<?php
session_start();

/**
 * AUREON ERP - Student Fee Receipt Page
 * Purple-Blue Theme | Secure String ID Mapping
 */

// --- 1. DATABASE CONNECTION ---
$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed.");
}

// --- 2. SECURE STUDENT IDENTIFICATION ---
$session_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$session_id) {
    if (isset($_SESSION['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $_SESSION['email']]);
        $u = $stmt->fetch();
        if ($u) {
            $session_id = $u['id'];
            $_SESSION['user_id'] = $session_id;
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// --- 3. FETCH EXACT STRING STUDENT ID FROM USERS TABLE ---
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$session_id]);
$user = $user_stmt->fetch();

if (!$user || empty($user['student_id'])) {
    die("Error: Student record or exact Student ID not found in database.");
}

$student_id_string = trim($user['student_id']); 
$full_name = $user['full_name'] ?? 'Student';
$course = $user['course'] ?? 'N/A';
$year = $user['year'] ?? 'N/A';

// --- 4. FILTERS ---
$semester_filter = trim($_GET['semester'] ?? '');

// --- 5. FETCH RECEIPTS ---
$sql = "SELECT * FROM receipts WHERE student_id = :sid";
$params = [':sid' => $student_id_string];

if ($semester_filter !== '') {
    $sql .= " AND semester = :sem";
    $params[':sem'] = $semester_filter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

// --- 6. CALCULATE TOTALS ---
$total_paid = 0;
foreach ($receipts as $r) {
    $total_paid += floatval($r['amount']);
}

// Fetch available semesters for the filter dropdown
$sem_stmt = $pdo->prepare("SELECT DISTINCT semester FROM receipts WHERE student_id = :sid ORDER BY semester ASC");
$sem_stmt->execute([':sid' => $student_id_string]);
$semesters_list = $sem_stmt->fetchAll(PDO::FETCH_COLUMN);

// Format Currency
function formatInr($amount) {
    return '₹' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipts - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* 🎨 STUDENT THEME COLORS APPLIED */
            --bg: #fdf4e8;
            --sidebar-bg: #f5f3ff;
            --accent: #8b5cf6;
            --accent-light: #ede9fe;
            --accent-hover: #7c3aed;
            --text: #1e293b;
            
            --muted: #64748b;
            --card: #ffffff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --border: rgba(0,0,0,0.05);
            --shadow: 0 10px 30px rgba(139, 92, 246, 0.06);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body { background-color: var(--bg); color: var(--text); display: flex; min-height: 100vh; overflow-x: hidden;}

        /* ========= SIDEBAR ========= */
        .sidebar {
            width: 300px; background: var(--sidebar-bg); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; padding: 25px 20px; position: fixed; height: 100vh; z-index: 100;
            box-shadow: 4px 0 15px rgba(139,92,246,0.08); /* 🧠 SIDEBAR SHADOW FIXED */
        }

        .logo {
            width: 120px; height: 120px; margin: 0 auto 18px; border-radius: 28px;
            background: linear-gradient(135deg, var(--accent-light), var(--accent));
            display: flex; align-items: center; justify-content: center; position: relative;
            box-shadow: 0 12px 35px rgba(139,92,246,0.25); transition: 0.4s ease;
        }
        .logo:hover { transform: translateY(-5px) scale(1.03); box-shadow: 0 18px 45px rgba(139,92,246,0.35); }
        .logo-text { font-size: 4.8rem; font-weight: 900; color: white; line-height: 1; text-shadow: 0 4px 10px rgba(0,0,0,0.08); }
        .logo-cap { position: absolute; top: 16px; right: 20px; font-size: 1.7rem; color: white; transform: rotate(-12deg); filter: drop-shadow(0 4px 8px rgba(0,0,0,0.15)); }
        
        .brand-title { font-size: 1.25rem; font-weight: 800; color: var(--text); letter-spacing: 0.5px; text-align: center; }
        .brand-sub { font-size: 0.85rem; color: var(--accent); font-weight: 600; margin-bottom: 30px; text-align: center; }

        .nav-menu { list-style: none; flex: 1; display: flex; flex-direction: column; gap: 6px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 13px 16px; border-radius: var(--radius-sm);
            color: var(--muted); text-decoration: none; font-weight: 600; font-size: 0.95rem;
            transition: all 0.25s ease; cursor: pointer; border: none; background: transparent; width: 100%;
        }
        .nav-item:hover { background: rgba(139,92,246,0.1); color: var(--accent); transform: translateX(4px); }
        .nav-item.active { background: var(--accent-light); color: var(--accent); }
        .nav-item i { width: 24px; font-size: 1.15rem; text-align: center; }

        .info-card { background: linear-gradient(135deg, #f5f3ff, #ede9fe); border: 1px solid var(--accent-light); border-radius: var(--radius-md); padding: 16px; margin-bottom: 15px; text-align: center; }
        .info-card p { font-size: 0.8rem; color: var(--muted); margin: 0; }
        .logout-btn { color: #dc2626; font-weight: 700; margin-top: auto; }
        .logout-btn:hover { background: rgba(220,38,38,0.08); color: #dc2626; }

        /* ========= MAIN CONTENT ========= */
        .main { flex: 1; margin-left: 300px; padding: 30px 40px; min-width: 0; }

        /* TOP BAR */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .topbar-left h2 { font-size: 1.4rem; font-weight: 800; color: var(--text); }
        .topbar-left span { color: var(--muted); font-size: 0.9rem; }
        .topbar-right { display: flex; align-items: center; gap: 18px; }
        
        .notif-icon {
            width: 44px; height: 44px; border-radius: 50%; background: var(--card); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center; color: var(--accent); cursor: pointer;
            box-shadow: var(--shadow); font-size: 1.1rem; transition: 0.3s;
        }
        .notif-icon:hover { transform: scale(1.05); background: var(--accent); color: white; }
        
        .profile-chip {
            display: flex; align-items: center; gap: 12px; background: var(--card); padding: 6px 16px 6px 6px;
            border-radius: 50px; box-shadow: var(--shadow); border: 1px solid var(--border);
        }
        .avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-hover)); color: white;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem;
        }
        .profile-chip div { line-height: 1.2; text-align: right; }
        .profile-chip strong { font-size: 0.9rem; color: var(--text); }
        .profile-chip small { font-size: 0.75rem; color: var(--muted); }

        /* HEADER CARD */
        .welcome-card {
            background: linear-gradient(135deg, var(--accent-light), #f5f3ff); border-radius: 30px; padding: 35px 40px; margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 30px rgba(139,92,246,0.12); border: 1px solid rgba(139,92,246,0.15);
        }
        .welcome-card h1 { font-size: 1.8rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .welcome-card p { color: var(--muted); font-size: 1.05rem; }
        .welcome-icon { font-size: 3rem; opacity: 0.25; color: var(--accent); }

        /* STATS SUMMARY */
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: var(--card); padding: 25px; border-radius: var(--radius-lg); border: 1px solid var(--border);
            box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; transition: 0.3s;
        }
        /* 📊 CARD HOVER STYLE FIXED */
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(139,92,246,0.15); }
        
        .stat-icon {
            width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: white;
        }
        .bg-green { background: linear-gradient(135deg, #10b981, #059669); }
        .bg-purple { background: linear-gradient(135deg, var(--accent), var(--accent-hover)); }
        .stat-details h4 { font-size: 0.9rem; color: var(--muted); font-weight: 600; margin-bottom: 4px; }
        .stat-details h2 { font-size: 1.8rem; font-weight: 800; color: var(--text); }

        /* FILTER CARD */
        .filter-card {
            background: var(--card); border-radius: var(--radius-lg); padding: 20px 30px;
            box-shadow: var(--shadow); margin-bottom: 25px; border: 1px solid var(--border);
            display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;
        }
        .filter-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .select-input {
            padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border);
            background: #f8fafc; color: var(--text); font-size: 0.95rem; outline: none; transition: 0.2s; min-width: 200px; cursor: pointer;
        }
        .select-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(139,92,246,0.12); }
        .btn-filter {
            background: var(--accent); color: white; border: none; padding: 12px 24px; border-radius: var(--radius-sm);
            font-weight: 700; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-filter:hover { background: var(--accent-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(139,92,246,0.25); }
        .btn-clear {
            background: #f1f5f9; color: var(--muted); text-decoration: none; padding: 12px 24px; border-radius: var(--radius-sm);
            font-weight: 700; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-clear:hover { background: #e2e8f0; }

        /* RECEIPT TABLE CARD */
        .table-card {
            background: var(--card); border-radius: var(--radius-lg); padding: 25px 30px;
            box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 40px;
        }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-header h3 { font-size: 1.25rem; font-weight: 800; color: var(--text); display:flex; align-items:center; gap:10px; }
        
        .table-wrap { overflow-x: auto; border-radius: var(--radius-md); border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        
        /* 🧾 TABLE HEADER FIXED */
        th {
            background: #f8fafc; color: var(--muted); font-weight: 700;
            padding: 16px; text-align: left; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid var(--accent-light);
        }
        td { padding: 16px; border-bottom: 1px solid #f5f3ff; vertical-align: middle; font-size: 0.95rem; }
        tr:hover { background: #fdfcff; }

        /* UI Enhancements for Table Data */
        /* 💳 RECEIPT LINK FIXED */
        .receipt-link {
            font-family: monospace; font-weight: 700; color: var(--accent); text-decoration: none;
            background: #ede9fe; padding: 5px 12px; border-radius: 8px; transition: 0.2s; display: inline-block;
        }
        .receipt-link:hover { background: var(--accent); color: white; transform: scale(1.05); }
        
        .amt-highlight { font-weight: 800; color: var(--success); font-size: 1.1rem; }
        .time-badge { font-family: monospace; font-size: 0.85rem; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; display: block; margin-top: 4px; font-weight: 600; width: fit-content; }
        
        /* 🎯 BADGE COLORS FIXED */
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .badge-full { background: #dcfce7; color: #166534; }
        .badge-install { background: #fef9c3; color: #854d0e; }
        .badge-upi { background: #ede9fe; color: #7c3aed; }
        .badge-cash { background: #dcfce7; color: #16a34a; }
        .badge-card { background: #dbeafe; color: #2563eb; }
        .badge-bank { background: #f3f4f6; color: #475569; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; padding: 20px 10px; }
            .sidebar .brand-title, .sidebar .brand-sub, .nav-item span, .info-card { display: none; }
            .main { margin-left: 80px; }
        }
        @media (max-width: 768px) {
            .main { margin-left: 0; padding: 20px; }
            .sidebar { display: none; }
            .welcome-card { flex-direction: column; text-align: center; gap: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div style="text-align: center; margin-bottom: 10px;">
            <div class="logo">
                <span class="logo-text">A</span>
                <i class="fa-solid fa-graduation-cap logo-cap"></i>
            </div>
            <div class="brand-title">AUREON</div>
            <div class="brand-sub">Student Portal</div>
        </div>

        <nav class="nav-menu">
            <a class="nav-item" href="student_dash.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
            <a class="nav-item" href="student_attendance.php"><i class="fa-regular fa-calendar-check"></i> <span>My Attendance</span></a>
            <a class="nav-item" href="view_marks.php"><i class="fa-solid fa-chart-simple"></i> <span>My Marks</span></a>
            <a class="nav-item active" href="student_fee.php"><i class="fa-solid fa-file-invoice-dollar"></i> <span>Fee Receipts</span></a>
            <a class="nav-item" href="view_books.php"><i class="fa-solid fa-book-open"></i> <span>Library</span></a>
            <a class="nav-item" href="student_profile.php"><i class="fa-solid fa-id-card"></i> <span>Profile</span></a>
        </nav>

        <a href="logout.php" class="nav-item logout-btn"><i class="fa-solid fa-power-off"></i> <span>Logout</span></a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <!-- TOP BAR -->
        <div class="topbar">
            <div class="topbar-left">
                <h2>Fee Management</h2>
                <span>View and download your payment receipts</span>
            </div>
            <div class="topbar-right">
                <div class="notif-icon"><i class="fa-regular fa-bell"></i></div>
                <div class="profile-chip">
                    <div class="avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
                    <div>
                        <strong><?= htmlspecialchars($full_name) ?></strong><br>
                        <small>ID: <?= htmlspecialchars($student_id_string) ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- WELCOME CARD -->
        <div class="welcome-card">
            <div>
                <h1>Your Digital Wallet 💳</h1>
                <p>Track all your tuition and academic fee payments securely.</p>
            </div>
            <div class="welcome-icon"><i class="fa-solid fa-vault"></i></div>
        </div>

        <!-- STATS SUMMARY -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-purple"><i class="fa-solid fa-receipt"></i></div>
                <div class="stat-details">
                    <h4>Total Transactions</h4>
                    <h2><?= count($receipts) ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div class="stat-details">
                    <h4>Total Paid Amount</h4>
                    <h2><?= formatInr($total_paid) ?></h2>
                </div>
            </div>
        </section>

        <!-- FILTER -->
        <form method="GET" class="filter-card">
            <div class="filter-group">
                <label>Filter by Semester</label>
                <select name="semester" class="select-input">
                    <option value="">All Semesters</option>
                    <?php foreach($semesters_list as $sem): ?>
                        <option value="<?= htmlspecialchars($sem) ?>" <?= ($semester_filter === (string)$sem) ? 'selected' : '' ?>>
                            Semester <?= htmlspecialchars($sem) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply</button>
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn-clear"><i class="fa-solid fa-rotate-left"></i> Reset</a>
        </form>

        <!-- RECEIPT AUDIT TABLE -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fa-solid fa-file-invoice" style="color: var(--accent);"></i> Payment History</h3>
                <button onclick="window.print()" class="btn-filter" style="padding: 8px 16px; font-size: 0.85rem;"><i class="fa-solid fa-print"></i> Print</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Date & Time</th>
                            <th>Semester</th>
                            <th>Payment Type</th>
                            <th>Mode</th>
                            <th>Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($receipts) > 0): ?>
                            <?php foreach($receipts as $rec): ?>
                            <tr>
                                <td>
                                    <!-- Optional: Can link to a print_receipt.php?id=... later -->
                                    <a href="#" class="receipt-link" title="Click to view details">#<?= htmlspecialchars($rec['receipt_id']) ?></a>
                                </td>
                                <td>
                                    <strong><?= date('d M Y', strtotime($rec['created_at'])) ?></strong>
                                    <span class="time-badge"><i class="fa-regular fa-clock" style="margin-right:4px;"></i><?= date('h:i A', strtotime($rec['created_at'])) ?></span>
                                </td>
                                <td>Sem <?= htmlspecialchars($rec['semester']) ?></td>
                                <td>
                                    <?php if(strtolower($rec['payment_type']) === 'full'): ?>
                                        <span class="badge badge-full"><i class="fa-solid fa-circle-check"></i> Full Payment</span>
                                    <?php else: ?>
                                        <span class="badge badge-install"><i class="fa-solid fa-clock-rotate-left"></i> Installment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $mode = strtolower($rec['payment_mode']);
                                        if($mode === 'upi') echo '<span class="badge badge-upi"><i class="fa-solid fa-qrcode"></i> UPI</span>';
                                        elseif($mode === 'cash') echo '<span class="badge badge-cash"><i class="fa-solid fa-money-bill-wave"></i> Cash</span>';
                                        elseif($mode === 'card') echo '<span class="badge badge-card"><i class="fa-regular fa-credit-card"></i> Card</span>';
                                        else echo '<span class="badge badge-bank"><i class="fa-solid fa-building-columns"></i> ' . htmlspecialchars($rec['payment_mode']) . '</span>';
                                    ?>
                                </td>
                                <td class="amt-highlight"><?= formatInr($rec['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:50px; color:var(--muted);">
                                    <i class="fa-solid fa-folder-open" style="font-size:3rem; opacity:0.2; display:block; margin-bottom:15px;"></i>
                                    <h3>No fee receipts found.</h3>
                                    <p style="font-size:0.9rem;">Your payment history will appear here once records are generated.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>