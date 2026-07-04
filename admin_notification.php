<?php
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// DATABASE CONFIGURATION
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'aureon';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// HANDLE AJAX REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'mark_read':
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("UPDATE admin_notifications SET status='Read' WHERE id=?");
            $stmt->bind_param("i", $id);
            $response['success'] = $stmt->execute();
            $response['message'] = $response['success'] ? 'Marked as read' : 'Failed';
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id=?");
            $stmt->bind_param("i", $id);
            $response['success'] = $stmt->execute();
            $response['message'] = $response['success'] ? 'Deleted' : 'Failed';
            break;
            
        case 'mark_all_read':
            $conn->query("UPDATE admin_notifications SET status='Read' WHERE status='Unread'");
            $response['success'] = true;
            $response['message'] = 'All marked as read';
            break;
            
        case 'delete_all':
            $conn->query("DELETE FROM admin_notifications");
            $response['success'] = true;
            $response['message'] = 'All deleted';
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// HANDLE CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_notifications.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Notification ID', 'Type', 'Title', 'Message', 'User Role', 'Status', 'Date & Time']);
    
    $expResult = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");
    while ($row = $expResult->fetch_assoc()) {
        $nid = 'N' . str_pad($row['id'], 3, '0', STR_PAD_LEFT);
        fputcsv($output, [
            $nid,
            $row['type'],
            $row['title'],
            $row['message'],
            $row['user_role'],  
            $row['status'],
            date('d-m-Y h:i A', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    exit();
}

// STATISTICS QUERIES
$total = $conn->query("SELECT COUNT(*) FROM admin_notifications")->fetch_row()[0];
$unread = $conn->query("SELECT COUNT(*) FROM admin_notifications WHERE status='Unread'")->fetch_row()[0];
$read = $conn->query("SELECT COUNT(*) FROM admin_notifications WHERE status='Read'")->fetch_row()[0];
$today = $conn->query("SELECT COUNT(*) FROM admin_notifications WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];
$notification_count = $unread;

// PAGINATION SETTINGS
$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// SEARCH & FILTER
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$filter = isset($_GET['filter']) ? $conn->real_escape_string($_GET['filter']) : 'All';

// BUILD WHERE CLAUSE
$where = "WHERE 1=1";
if ($search !== '') {
    $where .= " AND (title LIKE '%$search%' OR message LIKE '%$search%' OR type LIKE '%$search%')";
}
if ($filter !== '' && $filter !== 'All') {
    $where .= " AND type='$filter'";
}

// GET TOTAL RECORDS FOR PAGINATION
$countResult = $conn->query("SELECT COUNT(*) FROM admin_notifications $where");
$totalRecords = $countResult->fetch_row()[0];
$totalPages = max(1, ceil($totalRecords / $limit));

// FETCH NOTIFICATIONS
$sql = "SELECT * FROM admin_notifications $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$filters = ['All', 'Student', 'Teacher', 'Parent', 'Attendance', 'Marks', 'Event', 'Study Material', 'System'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - AUREON ERP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f8fafc;
            min-height: 100vh;
            padding: 20px;
            color: #1f1635;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title h1 {
            color: #1f1635;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-count-badge {
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .header-title p {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
            padding-left: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 12px 20px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            width: 280px;
            background: #ffffff;
            color: #1f1635;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .mark-all-btn {
            background: #7c3aed;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .mark-all-btn:hover {
            background: #6d28d9;
            transform: translateY(-2px);
        }

        /* Statistics Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
        }

        .stat-icon.red {
            background: #fef2f2;
            color: #ef4444;
        }

        .stat-icon.green {
            background: #ecfdf5;
            color: #10b981;
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: #3b82f6;
        }

        .stat-info h3 {
            font-size: 28px;
            color: #1f1635;
            font-weight: 700;
        }

        .stat-info p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Filters Section */
        .filters-section {
            background: #ffffff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .filters-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: #7c3aed;
            color: #ffffff;
            border-color: #7c3aed;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            border: none;
        }

        .action-btn.delete-all {
            background: #fef2f2;
            color: #ef4444;
        }

        .action-btn.delete-all:hover {
            background: #ef4444;
            color: #ffffff;
        }

        .action-btn.export {
            background: #eff6ff;
            color: #3b82f6;
        }

        .action-btn.export:hover {
            background: #3b82f6;
            color: #ffffff;
        }

        /* Notification Table */
        .table-container {
            background: #ffffff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            margin-bottom: 20px;
        }

        .notification-table {
            width: 100%;
            border-collapse: collapse;
        }

        .notification-table thead {
            background: #7c3aed;
        }

        .notification-table thead th {
            color: #ffffff;
            padding: 18px 15px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
        }

        .notification-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .notification-table tbody tr:hover {
            background: #f8fafc;
        }

        .notification-table tbody td {
            padding: 16px 15px;
            font-size: 14px;
            color: #1f1635;
        }

        .type-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .type-badge.Student { background: #ede9fe; color: #7c3aed; }
        .type-badge.Teacher { background: #eff6ff; color: #3b82f6; }
        .type-badge.Parent { background: #fef3c7; color: #d97706; }
        .type-badge.Attendance { background: #ecfdf5; color: #10b981; }
        .type-badge.Marks { background: #fce7f3; color: #ec4899; }
        .type-badge.Event { background: #f0fdf4; color: #22c55e; }
        .type-badge.Study { background: #fff7ed; color: #f97316; }
        .type-badge.System { background: #f1f5f9; color: #64748b; }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.Unread {
            background: #fef2f2;
            color: #ef4444;
        }

        .status-badge.Read {
            background: #ecfdf5;
            color: #10b981;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .table-btn {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .table-btn.view {
            background: #7c3aed;
            color: #ffffff;
        }

        .table-btn.view:hover {
            background: #6d28d9;
        }

        .table-btn.mark-read {
            background: #10b981;
            color: #ffffff;
        }

        .table-btn.mark-read:hover {
            background: #059669;
        }

        .table-btn.delete {
            background: #ef4444;
            color: #ffffff;
        }

        .table-btn.delete:hover {
            background: #dc2626;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
        }

        .page-btn {
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            color: #64748b;
            text-decoration: none;
        }

        .page-btn:hover,
        .page-btn.active {
            background: #7c3aed;
            color: #ffffff;
            border-color: #7c3aed;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(31, 22, 53, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: #7c3aed;
            color: #ffffff;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 24px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-label {
            width: 150px;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
        }

        .detail-value {
            flex: 1;
            color: #1f1635;
            font-size: 14px;
            font-weight: 500;
        }

        .modal-footer {
            padding: 20px 30px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-close-modal {
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-close-modal:hover {
            background: #e2e8f0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            color: #e2e8f0;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .notification-table {
                display: block;
                overflow-x: auto;
            }
            
            .filters-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-tabs {
                overflow-x: auto;
                width: 100%;
                padding-bottom: 5px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-left">
            <div class="header-title">
                <h1>
                    <i class="fas fa-bell" style="color: #7c3aed;"></i>
                    Notifications
                <span class="notification-count-badge" id="unreadBadge">
<?php echo $notification_count; ?>
</span>
                </h1>
                <p>Manage and Monitor System Alerts</p>
            </div>
        </div>
        <div class="header-right">
            <form method="GET" action="" style="display: contents;">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search Notifications..." 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           onkeyup="clearTimeout(window.searchTimer);window.searchTimer=setTimeout(()=>this.form.submit(),500)">
                </div>
            </form>
            <button class="mark-all-btn" onclick="markAllRead()">
                <i class="fas fa-check-double"></i>
                Mark All Read
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <h3 id="statTotal"><?php echo $total; ?></h3>
                <p>Total Notifications</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-info">
                <h3 id="statUnread"><?php echo max(0,$unread); ?></h3>
                <p>Unread Notifications</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-circle-check"></i>
            </div>
            <div class="stat-info">
                <h3 id="statRead"><?php echo $read; ?></h3>
                <p>Read Notifications</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-calendar-days"></i>
            </div>
            <div class="stat-info">
                <h3 id="statToday"><?php echo $today; ?></h3>
                <p>Today's Notifications</p>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="filters-wrapper">
            <div class="filter-tabs">
                <?php foreach ($filters as $f): ?>
                    <a href="?filter=<?php echo urlencode($f); ?>&search=<?php echo urlencode($search); ?>&page=1" 
                       class="filter-tab <?php echo ($filter === $f) ? 'active' : ''; ?>">
                        <?php echo $f === 'All' ? 'All Notifications' : $f; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="action-buttons">
                <button class="action-btn delete-all" onclick="deleteAll()">
                    <i class="fas fa-trash"></i>
                    Delete All
                </button>
                <a href="?export=csv&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>" class="action-btn export" style="text-decoration: none;">
                    <i class="fas fa-download"></i>
                    Export
                </a>
            </div>
        </div>
    </div>

    <!-- Notification Table -->
    <div class="table-container">
        <table class="notification-table">
            <thead>
                <tr>
                    <th>Notification ID</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>User Role</th>
                    <th>Date & Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $nid = 'NOT-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
                        $typeClass = str_replace(' ', '', $row['type']);
                        $date = date('d-m-Y h:i A', strtotime($row['created_at']));
                    ?>
                    <tr id="row-<?php echo $row['id']; ?>">
                        <td><?php echo $nid; ?></td>
                        <td><span class="type-badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($row['type']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['message']); ?></td>
                        <td><?php echo htmlspecialchars($row['user_role']); ?></td>
                        <td><?php echo $date; ?></td>
                        <td><span class="status-badge <?php echo $row['status']; ?>" id="status-<?php echo $row['id']; ?>"><?php echo $row['status']; ?></span></td>
                        <td>
                            <div class="table-actions">
                                <button class="table-btn view" onclick="openModal('<?php echo $nid; ?>', '<?php echo addslashes($row['title']); ?>', '<?php echo addslashes($row['message']); ?>', '<?php echo $row['type']; ?>', '<?php echo $row['status']; ?>', '<?php echo $date; ?>')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($row['status'] === 'Unread'): ?>
                                <button class="table-btn mark-read" onclick="markRead(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                                <button class="table-btn delete" onclick="deleteNotification(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No Notifications Available</h3>
                                <p>There are no notifications matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php 
                $prevPage = max(1, $page - 1);
                $nextPage = min($totalPages, $page + 1);
                $baseUrl = "?filter=" . urlencode($filter) . "&search=" . urlencode($search) . "&page=";
            ?>
            <a href="<?php echo $baseUrl . $prevPage; ?>" class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>" <?php echo ($page <= 1) ? 'onclick="return false;"' : ''; ?>>
                <i class="fas fa-chevron-left"></i>
            </a>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                    <a href="<?php echo $baseUrl . $i; ?>" class="page-btn <?php echo ($i === $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                    <span class="page-btn" style="cursor: default; border: none;">...</span>
                <?php endif; ?>
            <?php endfor; ?>
            
            <a href="<?php echo $baseUrl . $nextPage; ?>" class="page-btn <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>" <?php echo ($page >= $totalPages) ? 'onclick="return false;"' : ''; ?>>
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Notification Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-bell"></i> Notification Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="detail-row">
                <div class="detail-label">Notification ID</div>
                <div class="detail-value" id="modal-id"></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Title</div>
                <div class="detail-value" id="modal-title"></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Message</div>
                <div class="detail-value" id="modal-message"></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Type</div>
                <div class="detail-value" id="modal-type"></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value" id="modal-status"></div>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <div class="detail-label">Date & Time</div>
                <div class="detail-value" id="modal-date"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
    // Open Modal
    function openModal(id, title, message, type, status, date) {
        document.getElementById('modal-id').textContent = id;
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-message').textContent = message;
        document.getElementById('modal-type').textContent = type;
        document.getElementById('modal-status').innerHTML = `<span class="status-badge ${status}">${status}</span>`;
        document.getElementById('modal-date').textContent = date;
        document.getElementById('viewModal').style.display = 'flex';
    }

    // Close Modal
    function closeModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    // Close modal on outside click
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // AJAX Helper
    function sendAction(action, id = null) {
        const formData = new FormData();
        formData.append('action', action);
        if (id) formData.append('id', id);

        return fetch('notifications.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(res => res.json());
    }

    // Mark as Read
    function markRead(id) {
        sendAction('mark_read', id).then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to mark as read');
            }
        });
    }

    // Delete Notification
    function deleteNotification(id) {
        if (!confirm('Are you sure you want to delete this notification?')) return;
        sendAction('delete', id).then(data => {
            if (data.success) {
                document.getElementById('row-'+id).remove();
setTimeout(()=>location.reload(),500);
            } else {
                alert('Failed to delete');
            }
        });
    }

    // Mark All as Read
    function markAllRead() {
        if (!confirm('Mark all notifications as read?')) return;
        sendAction('mark_all_read').then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // Delete All
    function deleteAll() {
        if (!confirm('Are you sure you want to delete ALL notifications? This cannot be undone.')) return;
        sendAction('delete_all').then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
setInterval(function(){
    location.reload();
},30000);

</script>

</body>
</html>
<?php $conn->close(); ?>