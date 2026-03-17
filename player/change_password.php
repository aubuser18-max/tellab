<?php
// users/change_password.php - Step 1: Verify current password, then send OTP

session_start();
require_once 'auth_check.php';
require_once '../common/config.php';
require_once '../common/PHPMailer/Exception.php';
require_once '../common/PHPMailer/PHPMailer.php';
require_once '../common/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Fetch user data (password hash and email)
$user = $conn->query("SELECT password, email FROM users WHERE id = $user_id")->fetch_assoc();
$user_email = $user['email'] ?? '';
$conn->close();

$error = '';
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_current'])) {
    $current_password = $_POST['current_password'] ?? '';

    if (empty($current_password)) {
        $error = "Please enter your current password.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } else {
        // Current password correct – generate OTP
        $otp = rand(100000, 999999);
        $expiry = time() + 300; // 5 minutes

        // Store OTP in session
        $_SESSION['pw_change_otp'] = $otp;
        $_SESSION['pw_change_expiry'] = $expiry;
        $_SESSION['pw_change_verified'] = true; // flag to indicate current password verified

        // Fetch SMTP settings
        $conn = getDB();
        $smtp_host = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_host'")->fetch_assoc()['setting_value'] ?? '';
        $smtp_port = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_port'")->fetch_assoc()['setting_value'] ?? '587';
        $smtp_user = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_user'")->fetch_assoc()['setting_value'] ?? '';
        $smtp_pass = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_pass'")->fetch_assoc()['setting_value'] ?? '';
        $smtp_enc = $conn->query("SELECT setting_value FROM settings WHERE setting_key='smtp_encryption'")->fetch_assoc()['setting_value'] ?? 'tls';
        $conn->close();

        // Send OTP email
        $mail = new PHPMailer(true);
        try {
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

            // Disable SSL verification for development
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom($smtp_user, 'Your App');
            $mail->addAddress($user_email);

            $mail->isHTML(true);
            $mail->Subject = 'Password Change OTP';
            $mail->Body    = "Your OTP for password change is: <b>$otp</b>. It expires in 5 minutes.";

            $mail->send();
            header("Location: verify_change_otp.php");
            exit();
        } catch (Exception $e) {
            $error = "Could not send OTP. " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Verify Current Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(145deg, #0a0a0f 0%, #1a1a2a 100%);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            overflow-y: auto;
            padding: 1rem;
        }
        .card {
            max-width: 400px;
            margin: 2rem auto;
            background: rgba(20,30,50,0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 2rem;
            padding: 2rem;
        }
        .input-field {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            width: 100%;
            font-size: 16px;
            margin-bottom: 1rem;
        }
        .input-field:focus {
            border-color: #3b82f6;
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 1rem;
            width: 100%;
            border: none;
            cursor: pointer;
        }
        .btn-primary:active { transform: scale(0.97); }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="card">
        <h1 class="text-2xl font-bold mb-4 flex items-center"><i class="fas fa-lock text-yellow-400 mr-2"></i>Change Password</h1>

        <?php if ($error): ?>
            <div class="bg-red-900/60 border border-red-500/50 p-3 rounded-xl text-sm text-red-100 mb-4"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="bg-green-900/60 border border-green-500/50 p-3 rounded-xl text-sm text-green-100 mb-4"><?= $message ?></div>
        <?php endif; ?>

        <?php if (empty($user_email)): ?>
            <div class="bg-yellow-900/60 border border-yellow-500/50 p-3 rounded-xl text-sm text-yellow-100 mb-4">
                No email associated with your account. Contact admin to set an email.
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-300 mb-4">OTP will be sent to: <strong><?= htmlspecialchars($user_email) ?></strong></p>
        <?php endif; ?>

        <form method="POST">
            <label class="text-sm text-gray-400 mb-1 block">Current Password</label>
            <div class="password-wrapper mb-4">
                <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required class="input-field pr-10">
                <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-white" onclick="togglePassword()">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </button>
            </div>
            <button type="submit" name="verify_current" class="btn-primary" <?= empty($user_email) ? 'disabled' : '' ?>>Verify & Send OTP</button>
        </form>

        <div class="text-center mt-4">
            <a href="dashboard.php" class="text-sm text-blue-400 hover:underline">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('current_password');
            const icon = document.getElementById('toggleIcon');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>