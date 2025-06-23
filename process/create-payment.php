<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
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
        http_response_code(400);
        echo json_encode(['error' => 'Invalid assignment ID or amount']);
        exit;
    }
    
    try {
        // Verify assignment belongs to logged in client
        $stmt = $pdo->prepare("
            SELECT a.id, e.client_id, l.consultation_fee, l.user_id as lawyer_id
            FROM assignments a
            JOIN enquiries e ON a.enquiry_id = e.id
            JOIN lawyers l ON a.lawyer_id = l.user_id
            WHERE a.id = ? AND a.status = 'active'
        ");
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            http_response_code(404);
            echo json_encode(['error' => 'Assignment not found or inactive']);
            exit;
        }
        
        // Verify client owns this assignment
        if ($assignment['client_id'] != $auth->getUserId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - not your assignment']);
            exit;
        }
        
        // Verify amount matches consultation fee (with small tolerance for rounding)
        if (abs($amount - $assignment['consultation_fee']) > 0.01) {
            http_response_code(400);
            echo json_encode(['error' => 'Amount does not match consultation fee']);
            exit;
        }
        
        // Check for existing completed payment
        $stmt = $pdo->prepare("
            SELECT id FROM payments 
            WHERE assignment_id = ? AND status = 'completed'
        ");
        $stmt->execute([$assignmentId]);
        
        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment already completed for this assignment']);
            exit;
        }
        
        // Initialize Razorpay API
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        
        // Create order with 5 minute timeout (300 seconds)
        $orderData = [
            'receipt'         => 'order_rcpt_' . $assignmentId,
            'amount'          => round($amount * 100), // Convert to paise
            'currency'        => 'INR',
            'payment_capture' => 1,
            'notes'          => [
                'assignment_id' => $assignmentId,
                'client_id'    => $auth->getUserId(),
                'lawyer_id'   => $assignment['lawyer_id']
            ]
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        
        // Create payment record in database
        $stmt = $pdo->prepare("
            INSERT INTO payments (
                assignment_id, 
                amount, 
                razorpay_order_id, 
                status,
                created_at
            ) VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $assignmentId,
            $amount,
            $razorpayOrder->id
        ]);
        
        // Return order details to client
        echo json_encode([
            'success'  => true,
            'order_id' => $razorpayOrder->id,
            'amount'   => $amount,
            'currency' => 'INR'
        ]);
        
    } catch (Exception $e) {
        error_log("Payment creation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Payment initialization failed',
            'debug' => (ENVIRONMENT === 'development') ? $e->getMessage() : null
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>