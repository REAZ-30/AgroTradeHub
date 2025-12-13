<?php
session_start();

// Database connection function
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
        return null;
    }
}

$error = '';

// Get user type from URL or default to customer
$user_type = isset($_GET['type']) ? $_GET['type'] : 'customer';
$user_type = isset($_GET['type']);
$user_type_display = ucfirst($user_type);

if($_POST){
    $username = $_POST['username'];
    $password = $_POST['password'];
    
        
    // First try database login
    $db = getDBConnection();
    if($db) {
        try {
            $query = "SELECT * FROM users WHERE username = ? OR email = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username, $username]);
            
            if($stmt->rowCount() == 1){
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
               if($password === $user['password']){
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Redirect based on user type
                    if($user['user_type'] == 'admin'){
                        header("Location: index.php");
                    } elseif($user['user_type'] == 'seller'){
                        header("Location: index.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                } else {
                    $error = "Invalid password!";
                }
            } else {
                // User not found in database, check demo accounts
                if(isset($demo_users[$username]) && $password === $demo_users[$username]['password']) {
                    $_SESSION['user_id'] = rand(1000, 9999);
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = $demo_users[$username]['user_type'];
                    $_SESSION['full_name'] = $demo_users[$username]['full_name'];
                    $_SESSION['email'] = $username . '@agrotradehub.com';
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "User not found!";
                }
            }
        } catch(PDOException $e) {
            // If database error, fallback to demo accounts
            if(isset($demo_users[$username]) && $password === $demo_users[$username]['password']) {
                $_SESSION['user_id'] = rand(1000, 9999);
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $demo_users[$username]['user_type'];
                $_SESSION['full_name'] = $demo_users[$username]['full_name'];
                $_SESSION['email'] = $username . '@agrotradehub.com';
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        }
    } else {
        // Database not connected, use demo accounts
        if(isset($demo_users[$username]) && $password === $demo_users[$username]['password']) {
            $_SESSION['user_id'] = rand(1000, 9999);
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $demo_users[$username]['user_type'];
            $_SESSION['full_name'] = $demo_users[$username]['full_name'];
            $_SESSION['email'] = $username . '@agrotradehub.com';
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    }
}

// Check database connection status
$db_connected = getDBConnection() !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AgroTradeHub</title>
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
        
        /* Custom Navbar Styles - Same as index.php */
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
        
        /* Auth buttons - Now contains both Home and Register */
        .auth-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-shrink: 0;
        }
        
        /* Home button - Moved to auth-buttons near Register */
        .btn-home {
            padding: 8px 20px;
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
        
        /* Register button */
        .btn-register {
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
        
        .btn-register:hover {
            background: #25a049;
            color: #ffffff;
            transform: translateY(-2px);
        }
        
        /* Login Container */
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .login-card {
            width: 100%;
            max-width: 400px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: #2DC653;
            color: #000000;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        
        .user-type-badge {
            background: rgba(255, 255, 255, 0.3);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 10px;
            font-weight: 500;
        }

        .login-body {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
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
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
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
        
        /* .user-type-links {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .user-type-links a {
            color: #2DC653;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .user-type-links a:hover {
            text-decoration: underline;
        } */
        
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
            
            .login-body {
                padding: 30px 20px;
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
            
            .login-header {
                padding: 20px;
            }
            
            .login-header h2 {
                font-size: 24px;
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
        
        <!-- Home and Register buttons on the right side -->
        <div class="auth-buttons">
            <a href="index.php" class="btn-home">Home</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Login to AgroTradeHub</h2>
                <div class="user-type-badge">
                    Login as <?php echo $user_type_display; ?>
                </div>
            </div>
            
            <div class="login-body">
                
                <?php if($error): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter username or email" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                    
                    <button type="submit" class="btn-submit">Login</button>
                </form>
                
                <div class="register-link">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
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

</body>
</html>