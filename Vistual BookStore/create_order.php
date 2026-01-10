<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Disable error output to browser
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log errors
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once '../include/database.php';

// OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Read JSON
$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode(['success' => false, 'error' => 'Empty request body']);
    exit();
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
    error_log('Invalid JSON input: ' . json_last_error_msg() . ' Input: ' . substr($input, 0, 200));
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
$required_fields = ['customer_id', 'cart_ids', 'total_amount', 'payment_method'];
$missing_fields = [];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) $missing_fields[] = $field;
}
if (!empty($missing_fields)) {
    error_log('Missing fields: ' . implode(', ', $missing_fields));
    echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    exit();
}

// Normalize inputs
$customer_id = intval($data['customer_id']);
$total_amount = floatval($data['total_amount']);
$payment_method_raw = strtolower(trim((string)$data['payment_method']));
$payment_method = $conn->real_escape_string($payment_method_raw);

// cart_ids normalize to array
if (!is_array($data['cart_ids'])) {
    $cart_ids = [$data['cart_ids']];
} else {
    $cart_ids = $data['cart_ids'];
}
$cart_ids = array_values(array_filter(array_map('intval', $cart_ids), fn($v) => $v > 0));

// Optional fields
$shipping_address = isset($data['shipping_address']) ? $conn->real_escape_string((string)$data['shipping_address']) : 'Default Address';
$contact_phone    = isset($data['contact_phone']) ? $conn->real_escape_string((string)$data['contact_phone']) : '';
$contact_email    = isset($data['contact_email']) ? $conn->real_escape_string((string)$data['contact_email']) : '';

// Notes handling (backward compatible)
$notes = '';
if (isset($data['notes']) && trim((string)$data['notes']) !== '') {
    $notes .= trim((string)$data['notes']);
}

// Fields from cart.html (your latest payload)
$shipping_name  = isset($data['shipping_name']) ? trim((string)$data['shipping_name']) : '';
$shipping_notes = isset($data['shipping_notes']) ? trim((string)$data['shipping_notes']) : '';

$bank_transfer_reference = isset($data['bank_transfer_reference']) ? trim((string)$data['bank_transfer_reference']) : '';

$paypal_email         = isset($data['paypal_email']) ? trim((string)$data['paypal_email']) : '';
$paypal_approval_code = isset($data['paypal_approval_code']) ? trim((string)$data['paypal_approval_code']) : '';

// Append safe metadata into notes (no passwords)
$metaLines = [];

if ($shipping_name !== '')  $metaLines[] = "Shipping Name: " . $shipping_name;
if ($shipping_notes !== '') $metaLines[] = "Shipping Notes: " . $shipping_notes;

if ($payment_method_raw === 'bank_transfer' && $bank_transfer_reference !== '') {
    $metaLines[] = "Bank Transfer Ref: " . $bank_transfer_reference;
}
if ($payment_method_raw === 'paypal') {
    if ($paypal_email !== '') $metaLines[] = "PayPal Email: " . $paypal_email;
    if ($paypal_approval_code !== '') $metaLines[] = "PayPal Approval Code: " . $paypal_approval_code;
}

if (!empty($metaLines)) {
    if ($notes !== '') $notes .= "\n";
    $notes .= implode("\n", $metaLines);
}
$notes = $conn->real_escape_string($notes);

// Validate cart_ids
if (empty($cart_ids)) {
    echo json_encode(['success' => false, 'error' => 'No cart items selected']);
    exit();
}

// Payment + Order status policy
function getPaymentPolicy(string $method): array {
    // You can rename these statuses to match your DB enum/list if needed.
    // Keep them consistent across your project.
    switch ($method) {
        case 'bank_transfer':
            return [
                'payment_status' => 'pending',
                'order_status'   => 'pending' // keep pending until admin verifies
            ];
        case 'cash_on_delivery':
            return [
                'payment_status' => 'pending',
                'order_status'   => 'confirmed' // order can be confirmed, payment is collected later
            ];
        case 'paypal':
        case 'credit_card':
        case 'debit_card':
        default:
            return [
                'payment_status' => 'completed',
                'order_status'   => 'confirmed'
            ];
    }
}

