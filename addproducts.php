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

$success_message = '';
$error_message = '';

// Handle product operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an edit or add operation
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $product_id = $_POST['product_id'] ?? null;
        
        if ($action === 'edit' && $product_id) {
            // Update existing product
            $name = $_POST['name'];
            $description = $_POST['description'];
            $price = $_POST['price'];
            $quantity = $_POST['quantity'];
            $category_id = $_POST['category_id'];
            $product_type = $_POST['product_type'];
            $image_url = $_POST['image_url'] ?? '';
            $is_available = isset($_POST['is_available']) ? 1 : 0;

            try {
                $query = "UPDATE products SET 
                         name = ?, description = ?, price = ?, quantity = ?, 
                         category_id = ?, product_type = ?, image_url = ?, is_available = ?
                         WHERE id = ? AND seller_id = ?";
                $stmt = $conn->prepare($query);
                
                $success = $stmt->execute([
                    $name, $description, $price, $quantity,
                    $category_id, $product_type, $image_url, $is_available,
                    $product_id, $seller_id
                ]);
                
                if ($success) {
                    $success_message = "Product updated successfully!";
                    // Clear the edit mode
                    unset($_GET['edit']);
                } else {
                    $error_message = "Failed to update product. Please try again.";
                }
                
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } else {
        // Add new product
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $quantity = $_POST['quantity'];
        $category_id = $_POST['category_id'];
        $product_type = $_POST['product_type'];
        $image_url = $_POST['image_url'] ?? '';

        try {
            // Insert product into database
            $query = "INSERT INTO products (seller_id, category_id, name, description, price, quantity, image_url, product_type, is_available, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            $stmt = $conn->prepare($query);
            
            $success = $stmt->execute([
                $seller_id,
                $category_id,
                $name,
                $description,
                $price,
                $quantity,
                $image_url,
                $product_type
            ]);
            
            if ($success) {
                $success_message = "Product added successfully!";
                // Clear form
                $_POST = array();
            } else {
                $error_message = "Failed to add product. Please try again.";
            }
            
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categories = [];
try {
    $query = "SELECT id, name FROM categories ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error loading categories: " . $e->getMessage();
}

// Get seller's existing products with category names
$seller_products = [];
try {
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE p.seller_id = ? 
              ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$seller_id]);
    $seller_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If error, continue with empty products array
}

// Get product data for editing
$edit_product = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $product_id = $_GET['edit'];
    try {
        $query = "SELECT * FROM products WHERE id = ? AND seller_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$product_id, $seller_id]);
        $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If product not found or doesn't belong to seller
        if (!$edit_product) {
            $error_message = "Product not found or you don't have permission to edit it.";
        }
    } catch (Exception $e) {
        $error_message = "Error loading product: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Products - AgroTradeHub</title>
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
            color: black;
            font-weight: 500;
            margin-right: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        /* Original Page Styles (keeping Bootstrap for content) */
        .product-form-card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 10px;
        }
        .product-card {
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link.active {
            background-color: #198754;
            color: white;
            border-color: #198754;
        }
        .nav-tabs .nav-link {
            color: #198754;
        }
        .edit-indicator {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-bottom: 20px;
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
            <a href="addproducts.php" class="active">Add Products</a>
            <a href="analytics.php">Analytics</a>
        </div>

        <div class="auth-buttons">
            <div class="dropdown">
                <button class="dropbtn">
                    <span class="user-welcome">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                </button>
                <div class="dropdown-menu">
                    <div class="dropdown-header">Seller Account</div>
                    <a href="profile.php" class="dropdown-item">My Profile</a>
                    <a href="analytics.php" class="dropdown-item">Analytics</a>
                    <a href="addproducts.php" class="dropdown-item">Add Products</a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <h1 class="display-5 text-success mb-4">Manage Your Products</h1>
        
        <!-- Success/Error Messages -->
        <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Mode Indicator -->
        <?php if($edit_product): ?>
            <div class="edit-indicator">
                <strong>Editing:</strong> <?php echo htmlspecialchars($edit_product['name']); ?>
                - <a href="addproducts.php" class="text-decoration-none">Cancel Edit</a>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo !$edit_product ? 'active' : ''; ?>" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab">
                    <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $edit_product ? 'active' : ''; ?>" id="manage-tab" data-bs-toggle="tab" data-bs-target="#manage" type="button" role="tab">
                    Manage Products (<?php echo count($seller_products); ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="productTabsContent">
            <!-- Add/Edit Product Tab -->
            <div class="tab-pane fade <?php echo !$edit_product ? 'show active' : ''; ?>" id="add" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card product-form-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?></h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <?php if($edit_product): ?>
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Product Name *</label>
                                                <input type="text" name="name" class="form-control" 
                                                       value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : (htmlspecialchars($_POST['name'] ?? '')); ?>" 
                                                       placeholder="Enter product name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Product Type *</label>
                                                <select name="product_type" class="form-select" required>
                                                    <option value="">Select Type</option>
                                                    <option value="vegetable" <?php echo ($edit_product ? $edit_product['product_type'] : ($_POST['product_type'] ?? '')) == 'vegetable' ? 'selected' : ''; ?>>Vegetable</option>
                                                    <option value="grain" <?php echo ($edit_product ? $edit_product['product_type'] : ($_POST['product_type'] ?? '')) == 'grain' ? 'selected' : ''; ?>>Grain</option>
                                                    <option value="fruit" <?php echo ($edit_product ? $edit_product['product_type'] : ($_POST['product_type'] ?? '')) == 'fruit' ? 'selected' : ''; ?>>Fruit</option>
                                                    <option value="fish" <?php echo ($edit_product ? $edit_product['product_type'] : ($_POST['product_type'] ?? '')) == 'fish' ? 'selected' : ''; ?>>Fish</option>
                                                    <option value="meat" <?php echo ($edit_product ? $edit_product['product_type'] : ($_POST['product_type'] ?? '')) == 'meat' ? 'selected' : ''; ?>>Meat</option>
                                                    <option value="dairy" <?php echo ($edit_product ? $edit_product['product_type'] : ($_POST['product_type'] ?? '')) == 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Description *</label>
                                        <textarea name="description" class="form-control" rows="3" 
                                                  placeholder="Describe your product..." required><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : (htmlspecialchars($_POST['description'] ?? '')); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Price ($) *</label>
                                                <input type="number" name="price" class="form-control" 
                                                       value="<?php echo $edit_product ? $edit_product['price'] : ($_POST['price'] ?? ''); ?>" 
                                                       step="0.01" min="0.01" placeholder="0.00" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Quantity *</label>
                                                <input type="number" name="quantity" class="form-control" 
                                                       value="<?php echo $edit_product ? $edit_product['quantity'] : ($_POST['quantity'] ?? ''); ?>" 
                                                       min="1" placeholder="Available quantity" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Category *</label>
                                                <select name="category_id" class="form-select" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" 
                                                            <?php echo ($edit_product ? $edit_product['category_id'] : ($_POST['category_id'] ?? '')) == $category['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Product Image URL</label>
                                        <input type="url" name="image_url" class="form-control" 
                                               value="<?php echo $edit_product ? htmlspecialchars($edit_product['image_url']) : (htmlspecialchars($_POST['image_url'] ?? '')); ?>" 
                                               placeholder="https://example.com/image.jpg">
                                        <div class="form-text">Optional: Provide a URL for your product image</div>
                                    </div>

                                    <?php if($edit_product): ?>
                                        <div class="mb-3 form-check">
                                            <input type="checkbox" name="is_available" class="form-check-input" id="is_available" <?php echo $edit_product['is_available'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_available">Product Available</label>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                                        </button>
                                        <?php if($edit_product): ?>
                                            <a href="addproducts.php" class="btn btn-secondary">Cancel Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Products Tab -->
            <div class="tab-pane fade <?php echo $edit_product ? 'show active' : ''; ?>" id="manage" role="tabpanel">
                <?php if(empty($seller_products)): ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <div class="text-muted mb-4">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                            </div>
                            <h3 class="text-muted">No Products Yet</h3>
                            <p class="text-muted mb-4">You haven't added any products yet. Start by adding your first product!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($seller_products as $product): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card product-card h-100">
                                    <?php if($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <span class="text-muted">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <span class="badge <?php echo $product['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $product['is_available'] ? 'Available' : 'Disabled'; ?>
                                            </span>
                                        </div>
                                        <p class="card-text small text-muted">
                                            Type: <span class="text-capitalize"><?php echo $product['product_type']; ?></span>
                                        </p>
                                        <p class="card-text small text-muted">
                                            Category: <?php echo htmlspecialchars($product['category_name']); ?>
                                        </p>
                                        <p class="card-text small"><?php echo substr(htmlspecialchars($product['description']), 0, 100); ?>...</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="price-tag fw-bold text-success">$<?php echo number_format($product['price'], 2); ?></span>
                                            <span class="text-muted small">Qty: <?php echo $product['quantity']; ?></span>
                                        </div>
                                        <p class="card-text small text-muted mt-2">
                                            Added: <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="d-flex gap-2 flex-wrap">
                                            <!-- Only Edit button remains -->
                                            <a href="addproducts.php?edit=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
                        <li><a href="analytics.php" class="text-white">Analytics</a></li>
                        <li><a href="addproducts.php" class="text-white">Add Products</a></li>
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
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            // Price input formatting
            const priceInput = document.querySelector('input[name="price"]');
            if (priceInput) {
                priceInput.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = parseFloat(this.value).toFixed(2);
                    }
                });
            }

            // Auto-switch to manage tab after successful submission
            <?php if($success_message && !$edit_product): ?>
                const manageTab = new bootstrap.Tab(document.getElementById('manage-tab'));
                manageTab.show();
            <?php endif; ?>

            // Show manage tab if editing
            <?php if($edit_product): ?>
                const manageTab = new bootstrap.Tab(document.getElementById('manage-tab'));
                manageTab.show();
            <?php endif; ?>

            // Handle tab switching with URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit')) {
                const manageTab = new bootstrap.Tab(document.getElementById('manage-tab'));
                manageTab.show();
            }
        });
    </script>
</body>
</html>