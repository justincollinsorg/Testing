<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_REQUEST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';
if ($action === '') {
    respond_json(['error' => 'Missing action']);
}

$username = $_SESSION['username'];

switch ($action) {
    case 'send':
        $target = trim($input['to'] ?? '');
        $type = $input['type'] ?? '';
        $payload = $input['payload'] ?? null;
        $callId = $input['callId'] ?? '';
        if ($target === '' || $type === '' || $payload === null || $callId === '') {
            respond_json(['error' => 'Missing fields']);
        }
        $safeTarget = sanitize_name($target);
        $signalPath = SIGNALS_DIR . '/' . $safeTarget . '.json';
        locked_file_operation($signalPath, function ($data) use ($username, $type, $payload, $callId) {
            if (!is_array($data)) {
                $data = [];
            }
            $data[] = [
                'from' => $username,
                'type' => $type,
                'payload' => $payload,
                'callId' => $callId,
                'timestamp' => time()
            ];
            return $data;
        }, []);
        respond_json(['success' => true]);
    case 'poll':
        $signalPath = SIGNALS_DIR . '/' . sanitize_name($username) . '.json';
        $messages = [];
        if (file_exists($signalPath)) {
            locked_file_operation($signalPath, function ($data) use (&$messages) {
                $messages = $data ?: [];
                return [];
            }, []);
        }
        respond_json(['signals' => $messages]);
    default:
        respond_json(['error' => 'Unknown action']);
}
