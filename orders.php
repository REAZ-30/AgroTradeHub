<?php
session_start();
require_once 'database.php';

// Check if user is customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header('Location: login.php');
    exit();
}

$customer_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();

// Get customer orders from database
$orders = [];

try {
    $query = "SELECT o.id, o.order_number, o.total_amount, o.status, o.order_date, o.shipping_address, o.created_at
              FROM orders o 
              WHERE o.customer_id = ? 
              ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $items_query = "SELECT oi.quantity, oi.price, p.name, p.image_url, p.id as product_id
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->execute([$order['id']]);
        $order['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if reviews already exist for each product in this order
        foreach ($order['items'] as &$item) {
            $review_check_query = "SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?";
            $review_check_stmt = $conn->prepare($review_check_query);
            $review_check_stmt->execute([$item['product_id'], $customer_id]);
            $item['has_reviewed'] = $review_check_stmt->rowCount() > 0;
        }
    }
    
} catch (Exception $e) {
    $error_message = "Error loading orders: " . $e->getMessage();
}

// If no orders found, show empty state
if (empty($orders)) {
    $no_orders = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - AgroTradeHub</title>
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

        .cart-icon {
            position: relative;
            margin-left: 15px;
            color: #000000;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .cart-count {
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
        .order-card {
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 6px 12px;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .review-btn {
            margin-top: 5px;
            font-size: 0.8rem;
            padding: 4px 8px;
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
            <a href="cart.php">Cart</a>
            <a href="orders.php" class="active">My Orders</a>
        </div>

        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <button class="dropbtn">
                        <span class="user-welcome">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">Customer Account</div>
                        <a href="profile.php" class="dropdown-item">My Profile</a>
                        <a href="orders.php" class="dropdown-item">My Orders</a>
                        <a href="cart.php" class="dropdown-item">My Cart</a>
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
        <h1 class="display-5 text-success mb-4">My Orders</h1>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($no_orders) && $no_orders): ?>
            <!-- No Orders State -->
            <div class="card text-center py-5">
                <div class="card-body">
                    <div class="text-muted mb-4">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <h3 class="text-muted">No Orders Yet</h3>
                    <p class="text-muted mb-4">You haven't placed any orders yet. Start shopping to see your orders here!</p>
                    <a href="products.php" class="btn btn-success btn-lg">Start Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Orders List -->
            <div class="row">
                <div class="col-12">
                    <?php foreach($orders as $order): ?>
                        <div class="card order-card mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Order #<?php echo $order['order_number']; ?></h6>
                                    <small class="text-muted">Placed on <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="price-tag fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                    <span class="status-badge badge 
                                        <?php 
                                        switch($order['status']) {
                                            case 'completed': echo 'bg-success'; break;
                                            case 'processing': echo 'bg-primary'; break;
                                            case 'pending': echo 'bg-warning'; break;
                                            case 'cancelled': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Shipping Address -->
                                <div class="mb-3">
                                    <small class="text-muted">Shipping to:</small>
                                    <p class="mb-1 small"><?php echo $order['shipping_address']; ?></p>
                                </div>
                                
                                <!-- Order Items -->
                                <h6 class="mb-3">Order Items:</h6>
                                <div class="row">
                                    <?php foreach($order['items'] as $item): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="d-flex align-items-center">
                                                <?php if($item['image_url']): ?>
                                                    <img src="<?php echo $item['image_url']; ?>" 
                                                         alt="<?php echo $item['name']; ?>" 
                                                         class="product-image me-3">
                                                <?php else: ?>
                                                    <div class="product-image me-3 bg-light d-flex align-items-center justify-content-center">
                                                        <small class="text-muted">No image</small>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="mb-1 small fw-bold"><?php echo $item['name']; ?></p>
                                                    <p class="mb-0 small text-muted">
                                                        Qty: <?php echo $item['quantity']; ?> × 
                                                        $<?php echo number_format($item['price'], 2); ?>
                                                    </p>
                                                    <p class="mb-0 small text-success fw-bold">
                                                        $<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Order Actions -->
                                <div class="mt-3 pt-3 border-top">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-success btn-sm">Track Order</button>
                                        <button class="btn btn-outline-primary btn-sm">View Invoice</button>
                                        <?php if($order['status'] == 'pending' || $order['status'] == 'processing'): ?>
                                            <button class="btn btn-outline-danger btn-sm">Cancel Order</button>
                                        <?php endif; ?>
                                        
                                        <!-- LEAVE A REVIEW BUTTON - Only for completed orders -->
                                        <?php if($order['status'] == 'completed'): ?>
                                            <?php 
                                            // Check if there are any items that haven't been reviewed yet
                                            $has_unreviewed_items = false;
                                            foreach($order['items'] as $item) {
                                                if(!$item['has_reviewed']) {
                                                    $has_unreviewed_items = true;
                                                    break;
                                                }
                                            }
                                            ?>
                                            
                                            <?php if($has_unreviewed_items): ?>
                                                <a href="review.php?order_id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-warning btn-sm">
                                                    Leave a Review
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm" disabled>
                                                    All Items Reviewed
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Order Stats -->
            <div class="row mt-5">
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?php echo count($orders); ?></h5>
                            <p class="card-text small text-muted">Total Orders</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary">
                                <?php 
                                $pending_orders = array_filter($orders, function($order) {
                                    return $order['status'] == 'pending';
                                });
                                echo count($pending_orders);
                                ?>
                            </h5>
                            <p class="card-text small text-muted">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-info">
                                <?php 
                                $completed_orders = array_filter($orders, function($order) {
                                    return $order['status'] == 'completed';
                                });
                                echo count($completed_orders);
                                ?>
                            </h5>
                            <p class="card-text small text-muted">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning">
                                <?php 
                                $active_orders = array_filter($orders, function($order) {
                                    return in_array($order['status'], ['pending', 'processing']);
                                });
                                echo count($active_orders);
                                ?>
                            </h5>
                            <p class="card-text small text-muted">Active</p>
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
                        <li><a href="orders.php" class="text-white">My Orders</a></li>
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