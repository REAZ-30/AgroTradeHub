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

// Handle order confirmation - Set directly to 'completed' and reduce product quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $order_id = $_POST['order_id'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // First, get all products and quantities from this order
        $items_query = "SELECT oi.product_id, oi.quantity 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ? AND p.seller_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->execute([$order_id, $seller_id]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if all products have sufficient quantity
        $insufficient_quantity = false;
        $insufficient_products = [];
        
        foreach ($order_items as $item) {
            // Check current quantity
            $check_query = "SELECT name, quantity FROM products WHERE id = ? AND seller_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$item['product_id'], $seller_id]);
            $product = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product && $product['quantity'] < $item['quantity']) {
                $insufficient_quantity = true;
                $insufficient_products[] = $product['name'] . " (Available: " . $product['quantity'] . ", Required: " . $item['quantity'] . ")";
            }
        }
        
        if ($insufficient_quantity) {
            $conn->rollBack();
            $error_message = "Cannot complete order. Insufficient stock for: " . implode(", ", $insufficient_products);
        } else {
            // Update product quantities
            foreach ($order_items as $item) {
                $update_query = "UPDATE products 
                                SET quantity = quantity - ? 
                                WHERE id = ? AND seller_id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$item['quantity'], $item['product_id'], $seller_id]);
            }
            
            // Update order status to 'completed'
            $order_query = "UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'pending'";
            $order_stmt = $conn->prepare($order_query);
            $order_stmt->execute([$order_id]);
            
            if ($order_stmt->rowCount() > 0) {
                $conn->commit();
                
                // Get order number for success message
                $order_number_query = "SELECT order_number FROM orders WHERE id = ?";
                $order_number_stmt = $conn->prepare($order_number_query);
                $order_number_stmt->execute([$order_id]);
                $order_data = $order_number_stmt->fetch(PDO::FETCH_ASSOC);
                $order_number = $order_data['order_number'] ?? $order_id;
                
                $success_message = "Order #$order_number marked as completed successfully! Product quantities updated.";
            } else {
                $conn->rollBack();
                $error_message = "Order could not be updated. It may already be completed or cancelled.";
            }
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error updating order: " . $e->getMessage();
    }
}

// Get real analytics data from database
$analytics_data = [];

// Total products
$query = "SELECT COUNT(*) as total_products FROM products WHERE seller_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$analytics_data['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

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

// Recent orders - Get distinct orders
$query = "SELECT DISTINCT o.id as order_id, o.order_number, o.created_at, o.status,
                 (SELECT SUM(oi2.quantity * oi2.price) 
                  FROM order_items oi2 
                  WHERE oi2.order_id = o.id) as order_total
          FROM orders o
          JOIN order_items oi ON o.id = oi.order_id
          JOIN products p ON oi.product_id = p.id
          WHERE p.seller_id = ?
          ORDER BY o.created_at DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$seller_id]);
$recent_orders_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process recent orders data
$analytics_data['recent_orders'] = [];
foreach ($recent_orders_raw as $order) {
    // Get items for this order with stock information
    $items_query = "SELECT p.name, oi.quantity, p.quantity as current_stock
                   FROM order_items oi
                   JOIN products p ON oi.product_id = p.id
                   WHERE oi.order_id = ? AND p.seller_id = ?";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->execute([$order['order_id'], $seller_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items_text = '';
    $stock_warning = false;
    foreach ($items as $index => $item) {
        if ($index > 0) $items_text .= ', ';
        $items_text .= $item['name'] . ' × ' . $item['quantity'];
        
        // Check if stock is sufficient
        if ($order['status'] === 'pending' && $item['current_stock'] < $item['quantity']) {
            $stock_warning = true;
        }
    }
    
    $analytics_data['recent_orders'][] = [
        'order_id' => $order['order_id'],
        'order_number' => $order['order_number'],
        'created_at' => $order['created_at'],
        'status' => $order['status'],
        'items' => $items_text ?: 'No items',
        'order_total' => $order['order_total'] ?? 0,
        'stock_warning' => $stock_warning
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
        .order-status-confirmed {
            background-color: #cce7ff;
            color: #004085;
        }
        .order-status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .order-status-processing {
            background-color: #d1ecf1;
            color: #0c5460;
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
                        <h5 class="card-title">Total Orders</h5>
                        <h2 class="display-4"><?php echo $analytics_data['total_orders']; ?></h2>
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
                        <h5 class="card-title">Pending Orders</h5>
                        <h2 class="display-4">
                            <?php 
                            $pending_count = 0;
                            foreach($analytics_data['recent_orders'] as $order) {
                                if($order['status'] === 'pending') {
                                    $pending_count++;
                                }
                            }
                            echo $pending_count;
                            ?>
                        </h2>
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
            <!-- Recent Activity -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Orders</h5>
                        <span class="badge bg-light text-dark"><?php echo count($analytics_data['recent_orders']); ?> orders</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($analytics_data['recent_orders'])): ?>
                                        <?php foreach($analytics_data['recent_orders'] as $order): ?>
                                            <tr class="<?php echo ($order['status'] === 'pending' && $order['stock_warning']) ? 'stock-warning' : ''; ?>">
                                                <td>
                                                    <strong><?php echo $order['order_number']; ?></strong>
                                                    <?php if($order['status'] === 'pending' && $order['stock_warning']): ?>
                                                        <span class="warning-badge">Low Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></td>
                                                <td><?php echo $order['items']; ?></td>
                                                <td class="fw-bold text-success">৳<?php echo number_format($order['order_total'], 2); ?></td>
                                                <td>
                                                    <span class="badge order-status-<?php echo $order['status']; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($order['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                            <button type="submit" name="confirm_order" class="btn btn-success btn-sm">
                                                                Mark as Completed
                                                            </button>
                                                            <?php if($order['stock_warning']): ?>
                                                                <small class="d-block text-danger mt-1">Check stock before completing</small>
                                                            <?php endif; ?>
                                                        </form>
                                                    <?php elseif($order['status'] === 'completed'): ?>
                                                        <span class="text-success fw-bold">Completed</span>
                                                        <small class="d-block text-muted">Customers can review</small>
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
                                            <td colspan="6" class="text-center py-3">
                                                <p class="text-muted">No recent orders found.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
                    label: 'Sales Revenue ($)',
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
                                return '$' + value;
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
                    if (!confirm('⚠️ WARNING: One or more products have insufficient stock.\n\nAre you sure you want to complete this order?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>