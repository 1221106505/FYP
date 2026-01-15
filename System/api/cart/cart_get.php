<?php
// /api/cart/cart_get.php
require_once __DIR__ . '/_common.php';

$customer_id = get_customer_id();

// NOTE: Adjust these column names if your books table is different.
// Common: books(book_id, title, price)
$sql = "
  SELECT c.cart_id, c.book_id, c.quantity,
         b.title, b.price
  FROM cart c
  JOIN books b ON b.book_id = c.book_id
  WHERE c.customer_id = ?
    AND IFNULL(c.saved, 0) = 0
  ORDER BY c.cart_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$res = $stmt->get_result();
$cart = [];
while ($row = $res->fetch_assoc()) {
  $cart[] = $row;
}
$stmt->close();

$sql2 = "
  SELECT c.cart_id, c.book_id, c.quantity,
         b.title, b.price
  FROM cart c
  JOIN books b ON b.book_id = c.book_id
  WHERE c.customer_id = ?
    AND IFNULL(c.saved, 0) = 1
  ORDER BY c.cart_id DESC
";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $customer_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$saved = [];
while ($row = $res2->fetch_assoc()) {
  $saved[] = $row;
}
$stmt2->close();

json_out([
  'success' => true,
  'cart' => $cart,
  'saved' => $saved
]);