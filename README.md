# Testing

This repository now contains a lightweight PHP + JavaScript messenger that supports:

* User registration, login, and logout with password history stored on disk.
* Online presence tracking and status messaging persisted in JSON text files.
* WebRTC-powered video calls between any two online users with polling-based signaling.
* A dashboard showing full application state, including password history and session metadata.

## Getting Started

1. Launch a local PHP server from the project root:

   ```bash
   php -S localhost:8000
   ```

2. Open `http://localhost:8000/` in two separate browser windows to register users and place calls between them.

All state is stored in the `data/` directory as plain JSON text files so you can inspect or reset the application easily.
