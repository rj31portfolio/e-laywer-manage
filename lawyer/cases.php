 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('lawyer');
$pageTitle = "My Cases";

// Get all cases for the lawyer
$stmt = $pdo->prepare("
    SELECT e.id, e.subject, e.description, e.status, e.created_at,
           CONCAT(ud.first_name, ' ', ud.last_name) as client_name,
           c.name as category, a.id as assignment_id,
           p.status as payment_status
    FROM assignments a
    JOIN enquiries e ON a.enquiry_id = e.id
    JOIN users u ON e.client_id = u.id
    JOIN user_details ud ON u.id = ud.user_id
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN payments p ON a.id = p.assignment_id
    WHERE a.lawyer_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$auth->getUserId()]);
$cases = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">My Cases</h6>
    </div>
    <div class="card-body">
        <?php if (count($cases) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Client</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($case['subject']); ?></td>
                                <td><?php echo htmlspecialchars($case['client_name']); ?></td>
                                <td><?php echo htmlspecialchars($case['category']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $case['status'] === 'completed' ? 'success' : 
                                             ($case['status'] === 'in_progress' ? 'primary' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($case['payment_status'] === 'completed'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($case['payment_status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($case['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo SITE_URL; ?>/lawyer/case.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">You don't have any cases yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
