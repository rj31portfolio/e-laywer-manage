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
    
    if (!$assignmentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid assignment ID']);
        exit;
    }
    
    try {
        // Get assignment details
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
        
        // Check if client owns this assignment
        if ($assignment['client_id'] != $auth->getUserId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        
        // Check if payment already exists
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE assignment_id = ? AND status = 'completed'");
        $stmt->execute([$assignmentId]);
        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment already completed for this assignment']);
            exit;
        }
        
        // Initialize Razorpay API
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        
        // Create order
        $orderData = [
            'receipt' => 'order_rcpt_' . $assignmentId,
            'amount' => $assignment['consultation_fee'] * 100, // Convert to paise
            'currency' => 'INR',
            'payment_capture' => 1
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        
        // Save payment record in database
        $stmt = $pdo->prepare("
            INSERT INTO payments (assignment_id, amount, razorpay_order_id, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $assignmentId,
            $assignment['consultation_fee'],
            $razorpayOrder->id
        ]);
        
        // Return order details to client
        echo json_encode([
            'success' => true,
            'order_id' => $razorpayOrder->id,
            'amount' => $assignment['consultation_fee'],
            'key' => RAZORPAY_KEY_ID,
            'name' => SITE_NAME,
            'description' => 'Legal Consultation Payment',
            'prefill' => [
                'name' => $_SESSION['email'],
                'email' => $_SESSION['email']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>

