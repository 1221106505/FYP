<?php
// /api/cart/cart_checkout.php
require_once __DIR__ . '/_common.php';

// IMPORTANT: never echo warnings/notices (breaks JSON)
ini_set('display_errors', '0');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$customer_id = get_customer_id();
$body = read_json();

$payment_method = trim((string)($body['payment_method'] ?? 'card'));

// Recipient is optional now (fallback)
$recipient_name = trim((string)($body['recipient_name'] ?? ''));
if ($recipient_name === '') $recipient_name = 'Customer';

// Accept address as STRING (no validation, user responsibility)
$address = trim((string)($body['address'] ?? ''));

// Also accept split fields if you ever send them (optional)
$street   = trim((string)($body['street'] ?? ''));
$area     = trim((string)($body['area'] ?? ''));
$city     = trim((string)($body['city'] ?? ''));
$state    = trim((string)($body['state'] ?? ''));
$postcode = trim((string)($body['postcode'] ?? ''));

// Build a full address if split fields exist, else use address textarea
$parts = array_filter([$street, $area, $city, $state, $postcode], fn($x) => trim($x) !== '');
$address_full = count($parts) ? implode(", ", $parts) : $address;

// Payment method required (keep minimal validation)
if ($payment_method === '') json_out(['success' => false, 'error' => 'Payment method required']);

try {
  // 1) Load cart items (not saved)
  $cartQ = $conn->prepare("
    SELECT c.book_id, c.quantity, b.price AS unit_price
    FROM cart c
    JOIN books b ON b.book_id = c.book_id
    WHERE c.customer_id = ? AND IFNULL(c.saved,0)=0
  ");
  $cartQ->bind_param("i", $customer_id);
  $cartQ->execute();
  $res = $cartQ->get_result();

  if ($res->num_rows === 0) {
    json_out(['success' => false, 'error' => 'Cart is empty']);
  }

  // 2) Customer contact
  $custQ = $conn->prepare("SELECT phone, email FROM customer WHERE auto_id = ?");
  $custQ->bind_param("i", $customer_id);
  $custQ->execute();
  $custInfo = $custQ->get_result()->fetch_assoc();
  $custQ->close();

  $phone = $custInfo['phone'] ?? '';
  $email = $custInfo['email'] ?? '';

  // 3) Calculate total + store items
  $items = [];
  $total_amount = 0.0;

  while ($row = $res->fetch_assoc()) {
    $book_id = (int)$row['book_id'];
    $qty     = max(1, (int)$row['quantity']);
    $price   = (float)$row['unit_price'];

    $total_amount += ($qty * $price);
    $items[] = ['book_id' => $book_id, 'qty' => $qty, 'price' => $price];
  }
  $cartQ->close();

  // 4) Transaction
  $conn->begin_transaction();

  // 5) Insert order
  $status = "confirmed";
  $shipping_address = $address_full; // can be empty, allowed
  $billing_address  = $address_full;
  $notes = "";

  $orderStmt = $conn->prepare("
    INSERT INTO orders
      (customer_id, recipient_name, order_date, total_amount, status,
       shipping_address, billing_address, contact_phone, contact_email,
       notes, payment_method, updated_at,
       street, area, city, state, postcode)
    VALUES
      (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW(),
       ?, ?, ?, ?, ?)
  ");

  // 15 params total: i s d + 12 s
  $orderStmt->bind_param(
    "isdssssssssssss",
    $customer_id,
    $recipient_name,
    $total_amount,
    $status,
    $shipping_address,
    $billing_address,
    $phone,
    $email,
    $notes,
    $payment_method,
    $street,
    $area,
    $city,
    $state,
    $postcode
  );

  $orderStmt->execute();
  $order_id = $conn->insert_id;
  $orderStmt->close();

  if (!$order_id) throw new Exception("Order insert failed (no order_id).");

  // 6) Insert order items
  $itemStmt = $conn->prepare("
    INSERT INTO order_items (order_id, book_id, quantity, unit_price)
    VALUES (?, ?, ?, ?)
  ");
  foreach ($items as $it) {
    $bid = (int)$it['book_id'];
    $qty = (int)$it['qty'];
    $pr  = (float)$it['price'];
    $itemStmt->bind_param("iiid", $order_id, $bid, $qty, $pr);
    $itemStmt->execute();
  }
  $itemStmt->close();

  // 7) Clear cart
  $clearStmt = $conn->prepare("
    DELETE FROM cart
    WHERE customer_id = ? AND IFNULL(saved,0)=0
  ");
  $clearStmt->bind_param("i", $customer_id);
  $clearStmt->execute();
  $clearStmt->close();

  $conn->commit();

  json_out(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
  try { $conn->rollback(); } catch (Exception $ex) {}
  json_out(['success' => false, 'error' => $e->getMessage()]);
}
