<?php
session_start();

$conn = new PDO("mysql:host=localhost;dbname=aureon","root","");

$user_id = $_SESSION['user_id'];

$password = $_POST['password'];

$stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(password_verify($password, $user['password'])){
    $_SESSION['vault_access'] = true;
    echo "success";
}else{
    echo "failed";
}