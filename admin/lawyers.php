 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "Manage Lawyers";

// Get all lawyers
$lawyers = $pdo->query("
    SELECT u.id, u.email, u.status, u.created_at,
           CONCAT(ud.first_name, ' ', ud.last_name) as name,
           ud.phone, c.name as category, l.consultation_fee
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    JOIN lawyers l ON u.id = l.user_id
    JOIN categories c ON l.category_id = c.id
    WHERE u.role = 'lawyer'
    ORDER BY u.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Manage Lawyers</h6>
                <a href="<?php echo SITE_URL; ?>/admin/add-lawyer.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Add New Lawyer
                </a>
            </div>
            <div class="card-body">
                <?php if (count($lawyers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Category</th>
                                    <th>Fee</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lawyers as $lawyer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lawyer['name']); ?></td>
                                        <td><?php echo htmlspecialchars($lawyer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($lawyer['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($lawyer['category']); ?></td>
                                        <td>₹<?php echo number_format($lawyer['consultation_fee'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $lawyer['status'] ? 'success' : 'danger'; ?>">
                                                <?php echo $lawyer['status'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($lawyer['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/admin/edit-lawyer.php?id=<?php echo $lawyer['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger delete-lawyer" data-id="<?php echo $lawyer['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No lawyers found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Delete lawyer
    $('.delete-lawyer').click(function() {
        const lawyerId = $(this).data('id');
        if (confirm('Are you sure you want to delete this lawyer? This action cannot be undone.')) {
            $.post('<?php echo SITE_URL; ?>/process/delete-lawyer.php', { id: lawyerId }, function(response) {
                if (response.success) {
                    alert('Lawyer deleted successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            });
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>
