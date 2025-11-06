<?php
require_once __DIR__ . '/utils.php';
if (!isset($_SESSION['username'])) {
    respond_json(['user' => null]);
}

$username = $_SESSION['username'];
$userPath = get_user_path($username);
if (!file_exists($userPath)) {
    respond_json(['user' => null]);
}

respond_json(['user' => read_json($userPath)]);
