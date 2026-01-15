<?php
// /api/cart/cart_toggle_save.php
require_once __DIR__ . '/_common.php';

$customer_id = get_customer_id();
$body = read_json();

$cart_id = (int)($body['cart_id'] ?? 0);
$saved = (int)($body['saved'] ?? 0);
$saved = ($saved === 1) ? 1 : 0;

if ($cart_id <= 0) {
  json_out(['success' => false, 'error' => 'Invalid cart_id']);
}

$stmt = $conn->prepare("UPDATE cart SET saved = ? WHERE cart_id = ? AND customer_id = ?");
$stmt->bind_param("iii", $saved, $cart_id, $customer_id);
$stmt->execute();
$stmt->close();

json_out(['success' => true]);