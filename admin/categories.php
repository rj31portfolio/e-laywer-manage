 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "Manage Categories";

// Get all categories
$categories = $pdo->query("
    SELECT c.id, c.name, c.description, 
           CONCAT(ud.first_name, ' ', ud.last_name) as created_by,
           c.created_at
    FROM categories c
    JOIN users u ON c.created_by = u.id
    JOIN user_details ud ON u.id = ud.user_id
    ORDER BY c.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Manage Categories</h6>
                <a href="<?php echo SITE_URL; ?>/admin/add-category.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Add New Category
                </a>
            </div>
            <div class="card-body">
                <?php if (count($categories) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description']); ?></td>
                                        <td><?php echo htmlspecialchars($category['created_by']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($category['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/admin/edit-category.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger delete-category" data-id="<?php echo $category['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No categories found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Delete category
    $('.delete-category').click(function() {
        const categoryId = $(this).data('id');
        if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
            $.post('<?php echo SITE_URL; ?>/process/delete-category.php', { id: categoryId }, function(response) {
                if (response.success) {
                    alert('Category deleted successfully');
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
