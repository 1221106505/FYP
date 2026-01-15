<?php
// /api/cart/cart_update.php
require_once __DIR__ . '/_common.php';

$customer_id = get_customer_id();
$body = read_json();

$cart_id = (int)($body['cart_id'] ?? 0);
$quantity = (int)($body['quantity'] ?? 0);

if ($cart_id <= 0 || $quantity < 1) {
  json_out(['success' => false, 'error' => 'Invalid cart_id or quantity']);
}

$stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
$stmt->bind_param("iii", $quantity, $cart_id, $customer_id);
$stmt->execute();

if ($stmt->affected_rows < 0) {
  $stmt->close();
  json_out(['success' => false, 'error' => 'Failed to update']);
}

$stmt->close();
json_out(['success' => true]);