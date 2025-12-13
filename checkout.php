<?php
session_start();
// Redirect if user is not logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit();
}

// Get user details from database
$db = new Database();
$conn = $db->getConnection();
$user_query = "SELECT full_name, email, phone, address FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}
$tax = $subtotal * 0.1;
$shipping = ($subtotal > 50) ? 0 : 5.00;
$total = $subtotal + $tax + $shipping;

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $errors = [];
    
    if (empty($_POST['contact_phone'])) {
        $errors[] = 'Contact phone number is required';
    }
    
    if (empty($_POST['address'])) {
        $errors[] = 'Delivery address is required';
    }
    
    if (empty($_POST['bkash_mobile'])) {
        $errors[] = 'bKash mobile number is required';
    }
    
    if (empty($_POST['bkash_transaction'])) {
        $errors[] = 'bKash transaction ID is required';
    }
    
    if (empty($errors)) {
        $transactionStarted = false;
        try {
            // Start transaction
            $conn->beginTransaction();
            $transactionStarted = true;
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd-His') . '-' . rand(1000, 9999);
            
            // Create order with payment details - payment_status defaults to 'pending'
            $query = "INSERT INTO orders (customer_id, total_amount, shipping_address, status, 
                      payment_method, payment_transaction_id, payment_mobile, customer_phone, order_number) 
                      VALUES (?, ?, ?, 'pending', 'bkash', ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            $shipping_address = $_POST['address'];
            $contact_phone = $_POST['contact_phone'];
            $bkash_mobile = $_POST['bkash_mobile'];
            $bkash_transaction = $_POST['bkash_transaction'];
            
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
                // Get seller_id from product
                $product_query = "SELECT seller_id FROM products WHERE id = ?";
                $product_stmt = $conn->prepare($product_query);
                $product_stmt->execute([$product_id]);
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("Product with ID $product_id not found.");
                }
                
                if (!isset($product['seller_id']) || empty($product['seller_id'])) {
                    throw new Exception("Product '{$item['name']}' has no seller assigned.");
                }
                
                // Insert order item
                $item_query = "INSERT INTO order_items (order_id, product_id, seller_id, quantity, price) 
                               VALUES (?, ?, ?, ?, ?)";
                $item_stmt = $conn->prepare($item_query);
                $success = $item_stmt->execute([
                    $order_id, 
                    $product_id, 
                    $product['seller_id'], 
                    $item['quantity'], 
                    $item['price']
                ]);
                
                if (!$success) {
                    throw new Exception("Failed to add {$item['name']} to order.");
                }
            }
            
            // Commit the transaction first
            $conn->commit();
            $transactionStarted = false;
            
            // Now verify payment
            require_once 'bkash_api.php';
            $bkash = new BkashAPI();
            $payment_result = $bkash->verifyPayment($bkash_transaction, $total, $bkash_mobile);
            
            if ($payment_result['success']) {
                // Start a new transaction for payment update
                $conn->beginTransaction();
                $transactionStarted = true;
                
                // Update payment status
                $update_payment = "UPDATE orders SET payment_status = 'paid', status = 'pending' WHERE id = ?";
                $stmt = $conn->prepare($update_payment);
                $stmt->execute([$order_id]);
                
                $conn->commit();
                $transactionStarted = false;
                
                // Clear cart after successful checkout
                $_SESSION['cart'] = [];
                $_SESSION['order_success'] = [
                    'order_number' => $order_number,
                    'total' => $total,
                    'transaction_id' => $bkash_transaction,
                    'contact_phone' => $contact_phone
                ];
                
                header('Location: checkout.php?success=1');
                exit();
            } else {
                // Start a new transaction for payment failure update
                $conn->beginTransaction();
                $transactionStarted = true;
                
                // Update payment status to failed
                $update_payment = "UPDATE orders SET payment_status = 'failed' WHERE id = ?";
                $stmt = $conn->prepare($update_payment);
                $stmt->execute([$order_id]);
                
                $conn->commit();
                $transactionStarted = false;
                
                throw new Exception('Payment verification failed: ' . $payment_result['message']);
            }
            
        } catch (Exception $e) {
            // Only rollback if a transaction was started
            if ($transactionStarted && isset($conn)) {
                try {
                    $conn->rollBack();
                } catch (Exception $rollbackEx) {
                    // Ignore rollback errors
                }
            }
            $_SESSION['error_message'] = 'Order failed: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Check for success message
$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - AgroTradeHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }
        .bkash-payment {
            background: linear-gradient(135deg, #E2136E, #FF1493);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .bkash-btn {
            background: #E2136E;
            border: none;
            color: white;
        }
        .bkash-btn:hover {
            background: #C0105C;
            color: white;
        }
        .section-title {
            color: #28a745;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-label.required:after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">AgroTradeHub</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Products</a>
                    </li>
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">Cart</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                Welcome, <?php echo $_SESSION['full_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Display Error Message -->
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
            <!-- Checkout Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="section-title">Checkout Details</h4>
                            
                            <form method="POST">
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
                                    <h5 class="mb-3">bKash Payment Details</h5>
                                    <p class="mb-3">Complete payment using bKash and enter payment details below:</p>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label required">bKash Mobile Number</label>
                                            <input type="text" class="form-control" name="bkash_mobile" 
                                                   placeholder="01XXXXXXXXX" required>
                                            <small class="text-muted">The number you used for bKash payment</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label required">bKash Transaction ID</label>
                                            <input type="text" class="form-control" name="bkash_transaction" 
                                                   placeholder="TRX123456789" required>
                                            <small class="text-muted">Find this in your bKash transaction history/SMS</small>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <h6>How to Pay with bKash:</h6>
                                        <ol class="small">
                                            <li>Dial *247# from your bKash registered mobile</li>
                                            <li>Choose "Send Money"</li>
                                            <li>Enter Merchant Number: <strong>017XXXXXXXX</strong></li>
                                            <li>Enter Amount: <strong>$<?php echo number_format($total, 2); ?></strong></li>
                                            <li>Enter Reference: <strong>Order Payment</strong></li>
                                            <li>Enter your bKash PIN to complete</li>
                                            <li>Save the Transaction ID and enter it above</li>
                                        </ol>
                                        <div class="alert alert-light mt-2">
                                            <small>
                                                <strong>For Testing/Demo:</strong><br>
                                                • Use any 11-digit phone number starting with 01<br>
                                                • Transaction ID: Use <strong>TRX123456789</strong> or any TRX followed by numbers<br>
                                                • Or use any numeric ID with 10+ digits
                                            </small>
                                        </div>
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
                    <div class="card sticky-top" style="top: 20px;">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach($_SESSION['cart'] as $product_id => $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 40px; height: 40px; background-color: #f8f9fa; border-radius: 5px; margin-right: 10px;">
                                            <?php if(isset($item['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 5px;">
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <small class="d-block"><?php echo htmlspecialchars($item['name']); ?></small>
                                            <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                        </div>
                                    </div>
                                    <small class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></small>
                                </div>
                            <?php endforeach; ?>
                            
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (10%):</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Shipping:</span>
                                <span class="<?php echo $shipping == 0 ? 'text-success' : ''; ?>">
                                    <?php echo $shipping == 0 ? 'FREE' : '$' . number_format($shipping, 2); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top">
                                <span>Total:</span>
                                <span class="text-success">$<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <div class="mt-4">
                                <div class="alert alert-info small">
                                    <strong>Free Shipping</strong> on orders over $50
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-body">
                            <h6>Delivery Information</h6>
                            <ul class="small text-muted mt-2 mb-0">
                                <li><strong>Standard delivery:</strong> 2-3 business days</li>
                                <li><strong>Free shipping:</strong> Orders over $50</li>
                                <li><strong>Delivery hours:</strong> 9 AM - 8 PM</li>
                                <li><strong>Contact support:</strong> 24/7 available</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-6">
                    <h5>AgroTradeHub</h5>
                    <p>Connecting farmers directly with customers for fresh farm products.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="products.php" class="text-white">Products</a></li>
                        <li><a href="cart.php" class="text-white">Cart</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <p>Email: info@agrotradehub.com<br>Phone: +1 234 567 890</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>