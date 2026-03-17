<?php
// admin/settings.php - Profile, SMTP, Password

session_start();
require_once '../common/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$conn = getDB();
$admin_id = $_SESSION['admin_id'];
$admin = $conn->query("SELECT username, email FROM admin WHERE id = $admin_id")->fetch_assoc();

// Fetch SMTP settings
$smtp = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption')");
while ($row = $result->fetch_assoc()) {
    $smtp[$row['setting_key']] = $row['setting_value'];
}

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = trim($_POST['email']);
        $stmt = $conn->prepare("UPDATE admin SET email = ? WHERE id = ?");
        $stmt->bind_param("si", $email, $admin_id);
        $stmt->execute();
        $message = "Email updated.";
        $admin['email'] = $email;
    }
    elseif (isset($_POST['update_smtp'])) {
        $host = trim($_POST['smtp_host']);
        $port = intval($_POST['smtp_port']);
        $user = trim($_POST['smtp_user']);
        $pass = trim($_POST['smtp_pass']);
        $enc = trim($_POST['smtp_encryption']);

        $updates = [
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_user' => $user,
            'smtp_pass' => $pass,
            'smtp_encryption' => $enc
        ];
        foreach ($updates as $key => $value) {
            $conn->query("UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
        }
        $message = "SMTP settings saved.";
    }
    elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $admin_pass = $conn->query("SELECT password FROM admin WHERE id = $admin_id")->fetch_assoc();
        if (password_verify($current, $admin_pass['password'])) {
            if ($new === $confirm) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $conn->query("UPDATE admin SET password = '$hash' WHERE id = $admin_id");
                $message = "Password changed.";
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password incorrect.";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color:#0f0f1a; color:#e2e8f0; padding:20px; }
        .card { background:rgba(255,255,255,0.05); border-radius:1.5rem; padding:1.5rem; margin-bottom:1.5rem; border:1px solid rgba(255,255,255,0.1); }
        .input-field { background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.15); color:white; padding:0.75rem; border-radius:0.75rem; width:100%; margin-bottom:1rem; }
        .btn-primary { background:linear-gradient(145deg,#3b82f6,#8b5cf6); color:white; padding:0.75rem; border-radius:0.75rem; width:100%; border:none; cursor:pointer; }
    </style>
</head>
<body>
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-4">Settings</h1>
        <?php if ($message): ?><div class="bg-green-800/50 p-3 rounded mb-4"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="bg-red-800/50 p-3 rounded mb-4"><?= $error ?></div><?php endif; ?>

        <!-- Email -->
        <div class="card">
            <h2 class="text-lg font-semibold mb-3">Your Email (for OTP)</h2>
            <form method="POST">
                <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required class="input-field" placeholder="admin@example.com">
                <button type="submit" name="update_profile" class="btn-primary">Update Email</button>
            </form>
        </div>

        <!-- SMTP -->
        <div class="card">
            <h2 class="text-lg font-semibold mb-3">SMTP Configuration</h2>
            <form method="POST">
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtp['smtp_host']??'') ?>" placeholder="smtp.gmail.com" class="input-field">
                <input type="number" name="smtp_port" value="<?= htmlspecialchars($smtp['smtp_port']??'587') ?>" class="input-field">
                <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtp['smtp_user']??'') ?>" placeholder="your-email@gmail.com" class="input-field">
                <input type="password" name="smtp_pass" value="<?= htmlspecialchars($smtp['smtp_pass']??'') ?>" placeholder="App password" class="input-field">
                <select name="smtp_encryption" class="input-field">
                    <option value="tls" <?= ($smtp['smtp_encryption']??'')=='tls'?'selected':'' ?>>TLS</option>
                    <option value="ssl" <?= ($smtp['smtp_encryption']??'')=='ssl'?'selected':'' ?>>SSL</option>
                    <option value="" <?= ($smtp['smtp_encryption']??'')==''?'selected':'' ?>>None</option>
                </select>
                <button type="submit" name="update_smtp" class="btn-primary">Save SMTP</button>
            </form>
        </div>

        <!-- Password -->
        <div class="card">
            <h2 class="text-lg font-semibold mb-3">Change Password</h2>
            <form method="POST">
                <input type="password" name="current_password" placeholder="Current" class="input-field">
                <input type="password" name="new_password" placeholder="New" class="input-field">
                <input type="password" name="confirm_password" placeholder="Confirm" class="input-field">
                <button type="submit" name="change_password" class="btn-primary">Change Password</button>
            </form>
        </div>

        <div class="text-center mt-6">
            <a href="?logout=1" class="text-red-400">Logout</a>
        </div>
    </div>
    <?php include 'common/bottom.php'; ?>
</body>
</html>