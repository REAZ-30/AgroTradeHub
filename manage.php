<?php
session_start();
require_once 'database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Handle user actions (delete only)
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $action = $_GET['action'];
    
    if ($action == 'delete') {
        try {
            $conn->beginTransaction();
            
            // First delete related records to avoid foreign key constraints
            // Delete user's products and their related order items
            $product_query = "SELECT id FROM products WHERE seller_id = ?";
            $product_stmt = $conn->prepare($product_query);
            $product_stmt->execute([$user_id]);
            $user_products = $product_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($user_products)) {
                // Delete order items for user's products
                $delete_order_items = "DELETE FROM order_items WHERE product_id IN (" . 
                                    implode(',', array_fill(0, count($user_products), '?')) . ")";
                $order_items_stmt = $conn->prepare($delete_order_items);
                $order_items_stmt->execute($user_products);
                
                // Delete user's products
                $delete_products = "DELETE FROM products WHERE seller_id = ?";
                $products_stmt = $conn->prepare($delete_products);
                $products_stmt->execute([$user_id]);
            }
            
            // Delete user's orders and order items if user is a customer
            $order_query = "SELECT id FROM orders WHERE customer_id = ?";
            $order_stmt = $conn->prepare($order_query);
            $order_stmt->execute([$user_id]);
            $user_orders = $order_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($user_orders)) {
                // Delete order items for user's orders
                $delete_order_items = "DELETE FROM order_items WHERE order_id IN (" . 
                                    implode(',', array_fill(0, count($user_orders), '?')) . ")";
                $order_items_stmt = $conn->prepare($delete_order_items);
                $order_items_stmt->execute($user_orders);
                
                // Delete user's orders
                $delete_orders = "DELETE FROM orders WHERE customer_id = ?";
                $orders_stmt = $conn->prepare($delete_orders);
                $orders_stmt->execute([$user_id]);
            }
            
            // Finally delete the user
            $delete_user = "DELETE FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($delete_user);
            $user_stmt->execute([$user_id]);
            
            $conn->commit();
            $_SESSION['success_message'] = "User deleted successfully!";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
        }
    }
    
    header('Location: manage.php');
    exit();
}

// Handle product deletion
if (isset($_GET['delete_product']) && isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    
    try {
        $conn->beginTransaction();
        
        // First delete related order items
        $delete_order_items = "DELETE FROM order_items WHERE product_id = ?";
        $order_items_stmt = $conn->prepare($delete_order_items);
        $order_items_stmt->execute([$product_id]);
        
        // Then delete the product
        $delete_product = "DELETE FROM products WHERE id = ?";
        $product_stmt = $conn->prepare($delete_product);
        $product_stmt->execute([$product_id]);
        
        $conn->commit();
        $_SESSION['success_message'] = "Product deleted successfully!";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error deleting product: " . $e->getMessage();
    }
    
    header('Location: manage.php');
    exit();
}

// Get real management data from database
$management_data = [];

