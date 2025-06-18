<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Authenticate as Admin
$auth->requireRole('admin');
$pageTitle = "Payment Management";

// Initialize variables
$error = '';
$success = '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$filterLawyer = isset($_GET['lawyer']) ? (int)$_GET['lawyer'] : 0;

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Get list of lawyers for filter
$lawyers = $pdo->query("
    SELECT u.id, CONCAT(ud.first_name, ' ', ud.last_name) as name 
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    JOIN lawyers l ON u.id = l.user_id
    WHERE u.role = 'lawyer' AND u.status = 1
    ORDER BY ud.first_name
")->fetchAll();

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
        CONCAT(ud2.first_name, ' ', ud2.last_name) as lawyer_name,
        u2.id as lawyer_id
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

// Add filters if needed
$params = [];
$whereClauses = [];

if ($filterStatus !== 'all') {
    $whereClauses[] = "p.status = ?";
    $params[] = $filterStatus;
}

if ($filterLawyer > 0) {
    $whereClauses[] = "u2.id = ?";
    $params[] = $filterLawyer;
}

if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
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
                <div>
                    <div class="input-group">
                        <select id="lawyerFilter" class="form-select form-select-sm" style="width: 200px;">
                            <option value="0">All Lawyers</option>
                            <?php foreach ($lawyers as $lawyer): ?>
                                <option value="<?php echo $lawyer['id']; ?>" <?php echo $filterLawyer == $lawyer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lawyer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="form-select form-select-sm" style="width: 150px;">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                        <button id="applyFilters" class="btn btn-primary btn-sm">Apply</button>
                    </div>
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
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-success verify-payment" 
                                                    data-payment-id="<?php echo $payment['id']; ?>">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                        <?php endif; ?>
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
    const dataTable = $('#dataTable').DataTable({
        responsive: true,
        order: [[0, 'desc']], // Default sort by payment ID descending
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting for actions column
        ]
    });

    // Handle payment details view
    $(document).on('click', '.view-payment', function() {
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

    // Handle payment verification
    $(document).on('click', '.verify-payment', function() {
        const paymentId = $(this).data('payment-id');
        const button = $(this);
        
        if (confirm('Are you sure you want to manually verify this payment?')) {
            button.prop('disabled', true);
            button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying...');
            
            $.post('verify_payment.php', { payment_id: paymentId }, function(response) {
                if (response.success) {
                    alert('Payment verified successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    button.prop('disabled', false);
                    button.html('<i class="fas fa-check"></i> Verify');
                }
            }, 'json').fail(function() {
                alert('Failed to verify payment. Please try again.');
                button.prop('disabled', false);
                button.html('<i class="fas fa-check"></i> Verify');
            });
        }
    });

    // Handle filter application
    $('#applyFilters').click(function() {
        const status = $('#statusFilter').val();
        const lawyer = $('#lawyerFilter').val();
        
        let url = 'payments.php?';
        if (status !== 'all') url += 'status=' + status + '&';
        if (lawyer > 0) url += 'lawyer=' + lawyer;
        
        // Clean up URL if no params
        if (url.endsWith('?')) url = 'payments.php';
        
        window.location.href = url;
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>