<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$username = $_SESSION['username'];
$userPath = get_user_path($username);
if (!file_exists($userPath)) {
    respond_json(['error' => 'User missing']);
}

$userData = read_json($userPath);
$userData['online'] = true;
$userData['lastSeen'] = time();
write_json($userPath, $userData);

respond_json(['success' => true]);
