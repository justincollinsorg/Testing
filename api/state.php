<?php
require_once __DIR__ . '/utils.php';
ensure_logged_in();

$currentUser = $_SESSION['username'];

respond_json(build_state_snapshot($currentUser));
