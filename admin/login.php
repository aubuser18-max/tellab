<?php
// admin/login.php - Step 1: Credentials + send OTP (SSL fix applied)

session_start();

// PHPMailer includes – files directly in common/PHPMailer/
require_once '../common/PHPMailer/Exception.php';
require_once '../common/PHPMailer/PHPMailer.php';
require_once '../common/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../common/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $conn = getDB();

        // Check if admin table exists and get user with email
        $stmt = $conn->prepare("SELECT id, username, password, email FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // Credentials correct – generate OTP
                $otp = rand(100000, 999999); // 6-digit OTP
                $expiry = time() + 300; // 5 minutes

                // Store OTP in session
                $_SESSION['2fa_admin_id'] = $row['id'];
                $_SESSION['2fa_otp'] = $otp;
                $_SESSION['2fa_expiry'] = $expiry;

                // Fetch SMTP settings from database
                $smtp_host = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_host'")->fetch_assoc()['setting_value'] ?? '';
                $smtp_port = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_port'")->fetch_assoc()['setting_value'] ?? '587';
                $smtp_user = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_user'")->fetch_assoc()['setting_value'] ?? '';
                $smtp_pass = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_pass'")->fetch_assoc()['setting_value'] ?? '';
                $smtp_enc = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_encryption'")->fetch_assoc()['setting_value'] ?? 'tls';

                // Send OTP via email
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = $smtp_host;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_user;
                    $mail->Password   = $smtp_pass;
                    if ($smtp_enc == 'tls') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    } elseif ($smtp_enc == 'ssl') {
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    }
                    $mail->Port       = $smtp_port;

                    // ** FIX SSL Certificate Verification (for testing/development) **
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    // Recipients
                    $mail->setFrom($smtp_user, 'Admin Panel');
                    $mail->addAddress($row['email']); // admin's email

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Admin Login OTP';
                    $mail->Body    = "Your OTP is: <b>$otp</b>. It expires in 5 minutes.";

                    $mail->send();
                    header("Location: verify_otp.php");
                    exit();
                } catch (Exception $e) {
                    $error = "OTP could not be sent. Mailer Error: " . $mail->ErrorInfo;
                }
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "Username not found!";
        }
        $stmt->close();
        $conn->close();
    } else {
        $error = "Please fill all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(145deg, #0f0f1e 0%, #1a1a2f 100%);
            font-family: 'Inter', -apple-system, sans-serif;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-field {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            width: 100%;
            font-size: 16px;
        }
        .input-field:focus {
            border-color: #6366f1;
            outline: none;
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-primary {
            background: linear-gradient(145deg, #6366f1, #8b5cf6);
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 0.75rem;
            width: 100%;
            border: none;
            cursor: pointer;
        }
        .btn-primary:active { transform: scale(0.97); }
        .password-wrapper { position: relative; }
        .password-wrapper .input-field { padding-right: 40px; }
        .eye-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #a0a0c0;
        }
        .eye-toggle:hover { color: white; }
        .eye-toggle svg { width: 18px; height: 18px; fill: currentColor; }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <?php if ($success): ?>
            <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/30 rounded-xl text-emerald-200 text-sm">
                <i class="fas fa-check-circle mr-2"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-rose-500/20 border border-rose-500/30 rounded-xl text-rose-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="glass-card p-8">
            <div class="text-center mb-6">
                <div class="w-14 h-14 mx-auto mb-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-crown text-2xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">Admin Panel</h1>
                <p class="text-gray-400 text-xs mt-1">Secure access only</p>
            </div>

            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-300 text-xs font-medium mb-1">
                        <i class="fas fa-user mr-1 text-indigo-400"></i>Username
                    </label>
                    <input type="text" name="username" placeholder="Enter username" required autocomplete="off" class="input-field">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-300 text-xs font-medium mb-1">
                        <i class="fas fa-lock mr-1 text-indigo-400"></i>Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="off" class="input-field">
                        <button type="button" class="eye-toggle" onclick="togglePassword()">
                            <svg id="eyeIcon" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>
        </div>

        <div class="text-center mt-6">
            <a href="../snap/login.php" class="text-gray-500 hover:text-gray-300 text-xs transition">
                <i class="fas fa-arrow-left mr-1"></i> Back to user login
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('password');
            const type = field.type === 'password' ? 'text' : 'password';
            field.type = type;
            const icon = document.getElementById('eyeIcon');
            if (type === 'text') {
                icon.innerHTML = '<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>';
            } else {
                icon.innerHTML = '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>';
            }
        }
    </script>
</body>
</html>