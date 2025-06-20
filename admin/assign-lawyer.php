<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "Assign Lawyer";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid enquiry ID.");
}

$enquiryId = $_GET['id'];

// Get enquiry details
$stmt = $pdo->prepare("
    SELECT e.*, CONCAT(ud.first_name, ' ', ud.last_name) as client_name, c.name as category_name
    FROM enquiries e
    JOIN users u ON e.client_id = u.id
    JOIN user_details ud ON u.id = ud.user_id
    JOIN categories c ON e.category_id = c.id
    WHERE e.id = ?
");
$stmt->execute([$enquiryId]);
$enquiry = $stmt->fetch();

if (!$enquiry) {
    die("Enquiry not found.");
}

// Get active lawyers in the same category
$stmt = $pdo->prepare("
    SELECT u.id, CONCAT(ud.first_name, ' ', ud.last_name) as name, l.consultation_fee
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    JOIN lawyers l ON u.id = l.user_id
    WHERE u.role = 'lawyer' AND u.status = 1 AND l.category_id = ?
");
$stmt->execute([$enquiry['category_id']]);
$lawyers = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lawyerId = isset($_POST['lawyer_id']) ? trim($_POST['lawyer_id']) : '';

    if (empty($lawyerId) || !is_numeric($lawyerId)) {
        $error = 'Please select a valid lawyer.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert assignment
            $stmt = $pdo->prepare("
                INSERT INTO assignments (enquiry_id, lawyer_id, assigned_by, status)
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->execute([$enquiryId, $lawyerId, $auth->getUserId()]);

            // Update enquiry status
            $stmt = $pdo->prepare("UPDATE enquiries SET status = 'assigned' WHERE id = ?");
            $stmt->execute([$enquiryId]);

            $pdo->commit();
            $success = "Lawyer assigned successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to assign lawyer: ' . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="card-title mb-4">Assign Lawyer</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <div class="text-center mt-3">
                        <a href="<?php echo SITE_URL; ?>/admin/enquiries.php" class="btn btn-primary">Back to Enquiries</a>
                    </div>
                <?php else: ?>
                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($enquiry['subject']); ?></p>
                    <p><strong>Client:</strong> <?php echo htmlspecialchars($enquiry['client_name']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($enquiry['category_name']); ?></p>
                    <p><strong>Budget:</strong> ₹<?php echo htmlspecialchars($enquiry['budget']); ?></p>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="lawyer_id" class="form-label">Select Lawyer</label>
                            <select name="lawyer_id" id="lawyer_id" class="form-select" required>
                                <option value="">-- Choose a lawyer --</option>
                                <?php foreach ($lawyers as $lawyer): ?>
                                    <option value="<?php echo $lawyer['id']; ?>">
                                        <?php echo htmlspecialchars($lawyer['name']); ?> - ₹<?php echo number_format($lawyer['consultation_fee'], 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success">Assign Lawyer</button>
                        <a href="<?php echo SITE_URL; ?>/admin/enquiries.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
