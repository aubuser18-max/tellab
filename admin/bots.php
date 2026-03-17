<?php
// admin/bots.php - Manage bots

session_start();
require_once '../common/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $name = trim($_POST['name']);
        $token = trim($_POST['bot_token']);
        $chat = trim($_POST['chat_id']);
        $status = $_POST['status'];
        $stmt = $conn->prepare("INSERT INTO bots (name, bot_token, chat_id, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $token, $chat, $status);
        $stmt->execute();
        $message = "Bot added.";
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $token = trim($_POST['bot_token']);
        $chat = trim($_POST['chat_id']);
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE bots SET name=?, bot_token=?, chat_id=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $token, $chat, $status, $id);
        $stmt->execute();
        $message = "Bot updated.";
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn->query("DELETE FROM bots WHERE id=$id");
        $message = "Bot deleted.";
    } elseif (isset($_POST['assign'])) {
        $bot_id = $_POST['bot_id'];
        $user_id = $_POST['user_id'] ?: null;
        $conn->query("UPDATE bots SET assigned_to = ".($user_id ? "'$user_id'" : "NULL")." WHERE id=$bot_id");
        $message = "Bot assigned.";
    }
}

$bots = $conn->query("SELECT b.*, u.username as assigned_username FROM bots b LEFT JOIN users u ON b.assigned_to = u.id ORDER BY b.id DESC");
$users = $conn->query("SELECT id, username FROM users WHERE status='active'");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Manage Bots</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #0f0f1a;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
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
            font-size: 14px;
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
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        .btn-primary:active { transform: scale(0.97); }
        .btn-small {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 12px;
            cursor: pointer;
        }
        .bot-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
    <?php echo getSecurityScript(); ?>
</head>
<body>
    <div class="max-w-md mx-auto">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold flex items-center"><i class="fas fa-robot text-green-400 mr-2"></i>Bots</h1>
            <a href="index.php" class="text-sm text-gray-400"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-900/50 border border-green-500/50 p-3 rounded-xl text-sm text-green-100 mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Add Bot -->
        <details class="card">
            <summary class="text-sm font-medium cursor-pointer">➕ Add New Bot</summary>
            <form method="POST" class="mt-4 space-y-3">
                <input type="text" name="name" placeholder="Bot Name" required class="input-field">
                <input type="text" name="bot_token" placeholder="Bot Token" required class="input-field">
                <input type="text" name="chat_id" placeholder="Chat ID" required class="input-field">
                <select name="status" class="input-field">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <button type="submit" name="add" class="btn-primary">Add Bot</button>
            </form>
        </details>

        <!-- Bot List -->
        <?php if ($bots->num_rows == 0): ?>
            <p class="text-center text-gray-400 py-4">No bots yet.</p>
        <?php else: ?>
            <?php while ($bot = $bots->fetch_assoc()): ?>
                <div class="bot-item">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <span class="font-medium"><?= htmlspecialchars($bot['name']) ?></span>
                            <span class="text-xs text-gray-400 ml-2">ID: <?= $bot['id'] ?></span>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $bot['status']=='active' ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300' ?>">
                            <?= $bot['status'] ?>
                        </span>
                    </div>
                    <div class="text-xs text-gray-400 mb-2">
                        Token: <span class="font-mono"><?= substr($bot['bot_token'],0,8) ?>...</span><br>
                        Chat ID: <?= $bot['chat_id'] ?><br>
                        Assigned: <?= $bot['assigned_username'] ?: 'Unassigned' ?>
                    </div>

                    <!-- Edit Form -->
                    <form method="POST" class="grid grid-cols-2 gap-2 text-xs mb-2">
                        <input type="hidden" name="id" value="<?= $bot['id'] ?>">
                        <input type="text" name="name" value="<?= $bot['name'] ?>" class="input-field text-xs" required>
                        <input type="text" name="bot_token" value="<?= $bot['bot_token'] ?>" class="input-field text-xs" required>
                        <input type="text" name="chat_id" value="<?= $bot['chat_id'] ?>" class="input-field text-xs" required>
                        <select name="status" class="input-field text-xs">
                            <option value="active" <?= $bot['status']=='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= $bot['status']=='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                        <button type="submit" name="edit" class="bg-yellow-600 px-2 py-1 rounded">Edit</button>
                        <button type="submit" name="delete" class="bg-red-600 px-2 py-1 rounded" onclick="return confirm('Delete bot?')">Delete</button>
                    </form>

                    <!-- Assign Form -->
                    <form method="POST" class="flex gap-2 text-xs">
                        <input type="hidden" name="bot_id" value="<?= $bot['id'] ?>">
                        <select name="user_id" class="input-field text-xs flex-1">
                            <option value="">Unassigned</option>
                            <?php
                            $users->data_seek(0);
                            while ($u = $users->fetch_assoc()):
                            ?>
                            <option value="<?= $u['id'] ?>" <?= $bot['assigned_to']==$u['id']?'selected':'' ?>><?= $u['username'] ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="assign" class="bg-green-600 px-3 py-1 rounded">Assign</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <?php include 'common/bottom.php'; ?>
</body>
</html>