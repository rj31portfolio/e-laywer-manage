<?php
require_once '../includes/config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $lawyerId = (int) $_POST['id'];

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Delete related lawyer record
        $stmt = $pdo->prepare("DELETE FROM lawyers WHERE user_id = ?");
        $stmt->execute([$lawyerId]);

        // Delete related user_details record
        $stmt = $pdo->prepare("DELETE FROM user_details WHERE user_id = ?");
        $stmt->execute([$lawyerId]);

        // Delete main user record
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$lawyerId]);

        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
