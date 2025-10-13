<?php
session_start();

header('Content-Type: application/json');

$response = [
    'logged_in' => false,
    'username' => '',
    'user_type' => ''
];

if (isset($_SESSION['username'])) {
    $response['logged_in'] = true;
    $response['username'] = $_SESSION['username'];
    $response['user_type'] = $_SESSION['user_type'] ?? 'unknown';
}

echo json_encode($response);
?>