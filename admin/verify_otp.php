<?php
// admin/verify_otp.php - Step 2: Enter OTP

session_start();
require_once '../common/config.php';

// Check if 2FA session exists
if (!isset($_SESSION['2fa_admin_id']) || !isset($_SESSION['2fa_otp']) || !isset($_SESSION['2fa_expiry'])) {
    header("Location: login.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_otp = trim($_POST['otp'] ?? '');

    // Check expiry
    if (time() > $_SESSION['2fa_expiry']) {
        $error = "OTP expired. Please login again.";
        // Clear session
        unset($_SESSION['2fa_admin_id'], $_SESSION['2fa_otp'], $_SESSION['2fa_expiry']);
    } elseif ($user_otp == $_SESSION['2fa_otp']) {
        // OTP correct – final login
        $_SESSION['admin_id'] = $_SESSION['2fa_admin_id'];
        // You may also fetch username from DB to set session
        $conn = getDB();
        $id = $_SESSION['2fa_admin_id'];
        $result = $conn->query("SELECT username FROM admin WHERE id = $id");
        $admin = $result->fetch_assoc();
        $_SESSION['admin_username'] = $admin['username'];
        $conn->close();

        // Clear 2FA session data
        unset($_SESSION['2fa_admin_id'], $_SESSION['2fa_otp'], $_SESSION['2fa_expiry']);

        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid OTP.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(145deg, #0f0f1e 0%, #1a1a2f 100%);
            font-family: 'Inter', sans-serif;
        }
        .glass-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 32px;
            padding: 2rem;
        }
        .input-field {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            width: 100%;
            font-size: 16px;
            text-align: center;
            letter-spacing: 0.5rem;
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
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-rose-500/20 border border-rose-500/30 rounded-xl text-rose-200 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="text-center mb-6">
                <i class="fas fa-shield-alt text-4xl text-blue-400 mb-2"></i>
                <h1 class="text-2xl font-bold text-white">Two‑Factor Auth</h1>
                <p class="text-gray-400 text-sm mt-1">Enter the 6‑digit code sent to your email</p>
            </div>

            <form method="POST">
                <div class="mb-6">
                    <input type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="______" required class="input-field">
                </div>
                <button type="submit" class="btn-primary">Verify & Login</button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="text-sm text-gray-400 hover:text-gray-300">← Back to login</a>
            </div>
        </div>
    </div>
</body>
</html>