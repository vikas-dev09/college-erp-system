<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: mark_attendance.php");
    exit;
}

$host = 'localhost';
$db   = 'aureon';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['attendance'])) {

    $course = $_POST['course'] ?? '';
    $year   = $_POST['year'] ?? '';
    $stream = $_POST['stream'] ?? '';
    $subject = $_POST['subject'] ?? 'All';
    $date = $_POST['attendance_date'] ?? date('Y-m-d');   // Fixed

    $success = 0;

    foreach ($_POST['attendance'] as $student_id => $status) {
        $reason = $_POST['reason'][$student_id] ?? null;

        try {
            $stmt = $pdo->prepare("INSERT INTO attendance 
                (student_id, teacher_id, course, year, stream, subject, attendance_date, status, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $student_id, 
                $_SESSION['user_id'], 
                $course, 
                $year, 
                $stream, 
                $subject, 
                $date, 
                $status, 
                $reason
            ]);
            $success++;
        } catch(Exception $e) {
            // Skip duplicates or other errors
        }
    }

    if ($success > 0) {
        header("Location: mark_attendance.php?success=1");
    } else {
        header("Location: mark_attendance.php?error=1");
    }
    exit;
}

header("Location: mark_attendance.php");
?>