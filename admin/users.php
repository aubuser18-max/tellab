<?php
// admin/users.php - Manage users with token support

session_start();
require_once '../common/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDB();
$message = '';

// Handle add/edit/delete/reset/regenerate
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        $expiry = $_POST['expiry'] ?: null;
        $status = $_POST['status'];
        // Generate unique token for new user
        $token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO users (link_token, username, password, email, expiry_timestamp, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $token, $username, $password, $email, $expiry, $status);
        $stmt->execute();
        $message = "User added.";
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $expiry = $_POST['expiry'] ?: null;
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, expiry_timestamp=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $email, $expiry, $status, $id);
        $stmt->execute();
        $message = "User updated.";
    } elseif (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn->query("DELETE FROM users WHERE id=$id");
        $message = "User deleted.";
    } elseif (isset($_POST['reset_password'])) {
        $id = $_POST['id'];
        $newpass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$newpass' WHERE id=$id");
        $message = "Password reset.";
    } elseif (isset($_POST['regenerate_token'])) {
        $id = $_POST['id'];
        $new_token = bin2hex(random_bytes(16));
        $conn->query("UPDATE users SET link_token = '$new_token' WHERE id = $id");
        $message = "Token regenerated.";
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Manage Users</title>
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
        .user-item {
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
            <h1 class="text-xl font-bold flex items-center"><i class="fas fa-users text-blue-400 mr-2"></i>Users</h1>
            <a href="index.php" class="text-sm text-gray-400"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-900/50 border border-green-500/50 p-3 rounded-xl text-sm text-green-100 mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Add User -->
        <details class="card">
            <summary class="text-sm font-medium cursor-pointer">➕ Add New User</summary>
            <form method="POST" class="mt-4 space-y-3">
                <input type="text" name="username" placeholder="Username" required class="input-field">
                <input type="password" name="password" placeholder="Password" required class="input-field">
                <input type="email" name="email" placeholder="Email" required class="input-field">
                <input type="datetime-local" name="expiry" class="input-field">
                <select name="status" class="input-field">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <button type="submit" name="add" class="btn-primary">Add User</button>
            </form>
        </details>

        <!-- User List -->
        <?php if ($users->num_rows == 0): ?>
            <p class="text-center text-gray-400 py-4">No users yet.</p>
        <?php else: ?>
            <?php while ($row = $users->fetch_assoc()): ?>
                <div class="user-item">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <span class="font-medium"><?= htmlspecialchars($row['username']) ?></span>
                            <span class="text-xs text-gray-400 ml-2">ID: <?= $row['id'] ?></span>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $row['status']=='active' ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300' ?>">
                            <?= $row['status'] ?>
                        </span>
                    </div>
                    <div class="text-xs text-gray-400 mb-2">
                        <?= $row['email'] ?> | Exp: <?= $row['expiry_timestamp'] ?: 'Never' ?>
                    </div>
                    
                    <!-- Token Display with Regenerate Button -->
                    <div class="text-xs text-gray-400 mb-3 flex items-center justify-between bg-black/30 p-2 rounded">
                        <span class="font-mono truncate max-w-[150px]"><?= $row['link_token'] ?></span>
                        <form method="POST" class="inline">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" name="regenerate_token" class="text-blue-400 hover:text-blue-300 text-xs" onclick="return confirm('Generate new token? Old links will stop working.')">Regenerate</button>
                        </form>
                    </div>

                    <!-- Edit Form -->
                    <form method="POST" class="grid grid-cols-2 gap-2 text-xs mb-2">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="text" name="username" value="<?= $row['username'] ?>" class="input-field text-xs" required>
                        <input type="email" name="email" value="<?= $row['email'] ?>" class="input-field text-xs" required>
                        <input type="datetime-local" name="expiry" value="<?= str_replace(' ', 'T', $row['expiry_timestamp']) ?>" class="input-field text-xs">
                        <select name="status" class="input-field text-xs">
                            <option value="active" <?= $row['status']=='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= $row['status']=='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                        <button type="submit" name="edit" class="bg-yellow-600 px-2 py-1 rounded">Edit</button>
                        <button type="submit" name="delete" class="bg-red-600 px-2 py-1 rounded" onclick="return confirm('Delete user?')">Delete</button>
                    </form>

                    <!-- Reset Password -->
                    <form method="POST" class="flex gap-2">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="text" name="new_password" placeholder="New password" class="input-field text-xs flex-1" required>
                        <button type="submit" name="reset_password" class="bg-purple-600 px-3 py-1 rounded text-xs">Reset</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <?php include 'common/bottom.php'; ?>
</body>
</html>