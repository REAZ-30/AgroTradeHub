<?php
session_start();
require_once 'database.php';

// Check if user is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'seller') {
    header('Location: login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->getConnection();

// Handle order confirmation - Confirm only this seller's items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $order_id = $_POST['order_id'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Get items for this order and seller
        $items_query = "SELECT oi.product_id, oi.quantity, p.name, p.quantity as current_stock
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ? AND p.seller_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->execute([$order_id, $seller_id]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($order_items)) {
            throw new Exception("No items found for this seller in order #$order_id");
        }
        
        // Check if all products have sufficient quantity
        $insufficient_quantity = false;
        $insufficient_products = [];
        
        foreach ($order_items as $item) {
            if ($item['current_stock'] < $item['quantity']) {
                $insufficient_quantity = true;
                $insufficient_products[] = $item['name'] . " (Available: " . $item['current_stock'] . ", Required: " . $item['quantity'] . ")";
            }
        }
        
        if ($insufficient_quantity) {
            $conn->rollBack();
            $error_message = "Cannot confirm order. Insufficient stock for: " . implode(", ", $insufficient_products);
        } else {
            // Update product quantities
            foreach ($order_items as $item) {
                $update_query = "UPDATE products 
                                SET quantity = quantity - ? 
                                WHERE id = ? AND seller_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$item['quantity'], $item['product_id'], $seller_id]);
            }
            
            // Mark seller's items as confirmed (NOT the entire order)
            $confirm_query = "UPDATE order_items 
                             SET seller_confirmed = 1, confirmed_at = NOW() 
                             WHERE order_id = ? AND product_id IN (
                                 SELECT id FROM products WHERE seller_id = ?
                             )";
            $confirm_stmt = $conn->prepare($confirm_query);
            $confirm_stmt->execute([$order_id, $seller_id]);
            
            // Check if ALL items in the order are now confirmed by all sellers
            $check_complete_query = "SELECT 
                                    COUNT(*) as total_items,
                                    SUM(CASE WHEN seller_confirmed = 1 THEN 1 ELSE 0 END) as confirmed_items
                                    FROM order_items 
                                    WHERE order_id = ?";
            $check_stmt = $conn->prepare($check_complete_query);
            $check_stmt->execute([$order_id]);
            $confirmation_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // If all items are confirmed by their respective sellers, mark order as completed
            if ($confirmation_status['total_items'] == $confirmation_status['confirmed_items']) {
                $order_query = "UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'pending'";
                $order_stmt = $conn->prepare($order_query);
                $order_stmt->execute([$order_id]);
                
                // Log this action
                $seller_name = $_SESSION['full_name'];
                $order_number_query = "SELECT order_number FROM orders WHERE id = ?";
                $order_number_stmt = $conn->prepare($order_number_query);
                $order_number_stmt->execute([$order_id]);
                $order_data = $order_number_stmt->fetch(PDO::FETCH_ASSOC);
                $order_number = $order_data['order_number'] ?? $order_id;
                
                $success_message = "Your items in Order #$order_number have been confirmed! <strong>All sellers have now confirmed, order is now completed.</strong>";
            } else {
                $success_message = "Your items in Order #$order_id have been confirmed! Waiting for other sellers to confirm their items.";
            }
            
            $conn->commit();
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_message = "Error confirming order: " . $e->getMessage();
    }
}

// Get real analytics data from database
$analytics_data = [];

// Total products
$query = "SELECT COUNT(*) as total_products FROM products WHERE seller_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$analytics_data['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

// Total confirmed items (by this seller)
$query = "SELECT COUNT(*) as total_confirmed 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          WHERE p.seller_id = ? AND oi.seller_confirmed = 1";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$analytics_data['total_confirmed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_confirmed'];

// Total orders and revenue
$query = "SELECT COUNT(DISTINCT oi.order_id) as total_orders, 
                 SUM(oi.quantity * oi.price) as total_revenue 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          WHERE p.seller_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$analytics_data['total_orders'] = $result['total_orders'] ?? 0;
$analytics_data['total_revenue'] = $result['total_revenue'] ?? 0;

