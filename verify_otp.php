<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$user_otp = $data['otp'] ?? '';

// Check if an OTP was actually generated and saved in the session
if (!isset($_SESSION['vault_otp'])) {
    echo json_encode(['status' => 'error', 'message' => 'No OTP request found. Please request a new one.']);
    exit;
}

// Compare the user's input with the session OTP
if ($user_otp === $_SESSION['vault_otp']) {
    // Correct! Clear the OTP from session so it can't be reused
    unset($_SESSION['vault_otp']);
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again.']);
}
?>