// Total users
$query = "SELECT COUNT(*) as total_users FROM users";
$stmt = $conn->prepare($query);
$stmt->execute();
$management_data['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// Total sellers
$query = "SELECT COUNT(*) as total_sellers FROM users WHERE user_type = 'seller'";
$stmt = $conn->prepare($query);
$stmt->execute();
$management_data['total_sellers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sellers'];

// Total products
$query = "SELECT COUNT(*) as total_products FROM products";
$stmt = $conn->prepare($query);
$stmt->execute();
$management_data['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'];

// Total orders
$query = "SELECT COUNT(*) as total_orders FROM orders";
$stmt = $conn->prepare($query);
$stmt->execute();
$management_data['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'];

// Get all users for management
$query = "SELECT id, username, full_name, email, user_type, created_at 
          FROM users 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all products for management
$query = "SELECT p.id, p.name, p.price, p.quantity, p.product_type, p.created_at, u.full_name as seller_name
          FROM products p 
          JOIN users u ON p.seller_id = u.id 
          ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent users
$query = "SELECT id, full_name as name, email, user_type as type, created_at as joined 
          FROM users 
          ORDER BY created_at DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$management_data['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Product categories
$query = "SELECT product_type, COUNT(*) as count 
          FROM products 
          GROUP BY product_type";
$stmt = $conn->prepare($query);
$stmt->execute();
$categories_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$management_data['product_categories'] = [];
foreach ($categories_data as $category) {
    $management_data['product_categories'][$category['product_type']] = $category['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - AgroTradeHub</title>
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
        
        /* Admin Dropdown */
        .admin-dropdown .dropdown-menu {
            min-width: 200px;
        }
        
        /* Original Page Styles (keeping Bootstrap for content) */
        .stats-card {
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .sidebar {
            position: sticky;
            top: 20px;
        }
        .nav-tabs .nav-link.active {
            background-color: #198754;
            color: white;
            border-color: #198754;
        }
        .nav-tabs .nav-link {
            color: #198754;
        }
        .action-buttons .btn {
            margin: 2px;
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
            <div class="dropdown admin-dropdown">
                <a href="#" class="active">Admin Panel</a>
                <div class="dropdown-menu">
                    <div class="dropdown-header">Admin Menu</div>
                    <a href="manage.php" class="dropdown-item">Dashboard</a>
                    <a href="manage.php?tab=users" class="dropdown-item">User Management</a>
                    <a href="manage.php?tab=products" class="dropdown-item">Product Management</a>
                </div>
            </div>
        </div>

        <div class="auth-buttons">
            <div class="dropdown">
                <button class="dropbtn">
                    <span class="user-welcome">Admin <?php echo $_SESSION['full_name']; ?></span>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-header">Admin Account</div>
                    <a href="profile.php" class="dropdown-item">My Profile</a>
                    <a href="manage.php" class="dropdown-item">Admin Panel</a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2">
                <div class="card sidebar">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Admin Menu</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="manage.php" class="list-group-item list-group-item-action <?php echo !isset($_GET['tab']) ? 'active' : ''; ?>">Dashboard</a>
                        <a href="manage.php?tab=users" class="list-group-item list-group-item-action <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'users') ? 'active' : ''; ?>">User Management</a>
                        <a href="manage.php?tab=products" class="list-group-item list-group-item-action <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'products') ? 'active' : ''; ?>">Product Management</a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10">
                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if(isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <?php if(!isset($_GET['tab'])): ?>
                    <!-- Dashboard Tab -->
                    <h1 class="display-5 text-success mb-4">Admin Management Dashboard</h1>
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Users</h5>
                                    <h2 class="display-4"><?php echo $management_data['total_users']; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Sellers</h5>
                                    <h2 class="display-4"><?php echo $management_data['total_sellers']; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card stats-card bg-warning text-dark">
                                <div class="card-body">
                                    <h5 class="card-title">Total Products</h5>
                                    <h2 class="display-4"><?php echo $management_data['total_products']; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Orders</h5>
                                    <h2 class="display-4"><?php echo $management_data['total_orders']; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Recent Users -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Users</h5>
                                    <a href="manage.php?tab=users" class="btn btn-sm btn-light">View All</a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Type</th>
                                                    <th>Joined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($management_data['recent_users'] as $user): ?>
                                                    <tr>
                                                        <td><?php echo $user['name']; ?></td>
                                                        <td><?php echo $user['email']; ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $user['type'] == 'seller' ? 'bg-success' : 'bg-primary'; ?>">
                                                                <?php echo ucfirst($user['type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M j, Y', strtotime($user['joined'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Categories -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Product Categories</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="categoriesChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif($_GET['tab'] == 'users'): ?>
                    <!-- User Management Tab -->
                    <h1 class="display-5 text-success mb-4">User Management</h1>
                    
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">All Users (<?php echo count($users); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Type</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo $user['username']; ?></td>
                                                <td><?php echo $user['full_name']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $user['user_type'] == 'admin' ? 'bg-danger' : 
                                                             ($user['user_type'] == 'seller' ? 'bg-success' : 'bg-primary'); 
                                                    ?>">
                                                        <?php echo ucfirst($user['user_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="manage.php?tab=users&action=delete&user_id=<?php echo $user['id']; ?>" 
                                                           class="btn btn-danger btn-sm" 
                                                           onclick="return confirm('WARNING: This will permanently delete this user and all their associated data (products, orders, etc.). Are you sure?')">Delete</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Current User</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php elseif($_GET['tab'] == 'products'): ?>
                    <!-- Product Management Tab -->
                    <h1 class="display-5 text-success mb-4">Product Management</h1>
                    
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">All Products (<?php echo count($products); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Product Name</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Type</th>
                                            <th>Seller</th>
                                            <th>Added</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($products as $product): ?>
                                            <tr>
                                                <td><?php echo $product['id']; ?></td>
                                                <td><?php echo $product['name']; ?></td>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                <td><?php echo $product['quantity']; ?></td>
                                                <td>
                                                    <span class="badge bg-info text-capitalize">
                                                        <?php echo $product['product_type']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $product['seller_name']; ?></td>
                                                <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                                                <td class="action-buttons">
                                                    <a href="manage.php?tab=products&delete_product=1&product_id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Are you sure you want to delete this product? This will also remove it from any orders.')">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if(!isset($_GET['tab'])): ?>
    <script>
        // Categories Chart
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        const categoriesChart = new Chart(categoriesCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($management_data['product_categories'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($management_data['product_categories'])); ?>,
                    backgroundColor: [
                        '#198754', '#20c997', '#ffc107', '#fd7e14', '#dc3545', '#6f42c1',
                        '#e83e8c', '#6c757d', '#343a40', '#007bff', '#28a745', '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>