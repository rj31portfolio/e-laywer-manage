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
    $razorpayPaymentId = $_POST['razorpay_payment_id'] ?? null;
    $razorpayOrderId = $_POST['razorpay_order_id'] ?? null;
    $razorpaySignature = $_POST['razorpay_signature'] ?? null;
    $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    
    if (!$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature || !$assignmentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }
    
    try {
        // Get payment record
        $stmt = $pdo->prepare("
            SELECT p.id, p.amount, p.razorpay_order_id, a.enquiry_id, a.lawyer_id
            FROM payments p
            JOIN assignments a ON p.assignment_id = a.id
            WHERE p.assignment_id = ? AND p.status = 'pending'
        ");
        $stmt->execute([$assignmentId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Payment record not found or already processed']);
            exit;
        }
        
        // Verify signature
        $generatedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            RAZORPAY_KEY_SECRET
        );
        
        if ($generatedSignature !== $razorpaySignature) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
        
        // Initialize Razorpay API
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        
        // Fetch payment from Razorpay
        $razorpayPayment = $api->payment->fetch($razorpayPaymentId);
        
        // Verify payment
        if ($razorpayPayment->order_id !== $payment['razorpay_order_id'] || 
            $razorpayPayment->amount !== ($payment['amount'] * 100)) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment verification failed']);
            exit;
        }
        
        // Update payment status in database
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'completed', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$razorpayPaymentId, $razorpaySignature, $payment['id']]);
        
        // Update enquiry status
        $stmt = $pdo->prepare("UPDATE enquiries SET status = 'in_progress' WHERE id = ?");
        $stmt->execute([$payment['enquiry_id']]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