// Monthly sales (last 6 months)
$analytics_data['monthly_sales'] = [];
$query = "SELECT MONTH(o.created_at) as month, 
                 YEAR(o.created_at) as year,
                 SUM(oi.quantity * oi.price) as revenue
          FROM orders o
          JOIN order_items oi ON o.id = oi.order_id
          JOIN products p ON oi.product_id = p.id
          WHERE p.seller_id = ? 
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY YEAR(o.created_at), MONTH(o.created_at)
          ORDER BY year, month";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $month_num = date('n', strtotime($date));
    $year_num = date('Y', strtotime($date));
    
    $revenue = 0;
    foreach ($monthly_data as $data) {
        if ($data['month'] == $month_num && $data['year'] == $year_num) {
            $revenue = $data['revenue'];
            break;
        }
    }
    $analytics_data['monthly_sales'][$month_names[$month_num - 1]] = $revenue;
}

// Top products
$query = "SELECT p.name, 
                 SUM(oi.quantity) as sales, 
                 SUM(oi.quantity * oi.price) as revenue
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          WHERE p.seller_id = ?
          GROUP BY p.id, p.name
          ORDER BY sales DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$analytics_data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ALL orders - Get ALL orders where this seller has items (REMOVED LIMIT 5)
$query = "SELECT DISTINCT o.id as order_id, o.order_number, o.created_at, o.status,
                 (SELECT SUM(oi2.quantity * oi2.price) 
                  FROM order_items oi2 
                  WHERE oi2.order_id = o.id) as order_total,
                 -- Check if all items in this order are confirmed
                 (SELECT 
                    CASE 
                        WHEN COUNT(*) = 0 THEN 'no_items'
                        WHEN SUM(CASE WHEN seller_confirmed = 1 THEN 1 ELSE 0 END) = COUNT(*) THEN 'all_confirmed'
                        ELSE 'partial_confirmed'
                    END
                 FROM order_items oi3 
                 WHERE oi3.order_id = o.id) as order_confirmation_status
          FROM orders o
          JOIN order_items oi ON o.id = oi.order_id
          JOIN products p ON oi.product_id = p.id
          WHERE p.seller_id = ?
          ORDER BY o.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$recent_orders_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process orders data
$analytics_data['recent_orders'] = [];
foreach ($recent_orders_raw as $order) {
    // Get this seller's items for this order
    $items_query = "SELECT p.name, oi.quantity, p.quantity as current_stock, oi.seller_confirmed
                   FROM order_items oi
                   JOIN products p ON oi.product_id = p.id
                   WHERE oi.order_id = ? AND p.seller_id = ?";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->execute([$order['order_id'], $seller_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items_text = '';
    $stock_warning = false;
    $seller_confirmed = true; // Assume confirmed until proven otherwise
    
    foreach ($items as $index => $item) {
        if ($index > 0) $items_text .= ', ';
        $items_text .= $item['name'] . ' × ' . $item['quantity'];
        
        // Check if stock is sufficient
        if ($order['status'] === 'pending' && $item['current_stock'] < $item['quantity']) {
            $stock_warning = true;
        }
        
        // Check if this seller has confirmed their items
        if ($item['seller_confirmed'] == 0) {
            $seller_confirmed = false;
        }
    }
    
    // Determine display status for this seller
    $display_status = $order['status'];
    if ($order['status'] === 'pending') {
        if ($seller_confirmed) {
            $display_status = 'confirmed_by_me';
        }
    }
    
    $analytics_data['recent_orders'][] = [
        'order_id' => $order['order_id'],
        'order_number' => $order['order_number'],
        'created_at' => $order['created_at'],
        'status' => $order['status'],
        'display_status' => $display_status,
        'order_confirmation_status' => $order['order_confirmation_status'],
        'items' => $items_text ?: 'No items',
        'order_total' => $order['order_total'] ?? 0,
        'stock_warning' => $stock_warning,
        'seller_confirmed' => $seller_confirmed
    ];
}

// If no data exists, show zeros instead of errors
if (empty($analytics_data['top_products'])) {
    $analytics_data['top_products'] = [];
}
if (empty($analytics_data['recent_orders'])) {
    $analytics_data['recent_orders'] = [];
}

