<?php
session_start();

// Simple database connection function
function getDBConnection() {
    $host = "localhost";
    $dbname = "agrotradehub";
    $username = "root";
    $password = "";
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        // If database doesn't exist, continue without DB functions
        return null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AgroTradeHub</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Aladin&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Additional styles for the integrated design */
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: #FFFFFF;
      overflow-x: hidden;
    }

/* Navbar */
.navbar {
  background: #2DC653;
  padding: 15px 40px;
  display: flex;
  align-items: center;
  color: #000000;
  justify-content: space-between;
  width: 100%;
  box-sizing: border-box;
}

/* Logo and navigation links on LEFT side */
.logo-container {
  display: flex;
  align-items: center;
  flex-shrink: 0;
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

/* Navigation links - Now positioned next to logo */
.nav-links {
  display: flex;
  align-items: center;
  gap: 25px; /* Space between Home, Products, and other links */
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

/* Auth buttons on RIGHT side */
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
      min-width: 130px;
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
      text-align: center;
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

    /* Auth buttons */
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
      white-space: nowrap;
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

    /* HERO SECTION */
    .hero {
      width: 100%;
      height: 600px;
      display: flex;
      align-items: center;
      padding-left: 60px;
      background: url("images/home.png") no-repeat center center;
      background-size: cover;
    }

    .hero-text {
    width: 45%;
    padding: 20px;
}

    .hero-text h1 {
  font-size: 60px;
  color: #30C976;
  margin: 0;
  line-height: 1.1;
  font-family: 'Aladin', cursive;
  /* text-shadow: 2px 2px 5px rgba(0, 0, 0, 0); */
}

.hero-text p {
  font-size: 18px;
  margin-top: 15px;
  color: #000000;
  line-height: 1.6;
  /* text-shadow: 1px 1px 3px rgba(0, 0, 0, 0); */
}

.hero-text button {
  margin-top: 25px;
  padding: 12px 25px;
  background: #2DC653;
  color: #ffffff;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 18px;
  font-weight: 600;
  transition: all 0.3s;
}

.hero-text button:hover {
  background: #ffffff;
  color: #2DC653;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

    /* Search Bar */
    .search-box {
      margin: 40px auto;
      max-width: 970px;
      display: flex;
      align-items: center;
      border: 2px solid #2DC653;
      padding: 10px;
      background: #E0FFE8;
      border-radius: 10px;
      box-sizing: border-box;
    }

    .search-box input {
      width: 100%;
      border: none;
      outline: none;
      font-size: 18px;
      background: #E0FFE8;
      padding: 10px;
      box-sizing: border-box;
    }

    .search-box button {
      background: #2DC653;
      border: none;
      padding: 12px 20px;
      cursor: pointer;
      border-radius: 5px;
      color: white;
      font-size: 18px;
      transition: background 0.3s;
      flex-shrink: 0;
    }

    .search-box button:hover {
      background: #25a049;
    }

    /* Categories */
    .categories {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 40px;
      max-width: 1000px;
      margin: 50px auto;
      padding: 0 20px;
      box-sizing: border-box;
    }

    .category-box {
      background: #d8ffda;
      padding: 25px;
      text-align: center;
      border-radius: 15px;
      transition: transform 0.3s, box-shadow 0.3s;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      box-sizing: border-box;
    }

    .category-box:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }

    .category-box img {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      border: 5px solid #ffffff;
    }

    .category-box h3 {
      margin-top: 20px;
      font-size: 22px;
      color: #2e7d32;
    }

    .category-box button {
      margin-top: 10px;
      padding: 10px 20px;
      background: #2DC653;
      color: #ffffff;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 500;
      transition: all 0.3s;
    }

    .category-box button:hover {
      background: #25a049;
      transform: translateY(-2px);
    }

    /* Footer */
    footer {
      background: #212529;
      color: #ffffff;
      display: flex;
      justify-content: space-between;
      padding: 40px;
      margin-top: 60px;
      flex-wrap: wrap;
      box-sizing: border-box;
    }

    footer h4 {
      margin-bottom: 10px;
      font-size: 18px;
      font-weight: 600;
    }

    footer p, footer a {
      font-size: 14px;
      color: #ffffff;
      text-decoration: none;
      line-height: 1.6;
    }

    footer a:hover {
      text-decoration: underline;
    }

    /* User Type Cards
    .user-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 30px;
      max-width: 1000px;
      margin: 50px auto;
      padding: 0 20px;
      box-sizing: border-box;
    }

    .user-card {
      background: #f8fff9;
      padding: 30px;
      text-align: center;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s;
      box-sizing: border-box;
    }

    .user-card:hover {
      transform: translateY(-5px);
    }

    .user-card h3 {
      color: #2DC653;
      margin-bottom: 15px;
    }

    .user-card p {
      color: #666;
      margin-bottom: 20px;
    }

    .user-card .btn {
      padding: 10px 25px;
      background: #2DC653;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: background 0.3s;
    }

    .user-card .btn:hover {
      background: #25a049;
    } */

    /* Mobile Responsive */
    @media (max-width: 1024px) {
      .navbar {
        padding: 15px 20px;
      }
      
      .nav-links {
        gap: 15px;
      }
      
      .logo {
        margin-right: 25px;
        font-size: 24px;
      }
      
      .auth-buttons {
        gap: 10px;
      }
      
      .dropbtn {
        padding: 8px 12px;
      }
    }

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

      .hero {
        height: 400px;
        padding-left: 20px;
        justify-content: center;
        text-align: center;
      }

      .hero-text {
        width: 90%;
        padding: 15px;
      }

      .hero-text h1 {
        font-size: 40px;
      }

      .categories, .user-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        padding: 0 20px;
      }

      footer {
        flex-direction: column;
        gap: 30px;
        text-align: center;
      }
      
      .dropdown-menu {
        right: auto;
        left: 50%;
        transform: translateX(-50%);
      }
    }

    @media (max-width: 480px) {
      .categories, .user-cards {
        grid-template-columns: 1fr;
      }

      .hero-text h1 {
        font-size: 32px;
      }

      .auth-buttons {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
      }
      
      .nav-links {
        font-size: 14px;
        gap: 10px;
      }
      
      .nav-links a {
        font-size: 14px;
      }
      
      .dropbtn {
        font-size: 14px;
        padding: 6px 10px;
      }
      
      .logo {
        font-size: 20px;
      }
      
      .logo-icon {
        width: 30px;
        height: 30px;
      }
    }
    
    @media (max-width: 360px) {
      .auth-buttons {
        flex-direction: column;
      }
      
      .dropdown-menu {
        min-width: 200px;
      }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
  <div class="logo">
    <img src="images/cart.png" alt="AgroTradeHub Logo" class="logo-icon">
    AgroTradeHub
  </div>
  
  <div class="nav-links">
    <a href="index.php" class="active">Home</a>
    <a href="products.php">Products</a>
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
            <a href="orders.php" class="dropdown-item">My Orders</a>
          <?php elseif($_SESSION['user_type'] == 'seller'): ?>
            <a href="addproducts.php" class="dropdown-item">Add Products</a>
          <?php elseif($_SESSION['user_type'] == 'admin'): ?>
            <a href="manage.php" class="dropdown-item">Admin Panel</a>
          <?php endif; ?>
          <a href="logout.php" class="dropdown-item">Logout</a>
        </div>
      </div>
    <?php else: ?>
      <!-- Improved Login Dropdown -->
      <div class="dropdown">
        <button class="dropbtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
          </svg>
          Login
        </button>
        <div class="dropdown-menu" id="loginDropdownMenu">
          <div class="dropdown-header">Quick Login</div>
          <a class="dropdown-item" href="login.php?type=customer">
            <div class="dropdown-item-content">
              <strong>Customer Login</strong>
              <!-- <small class="dropdown-item-desc">Buy fresh farm products</small> -->
            </div>
          </a>
          <a class="dropdown-item" href="login.php?type=seller">
            <div class="dropdown-item-content">
              <strong>Seller Login</strong>
              <!-- <small class="dropdown-item-desc">Sell products & view analytics</small> -->
            </div>
          </a>
          <a class="dropdown-item" href="login.php?type=admin">
            <div class="dropdown-item-content">
              <strong>Admin Login</strong>
              <!-- <small class="dropdown-item-desc">Manage platform & users</small> -->
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <!-- <a class="dropdown-item text-center" href="login.php">
            Go to Login Page
          </a> -->
        </div>
      </div>

      <!-- Improved Register Dropdown -->
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
        <div class="dropdown-menu" id="registerDropdownMenu">
          <div class="dropdown-header">Create Account</div>
          <a class="dropdown-item" href="register.php?type=customer">
            <div class="dropdown-item-content">
              <strong>Customer Account</strong>
              <!-- <small class="dropdown-item-desc">Buy fresh products directly from farmers</small> -->
            </div>
          </a>
          <a class="dropdown-item" href="register.php?type=seller">
            <div class="dropdown-item-content">
              <strong>Seller Account</strong>
              <!-- <small class="dropdown-item-desc">Sell your farm products directly to customers</small> -->
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-center" href="register.php">
            <!-- Go to Registration Page -->
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Hero Section -->
<section class="hero">
  <div class="hero-text">
    <h1>Farm Fresh<br>Products</h1>
    <p>Buy vegetables, grains, fruits, fish, meat, dairy directly from trusted farmers. Fresh from farm to your table.</p>
    <a href="products.php"><button>Shop Now</button></a>
  </div>
</section>

<!-- Search Bar -->
<form action="products.php" method="GET" class="search-box">
  <input type="text" name="search" placeholder="Search Products...">
  <button type="submit">🔍</button>
</form>

<!-- Categories -->
<section class="categories">
  <?php
  $categories = [
    ['name' => 'Vegetables', 'type' => 'vegetable', 'image' => 'images/vegetable.webp'],
    ['name' => 'Grains', 'type' => 'grain', 'image' => 'images/grain.png'],
    ['name' => 'Fruits', 'type' => 'fruit', 'image' => 'images/fruit.jpg'],
    ['name' => 'Fish', 'type' => 'fish', 'image' => 'images/fish.webp'],
    ['name' => 'Meat', 'type' => 'meat', 'image' => 'images/meat.png'],
    ['name' => 'Dairy', 'type' => 'dairy', 'image' => 'images/dairy.jpg']
  ];
  
  foreach ($categories as $category) {
    echo '
    <div class="category-box">
      <img src="' . $category['image'] . '" alt="' . $category['name'] . '">
      <h3>' . $category['name'] . '</h3>
      <a href="products.php?type=' . $category['type'] . '"><button>Browse</button></a>
    </div>';
  }
  ?>
</section>

<!-- User Type Cards -->
<!-- <section class="user-cards">
  <div class="user-card">
    <h3>Customer</h3>
    <p>Buy fresh products directly from farmers at best prices</p>
    <?php if(!isset($_SESSION['user_id'])): ?>
      <a href="register.php?type=customer" class="btn">Join as Customer</a>
    <?php else: ?>
      <span class="btn" style="background: #6c757d;">Already Registered</span>
    <?php endif; ?>
  </div>
  
  <div class="user-card">
    <h3>Seller</h3>
    <p>Sell your farm products directly to customers</p>
    <?php if(!isset($_SESSION['user_id'])): ?>
      <a href="register.php?type=seller" class="btn">Join as Seller</a>
    <?php else: ?>
      <span class="btn" style="background: #6c757d;">Already Registered</span>
    <?php endif; ?>
  </div>
  
  <div class="user-card">
    <h3>Admin</h3>
    <p>Manage the platform and monitor activities</p>
    <?php if(!isset($_SESSION['user_id'])): ?>
      <a href="login.php" class="btn">Admin Login</a>
    <?php else: ?>
      <span class="btn" style="background: #6c757d;">Already Logged In</span>
    <?php endif; ?>
  </div>
</section> -->

<!-- Footer -->
<footer>
  <div>
    <h4>AgroTradeHub</h4>
    <p>Connecting farmers directly with customers for fresh farm products.</p>
  </div>

  <div>
    <h4>Quick Links</h4>
    <a href="index.php">Home</a><br>
    <a href="products.php">Products</a><br>
    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
      <a href="cart.php">Cart</a><br>
      <a href="orders.php">My Orders</a>
    <?php endif; ?>
  </div>

  <div>
    <h4>Contact</h4>
    <p>Email: info@agrotradehub.com</p>
    <p>Phone: +1 234 567 890</p>
  </div>
</footer>

<script>
  // Add interactivity to dropdowns
  document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const navLinks = document.querySelector('.nav-links');
    const authButtons = document.querySelector('.auth-buttons');
    
    // Auto-fill demo credentials if coming from dropdown
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('demo')) {
      setTimeout(() => {
        alert('Try login with: ' + urlParams.get('demo') + '\nPassword: ' + urlParams.get('demo') + '123');
      }, 500);
    }
    
    // Add active class to current page
    const currentPage = window.location.pathname.split('/').pop();
    const links = document.querySelectorAll('.nav-links a');
    links.forEach(link => {
      if (link.getAttribute('href') === currentPage) {
        link.classList.add('active');
      }
    });
    
    // Category images fallback
    const categoryImages = document.querySelectorAll('.category-box img');
    categoryImages.forEach(img => {
      img.onerror = function() {
        this.src = 'https://via.placeholder.com/150/2DC653/FFFFFF?text=' + this.alt.charAt(0);
      };
    });
  });
</script>

</body>
</html>