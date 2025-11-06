<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$currentUser = $_SESSION['username'];

$users = [];
$now = time();
foreach (glob(USER_DIR . '/*.json') as $file) {
    $data = read_json($file);
    if ($now - ($data['lastSeen'] ?? 0) > 12) {
        $data['online'] = false;
        write_json($file, $data);
    }
    $users[$data['username']] = $data;
}

$passwordHistoryPath = get_password_history_path($currentUser);
$passwordHistory = read_json($passwordHistoryPath, []);

$sessions = [];
foreach (glob(SESSIONS_DIR . '/*.json') as $sessionFile) {
    $sessions[] = read_json($sessionFile);
}

respond_json([
    'currentUser' => $users[$currentUser] ?? null,
    'passwordHistory' => $passwordHistory,
    'users' => array_values($users),
    'sessions' => $sessions,
    'serverTime' => time()
]);