// If monthly sales is empty, create default structure
if (empty($analytics_data['monthly_sales'])) {
    $analytics_data['monthly_sales'] = [
        'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 
        'Apr' => 0, 'May' => 0, 'Jun' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Analytics - AgroTradeHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            color: black;
            font-weight: 500;
            margin-right: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        /* Order Status Colors */
        .order-status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .order-status-confirmed_by_me {
            background-color: #cce7ff;
            color: #004085;
        }
        .order-status-processing {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .order-status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .order-status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Stock Warning */
        .stock-warning {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .warning-badge {
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }
        
        /* Stats Cards */
        .stats-card {
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        /* Confirmation status indicator */
        .confirmation-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 5px;
        }
        
        .confirmation-all {
            background-color: #d4edda;
            color: #155724;
        }
        
        .confirmation-partial {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .confirmation-none {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Table scroll for many orders */
        .table-container {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .table-container table {
            margin-bottom: 0;
        }
        
        .table-container thead {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
        }
        
        /* Filter buttons */
        .filter-buttons {
            margin-bottom: 15px;
        }
        
        .filter-btn {
            margin-right: 5px;
            margin-bottom: 5px;
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
            
            .table-container {
                max-height: 400px;
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
            <a href="addproducts.php">Add Products</a>
            <a href="analytics.php" class="active">Analytics</a>
        </div>

        <div class="auth-buttons">
            <div class="dropdown">
                <button class="dropbtn">
                    <span class="user-welcome"><?php echo $_SESSION['full_name']; ?></span>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-header">Seller Account</div>
                    <a href="profile.php" class="dropdown-item">My Profile</a>
                   
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <h1 class="display-5 text-success mb-4">Seller Analytics Dashboard</h1>
        
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <h2 class="display-4"><?php echo $analytics_data['total_products']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Confirmed Items</h5>
                        <h2 class="display-4"><?php echo $analytics_data['total_confirmed']; ?></h2>
                        <small>Items you've confirmed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Total Revenue</h5>
                        <h2 class="display-6">৳<?php echo number_format($analytics_data['total_revenue'], 2); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Orders</h5>
                        <h2 class="display-4">
                            <?php echo $analytics_data['total_orders']; ?>
                        </h2>
                        <small>Orders with your products</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales Chart -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Monthly Sales Revenue</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($analytics_data['top_products'])): ?>
                            <?php foreach($analytics_data['top_products'] as $product): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo $product['name']; ?></h6>
                                        <small class="text-muted"><?php echo $product['sales']; ?> sales</small>
                                    </div>
                                    <span class="text-success fw-bold">৳<?php echo number_format($product['revenue'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No sales data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- All Orders -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Orders (<?php echo count($analytics_data['recent_orders']); ?> orders)</h5>
                        <div class="filter-buttons">
                            <button class="btn btn-sm btn-light filter-btn" data-filter="all">All</button>
                            <button class="btn btn-sm btn-warning filter-btn" data-filter="pending">Pending</button>
                            <button class="btn btn-sm btn-primary filter-btn" data-filter="processing">Processing</button>
                            <button class="btn btn-sm btn-success filter-btn" data-filter="completed">Completed</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container">
                            <table class="table table-hover mb-0" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Your Items</th>
                                        <th>Order Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($analytics_data['recent_orders'])): ?>
                                        <?php foreach($analytics_data['recent_orders'] as $order): ?>
                                            <tr class="order-row <?php echo ($order['status'] === 'pending' && $order['stock_warning']) ? 'stock-warning' : ''; ?>" 
                                                data-status="<?php echo $order['display_status']; ?>">
                                                <td>
                                                    <strong><?php echo $order['order_number']; ?></strong>
                                                    <?php if($order['status'] === 'pending' && $order['stock_warning']): ?>
                                                        <span class="warning-badge">Low Stock</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Confirmation: 
                                                        <span class="confirmation-status 
                                                            <?php 
                                                            switch($order['order_confirmation_status']) {
                                                                case 'all_confirmed': echo 'confirmation-all'; break;
                                                                case 'partial_confirmed': echo 'confirmation-partial'; break;
                                                                case 'no_items': echo 'confirmation-none'; break;
                                                                default: echo 'confirmation-none';
                                                            }
                                                            ?>">
                                                            <?php 
                                                            switch($order['order_confirmation_status']) {
                                                                case 'all_confirmed': echo 'All confirmed'; break;
                                                                case 'partial_confirmed': echo 'Partial'; break;
                                                                case 'no_items': echo 'No items'; break;
                                                                default: echo 'Unknown';
                                                            }
                                                            ?>
                                                        </span>
                                                    </small>
                                                </td>
                                                <td><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <?php echo $order['items']; ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Your status: 
                                                        <?php if($order['seller_confirmed']): ?>
                                                            <span class="text-success">✓ Confirmed</span>
                                                        <?php else: ?>
                                                            <span class="text-warning">⏳ Pending</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge order-status-<?php echo $order['display_status']; ?>">
                                                        <?php 
                                                        if ($order['display_status'] === 'confirmed_by_me') {
                                                            echo 'Confirmed by you';
                                                        } else {
                                                            echo ucfirst($order['status']);
                                                        }
                                                        ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php if($order['order_confirmation_status'] === 'partial_confirmed' && $order['status'] === 'pending'): ?>
                                                            ⏳ Waiting for other sellers
                                                        <?php elseif($order['order_confirmation_status'] === 'all_confirmed' && $order['status'] === 'pending'): ?>
                                                            ✓ All sellers confirmed
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if($order['status'] === 'pending' && !$order['seller_confirmed']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                            <button type="submit" name="confirm_order" class="btn btn-success btn-sm">
                                                                Confirm Your Items
                                                            </button>
                                                            <?php if($order['stock_warning']): ?>
                                                                <small class="d-block text-danger mt-1">Check stock before confirming</small>
                                                            <?php endif; ?>
                                                        </form>
                                                    <?php elseif($order['status'] === 'pending' && $order['seller_confirmed']): ?>
                                                        <span class="text-success fw-bold">✓ Confirmed</span>
                                                        <small class="d-block text-muted">
                                                            <?php if($order['order_confirmation_status'] === 'partial_confirmed'): ?>
                                                                Waiting for other sellers
                                                            <?php elseif($order['order_confirmation_status'] === 'all_confirmed'): ?>
                                                                All sellers confirmed
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php elseif($order['status'] === 'completed'): ?>
                                                        <span class="text-success fw-bold">Completed</span>
                                                        <small class="d-block text-muted">Order delivered</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3">
                                                <p class="text-muted">No orders found.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Confirmation Legend -->
                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Confirmation Legend</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <span class="confirmation-status confirmation-all">All confirmed</span>
                                <small class="text-muted"> - All sellers have confirmed</small>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="confirmation-status confirmation-partial">Partial</span>
                                <small class="text-muted"> - Some sellers confirmed</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <span class="badge order-status-pending">Pending</span>
                                <small class="text-muted"> - Order awaiting confirmation</small>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="badge order-status-confirmed_by_me">Confirmed by you</span>
                                <small class="text-muted"> - You've confirmed, waiting for others</small>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="badge order-status-processing">Processing</span>
                                <small class="text-muted"> - Some sellers confirmed</small>
                            </div>
                            <div class="col-md-6 mb-2">
                                <span class="badge order-status-completed">Completed</span>
                                <small class="text-muted"> - All sellers confirmed</small>
                            </div>
                        </div>
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
                
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <p>Email: info@agrotradehub.com<br>Phone: +1 234 567 890</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($analytics_data['monthly_sales'])); ?>,
                datasets: [{
                    label: 'Sales Revenue (৳)',
                    data: <?php echo json_encode(array_values($analytics_data['monthly_sales'])); ?>,
                    backgroundColor: '#198754',
                    borderColor: '#146c43',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '৳' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Confirm order with stock warning
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const stockWarning = this.querySelector('.text-danger');
                if (stockWarning) {
                    if (!confirm('⚠️ WARNING: One or more products have insufficient stock.\n\nAre you sure you want to confirm these items?')) {
                        e.preventDefault();
                    }
                }
            });
        });
        
        // Order filtering functionality
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Filter rows
                const rows = document.querySelectorAll('.order-row');
                rows.forEach(row => {
                    if (filter === 'all') {
                        row.style.display = '';
                    } else {
                        const rowStatus = row.getAttribute('data-status');
                        if (rowStatus === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        });
        
        // Show all orders by default
        document.querySelector('.filter-btn[data-filter="all"]').classList.add('active');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>