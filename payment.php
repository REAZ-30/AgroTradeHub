<?php
/* =====================================================
   PAYMENT CONTROLLER – SSLCommerz (Sandbox)
   Project: AgroTradeHub
   Hosting: InfinityFree
===================================================== */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'database.php';

$db   = new Database();
$conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? '';

/* =====================================================
   ACTION: PAY
   - Create order
   - Insert order items
   - Save order number in session
   - Redirect to SSLCommerz
===================================================== */
if ($action === 'pay') {

    if (empty($_SESSION['cart'])) {
        die('Cart is empty.');
    }

    // Data from checkout.php
    $customer_id = $_POST['customer_id'];
    $total       = $_POST['total_amount'];
    $address     = $_POST['address'];
    $phone       = $_POST['contact_phone'];

    // Generate unique order number
    $order_number = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);

    try {
        $conn->beginTransaction();

        /* ---------- INSERT ORDER ---------- */
        $stmt = $conn->prepare(
            "INSERT INTO orders
            (customer_id, total_amount, status, shipping_address, order_number,
             payment_method, payment_status, payment_mobile, customer_phone)
            VALUES (?, ?, 'pending', ?, ?, 'sslcommerz', 'pending', ?, ?)"
        );

        $stmt->execute([
            $customer_id,
            $total,
            $address,
            $order_number,
            $phone, // payment_mobile
            $phone  // customer_phone
        ]);

        $order_id = $conn->lastInsertId();

        /* ---------- INSERT ORDER ITEMS ---------- */
        foreach ($_SESSION['cart'] as $product_id => $item) {

            $seller_id = null;
            $p = $conn->prepare("SELECT seller_id FROM products WHERE id = ? LIMIT 1");
            $p->execute([$product_id]);
            $row = $p->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $seller_id = $row['seller_id'];
            }

            $item_stmt = $conn->prepare(
                "INSERT INTO order_items
                 (order_id, product_id, seller_id, quantity, price)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $item_stmt->execute([
                $order_id,
                $product_id,
                $seller_id,
                $item['quantity'],
                $item['price']
            ]);
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        die('Order creation failed: ' . $e->getMessage());
    }

    // Store order number in session (IMPORTANT for free hosting)
    $_SESSION['last_order_number'] = $order_number;

    /* ---------- SSLCommerz REQUEST ---------- */
    $post_data = [
        'store_id'     => 'agrot693d73d2e099d',
        'store_passwd' => 'agrot693d73d2e099d@ssl',
        'total_amount' => $total,
        'currency'     => 'BDT',
        'tran_id'      => $order_number,

        'success_url' => 'https://agrotradehub.free.nf/payment.php?action=success',
        'fail_url'    => 'https://agrotradehub.free.nf/payment.php?action=fail',
        'cancel_url'  => 'https://agrotradehub.free.nf/payment.php?action=cancel',

        'cus_name'  => 'Customer',
        'cus_phone' => $phone,
        'cus_email' => 'demo@email.com',

        'product_name'     => 'Agro Products',
        'product_category' => 'Agro',
        'product_profile'  => 'general'
    ];

    $ch = curl_init('https://sandbox.sslcommerz.com/gwprocess/v3/api.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));

    // Required for free hosting
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);

    if ($result === false) {
        die('cURL Error: ' . curl_error($ch));
    }

    $response = json_decode($result, true);
    curl_close($ch);

    if (!isset($response['GatewayPageURL'])) {
        echo "<pre>";
        print_r($response);
        exit;
    }

    header("Location: " . $response['GatewayPageURL']);
    exit;
}

/* =====================================================
   ACTION: SUCCESS
   - Mark order as paid
   - Use session order number (safe)
===================================================== */
if ($action === 'success') {

    $order_number = $_SESSION['last_order_number'] ?? null;
    $val_id       = $_POST['val_id'] ?? 'SANDBOX-SIMULATED';

    if (!$order_number) {
        die('Invalid payment response.');
    }

    $stmt = $conn->prepare(
        "UPDATE orders
         SET payment_status = 'paid',
             status = 'pending',
             payment_transaction_id = ?
         WHERE order_number = ?"
    );

    $stmt->execute([$val_id, $order_number]);

    unset($_SESSION['last_order_number']);
    $_SESSION['cart'] = [];

    header('Location: orders.php');
    exit;
}

/* =====================================================
   ACTION: FAIL
===================================================== */
if ($action === 'fail') {

    if (isset($_SESSION['last_order_number'])) {
        $stmt = $conn->prepare(
            "UPDATE orders SET payment_status = 'failed' WHERE order_number = ?"
        );
        $stmt->execute([$_SESSION['last_order_number']]);
        unset($_SESSION['last_order_number']);
    }

    header('Location: checkout.php');
    exit;
}

/* =====================================================
   ACTION: CANCEL
===================================================== */
if ($action === 'cancel') {

    unset($_SESSION['last_order_number']);
    header('Location: checkout.php');
    exit;
}
