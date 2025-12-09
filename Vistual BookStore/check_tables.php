<?php
// check_tables.php
require_once '../include/database.php';

header('Content-Type: text/plain');

echo "=== CHECKING DATABASE TABLES ===\n\n";

// 检查books表结构
echo "1. Books table structure:\n";
$result = $conn->query("DESCRIBE books");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['Field']} - {$row['Type']}\n";
    }
} else {
    echo "   Error: " . $conn->error . "\n";
}

echo "\n2. Sample books data:\n";
$result = $conn->query("SELECT book_id, title, price, pre_order_price, pre_order_available, stock_quantity, expected_date FROM books LIMIT 3");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "   Book ID: {$row['book_id']}\n";
        echo "   Title: {$row['title']}\n";
        echo "   Price: {$row['price']}\n";
        echo "   Pre-order Price: " . (isset($row['pre_order_price']) ? $row['pre_order_price'] : 'NULL') . "\n";
        echo "   Pre-order Available: " . (isset($row['pre_order_available']) ? $row['pre_order_available'] : '0') . "\n";
        echo "   Stock: {$row['stock_quantity']}\n";
        echo "   Expected Date: " . (isset($row['expected_date']) ? $row['expected_date'] : 'NULL') . "\n";
        echo "   ---\n";
    }
}

echo "\n3. Checking pre_orders table:\n";
$result = $conn->query("SHOW TABLES LIKE 'pre_orders'");
if ($result && $result->num_rows > 0) {
    echo "   Table 'pre_orders' exists\n";
    
    // 显示表结构
    $result2 = $conn->query("DESCRIBE pre_orders");
    if ($result2) {
        while ($row = $result2->fetch_assoc()) {
            echo "   {$row['Field']} - {$row['Type']}\n";
        }
    }
} else {
    echo "   Table 'pre_orders' does NOT exist\n";
    
    // 尝试创建表
    echo "\n   Attempting to create pre_orders table...\n";
    $create_sql = "CREATE TABLE pre_orders (
        pre_order_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        book_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        total_amount DECIMAL(10,2) NOT NULL,
        expected_delivery_date DATE,
        pre_order_status VARCHAR(20) DEFAULT 'pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
        FOREIGN KEY (book_id) REFERENCES books(book_id)
    )";
    
    if ($conn->query($create_sql)) {
        echo "   ✓ Table created successfully\n";
    } else {
        echo "   ✗ Failed to create table: " . $conn->error . "\n";
    }
}

$conn->close();
?>