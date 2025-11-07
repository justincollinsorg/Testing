<?php

declare(strict_types=1);

namespace AISession\Storage;

class ConversationStore
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                throw new \RuntimeException('Unable to create storage directory: ' . $this->directory);
            }
        }
    }

    public function load(string $sessionId): array
    {
        $path = $this->buildPath($sessionId);
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return [];
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    public function save(string $sessionId, array $messages): void
    {
        $path = $this->buildPath($sessionId);
        $payload = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new \RuntimeException('Failed to encode conversation for storage.');
        }

        if (file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to persist conversation to ' . $path);
        }
    }

    private function buildPath(string $sessionId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId);
        return $this->directory . DIRECTORY_SEPARATOR . $safeId . '.json';
    }
}
