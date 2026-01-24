<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

try {
    // 修正查询：customer表主键是auto_id，orders表使用customer_id关联
    $sql = "SELECT 
                c.auto_id as customer_id,
                c.username,
                c.email,
                c.first_name,
                c.last_name,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as full_name,
                c.phone,
                c.address,
                c.city,
                c.state,
                c.zip_code,
                COUNT(DISTINCT o.order_id) as order_count,
                SUM(CASE WHEN o.status NOT IN ('cancelled') THEN o.total_amount ELSE 0 END) as total_spent,
                MAX(o.order_date) as last_order_date
            FROM customer c
            LEFT JOIN orders o ON c.auto_id = o.customer_id
            WHERE o.order_id IS NOT NULL  -- 只返回有订单的客户
            GROUP BY c.auto_id
            HAVING order_count > 0
            ORDER BY total_spent DESC, full_name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        die(json_encode([
            'success' => false, 
            'error' => $conn->error,
            'sql' => $sql
        ]));
    }
    
    $customers = [];
    
    while ($row = $result->fetch_assoc()) {
        // 确保名称不为空
        if (empty($row['full_name']) || trim($row['full_name']) === '') {
            $row['full_name'] = $row['username'];
        }
        
        // 确保全名不为空
        if (empty($row['full_name']) || trim($row['full_name']) === '') {
            $row['full_name'] = $row['username'];
        }
        
        // 格式化地址
        $address_parts = [];
        if (!empty($row['address'])) $address_parts[] = $row['address'];
        if (!empty($row['city'])) $address_parts[] = $row['city'];
        if (!empty($row['state'])) $address_parts[] = $row['state'];
        if (!empty($row['zip_code'])) $address_parts[] = $row['zip_code'];
        $row['formatted_address'] = implode(', ', $address_parts);
        if (empty($row['formatted_address'])) {
            $row['formatted_address'] = 'No address provided';
        }
        
        // 格式化金额
        $row['total_spent_formatted'] = number_format($row['total_spent'], 2);
        
        // 格式化最后订单日期
        if (!empty($row['last_order_date'])) {
            $row['last_order_date_formatted'] = date('Y-m-d H:i', strtotime($row['last_order_date']));
        }
        
        $customers[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'count' => count($customers),
        'summary' => [
            'total_customers' => count($customers),
            'total_orders' => array_sum(array_column($customers, 'order_count')),
            'total_revenue' => array_sum(array_column($customers, 'total_spent'))
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>