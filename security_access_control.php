<?php
session_start();

/*
✅ FUTURE INTEGRATION NOTES
Later you will:
- Fetch roles & permissions from database
- Save permissions to role_permissions table
- Apply session-based access control
*/

// Sample default permissions (can later fetch from DB)
$permissions = [
    "Admin" => ["dashboard"=>1,"students"=>1,"subjects"=>1,"attendance"=>1,"events"=>1,"reports"=>1,"settings"=>1],
    "Teacher" => ["dashboard"=>1,"students"=>1,"subjects"=>1,"attendance"=>1,"events"=>1,"reports"=>0,"settings"=>0],
    "Student" => ["dashboard"=>1,"students"=>0,"subjects"=>0,"attendance"=>1,"events"=>1,"reports"=>0,"settings"=>0],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security & Access Control - Aureon ERP</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

:root{
    --primary:#4F46E5;
    --secondary:#F59E0B;
    --success:#10B981;
    --danger:#EF4444;
    --lightbg:#F8F9FC;
    --card:#ffffff;
    --border:#E5E7EB;
    --text:#1F2937;
    --muted:#6B7280;
}

*{
    font-family:'Inter',sans-serif;
}

body{
    background:var(--lightbg);
    color:var(--text);
}

/* Sidebar */
.sidebar{
    width:260px;
    height:100vh;
    position:fixed;
    background:#fff;
    border-right:1px solid var(--border);
    padding:25px 15px;
}

.sidebar h4{
    font-weight:800;
    color:var(--primary);
}

.sidebar a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px 15px;
    border-radius:10px;
    text-decoration:none;
    color:var(--muted);
    margin-bottom:6px;
    font-weight:500;
}

.sidebar a:hover{
    background:var(--primary);
    color:#fff;
}

/* Top Navbar */
.topbar{
    margin-left:260px;
    background:#fff;
    padding:15px 30px;
    border-bottom:1px solid var(--border);
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.topbar h5{
    font-weight:700;
    margin:0;
}

.main{
    margin-left:260px;
    padding:35px;
}

.card{
    border:none;
    border-radius:16px;
    box-shadow:0 6px 25px rgba(0,0,0,0.04);
}

.table th{
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.5px;
    color:var(--muted);
}

.form-switch .form-check-input{
    width:50px;
    height:25px;
}

.badge-role{
    padding:6px 14px;
    font-size:12px;
    border-radius:20px;
    font-weight:600;
}

.role-admin{background:#EEF2FF;color:var(--primary);}
.role-teacher{background:#E0F2FE;color:#0284C7;}
.role-student{background:#F3E8FF;color:#7C3AED;}

.security-box{
    background:#FFF7ED;
    border-left:5px solid var(--secondary);
    padding:20px;
    border-radius:12px;
}

.btn-primary{
    background:var(--primary);
    border:none;
    border-radius:10px;
    padding:10px 20px;
    font-weight:600;
}

.btn-outline-secondary{
    border-radius:10px;
}

</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4>Aureon ERP</h4>
    <hr>
    <a href="super_admin.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="#"><i class="bi bi-people"></i> Users</a>
    <a href="#"><i class="bi bi-shield-lock"></i> Security</a>
    <a href="#"><i class="bi bi-gear"></i> Settings</a>
</div>

<!-- Topbar -->
<div class="topbar">
    <h5>Security & Access Control</h5>
    <button class="btn btn-outline-danger btn-sm">
        <i class="bi bi-box-arrow-right"></i> Logout
    </button>
</div>

<!-- Main Content -->
<div class="main">

<div class="mb-4">
    <h4 class="fw-bold">Manage system permissions and protect sensitive modules.</h4>
</div>

<!-- Security Notice -->
<div class="security-box mb-4">
    <strong><i class="bi bi-info-circle-fill"></i> Security Notice:</strong><br>
    Access control protects sensitive data and ensures only authorized users can access specific modules of the ERP.
</div>

<!-- Permission Table -->
<div class="card p-4 mb-4">
    <h5 class="fw-bold mb-3">Role Permission Matrix</h5>

    <div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Role</th>
                <th>Dashboard</th>
                <th>Students</th>
                <th>Subjects</th>
                <th>Attendance</th>
                <th>Events</th>
                <th>Reports</th>
                <th>Settings</th>
            </tr>
        </thead>
        <tbody>

        <?php foreach($permissions as $role => $perms): ?>
        <tr>
            <td>
                <span class="badge-role role-<?php echo strtolower($role); ?>">
                    <?php echo $role; ?>
                </span>
            </td>

            <?php foreach($perms as $key => $value): ?>
            <td>
                <div class="form-check form-switch">
                    <input class="form-check-input"
                           type="checkbox"
                           <?php echo $value ? "checked" : ""; ?>>
                </div>
            </td>
            <?php endforeach; ?>

        </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
    </div>

    <div class="mt-4 d-flex gap-3">
        <button class="btn btn-primary">
            <i class="bi bi-save"></i> Save Permission Changes
        </button>
        <button class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise"></i> Reset Default Permissions
        </button>
    </div>
</div>

<!-- Role Manager -->
<div class="card p-4">
    <h5 class="fw-bold mb-3">Role Manager</h5>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Select User</label>
            <select class="form-select">
                <option>John Doe</option>
                <option>Mary Smith</option>
                <option>Rahul Kumar</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Assign Role</label>
            <select class="form-select">
                <option>Admin</option>
                <option>Teacher</option>
                <option>Student</option>
            </select>
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100">
                <i class="bi bi-person-check"></i> Update Role
            </button>
        </div>
    </div>
</div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>