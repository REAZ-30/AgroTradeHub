<?php
session_start();

// Database connection function
function getDBConnection() {
    $host = "sql209.infinityfree.com";
    $dbname = "if0_40668683_db";
    $username = "if0_40668683";
    $password = "JSgSGDQAS52E3N";
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        return null;
    }
}

$error = '';
$success = '';
$show_form = true; // Control form visibility

if($_POST && isset($_POST['username'])){
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type'] ?? 'customer';
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    $db = getDBConnection();
    
    if($db) {
        try {
            // Check if username or email already exists
            $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$username, $email]);
            
            if($checkStmt->rowCount() > 0) {
                $error = "Username or Email already exists! Please choose different ones.";
            } else {
                // Hash the password
               $hashed_password = $password; // Store plain text
                // Insert into database
                $insertQuery = "INSERT INTO users (username, email, password, user_type, full_name, phone, address, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $insertStmt = $db->prepare($insertQuery);
                
                if($insertStmt->execute([$username, $email, $hashed_password, $user_type, $full_name, $phone, $address])) {
                    // Get the last inserted ID
                    $user_id = $db->lastInsertId();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    
                    $success = "Registration successful! Welcome to AgroTradeHub.";
                    $show_form = false; // Hide form after success
                    
                    // Redirect after 3 seconds
                    header("refresh:3;url=index.php");
                } else {
                    $error = "Registration failed! Please try again.";
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Database connection failed! Please check if MySQL is running.";
    }
}

// Get user type from URL or default to customer
$user_type = isset($_GET['type']) ? $_GET['type'] : 'customer';
$user_type_display = ucfirst($user_type);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AgroTradeHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Custom Navbar Styles - Same as login.php */
        .navbar {
            background: #2DC653;
            padding: 15px 40px;
            display: flex;
            align-items: center;
            color: #000000;
            justify-content: space-between;
            width: 100%;
            box-sizing: border-box;
            flex-shrink: 0;
        }
        
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
            white-space: nowrap;
            text-decoration: none;
            color: #000000;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        /* Navigation links - Removed since we don't need center nav */
        .nav-links {
            display: none; /* Hide the center navigation */
        }
        
        /* Auth buttons - Now contains both Home and Login */
        .auth-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-shrink: 0;
        }
        
        /* Home button */
        .btn-home {
            padding: 8px 20px;
            /* background: #ffffff; */
            color: #000000;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-home:hover {
            background: #25a049;
            color: #ffffff;
            transform: translateY(-2px);
        }
        
        /* Login button */
        .btn-login {
            padding: 8px 20px;
            background: #ffffff;
            color: #000000;
            border: 2px solid #000000;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-login:hover {
            background: #25a049;
            color: #fffffff;
            transform: translateY(-2px);
        }
        
        /* Register Container */
        .register-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .register-card {
            width: 100%;
            max-width: 600px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .register-header {
            background: #2DC653;
            color: #000000;
            padding: 30px;
            text-align: center;
        }
        
        .register-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        
        /* .register-header p {
            margin-top: 10px;
            opacity: 0.9;
        }
         */
        .user-type-badge {
            background: rgba(255, 255, 255, 0.3);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 10px;
            font-weight: 500;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2DC653;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #2DC653;
            color: #000000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-submit:hover {
            background: #25a049;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .success-message {
            background: #E0FFE8;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #2DC653;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .info-box {
            background: #f8fff9;
            padding: 15px;
            border-radius: 8px;
            border: 2px dashed #2DC653;
            margin-bottom: 20px;
        }
        
        .info-box h6 {
            color: #2DC653;
            margin-bottom: 10px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
            box-sizing: border-box;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .register-link a {
            color: #2DC653;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
      
        .terms-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .terms-check input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            accent-color: #2DC653;
        }
        
        .terms-check label {
            font-size: 14px;
            color: #333;
        }
        
        /* Footer */
        footer {
            background: #212529;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            padding: 40px;
            flex-wrap: wrap;
            box-sizing: border-box;
            flex-shrink: 0;
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
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
                flex-direction: row;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .auth-buttons {
                gap: 10px;
            }
            
            .register-body {
                padding: 30px 20px;
            }
            
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            footer {
                flex-direction: column;
                gap: 30px;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .logo {
                justify-content: center;
                width: 100%;
            }
            
            .auth-buttons {
                margin-top: 10px;
                justify-content: center;
            }
            
            .logo-icon {
                width: 30px;
                height: 30px;
            }
            
            .register-header {
                padding: 20px;
            }
            
            .register-header h2 {
                font-size: 24px;
            }
            
            .row {
                margin: 0;
            }
            
            .col-md-6 {
                padding: 0;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Custom Navbar -->
    <div class="navbar">
        <!-- Logo on the left -->
        <div class="logo-container">
            <a href="index.php" class="logo">
                <img src="images/cart.png" alt="AgroTradeHub Logo" class="logo-icon">
                AgroTradeHub
            </a>
        </div>
        
        <!-- Empty center navigation (hidden) -->
        <div class="nav-links"></div>
        
        <!-- Home and Login buttons on the right side -->
        <div class="auth-buttons">
            <a href="index.php" class="btn-home">Home</a>
            <a href="login.php" class="btn-login">Login</a>
        </div>
    </div>

    <!-- Register Container -->
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h2>Join AgroTradeHub</h2>
                
                
            </div>
            
            <div class="register-body">
                <?php if(isset($success) && !empty($success)): ?>
                    <div class="success-message">
                        <h5>🎉 Welcome to AgroTradeHub!</h5>
                        <p><?php echo $success; ?></p>
                        <small>Redirecting to homepage...</small>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error) && !empty($error)): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- REGISTRATION FORM -->
                <?php if($show_form): ?>
                <form method="POST">
                    <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Name <span style="color: red;">*</span></label>
                                <input type="text" name="full_name" class="form-control" 
                                       placeholder="Enter your full name" required
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username <span style="color: red;">*</span></label>
                                <input type="text" name="username" class="form-control" 
                                       placeholder="Choose a username" required
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span style="color: red;">*</span></label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span style="color: red;">*</span></label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Create a strong password" required
                               minlength="6">
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       placeholder="Enter phone number"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" 
                                       placeholder="Enter your address"
                                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Role-specific information -->
                    <?php if($user_type == 'seller'): ?>
                        <div class="info-box">
                            <h6>Seller Information</h6>
                            <small>As a seller, you'll be able to list your farm products and manage your inventory.</small>
                        </div>
                    <?php elseif($user_type == 'admin'): ?>
                        <div class="info-box">
                            <h6>Admin Registration</h6>
                            <small>Admin accounts require verification. You'll be contacted for approval.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="terms-check">
                        <input type="checkbox" id="terms" required>
                        <label for="terms">
                            I agree to the <a href="#" style="color: #2DC653;">Terms of Service</a> and <a href="#" style="color: #2DC653;">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        Create Account as <?php echo $user_type_display; ?>
                    </button>
                </form>
                <?php endif; ?>
                <!-- END REGISTRATION FORM -->
                
                <div class="register-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
                
                
            </div>
        </div>
    </div>

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
            <a href="login.php">Login</a><br>
            <a href="register.php">Register</a>
        </div>
        
        <div>
            <h4>Contact</h4>
            <p>Email: info@agrotradehub.com</p>
            <p>Phone: +1 234 567 890</p>
        </div>
    </footer>

    <script>
        // Password strength indicator (preserved from original)
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.querySelector('input[name="password"]');
            const passwordHelp = document.querySelector('.form-text');
            
            if(passwordInput && passwordHelp) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = '';
                    let color = '';
                    
                    if(password.length === 0) {
                        strength = '';
                    } else if(password.length < 6) {
                        strength = 'Weak';
                        color = 'red';
                    } else if(password.length < 10) {
                        strength = 'Medium';
                        color = 'orange';
                    } else {
                        strength = 'Strong';
                        color = 'green';
                    }
                    
                    if(strength) {
                        passwordHelp.innerHTML = `Password strength: <span style="color: ${color}; font-weight: bold;">${strength}</span>`;
                    } else {
                        passwordHelp.textContent = 'Password must be at least 6 characters long.';
                    }
                });
            }
        });
    </script>
</body>
</html>