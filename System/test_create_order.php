<?php
// check_tables.php
header('Content-Type: application/json');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// 获取所有表名
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo json_encode([
    'database' => $database,
    'tables' => $tables,
    'count' => count($tables)
]);

$conn->close();
?>