$policy = getPaymentPolicy($payment_method_raw);
$payment_status = $policy['payment_status'];
$final_order_status = $policy['order_status'];

// Begin transaction
$conn->autocommit(false);

try {
    // 1) Verify cart items (IMPORTANT: check real pre-order using c.is_pre_order)
    $cart_ids_str = implode(',', array_map('intval', $cart_ids));

    $cart_sql = "
        SELECT
            c.cart_id,
            c.book_id,
            c.quantity,
            COALESCE(c.is_pre_order, 0) AS is_pre_order,
            b.price,
            b.title,
            b.stock_quantity,
            b.pre_order_available
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        WHERE c.cart_id IN ($cart_ids_str)
          AND c.customer_id = ?
    ";

    error_log("Cart SQL: $cart_sql with customer_id: $customer_id");

    $cart_stmt = $conn->prepare($cart_sql);
    if (!$cart_stmt) throw new Exception('Failed to prepare cart statement: ' . $conn->error);

    $cart_stmt->bind_param("i", $customer_id);
    if (!$cart_stmt->execute()) throw new Exception('Failed to execute cart query: ' . $cart_stmt->error);

    $cart_result = $cart_stmt->get_result();
    if ($cart_result->num_rows === 0) throw new Exception('No valid cart items found for this customer');

    $order_items = [];
    $total_verification = 0.0;

    while ($item = $cart_result->fetch_assoc()) {
        // Block real pre-order items from normal checkout
        if (intval($item['is_pre_order']) === 1) {
            throw new Exception("Book '{$item['title']}' is a pre-order item and cannot be purchased directly from cart checkout.");
        }

        // Stock check
        $stock_qty = intval($item['stock_quantity']);
        $qty = intval($item['quantity']);
        if ($stock_qty < $qty) {
            throw new Exception("Insufficient stock for '{$item['title']}'. Available: {$stock_qty}, Requested: {$qty}");
        }

        $unit_price = floatval($item['price']);
        $subtotal = $unit_price * $qty;

        $order_items[] = [
            'cart_id' => intval($item['cart_id']),
            'book_id' => intval($item['book_id']),
            'quantity' => $qty,
            'unit_price' => $unit_price,
            'subtotal' => $subtotal,
            'title' => $item['title']
        ];

        $total_verification += $subtotal;
    }
    $cart_stmt->close();

    // Verify totals (log only, do not block)
    $expected_total = $total_verification + 5.00 + ($total_verification * 0.06);
    if (abs($total_amount - $expected_total) > 0.01) {
        error_log("Total mismatch. Received: $total_amount, Expected: $expected_total");
    }

    // 2) Create order (initial status always pending first)
    $order_sql = "
        INSERT INTO orders (
            customer_id,
            total_amount,
            status,
            shipping_address,
            billing_address,
            contact_phone,
            contact_email,
            notes
        ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)
    ";

    $order_stmt = $conn->prepare($order_sql);
    if (!$order_stmt) throw new Exception('Failed to prepare order statement: ' . $conn->error);

    $billing_address = $shipping_address;

    $order_stmt->bind_param(
        "idsssss",
        $customer_id,
        $total_amount,
        $shipping_address,
        $billing_address,
        $contact_phone,
        $contact_email,
        $notes
    );

    if (!$order_stmt->execute()) throw new Exception('Failed to create order: ' . $order_stmt->error);
    $order_id = $order_stmt->insert_id;
    $order_stmt->close();

    error_log("Order created with ID: $order_id");

    // 3) Create order items + update stock
    foreach ($order_items as $item) {
        $item_sql = "INSERT INTO order_items (order_id, book_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)";
        $item_stmt = $conn->prepare($item_sql);
        if (!$item_stmt) throw new Exception('Failed to prepare item statement: ' . $conn->error);

        $item_stmt->bind_param(
            "iiidd",
            $order_id,
            $item['book_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['subtotal']
        );

        if (!$item_stmt->execute()) throw new Exception('Failed to create order item: ' . $item_stmt->error);
        $item_stmt->close();

        $update_sql = "UPDATE books SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE book_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) throw new Exception('Failed to prepare update statement: ' . $conn->error);

        $update_stmt->bind_param("ii", $item['quantity'], $item['book_id']);
        if (!$update_stmt->execute()) throw new Exception('Failed to update stock: ' . $update_stmt->error);
        $update_stmt->close();
    }

    // 4) Remove purchased cart items
    $delete_sql = "DELETE FROM cart WHERE cart_id IN ($cart_ids_str) AND customer_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    if (!$delete_stmt) throw new Exception('Failed to prepare delete statement: ' . $conn->error);

    $delete_stmt->bind_param("i", $customer_id);
    if (!$delete_stmt->execute()) throw new Exception('Failed to clear cart items: ' . $delete_stmt->error);
    $delete_stmt->close();

    // 5) Create payment record with method-based status
    $payment_sql = "
        INSERT INTO payments (
            order_id,
            customer_id,
            payment_method,
            payment_status,
            amount,
            transaction_id,
            payment_date,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
    ";

    $payment_stmt = $conn->prepare($payment_sql);
    if (!$payment_stmt) throw new Exception('Failed to prepare payment statement: ' . $conn->error);

    // transaction_id policy:
    // - bank_transfer: use bank transfer reference if provided; else generate one
    // - COD: COD_<order>_<timestamp>
    // - others: TXN_<order>_<timestamp>_<rand>
    $timestamp = time();
    $random_suffix = bin2hex(random_bytes(3));

    if ($payment_method_raw === 'bank_transfer') {
        if ($bank_transfer_reference === '') {
            $bank_transfer_reference = "BT_" . $order_id . "_" . $timestamp . "_" . $random_suffix;
        }
        $transaction_id = $bank_transfer_reference; // store reference in transaction_id for DB compatibility
    } elseif ($payment_method_raw === 'cash_on_delivery') {
        $transaction_id = "COD_" . $order_id . "_" . $timestamp . "_" . $random_suffix;
    } else {
        $transaction_id = "TXN_" . $order_id . "_" . $timestamp . "_" . $random_suffix;
    }

    // bind: order_id(i), customer_id(i), method(s), status(s), amount(d), transaction_id(s)
    $payment_stmt->bind_param(
        "iissds",
        $order_id,
        $customer_id,
        $payment_method,
        $payment_status,
        $total_amount,
        $transaction_id
    );

    if (!$payment_stmt->execute()) throw new Exception('Failed to create payment record: ' . $payment_stmt->error);
    $payment_id = $payment_stmt->insert_id;
    $payment_stmt->close();

    // 6) Update order status based on payment method policy
    $update_order_sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
    $update_order_stmt = $conn->prepare($update_order_sql);
    if (!$update_order_stmt) throw new Exception('Failed to prepare update order statement: ' . $conn->error);

    $update_order_stmt->bind_param("si", $final_order_status, $order_id);
    if (!$update_order_stmt->execute()) throw new Exception('Failed to update order status: ' . $update_order_stmt->error);
    $update_order_stmt->close();

    // Commit
    $conn->commit();
    error_log("Transaction committed successfully");

    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order_id' => $order_id,
        'payment_id' => $payment_id,
        'transaction_id' => $transaction_id,
        'total_amount' => $total_amount,
        'item_count' => count($order_items),
        'items' => array_column($order_items, 'title'),
        'payment_method' => $payment_method_raw,
        'payment_status' => $payment_status,
        'order_status' => $final_order_status,
        'bank_transfer_reference' => ($payment_method_raw === 'bank_transfer') ? $transaction_id : null
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log('Transaction failed: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Restore autocommit
$conn->autocommit(true);
$conn->close();
?>
