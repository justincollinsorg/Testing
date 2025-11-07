<?php

declare(strict_types=1);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Session Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/chat.css">
</head>
<body>
    <div class="system-banner">
        <span class="badge rounded-pill bg-warning" id="status-badge">Chatgot API Offline</span>
        <span id="loading-indicator" class="spinner-border spinner-border-sm text-primary d-none" role="status">
            <span class="visually-hidden">Loading...</span>
        </span>
        <span>Welcome to your AI Session. The conversation history is preserved for the duration of your visit.</span>
    </div>
    <main class="chat-shell">
        <section class="chat-history" id="chat-history" aria-live="polite"></section>
        <div class="composer-wrapper">
            <form id="composer-form" class="composer-inner needs-validation" novalidate>
                <label for="prompt" class="form-label fw-semibold">Send a message</label>
                <textarea id="prompt" class="form-control" placeholder="Type your message... Use Shift + Enter for a new line"></textarea>
                <div class="d-flex justify-content-end align-items-center mt-3 gap-3">
                    <small class="text-muted">Press Enter to send • Shift + Enter for newline</small>
                    <button type="submit" class="btn btn-primary send-button">
                        <span class="me-2" aria-hidden="true">➤</span>
                        Send
                    </button>
                </div>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/js/app.js" type="module"></script>
</body>
</html>
