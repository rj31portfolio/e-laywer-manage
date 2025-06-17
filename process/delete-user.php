<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn() || !$auth->getUserRole() === 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // Check if user is superadmin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['role'] === 'superadmin') {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete superadmin']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete user details
    $stmt = $pdo->prepare("DELETE FROM user_details WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // If user is lawyer, delete from lawyers table
    $stmt = $pdo->prepare("DELETE FROM lawyers WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>