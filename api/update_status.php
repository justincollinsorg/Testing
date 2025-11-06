<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$status = trim($input['status'] ?? '');
$username = $_SESSION['username'];
$userPath = get_user_path($username);

if (!file_exists($userPath)) {
    respond_json(['error' => 'User not found']);
}

$userData = read_json($userPath);
$userData['statusMessage'] = $status;
$userData['lastSeen'] = time();
write_json($userPath, $userData);

append_log($username, 'Updated status to "' . $status . '"');

respond_json(['success' => true, 'status' => $status]);
