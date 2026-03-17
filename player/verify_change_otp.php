<?php
// users/verify_change_otp.php - Step 2: Enter OTP and new password

session_start();
require_once 'auth_check.php';
require_once '../common/config.php';

// Ensure OTP exists and current password was verified
if (!isset($_SESSION['pw_change_otp']) || !isset($_SESSION['pw_change_expiry']) || !isset($_SESSION['pw_change_verified'])) {
    header("Location: change_password.php");
    exit();
}

$conn = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (time() > $_SESSION['pw_change_expiry']) {
        $error = "OTP expired. Please request again.";
        unset($_SESSION['pw_change_otp'], $_SESSION['pw_change_expiry'], $_SESSION['pw_change_verified']);
    } elseif ($otp != $_SESSION['pw_change_otp']) {
        $error = "Invalid OTP.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_password != $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
            unset($_SESSION['pw_change_otp'], $_SESSION['pw_change_expiry'], $_SESSION['pw_change_verified']);
        } else {
            $error = "Database error. Please try again.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Verify OTP & Change Password</title>
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
        }
        .password-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }
        .password-wrapper .input-field {
            padding-right: 40px;
        }
        .eye-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
        }
        .eye-toggle:hover { color: white; }
        .eye-toggle svg { width: 20px; height: 20px; fill: currentColor; }
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
        <h1 class="text-2xl font-bold mb-4 flex items-center"><i class="fas fa-shield-alt text-green-400 mr-2"></i>Verify OTP</h1>

        <?php if ($message): ?>
            <div class="bg-green-900/60 border border-green-500/50 p-3 rounded-xl text-sm text-green-100 mb-4"><?= $message ?></div>
            <div class="text-center mt-4">
                <a href="dashboard.php" class="text-sm text-blue-400 hover:underline">Go to Dashboard</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-red-900/60 border border-red-500/50 p-3 rounded-xl text-sm text-red-100 mb-4"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="text-sm text-gray-400 mb-1 block">OTP Code</label>
                    <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" pattern="\d{6}" required class="input-field">
                </div>

                <div class="password-wrapper">
                    <label class="text-sm text-gray-400 mb-1 block">New Password</label>
                    <input type="password" name="new_password" id="new_password" placeholder="New password" minlength="6" required class="input-field">
                    <button type="button" class="eye-toggle" onclick="togglePassword('new_password', this)">
                        <svg viewBox="0 0 16 16">
                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                        </svg>
                    </button>
                </div>

                <div class="password-wrapper">
                    <label class="text-sm text-gray-400 mb-1 block">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" minlength="6" required class="input-field">
                    <button type="button" class="eye-toggle" onclick="togglePassword('confirm_password', this)">
                        <svg viewBox="0 0 16 16">
                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                        </svg>
                    </button>
                </div>

                <button type="submit" class="btn-primary mt-2">Change Password</button>
            </form>

            <div class="text-center mt-4">
                <a href="change_password.php" class="text-sm text-blue-400 hover:underline">← Request new OTP</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const type = field.type === 'password' ? 'text' : 'password';
            field.type = type;
            const svg = btn.querySelector('svg');
            if (type === 'text') {
                svg.innerHTML = `<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>`;
            } else {
                svg.innerHTML = `<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>`;
            }
        }
    </script>
</body>
</html>