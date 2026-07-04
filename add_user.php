<?php
session_start();

// Database Connection
$host = 'localhost';
$dbname = 'aureon';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ensure reference_id column exists in users table
try {
    $pdo->query("ALTER TABLE users ADD COLUMN reference_id VARCHAR(50) NULL AFTER password");
} catch (PDOException $e) {
    // Column already exists
}

// Fetch Source Data
$students = $pdo->query("SELECT student_id, full_name, email, dob, father_name, mother_name FROM students WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $pdo->query("SELECT teacher_id, first_name, last_name, email, password FROM teachers WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Form Submission for Multiple Users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items'])) {
    $selected_items = json_decode($_POST['selected_items'], true);
    $success_count = 0;
    $error_messages = [];
    
    if (!empty($selected_items)) {
        foreach ($selected_items as $item) {
            $role = $item['role'];
            $ref_id = $item['ref_id'];
            
            try {
                // STUDENT LOGIN CREATION
                if ($role === 'student') {
                    // Re-fetch from DB
                    $stmt = $pdo->prepare("SELECT student_id, full_name, email, dob FROM students WHERE student_id = ?");
                    $stmt->execute([$ref_id]);
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$data) throw new Exception("Student not found");
                    
                    // Duplicate check
                    $check = $pdo->prepare("SELECT id FROM users WHERE reference_id = ? AND role = 'student'");
                    $check->execute([$data['student_id']]);
                    if ($check->fetch()) throw new Exception("Student already has login access");
                    
                    // Insert minimal login data
                    $insert = $pdo->prepare("INSERT INTO users (role, full_name, email, password, reference_id, student_id, dob, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
                    $insert->execute([
                        'student',
                        $data['full_name'],
                        $data['email'],
                        password_hash($data['dob'], PASSWORD_DEFAULT),
                        $data['student_id'],
                        $data['student_id'],
                        $data['dob']
                    ]);
                    
                    $success_count++;
                }
                
                // TEACHER LOGIN CREATION
                elseif ($role === 'teacher') {
                    // Re-fetch from DB
                    $stmt = $pdo->prepare("SELECT teacher_id, first_name, last_name, email, password FROM teachers WHERE teacher_id = ?");
                    $stmt->execute([$ref_id]);
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$data) throw new Exception("Teacher not found");
                    
                    // Duplicate check
                    $check = $pdo->prepare("SELECT id FROM users WHERE reference_id = ? AND role = 'teacher'");
                    $check->execute([$data['teacher_id']]);
                    if ($check->fetch()) throw new Exception("Teacher already has login access");
                    
                    $full_name = $data['first_name'] . ' ' . $data['last_name'];
                    $password = !empty($data['password']) ? $data['password'] : password_hash('Welcome@123', PASSWORD_DEFAULT);
                    
                    // Insert minimal login data
                    $insert = $pdo->prepare("INSERT INTO users (role, full_name, email, password, reference_id, teacher_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())");
                    $insert->execute([
                        'teacher',
                        $full_name,
                        $data['email'],
                        $password,
                        $data['teacher_id'],
                        $data['teacher_id']
                    ]);
                    
                    $success_count++;
                }
                
                // PARENT LOGIN CREATION
                elseif ($role === 'parent') {
                    // Re-fetch from DB
                    $stmt = $pdo->prepare("SELECT student_id, father_name, mother_name, dob, email FROM students WHERE student_id = ?");
                    $stmt->execute([$ref_id]);
                    $data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$data) throw new Exception("Student not found");
                    
                    // Duplicate check
                    $check = $pdo->prepare("SELECT id FROM users WHERE reference_id = ? AND role = 'parent'");
                    $check->execute([$data['student_id']]);
                    if ($check->fetch()) throw new Exception("Parent already has login access for this student");
                    
                    $parent_name = $data['father_name'] ?: ($data['mother_name'] ?: 'Guardian');
                    
                    // Insert minimal login data
                    $insert = $pdo->prepare("INSERT INTO users (role, full_name, email, password, reference_id, student_id, parent_name, dob, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
                    $insert->execute([
                        'parent',
                        $parent_name,
                        $data['email'],
                        password_hash($data['dob'], PASSWORD_DEFAULT),
                        $data['student_id'],
                        $data['student_id'],
                        $parent_name,
                        $data['dob']
                    ]);
                    
                    $success_count++;
                }
                
            } catch (Exception $e) {
                $error_messages[] = "Error creating {$role} (ID: {$ref_id}): " . $e->getMessage();
            }
        }
        
        if ($success_count > 0) {
            $success = "✅ Successfully created {$success_count} user account(s)!";
        }
        
        if (!empty($error_messages)) {
            $error = implode("<br>", $error_messages);
        }
    } else {
        $error = "No items selected. Please select at least one record.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User Access - AUREON ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --violet: #7c3aed;
            --pink: #ec4899;
            --blue: #3b82f6;
            --green: #10b981;
            --dark: #1f1635;
            --muted: #334155;
            --light: #64748b;
            --glass: rgba(255, 255, 255, 0.85);
            --shadow: 0 12px 30px rgba(0,0,0,0.08);
            --radius: 18px;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #fdfbff, #fff8f5, #f8fcff); color: var(--dark); min-height:100vh; display:flex; }

        /* Glass Sidebar - 80px */
        .sidebar {
            width: 80px; background: var(--glass); backdrop-filter: blur(20px);
            border-right: 1px solid rgba(124,58,237,0.15);
            display: flex; flex-direction: column; align-items: center;
            padding: 25px 0; position: fixed; height: 100vh; z-index: 100;
            box-shadow: 4px 0 20px rgba(0,0,0,0.05);
        }
        .logo {
            width: 50px; height: 50px;
            background: linear-gradient(135deg, var(--violet), var(--pink));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 24px; font-weight: 900; margin-bottom: 40px;
            box-shadow: 0 8px 20px rgba(124,58,237,0.3);
        }
        .nav-item {
            width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
            margin: 10px 0; border-radius: 14px; color: var(--muted); position: relative;
            transition: all 0.3s; cursor: pointer;
        }
        .nav-item:hover { background: rgba(124,58,237,0.1); color: var(--violet); transform: scale(1.05); }
        .nav-item.active { background: linear-gradient(135deg, var(--violet), var(--pink)); color: white; box-shadow: 0 6px 15px rgba(236,72,153,0.3); }
        .nav-item span { position: absolute; left: 80px; background: var(--dark); color: white; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; white-space: nowrap; opacity: 0; transform: translateX(-10px); transition: all 0.3s; pointer-events: none; }
        .nav-item:hover span { opacity: 1; transform: translateX(0); }
        .logout { margin-top: auto; color: #ef4444; }

        /* Main Content */
        .main { margin-left: 80px; flex: 1; padding: 30px 40px; overflow-y: auto; min-height: 100vh; }

        /* Header */
        .header {
            background: var(--glass); backdrop-filter: blur(16px); padding: 15px 25px;
            border-radius: 16px; display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; box-shadow: var(--shadow); border: 1px solid rgba(255,255,255,0.6);
        }
        .header-left {
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 700;
    font-size: 1.3rem;
}

.header-title {
    background: linear-gradient(to right, var(--violet), var(--pink)); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
}
        .back-btn {
            background: #ede9fe; color: var(--violet); padding: 8px 16px;
            border-radius: 10px; text-decoration: none; font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px; transition: all 0.3s;
        }
        .back-btn:hover { background: var(--violet); color: white; }
        .header-right { display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .avatar { width: 40px; height: 40px; background: linear-gradient(135deg, var(--violet), var(--pink)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }

        /* Alerts */
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #059669; }
        .alert-error { background: #fee2e2; color: #ef4444; }

        /* Title */
        .page-title { margin-bottom: 30px; }
        .page-title h1 { font-size: 2rem; font-weight: 700; margin-bottom: 8px; }
        .page-title p { color: var(--light); font-size: 1rem; }

        /* Role Selector Cards */
        .role-selector { display: flex; gap: 20px; margin-bottom: 30px; }
        .role-card {
            flex: 1; max-width: 220px; background: white; padding: 25px; border-radius: var(--radius);
            box-shadow: var(--shadow); cursor: pointer; text-align: center;
            border: 2px solid transparent; transition: all 0.3s;
        }
        .role-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(124,58,237,0.15); }
        .role-card.active { border-color: var(--violet); background: linear-gradient(135deg, #f5f3ff, white); }
        .role-icon {
            width: 60px; height: 60px; margin: 0 auto 15px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: white;
        }
        .role-card:nth-child(1) .role-icon { background: linear-gradient(135deg, var(--blue), #06b6d4); }
        .role-card:nth-child(2) .role-icon { background: linear-gradient(135deg, var(--violet), var(--pink)); }
        .role-card:nth-child(3) .role-icon { background: linear-gradient(135deg, var(--green), #34d399); }
        .role-title { font-weight: 700; font-size: 1.1rem; margin-bottom: 5px; }
        .role-desc { font-size: 0.8rem; color: var(--light); }

        /* Table Section */
        .table-section {
            background: white; border-radius: var(--radius); padding: 25px;
            box-shadow: var(--shadow); margin-bottom: 100px; display: none;
        }
        .table-section.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9;
        }
        .section-title { font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .count-badge { background: #ede9fe; color: var(--violet); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }

        .search-box {
            display: flex; align-items: center; background: #f8fafc;
            padding: 10px 16px; border-radius: 12px; border: 1px solid #e2e8f0; width: 300px;
        }
        .search-box input { border: none; background: transparent; outline: none; padding: 4px; width: 100%; font-size: 0.95rem; }
        .search-box i { color: var(--light); margin-right: 8px; }

        /* Table */
        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid #f1f5f9; max-height: 500px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        
        th {
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
            color: var(--violet); padding: 14px; text-align: left; font-weight: 600;
            position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #e2e8f0;
        }
        th:first-child { width: 60px; text-align: center; }
        
        td { padding: 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr { cursor: pointer; transition: all 0.2s; }
        tr:hover { background: #faf5ff; }
        tr.selected { background: #ede9fe; border-left: 4px solid var(--violet); }
        
        /* Checkbox for multiple selection */
        .multi-checkbox {
            width: 20px; height: 20px; accent-color: var(--violet);
            cursor: pointer;
        }
        
        .cell-highlight { font-weight: 600; color: var(--dark); }
        .cell-muted { color: var(--light); font-size: 0.9rem; }
        .cell-id { font-family: monospace; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; }

        /* Action Bar */
        .action-bar {
            position: fixed; bottom: 0; left: 80px; right: 0; background: white;
            padding: 20px 40px; box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
            display: none; justify-content: space-between; align-items: center;
            z-index: 90; border-top: 1px solid #f1f5f9;
        }
        .action-bar.show { display: flex; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

        .selection-info { display: flex; gap: 30px; align-items: center; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 0.8rem; color: var(--light); margin-bottom: 4px; }
        .info-value { font-size: 1.1rem; font-weight: 700; color: var(--dark); }
        .info-value.violet { color: var(--violet); }

        .preview-card {
            background: #f5f3ff; padding: 12px 20px; border-radius: 12px;
            border-left: 4px solid var(--violet);
        }
        .preview-title { font-size: 0.8rem; color: var(--light); margin-bottom: 4px; }
        .preview-name { font-weight: 700; color: var(--dark); }

        .btn-create {
            background: linear-gradient(135deg, var(--violet), var(--pink));
            color: white; border: none; padding: 16px 32px; border-radius: 12px;
            font-weight: 600; font-size: 1rem; cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            box-shadow: 0 8px 20px rgba(124,58,237,0.3);
            transition: all 0.3s;
        }
        .btn-create:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(236,72,153,0.4); }
        .btn-create:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .select-all {
            background: #ede9fe; color: var(--violet); border: none;
            padding: 8px 16px; border-radius: 8px; font-weight: 600;
            cursor: pointer; margin-right: 15px;
        }
        .select-all:hover { background: #ddd6fe; }

        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main { margin-left: 0; padding: 20px; }
            .action-bar { left: 0; }
            .role-selector { flex-direction: column; }
            .role-card { max-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">A</div>
        <div class="nav-item active"><i class="fa-solid fa-user-plus"></i><span>Add User</span></div>
        <div class="nav-item"><i class="fa-solid fa-users"></i><span>All Users</span></div>
        <div class="nav-item"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></div>
        <div class="nav-item"><i class="fa-solid fa-gear"></i><span>Settings</span></div>
        <a href="logout.php" class="nav-item logout"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
    </aside>

    <!-- Main Content -->
    <main class="main">
        
        <!-- Header -->
      <header class="header">
    <div class="header-left">
        <div class="header-title">AUREON ERP - Add User Access</div>
        <a href="super_admin.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="header-right">
        <span>Administrator</span>
        <div class="avatar">AD</div>
    </div>
</header>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= nl2br(htmlspecialchars($error)) ?></span>
            </div>
        <?php endif; ?>

        <!-- Title -->
        <div class="page-title">
            <h1>Add User Access</h1>
            <p>Select role, choose existing records (multiple allowed), system creates logins in users table</p>
        </div>

        <!-- Step 1: Role Selection -->
        <div class="role-selector">
            <div class="role-card" onclick="selectRole('student')" id="card-student">
                <div class="role-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div class="role-title">Student</div>
                <div class="role-desc">Create login from student records</div>
            </div>
            <div class="role-card" onclick="selectRole('teacher')" id="card-teacher">
                <div class="role-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="role-title">Teacher</div>
                <div class="role-desc">Create login from teacher records</div>
            </div>
            <div class="role-card" onclick="selectRole('parent')" id="card-parent">
                <div class="role-icon"><i class="fa-solid fa-user-group"></i></div>
                <div class="role-title">Parent</div>
                <div class="role-desc">Create login using student parent data</div>
            </div>
        </div>

        <!-- Step 2: Tables -->

        <!-- Students Table -->
        <div id="section-student" class="table-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-list" style="color: var(--violet);"></i>
                    Select Students
                    <span class="count-badge"><?= count($students) ?> records</span>
                </div>
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search by name or ID..." onkeyup="filterTable('students-table', this.value)">
                </div>
            </div>
            
            <div class="table-wrapper">
                <table id="students-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="multi-checkbox" onclick="toggleSelectAll(this, 'student')"></th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Date of Birth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr onclick="toggleRowSelection(this, 'student', '<?= $s['student_id'] ?>', '<?= htmlspecialchars($s['full_name']) ?>')">
                            <td style="text-align: center;">
                                <input type="checkbox" class="multi-checkbox student-checkbox" 
                                       data-id="<?= $s['student_id'] ?>" 
                                       data-name="<?= htmlspecialchars($s['full_name']) ?>"
                                       onchange="updateSelection()">
                            </td>
                            <td><span class="cell-id"><?= htmlspecialchars($s['student_id']) ?></span></td>
                            <td class="cell-highlight"><?= htmlspecialchars($s['full_name']) ?></td>
                            <td class="cell-muted"><?= htmlspecialchars($s['email']) ?></td>
                            <td><?= date('d M Y', strtotime($s['dob'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Teachers Table -->
        <div id="section-teacher" class="table-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-list" style="color: var(--violet);"></i>
                    Select Teachers
                    <span class="count-badge"><?= count($teachers) ?> records</span>
                </div>
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search by name or ID..." onkeyup="filterTable('teachers-table', this.value)">
                </div>
            </div>
            
            <div class="table-wrapper">
                <table id="teachers-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="multi-checkbox" onclick="toggleSelectAll(this, 'teacher')"></th>
                            <th>Teacher ID</th>
                            <th>Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teachers as $t): ?>
                        <tr onclick="toggleRowSelection(this, 'teacher', '<?= $t['teacher_id'] ?>', '<?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?>')">
                            <td style="text-align: center;">
                                <input type="checkbox" class="multi-checkbox teacher-checkbox" 
                                       data-id="<?= $t['teacher_id'] ?>" 
                                       data-name="<?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?>"
                                       onchange="updateSelection()">
                            </td>
                            <td><span class="cell-id"><?= htmlspecialchars($t['teacher_id']) ?></span></td>
                            <td class="cell-highlight"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></td>
                            <td class="cell-muted"><?= htmlspecialchars($t['email']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Parents Table -->
        <div id="section-parent" class="table-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-list" style="color: var(--violet);"></i>
                    Select Parents (via Student)
                    <span class="count-badge"><?= count($students) ?> records</span>
                </div>
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search student..." onkeyup="filterTable('parents-table', this.value)">
                </div>
            </div>
            
            <div class="table-wrapper">
                <table id="parents-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="multi-checkbox" onclick="toggleSelectAll(this, 'parent')"></th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Parent Name</th>
                            <th>Date of Birth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): 
                            $parent = $s['father_name'] ?? $s['mother_name'] ?? 'Guardian';
                        ?>
                        <tr onclick="toggleRowSelection(this, 'parent', '<?= $s['student_id'] ?>', '<?= htmlspecialchars($parent) ?>')">
                            <td style="text-align: center;">
                                <input type="checkbox" class="multi-checkbox parent-checkbox" 
                                       data-id="<?= $s['student_id'] ?>" 
                                       data-name="<?= htmlspecialchars($parent) ?>"
                                       onchange="updateSelection()">
                            </td>
                            <td><span class="cell-id"><?= htmlspecialchars($s['student_id']) ?></span></td>
                            <td class="cell-highlight"><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($parent) ?></td>
                            <td><?= date('d M Y', strtotime($s['dob'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Step 3: Create Button -->
    <form method="POST" id="userForm" style="display: contents;">
        <input type="hidden" name="selected_items" id="selectedItems">
        
        <div class="action-bar" id="actionBar">
            <div class="selection-info">
                <button type="button" class="select-all" onclick="selectAll()">Select All</button>
                <div class="info-item">
                    <span class="info-label">Total Selected</span>
                    <span class="info-value violet" id="totalCount">0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Students</span>
                    <span class="info-value" id="studentCount">0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teachers</span>
                    <span class="info-value" id="teacherCount">0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Parents</span>
                    <span class="info-value" id="parentCount">0</span>
                </div>
            </div>
            <button type="submit" class="btn-create" id="createBtn" disabled onclick="return confirmCreate()">
                <i class="fa-solid fa-user-plus"></i>
                Create Selected User Accounts
            </button>
        </div>
    </form>

    <script>
        let currentRole = '';
        let selectedItems = [];

        function selectRole(role) {
            currentRole = role;
            
            // Update cards
            document.querySelectorAll('.role-card').forEach(card => card.classList.remove('active'));
            document.getElementById('card-' + role).classList.add('active');
            
            // Show section
            document.querySelectorAll('.table-section').forEach(sec => sec.classList.remove('active'));
            document.getElementById('section-' + role).classList.add('active');
            
            // Update action bar
            updateActionBar();
        }

        function toggleRowSelection(row, role, id, name) {
            const checkbox = row.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            updateSelection();
        }

        function toggleSelectAll(source, role) {
            const checkboxes = document.querySelectorAll(`.${role}-checkbox`);
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateSelection();
        }

        function selectAll() {
            if (!currentRole) return;
            
            const checkboxes = document.querySelectorAll(`.${currentRole}-checkbox`);
            checkboxes.forEach(cb => cb.checked = true);
            updateSelection();
        }

        function updateSelection() {
            selectedItems = [];
            let studentCount = 0, teacherCount = 0, parentCount = 0;

            // Process Students
            document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
                selectedItems.push({
                    role: 'student',
                    ref_id: cb.dataset.id,
                    name: cb.dataset.name
                });
                studentCount++;
            });

            // Process Teachers
            document.querySelectorAll('.teacher-checkbox:checked').forEach(cb => {
                selectedItems.push({
                    role: 'teacher',
                    ref_id: cb.dataset.id,
                    name: cb.dataset.name
                });
                teacherCount++;
            });

            // Process Parents
            document.querySelectorAll('.parent-checkbox:checked').forEach(cb => {
                selectedItems.push({
                    role: 'parent',
                    ref_id: cb.dataset.id,
                    name: cb.dataset.name
                });
                parentCount++;
            });

            // Update UI
            document.getElementById('totalCount').textContent = selectedItems.length;
            document.getElementById('studentCount').textContent = studentCount;
            document.getElementById('teacherCount').textContent = teacherCount;
            document.getElementById('parentCount').textContent = parentCount;
            document.getElementById('selectedItems').value = JSON.stringify(selectedItems);
            
            const createBtn = document.getElementById('createBtn');
            createBtn.disabled = selectedItems.length === 0;
            
            document.getElementById('actionBar').classList.toggle('show', selectedItems.length > 0);
        }

        function filterTable(tableId, query) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tbody tr');
            query = query.toLowerCase();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function confirmCreate() {
            if (selectedItems.length === 0) {
                alert('Please select at least one record to create user accounts.');
                return false;
            }
            
            return confirm(`Are you sure you want to create ${selectedItems.length} user account(s)?\n\nThis action cannot be undone.`);
        }

        // Initialize with student role
        selectRole('student');
    </script>
</body>
</html>