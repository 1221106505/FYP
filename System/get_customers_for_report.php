<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");

try {
    // 获取有订单的客户
    $sql = "SELECT 
                c.auto_id as customer_id,
                c.username,
                c.email,
                CONCAT(c.first_name, ' ', c.last_name) as full_name
            FROM customer c
            WHERE EXISTS (
                SELECT 1 FROM orders o 
                WHERE o.customer_id = c.auto_id 
                AND o.status NOT IN ('cancelled')
            )
            ORDER BY c.username ASC";
    
    $result = $conn->query($sql);
    $customers = [];
    
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'count' => count($customers)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>