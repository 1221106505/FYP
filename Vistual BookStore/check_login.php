<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['user_type'])) {
    echo json_encode([
        'logged_in' => true,
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_type']
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
?>