<?php
// check_login.php
session_start();
header('Content-Type: application/json');

$response = [
    'logged_in' => false,
    'username' => '',
    'user_type' => '',
    'user_id' => null
];

if (isset($_SESSION['user_id'])) {
    $response['logged_in'] = true;
    $response['username'] = $_SESSION['username'];
    $response['user_type'] = $_SESSION['user_type'];
    $response['user_id'] = $_SESSION['user_id'];
}

echo json_encode($response);
?>