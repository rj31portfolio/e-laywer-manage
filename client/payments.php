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
    $input = json_decode(file_get_contents('php://input'), true);
    $assignmentId = filter_var($input['assignment_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$assignmentId || $assignmentId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid assignment ID']);
        exit;
    }
    
    try {
        // Get assignment details with additional validation
        $stmt = $pdo->prepare("
            SELECT a.id, e.client_id, l.consultation_fee, l.user_id as lawyer_id,
                   u.name as client_name, u.email as client_email
            FROM assignments a
            JOIN enquiries e ON a.enquiry_id = e.id
            JOIN lawyers l ON a.lawyer_id = l.user_id
            JOIN users u ON e.client_id = u.id
            WHERE a.id = ? AND a.status = 'active'
        ");
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            http_response_code(404);
            echo json_encode(['error' => 'Assignment not found or inactive']);
            exit;
        }
        
        // Validate client ownership
        if ($assignment['client_id'] != $auth->getUserId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - You do not own this assignment']);
            exit;
        }
        
        // Validate consultation fee
        if ($assignment['consultation_fee'] <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid consultation fee amount']);
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
        
        // Create order with proper metadata
        $orderData = [
            'receipt'         => 'CONSULT_' . $assignmentId . '_' . time(),
            'amount'           => round($assignment['consultation_fee'] * 100), // Convert to paise
            'currency'         => 'INR',
            'payment_capture'  => 1,
            'notes'           => [
                'assignment_id' => $assignmentId,
                'client_id'    => $assignment['client_id'],
                'lawyer_id'     => $assignment['lawyer_id'],
                'purpose'      => 'Legal Consultation Fee'
            ]
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        
        // Save payment record in database
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
            $assignment['consultation_fee'],
            $razorpayOrder->id
        ]);
        
        // Prepare response with necessary payment details
        $response = [
            'success' => true,
            'order_id' => $razorpayOrder->id,
            'amount' => $assignment['consultation_fee'],
            'currency' => 'INR',
            'key' => RAZORPAY_KEY_ID,
            'name' => SITE_NAME,
            'description' => 'Professional Consultation Fee',
            'prefill' => [
                'name' => $assignment['client_name'],
                'email' => $assignment['client_email'],
                'contact' => '' // You can add client phone if available
            ],
            'notes' => [
                'assignment_id' => $assignmentId,
                'client_id' => $assignment['client_id']
            ],
            'theme' => [
                'color' => '#528FF0' // Customize as needed
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log('Payment Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Payment processing failed. Please try again.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>