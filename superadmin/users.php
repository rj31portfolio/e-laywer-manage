<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
$auth->requireRole('superadmin');
$pageTitle = "Manage Users";

// Start session for flash messages
if (session_status() == PHP_SESSION_NONE) session_start();

// Handle AJAX Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $id = (int)$_POST['delete_user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetchColumn();

    if ($role && $role !== 'superadmin') {
        $pdo->prepare("DELETE FROM user_details WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $_SESSION['success'] = "User deleted successfully.";
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Cannot delete superadmin']);
    }
    exit;
}

// Handle Add/Edit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $id = $_POST['user_id'] ?? 0;
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = isset($_POST['status']) ? 1 : 0;
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];

    if ($id) {
        // Update
        $pdo->prepare("UPDATE users SET email=?, role=?, status=? WHERE id=?")
            ->execute([$email, $role, $status, $id]);
        $pdo->prepare("UPDATE user_details SET first_name=?, last_name=?, phone=? WHERE user_id=?")
            ->execute([$first_name, $last_name, $phone, $id]);
        $_SESSION['success'] = "User updated successfully.";
    } else {
        // Add
        $pdo->prepare("INSERT INTO users (email, role, status, created_at) VALUES (?, ?, ?, NOW())")
            ->execute([$email, $role, $status]);
        $newUserId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO user_details (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)")
            ->execute([$newUserId, $first_name, $last_name, $phone]);
        $_SESSION['success'] = "User added successfully.";
    }

    header("Location: manage-users.php");
    exit();
}

// Get All Users
$users = $pdo->query("
    SELECT u.id, u.email, u.role, u.status, u.created_at,
           CONCAT(ud.first_name, ' ', ud.last_name) AS name,
           ud.first_name, ud.last_name, ud.phone
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    ORDER BY u.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h4>Manage Users</h4>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#userModal" onclick="clearForm()">+ Add User</button>

    <table class="table table-bordered" id="userTable">
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr id="user-row-<?= $u['id'] ?>">
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= ucfirst($u['role']) ?></td>
                <td><?= htmlspecialchars($u['phone']) ?></td>
                <td>
                    <span class="badge bg-<?= $u['status'] ? 'success' : 'danger' ?>">
                        <?= $u['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick='editUser(<?= json_encode($u) ?>)'>
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php if ($u['role'] !== 'superadmin'): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $u['id'] ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User Form</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="user_id" id="user_id">
        <div class="mb-2">
            <label>First Name</label>
            <input type="text" name="first_name" id="first_name" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>Last Name</label>
            <input type="text" name="last_name" id="last_name" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>Email</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="mb-2">
            <label>Phone</label>
            <input type="text" name="phone" id="phone" class="form-control">
        </div>
        <div class="mb-2">
            <label>Role</label>
            <select name="role" id="role" class="form-control" required>
                <option value="client">Client</option>
                <option value="lawyer">Lawyer</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" name="status" id="status" class="form-check-input" checked>
            <label class="form-check-label" for="status">Active</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="save_user" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function editUser(user) {
    document.getElementById('user_id').value = user.id;
    document.getElementById('first_name').value = user.first_name;
    document.getElementById('last_name').value = user.last_name;
    document.getElementById('email').value = user.email;
    document.getElementById('phone').value = user.phone;
    document.getElementById('role').value = user.role;
    document.getElementById('status').checked = user.status == 1;

    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function clearForm() {
    document.getElementById('user_id').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('email').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('role').value = 'client';
    document.getElementById('status').checked = true;
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
        fetch('manage-users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ delete_user_id: userId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('user-row-' + userId).remove();
                location.reload(); // reload to show flash message
            } else {
                alert(data.error || 'Failed to delete user.');
            }
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
