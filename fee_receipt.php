<?php
ob_start();
session_start();

// Check if user is logged in as admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
} catch(PDOException $e) {
    die("Database connection failed");
}

// Auto-create receipts table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS `receipts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `receipt_id` VARCHAR(50) UNIQUE NOT NULL,
    `student_id` VARCHAR(50) NOT NULL,
    `semester` VARCHAR(50) NOT NULL,
    `payment_type` VARCHAR(50) NOT NULL,
    `payment_mode` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$admin_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin';
$initials = strtoupper(substr($admin_name, 0, 1) . substr(strrchr($admin_name, ' ') ?: $admin_name, 1, 1));

$student_details = [];
$payment_details = [];
$errors = [];

// Handle Form Submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SEARCH STUDENT
    if(isset($_POST['search_student'])) {
        $student_id = trim($_POST['student_id'] ?? '');
        if(empty($student_id)) {
            $errors['student_id'] = 'Enter Student ID';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $student_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$student_details) {
                $errors['student_id'] = 'Student not found in database.';
            } else {
                // Robust Course Checking for Semester Dropdown
                $course = strtoupper(trim($student_details['course'] ?? ''));
                
                if($course === 'PUC' || strpos($course, 'PUC') !== false) {
                    $semesters = ['1st Year', '2nd Year'];
                    $amount = 25000;
                } elseif($course === 'BCA' || strpos($course, 'BCA') !== false || $course === 'UG') {
                    $semesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6'];
                    $amount = 35000;
                } elseif($course === 'MCA' || strpos($course, 'MCA') !== false || $course === 'PG') {
                    $semesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4'];
                    $amount = 45000;
                } else {
                    // Fallback if course name is different/custom
                    $semesters = ['1st Year', '2nd Year', '3rd Year', 'Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6'];
                    $amount = 30000;
                }

                $payment_details['semesters'] = $semesters;
                $payment_details['amount_per_sem'] = $amount;
            }
        }
    } 
    
    // 2. GENERATE RECEIPT
    elseif(isset($_POST['generate_receipt'])) {
        $student_id = trim($_POST['student_id'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $payment_type = trim($_POST['payment_type'] ?? '');
        $payment_mode = trim($_POST['payment_mode'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);

        if(empty($semester) || empty($payment_mode) || empty($payment_type) || $amount <= 0) {
            $errors['form'] = "Please fill all payment fields correctly.";
            
            // Refetch student to keep form visible
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
            $stmt->execute([$student_id]);
            $student_details = $stmt->fetch(PDO::FETCH_ASSOC);
            $payment_details['semesters'] = ['1st Year', '2nd Year', 'Semester 1', 'Semester 2', 'Semester 3', 'Semester 4'];
            $payment_details['amount_per_sem'] = $amount;
        } else {
            // Generate unique receipt ID
            $receipt_id = 'REC' . date('ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO receipts (receipt_id, student_id, semester, payment_type, payment_mode, amount) VALUES (?, ?, ?, ?, ?, ?)");
            if($stmt->execute([$receipt_id, $student_id, $semester, $payment_type, $payment_mode, $amount])) {
                
                // REDIRECT TO PRINT/PDF PAGE
                header("Location: view_receipt.php?receipt_id=" . $receipt_id);
                exit;

            } else {
                $errors['form'] = "Database error. Could not generate receipt.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt Generator | AUREON ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}

        :root{
            --violet:#7c3aed;--violet-dark:#6d28d9;--violet-light:#a78bfa;
            --violet-pale:#ede9fe;--violet-glow:rgba(124,58,237,0.12);
            --orange:#f97316;--pink:#ec4899;
            --green:#10b981;--red:#ef4444;--red-dark:#b91c1c;
            --dark:#1f1635;--text:#334155;--text-muted:#64748b;--text-dim:#94a3b8;
            --border:#e2e8f0;--border-light:#f1f5f9;--white:#ffffff;
            --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);
            --card-shadow:0 10px 30px rgba(0,0,0,0.06);
            --radius:16px;--radius-sm:12px;--radius-xs:8px;
        }

        body{
            min-height:100vh;
            font-family:'Inter','Segoe UI',sans-serif;
            background:var(--bg);
            color:var(--text);
            font-size:16px;
        }

        /* ═══════════ BIG HEADER ═══════════ */
        .header{
            display:flex;align-items:center;justify-content:space-between;
            padding:30px 40px;
            background:var(--white);
            border-bottom:1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .logo-container{
            display:flex;align-items:center;gap:20px;
        }

        .logo{
            width:80px;height:80px;
            object-fit:contain;
            filter:drop-shadow(0 4px 10px rgba(124,58,237,0.2));
            mix-blend-mode: multiply;
        }

        .logo-text h1{
            font-size:32px;font-weight:800;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
            letter-spacing: -0.5px;
        }

        .logo-text p{
            font-size:16px;color:var(--text-muted);
            margin-top:4px;font-weight:500;
        }

        .dash-btn{
            padding: 12px 24px;
            background: var(--violet-pale);
            color: var(--violet);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.3s;
        }
        .dash-btn:hover{ background: var(--violet); color: white; }

        /* ═══════════ MAIN CONTENT ═══════════ */
        .container{
            max-width:1100px;margin:0 auto;padding:40px 20px;
        }

        .section-title{
            font-size:28px;font-weight:800;color:var(--dark);
            margin-bottom:24px;
            display:flex;align-items:center;gap:12px;
        }

        .section-title i{
            color:var(--white);
            background: linear-gradient(135deg, var(--violet), var(--pink));
            padding: 10px;
            border-radius: 12px;
            font-size: 20px;
        }

        /* ═══════════ SEARCH CARD ═══════════ */
        .search-card{
            background:var(--white);border-radius:var(--radius);
            padding:35px;box-shadow:var(--card-shadow);
            margin-bottom:30px;
        }

        .search-row{
            display:flex;align-items:center;gap:16px;
        }

        .search-input{
            flex:1;
            padding:16px 20px;border:2px solid var(--border);
            border-radius:var(--radius-sm);
            font-size:18px;color:var(--dark);
            font-family:'Inter',sans-serif;
            outline:none;
            transition:all 0.3s;
        }

        .search-input:focus{
            border-color:var(--violet);
            box-shadow:0 0 0 4px var(--violet-glow);
        }

        .search-btn{
            padding:16px 36px;border:none;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            color:white;font-size:18px;font-weight:700;
            border-radius:var(--radius-sm);
            cursor:pointer;display:flex;align-items:center;gap:10px;
            transition:all 0.3s;
        }

        .search-btn:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(124,58,237,0.3)}

        .error{
            color:var(--red);font-size:15px;font-weight:600;
            margin-top:12px;display:flex;align-items:center;gap:6px;
            background: rgba(239, 68, 68, 0.1); padding: 10px 15px; border-radius: 8px;
        }

        /* ═══════════ STUDENT DETAILS ═══════════ */
        .student-details{
            background:var(--white);border-radius:var(--radius);
            padding:35px;box-shadow:var(--card-shadow);
            margin-bottom:30px;
            display:grid;grid-template-columns:repeat(3,1fr);
            gap:24px;
            border-top: 5px solid var(--violet);
        }

        .detail-item{display:flex;flex-direction:column;gap:6px}

        .detail-label{
            font-size:13px;font-weight:700;color:var(--text-muted);
            text-transform:uppercase;letter-spacing:0.8px;
        }

        .detail-value{
            font-size:18px;font-weight:700;color:var(--dark);
        }

        .detail-value.highlight{color:var(--violet)}

        /* ═══════════ PAYMENT FORM ═══════════ */
        .payment-form{
            background:var(--white);border-radius:var(--radius);
            padding:40px;box-shadow:var(--card-shadow);
        }

        .form-grid{
            display:grid;grid-template-columns:repeat(2,1fr);
            gap:24px;margin-bottom:30px;
        }

        .form-group{display:flex;flex-direction:column;gap:8px}

        .form-group label{
            font-size:16px;font-weight:700;color:var(--dark);
            display:flex;align-items:center;gap:6px;
        }

        .form-group label .req{color:var(--red)}

        .form-control{
            padding:16px 20px;border:2px solid var(--border);
            border-radius:var(--radius-sm);
            font-size:16px;color:var(--dark);
            font-family:'Inter',sans-serif;font-weight:500;
            outline:none;transition:all 0.3s;
            cursor: pointer;
            background: var(--bg);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
        }

        .form-control:focus{
            border-color:var(--violet);
            box-shadow:0 0 0 4px var(--violet-glow);
            background: var(--white);
        }

        /* ═══════════ UPI QR SECTION ═══════════ */
        .upi-container{
            background: var(--border-light);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            border: 2px dashed var(--violet-light);
        }

        .upi-qr{
            width:150px;height:150px;
            background:var(--white);
            padding: 10px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .upi-qr img{width:100%;height:100%;object-fit:contain}

        .upi-details h4{font-size:20px;color:var(--dark);margin-bottom:6px; font-weight: 800;}
        .upi-details p{font-size:16px;color:var(--text-muted); font-weight: 500;}
        .upi-details .amt{font-size: 24px; color: var(--green); font-weight: 800; margin-top: 5px;}

        /* ═══════════ SUBMIT BUTTON (BIG RED) ═══════════ */
        .form-actions{
            display:flex;align-items:center;justify-content:flex-end;
            gap:20px;margin-top:40px;
            padding-top: 30px;
            border-top: 2px solid var(--border-light);
        }

        .btn-reset{
            padding:18px 30px;border:2px solid var(--border);
            background:var(--white);color:var(--text-muted);
            font-size:18px;font-weight:700;border-radius:var(--radius-sm);
            cursor:pointer;text-decoration:none;
            display:inline-flex;align-items:center;gap:10px;
            transition:all 0.3s;
        }

        .btn-reset:hover{background:var(--border-light);color:var(--dark);}

        .btn-submit-red{
            padding:18px 40px;border:none;
            background:linear-gradient(135deg, var(--red), var(--red-dark));
            color:white;font-size:20px;font-weight:800;
            border-radius:var(--radius-sm);
            cursor:pointer;display:inline-flex;align-items:center;gap:12px;
            transition:all 0.3s;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-submit-red:hover{
            transform:translateY(-4px);
            box-shadow: 0 15px 35px rgba(239, 68, 68, 0.4);
        }

        /* ═══════════ RESPONSIVE ═══════════ */
        @media(max-width:900px){
            .student-details{grid-template-columns:repeat(2,1fr)}
            .form-grid{grid-template-columns:1fr}
            .search-row{flex-direction:column}
            .search-input{width:100%}
            .search-btn{width: 100%; justify-content: center;}
            .form-actions{flex-direction:column;gap:16px}
            .btn-reset, .btn-submit-red{width:100%;justify-content:center}
            .header{flex-direction:column; gap:20px;}
            .upi-container{flex-direction: column; text-align: center;}
        }

        @media(max-width:600px){
            .student-details{grid-template-columns:1fr}
        }
  /* HEADER BRAND */
.brand{
    display:flex;
    align-items:center;
    gap:12px;
}

/* LOGO BOX */
.aureon-logo{
    width:50px;
    height:50px;
    border-radius:12px;
    background:linear-gradient(135deg,#ede9fe,#fdf2f8);
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
    box-shadow:0 6px 18px rgba(124,58,237,0.15);
}

/* LETTER */
.logo-letter{
    font-size:66px;
    font-weight:900;
    color:#7c3aed;
}

/* CAP ICON */
.logo-cap{
    position:absolute;
    top:6px;
    right:6px;
    font-size:19px;
    color:#f97316;
}

/* TEXT */
.brand-text h2{
    font-size:20px;
    font-weight:800;
    background:linear-gradient(135deg,#7c3aed,#ec4899);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    margin:0;
}
    </style>
</head>
<body>

<header class="header">

    <div class="brand">
        <div class="aureon-logo">
            <span class="logo-letter">A</span>
            <i class="fa-solid fa-graduation-cap logo-cap"></i>
        </div>

        <div class="brand-text">
            <h2>AUREON ERP</h2>
        </div>
    </div>

    <a href="super_admin.php" class="dash-btn">
        <i class="fa-solid fa-grid-2"></i> Dashboard
    </a>

</header>
</header>
<main class="container">

    <h2 class="section-title">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        Step 1: Find Student
    </h2>

    <!-- Search Card -->
    <div class="search-card">
        <form method="POST">
            <div class="search-row">
                <input type="text" name="student_id" class="search-input" placeholder="Enter Student ID (e.g., AUR250001)" required value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
                <button type="submit" name="search_student" class="search-btn">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search Student
                </button>
            </div>
            <?php if(isset($errors['student_id'])): ?>
            <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errors['student_id'] ?></div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Payment Section Appears Only if Student Found -->
    <?php if(!empty($student_details)): ?>
    
    <h2 class="section-title">
        <i class="fa-solid fa-user-graduate"></i>
        Step 2: Student Details
    </h2>

    <div class="student-details">
        <div class="detail-item">
            <span class="detail-label">Student ID</span>
            <span class="detail-value highlight"><?= htmlspecialchars($student_details['student_id']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Full Name</span>
            <span class="detail-value"><?= htmlspecialchars($student_details['first_name'] . ' ' . $student_details['last_name']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Course & Stream</span>
            <span class="detail-value"><?= htmlspecialchars($student_details['course']) ?> <?= !empty($student_details['stream']) ? '('.htmlspecialchars($student_details['stream']).')' : '' ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Current Year</span>
            <span class="detail-value">Year <?= htmlspecialchars($student_details['year']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Phone Number</span>
            <span class="detail-value"><?= htmlspecialchars($student_details['phone']) ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Admission Type</span>
            <span class="detail-value"><?= htmlspecialchars($student_details['admission_type'] ?? 'Regular') ?></span>
        </div>
    </div>

    <h2 class="section-title">
        <i class="fa-solid fa-credit-card"></i>
        Step 3: Payment Configuration
    </h2>

    <form method="POST" class="payment-form">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_details['student_id']) ?>">
        
        <?php if(isset($errors['form'])): ?>
            <div class="error" style="margin-bottom: 20px;"><i class="fa-solid fa-triangle-exclamation"></i> <?= $errors['form'] ?></div>
        <?php endif; ?>

        <div class="form-grid">
            
            <!-- SEMESTER / YEAR SELECT -->
            <div class="form-group">
                <label>Semester / Year <span class="req">*</span></label>
                <select name="semester" class="form-control" required>
                    <option value="">-- Choose Option --</option>
                    <?php 
                    $sems = $payment_details['semesters'] ?? ['1st Year', '2nd Year', 'Sem 1', 'Sem 2']; 
                    foreach($sems as $sem): ?>
                        <option value="<?= htmlspecialchars($sem) ?>"><?= htmlspecialchars($sem) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- PAYMENT TYPE -->
            <div class="form-group">
                <label>Payment Type <span class="req">*</span></label>
                <select name="payment_type" class="form-control" required>
                    <option value="">-- Choose Option --</option>
                    <option value="Full">Full Payment</option>
                    <option value="Installment">Installment</option>
                </select>
            </div>

            <!-- PAYMENT MODE -->
            <div class="form-group">
                <label>Payment Mode <span class="req">*</span></label>
                <select name="payment_mode" class="form-control" id="paymentMode" required>
                    <option value="">-- Choose Option --</option>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI Scanner</option>
                    <option value="Card">Debit / Credit Card</option>
                    <option value="Net Banking">Net Banking</option>
                </select>
            </div>

            <!-- AMOUNT -->
            <div class="form-group">
                <label>Amount (₹) <span class="req">*</span></label>
                <input type="number" name="amount" id="amountInput" class="form-control" min="1" step="0.01" value="<?= $payment_details['amount_per_sem'] ?? 30000 ?>" required>
            </div>
        </div>

        <!-- DYNAMIC UPI QR SCANNER -->
        <div id="upiSection" style="display:none;">
            <div class="upi-container">
                <div class="upi-qr">
                    <img id="qrImage" src="" alt="UPI QR">
                </div>
                <div class="upi-details">
                    <h4>Scan to Pay via Any UPI App</h4>
                    <p>Official UPI ID: <strong>aureonerp@hdfc</strong></p>
                    <div class="amt" id="qrAmountDisplay">₹<?= $payment_details['amount_per_sem'] ?? 30000 ?></div>
                </div>
            </div>
        </div>

        <!-- BIG SUBMIT BUTTON -->
        <div class="form-actions">
            <a href="?" class="btn-reset">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
            
            <button type="submit" name="generate_receipt" class="btn-submit-red">
                <i class="fa-solid fa-file-pdf"></i>
                Generate Official Receipt
            </button>
        </div>
    </form>
    <?php endif; ?>

</main>

<script>
    const paymentMode = document.getElementById('paymentMode');
    const upiSection = document.getElementById('upiSection');
    const amountInput = document.getElementById('amountInput');
    const qrImage = document.getElementById('qrImage');
    const qrAmountDisplay = document.getElementById('qrAmountDisplay');
    const upiBase = "upi://pay?pa=aureonerp@hdfc&pn=AUREON%20ERP&cu=INR&am=";

    function updateQR() {
        const amount = amountInput.value || 0;
        qrAmountDisplay.textContent = '₹' + amount;
        qrImage.src = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" + encodeURIComponent(upiBase + amount);
    }

    if(paymentMode) {
        paymentMode.addEventListener('change', function(){
            if(this.value === 'UPI'){
                upiSection.style.display = 'flex';
                updateQR();
            } else {
                upiSection.style.display = 'none';
            }
        });
    }

    if(amountInput) {
        amountInput.addEventListener('input', function(){
            if(paymentMode.value === 'UPI') {
                updateQR();
            }
        });
    }
</script>

</body>
</html>