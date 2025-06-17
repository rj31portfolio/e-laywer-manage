 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('superadmin');
$pageTitle = "Super Admin Dashboard";

// Get statistics
$stats = [];
$queries = [
    'total_users' => "SELECT COUNT(*) FROM users",
    'total_admins' => "SELECT COUNT(*) FROM users WHERE role = 'admin'",
    'total_lawyers' => "SELECT COUNT(*) FROM users WHERE role = 'lawyer'",
    'total_clients' => "SELECT COUNT(*) FROM users WHERE role = 'client'",
    'total_enquiries' => "SELECT COUNT(*) FROM enquiries",
    'total_payments' => "SELECT COUNT(*) FROM payments WHERE status = 'completed'",
    'total_revenue' => "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'"
];

foreach ($queries as $key => $query) {
    $stmt = $pdo->query($query);
    $stats[$key] = $stmt->fetchColumn();
}

// Get recent users
$recentUsers = $pdo->query("
    SELECT u.id, u.email, u.role, u.created_at, 
           CONCAT(ud.first_name, ' ', ud.last_name) as name
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    ORDER BY u.created_at DESC LIMIT 5
")->fetchAll();

// Get recent payments
$recentPayments = $pdo->query("
    SELECT p.amount, p.created_at, 
           CONCAT(ud.first_name, ' ', ud.last_name) as client_name,
           a.id as assignment_id
    FROM payments p
    JOIN assignments a ON p.assignment_id = a.id
    JOIN users u ON a.enquiry_id IN (SELECT id FROM enquiries WHERE client_id = u.id)
    JOIN user_details ud ON u.id = ud.user_id
    WHERE p.status = 'completed'
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">Super Admin Dashboard</h2>
    </div>
</div>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-rupee-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Active Enquiries</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_enquiries']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Lawyers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_lawyers']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-gavel fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Payments</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                    <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                    <td>#<?php echo $payment['assignment_id']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo SITE_URL; ?>/superadmin/users.php" class="btn btn-primary w-100">
                            <i class="fas fa-users me-2"></i> Manage Users
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo SITE_URL; ?>/superadmin/categories.php" class="btn btn-success w-100">
                            <i class="fas fa-tags me-2"></i> Manage Categories
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo SITE_URL; ?>/superadmin/payments.php" class="btn btn-info w-100">
                            <i class="fas fa-rupee-sign me-2"></i> View Payments
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="<?php echo SITE_URL; ?>/superadmin/settings.php" class="btn btn-warning w-100">
                            <i class="fas fa-cog me-2"></i> Platform Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
