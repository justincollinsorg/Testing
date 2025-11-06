<?php
require_once __DIR__ . '/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['error' => 'Invalid method']);
}

ensure_logged_in();
$username = $_SESSION['username'];

$userPath = get_user_path($username);
if (file_exists($userPath)) {
    $userData = read_json($userPath);
    $userData['online'] = false;
    $userData['lastSeen'] = time();
    write_json($userPath, $userData);
}

append_log($username, 'Logged out');

$sessionPath = SESSIONS_DIR . '/' . session_id() . '.json';
if (file_exists($sessionPath)) {
    unlink($sessionPath);
}

session_destroy();
respond_json(['success' => true]);
