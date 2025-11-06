<?php
require_once __DIR__ . '/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Invalid method']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    respond_json(['error' => 'Missing credentials']);
}

$userPath = get_user_path($username);
if (!file_exists($userPath)) {
    respond_json(['error' => 'Invalid username or password']);
}

$userData = read_json($userPath);

if (!password_verify($password . ($userData['passwordSalt'] ?? ''), $userData['passwordHash'] ?? '')) {
    respond_json(['error' => 'Invalid username or password']);
}

$_SESSION['username'] = $username;

$userData['lastLogin'] = time();
$userData['online'] = true;
$userData['lastSeen'] = time();
write_json($userPath, $userData);

$sessionPath = SESSIONS_DIR . '/' . session_id() . '.json';
write_json($sessionPath, [
    'username' => $username,
    'createdAt' => time()
]);

append_log($username, 'Logged in');

respond_json(['success' => true, 'user' => $userData]);
