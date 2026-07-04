<?php
session_start();

// Authentication: Only admins can view receipts
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db_host = 'localhost';
$db_name = 'aureon'; 
$db_user = 'root';
$db_pass = '';

$receipt_data = null;
$error_message = "";

if (isset($_GET['receipt_id']) && !empty($_GET['receipt_id'])) {
    $receipt_id = $_GET['receipt_id'];

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch receipt details AND student details using a JOIN
        $stmt = $pdo->prepare("
            SELECT r.*, s.first_name, s.last_name, s.course, s.stream, s.year, s.address, s.phone, s.email
            FROM receipts r
            JOIN students s ON r.student_id = s.student_id
            WHERE r.receipt_id = ?
        ");
        $stmt->execute([$receipt_id]);
        $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt_data) {
            $error_message = "Receipt not found in the database.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
} else {
    $error_message = "No Receipt ID provided in the URL.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($receipt_data['receipt_id'] ?? 'Error') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <!-- PDF GENERATION SCRIPT -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            --violet:#7c3aed;--pink:#ec4899;--dark:#1f1635;
            --text:#334155;--text-muted:#64748b;--border:#e2e8f0;
            --bg:#f8fafc;--white:#ffffff;
        }
        body{
            min-height:100vh;font-family:'Inter',sans-serif;
            background:var(--bg);color:var(--text);font-size:16px;
            display:flex;flex-direction:column;align-items:center;
            padding:40px 20px;
        }

        /* ACTIONS BAR (Hidden when printing) */
        .actions-bar{
            width:100%;max-width:850px;
            display:flex;justify-content:space-between;align-items:center;
            margin-bottom:20px;
        }
        
        .btn{
            padding:12px 24px;border:none;border-radius:8px;
            font-size:16px;font-weight:600;cursor:pointer;
            display:inline-flex;align-items:center;gap:8px;
            transition:all 0.3s;text-decoration:none;
        }
        .btn-back{background:var(--white);color:var(--text);border:1px solid var(--border);}
        .btn-back:hover{background:#f1f5f9;}
        
        .btn-group{display:flex;gap:10px;}
        .btn-print{background:#2563eb;color:white;}
        .btn-print:hover{background:#1d4ed8;}
        .btn-pdf{background:#dc2626;color:white;}
        .btn-pdf:hover{background:#b91c1c;}

        /* RECEIPT CONTAINER */
        .receipt-wrapper{
            background:var(--white);
            width:100%;max-width:850px;
            padding:50px;
            border-top:8px solid var(--violet);
            box-shadow:0 10px 40px rgba(0,0,0,0.08);
            border-radius: 8px;
        }

        /* HEADER */
        .receipt-header{
            display:flex;justify-content:space-between;align-items:flex-start;
            margin-bottom:40px;border-bottom:2px solid var(--border);padding-bottom:20px;
        }
        .receipt-logo{display:flex;align-items:center;gap:15px}
        .receipt-logo img{height:70px;mix-blend-mode:multiply;}
        .receipt-logo-text{
            font-size:32px;font-weight:800;color:var(--dark);
            letter-spacing:-1px;
        }
        .receipt-logo-sub{font-size:13px;color:var(--text-muted);font-weight:500;}
        
        .receipt-meta{text-align:right;}
        .receipt-meta h2{font-size:28px;color:var(--violet);margin-bottom:10px;text-transform:uppercase;}
        .receipt-meta p{font-size:14px;color:var(--text-muted);margin-bottom:4px;}
        .receipt-meta strong{color:var(--dark);}

        /* STUDENT INFO */
        .student-info{
            display:grid;grid-template-columns:1fr 1fr;gap:20px;
            margin-bottom:40px;
        }
        .info-box{background:#f8fafc;padding:20px;border-radius:8px;border:1px solid var(--border);}
        .info-box h3{font-size:14px;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;}
        .info-row{display:flex;margin-bottom:8px;font-size:15px;}
        .info-row span{width:120px;color:var(--text-muted);font-weight:600;}
        .info-row strong{color:var(--dark);}

        /* TABLE */
        .receipt-table{width:100%;border-collapse:collapse;margin-bottom:40px;}
        .receipt-table th{background:var(--dark);color:white;padding:15px;text-align:left;font-size:15px;}
        .receipt-table td{padding:15px;border-bottom:1px solid var(--border);color:var(--dark);font-size:16px;}
        .receipt-table th:last-child, .receipt-table td:last-child{text-align:right;}
        
        .total-row td{font-size:20px;font-weight:800;color:var(--violet);border-bottom:none;}
        
        /* FOOTER */
        .receipt-footer{
            display:flex;justify-content:space-between;align-items:flex-end;
            margin-top:60px;
        }
        .note{font-size:13px;color:var(--text-muted);max-width:400px;font-style:italic;}
        .signature{text-align:center;}
        .signature-line{width:200px;border-top:1px solid var(--dark);margin-bottom:10px;}
        .signature p{font-size:14px;font-weight:600;color:var(--dark);}

        .error-card{
            background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;
            padding:30px;border-radius:12px;text-align:center;font-size:18px;font-weight:600;
        }

        /* PRINT STYLES */
        @media print {
            body {background: none;padding:0;}
            .actions-bar {display: none !important;}
            .receipt-wrapper{box-shadow:none;border-top:8px solid var(--dark);padding:0;}
            .total-row td{color:var(--dark);}
            .receipt-meta h2{color:var(--dark);}
        }
        
        @media(max-width:700px){
            .student-info{grid-template-columns:1fr;}
            .receipt-header{flex-direction:column;gap:20px;text-align:center;}
            .receipt-meta{text-align:center;}
            .actions-bar{flex-direction:column;gap:15px;}
            .btn-group{width:100%;display:grid;grid-template-columns:1fr 1fr;}
            .btn{justify-content:center;}
            .receipt-wrapper{padding:20px;}
        }
    </style>
</head>
<body>

<?php if ($receipt_data): ?>
    
    <!-- Buttons (Hidden in Print) -->
    <div class="actions-bar">
        <a href="fee_receipt.php" class="btn btn-back">
            <i class="fa-solid fa-arrow-left"></i> Back to Fees
        </a>
        <div class="btn-group">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>
            <button class="btn btn-pdf" onclick="generatePdf()">
                <i class="fa-solid fa-file-pdf"></i> Save PDF
            </button>
        </div>
    </div>

    <!-- The Actual Receipt to be Printed/Saved -->
    <div class="receipt-wrapper" id="receiptContent">
        
        <div class="receipt-header">
            <div class="receipt-logo">
                <img src="logo.png" alt="Logo">
                <div>
                    <div class="receipt-logo-text">AUREON ERP</div>
                    <div class="receipt-logo-sub">Education Management System</div>
                </div>
            </div>
            <div class="receipt-meta">
                <h2>FEE RECEIPT</h2>
                <p>Receipt No: <strong><?= htmlspecialchars($receipt_data['receipt_id']) ?></strong></p>
                <p>Date: <strong><?= date('d M Y, h:i A', strtotime($receipt_data['created_at'])) ?></strong></p>
            </div>
        </div>

        <div class="student-info">
            <div class="info-box">
                <h3>Student Details</h3>
                <div class="info-row"><span>Student ID:</span> <strong><?= htmlspecialchars($receipt_data['student_id']) ?></strong></div>
                <div class="info-row"><span>Name:</span> <strong><?= htmlspecialchars($receipt_data['first_name'] . ' ' . $receipt_data['last_name']) ?></strong></div>
                <div class="info-row"><span>Phone:</span> <strong><?= htmlspecialchars($receipt_data['phone']) ?></strong></div>
            </div>
            <div class="info-box">
                <h3>Academic Details</h3>
                <div class="info-row"><span>Course:</span> <strong><?= htmlspecialchars($receipt_data['course']) ?> <?= !empty($receipt_data['stream']) ? '('.htmlspecialchars($receipt_data['stream']).')' : '' ?></strong></div>
                <div class="info-row"><span>Year:</span> <strong>Year <?= htmlspecialchars($receipt_data['year']) ?></strong></div>
                <div class="info-row"><span>Semester:</span> <strong><?= htmlspecialchars($receipt_data['semester']) ?></strong></div>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Fee Description</th>
                    <th>Payment Mode</th>
                    <th>Payment Type</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Tuition Fees - <?= htmlspecialchars($receipt_data['semester']) ?></td>
                    <td><?= htmlspecialchars($receipt_data['payment_mode']) ?></td>
                    <td><?= htmlspecialchars($receipt_data['payment_type']) ?></td>
                    <td><?= number_format($receipt_data['amount'], 2) ?></td>
                </tr>
                <!-- Spacer row -->
                <tr><td colspan="5" style="padding:20px;"></td></tr>
                <tr class="total-row">
                    <td colspan="4" style="text-align:right;">Grand Total Paid:</td>
                    <td>₹ <?= number_format($receipt_data['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="receipt-footer">
            <div class="note">
                * This is a computer generated receipt and does not require a physical signature. Keep this for your records.
            </div>
            <div class="signature">
                <div class="signature-line"></div>
                <p>Authorized Signatory</p>
            </div>
        </div>

    </div>

<?php else: ?>
    <div class="error-card">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:40px;margin-bottom:15px;"></i><br>
        <?= htmlspecialchars($error_message ?: 'An unexpected error occurred.') ?>
        <br><br>
        <a href="fees.php" class="btn btn-back" style="display:inline-flex;margin-top:10px;">Go Back</a>
    </div>
<?php endif; ?>

<script>
    // Function to generate and download PDF using html2pdf
    function generatePdf() {
        const element = document.getElementById('receiptContent');
        const filename = 'Receipt_<?= htmlspecialchars($receipt_data['receipt_id'] ?? 'Error') ?>.pdf';
        
        const options = {
            margin:       10,
            filename:     filename,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        html2pdf().set(options).from(element).save();
    }
</script>

</body>
</html>