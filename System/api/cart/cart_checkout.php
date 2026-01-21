<?php
// /api/cart/cart_checkout.php
require_once __DIR__ . '/_common.php';

// Strong error reporting during development
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$customer_id = get_customer_id();
$body = read_json();

$payment_method = trim((string)($body['payment_method'] ?? 'Card'));
$address        = trim((string)($body['address'] ?? ''));

// Validate
if ($address === '' || strlen($address) < 8) {
  json_out(['success' => false, 'error' => 'Invalid address']);
}
if ($payment_method === '') {
  json_out(['success' => false, 'error' => 'Payment method required']);
}

try {
  // 1) Load cart items (NOT saved) + join books to get price
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

  // 2) Get customer phone/email (your customer table uses: phone, email)
  $custQ = $conn->prepare("SELECT phone, email FROM customer WHERE auto_id = ?");
  $custQ->bind_param("i", $customer_id);
  $custQ->execute();
  $custInfo = $custQ->get_result()->fetch_assoc();
  $custQ->close();

  $phone = $custInfo['phone'] ?? '';
  $email = $custInfo['email'] ?? '';

  // 3) Build items + calculate total
  $items = [];
  $total_amount = 0.0;

  while ($row = $res->fetch_assoc()) {
    $book_id = (int)$row['book_id'];
    $qty     = (int)$row['quantity'];
    $price   = (float)$row['unit_price'];

    if ($qty < 1) $qty = 1;

    $total_amount += ($qty * $price);

    $items[] = [
      'book_id' => $book_id,
      'qty'     => $qty,
      'price'   => $price
    ];
  }
  $cartQ->close();

  // 4) Start transaction
  $conn->begin_transaction();

  // 5) Insert into orders (match your table columns)
  // orders columns seen: order_id, customer_id, order_date, total_amount, status,
  // shipping_address, billing_address, contact_phone, contact_email, notes, payment_method, updated_at
  $status = "confirmed";
  $shipping_address = $address;
  $billing_address  = $address;
  $notes = NULL; // optional

  $orderStmt = $conn->prepare("
    INSERT INTO orders
      (customer_id, order_date, total_amount, status,
       shipping_address, billing_address, contact_phone, contact_email,
       notes, payment_method, updated_at)
    VALUES
      (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");

  // 9 placeholders => bind string must have 9 letters:
  // i d s s s s s s s
  $orderStmt->bind_param(
    "idsssssss",
    $customer_id,
    $total_amount,
    $status,
    $shipping_address,
    $billing_address,
    $phone,
    $email,
    $notes,
    $payment_method
  );

  $orderStmt->execute();
  $order_id = $conn->insert_id;
  $orderStmt->close();

  if (!$order_id) {
    throw new Exception("Order insert failed (no order_id).");
  }

  // 6) Insert into order_items
  // Your order_items table likely has: order_item_id, order_id, book_id, quantity, price
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

  // 7) Clear purchased items from cart (ONLY saved=0)
  $clearStmt = $conn->prepare("
    DELETE FROM cart
    WHERE customer_id = ? AND IFNULL(saved,0)=0
  ");
  $clearStmt->bind_param("i", $customer_id);
  $clearStmt->execute();
  $clearStmt->close();

  // 8) Commit
  $conn->commit();

  // 9) Done
  json_out([
    'success'  => true,
    'order_id' => $order_id
  ]);

} catch (Exception $e) {
  // rollback if transaction started
  if ($conn->errno === 0) {
    // nothing
  } else {
    try { $conn->rollback(); } catch (Exception $ex) {}
  }

  json_out([
    'success' => false,
    'error'   => $e->getMessage()
  ]);
}
