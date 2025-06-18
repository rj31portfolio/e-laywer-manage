<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Authenticate as Admin
$auth->requireRole('admin');

// Get payment ID from request
$paymentId = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;

if ($paymentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

try {
    // Verify the payment exists and is pending
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ? AND status = 'pending'");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found or already processed']);
        exit;
    }
    
    // Update payment status
    $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$paymentId]);
    
    // Update related assignment status if needed
    $stmt = $pdo->prepare("
        UPDATE assignments a
        JOIN payments p ON a.id = p.assignment_id
        SET a.status = 'active'
        WHERE p.id = ?
    ");
    $stmt->execute([$paymentId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}