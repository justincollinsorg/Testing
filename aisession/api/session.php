<?php

declare(strict_types=1);

use AISession\Service\ChatgotClient;
use AISession\Storage\ConversationStore;

require_once __DIR__ . '/../src/ConversationStore.php';
require_once __DIR__ . '/../src/ChatgotClient.php';

header('Content-Type: application/json');

session_start();

$sessionKey = 'aisession_conversation_id';
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = bin2hex(random_bytes(16));
}
$conversationId = $_SESSION[$sessionKey];

$dataRoot = realpath(__DIR__ . '/../../data');
if ($dataRoot === false) {
    $dataRoot = __DIR__ . '/../../data';
}
$storage = new ConversationStore($dataRoot . '/aisession_sessions');
$client = new ChatgotClient();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $messages = $storage->load($conversationId);
    echo json_encode([
        'sessionId' => $conversationId,
        'messages' => $messages,
        'chatgotConfigured' => $client->isConfigured(),
    ]);
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    return;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload) || !isset($payload['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request payload']);
    return;
}

$userMessage = trim((string) $payload['message']);
if ($userMessage === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Message cannot be empty']);
    return;
}

$messages = $storage->load($conversationId);
$messages[] = [
    'id' => uniqid('user_', true),
    'role' => 'user',
    'content' => $userMessage,
    'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
];

$assistantMessage = handleAssistantResponse($userMessage, $conversationId, $client, $messages);

$messages[] = [
    'id' => uniqid('assistant_', true),
    'role' => 'assistant',
    'content' => $assistantMessage,
    'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
];

$storage->save($conversationId, $messages);

echo json_encode([
    'messages' => $messages,
    'assistantMessage' => $assistantMessage,
]);

function handleAssistantResponse(string $userMessage, string $conversationId, ChatgotClient $client, array $messages): string
{
    if (shouldOfferZip($userMessage)) {
        $relativeLink = '/aisession/api/download.php?session=' . urlencode($conversationId) . '&t=' . time();
        return 'You can download a ZIP archive of this conversation here: [Download conversation ZIP](' . $relativeLink . ').';
    }

    $chatMessages = array_map(static function (array $message): array {
        return [
            'role' => $message['role'] ?? 'assistant',
            'content' => $message['content'] ?? '',
        ];
    }, $messages);

    try {
        return $client->send($chatMessages);
    } catch (\Throwable $exception) {
        return 'I ran into a problem while contacting the Chatgot API: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function shouldOfferZip(string $message): bool
{
    $normalized = strtolower($message);
    return (str_contains($normalized, 'zip') || str_contains($normalized, 'download'))
        && (str_contains($normalized, 'conversation') || str_contains($normalized, 'chat') || str_contains($normalized, 'history'));
}
