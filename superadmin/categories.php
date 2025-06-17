<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

$auth->requireRole('superadmin');
$pageTitle = "Manage Categories";

$error = '';
$success = '';

// Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    if (empty($name)) {
        $error = 'Category name is required';
    } else {
        try {
            if ($categoryId > 0) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $categoryId]);
                $success = 'Category updated successfully!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_by) VALUES (?, ?, ?)");
                $stmt->execute([$name, $description, $auth->getUserId()]);
                $success = 'Category created successfully!';
            }
            header("Location: categories.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle GET actions
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lawyers WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $lawyerCount = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enquiries WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $enquiryCount = $stmt->fetchColumn();

        if ($lawyerCount > 0 || $enquiryCount > 0) {
            $error = 'Cannot delete category - in use by lawyers or enquiries';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            $success = 'Category deleted successfully!';
            header("Location: categories.php?success=" . urlencode($success));
            exit();
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get all categories
$categories = $pdo->query("
    SELECT c.*, CONCAT(ud.first_name, ' ', ud.last_name) as created_by_name 
    FROM categories c
    JOIN users u ON c.created_by = u.id
    JOIN user_details ud ON u.id = ud.user_id
    ORDER BY c.name ASC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Manage Categories</h6>
                <button class="btn btn-primary btn-sm" onclick="openCategoryModal()">+ Add New Category</button>
            </div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (isset($_GET['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div><?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%">
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
                                    <td><?php echo htmlspecialchars($category['created_by_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick='editCategory(<?php echo json_encode($category); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?action=delete&id=<?php echo $category['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this category?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit Category -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Add/Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="category_id" id="category_id">
                <div class="mb-3">
                    <label for="name" class="form-label">Category Name *</label>
                    <input type="text" class="form-control" name="name" id="name" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_category" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('category_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
    var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function editCategory(category) {
    document.getElementById('category_id').value = category.id;
    document.getElementById('name').value = category.name;
    document.getElementById('description').value = category.description;
    var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

// Initialize DataTable
document.addEventListener('DOMContentLoaded', function () {
    $('#dataTable').DataTable({
        responsive: true,
        columnDefs: [{ orderable: false, targets: -1 }]
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
