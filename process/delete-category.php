<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn() || !in_array($auth->getUserRole(), ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$categoryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$categoryId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category ID']);
    exit;
}

try {
    // Check if category is being used by any lawyers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lawyers WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Category is in use by lawyers']);
        exit;
    }
    
    // Check if category is being used by any enquiries
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enquiries WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Category is in use by enquiries']);
        exit;
    }
    
    // Delete category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>