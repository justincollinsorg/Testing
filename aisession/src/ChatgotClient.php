<?php

declare(strict_types=1);

namespace AISession\Service;

class ChatgotClient
{
    private ?string $apiKey;
    private string $apiUrl;
    private string $model;
    private float $temperature;

    public function __construct(?string $apiKey = null, ?string $apiUrl = null, ?string $model = null, float $temperature = 0.7)
    {
        $this->apiKey = $apiKey ?? getenv('CHATGOT_API_KEY') ?: null;
        $this->apiUrl = $apiUrl ?? getenv('CHATGOT_API_URL') ?: 'https://api.openai.com/v1/chat/completions';
        $this->model = $model ?? getenv('CHATGOT_MODEL') ?: 'gpt-4o-mini';
        $this->temperature = $temperature;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * @param array<int, array<string, string>> $messages
     */
    public function send(array $messages): string
    {
        if (!$this->isConfigured()) {
            return $this->buildOfflineFallback($messages);
        }

        $payload = json_encode([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
        ]);

        if ($payload === false) {
            throw new \RuntimeException('Failed to encode chat payload.');
        }

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize request to Chatgot API.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Chatgot API request failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected response from Chatgot API.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? 'Unknown error communicating with Chatgot API.';
            throw new \RuntimeException('Chatgot API returned HTTP ' . $status . ': ' . $message);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new \RuntimeException('Chatgot API response missing message content.');
        }

        return $content;
    }

    /**
     * @param array<int, array<string, string>> $messages
     */
    private function buildOfflineFallback(array $messages): string
    {
        $lastUserMessage = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $lastUserMessage = $messages[$i]['content'] ?? '';
                break;
            }
        }

        $summary = trim($lastUserMessage);
        if ($summary === '') {
            $summary = 'Tell me something you would like to discuss.';
        }

        return "(Offline mode) I understood your request: \n\n" . $summary . "\n\n" .
            "Configure CHATGOT_API_KEY to unlock live responses from the Chatgot API.";
    }
}
