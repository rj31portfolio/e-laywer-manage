<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!$auth->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include Razorpay PHP library
require_once '../assets/vendor/razorpay/razorpay-php/Razorpay.php';
use Razorpay\Api\Api;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    
    if (!$assignmentId || !$amount) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }
    
    try {
        // Initialize Razorpay API
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        
        // Create order
        $orderData = [
            'receipt' => 'order_rcpt_' . $assignmentId,
            'amount' => $amount * 100, // Convert to paise
            'currency' => 'INR',
            'payment_capture' => 1
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        
        // Save payment record in database
        $stmt = $pdo->prepare("INSERT INTO payments (assignment_id, amount, razorpay_order_id, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$assignmentId, $amount, $razorpayOrder->id]);
        
        // Return order details to client
        echo json_encode([
            'success' => true,
            'order_id' => $razorpayOrder->id,
            'amount' => $amount,
            'key' => RAZORPAY_KEY_ID,
            'name' => 'LegalConnect',
            'description' => 'Legal Consultation Payment',
            'prefill' => [
                'name' => $_SESSION['email'],
                'email' => $_SESSION['email']
            ]
        ]);
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
}
?>