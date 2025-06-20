<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "View Enquiry";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid enquiry ID.");
}

$enquiryId = $_GET['id'];

// Fetch enquiry details
$stmt = $pdo->prepare("
    SELECT e.*, 
           CONCAT(ud.first_name, ' ', ud.last_name) as client_name,
           c.name as category_name,
           CONCAT(ud2.first_name, ' ', ud2.last_name) as lawyer_name,
           a.status as assignment_status
    FROM enquiries e
    JOIN users u ON e.client_id = u.id
    JOIN user_details ud ON u.id = ud.user_id
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN assignments a ON e.id = a.enquiry_id AND a.status = 'active'
    LEFT JOIN users u2 ON a.lawyer_id = u2.id
    LEFT JOIN user_details ud2 ON u2.id = ud2.user_id
    WHERE e.id = ?
");
$stmt->execute([$enquiryId]);
$enquiry = $stmt->fetch();

if (!$enquiry) {
    die("Enquiry not found.");
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0">Enquiry Details</h5>
            </div>
            <div class="card-body">

                <p><strong>Subject:</strong> <?php echo htmlspecialchars($enquiry['subject']); ?></p>
                <p><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($enquiry['description'])); ?></p>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($enquiry['category_name']); ?></p>
                <p><strong>Budget:</strong> ₹<?php echo $enquiry['budget'] ? number_format($enquiry['budget'], 2) : 'Not specified'; ?></p>
                <p><strong>Status:</strong> 
                    <span class="badge bg-<?php 
                        echo $enquiry['status'] === 'completed' ? 'success' : 
                             ($enquiry['status'] === 'assigned' ? 'primary' : 'warning'); 
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $enquiry['status'])); ?>
                    </span>
                </p>
                <p><strong>Submitted on:</strong> <?php echo date('d M Y, h:i A', strtotime($enquiry['created_at'])); ?></p>

                <hr>

                <h6>Client Info</h6>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($enquiry['client_name']); ?></p>

                <hr>

                <h6>Assigned Lawyer</h6>
                <p>
                    <?php if ($enquiry['lawyer_name']): ?>
                        <?php echo htmlspecialchars($enquiry['lawyer_name']); ?>
                    <?php else: ?>
                        <span class="text-muted">Not assigned yet</span>
                    <?php endif; ?>
                </p>

                <div class="mt-3">
                    <a href="enquiries.php" class="btn btn-secondary">Back to Enquiries</a>
                    <?php if ($enquiry['status'] === 'pending'): ?>
                        <a href="assign-lawyer.php?id=<?php echo $enquiry['id']; ?>" class="btn btn-success">Assign Lawyer</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
