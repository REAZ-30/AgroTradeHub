<?php
session_start();
// Redirect if user is not customer
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] != 'customer') {
    header('Location: index.php');
    exit();
}
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

require_once 'database.php';

// Handle add to cart from products page
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'] ?? 1;
    
    // Find product
    $product = null;
    foreach ($sample_products as $p) {
        if ($p['id'] == $product_id) {
            $product = $p;
            break;
        }
    }
    
    if ($product) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image_url' => $product['image_url'],
                'seller_name' => $product['seller_name']
            ];
        }
        $_SESSION['success_message'] = 'Product added to cart!';
        header('Location: cart.php');
        exit();
    }
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $product_id = $_GET['remove'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        $_SESSION['success_message'] = 'Product removed from cart!';
        header('Location: cart.php');
        exit();
    }
}

// Handle update quantity
if (isset($_POST['update_quantity'])) {
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$product_id]);
        $_SESSION['success_message'] = 'Product removed from cart!';
    } else {
        // Check product stock from database
        $db = new Database();
        $conn = $db->getConnection();
        
        if ($conn) {
            try {
                $query = "SELECT quantity FROM products WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $available_quantity = (int)$product['quantity'];
                    
                    // Validate against available stock
                    if ($quantity <= $available_quantity) {
                        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        $_SESSION['success_message'] = 'Cart updated!';
                    } else {
                        $_SESSION['error_message'] = 'Only ' . $available_quantity . ' items available in stock!';
                        // Set to max available quantity
                        $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                    }
                } else {
                    $_SESSION['error_message'] = 'Product not found!';
                    unset($_SESSION['cart'][$product_id]);
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error checking product availability.';
                // Fallback: keep the requested quantity
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                $_SESSION['success_message'] = 'Cart updated!';
            }
        } else {
            // If no database connection, proceed without validation (fallback)
            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            $_SESSION['success_message'] = 'Cart updated!';
        }
    }
    
    header('Location: cart.php');
    exit();
}

