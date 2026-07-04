<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    die("Unauthorized");
}

$pdo = new PDO(
    "mysql:host=localhost;dbname=aureon;charset=utf8mb4",
    "root",
    ""
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$parent_id = $_SESSION['user_id'];

$teacher_id = $_POST['teacher_id'];
$message = trim($_POST['message']);

if(empty($teacher_id) || empty($message)) {
    die("Empty fields");
}

// GET STUDENT LINKED TO PARENT
$stmt = $pdo->prepare("
    SELECT student_id 
    FROM users 
    WHERE id = ?
");

$stmt->execute([$parent_id]);

$parent = $stmt->fetch();

$student_id = $parent['student_id'];

// STORE MESSAGE
$insert = $pdo->prepare("
    INSERT INTO teacher_messages
    (parent_id, student_id, teacher_id, message)
    VALUES (?, ?, ?, ?)
");

$insert->execute([
    $parent_id,
    $student_id,
    $teacher_id,
    $message
]);

echo "success";
?>