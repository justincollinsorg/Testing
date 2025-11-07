<?php
session_start();
$loggedIn = isset($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Messenger Video Call</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div id="app" data-logged-in="<?php echo $loggedIn ? 'true' : 'false'; ?>">
        <header>
            <h1>Messenger &amp; Video Call</h1>
            <div id="user-info">
                <?php if ($loggedIn): ?>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <button id="logout-btn">Logout</button>
                <?php else: ?>
                    <span>Please login or register</span>
                <?php endif; ?>
            </div>
        </header>
        <main>
            <section id="auth-section" class="panel <?php echo $loggedIn ? 'hidden' : ''; ?>">
                <div class="form-container">
                    <h2>Login</h2>
                    <form id="login-form">
                        <label>Username
                            <input type="text" name="username" required>
                        </label>
                        <label>Password
                            <input type="password" name="password" required>
                        </label>
                        <button type="submit">Login</button>
                        <div class="form-message" data-for="login"></div>
                    </form>
                </div>
                <div class="form-container">
                    <h2>Register</h2>
                    <form id="register-form">
                        <label>Username
                            <input type="text" name="username" required>
                        </label>
                        <label>Password
                            <input type="password" name="password" required>
                        </label>
                        <label>Display Name
                            <input type="text" name="displayName" required>
                        </label>
                        <button type="submit">Register</button>
                        <div class="form-message" data-for="register"></div>
                    </form>
                </div>
            </section>
            <section id="messenger" class="panel <?php echo $loggedIn ? '' : 'hidden'; ?>">
                <aside id="sidebar">
                    <div class="state-summary">
                        <h3>System State</h3>
                        <pre id="state-json">Loading...</pre>
                    </div>
                    <div class="online-users">
                        <h3>Online Users</h3>
                        <ul id="online-list"></ul>
                    </div>
                </aside>
                <section id="chat-area">
                    <div id="status-bar">
                        <label>Status
                            <input type="text" id="status-input" placeholder="Set your status">
                        </label>
                        <button id="update-status-btn">Update</button>
                        <span id="status-result"></span>
                    </div>
                    <div id="password-bar">
                        <form id="password-form">
                            <label>New Password
                                <input type="password" id="new-password" required>
                            </label>
                            <label>Confirm Password
                                <input type="password" id="confirm-password" required>
                            </label>
                            <button type="submit">Change Password</button>
                            <span id="password-result"></span>
                        </form>
                    </div>
                    <div id="call-area">
                        <div class="videos">
                            <div>
                                <h4>You</h4>
                                <video id="local-video" autoplay muted playsinline></video>
                            </div>
                            <div>
                                <h4>Remote</h4>
                                <video id="remote-video" autoplay playsinline></video>
                            </div>
                        </div>
                        <div class="call-controls">
                            <button id="start-call-btn" disabled>Start Call</button>
                            <button id="hangup-btn" disabled>Hang Up</button>
                        </div>
                        <div class="modal fade" id="call-notification-modal" tabindex="-1" aria-labelledby="incomingCallLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="incomingCallLabel">Incoming Call</h5>
                                        <button id="dismiss-call-btn" type="button" class="btn-close" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-0"><strong id="caller-name"></strong> is calling...</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button id="decline-call-btn" type="button" class="btn btn-outline-danger">Decline</button>
                                        <button id="accept-call-btn" type="button" class="btn btn-success">Accept</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="call-log">
                            <h4>Call &amp; Message Log</h4>
                            <ul id="call-log-list"></ul>
                        </div>
                    </div>
                </section>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="js/app.js"></script>
</body>
</html>
