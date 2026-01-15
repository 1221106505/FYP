<?php
// /api/cart/cart_remove.php
require_once __DIR__ . '/_common.php';

$customer_id = get_customer_id();
$body = read_json();

$ids = $body['cart_ids'] ?? [];
if (!is_array($ids) || count($ids) === 0) {
  json_out(['success' => false, 'error' => 'No cart_ids provided']);
}

$ids = array_values(array_filter(array_map('intval', $ids), fn($x) => $x > 0));
if (count($ids) === 0) {
  json_out(['success' => false, 'error' => 'Invalid cart_ids']);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids) + 1); // +1 for customer_id

$sql = "DELETE FROM cart WHERE customer_id = ? AND cart_id IN ($placeholders)";
$stmt = $conn->prepare($sql);

// bind params dynamically
$params = array_merge([$customer_id], $ids);
$stmt->bind_param($types, ...$params);

$stmt->execute();
$stmt->close();

json_out(['success' => true]);