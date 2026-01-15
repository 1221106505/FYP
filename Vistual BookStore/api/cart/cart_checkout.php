<?php
// /api/cart/cart_checkout.php
require_once __DIR__ . '/_common.php';

$customer_id = get_customer_id();
$body = read_json();

$payment_method = trim((string)($body['payment_method'] ?? ''));
$address = trim((string)($body['address'] ?? ''));

if ($address === '' || strlen($address) < 8) {
  json_out(['success' => false, 'error' => 'Invalid address']);
}

if ($payment_method === '') {
  json_out(['success' => false, 'error' => 'Payment method required']);
}

// TODO: Insert into orders + order_items + payments (depends on your schema)
// For now: just return a fake order id and clear cart
$stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND IFNULL(saved,0)=0");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->close();

json_out([
  'success' => true,
  'order_id' => rand(10000, 99999)
]);