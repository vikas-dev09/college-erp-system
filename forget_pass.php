<?php
/**
 * AUREON ERP - Forgot Password System
 * Single File Implementation
 * 
 * SECURITY: PDO Prepared Statements, Secure Token, Input Validation
 * SMTP: PHPMailer with Gmail (Spaces auto-removed from App Password)
 * UI: Modern Glassmorphism, AJAX, Toasts, Responsive
 */

session_start();

// =========================================================================
// DATABASE CONFIGURATION
// =========================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'aureon');      // Update if your DB name is different
define('DB_USER', 'root');        // Update if needed
define('DB_PASS', '');            // Update if needed

// =========================================================================
// PHPMAILER SETUP
// =========================================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Ensure PHPMailer is installed: composer require phpmailer/phpmailer
require 'vendor/autoload.php';

// =========================================================================
// HELPERS
// =========================================================================
function jsonResponse($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// =========================================================================
// AJAX REQUEST HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    
    // 1. Sanitize & Validate Email
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        jsonResponse('error', 'Email address is required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse('error', 'Invalid email format.');
    }

    // 2. Database Connection
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        error_log("DB Connection Error: " . $e->getMessage());
        jsonResponse('error', 'Database connection failed.');
    }

    // 3. Check if Email Exists in Users Table
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            jsonResponse('error', 'No account found with this email.');
        }
    } catch (PDOException $e) {
        jsonResponse('error', 'Database query error.');
    }

    // 4. Generate Secure Token & Expiry
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // 5. Delete Old Tokens & Insert New One
    try {
        $pdo->beginTransaction();

        // Remove previous tokens for this email
        $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $delStmt->execute([$email]);

        // Insert new token
        $insStmt = $pdo->prepare("
            INSERT INTO password_resets (email, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $insStmt->execute([$email, $token, $expires_at]);

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse('error', 'Failed to generate reset token.');
    }

    // 6. SEND EMAIL VIA PHPMAILER
    $resetLink = "http://localhost/aureon/reset_password.php?token=" . $token;

    try {
        $mail = new PHPMailer(true);

        // 🔥 SMTP CONFIGURATION (FIXED) 🔥
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // Username must match the Gmail account used to generate the App Password
        $mail->Username   = 'vikasnaik9741@gmail.com';
        
        // 🔧 CRITICAL FIX: Remove spaces from App Password
        // Gmail App Passwords must be 16 characters with NO spaces.
        $rawPassword = 'ijoz gyrn crsr rxrf';
        $mail->Password   = str_replace(' ', '', $rawPassword); // Result: ijozgyrncrsrrxrf

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('vikasnaik9741@gmail.com', 'AUREON ERP');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'AUREON ERP - Secure Password Reset';
        
        // Professional HTML Email Template
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { margin:0; padding:0; font-family:"Inter",sans-serif; background:#0f172a; color:#ffffff; }
                .wrapper { max-width:600px; margin:40px auto; background:#1e293b; border-radius:20px; overflow:hidden; border:1px solid #334155; box-shadow:0 20px 60px rgba(0,0,0,0.4); }
                .header { background:linear-gradient(135deg,#8b5cf6,#06b6d4); padding:40px 30px; text-align:center; }
                .logo { font-size:24px; font-weight:800; color:#fff; letter-spacing:1px; margin-bottom:8px; }
                .tagline { font-size:13px; color:rgba(255,255,255,0.85); }
                .content { padding:40px 30px; }
                .title { font-size:20px; font-weight:700; margin-bottom:15px; color:#fff; }
                .text { color:#cbd5e1; line-height:1.7; font-size:15px; margin-bottom:25px; }
                .btn { display:inline-block; background:linear-gradient(90deg,#8b5cf6,#06b6d4); color:#fff; padding:14px 30px; text-decoration:none; border-radius:12px; font-weight:600; font-size:15px; box-shadow:0 8px 20px rgba(139,92,246,0.3); }
                .warning { background:rgba(239,68,68,0.1); border-left:4px solid #ef4444; padding:15px; margin-top:25px; border-radius:0 8px 8px 0; }
                .warning-text { color:#fca5a5; font-size:13px; line-height:1.6; }
                .footer { padding:25px; text-align:center; border-top:1px solid #334155; font-size:12px; color:#64748b; }
                .expiry { display:inline-block; background:rgba(245,158,11,0.15); color:#fbbf24; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; margin-bottom:20px; }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="header">
                    <div class="logo">AUREON ERP</div>
                    <div class="tagline">Enterprise Resource Planning System</div>
                </div>
                <div class="content">
                    <div class="title">Password Reset Request</div>
                    <div class="text">
                        You requested to reset the password for your AUREON ERP account. 
                        Click the button below to create a new password.
                    </div>
                    
                    <div class="expiry">⏱️ Link expires in 30 minutes</div>
                    <br>
                    
                    <a href="' . $resetLink . '" class="btn">Reset Password</a>
                    
                    <div class="warning">
                        <div class="warning-text">
                            🔒 <strong>Security Notice:</strong> If you did not request this password reset, 
                            please ignore this email. Your account remains secure. 
                            Never share this link with anyone.
                        </div>
                    </div>
                </div>
                <div class="footer">
                    &copy; ' . date('Y') . ' AUREON ERP. All rights reserved.<br>
                    This is an automated message. Please do not reply.
                </div>
            </div>
        </body>
        </html>';

        // 🔥 CHECK SEND STATUS 🔥
        if ($mail->send()) {
            jsonResponse('success', 'Password reset link sent to your email.');
        } else {
            // This block should rarely hit with exceptions enabled, but good practice
            jsonResponse('error', 'Failed to send email: ' . $mail->ErrorInfo);
        }

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        jsonResponse('error', 'SMTP Error: ' . $mail->ErrorInfo);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUREON ERP - Forgot Password</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --bg-dark: #050816;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --primary: #8b5cf6;
            --secondary: #06b6d4;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --success: #10b981;
            --error: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 15% 30%, rgba(139, 92, 246, 0.15), transparent 40%),
                radial-gradient(circle at 85% 70%, rgba(6, 182, 212, 0.12), transparent 40%);
            z-index: -2;
        }

        .particles {
            position: absolute;
            inset: 0;
            overflow: hidden;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.3;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            50% { opacity: 0.5; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* Layout */
        .auth-wrapper {
            width: 90%;
            max-width: 1280px;
            height: 740px;
            display: flex;
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 32px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: fadeIn 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Left Panel */
        .left-panel {
            flex: 1;
            padding: 60px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(6, 182, 212, 0.04));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--glass-border);
        }

        .brand-header h1 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(90deg, #fff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .brand-desc {
            color: var(--text-muted);
            line-height: 1.7;
            font-size: 15px;
            max-width: 420px;
        }

        .security-card {
            margin-top: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .shield-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
        }

        .ssl-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--success);
            font-size: 13px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.1);
            padding: 10px 16px;
            border-radius: 12px;
            width: fit-content;
        }

        /* Right Panel */
        .right-panel {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .form-container {
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-header h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* Inputs */
        .input-group {
            position: relative;
            margin-bottom: 28px;
        }

        .input-group i.icon {
            position: absolute;
            left: 20px;
            top: 22px;
            color: var(--text-muted);
            font-size: 18px;
            transition: 0.3s;
        }

        .input-group input {
            width: 100%;
            padding: 22px 20px 22px 55px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
            color: #fff;
            font-size: 15px;
            outline: none;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
        }

        .input-group input:focus ~ i.icon {
            color: var(--primary);
        }

        .input-group label {
            position: absolute;
            left: 55px;
            top: 22px;
            color: var(--text-muted);
            pointer-events: none;
            transition: 0.3s ease;
            font-size: 15px;
        }

        .input-group input:focus ~ label,
        .input-group input:not(:placeholder-shown) ~ label {
            top: -10px;
            left: 20px;
            font-size: 12px;
            color: var(--primary);
            background: #0b0e1f;
            padding: 0 8px;
            border-radius: 6px;
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 18px;
            border-radius: 18px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.5);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            margin-top: 20px;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--glass-border);
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: white;
            background: rgba(139, 92, 246, 0.05);
        }

        /* Loader */
        .loader {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Success Overlay */
        .success-overlay {
            position: absolute;
            inset: 0;
            background: rgba(5, 8, 22, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: 0.4s ease;
            z-index: 10;
            border-radius: 0 32px 32px 0;
        }

        .success-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .check-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--success);
            font-size: 36px;
            margin-bottom: 20px;
            transform: scale(0);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .success-overlay.active .check-circle {
            transform: scale(1);
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 30px;
            right: 30px;
            min-width: 300px;
            padding: 18px 24px;
            border-radius: 16px;
            background: #111827;
            border: 1px solid var(--glass-border);
            color: white;
            transform: translateX(120%);
            transition: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .toast.show { transform: translateX(0); }
        .toast.success { border-color: var(--success); box-shadow: 0 0 20px rgba(16, 185, 129, 0.2); }
        .toast.error { border-color: var(--error); box-shadow: 0 0 20px rgba(239, 68, 68, 0.2); }

        /* Responsive */
        @media (max-width: 992px) {
            .auth-wrapper {
                flex-direction: column;
                height: auto;
                width: 95%;
                margin: 20px 0;
            }
            .left-panel {
                display: none;
            }
            .right-panel {
                padding: 40px 25px;
            }
            .success-overlay {
                border-radius: 32px;
            }
        }
    </style>
</head>
<body>

    <!-- Particles Background -->
    <div class="particles" id="particles"></div>

    <div class="auth-wrapper">
        <!-- Left Panel -->
        <div class="left-panel">
            <div class="brand-header">
                <h1>AUREON ERP</h1>
                <p class="brand-desc">
                    Intelligent enterprise resource planning for modern institutions. 
                    Secure, scalable, and AI-powered management suite.
                </p>
            </div>

            <div class="security-card">
                <div class="shield-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3 style="font-size:18px; margin-bottom:5px;">Account Recovery</h3>
                    <p style="color:var(--text-muted); font-size:14px;">Securely reset your access credentials.</p>
                </div>
            </div>

            <div class="ssl-badge">
                <i class="fa-solid fa-lock"></i>
                <span>256-bit SSL Encrypted Recovery</span>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="right-panel">
            <div class="form-container">
                <div class="form-header">
                    <h2>Forgot Password?</h2>
                    <p>Enter your registered email to receive a reset link.</p>
                </div>

                <form id="forgotForm">
                    <input type="hidden" name="ajax" value="1">

                    <div class="input-group" id="emailGroup">
                        <i class="fa-solid fa-envelope icon"></i>
                        <input type="email" id="email" name="email" placeholder=" " autocomplete="off" required>
                        <label for="email">Email Address</label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span id="btnText">Send Reset Link</span>
                        <div class="loader" id="loader"></div>
                    </button>
                </form>

                <a href="login.php" class="btn btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
            </div>

            <!-- Success Overlay -->
            <div class="success-overlay" id="successOverlay">
                <div class="check-circle">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3 style="font-size:22px; margin-bottom:10px;">Link Sent!</h3>
                <p style="color:var(--text-muted); text-align:center; max-width:300px;">
                    Check your inbox for the password reset instructions.
                </p>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i id="toastIcon"></i>
        <span id="toastMsg"></span>
    </div>

    <script>
        // Generate Particles
        const particleContainer = document.getElementById('particles');
        for (let i = 0; i < 40; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.width = p.style.height = Math.random() * 4 + 2 + 'px';
            p.style.animationDuration = Math.random() * 15 + 10 + 's';
            p.style.animationDelay = Math.random() * 5 + 's';
            p.style.background = Math.random() > 0.5 ? 'var(--primary)' : 'var(--secondary)';
            particleContainer.appendChild(p);
        }

        // DOM Elements
        const form = document.getElementById('forgotForm');
        const emailInput = document.getElementById('email');
        const emailGroup = document.getElementById('emailGroup');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const loader = document.getElementById('loader');
        const toast = document.getElementById('toast');
        const successOverlay = document.getElementById('successOverlay');

        // Live Validation
        emailInput.addEventListener('input', () => {
            const email = emailInput.value.trim();
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email.length > 0) {
                if (regex.test(email)) {
                    emailGroup.classList.add('valid');
                    emailGroup.classList.remove('invalid');
                    emailInput.style.borderColor = 'rgba(16, 185, 129, 0.5)';
                } else {
                    emailGroup.classList.add('invalid');
                    emailGroup.classList.remove('valid');
                    emailInput.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                }
            } else {
                emailGroup.classList.remove('valid', 'invalid');
                emailInput.style.borderColor = 'var(--glass-border)';
            }
        });

        // Form Submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = emailInput.value.trim();
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!regex.test(email)) {
                showToast('Please enter a valid email address.', 'error');
                emailInput.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                return;
            }

            // Loading State
            btnText.style.display = 'none';
            loader.style.display = 'block';
            submitBtn.disabled = true;

            const formData = new FormData(form);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    showToast(result.message, 'success');
                    
                    // Show Success Animation
                    setTimeout(() => {
                        successOverlay.classList.add('active');
                    }, 500);
                } else {
                    showToast(result.message, 'error');
                    // Reset Button
                    btnText.style.display = 'inline';
                    loader.style.display = 'none';
                    submitBtn.disabled = false;
                }

            } catch (error) {
                console.error(error);
                showToast('Connection error. Please try again.', 'error');
                btnText.style.display = 'inline';
                loader.style.display = 'none';
                submitBtn.disabled = false;
            }
        });

        // Toast Function
        function showToast(message, type) {
            const icon = document.getElementById('toastIcon');
            const msg = document.getElementById('toastMsg');
            
            toast.className = `toast ${type}`;
            msg.innerText = message;
            
            if (type === 'success') {
                icon.className = 'fa-solid fa-circle-check';
                icon.style.color = 'var(--success)';
            } else {
                icon.className = 'fa-solid fa-triangle-exclamation';
                icon.style.color = 'var(--error)';
            }

            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>