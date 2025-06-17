 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Authenticate as Super Admin
$auth->requireRole('superadmin');
$pageTitle = "Manage Payments";

// Initialize variables
$error = '';
$success = '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Build the base query
$query = "
    SELECT 
        p.id, 
        p.amount, 
        p.status, 
        p.created_at, 
        p.updated_at,
        p.razorpay_order_id,
        p.razorpay_payment_id,
        a.id as assignment_id,
        e.subject as case_subject,
        CONCAT(ud1.first_name, ' ', ud1.last_name) as client_name,
        CONCAT(ud2.first_name, ' ', ud2.last_name) as lawyer_name
    FROM 
        payments p
    JOIN 
        assignments a ON p.assignment_id = a.id
    JOIN 
        enquiries e ON a.enquiry_id = e.id
    JOIN 
        users u1 ON e.client_id = u1.id
    JOIN 
        user_details ud1 ON u1.id = ud1.user_id
    JOIN 
        users u2 ON a.lawyer_id = u2.id
    JOIN 
        user_details ud2 ON u2.id = ud2.user_id
";

// Add status filter if needed
$params = [];
if ($filterStatus !== 'all') {
    $query .= " WHERE p.status = ?";
    $params[] = $filterStatus;
}

// Complete the query
$query .= " ORDER BY p.created_at DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Payment Records</h6>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" id="statusFilter" data-bs-toggle="dropdown" aria-expanded="false">
                        Filter: <?php echo ucfirst($filterStatus); ?>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="statusFilter">
                        <li><a class="dropdown-item <?php echo $filterStatus === 'all' ? 'active' : ''; ?>" href="payments.php">All Payments</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?php echo $filterStatus === 'completed' ? 'active' : ''; ?>" href="payments.php?status=completed">Completed</a></li>
                        <li><a class="dropdown-item <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>" href="payments.php?status=pending">Pending</a></li>
                        <li><a class="dropdown-item <?php echo $filterStatus === 'failed' ? 'active' : ''; ?>" href="payments.php?status=failed">Failed</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Client</th>
                                <th>Lawyer</th>
                                <th>Case Subject</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['id']; ?></td>
                                    <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $payment['status'] === 'completed' ? 'success' : 
                                                 ($payment['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['lawyer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['case_subject']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-payment" 
                                                data-payment-id="<?php echo $payment['id']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#paymentDetailsModal">
                                            <i class="fas fa-eye"></i> View
                                        </button>
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

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentDetailsModalLabel">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Content will be loaded via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTables
document.addEventListener('DOMContentLoaded', function() {
    $('#dataTable').DataTable({
        responsive: true,
        order: [[0, 'desc']], // Default sort by payment ID descending
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting for actions column
        ]
    });

    // Handle payment details view
    $('.view-payment').click(function() {
        const paymentId = $(this).data('payment-id');
        $('#paymentDetailsContent').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
        
        $.get('payment_details.php?id=' + paymentId, function(data) {
            $('#paymentDetailsContent').html(data);
        }).fail(function() {
            $('#paymentDetailsContent').html(`
                <div class="alert alert-danger">
                    Failed to load payment details. Please try again.
                </div>
            `);
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>
