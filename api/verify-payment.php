<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include Razorpay PHP library
require_once '../../assets/vendor/razorpay/razorpay-php/Razorpay.php';
use Razorpay\Api\Api;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $razorpayPaymentId = $input['razorpay_payment_id'] ?? null;
    $razorpayOrderId = $input['razorpay_order_id'] ?? null;
    $razorpaySignature = $input['razorpay_signature'] ?? null;
    $assignmentId = filter_var($input['assignment_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing payment verification data']);
        exit;
    }
    
    if (!$assignmentId || $assignmentId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid assignment ID']);
        exit;
    }

    try {
        // Verify assignment belongs to this client
        $stmt = $pdo->prepare("
            SELECT a.id, e.client_id
            FROM assignments a
            JOIN enquiries e ON a.enquiry_id = e.id
            WHERE a.id = ? AND a.status = 'active'
        ");
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            http_response_code(404);
            echo json_encode(['error' => 'Assignment not found or inactive']);
            exit;
        }
        
        if ($assignment['client_id'] != $auth->getUserId()) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to verify this payment']);
            exit;
        }
        
        // Get payment record
        $stmt = $pdo->prepare("
            SELECT id, amount, razorpay_order_id
            FROM payments
            WHERE assignment_id = ? AND razorpay_order_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$assignmentId, $razorpayOrderId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Payment record not found']);
            exit;
        }
        
        // Verify payment signature
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        
        $attributes = [
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_signature' => $razorpaySignature
        ];
        
        $api->utility->verifyPaymentSignature($attributes);
        
        // Update payment record
        $stmt = $pdo->prepare("
            UPDATE payments SET
                razorpay_payment_id = ?,
                razorpay_signature = ?,
                status = 'completed',
                paid_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $razorpayPaymentId,
            $razorpaySignature,
            $payment['id']
        ]);
        
        // Update assignment status if needed
        $stmt = $pdo->prepare("
            UPDATE assignments SET
                status = 'in_progress'
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$assignmentId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and recorded successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('Payment Verification Error: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'Payment verification failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>