// Validate cart items against current stock when loading the page
if (!empty($_SESSION['cart'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        try {
            // Get all product IDs in cart
            $cart_product_ids = array_keys($_SESSION['cart']);
            if (!empty($cart_product_ids)) {
                $placeholders = str_repeat('?,', count($cart_product_ids) - 1) . '?';
                $query = "SELECT id, quantity FROM products WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($query);
                $stmt->execute($cart_product_ids);
                $products_stock = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // Adjust cart quantities to match available stock
                foreach ($_SESSION['cart'] as $product_id => $item) {
                    if (isset($products_stock[$product_id])) {
                        $available_quantity = (int)$products_stock[$product_id];
                        $cart_quantity = (int)$item['quantity'];
                        
                        if ($cart_quantity > $available_quantity) {
                            // Adjust to max available
                            $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                            if (!isset($_SESSION['stock_adjustment_message'])) {
                                $_SESSION['stock_adjustment_message'] = 'Some items were adjusted to match available stock.';
                            }
                        }
                    } else {
                        // Product no longer exists
                        unset($_SESSION['cart'][$product_id]);
                        if (!isset($_SESSION['stock_adjustment_message'])) {
                            $_SESSION['stock_adjustment_message'] = 'Some unavailable items were removed from your cart.';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Continue without stock validation if there's an error
        }
    }
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}
$tax = $subtotal * 0.1; // 10% tax
$shipping = $subtotal > 0 ? 5.00 : 0; // $5 shipping
$total = $subtotal + $tax + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - AgroTradeHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }
        
        /* Agro Navbar Styles - Custom (No Bootstrap) */
        .navbar {
            background: #2DC653;
            padding: 15px 40px;
            display: flex;
            align-items: center;
            color: #000000;
            justify-content: space-between;
            width: 100%;
            box-sizing: border-box;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 28px;
            font-weight: 600;
            margin-right: 45px;
            white-space: nowrap;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #000000;
            font-size: 16px;
            font-weight: 500;
            transition: color 0.3s;
            white-space: nowrap;
            position: relative;
        }

        .nav-links a:hover {
            color: #ffffff;
        }

        .nav-links a.active {
            color: #ffffff;
            font-weight: 600;
        }

        .auth-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-shrink: 0;
        }

        /* Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            padding: 8px 16px;
            border: none;
            cursor: pointer;
            border-radius: 10px;
            background: #ffffff;
            color: #000000;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
            min-width: max-content;
        }

        .dropbtn:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        /* Improved Dropdown Menu Styles */
        .dropdown-menu {
            display: none;
            position: absolute;
            background: #ffffff;
            min-width: 250px;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            border: none;
            right: 0;
            top: 100%;
        }

        .dropdown-item {
            padding: 10px 15px;
            border-radius: 5px;
            margin: 2px 0;
            width: 100%;
            display: block;
            text-decoration: none;
            color: #000000;
            background: #E0FFE8;
            transition: all 0.3s;
            text-align: left;
            border: none;
            font-size: 14px;
            box-sizing: border-box;
        }

        .dropdown-item:hover {
            background-color: #2DC653;
            color: white;
            transform: translateX(5px);
        }

        .dropdown-header {
            font-weight: bold;
            color: #2DC653;
            font-size: 0.9rem;
            padding: 10px 15px;
            margin-bottom: 5px;
            white-space: nowrap;
        }

        .dropdown-divider {
            height: 1px;
            background: #dee2e6;
            margin: 10px 0;
        }

        .dropdown-item-content {
            display: flex;
            flex-direction: column;
        }

        .dropdown-item-desc {
            color: #666;
            font-size: 0.85rem;
            margin-top: 2px;
        }

        .dropdown-item:hover .dropdown-item-desc {
            color: rgba(255,255,255,0.8);
        }

        /* Make sure dropdown menu stays open on hover */
        .dropdown:hover .dropdown-menu {
            display: block;
        }

        .user-welcome {
            color: #000000;
            font-weight: 500;
            margin-right: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        /* Cart Count Badge */
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Original Page Styles (keeping Bootstrap for content) */
        .cart-item {
            transition: all 0.3s;
        }
        .cart-item:hover {
            background-color: #f8f9fa;
        }
        .quantity-input {
            width: 80px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 15px;
                gap: 15px;
            }
            
            .nav-links {
                margin: 10px 0;
                flex-direction: row;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .auth-buttons {
                margin-top: 10px;
                justify-content: center;
            }
            
            .logo {
                margin-right: 0;
                justify-content: center;
                width: 100%;
            }
            
            .dropdown-menu {
                right: auto;
                left: 50%;
                transform: translateX(-50%);
            }
        }
    </style>
</head>
<body>
    <!-- Agro Custom Navbar (No Bootstrap Navbar Classes) -->
    <div class="navbar">
        <div class="logo">
            <img src="images/cart.png" alt="AgroTradeHub Logo" class="logo-icon">
            AgroTradeHub
        </div>
        
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="products.php">Products</a>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                <a href="cart.php" class="active">
                    Cart
                    <?php if($total_items > 0): ?>
                        <span class="cart-badge"><?php echo $total_items; ?></span>
                    <?php endif; ?>
                </a>
                <a href="orders.php">My Orders</a>
            <?php endif; ?>
        </div>

        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <button class="dropbtn">
                        <span class="user-welcome">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">My Profile</a>
                        <?php if($_SESSION['user_type'] == 'customer'): ?>
                            <a href="cart.php" class="dropdown-item">My Cart</a>
                            <a href="orders.php" class="dropdown-item">My Orders</a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login Dropdown -->
                <div class="dropdown">
                    <button class="dropbtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        Login
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">Quick Login</div>
                        <a href="login.php?demo=customer" class="dropdown-item">Customer Login</a>
                        <div class="dropdown-divider"></div>
                        <a href="login.php" class="dropdown-item">Go to Login Page</a>
                    </div>
                </div>

                <!-- Register Dropdown -->
                <div class="dropdown">
                    <button class="dropbtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        Register
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">Create Account</div>
                        <a href="register.php?type=customer" class="dropdown-item">Customer Account</a>
                        <div class="dropdown-divider"></div>
                        <a href="register.php" class="dropdown-item">Go to Registration Page</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Success Message -->
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Stock Adjustment Message -->
        <?php if(isset($_SESSION['stock_adjustment_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['stock_adjustment_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['stock_adjustment_message']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Shopping Cart</h4>
                    </div>
                    <div class="card-body">
                        <?php if(empty($_SESSION['cart'])): ?>
                            <div class="text-center py-5">
                                <h5 class="text-muted">Your cart is empty</h5>
                                <p class="text-muted">Add some products to get started!</p>
                                <a href="products.php" class="btn btn-success">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <?php 
                            $db = new Database();
                            $conn = $db->getConnection();
                            
                            foreach($_SESSION['cart'] as $product_id => $item): 
                                // Get available stock for this product
                                $available_stock = 99; // Default fallback
                                if ($conn) {
                                    try {
                                        $query = "SELECT quantity FROM products WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->execute([$product_id]);
                                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($product) {
                                            $available_stock = (int)$product['quantity'];
                                        }
                                    } catch (Exception $e) {
                                        // Silently fail
                                    }
                                }
                            ?>
                                <div class="cart-item border-bottom pb-3 mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <img src="<?php echo $item['image_url']; ?>" 
                                                 class="img-fluid rounded" 
                                                 alt="<?php echo $item['name']; ?>"
                                                 onerror="this.src='https://via.placeholder.com/100/2DC653/FFFFFF?text=Prod'">
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="mb-1"><?php echo $item['name']; ?></h6>
                                            <p class="text-muted small mb-0">Seller: <?php echo $item['seller_name']; ?></p>
                                            <p class="text-success fw-bold mb-0">$<?php echo number_format($item['price'], 2); ?></p>
                                            <?php if ($available_stock < 99): ?>
                                                <small class="text-muted d-block">Available: <?php echo $available_stock; ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <form method="POST" class="d-flex align-items-center">
                                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                <label class="me-2">Qty:</label>
                                                <input type="number" name="quantity" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       min="1" 
                                                       max="<?php echo $available_stock; ?>" 
                                                       class="form-control form-control-sm quantity-input">
                                                <button type="submit" name="update_quantity" 
                                                        class="btn btn-sm btn-outline-success ms-2">Update</button>
                                            </form>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <a href="cart.php?remove=<?php echo $product_id; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Remove this item from cart?')">×</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal (<?php echo $total_items; ?> items):</span>
                            <span>$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (10%):</span>
                            <span>$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping:</span>
                            <span>$<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($total, 2); ?></strong>
                        </div>
                        
                        <?php if(!empty($_SESSION['cart'])): ?>
                            <a href="checkout.php" class="btn btn-success w-100 btn-lg">Proceed to Checkout</a>
                            <a href="products.php" class="btn btn-outline-success w-100 mt-2">Continue Shopping</a>
                        <?php else: ?>
                            <button class="btn btn-success w-100 btn-lg" disabled>Proceed to Checkout</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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