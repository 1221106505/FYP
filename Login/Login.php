<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if(!$data || !isset($data['username']) || !isset($data['password'])){
    echo json_encode(['error'=>'Invalid input']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];

// Read JSON file
$users = json_decode(file_get_contents('Login.json'), true);

foreach($users as $u){
    if($u['username'] === $username){
        if($u['password'] === $password){
            echo json_encode($u);
            exit;
        } else {
            echo json_encode(['error'=>'Wrong password']);
            exit;
        }
    }
}

echo json_encode(['error'=>'User not found']);
?>
