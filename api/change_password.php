<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$newPassword = $input['newPassword'] ?? '';
if (strlen($newPassword) < 6) {
    respond_json(['error' => 'Password must be at least 6 characters long']);
}

$username = $_SESSION['username'];
$userPath = get_user_path($username);
if (!file_exists($userPath)) {
    respond_json(['error' => 'User not found']);
}

$userData = read_json($userPath);
$salt = bin2hex(random_bytes(8));
$hash = password_hash($newPassword . $salt, PASSWORD_DEFAULT);
$now = time();
$userData['passwordSalt'] = $salt;
$userData['passwordHash'] = $hash;
$userData['passwordUpdatedAt'] = $now;
$userData['lastSeen'] = $now;
write_json($userPath, $userData);

$passwordHistoryPath = get_password_history_path($username);
locked_file_operation($passwordHistoryPath, function ($history) use ($hash, $salt, $now) {
    if (!is_array($history)) {
        $history = [];
    }
    array_unshift($history, [
        'hash' => $hash,
        'salt' => $salt,
        'changedAt' => $now
    ]);
    return array_slice($history, 0, 20);
}, []);

append_log($username, 'Updated password');

respond_json(['success' => true]);
