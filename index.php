<?php
session_start();
$loggedIn = isset($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Messenger Video Call</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-body-tertiary">
    <div id="app" data-logged-in="<?php echo $loggedIn ? 'true' : 'false'; ?>">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="#">SignalLink</a>
                <div class="d-flex align-items-center gap-3">
                    <?php if ($loggedIn): ?>
                        <span class="text-white-50 small">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <button id="logout-btn" class="btn btn-outline-light btn-sm">Logout</button>
                    <?php else: ?>
                        <span class="text-white-50 small">Please login or register</span>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <main class="container py-4">
            <div id="incoming-call-banner" class="alert alert-info align-items-center shadow-lg border-0 d-none" role="alert">
                <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                    <div class="me-auto">
                        <strong id="caller-name">Someone</strong> is calling you.
                    </div>
                    <div class="d-flex gap-2">
                        <button id="accept-call-btn" type="button" class="btn btn-success btn-sm">Accept</button>
                        <button id="decline-call-btn" type="button" class="btn btn-outline-danger btn-sm">Decline</button>
                        <button id="dismiss-call-btn" type="button" class="btn-close" aria-label="Dismiss"></button>
                    </div>
                </div>
            </div>

            <section id="auth-section" class="<?php echo $loggedIn ? 'hidden' : ''; ?>">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h2 class="h4 mb-4">Login</h2>
                                <form id="login-form" class="needs-validation" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label" for="login-username">Username</label>
                                        <input type="text" class="form-control" id="login-username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="login-password">Password</label>
                                        <input type="password" class="form-control" id="login-password" name="password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                    <div class="form-message small mt-3 d-none" data-for="login"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h2 class="h4 mb-4">Register</h2>
                                <form id="register-form" class="needs-validation" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label" for="register-username">Username</label>
                                        <input type="text" class="form-control" id="register-username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="register-password">Password</label>
                                        <input type="password" class="form-control" id="register-password" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="register-display-name">Display Name</label>
                                        <input type="text" class="form-control" id="register-display-name" name="displayName" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">Create Account</button>
                                    <div class="form-message small mt-3 d-none" data-for="register"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="messenger" class="<?php echo $loggedIn ? '' : 'hidden'; ?>">
                <div class="row g-4">
                    <div class="col-lg-4 d-flex flex-column gap-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h3 class="h5 mb-3">System State</h3>
                                <pre id="state-json" class="bg-dark text-success-emphasis rounded p-3 small mb-0">Loading...</pre>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h3 class="h5 mb-3">Online Users</h3>
                                <p class="text-muted small">Start a call directly from the list below.</p>
                                <ul id="online-list" class="list-group list-group-flush"></ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8 d-flex flex-column gap-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3 align-items-end">
                                    <div class="flex-grow-1">
                                        <label for="status-input" class="form-label">Status</label>
                                        <input type="text" id="status-input" class="form-control" placeholder="Let others know what you're up to">
                                    </div>
                                    <div>
                                        <button id="update-status-btn" class="btn btn-outline-primary">Update</button>
                                    </div>
                                </div>
                                <div id="status-result" class="small mt-2 text-muted"></div>
                                <hr class="my-4">
                                <form id="password-form" class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label for="new-password" class="form-label">New Password</label>
                                        <input type="password" id="new-password" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="confirm-password" class="form-label">Confirm Password</label>
                                        <input type="password" id="confirm-password" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-secondary w-100">Change Password</button>
                                    </div>
                                    <div class="col-12">
                                        <span id="password-result" class="small"></span>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm" id="call-area">
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="video-tile">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h4 class="h6 text-uppercase text-muted mb-0">You</h4>
                                            </div>
                                            <div class="ratio ratio-4x3 rounded bg-black overflow-hidden">
                                                <video id="local-video" autoplay muted playsinline class="w-100 h-100"></video>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="video-tile">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h4 class="h6 text-uppercase text-muted mb-0">Remote</h4>
                                            </div>
                                            <div class="ratio ratio-4x3 rounded bg-black overflow-hidden">
                                                <video id="remote-video" autoplay playsinline class="w-100 h-100"></video>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
                                    <div class="text-muted small" id="call-helper-text">Select an online user to start a call.</div>
                                    <div class="d-flex gap-2">
                                        <button id="hangup-btn" class="btn btn-danger" disabled>Hang Up</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h4 class="h6 text-uppercase text-muted mb-3">Call &amp; Message Log</h4>
                                <ul id="call-log-list" class="list-group list-group-flush small"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="js/app.js"></script>
</body>
</html>
