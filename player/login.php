<?php
// users/login.php - User login with eye toggle and scroll fix

require_once '../common/config.php';
session_start();

$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'account_inactive':
            $error = "Your account is inactive. Contact admin.";
            break;
        case 'account_expired':
            $error = "Your account has expired. Contact admin.";
            break;
        case 'account_not_found':
            $error = "Account not found.";
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status='active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user && password_verify($password, $user['password'])) {
        if ($user['expiry_timestamp'] && strtotime($user['expiry_timestamp']) < time()) {
            $error = "Account expired. Contact admin.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $error = "Invalid username or password.";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>User Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(145deg, #0a0a0f 0%, #1a1a2a 100%);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .login-card {
            background: rgba(20, 30, 50, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            padding: 2rem 1.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-field {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            width: 100%;
            font-size: 16px;
            transition: all 0.2s;
        }
        .input-field:focus {
            border-color: #3b82f6;
            outline: none;
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
        }
        .input-field::placeholder {
            color: rgba(255,255,255,0.4);
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .input-field {
            padding-right: 3rem;
        }
        .eye-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            color: #94a3b8;
            transition: color 0.2s;
        }
        .eye-toggle:hover {
            color: white;
        }
        .eye-toggle svg {
            width: 1.25rem;
            height: 1.25rem;
            fill: currentColor;
        }
        .btn-primary {
            background: linear-gradient(145deg, #3b82f6, #8b5cf6);
            color: white;
            font-weight: 600;
            padding: 0.85rem;
            border-radius: 1rem;
            width: 100%;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary:active { transform: scale(0.97); opacity: 0.9; }
        .text-label {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            display: block;
        }
        .error-box {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid rgba(220, 38, 38, 0.5);
            color: #fecaca;
            padding: 0.75rem;
            border-radius: 1rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl flex items-center justify-center">
                <i class="fas fa-user-circle text-2xl text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">User Login</h1>
            <p class="text-xs text-gray-400 mt-1">Enter your credentials</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="mb-4">
                <label class="text-label">Username</label>
                <input type="text" name="username" id="username" required autocomplete="off" class="input-field" placeholder="Enter username">
            </div>
            <div class="mb-6">
                <label class="text-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" required class="input-field" placeholder="Enter password">
                    <button type="button" class="eye-toggle" id="togglePassword">
                        <svg viewBox="0 0 16 16" id="eyeIcon">
                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-primary">Login</button>
        </form>

        <div class="mt-4 text-center">
            <a href="forgot.php" class="text-xs text-blue-400 hover:underline">Forgot password?</a>
        </div>
        <div class="mt-6 text-center">
            <a href="../snap/login.php" class="text-xs text-gray-500 hover:text-gray-300 transition">
                ← Back to public page
            </a>
        </div>
    </div>

    <script>
        // Eye toggle functionality
        const passwordField = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            if (type === 'text') {
                eyeIcon.innerHTML = `
                    <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/>
                    <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/>
                    <path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>
                `;
            } else {
                eyeIcon.innerHTML = `
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                `;
            }
        });

        // Scroll input into view when focused (helps when keyboard covers field)
        document.querySelectorAll('.input-field').forEach(input => {
            input.addEventListener('focus', () => {
                setTimeout(() => {
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });

        // Touch feedback
        document.querySelectorAll('button, a').forEach(el => {
            el.addEventListener('touchstart', () => el.style.opacity = '0.7');
            el.addEventListener('touchend', () => el.style.opacity = '1');
            el.addEventListener('touchcancel', () => el.style.opacity = '1');
        });
    </script>
</body>
</html>