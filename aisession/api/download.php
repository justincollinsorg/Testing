<?php

declare(strict_types=1);

use AISession\Storage\ConversationStore;

require_once __DIR__ . '/../src/ConversationStore.php';

$sessionId = $_GET['session'] ?? '';
if (!is_string($sessionId) || $sessionId === '') {
    http_response_code(400);
    echo 'Missing session identifier';
    return;
}

$dataRoot = realpath(__DIR__ . '/../../data');
if ($dataRoot === false) {
    $dataRoot = __DIR__ . '/../../data';
}
$store = new ConversationStore($dataRoot . '/aisession_sessions');

$messages = $store->load($sessionId);
if ($messages === []) {
    http_response_code(404);
    echo 'Conversation not found';
    return;
}

$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'aisession_zip_');
if ($tmpFile === false) {
    http_response_code(500);
    echo 'Unable to create temporary file';
    return;
}

if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    unlink($tmpFile);
    http_response_code(500);
    echo 'Unable to create archive';
    return;
}

$zip->addFromString('conversation.json', json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$zip->addFromString('conversation.txt', renderTextTranscript($messages));
$zip->close();

$filename = 'conversation-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId) . '.zip';

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($tmpFile));
header('Content-Disposition: attachment; filename="' . $filename . '"');

readfile($tmpFile);
unlink($tmpFile);

function renderTextTranscript(array $messages): string
{
    $lines = [];
    foreach ($messages as $message) {
        $role = strtoupper($message['role'] ?? 'ASSISTANT');
        $timestamp = $message['timestamp'] ?? '';
        $content = $message['content'] ?? '';
        $lines[] = $timestamp . ' [' . $role . ']';
        $lines[] = $content;
        $lines[] = str_repeat('-', 40);
    }

    return implode(PHP_EOL, $lines);
}
