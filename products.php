<?php
session_start();
require_once 'database.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Get products from database
$products = [];
$filtered_products = [];

if ($conn) {
    try {
        $query = "SELECT p.*, u.full_name as seller_name, c.name as category_name 
                  FROM products p 
                  JOIN users u ON p.seller_id = u.id 
                  JOIN categories c ON p.category_id = c.id 
                  WHERE p.is_available = 1 
                  ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $filtered_products = $products;
        
    } catch (Exception $e) {
        // If error, use sample products as fallback
        $products = getSampleProducts();
        $filtered_products = $products;
    }
} else {
    // If no database connection, use sample products
    $products = getSampleProducts();
    $filtered_products = $products;
}

// Handle add to cart from products page
if (isset($_POST['add_to_cart']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'customer') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'] ?? 1;
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Find product in database products or sample products
    $product = null;
    foreach ($products as $p) {
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
        header('Location: products.php');
        exit();
    }
}

// Apply filters if any
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = strtolower($_GET['search']);
    $filtered_products = array_filter($filtered_products, function($product) use ($search_term) {
        return strpos(strtolower($product['name']), $search_term) !== false || 
               strpos(strtolower($product['description']), $search_term) !== false;
    });
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $filtered_products = array_filter($filtered_products, function($product) {
        return $product['product_type'] == $_GET['type'];
    });
}

if (isset($_GET['price']) && !empty($_GET['price'])) {
    $filtered_products = array_filter($filtered_products, function($product) {
        $price = $product['price'];
        switch($_GET['price']) {
            case '0-5': return $price < 5;
            case '5-10': return $price >= 5 && $price < 10;
            case '10-20': return $price >= 10 && $price < 20;
            case '20': return $price >= 20;
            default: return true;
        }
    });
}

