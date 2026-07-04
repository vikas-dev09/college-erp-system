<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['full_name'] ?? 'Super Admin';
$admin_id   = $_SESSION['user_id'] ?? 'SA001';

// Database Connection
$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts & Payment Analysis - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --violet: #7c3aed;
            --violet-dark: #6d28d9;
            --violet-light: #a78bfa;
            --violet-pale: #ede9fe;
            --dark: #1f1635;
            --text: #334155;
            --muted: #64748b;
            --white: #ffffff;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { background: linear-gradient(135deg, #fdfbff 0%, #fff8f5 100%); color: var(--text); display:flex; min-height:100vh; }

        /* ================= SIDEBAR ================= */
        .sidebar {
            width: 290px;
            background: #f5f3ff;
            height: 100vh;
            position: fixed;
            padding: 25px 20px;
            box-shadow: 4px 0 20px rgba(124,58,237,0.08);
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .aureon-logo {
            width: 82px;
            height: 82px;
            border-radius: 22px;
            background: linear-gradient(135deg, #ede9fe, #fdf2f8);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 10px 25px rgba(124,58,237,0.15);
            margin: 0 auto 12px;
        }
        .logo-letter {
            font-size: 52px;
            font-weight: 900;
            color: #7c3aed;
        }
        .logo-cap {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 18px;
            color: #f97316;
            transform: rotate(-15deg);
        }
        .sidebar h2 {
            text-align: center;
            font-size: 1.35rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7c3aed, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            color: var(--muted);
            text-decoration: none;
            border-radius: 14px;
            margin-bottom: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .nav-link:hover { background: rgba(124,58,237,0.1); color: var(--violet); }
        .nav-link.active { background: var(--violet); color: white; }

        /* ================= MAIN ================= */
        .main {
            margin-left: 290px;
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
        }

        .top-header {
            background: white;
            padding: 20px 25px;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 8px 25px rgba(139,92,246,0.08);
            margin-bottom: 25px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: linear-gradient(135deg, var(--violet-pale), white);
            padding: 22px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid #e9d5ff;
        }

        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th { background:var(--violet-pale); padding:14px; text-align:left; color:var(--violet); font-weight:600; }
        td { padding:14px; border-bottom:1px solid #f1f5f9; }
        .amount { font-weight:700; color:var(--violet); }
    </style>
</head>
<body>

    <!-- ================= SIDEBAR WITH YOUR LOGO ================= -->
    <aside class="sidebar">
        <div style="text-align:center; margin-bottom:35px;">
            <div class="aureon-logo">
                <span class="logo-letter">A</span>
                <i class="fa-solid fa-graduation-cap logo-cap"></i>
            </div>
            <h2>AUREON ERP</h2>
            <p style="color:var(--violet); font-size:0.9rem; font-weight:600;">Super Admin</p>
        </div>

        <nav>
            <a href="super_admin.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="view_students.php" class="nav-link"><i class="fa-solid fa-user-graduate"></i> Students</a>
            <a href="add_teacher.php" class="nav-link"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a>
            <a href="report.php" class="nav-link active"><i class="fa-solid fa-receipt"></i> Receipts Report</a>
            <a href="annocement.php" class="nav-link"><i class="fa-solid fa-bullhorn"></i> Announcements</a>
            <a href="analystics.php" class="nav-link"><i class="fa-solid fa-chart-line"></i> Analytics</a>
        </nav>

        <div style="margin-top:auto;">
            <div style="background:white; padding:15px; border-radius:14px; text-align:center;">
                <strong><?= htmlspecialchars($admin_name) ?></strong><br>
                <small>ID: <?= htmlspecialchars($admin_id) ?></small>
            </div>
            <a href="logout.php" style="display:block; margin-top:15px; padding:12px; background:#ef4444; color:white; text-align:center; border-radius:10px; text-decoration:none; font-weight:600;">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <!-- ================= MAIN CONTENT ================= -->
    <main class="main">
        <div class="top-header">
            <h1 style="margin:0; font-size:1.8rem;">💰 Receipts & Payment Analysis</h1>
            <small style="color:var(--muted);">Generated on <?= date('d M Y, h:i A') ?></small>
        </div>

        <?php
        $summary = $pdo->query("
            SELECT 
                COUNT(*) as total_receipts,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount,
                MAX(created_at) as latest_date
            FROM receipts
        ")->fetch();
        ?>

        <div class="summary-grid">
            <div class="summary-card">
                <div style="color:var(--muted);">Total Receipts</div>
                <div style="font-size:2.4rem; font-weight:700; color:var(--violet);"><?= number_format($summary['total_receipts']) ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Total Collected</div>
                <div style="font-size:2.4rem; font-weight:700; color:var(--violet);">₹<?= number_format($summary['total_amount'] ?? 0) ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Average Payment</div>
                <div style="font-size:2.2rem; font-weight:700;">₹<?= number_format($summary['avg_amount'] ?? 0, 2) ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Latest Transaction</div>
                <div style="font-size:1.1rem; font-weight:600;"><?= $summary['latest_date'] ? date('d M Y', strtotime($summary['latest_date'])) : 'N/A' ?></div>
            </div>
        </div>

        <!-- Other sections remain same as your code -->
        <!-- Student Payment Analysis, Payment Mode, Semester, Monthly Trend... -->

        <div class="card">
            <h2>👨‍🎓 Student-wise Payment Analysis</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Total Paid</th>
                        <th>Receipts</th>
                        <th>Payment Types</th>
                        <th>Last Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $student_analysis = $pdo->query("
                        SELECT 
                            r.student_id,
                            u.full_name,
                            SUM(r.amount) as total_paid,
                            COUNT(r.id) as receipt_count,
                            GROUP_CONCAT(DISTINCT r.payment_type) as payment_types,
                            MAX(r.created_at) as last_payment
                        FROM receipts r
                        LEFT JOIN users u ON r.student_id = u.student_id
                        GROUP BY r.student_id
                        ORDER BY total_paid DESC
                    ")->fetchAll();
                    foreach ($student_analysis as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['student_id']) ?></td>
                        <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
                        <td class="amount">₹<?= number_format($row['total_paid']) ?></td>
                        <td><?= $row['receipt_count'] ?></td>
                        <td><?= htmlspecialchars($row['payment_types']) ?></td>
                        <td><?= $row['last_payment'] ? date('d M Y', strtotime($row['last_payment'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- You can keep other sections (Payment Mode, Semester, Monthly) as they were -->
    </main>
</body>
</html>