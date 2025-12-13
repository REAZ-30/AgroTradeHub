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

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Initialize variables
$order = null;
$order_items = [];
$error = '';
$success = '';

// Validate that the order belongs to the customer and is completed
if ($order_id > 0) {
    try {
        // Check if order belongs to customer and is completed
        $order_check_query = "SELECT o.id, o.order_number, o.status 
                              FROM orders o 
                              WHERE o.id = ? AND o.customer_id = ? AND o.status = 'completed'";
        $order_check_stmt = $conn->prepare($order_check_query);
        $order_check_stmt->execute([$order_id, $customer_id]);
        
        $order = $order_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $error = "Invalid order or order is not completed yet.";
        } else {
            // Get all products from the order that haven't been reviewed yet
            $items_query = "SELECT p.*, oi.quantity, oi.price
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ? 
                           AND p.id NOT IN (
                               SELECT product_id FROM reviews WHERE customer_id = ?
                           )";
            $items_stmt = $conn->prepare($items_query);
            $items_stmt->execute([$order_id, $customer_id]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($order_items)) {
                $error = "You have already reviewed all products in this order.";
            }
        }
    } catch (Exception $e) {
        $error = "Error validating order: " . $e->getMessage();
    }
} else {
    $error = "Invalid request parameters.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    $reviews_submitted = 0;
    $errors = [];
    
    foreach ($_POST['rating'] as $product_id => $rating) {
        $product_id = intval($product_id);
        $rating = intval($rating);
        $comment = isset($_POST['comment'][$product_id]) ? trim($_POST['comment'][$product_id]) : '';
        
        // Validate inputs
        if ($rating < 1 || $rating > 5) {
            $errors[] = "Please select a rating between 1 and 5 stars for product ID: $product_id.";
        } elseif (empty($comment)) {
            $errors[] = "Please write a comment for product ID: $product_id.";
        } elseif (strlen($comment) < 10) {
            $errors[] = "Review comment must be at least 10 characters long for product ID: $product_id.";
        } else {
            try {
                // Check if review already exists
                $check_query = "SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([$product_id, $customer_id]);
                
                if ($check_stmt->rowCount() == 0) {
                    // Insert review into database
                    $insert_query = "INSERT INTO reviews (product_id, customer_id, rating, comment) 
                                     VALUES (?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->execute([$product_id, $customer_id, $rating, $comment]);
                    $reviews_submitted++;
                }
            } catch (Exception $e) {
                $errors[] = "Error submitting review for product ID: $product_id. " . $e->getMessage();
            }
        }
    }
    
    if (count($errors) > 0) {
        $error = implode("<br>", $errors);
    } elseif ($reviews_submitted > 0) {
        $success = "Thank you for your review" . ($reviews_submitted > 1 ? "s" : "") . "!";
        // Redirect back to orders page after 2 seconds
        header("refresh:2;url=orders.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review - AgroTradeHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }
        
        /* Agro Navbar Styles - Matching orders.php */
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

        /* Review Page Styles */
        .review-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .order-header {
            background: linear-gradient(135deg, #2DC653, #28a745);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .product-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            background: white;
        }
        
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .star-rating {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            margin: 15px 0;
        }
        
        .star-rating .selected {
            color: #FFD700;
        }
        
        .star-rating .hover {
            color: #FFD700;
            opacity: 0.7;
        }
        
        .review-form textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .rating-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .rating-text {
            font-size: 1rem;
            margin-top: 10px;
            text-align: center;
            color: #f39c12;
            font-weight: 500;
        }
        
        .product-section {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .product-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
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
            
            .product-image {
                width: 100px;
                height: 100px;
            }
            
            .star-rating {
                font-size: 1.8rem;
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
            <a href="products.php">Products</a>
            <a href="cart.php">Cart</a>
            <a href="orders.php" class="active">My Orders</a>
        </div>

        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <button class="dropbtn">
                        <span class="user-welcome">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
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

    <div class="container mt-4 mb-5">
        <div class="review-container">
            <!-- Back to Orders Link -->
            <a href="orders.php" class="btn btn-outline-secondary mb-4">
                ← Back to My Orders
            </a>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                    <div class="mt-2">
                        <a href="orders.php" class="btn btn-sm btn-outline-danger">Return to Orders</a>
                    </div>
                </div>
            <?php elseif($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <p class="mb-0 mt-2">Redirecting back to orders page...</p>
                </div>
            <?php elseif($order && !empty($order_items)): ?>
                <!-- Order Information -->
                <div class="order-header">
                    <h2>Review Your Order</h2>
                    <p class="mb-0">Order #<?php echo $order['order_number']; ?> - Please review your products</p>
                </div>
                
                <!-- Review Form -->
                <form method="POST" action="">
                    <?php foreach($order_items as $index => $item): ?>
                        <div class="product-card">
                            <div class="product-section">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center mb-3 mb-md-0">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image"
                                             onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&h=300&fit=crop'">
                                    </div>
                                    <div class="col-md-9">
                                        <h4 class="text-success"><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <p class="mb-0">
                                            <strong>Quantity:</strong> <?php echo $item['quantity']; ?> × 
                                            <strong>Price:</strong> $<?php echo number_format($item['price'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Star Rating -->
                                <div class="mt-4">
                                    <div class="rating-label">How would you rate this product?</div>
                                    <div class="star-rating" id="starRating_<?php echo $item['id']; ?>">
                                        <span data-value="1">☆</span>
                                        <span data-value="2">☆</span>
                                        <span data-value="3">☆</span>
                                        <span data-value="4">☆</span>
                                        <span data-value="5">☆</span>
                                    </div>
                                    <input type="hidden" name="rating[<?php echo $item['id']; ?>]" 
                                           id="ratingValue_<?php echo $item['id']; ?>" value="5" required>
                                    <div class="rating-text" id="ratingText_<?php echo $item['id']; ?>">Excellent - 5 stars</div>
                                </div>
                                
                                <!-- Review Comment -->
                                <div class="mt-3">
                                    <label for="comment_<?php echo $item['id']; ?>" class="form-label rating-label">
                                        Your Review for <?php echo htmlspecialchars($item['name']); ?>
                                    </label>
                                    <textarea name="comment[<?php echo $item['id']; ?>]" 
                                              id="comment_<?php echo $item['id']; ?>" 
                                              class="form-control" 
                                              placeholder="Share your experience with this product. What did you like or dislike? How was the quality?"
                                              required></textarea>
                                    <div class="form-text">Please write at least 10 characters about your experience.</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Submit Button -->
                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="orders.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" name="submit_review" class="btn btn-success btn-lg">
                                    Submit All Reviews
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Review Guidelines -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Review Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Be honest about your experience with the products</li>
                            <li>Focus on product quality, freshness, and delivery</li>
                            <li>Avoid personal information about the seller</li>
                            <li>Keep your reviews respectful and constructive</li>
                            <li>Your reviews will help other customers make better decisions</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
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
    <script>
        // Initialize all star rating systems
        const ratingDescriptions = {
            1: 'Poor - 1 star',
            2: 'Fair - 2 stars',
            3: 'Good - 3 stars',
            4: 'Very Good - 4 stars',
            5: 'Excellent - 5 stars'
        };
        
        // Initialize star rating for each product
        <?php foreach($order_items as $item): ?>
        (function() {
            const productId = <?php echo $item['id']; ?>;
            const stars = document.querySelectorAll('#starRating_' + productId + ' span');
            const ratingValue = document.getElementById('ratingValue_' + productId);
            const ratingText = document.getElementById('ratingText_' + productId);
            
            function updateStars(value) {
                stars.forEach((star, index) => {
                    if (index < value) {
                        star.textContent = '★';
                        star.classList.add('selected');
                        star.classList.remove('hover');
                    } else {
                        star.textContent = '☆';
                        star.classList.remove('selected', 'hover');
                    }
                });
            }
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    ratingValue.value = value;
                    ratingText.textContent = ratingDescriptions[value];
                    updateStars(value);
                });
                
                star.addEventListener('mouseover', function() {
                    const value = this.getAttribute('data-value');
                    ratingText.textContent = ratingDescriptions[value];
                    
                    stars.forEach((s, index) => {
                        if (index < value) {
                            s.textContent = '★';
                            s.classList.add('hover');
                        } else {
                            s.textContent = '☆';
                            s.classList.remove('hover');
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    const currentValue = ratingValue.value;
                    ratingText.textContent = ratingDescriptions[currentValue];
                    updateStars(currentValue);
                });
            });
            
            // Initialize stars
            updateStars(ratingValue.value);
        })();
        <?php endforeach; ?>
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessages = [];
            
            <?php foreach($order_items as $item): ?>
            const rating<?php echo $item['id']; ?> = parseInt(document.getElementById('ratingValue_<?php echo $item['id']; ?>').value);
            const comment<?php echo $item['id']; ?> = document.getElementById('comment_<?php echo $item['id']; ?>').value.trim();
            
            if (rating<?php echo $item['id']; ?> < 1 || rating<?php echo $item['id']; ?> > 5) {
                isValid = false;
                errorMessages.push('Please select a rating for <?php echo htmlspecialchars(addslashes($item['name'])); ?>');
            }
            
            if (comment<?php echo $item['id']; ?>.length < 10) {
                isValid = false;
                errorMessages.push('Please write at least 10 characters for <?php echo htmlspecialchars(addslashes($item['name'])); ?>');
            }
            <?php endforeach; ?>
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>