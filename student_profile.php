<?php
session_start();

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
    die("Connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Get Student ID from Users table
$user_stmt = $pdo->prepare("SELECT full_name, reference_id FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();
$student_id = $user['reference_id'];

// Fetch Full Student Profile
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) die("Student record not found in database.");

// Allowed Fields Dictionary for dropdowns
$allowed_fields = [
    'phone' => 'Phone Number',
    'email' => 'Email Address',
    'address' => 'Home Address',
    'guardian_phone' => 'Guardian Phone',
    'religion' => 'Religion',
    'category' => 'Category',
    'blood_group' => 'Blood Group'
];

// Handle New Edit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_edit'])) {
    $field = $_POST['field_name'];
    $new_value = trim($_POST['new_value']);
    
    if (array_key_exists($field, $allowed_fields) && !empty($new_value)) {
        $old_value = $student[$field];
        
        // Check for existing pending request for this field
        $check = $pdo->prepare("SELECT id FROM profile_edit_requests WHERE student_id = ? AND field_name = ? AND status = 'pending'");
        $check->execute([$student_id, $field]);
        
        if ($check->fetch()) {
            $error = "You already have a pending request for " . $allowed_fields[$field];
        } else {
            $insert = $pdo->prepare("INSERT INTO profile_edit_requests (student_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
            $insert->execute([$student_id, $field, $old_value, $new_value]);
            $success = "Edit request submitted successfully! Pending admin approval.";
        }
    } else {
        $error = "Invalid field or empty value.";
    }
}

// Fetch Request History
$history_stmt = $pdo->prepare("SELECT * FROM profile_edit_requests WHERE student_id = ? ORDER BY request_date DESC");
$history_stmt->execute([$student_id]);
$requests = $history_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #fdf4e8;
            --sidebar-bg: #f5f3ff;
            --accent-color: #8b5cf6;
            --light-accent: #ede9fe;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 10px 25px -5px rgba(139, 92, 246, 0.15);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* Sidebar (Same as Dashboard) */
        .sidebar { width: 290px; background-color: var(--sidebar-bg); display: flex; flex-direction: column; padding: 24px; position: fixed; height: 100%; box-shadow: 2px 0 15px rgba(0,0,0,0.02); z-index: 100; }
        .logo-circle { width: 130px; height: 130px; border-radius: 50%; background: linear-gradient(135deg, var(--light-accent), var(--accent-color)); margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-md); color: white; font-size: 60px; font-weight: 900; }
        .brand-name { text-align: center; font-weight: 700; font-size: 1.25rem; color: var(--text-dark); }
        .nav-menu { list-style: none; flex: 1; margin-top: 30px; }
        .nav-link { display: flex; align-items: center; padding: 14px 16px; text-decoration: none; color: var(--text-muted); border-radius: 12px; font-weight: 500; transition: 0.3s; margin-bottom: 5px; }
        .nav-link i { width: 24px; font-size: 1.1rem; margin-right: 10px; }
        .nav-link:hover { background: rgba(139, 92, 246, 0.1); color: var(--accent-color); }
        .nav-link.active { background-color: var(--accent-color); color: var(--white); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); }

        /* Main Content */
        .main-content { flex: 1; margin-left: 290px; padding: 30px 40px; overflow-y: auto; height: 100vh; }
        
        /* Top Bar */
        .top-bar { display: flex; justify-content: space-between; align-items: center; background: var(--white); padding: 15px 25px; border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 30px; }
        .page-title { font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--accent-color); }

        /* Alerts */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }

        /* Profile Header */
        .profile-header { background: var(--white); padding: 30px; border-radius: var(--radius); display: flex; align-items: center; gap: 30px; box-shadow: var(--shadow-sm); margin-bottom: 25px; }
        .profile-img { width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--light-accent); object-fit: cover; }
        .profile-info h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .profile-info p { color: var(--text-muted); font-size: 1rem; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #d1fae5; color: #065f46; }

        /* Bento Grid for Details */
        .bento-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 30px; }
        .bento-card { background: var(--white); padding: 25px; border-radius: var(--radius); box-shadow: var(--shadow-sm); }
        .bento-card h3 { font-size: 1.1rem; color: var(--accent-color); margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.95rem; }
        .detail-label { color: var(--text-muted); font-weight: 500; }
        .detail-value { font-weight: 600; color: var(--text-dark); text-align: right; }

        /* Request Edit Section */
        .request-section { background: linear-gradient(135deg, var(--light-accent), #ffffff); padding: 25px; border-radius: var(--radius); border: 1px solid var(--accent-color); margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #cbd5e1; outline: none; }
        .form-control:focus { border-color: var(--accent-color); }
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-primary:hover { background: #7c3aed; transform: translateY(-2px); }

        /* History Table */
        .table-wrapper { background: var(--white); border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow-sm); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; color: var(--text-muted); }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; }
        .status-pending { color: #d97706; background: #fef3c7; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;}
        .status-approved { color: #059669; background: #d1fae5; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;}
        .status-rejected { color: #dc2626; background: #fee2e2; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;}

    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-container">
            <div class="logo-circle">A</div>
            <div class="brand-name">AUREON ERP</div>
        </div>
        <ul class="nav-menu">
            <li><a href="student_dash.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="student_profile.php" class="nav-link active"><i class="fa-solid fa-user"></i> My Profile</a></li>
            <li><a href="logout.php" class="nav-link" style="color: #e11d48; margin-top: 50px;"><i class="fa-solid fa-power-off"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title"><i class="fa-solid fa-id-card"></i> Student Profile</div>
            <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-download"></i> Download</button>
        </div>

        <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

        <!-- Header -->
        <div class="profile-header">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['full_name']) ?>&background=8b5cf6&color=fff&size=128" class="profile-img">
            <div class="profile-info">
                <h1><?= htmlspecialchars($student['full_name']) ?></h1>
                <p><?= htmlspecialchars($student['student_id']) ?> | <?= htmlspecialchars($student['course']) ?> - Year <?= htmlspecialchars($student['year']) ?></p>
                <div style="margin-top: 10px;">
                    <span class="badge badge-active"><?= htmlspecialchars($student['status']) ?></span>
                </div>
            </div>
        </div>

        <!-- Bento Grid Info -->
        <div class="bento-grid">
            <div class="bento-card">
                <h3><i class="fa-solid fa-user me-2"></i> Personal Details</h3>
                <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= date('d M Y', strtotime($student['dob'])) ?></span></div>
                <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($student['gender']) ?></span></div>
                <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($student['blood_group'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Religion</span><span class="detail-value"><?= htmlspecialchars($student['religion'] ?? 'N/A') ?></span></div>
            </div>

            <div class="bento-card">
                <h3><i class="fa-solid fa-phone me-2"></i> Contact Details</h3>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($student['phone']) ?></span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($student['email']) ?></span></div>
                <div class="detail-row"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($student['address']) ?></span></div>
            </div>

            <div class="bento-card">
                <h3><i class="fa-solid fa-users me-2"></i> Family Details</h3>
                <div class="detail-row"><span class="detail-label">Father Name</span><span class="detail-value"><?= htmlspecialchars($student['father_name'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Mother Name</span><span class="detail-value"><?= htmlspecialchars($student['mother_name'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Guardian Phone</span><span class="detail-value"><?= htmlspecialchars($student['guardian_phone'] ?? 'N/A') ?></span></div>
            </div>

            <div class="bento-card">
                <h3><i class="fa-solid fa-graduation-cap me-2"></i> Academic Info</h3>
                <div class="detail-row"><span class="detail-label">Course</span><span class="detail-value"><?= htmlspecialchars($student['course']) ?></span></div>
                <div class="detail-row"><span class="detail-label">Stream</span><span class="detail-value"><?= htmlspecialchars($student['stream'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Admission Type</span><span class="detail-value"><?= htmlspecialchars($student['admission_type']) ?></span></div>
            </div>
        </div>

        <!-- Request Edit Form -->
        <div class="request-section">
            <h3><i class="fa-solid fa-pen-to-square"></i> Request Profile Update</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">You cannot edit details directly. Submit a request, and administration will review it.</p>
            
            <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">Select Field to Edit</label>
                    <select name="field_name" class="form-control" required>
                        <option value="">-- Select Field --</option>
                        <?php foreach($allowed_fields as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark);">Enter New Value</label>
                    <input type="text" name="new_value" class="form-control" placeholder="Type new details here..." required>
                </div>
                <button type="submit" name="request_edit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
            </form>
        </div>

        <!-- Request History Table -->
        <div class="table-wrapper">
            <h3 style="margin-bottom: 15px;"><i class="fa-solid fa-clock-rotate-left"></i> My Request History</h3>
            <?php if(count($requests) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Field</th>
                        <th>Current/Old Value</th>
                        <th>Requested Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($requests as $req): ?>
                    <tr>
                        <td><?= date('d M Y, h:i A', strtotime($req['request_date'])) ?></td>
                        <td><strong><?= $allowed_fields[$req['field_name']] ?? $req['field_name'] ?></strong></td>
                        <td style="color: var(--text-muted);"><del><?= htmlspecialchars($req['old_value']) ?></del></td>
                        <td style="color: var(--accent-color); font-weight: 500;"><?= htmlspecialchars($req['new_value']) ?></td>
                        <td><span class="status-<?= strtolower($req['status']) ?>"><?= ucfirst($req['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 20px;">No edit requests submitted yet.</p>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>