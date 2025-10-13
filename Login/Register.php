<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if(!$data || !isset($data['username']) || !isset($data['password']) || !isset($data['identity'])) {
    echo json_encode(['error'=>'Invalid input']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];
$identity = $data['identity'];

// Read JSON file
$users = json_decode(file_get_contents('Login.json'), true);

// Check if username exists
foreach($users as $u) {
    if($u['username'] === $username){
        echo json_encode(['error'=>'Username already exists']);
        exit;
    }
}

// Add new user
$newUser = [
    'id' => count($users)+1,
    'username' => $username,
    'password' => $password,
    'identity' => $identity
];
$users[] = $newUser;

// Save back to JSON
file_put_contents('Login.json', json_encode($users, JSON_PRETTY_PRINT));

echo json_encode($newUser);
?>
