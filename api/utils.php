<?php
session_start();

define('DATA_DIR', __DIR__ . '/../data');
define('USER_DIR', DATA_DIR . '/users');
define('PASSWORD_HISTORY_DIR', DATA_DIR . '/password_history');
define('SIGNALS_DIR', DATA_DIR . '/signals');
define('SESSIONS_DIR', DATA_DIR . '/sessions');
define('LOGS_DIR', DATA_DIR . '/logs');

foreach ([DATA_DIR, USER_DIR, PASSWORD_HISTORY_DIR, SIGNALS_DIR, SESSIONS_DIR, LOGS_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function sanitize_name($value)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($value));
}

function read_json($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return $data === null ? $default : $data;
}

function write_json($path, $data) {
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $fp = fopen($tmp, 'w');
    if (!$fp) {
        throw new RuntimeException('Cannot open temp file for writing');
    }
    fwrite($fp, $json);
    fclose($fp);
    rename($tmp, $path);
}

function load_all_users_with_presence()
{
    $users = [];
    $now = time();
    foreach (glob(USER_DIR . '/*.json') as $file) {
        $data = read_json($file);
        if ($now - ($data['lastSeen'] ?? 0) > 12) {
            $data['online'] = false;
            write_json($file, $data);
        }
        if (isset($data['username'])) {
            $users[$data['username']] = $data;
        }
    }

    return $users;
}

function build_state_snapshot($currentUser)
{
    $users = load_all_users_with_presence();
    $passwordHistory = read_json(get_password_history_path($currentUser), []);

    $sessions = [];
    foreach (glob(SESSIONS_DIR . '/*.json') as $sessionFile) {
        $sessions[] = read_json($sessionFile);
    }

    return [
        'currentUser' => $users[$currentUser] ?? null,
        'passwordHistory' => $passwordHistory,
        'users' => array_values($users),
        'sessions' => $sessions,
        'serverTime' => time()
    ];
}

function locked_file_operation($path, callable $callback, $default = []) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $fp = fopen($path, 'c+');
    if (!$fp) {
        throw new RuntimeException('Unable to open file');
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Unable to obtain lock');
        }
        $contents = stream_get_contents($fp);
        $data = $contents ? json_decode($contents, true) : $default;
        if ($data === null) {
            $data = $default;
        }
        $result = $callback($data);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($result, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result;
    } catch (Throwable $e) {
        flock($fp, LOCK_UN);
        fclose($fp);
        throw $e;
    }
}

function ensure_logged_in() {
    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
}

function get_user_path($username) {
    $safe = sanitize_name($username);
    return USER_DIR . '/' . $safe . '.json';
}

function get_password_history_path($username) {
    $safe = sanitize_name($username);
    return PASSWORD_HISTORY_DIR . '/' . $safe . '.json';
}

function append_log($username, $message) {
    $path = LOGS_DIR . '/' . date('Y-m-d') . '.log';
    $entry = sprintf("[%s] %s: %s\n", date('c'), $username, $message);
    file_put_contents($path, $entry, FILE_APPEND);
}

function respond_json($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}
