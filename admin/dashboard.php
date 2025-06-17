 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('admin');
$pageTitle = "Admin Dashboard";

// Get statistics
$stats = [];
$queries = [
    'total_lawyers' => "SELECT COUNT(*) FROM users WHERE role = 'lawyer' AND status = 1",
    'total_clients' => "SELECT COUNT(*) FROM users WHERE role = 'client' AND status = 1",
    'pending_enquiries' => "SELECT COUNT(*) FROM enquiries WHERE status = 'pending'",
    'assigned_enquiries' => "SELECT COUNT(*) FROM enquiries WHERE status = 'assigned'",
    'completed_enquiries' => "SELECT COUNT(*) FROM enquiries WHERE status = 'completed'",
    'total_payments' => "SELECT COUNT(*) FROM payments WHERE status = 'completed'",
    'total_revenue' => "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'"
];

foreach ($queries as $key => $query) {
    $stmt = $pdo->query($query);
    $stats[$key] = $stmt->fetchColumn();
}

// Get recent enquiries
$recentEnquiries = $pdo->query("
    SELECT e.id, e.subject, e.status, e.created_at, 
           CONCAT(ud.first_name, ' ', ud.last_name) as client_name,
           c.name as category_name
    FROM enquiries e
    JOIN users u ON e.client_id = u.id
    JOIN user_details ud ON u.id = ud.user_id
    JOIN categories c ON e.category_id = c.id
    ORDER BY e.created_at DESC LIMIT 5
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2 class="mb-4">Admin Dashboard</h2>
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
                            Total Lawyers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_lawyers']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-gavel fa-2x text-gray-300"></i>
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
                            Pending Enquiries</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_enquiries']; ?></div>
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
                            Clients</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_clients']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                <h6 class="m-0 font-weight-bold text-primary">Recent Enquiries</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Client</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEnquiries as $enquiry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($enquiry['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($enquiry['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($enquiry['category_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $enquiry['status'] === 'completed' ? 'success' : 
                                                 ($enquiry['status'] === 'assigned' ? 'primary' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $enquiry['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($enquiry['created_at'])); ?></td>
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
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/lawyers.php" class="btn btn-primary w-100">
                            <i class="fas fa-gavel me-2"></i> Manage Lawyers
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/categories.php" class="btn btn-success w-100">
                            <i class="fas fa-tags me-2"></i> Manage Categories
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/enquiries.php" class="btn btn-info w-100">
                            <i class="fas fa-clipboard-list me-2"></i> View Enquiries
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo SITE_URL; ?>/admin/payments.php" class="btn btn-warning w-100">
                            <i class="fas fa-rupee-sign me-2"></i> View Payments
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
