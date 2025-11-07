<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$username = $_SESSION['username'];

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');

set_time_limit(0);
ignore_user_abort(true);

function send_event($type, $payload)
{
    echo 'event: ' . $type . "\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
}

$lastStateHash = null;

while (!connection_aborted()) {
    $state = build_state_snapshot($username);
    $stateHash = md5(json_encode($state));
    if ($stateHash !== $lastStateHash) {
        send_event('state', $state);
        $lastStateHash = $stateHash;
    }

    $signalPath = SIGNALS_DIR . '/' . sanitize_name($username) . '.json';
    if (file_exists($signalPath)) {
        $messages = [];
        locked_file_operation($signalPath, function ($data) use (&$messages) {
            $messages = $data ?: [];
            return [];
        }, []);

        foreach ($messages as $message) {
            send_event('signal', $message);
        }
    }

    echo ": keep-alive\n\n";
    @ob_flush();
    @flush();
    sleep(2);
}

exit;