// Sample products fallback function
function getSampleProducts() {
    return [
        [
            'id' => 1,
            'name' => 'Fresh Tomatoes',
            'description' => 'Organic fresh red tomatoes from local farms',
            'price' => 2.99,
            'quantity' => 50,
            'product_type' => 'vegetable',
            'seller_name' => 'Green Valley Farms',
            'image_url' => 'https://images.unsplash.com/photo-1546470427-e212b7d31075?w=400&h=300&fit=crop',
            'category_name' => 'Vegetables'
        ],
        [
            'id' => 2,
            'name' => 'Basmati Rice',
            'description' => 'Premium quality basmati rice, 1kg pack',
            'price' => 4.99,
            'quantity' => 100,
            'product_type' => 'grain',
            'seller_name' => 'Golden Grains Co.',
            'image_url' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=400&h=300&fit=crop',
            'category_name' => 'Grains'
        ],
        [
            'id' => 3,
            'name' => 'Fresh Apples',
            'description' => 'Sweet and crunchy red apples',
            'price' => 3.49,
            'quantity' => 75,
            'product_type' => 'fruit',
            'seller_name' => 'Orchard Fresh',
            'image_url' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=400&h=300&fit=crop',
            'category_name' => 'Fruits'
        ],
        [
            'id' => 4,
            'name' => 'Fresh Salmon',
            'description' => 'Fresh Atlantic salmon fillets',
            'price' => 12.99,
            'quantity' => 20,
            'product_type' => 'fish',
            'seller_name' => 'Ocean Catch',
            'image_url' => 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=400&h=300&fit=crop',
            'category_name' => 'Fish'
        ],
        [
            'id' => 5,
            'name' => 'Chicken Breast',
            'description' => 'Fresh boneless chicken breast',
            'price' => 8.99,
            'quantity' => 30,
            'product_type' => 'meat',
            'seller_name' => 'Farm Fresh Meats',
            'image_url' => 'https://images.unsplash.com/photo-1587593810167-a84920ea0781?w=400&h=300&fit=crop',
            'category_name' => 'Meat'
        ],
        [
            'id' => 6,
            'name' => 'Fresh Milk',
            'description' => 'Pure farm fresh milk, 1 liter',
            'price' => 2.49,
            'quantity' => 60,
            'product_type' => 'dairy',
            'seller_name' => 'Happy Cows Dairy',
            'image_url' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=400&h=300&fit=crop',
            'category_name' => 'Dairy'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - AgroTradeHub</title>
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
            color: #000000;
            font-weight: 500;
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

        /* User Welcome Styles */
        .user-welcome {
            color: #000000;
            font-weight: 500;
            margin-right: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        /* Cart Icon Styles */
        .cart-icon {
            position: relative;
            margin-left: 15px;
            color: #000000;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .cart-icon:hover {
            color: #ffffff;
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
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
        .price-tag {
            font-size: 1.25rem;
            font-weight: bold;
            color: #28a745;
        }
        .filter-card {
            position: sticky;
            top: 20px;
        }
        .database-badge {
            font-size: 0.7rem;
            position: absolute;
            top: 10px;
            right: 10px;
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
            <a href="products.php" class="active">Products</a>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                <a href="cart.php" class="cart-icon">
                    Cart
                    <?php 
                    $cart_count = 0;
                    if(isset($_SESSION['cart'])) {
                        foreach($_SESSION['cart'] as $item) {
                            $cart_count += $item['quantity'];
                        }
                    }
                    if($cart_count > 0): ?>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="orders.php">My Orders</a>
            <?php endif; ?>
            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'seller'): ?>
                <a href="addproducts.php">Add Products</a>
                <a href="analytics.php">Analytics</a>
            <?php endif; ?>
        </div>

        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <button class="dropbtn">
                        <span class="user-welcome"><?php echo $_SESSION['full_name']; ?></span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">My Profile</a>
                        <?php if($_SESSION['user_type'] == 'customer'): ?>
                         
                        <?php elseif($_SESSION['user_type'] == 'seller'): ?>
                            
                        <?php elseif($_SESSION['user_type'] == 'admin'): ?>
                            <a href="manage.php" class="dropdown-item">Admin Panel</a>
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
                        <a href="login.php?demo=seller" class="dropdown-item">Seller Login</a>
                        <a href="login.php?demo=admin" class="dropdown-item">Admin Login</a>
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
                        <a href="register.php?type=seller" class="dropdown-item">Seller Account</a>
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

        <
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-5 text-success">Our Fresh Products</h1>
                <p class="lead">Direct from farmers to your table</p>
                
                <!-- Search Bar -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" name="search" class="form-control form-control-lg" 
                                       placeholder="Search for vegetables, grains, fruits, fish, meat, dairy..."
                                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-success btn-lg w-100">Search Products</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card filter-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <!-- Product Type Filter -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Product Type</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="vegetable" <?php echo (isset($_GET['type']) && $_GET['type'] == 'vegetable') ? 'selected' : ''; ?>>Vegetables</option>
                                    <option value="grain" <?php echo (isset($_GET['type']) && $_GET['type'] == 'grain') ? 'selected' : ''; ?>>Grains</option>
                                    <option value="fruit" <?php echo (isset($_GET['type']) && $_GET['type'] == 'fruit') ? 'selected' : ''; ?>>Fruits</option>
                                    <option value="fish" <?php echo (isset($_GET['type']) && $_GET['type'] == 'fish') ? 'selected' : ''; ?>>Fish</option>
                                    <option value="meat" <?php echo (isset($_GET['type']) && $_GET['type'] == 'meat') ? 'selected' : ''; ?>>Meat</option>
                                    <option value="dairy" <?php echo (isset($_GET['type']) && $_GET['type'] == 'dairy') ? 'selected' : ''; ?>>Dairy</option>
                                </select>
                            </div>
                            
                            <!-- Price Range Filter -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Price Range</label>
                                <select name="price" class="form-select" onchange="this.form.submit()">
                                    <option value="">Any Price</option>
                                    <option value="0-5" <?php echo (isset($_GET['price']) && $_GET['price'] == '0-5') ? 'selected' : ''; ?>>Under $5</option>
                                    <option value="5-10" <?php echo (isset($_GET['price']) && $_GET['price'] == '5-10') ? 'selected' : ''; ?>>$5 - $10</option>
                                    <option value="10-20" <?php echo (isset($_GET['price']) && $_GET['price'] == '10-20') ? 'selected' : ''; ?>>$10 - $20</option>
                                    <option value="20" <?php echo (isset($_GET['price']) && $_GET['price'] == '20') ? 'selected' : ''; ?>>Over $20</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">Apply Filters</button>
                            <?php if(isset($_GET['search']) || isset($_GET['type']) || isset($_GET['price'])): ?>
                                <a href="products.php" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9">
                <!-- Results Info -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Available Products</h4>
                    <span class="text-muted">
                        <?php echo count($filtered_products); ?> products found
                    </span>
                </div>
                
                <!-- Products -->
                <div class="row">
                    <?php if(count($filtered_products) > 0): ?>
                        <?php foreach($filtered_products as $product): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card product-card position-relative">
                                    <!-- Database Indicator Badge -->
                                    <?php if($product['id'] > 6): ?>
                                        <span class="badge bg-info database-badge">Live</span>
                                    <?php endif; ?>
                                    
                                    <img src="<?php echo $product['image_url']; ?>" 
                                         class="card-img-top product-image" 
                                         alt="<?php echo $product['name']; ?>"
                                         onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&h=300&fit=crop'">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                        <p class="card-text text-muted small"><?php echo $product['description']; ?></p>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="price-tag">৳<?php echo number_format($product['price'], 2); ?></span>
                                            <span class="badge bg-success">In Stock: <?php echo $product['quantity']; ?></span>
                                        </div>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Seller: <?php echo $product['seller_name']; ?><br>
                                                Type: <span class="text-capitalize"><?php echo $product['product_type']; ?></span><br>
                                                Category: <?php echo $product['category_name']; ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'customer'): ?>
                                        <!-- Show Add to Cart only for customers -->
                                        <form method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-success w-100">Add to Cart</button>
                                        </form>
                                        <?php elseif(!isset($_SESSION['user_id'])): ?>
                                        <!-- Show Add to Cart for guests (non-logged in users) -->
                                        <form method="POST">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-success w-100">Add to Cart</button>
                                        </form>
                                        <?php else: ?>
                                        <!-- Show disabled button for sellers and admins -->
                                        <button class="btn btn-outline-secondary w-100" disabled>Login as Customer to Purchase</button>
                                        <?php endif; ?>
                                        <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-success w-100 mt-2">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card text-center py-5">
                                <div class="card-body">
                                    <h5 class="text-muted">No products found</h5>
                                    <p class="text-muted">Try adjusting your search or filters</p>
                                    <a href="products.php" class="btn btn-success">Clear Filters</a>
                                    <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'seller'): ?>
                                        <a href="addproducts.php" class="btn btn-outline-success">Add New Product</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                    <!-- <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="products.php" class="text-white">Products</a></li>
                    </ul> -->
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