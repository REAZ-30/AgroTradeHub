<?php
session_start();

// Redirect if user is not logged in as customer
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'customer') {
    header('Location: login.php');
    exit();
}

require_once 'database.php';

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

// Get user details (safe fallback if DB fails)
$db = new Database();
$conn = $db->getConnection();

$user = [
    'full_name' => $_SESSION['full_name'] ?? 'Customer',
    'email'     => $_SESSION['email'] ?? '',
    'phone'     => '',
    'address'   => ''
];

if ($conn) {
    $user_query = "SELECT full_name, email, phone, address FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->execute([$_SESSION['user_id']]);
    $db_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($db_user) {
        $user = array_merge($user, $db_user);
    }
}

// Calculate totals
$subtotal = 0;
$total_items = 0;

foreach ($_SESSION['cart'] as $item) {
    $subtotal += ((float)$item['price']) * ((int)$item['quantity']);
    $total_items += (int)$item['quantity'];
}

$tax = $subtotal * 0.1;
$shipping = ($subtotal > 50) ? 0 : 5.00;
$total = $subtotal + $tax + $shipping;

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (empty($_POST['contact_phone'])) $errors[] = 'Contact phone number is required';
    if (empty($_POST['address'])) $errors[] = 'Delivery address is required';
    if (empty($errors)) {
        $transactionStarted = false;

        try {
            if (!$conn) {
                throw new Exception("Database connection failed.");
            }

            $conn->beginTransaction();
            $transactionStarted = true;

            $order_number = 'ORD-' . date('Ymd-His') . '-' . rand(1000, 9999);

            $query = "INSERT INTO orders (
                        customer_id, total_amount, shipping_address, status,
                        payment_method, payment_transaction_id, payment_mobile, customer_phone, order_number
                      ) VALUES (?, ?, ?, 'pending', 'bkash', ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);

            $shipping_address   = trim($_POST['address']);
            $contact_phone      = trim($_POST['contact_phone']);
            $bkash_mobile       = trim($_POST['bkash_mobile']);
            $bkash_transaction  = trim($_POST['bkash_transaction']);

            $stmt->execute([
                $_SESSION['user_id'],
                $total,
                $shipping_address,
                $bkash_transaction,
                $bkash_mobile,
                $contact_phone,
                $order_number
            ]);

            $order_id = $conn->lastInsertId();

            // Create order items
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $product_query = "SELECT seller_id FROM products WHERE id = ?";
                $product_stmt = $conn->prepare($product_query);
                $product_stmt->execute([$product_id]);
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("Product with ID $product_id not found.");
                }
                if (empty($product['seller_id'])) {
                    throw new Exception("Product '{$item['name']}' has no seller assigned.");
                }

                $item_query = "INSERT INTO order_items (order_id, product_id, seller_id, quantity, price)
                               VALUES (?, ?, ?, ?, ?)";
                $item_stmt = $conn->prepare($item_query);
                $success = $item_stmt->execute([
                    $order_id,
                    $product_id,
                    $product['seller_id'],
                    (int)$item['quantity'],
                    (float)$item['price']
                ]);

                if (!$success) {
                    throw new Exception("Failed to add {$item['name']} to order.");
                }
            }

            // Commit order creation first
            $conn->commit();
            $transactionStarted = false;

            if (!empty($payment_result['success'])) {
                $conn->beginTransaction();
                $transactionStarted = true;

                $stmt = $conn->prepare($update_payment);
                $stmt->execute([$order_id]);

                $conn->commit();
                $transactionStarted = false;

                // Clear cart after successful checkout
                $_SESSION['cart'] = [];
                $_SESSION['order_success'] = [
                    'order_number'    => $order_number,
                    'total'           => $total,
                    'transaction_id'  => $bkash_transaction,
                    'contact_phone'   => $contact_phone
                ];

                header('Location: checkout.php?success=1');
                exit();
            } else {
                $conn->beginTransaction();
                $transactionStarted = true;

                $update_payment = "UPDATE orders SET payment_status = 'failed' WHERE id = ?";
                $stmt = $conn->prepare($update_payment);
                $stmt->execute([$order_id]);

                $conn->commit();
                $transactionStarted = false;

                $msg = $payment_result['message'] ?? 'Unknown error';
                throw new Exception('Payment verification failed: ' . $msg);
            }

        } catch (Exception $e) {
            if ($transactionStarted && $conn) {
                try { $conn->rollBack(); } catch (Exception $ignore) {}
            }
            $_SESSION['error_message'] = 'Order failed: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Success page state
$success = isset($_GET['success']);

// Cart badge count
$cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cart_count += (int)$it['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checkout - AgroTradeHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            font-family: 'Poppins', sans-serif;
            background-color:#f8f9fa;
            margin:0;
        }

        /* ======= CUSTOM NAVBAR (customer style) ======= */
        .navbar-custom{
            background:#2DC653;
            padding:15px 40px;
            display:flex;
            align-items:center;
            color:#000;
            justify-content:space-between;
            width:100%;
            box-sizing:border-box;
            position:sticky;
            top:0;
            z-index:1000;
        }
        .logo{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:28px;
            font-weight:600;
            margin-right:45px;
            white-space:nowrap;
        }
        .logo-icon{ width:40px; height:40px; object-fit:contain; }

        .nav-links{
            display:flex;
            align-items:center;
            gap:25px;
            flex-wrap:wrap;
        }
        .nav-links a{
            text-decoration:none;
            color:#000;
            font-size:16px;
            font-weight:500;
            transition:color .3s;
            white-space:nowrap;
            position:relative;
        }
        .nav-links a:hover{ color:#fff; }
        .nav-links a.active{ color:#fff; font-weight:600; }

        .auth-buttons{
            display:flex;
            gap:15px;
            align-items:center;
            flex-shrink:0;
        }

        /* Dropdown */
        .dropdown{ position:relative; display:inline-block; }
        .dropbtn{
            padding:8px 16px;
            border:none;
            cursor:pointer;
            border-radius:10px;
            background:#fff;
            color:#000;
            font-weight:500;
            transition:all .3s;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:5px;
            white-space:nowrap;
            min-width:max-content;
        }
        .dropbtn:hover{
            background:#f0f0f0;
            transform:translateY(-2px);
        }
        .dropdown-menu-custom{
            display:none;
            position:absolute;
            background:#fff;
            min-width:180px;
            border-radius:10px;
            padding:10px;
            box-shadow:0 5px 15px rgba(0,0,0,0.1);
            z-index:9999;   /* keep dropdown always above sticky cards */
            border:none;
            right:0;
            top:100%;
        }
        .dropdown-item-custom{
            padding:10px 15px;
            border-radius:5px;
            margin:2px 0;
            width:100%;
            display:block;
            text-decoration:none;
            color:#000;
            background:#E0FFE8;
            transition:all .3s;
            text-align:center;
            border:none;
            font-size:14px;
            box-sizing:border-box;
        }
        .dropdown-item-custom:hover{
            background-color:#2DC653;
            color:#fff;
            transform:translateX(5px);
        }
        .dropdown:hover .dropdown-menu-custom{ display:block; }

        .user-welcome{
            color:#000;
            font-weight:500;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            max-width:160px;
        }

        .cart-icon{ position:relative; margin-left:15px; white-space:nowrap; }
        .cart-count{
            position:absolute;
            top:-8px;
            right:-8px;
            background:#ff4444;
            color:#fff;
            border-radius:50%;
            width:20px;
            height:20px;
            font-size:12px;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        /* ======= PAGE STYLES ======= */
        .bkash-payment{
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            color:white;
            border-radius:10px;
            padding:20px;
            margin-top:20px;
        }
        .section-title{
            color:#28a745;
            border-bottom:2px solid #28a745;
            padding-bottom:10px;
            margin-bottom:20px;
        }
        .form-label.required:after{
            content:" *";
            color:#dc3545;
        }

        /* Sticky Order Summary - fixed overlap */
        .order-summary-sticky{
            position:sticky;
            top:140px;  /* space below navbar */
            z-index:2;  /* below dropdown (9999), above content */
        }
        @media (max-width: 992px){
            .order-summary-sticky{ position:static; top:auto; }
        }

        /* FOOTER full-width background */
        footer.footer-custom{
            background:#212529;
            color:#fff;
            margin-top:60px;
            width:100%;
        }
        footer.footer-custom .footer-inner{
            padding:40px 0;
        }
        footer.footer-custom h5{
            margin-bottom:10px;
            font-size:18px;
            font-weight:600;
        }
        footer.footer-custom p{
            font-size:14px;
            color:#fff;
            line-height:1.6;
            margin-bottom:8px;
        }

        /* Responsive navbar */
        @media (max-width:768px){
            .navbar-custom{
                flex-direction:column;
                padding:15px;
                gap:15px;
            }
            .nav-links{
                margin:10px 0;
                flex-direction:row;
                gap:15px;
                justify-content:center;
                flex-wrap:wrap;
            }
            .auth-buttons{
                margin-top:10px;
                justify-content:center;
            }
            .logo{
                margin-right:0;
                justify-content:center;
                width:100%;
            }
            .dropdown-menu-custom{
                right:auto;
                left:50%;
                transform:translateX(-50%);
            }
        }
    </style>
</head>
<body>

<!-- CUSTOM NAVBAR -->
<div class="navbar-custom">
    <div class="logo">
        <img src="images/cart.png" alt="AgroTradeHub Logo" class="logo-icon">
        AgroTradeHub
    </div>

    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="products.php">Products</a>
        <a href="cart.php" class="cart-icon">
            Cart
            <?php if($cart_count > 0): ?>
                <span class="cart-count"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="orders.php">My Orders</a>
        <!-- <a href="checkout.php" class="active">Checkout</a> -->
    </div>

    <div class="auth-buttons">
        <div class="dropdown">
            <button class="dropbtn">
                <span class="user-welcome"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Customer'); ?></span>
            </button>
            <div class="dropdown-menu-custom">
                <a href="profile.php" class="dropdown-item-custom">My Profile</a>
                <a href="logout.php" class="dropdown-item-custom">Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">

    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if($success && isset($_SESSION['order_success'])): ?>
        <!-- Order Success -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card text-center">
                    <div class="card-body py-5">
                        <div class="text-success mb-4">
                            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h2 class="text-success">Order Confirmed!</h2>
                        <p class="lead">Thank you for your purchase!</p>

                        <div class="card bg-light mt-4">
                            <div class="card-body">
                                <h5>Order Number: <?php echo $_SESSION['order_success']['order_number']; ?></h5>
                                <p>Contact Phone: <strong><?php echo $_SESSION['order_success']['contact_phone']; ?></strong></p>
                                <p>Transaction ID: <strong><?php echo $_SESSION['order_success']['transaction_id']; ?></strong></p>
                                <p>Total Amount: <strong>$<?php echo number_format($_SESSION['order_success']['total'], 2); ?></strong></p>
                                <p class="text-success">Payment Status: <strong>Verified</strong></p>
                                <p>Your order is now being processed by the seller.</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="products.php" class="btn btn-success">Continue Shopping</a>
                            <a href="orders.php" class="btn btn-outline-success">View My Orders</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['order_success']); ?>
    <?php else: ?>

        <div class="row">
            <!-- Checkout Form -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="section-title">Checkout Details</h4>

                        <form method="POST" action="payment.php?action=pay">
                            <!-- Customer Info (Read-only) -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                </div>
                            </div>

                            <!-- Contact & Shipping Information -->
                            <div class="mb-4">
                                <h5 class="section-title">Contact & Shipping Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Contact Phone Number</label>
                                        <input type="text" class="form-control" name="contact_phone"
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               placeholder="01XXXXXXXXX" required>
                                        <small class="text-muted">For delivery updates and contact</small>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label required">Delivery Address</label>
                                        <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                        <small class="text-muted">Enter the complete delivery address with house/road number</small>
                                    </div>
                                </div>
                            </div>

                            <!-- bKash Payment -->
                           <div class="bkash-payment">
    <h5 class="mb-3">Secure Online Payment (SSLCommerz)</h5>

    <p class="mb-3">
        You will be redirected to a secure payment gateway to complete your payment.
    </p>

    <!-- REQUIRED FOR payment.php -->
    <input type="hidden" name="customer_id" value="<?php echo $_SESSION['user_id']; ?>">
    <input type="hidden" name="total_amount" value="<?php echo number_format($total,2,'.',''); ?>">

    <!-- Dummy values to keep backend validation safe -->
    <input type="hidden" name="bkash_mobile" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
    <input type="hidden" name="bkash_transaction" value="SSL-PAYMENT">

    <div class="alert alert-light mt-2">
        <small>
            Do not refresh the page during payment.<br>
            You will be redirected back automatically.
        </small>
    </div>
</div>


                            <div class="mt-4">
                                <button type="submit" class="btn btn-success btn-lg w-100 py-3">
                                    Complete Order & Verify Payment
                                </button>
                                <p class="text-muted small mt-2 text-center">
                                    By completing this order, you agree to our terms and conditions.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card order-summary-sticky">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($_SESSION['cart'] as $product_id => $item): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div class="d-flex align-items-center">
                                    <div style="width:40px;height:40px;background-color:#f8f9fa;border-radius:5px;margin-right:10px;overflow:hidden;">
                                        <?php if(!empty($item['image'])): ?>
                                            <img
                                                src="<?php echo htmlspecialchars($item['image']); ?>"
                                                alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                style="width:100%;height:100%;object-fit:cover;"
                                            >
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <small class="d-block"><?php echo htmlspecialchars($item['name']); ?></small>
                                        <small class="text-muted">Qty: <?php echo (int)$item['quantity']; ?></small>
                                    </div>
                                </div>
                                <small class="fw-bold">৳<?php echo number_format(((float)$item['price']) * ((int)$item['quantity']), 2); ?></small>
                            </div>
                        <?php endforeach; ?>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>৳<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (10%):</span>
                            <span>৳<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping:</span>
                            <span class="<?php echo $shipping == 0 ? 'text-success' : ''; ?>">
                                <?php echo $shipping == 0 ? 'FREE' : '৳' . number_format($shipping, 2); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top">
                            <span>Total:</span>
                            <span class="text-success">৳<?php echo number_format($total, 2); ?></span>
                        </div>

                        <div class="mt-4">
                            <div class="alert alert-info small mb-0">
                                <strong>Free Shipping</strong> on orders over ৳50
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Information (fixed missing tags) -->
                <div class="card mt-3">
                    
                </div>

            </div>
        </div>
    <?php endif; ?>

</div>

<!-- FOOTER (Full width background, Quick Links removed) -->
<footer class="footer-custom">
    <div class="footer-inner container">
        <div class="row">
            <div class="col-md-8 mb-3">
                <h5>AgroTradeHub</h5>
                <p>Connecting farmers directly with customers for fresh farm products.</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Contact</h5>
                <p>Email: info@agrotradehub.com</p>
                <p>Phone: +1 234 567 890</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>