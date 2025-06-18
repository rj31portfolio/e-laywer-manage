<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "Manage Categories";

// Fetch all categories
$categories = $pdo->query("
    SELECT c.id, c.name, c.description, 
           CONCAT(ud.first_name, ' ', ud.last_name) AS created_by,
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
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCategoryModal()">
                    <i class="fas fa-plus me-1"></i> Add New Category
                </button>
            </div>
            <div class="card-body">
                <div id="alert-msg"></div>
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
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cat['name']) ?></td>
                                        <td><?= htmlspecialchars($cat['description']) ?></td>
                                        <td><?= htmlspecialchars($cat['created_by']) ?></td>
                                        <td><?= date('d M Y', strtotime($cat['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick='editCategory(<?= json_encode($cat) ?>)'><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger delete-category" data-id="<?= $cat['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No categories found.</div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="categoryForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="categoryModalLabel">Add/Edit Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="categoryId">
          <div class="mb-3">
            <label for="categoryName" class="form-label">Category Name</label>
            <input type="text" class="form-control" name="name" id="categoryName" required>
          </div>
          <div class="mb-3">
            <label for="categoryDesc" class="form-label">Description</label>
            <textarea class="form-control" name="description" id="categoryDesc" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openCategoryModal() {
  $('#categoryForm')[0].reset();
  $('#categoryId').val('');
  $('#categoryModalLabel').text('Add New Category');
}

function editCategory(data) {
  $('#categoryId').val(data.id);
  $('#categoryName').val(data.name);
  $('#categoryDesc').val(data.description);
  $('#categoryModalLabel').text('Edit Category');
  $('#categoryModal').modal('show');
}

$('#categoryForm').on('submit', function(e) {
  e.preventDefault();
  $.post('process-category.php', $(this).serialize(), function(response) {
    if (response.success) {
      alert(response.message);
      location.reload();
    } else {
      $('#alert-msg').html(`<div class="alert alert-danger">${response.message}</div>`);
    }
  }, 'json');
});

$('.delete-category').click(function() {
  const id = $(this).data('id');
  if (confirm('Are you sure to delete this category?')) {
    $.post('process-category.php', { action: 'delete', id }, function(response) {
      if (response.success) {
        alert('Deleted successfully');
        location.reload();
      } else {
        alert('Error: ' + response.message);
      }
    }, 'json');
  }
});
</script>

<?php require_once '../includes/footer.php'; ?>
