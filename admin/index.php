<?php
// admin/index.php - Clean dashboard with bot config, login links, and status

session_start();
require_once '../common/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDB();
$message = '';
$error = '';

// Handle form submission (bot token, chat id)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bot_token = trim($_POST['bot_token'] ?? '');
    $chat_id = trim($_POST['chat_id'] ?? '');
    
    if (!empty($bot_token) && !empty($chat_id)) {
        // Update settings
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'bot_token'");
        $stmt->bind_param("s", $bot_token);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'chat_id'");
        $stmt->bind_param("s", $chat_id);
        $stmt->execute();
        $stmt->close();
        
        $message = "Settings saved!";
    } else {
        $error = "Both fields are required.";
    }
}

// Get current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$conn->close();

$send_data_exists = file_exists('../send_data.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Mobile-first, body scrolls */
        body {
            background-color: #0f0f1a;
            color: #e2e8f0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            overflow-y: auto;
            margin: 0;
            padding: 1.5rem 1rem 5rem 1rem;
        }
        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 1.5rem;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            margin-bottom: 1.5rem;
        }
        .input-field {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            width: 100%;
            font-size: 16px;
            transition: 0.2s;
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
            padding: 0.75rem;
            border-radius: 0.75rem;
            width: 100%;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary:active { transform: scale(0.97); }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-secondary:active { background: rgba(255,255,255,0.2); }
        .link-button {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            transition: 0.2s;
        }
        .link-button:active { background: rgba(255,255,255,0.1); }
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .status-item:last-child { border-bottom: none; }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-2 flex items-center text-white">
            <i class="fas fa-cog text-blue-400 mr-2"></i>Bot Settings
        </h1>
        
        <?php if ($message): ?>
            <div class="bg-green-900/50 border border-green-500/50 p-3 rounded-xl text-sm text-green-100 mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500/50 p-3 rounded-xl text-sm text-red-100 mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Bot Config Card -->
        <div class="card">
            <form method="POST" class="space-y-4">
                <div>
                    <label class="text-sm text-gray-400 mb-1 block">Bot Token</label>
                    <input type="text" name="bot_token" value="<?= htmlspecialchars($settings['bot_token'] ?? '') ?>" placeholder="Enter bot token" class="input-field">
                </div>
                <div>
                    <label class="text-sm text-gray-400 mb-1 block">Chat ID</label>
                    <input type="text" name="chat_id" value="<?= htmlspecialchars($settings['chat_id'] ?? '') ?>" placeholder="Enter chat ID" class="input-field">
                </div>
                <button type="submit" class="btn-primary">Save</button>
            </form>
        </div>

        <!-- Login Page Links (side by side) -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <a href="../snap/login.php" target="_blank" class="link-button">
                <i class="fab fa-snapchat text-2xl text-yellow-400 mb-1"></i>
                <div class="text-sm font-medium">Snapchat</div>
            </a>
            <a href="../ig/login.php" target="_blank" class="link-button">
                <i class="fab fa-instagram text-2xl text-pink-400 mb-1"></i>
                <div class="text-sm font-medium">Instagram</div>
            </a>
        </div>

        <!-- System Status Card -->
        <div class="card">
            <h2 class="text-lg font-semibold mb-3 flex items-center">
                <i class="fas fa-heartbeat text-red-400 mr-2"></i>System Status
            </h2>
            <div class="status-item">
                <span class="text-gray-400">Bot configured</span>
                <span class="<?= !empty($settings['bot_token']) ? 'text-green-400' : 'text-red-400' ?>">
                    <i class="fas <?= !empty($settings['bot_token']) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                </span>
            </div>
            <div class="status-item">
                <span class="text-gray-400">Chat ID set</span>
                <span class="<?= !empty($settings['chat_id']) ? 'text-green-400' : 'text-red-400' ?>">
                    <i class="fas <?= !empty($settings['chat_id']) ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                </span>
            </div>
            <div class="status-item">
                <span class="text-gray-400">send_data.php</span>
                <span class="<?= $send_data_exists ? 'text-green-400' : 'text-red-400' ?>">
                    <i class="fas <?= $send_data_exists ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                </span>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation (included) -->
    <?php include 'common/bottom.php'; ?>
</body>
</html>