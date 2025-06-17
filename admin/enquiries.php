 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "Manage Enquiries";

// Get all enquiries
$enquiries = $pdo->query("
    SELECT e.id, e.subject, e.description, e.budget, e.status, e.created_at,
           CONCAT(ud.first_name, ' ', ud.last_name) as client_name,
           c.name as category_name, a.id as assignment_id,
           CONCAT(ud2.first_name, ' ', ud2.last_name) as lawyer_name
    FROM enquiries e
    JOIN users u ON e.client_id = u.id
    JOIN user_details ud ON u.id = ud.user_id
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN assignments a ON e.id = a.enquiry_id AND a.status = 'active'
    LEFT JOIN users u2 ON a.lawyer_id = u2.id
    LEFT JOIN user_details ud2 ON u2.id = ud2.user_id
    ORDER BY e.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Manage Enquiries</h6>
            </div>
            <div class="card-body">
                <?php if (count($enquiries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Client</th>
                                    <th>Category</th>
                                    <th>Budget</th>
                                    <th>Status</th>
                                    <th>Lawyer</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enquiries as $enquiry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enquiry['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($enquiry['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($enquiry['category_name']); ?></td>
                                        <td><?php echo $enquiry['budget'] ? '₹' . number_format($enquiry['budget'], 2) : 'Not specified'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $enquiry['status'] === 'completed' ? 'success' : 
                                                     ($enquiry['status'] === 'assigned' ? 'primary' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $enquiry['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($enquiry['lawyer_name']): ?>
                                                <?php echo htmlspecialchars($enquiry['lawyer_name']); ?>
                                            <?php else: ?>
                                                Not assigned
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($enquiry['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/admin/enquiry.php?id=<?php echo $enquiry['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($enquiry['status'] === 'pending'): ?>
                                                <a href="<?php echo SITE_URL; ?>/admin/assign-lawyer.php?id=<?php echo $enquiry['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-user-tag"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No enquiries found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
