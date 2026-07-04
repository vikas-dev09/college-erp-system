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
    <title>Receipts & Payment Analytics - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --violet: #7c3aed;
            --violet-dark: #6d28d9;
            --violet-light: #a78bfa;
            --violet-pale: #ede9fe;
            --text: #1f1635;
            --muted: #64748b;
            --white: #ffffff;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }
        body { background: linear-gradient(135deg, #fdfbff 0%, #fff8f5 100%); color: var(--text); display:flex; min-height:100vh; }

        /* SIDEBAR WITH YOUR LOGO */
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
        .logo-letter { font-size: 52px; font-weight: 900; color: #7c3aed; }
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
        .nav-link.active { background: var(--violet); color: white; }

        /* MAIN */
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

        .chart-container {
            position: relative;
            height: 320px;
            margin: 20px 0;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
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
            <a href="add_user.php" class="nav-link"><i class="fa-solid fa-chalkboard-user"></i> users</a>
            <a href="analystics.php" class="nav-link active"><i class="fa-solid fa-receipt"></i> Receipts Analytics</a>
        </nav>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <div class="top-header">
            <h1 style="margin:0; font-size:1.8rem;">💰 Receipts & Payment Analytics</h1>
            <small style="color:var(--muted);">Generated on <?= date('d M Y, h:i A') ?></small>
        </div>

        <?php
        $summary = $pdo->query("SELECT COUNT(*) as total_receipts, SUM(amount) as total_amount, AVG(amount) as avg_amount, MAX(created_at) as latest_date FROM receipts")->fetch();
        ?>

        <div class="summary-grid">
            <div class="summary-card">
                <div style="color:var(--muted);">Total Receipts</div>
                <div style="font-size:2.5rem; font-weight:700; color:var(--violet);"><?= number_format($summary['total_receipts']) ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Total Collected</div>
                <div style="font-size:2.5rem; font-weight:700; color:var(--violet);">₹<?= number_format($summary['total_amount'] ?? 0) ?></div>
            </div>
            <div class="summary-card">
                <div style="color:var(--muted);">Average Payment</div>
                <div style="font-size:2.2rem; font-weight:700;">₹<?= number_format($summary['avg_amount'] ?? 0, 2) ?></div>
            </div>
        </div>

        <!-- Charts Row -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;">
            
            <!-- Payment Mode Pie Chart -->
            <div class="card">
                <h2>💳 Payment Mode Distribution</h2>
                <div class="chart-container">
                    <canvas id="modeChart"></canvas>
                </div>
            </div>

            <!-- Monthly Revenue Bar Chart -->
            <div class="card">
                <h2>📈 Monthly Revenue Trend</h2>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Student-wise Table -->
        <div class="card">
            <h2>👨‍🎓 Student-wise Payment Analysis</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Total Paid</th>
                        <th>Receipts</th>
                        <th>Last Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $students = $pdo->query("
                        SELECT r.student_id, u.full_name, SUM(r.amount) as total_paid, 
                               COUNT(r.id) as count, MAX(r.created_at) as last_pay
                        FROM receipts r
                        LEFT JOIN users u ON r.student_id = u.student_id
                        GROUP BY r.student_id
                        ORDER BY total_paid DESC LIMIT 10
                    ")->fetchAll();
                    foreach ($students as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['student_id']) ?></td>
                        <td><?= htmlspecialchars($s['full_name'] ?? 'N/A') ?></td>
                        <td class="amount">₹<?= number_format($s['total_paid']) ?></td>
                        <td><?= $s['count'] ?></td>
                        <td><?= $s['last_pay'] ? date('d M Y', strtotime($s['last_pay'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Payment Mode Pie Chart
        new Chart(document.getElementById('modeChart'), {
            type: 'pie',
            data: {
                labels: ['Cash', 'UPI', 'Card', 'Bank Transfer'],
                datasets: [{
                    data: [45, 120, 30, 25], // Replace with real data if needed
                    backgroundColor: ['#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6']
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        // Monthly Revenue Bar Chart
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue (₹)',
                    data: [45000, 62000, 58000, 71000, 89000, 76000],
                    backgroundColor: '#7c3aed',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>