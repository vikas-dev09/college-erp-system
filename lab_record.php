<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=aureon;charset=utf8",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("DB Connection Failed");
}

/* =========================
   AJAX: GET STUDENTS
========================= */
if (isset($_GET['get_students'])) {

    header('Content-Type: application/json');

    try {

        $course = trim($_GET['course'] ?? '');
        $year   = trim($_GET['year'] ?? '');
        $stream = trim($_GET['stream'] ?? '');

        if ($course === '' || $year === '') {
            echo json_encode(['error' => 'Course and Year required']);
            exit;
        }

        $sql = "SELECT id, student_id, full_name 
                FROM students 
                WHERE status='Active'
                AND course=?
                AND year=?";

        $params = [$course, $year];

        // ONLY PUC uses stream
        if ($course === "PUC" && !empty($stream)) {
            $sql .= " AND stream=? ";
            $params[] = $stream;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Records | AUREON ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --cream: #fff9f0;
            --light-orange: #ffe6c7;
            --accent: #e89a4a;
            --text: #3f2a1e;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--cream); color: var(--text); }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 290px; background: #fffaf0; border-right: 1px solid #f0e4d0; position: fixed; height: 100vh; box-shadow: 4px 0 25px rgba(232,154,74,0.08); z-index:1000; }
        .sidebar .logo { width:120px; height:120px; background: linear-gradient(135deg,#ffe6c7,#ffbe7a); border-radius:28px; margin:0 auto 18px; display:flex; align-items:center; justify-content:center; font-size:3.5rem; font-weight:800; color:#3f2a1e; }
        .nav-item { padding:16px 24px; display:flex; align-items:center; gap:14px; color:#6b5a44; font-size:1.05rem; margin-bottom:8px; border-radius:16px; transition:all 0.3s; text-decoration:none; }
        .nav-item:hover, .nav-item.active { background:var(--light-orange); color:var(--accent); transform:translateX(6px); }
        .logout-btn { margin-top:auto; padding:16px 24px; color:#b56a5e; display:flex; align-items:center; gap:14px; font-size:1.05rem; border-radius:16px; transition:all 0.3s; text-decoration:none; }
        .logout-btn:hover { background:rgba(239,68,68,0.1); color:#ef4444; }
        .main { margin-left:290px; flex:1; padding:35px 40px; }
        .top-bar { background: rgba(255,250,240,0.98); padding: 18px 32px; margin: -35px -40px 35px -40px; border-bottom: 1px solid #f0e4d0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 900; }
        .content-card { background: white; padding: 32px; border-radius: 24px; box-shadow: 0 10px 30px rgba(232,154,74,0.07); }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="brand text-center">
            <div class="logo">A</div>
            <h4>AUREON ERP</h4>
            <small>Teacher Portal</small>
        </div>
        <div class="sidebar-nav">
            <a href="teacher_dash.php" class="nav-item"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
            <a href="mark_attendance.php" class="nav-item"><i class="bi bi-calendar-check"></i><span>Mark Attendance</span></a>
            <a href="#" class="nav-item"><i class="bi bi-pencil-square"></i><span>Marks Entry</span></a>
            <a href="lab_records.php" class="nav-item active"><i class="bi bi-flask"></i><span>Lab Records</span></a>
            <a href="study_materials.php" class="nav-item"><i class="bi bi-cloud-arrow-up"></i><span>Study Materials</span></a>
            <a href="#" class="nav-item"><i class="bi bi-megaphone-fill"></i><span>Notices</span></a>
        </div>
        <a href="logout.php" class="logout-btn"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </nav>

    <main class="main">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-flask-fill fs-4" style="color:#e89a4a;"></i>
                <h5 class="mb-0 fw-semibold">Lab Records</h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-circle fs-5" style="color:#8c6f4e;"></i>
                <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
            </div>
        </div>

        <div class="content-card">
            <?php if ($message) echo $message; ?>

            <h4 class="fw-bold mb-4"><i class="bi bi-flask me-2" style="color:#0d9488;"></i>Enter Lab Records</h4>

            <!-- Filters -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted">Course</label>
                    <select class="form-select" id="courseSelect" onchange="updateDynamicFields()">
                        <option value="">Select Course</option>
                        <option value="PUC">PUC</option>
                        <option value="BCA">BCA</option>
                        <option value="MCA">MCA</option>
                    </select>
                </div>
                <div class="col-md-4" id="streamContainer" style="display:none;">
                    <label class="form-label fw-semibold text-muted">Stream</label>
                    <select class="form-select" id="streamSelect">
                        <option value="">Select Stream</option>
                        <option value="Science">Science</option>
                        <option value="Commerce">Commerce</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-muted">Year</label>
                    <select class="form-select" id="yearSelect">
                        <option value="">Select Year</option>
                    </select>
                </div>
            </div>

            <!-- Subject and Experiment Title -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Subject / Lab <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject" placeholder="e.g. Physics Lab">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-muted">Experiment Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="experiment" placeholder="e.g. Ohm's Law Verification">
                </div>
            </div>

            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-outline-secondary" onclick="loadStudents()">
                    <i class="bi bi-arrow-clockwise"></i> Load Students
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="course" id="hidden_course">
                <input type="hidden" name="year" id="hidden_year">
                <input type="hidden" name="stream" id="hidden_stream">
                <input type="hidden" name="subject" id="hidden_subject">
                <input type="hidden" name="experiment" id="hidden_experiment">

                <div class="table-responsive">
                    <table class="table table-hover" id="labTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th class="text-center">Marks (Out of 30)</th>
                                <th class="text-center">Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="studentsBody">
                            <tr><td colspan="5" class="text-center py-5 text-muted">Fill Subject & Experiment Title, then click Load Students</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4">
                    <button type="button" class="btn btn-secondary px-4" onclick="history.back()">Cancel</button>
                    <button type="submit" class="btn px-5" style="background:#e89a4a; color:white; border:none;">
                        <i class="bi bi-save"></i> Save Lab Records
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadStudents() {

    const course = document.getElementById('courseSelect').value;
    const year   = document.getElementById('yearSelect').value;
    const stream = document.getElementById('streamSelect')?.value || '';
    const subject = document.getElementById('subject').value.trim();
    const experiment = document.getElementById('experiment').value.trim();

    if (!course || !year || !subject || !experiment) {
        alert("Fill all required fields");
        return;
    }

    const tbody = document.getElementById('studentsBody');

    tbody.innerHTML = `
        <tr>
            <td colspan="5" style="text-align:center;">
                Loading students...
            </td>
        </tr>
    `;

    fetch(`lab_records.php?get_students=1&course=${encodeURIComponent(course)}&year=${encodeURIComponent(year)}&stream=${encodeURIComponent(stream)}`)
        .then(res => res.json())
        .then(data => {

            console.log("Response:", data);

            tbody.innerHTML = '';

            if (data.error) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="color:red; text-align:center;">
                            ${data.error}
                        </td>
                    </tr>
                `;
                return;
            }

            if (!data.students || data.students.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align:center;">
                            No students found
                        </td>
                    </tr>
                `;
                return;
            }

            data.students.forEach(s => {

                tbody.innerHTML += `
                    <tr>
                        <td>${s.student_id}</td>
                        <td>${s.full_name}</td>

                        <td>
                            <input type="number" name="marks[${s.id}]" class="form-control" min="0" max="30">
                        </td>

                        <td>
                            <select name="status[${s.id}]" class="form-control">
                                <option value="Completed">Completed</option>
                                <option value="Pending">Pending</option>
                                <option value="Absent">Absent</option>
                            </select>
                        </td>

                        <td>
                            <input type="text" name="remarks[${s.id}]" class="form-control">
                        </td>
                    </tr>
                `;
            });

        })
        .catch(err => {
            console.error("Fetch error:", err);

            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="color:red; text-align:center;">
                        Server Error / Check Console
                    </td>
                </tr>
            `;
        });
}
</script>
</body>
</html>