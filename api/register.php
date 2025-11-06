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
$displayName = trim($input['displayName'] ?? '');

if ($username === '' || $password === '' || $displayName === '') {
    respond_json(['error' => 'Missing fields']);
}

$userPath = get_user_path($username);
if (file_exists($userPath)) {
    respond_json(['error' => 'User already exists']);
}

$salt = bin2hex(random_bytes(8));
$hash = password_hash($password . $salt, PASSWORD_DEFAULT);
$now = time();
$userData = [
    'username' => $username,
    'displayName' => $displayName,
    'createdAt' => $now,
    'passwordSalt' => $salt,
    'passwordHash' => $hash,
    'passwordUpdatedAt' => $now,
    'statusMessage' => '',
    'lastLogin' => null,
    'online' => false,
    'lastSeen' => $now,
    'metadata' => [
        'passwordHistoryFile' => basename(get_password_history_path($username)),
        'logs' => []
    ]
];

write_json($userPath, $userData);

$passwordHistoryPath = get_password_history_path($username);
write_json($passwordHistoryPath, [
    [
        'hash' => $hash,
        'salt' => $salt,
        'changedAt' => $now
    ]
]);

append_log($username, 'Registered a new account');

respond_json(['success' => true]);
