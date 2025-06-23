<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if the request is POST and user is authenticated
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Validate input
if (!isset($_POST['assignment_id']) || !is_numeric($_POST['assignment_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment ID']);
    exit;
}

$assignmentId = (int)$_POST['assignment_id'];
$userId = $auth->getUserId();

try {
    // Verify the assignment belongs to the client
    $stmt = $pdo->prepare("
        SELECT a.amount, a.status, e.client_id
        FROM assignments a
        JOIN enquiries e ON a.enquiry_id = e.id
        WHERE a.id = ? AND e.client_id = ? AND a.status = 'active'
    ");
    $stmt->execute([$assignmentId, $userId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        echo json_encode(['success' => false, 'error' => 'Assignment not found or not authorized']);
        exit;
    }

    // Create Razorpay order
    $amount = (float)$assignment['amount'];
    $currency = 'INR';
    $receiptId = 'LC_' . $assignmentId . '_' . time();

    $razorpay = new Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $order = $razorpay->order->create([
        'receipt' => $receiptId,
        'amount' => $amount * 100, // Razorpay expects amount in paise
        'currency' => $currency,
        'payment_capture' => 1 // auto-capture payment
    ]);

    // Log the payment attempt
    $stmt = $pdo->prepare("
        INSERT INTO payment_attempts 
        (assignment_id, amount, currency, razorpay_order_id, status, created_at)
        VALUES (?, ?, ?, ?, 'created', NOW())
    ");
    $stmt->execute([
        $assignmentId,
        $amount,
        $currency,
        $order->id
    ]);

    echo json_encode([
        'success' => true,
        'amount' => $amount,
        'order_id' => $order->id,
        'currency' => $currency
    ]);

} catch (Exception $e) {
    error_log("Payment details error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create payment order. Please try again.'
    ]);
}