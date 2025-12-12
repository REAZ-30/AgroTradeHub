<?php
session_start();
require_once 'database.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Get product ID from URL parameter
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize variables
$product = null;
$average_rating = 0;
$reviews = [];
$review_count = 0;

if ($product_id > 0 && $conn) {
    try {
        // Fetch product details with seller and category info
        $product_query = "SELECT p.*, u.full_name as seller_name, u.email as seller_email, 
                                 u.phone as seller_phone, u.address as seller_address,
                                 c.name as category_name
                          FROM products p 
                          JOIN users u ON p.seller_id = u.id 
                          JOIN categories c ON p.category_id = c.id 
                          WHERE p.id = ? AND p.is_available = 1";
        $stmt = $conn->prepare($product_query);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Fetch reviews for this product
            $review_query = "SELECT r.*, u.full_name as customer_name 
                             FROM reviews r 
                             JOIN users u ON r.customer_id = u.id 
                             WHERE r.product_id = ? 
                             ORDER BY r.created_at DESC";
            $stmt2 = $conn->prepare($review_query);
            $stmt2->execute([$product_id]);
            $reviews = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $review_count = count($reviews);
            
            // Calculate average rating
            if ($review_count > 0) {
                $total_rating = 0;
                foreach ($reviews as $review) {
                    $total_rating += $review['rating'];
                }
                $average_rating = round($total_rating / $review_count, 1);
            }
        }
    } catch (Exception $e) {
        // Handle error
        error_log("Error fetching product details: " . $e->getMessage());
    }
}

// If product not found, redirect to products page
if (!$product) {
    header("Location: products.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - AgroTradeHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }
        
        /* Agro Navbar Styles - Matching products.php */
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
        
        /* Product Details Styles */
        .product-image-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: white;
            padding: 20px;
            height: 100%;
        }
        
        .product-main-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
        }
        
        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .product-price {
            font-size: 2rem;
            color: #28a745;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .product-category {
            display: inline-block;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
        
        .product-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #555;
            margin-bottom: 2rem;
        }
        
        .seller-info-card {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .seller-info-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .rating-stars {
            color: #FFD700;
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .rating-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #f39c12;
        }
        
        .rating-count {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .review-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: white;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .review-date {
            color: #95a5a6;
            font-size: 0.9rem;
        }
        
        .no-reviews {
            text-align: center;
            padding: 3rem;
            color: #95a5a6;
        }
        
        .info-box {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .info-box-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
            border-bottom: 2px solid #2DC653;
            padding-bottom: 0.5rem;
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
            
            .product-title {
                font-size: 2rem;
            }
            
            .product-price {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Agro Custom Navbar -->
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
                        <span class="user-welcome">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">My Profile</a>
                        <?php if($_SESSION['user_type'] == 'customer'): ?>
                            <a href="cart.php" class="dropdown-item">My Cart</a>
                            <a href="orders.php" class="dropdown-item">My Orders</a>
                        <?php elseif($_SESSION['user_type'] == 'seller'): ?>
                            <a href="addproducts.php" class="dropdown-item">Add Products</a>
                            <a href="analytics.php" class="dropdown-item">Seller Analytics</a>
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

    <div class="container mt-4 mb-5">
        <!-- Back to Products Link -->
        <a href="products.php" class="btn btn-outline-secondary mb-4">
            ← Back to Products
        </a>

        <!-- Product Details -->
        <div class="row">
            <!-- Product Image -->
            <div class="col-lg-6 mb-4">
                <div class="product-image-container">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="product-main-image"
                         onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=600&h=400&fit=crop'">
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-lg-6">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- Rating -->
                <div class="d-flex align-items-center mb-3">
                    <div class="rating-stars">
                        <?php
                        $full_stars = floor($average_rating);
                        $has_half_star = ($average_rating - $full_stars) >= 0.5;
                        
                        for($i = 1; $i <= 5; $i++):
                            if($i <= $full_stars):
                                echo '★';
                            elseif($i == $full_stars + 1 && $has_half_star):
                                echo '★';
                            else:
                                echo '☆';
                            endif;
                        endfor;
                        ?>
                    </div>
                    <span class="rating-value"><?php echo $average_rating; ?></span>
                    <span class="rating-count ms-2">(<?php echo $review_count; ?> reviews)</span>
                </div>
                
                <!-- Price -->
                <div class="product-price">
                    ৳<?php echo number_format($product['price'], 2); ?> per kg
                </div>
                
                <!-- Category -->
                <div class="product-category">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </div>
                
                <!-- Stock Status -->
                <div class="info-box">
                    <h5 class="info-box-title">Availability</h5>
                    <div class="alert <?php echo $product['quantity'] > 0 ? 'alert-success' : 'alert-danger'; ?> mb-0">
                        <?php echo $product['quantity'] > 0 ? '✓ In Stock: ' . $product['quantity'] . ' kg available' : '✗ Out of Stock'; ?>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="info-box">
                    <h5 class="info-box-title">Description</h5>
                    <p class="product-description mb-0"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                
                <!-- Seller Information -->
                <div class="seller-info-card">
                    <h5 class="seller-info-title">Seller Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong><br><?php echo htmlspecialchars($product['seller_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong><br><?php echo htmlspecialchars($product['seller_email']); ?></p>
                        </div>
                    </div>
                    <?php if(!empty($product['seller_phone'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Phone:</strong><br><?php echo htmlspecialchars($product['seller_phone']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if(!empty($product['seller_address'])): ?>
                        <div class="row">
                            <div class="col-12">
                                <p><strong>Address:</strong><br><?php echo htmlspecialchars($product['seller_address']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Details -->
                <div class="info-box">
                    <h5 class="info-box-title">Product Details</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Product Type:</strong><br><?php echo htmlspecialchars(ucfirst($product['product_type'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Category:</strong><br><?php echo htmlspecialchars($product['category_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Price:</strong><br>৳<?php echo number_format($product['price'], 2); ?> per kg</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Available Quantity:</strong><br><?php echo $product['quantity']; ?> kg</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Reviews Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Customer Reviews (<?php echo $review_count; ?>)</h4>
                    </div>
                    <div class="card-body">
                        <?php if($review_count > 0): ?>
                            <?php foreach($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="reviewer-name">
                                            <?php echo htmlspecialchars($review['customer_name']); ?>
                                        </div>
                                        <div class="review-date">
                                            <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="rating-stars mb-2">
                                        <?php
                                        for($i = 1; $i <= 5; $i++):
                                            if($i <= $review['rating']):
                                                echo '★';
                                            else:
                                                echo '☆';
                                            endif;
                                        endfor;
                                        ?>
                                        <span class="rating-value ms-2"><?php echo $review['rating']; ?>/5</span>
                                    </div>
                                    <?php if(!empty($review['comment'])): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-reviews">
                                <p class="mb-3">No reviews yet for this product.</p>
                            </div>
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