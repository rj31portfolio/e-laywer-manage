<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
$auth->requireRole('admin');
$pageTitle = "Manage Lawyers";

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Fetch lawyers
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
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#lawyerModal" onclick="openLawyerModal()">
                    <i class="fas fa-plus me-1"></i> Add New Lawyer
                </button>
            </div>
            <div class="card-body">
                <div id="alert-msg"></div>
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
                                        <td><?= htmlspecialchars($lawyer['name']) ?></td>
                                        <td><?= htmlspecialchars($lawyer['email']) ?></td>
                                        <td><?= htmlspecialchars($lawyer['phone']) ?></td>
                                        <td><?= htmlspecialchars($lawyer['category']) ?></td>
                                        <td>₹<?= number_format($lawyer['consultation_fee'], 2) ?></td>
                                        <td><span class="badge bg-<?= $lawyer['status'] ? 'success' : 'danger' ?>"><?= $lawyer['status'] ? 'Active' : 'Inactive' ?></span></td>
                                        <td><?= date('d M Y', strtotime($lawyer['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick='editLawyer(<?= json_encode($lawyer) ?>)'><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger delete-lawyer" data-id="<?= $lawyer['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No lawyers found.</div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="lawyerModal" tabindex="-1" aria-labelledby="lawyerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="lawyerForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="lawyerModalLabel">Add/Edit Lawyer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body row g-3">
          <input type="hidden" name="id" id="lawyerId">
          <div class="col-md-6">
            <label>Email</label>
            <input type="email" name="email" id="email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label>Phone</label>
            <input type="text" name="phone" id="phone" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label>First Name</label>
            <input type="text" name="first_name" id="firstName" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label>Last Name</label>
            <input type="text" name="last_name" id="lastName" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label>Category</label>
            <select name="category_id" id="categoryId" class="form-control" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="col-md-6">
            <label>Consultation Fee</label>
            <input type="number" name="consultation_fee" id="consultationFee" class="form-control" required>
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
function openLawyerModal() {
  $('#lawyerForm')[0].reset();
  $('#lawyerId').val('');
  $('#lawyerModal').modal('show');
}

function editLawyer(data) {
  $('#lawyerId').val(data.id);
  $('#email').val(data.email);
  $('#phone').val(data.phone);
  $('#firstName').val(data.name.split(' ')[0]);
  $('#lastName').val(data.name.split(' ')[1]);
  $('#categoryId').val(data.category_id);
  $('#consultationFee').val(data.consultation_fee);
  $('#lawyerModal').modal('show');
}

$('#lawyerForm').on('submit', function(e) {
  e.preventDefault();
  $.post('process-lawyer.php', $(this).serialize(), function(response) {
    if (response.success) {
      alert(response.message);
      location.reload();
    } else {
      $('#alert-msg').html(`<div class="alert alert-danger">${response.message}</div>`);
    }
  }, 'json');
});

$('.delete-lawyer').click(function() {
  const id = $(this).data('id');
  if (confirm('Are you sure to delete this lawyer?')) {
    $.post('process-lawyer.php', { action: 'delete', id }, function(response) {
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
