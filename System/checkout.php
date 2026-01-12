<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BookStore</title>
    <style>
        :root {
            --primary: #6d28d9;
            --primary-dark: #5b21b6;
            --secondary: #f1f5f9;
            --accent: #10b981;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --card-bg: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #e74c3c;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: var(--text);
        }

        .checkout-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--primary);
            font-weight: bold;
            font-size: 1.5rem;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }

        .step.active .step-circle {
            background: var(--primary);
            color: white;
        }

        .step.completed .step-circle {
            background: var(--success);
            color: white;
        }

        .checkout-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .form-section {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .form-section h2 {
            margin-bottom: 20px;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 16px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-method {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method:hover {
            border-color: var(--primary);
        }

        .payment-method.selected {
            border-color: var(--primary);
            background: rgba(109, 40, 217, 0.05);
        }

        .order-summary {
            background: var(--secondary);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }

        .total {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            margin-top: 10px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text);
        }

        .error {
            color: var(--danger);
            background: #fee;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        @media (max-width: 768px) {
            .checkout-form {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <!-- Header -->
        <div class="header">
            <a href="Main.html" class="logo">
                <i class="fas fa-book"></i> BookStore
            </a>
            <div class="user-info">
                Welcome, <span id="username">Guest</span>
            </div>
        </div>

        <!-- Checkout Steps -->
        <div class="steps">
            <div class="step active">
                <div class="step-circle">1</div>
                <div>Cart</div>
            </div>
            <div class="step active">
                <div class="step-circle">2</div>
                <div>Checkout</div>
            </div>
            <div class="step">
                <div class="step-circle">3</div>
                <div>Payment</div>
            </div>
            <div class="step">
                <div class="step-circle">4</div>
                <div>Complete</div>
            </div>
        </div>

        <!-- Error Display -->
        <div class="error" id="errorMessage"></div>

        <!-- Checkout Form -->
        <div class="checkout-form">
            <!-- Shipping & Billing -->
            <div class="form-section">
                <h2>Shipping Information</h2>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="fullName" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" id="phone" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea id="address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="city" required>
                </div>
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" id="postalCode" required>
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <select id="country">
                        <option value="Malaysia">Malaysia</option>
                        <option value="Singapore">Singapore</option>
                        <option value="Indonesia">Indonesia</option>
                    </select>
                </div>
            </div>

            <!-- Payment & Order Summary -->
            <div class="form-section">
                <h2>Payment Method</h2>
                <div class="payment-methods">
                    <div class="payment-method" data-method="credit_card">
                        <i class="fas fa-credit-card fa-2x"></i>
                        <div>Credit Card</div>
                    </div>
                    <div class="payment-method" data-method="paypal">
                        <i class="fab fa-paypal fa-2x"></i>
                        <div>PayPal</div>
                    </div>
                    <div class="payment-method" data-method="bank_transfer">
                        <i class="fas fa-university fa-2x"></i>
                        <div>Bank Transfer</div>
                    </div>
                </div>

                <h2>Order Summary</h2>
                <div class="order-summary" id="orderSummary">
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading order...</p>
                    </div>
                </div>

                <button class="btn btn-primary" onclick="processPayment()">
                    <i class="fas fa-lock"></i> Complete Payment
                </button>
                <button class="btn btn-secondary" onclick="window.history.back()" style="margin-top: 10px;">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 400px; max-width: 90%;">
            <h2>Payment Processing</h2>
            <div id="paymentDetails"></div>
            <div style="margin-top: 20px; text-align: center;">
                <button class="btn btn-primary" onclick="confirmPayment()">
                    <i class="fas fa-check"></i> Confirm Payment
                </button>
                <button class="btn btn-secondary" onclick="cancelPayment()" style="margin-top: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // API endpoints
        const CHECK_LOGIN_API = 'check_login.php';
        const GET_CART_API = 'get_cart.php';
        const CREATE_ORDER_API = 'create_order.php';
        const GET_PAYMENT_API = 'get_payment.php';
        const PROCESS_PAYMENT_API = 'process_payment.php';

        // Global variables
        let currentUser = null;
        let cartData = null;
        let selectedPaymentMethod = 'credit_card';
        let currentOrderId = null;

        // Initialize checkout
        document.addEventListener('DOMContentLoaded', function() {
            checkLogin();
            loadCart();
            setupPaymentMethods();
        });

        // Check login status
        async function checkLogin() {
            try {
                const response = await fetch(CHECK_LOGIN_API, { credentials: 'include' });
                const data = await response.json();
                
                if (data.logged_in) {
                    currentUser = data;
                    document.getElementById('username').textContent = data.username;
                    populateUserInfo(data);
                } else {
                    window.location.href = '../Login/Login.html?redirect=checkout';
                }
            } catch (error) {
                showError('Error checking login status');
            }
        }

        // Populate user information
        function populateUserInfo(user) {
            if (user.first_name && user.last_name) {
                document.getElementById('fullName').value = `${user.first_name} ${user.last_name}`;
            }
            if (user.email) {
                document.getElementById('email').value = user.email;
            }
            if (user.phone) {
                document.getElementById('phone').value = user.phone;
            }
            if (user.address) {
                document.getElementById('address').value = user.address;
            }
            if (user.city) {
                document.getElementById('city').value = user.city;
            }
            if (user.zip_code) {
                document.getElementById('postalCode').value = user.zip_code;
            }
            if (user.country) {
                document.getElementById('country').value = user.country;
            }
        }

        // Load cart data
        async function loadCart() {
            try {
                const response = await fetch(GET_CART_API, { credentials: 'include' });
                const data = await response.json();
                
                if (data.success && data.cart_items && data.cart_items.length > 0) {
                    cartData = data;
                    displayOrderSummary();
                } else {
                    showError('Your cart is empty');
                    setTimeout(() => window.location.href = 'cart.html', 2000);
                }
            } catch (error) {
                showError('Error loading cart');
            }
        }

        // Display order summary
        function displayOrderSummary() {
            if (!cartData) return;

            let html = '';
            let subtotal = 0;

            cartData.cart_items.forEach(item => {
                if (item.is_pre_order != 1) { // 排除预购商品
                    const itemTotal = item.price * item.quantity;
                    subtotal += itemTotal;
                    
                    html += `
                        <div class="summary-item">
                            <div>${item.title} × ${item.quantity}</div>
                            <div>RM ${itemTotal.toFixed(2)}</div>
                        </div>
                    `;
                }
            });

            const shipping = 5.00;
            const tax = subtotal * 0.06;
            const total = subtotal + shipping + tax;

            html += `
                <hr style="margin: 15px 0;">
                <div class="summary-item">
                    <div>Subtotal</div>
                    <div>RM ${subtotal.toFixed(2)}</div>
                </div>
                <div class="summary-item">
                    <div>Shipping</div>
                    <div>RM ${shipping.toFixed(2)}</div>
                </div>
                <div class="summary-item">
                    <div>Tax (6%)</div>
                    <div>RM ${tax.toFixed(2)}</div>
                </div>
                <div class="summary-item total">
                    <div>Total</div>
                    <div>RM ${total.toFixed(2)}</div>
                </div>
            `;

            document.getElementById('orderSummary').innerHTML = html;
        }

        // Setup payment methods
        function setupPaymentMethods() {
            const methods = document.querySelectorAll('.payment-method');
            methods.forEach(method => {
                method.addEventListener('click', function() {
                    methods.forEach(m => m.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedPaymentMethod = this.dataset.method;
                });
            });
        }

        // Process payment
        async function processPayment() {
            // 验证表单
            if (!validateForm()) {
                return;
            }

            try {
                // 1. 创建订单
                const orderResponse = await fetch(CREATE_ORDER_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        shipping_address: getShippingAddress(),
                        billing_address: getShippingAddress(), // 使用相同地址
                        contact_phone: document.getElementById('phone').value,
                        contact_email: document.getElementById('email').value,
                        notes: 'Online order checkout'
                    })
                });

                const orderData = await orderResponse.json();
                
                if (!orderData.success) {
                    showError(orderData.error || 'Failed to create order');
                    return;
                }

                currentOrderId = orderData.order_id;
                
                // 2. 显示支付模态框
                showPaymentModal(orderData);

            } catch (error) {
                console.error('Error processing payment:', error);
                showError('Error processing payment');
            }
        }

        // Show payment modal
        function showPaymentModal(orderData) {
            const modal = document.getElementById('paymentModal');
            const details = document.getElementById('paymentDetails');
            
            const orderTotal = orderData.total_amount || 0;
            
            details.innerHTML = `
                <p><strong>Order ID:</strong> ${orderData.order_id}</p>
                <p><strong>Payment Method:</strong> ${selectedPaymentMethod.replace('_', ' ').toUpperCase()}</p>
                <p><strong>Amount:</strong> RM ${orderTotal.toFixed(2)}</p>
                <p><strong>Transaction will be recorded as virtual payment.</strong></p>
            `;
            
            modal.style.display = 'flex';
        }

        // Confirm payment
        async function confirmPayment() {
            if (!currentOrderId || !currentUser) {
                showError('Missing order or user information');
                return;
            }

            try {
                // 创建支付记录
                const paymentResponse = await fetch(GET_PAYMENT_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: currentOrderId,
                        customer_id: currentUser.user_id || currentUser.auto_id,
                        payment_method: selectedPaymentMethod,
                        amount: calculateTotal(),
                        notes: 'Payment via checkout'
                    })
                });

                const paymentData = await paymentResponse.json();
                
                if (paymentData.success) {
                    // 更新支付状态为完成
                    const processResponse = await fetch(PROCESS_PAYMENT_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            payment_id: paymentData.payment_id,
                            payment_status: 'completed',
                            transaction_id: paymentData.transaction_id
                        })
                    });

                    const processData = await processResponse.json();
                    
                    if (processData.success) {
                        // 支付成功，重定向到确认页面
                        window.location.href = `order_confirmation.html?order_id=${currentOrderId}&payment_id=${paymentData.payment_id}`;
                    } else {
                        showError(processData.error || 'Payment processing failed');
                    }
                } else {
                    showError(paymentData.error || 'Failed to create payment');
                }
            } catch (error) {
                console.error('Error confirming payment:', error);
                showError('Error confirming payment');
            }
        }

        // Cancel payment
        function cancelPayment() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        // Validate form
        function validateForm() {
            const required = ['fullName', 'email', 'phone', 'address', 'city', 'postalCode'];
            
            for (let field of required) {
                const element = document.getElementById(field);
                if (!element.value.trim()) {
                    showError(`Please fill in ${field.replace(/([A-Z])/g, ' $1').toLowerCase()}`);
                    element.focus();
                    return false;
                }
            }
            
            if (!cartData || !cartData.cart_items || cartData.cart_items.length === 0) {
                showError('Cart is empty');
                return false;
            }
            
            return true;
        }

        // Get shipping address
        function getShippingAddress() {
            return `
                ${document.getElementById('fullName').value}
                ${document.getElementById('address').value}
                ${document.getElementById('city').value}
                ${document.getElementById('postalCode').value}
                ${document.getElementById('country').value}
            `.trim();
        }

        // Calculate total
        function calculateTotal() {
            if (!cartData) return 0;
            
            let subtotal = 0;
            cartData.cart_items.forEach(item => {
                if (item.is_pre_order != 1) {
                    subtotal += item.price * item.quantity;
                }
            });
            
            const shipping = 5.00;
            const tax = subtotal * 0.06;
            return subtotal + shipping + tax;
        }

        // Show error
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>