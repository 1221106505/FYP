<?php
// /api/cart/cart_clear.php
require_once __DIR__ . '/_common.php';

$customer_id = get_customer_id();

// Clear only active cart (not saved)
$stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND IFNULL(saved,0)=0");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->close();

json_out(['success' => true]);