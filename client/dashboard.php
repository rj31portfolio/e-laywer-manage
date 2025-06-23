<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('client');
$pageTitle = "Client Dashboard";

// Get client details
$stmt = $pdo->prepare("SELECT ud.first_name, ud.last_name, ud.phone FROM user_details ud WHERE ud.user_id = ?");
$stmt->execute([$auth->getUserId()]);
$client = $stmt->fetch();

// Get client enquiries
$stmt = $pdo->prepare("
    SELECT e.id, e.subject, e.description, e.budget, e.status, e.created_at, 
           c.name as category_name, a.id as assignment_id, 
           CONCAT(ud.first_name, ' ', ud.last_name) as lawyer_name,
           p.status as payment_status
    FROM enquiries e
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN assignments a ON e.id = a.enquiry_id AND a.status = 'active'
    LEFT JOIN users u ON a.lawyer_id = u.id
    LEFT JOIN user_details ud ON u.id = ud.user_id
    LEFT JOIN payments p ON a.id = p.assignment_id
    WHERE e.client_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$auth->getUserId()]);
$enquiries = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.jpg" class="rounded-circle mb-3" width="150" height="150">
                <h4><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($client['phone'] ?? 'Not provided'); ?></p>
                <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">My Legal Enquiries</h5>
            </div>
            <div class="card-body">
                <a href="<?php echo SITE_URL; ?>/client/new-enquiry.php" class="btn btn-primary mb-3">New Enquiry</a>
                
                <?php if (count($enquiries) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Lawyer</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enquiries as $enquiry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enquiry['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($enquiry['category_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $enquiry['status'] === 'completed' ? 'success' : 
                                                     ($enquiry['status'] === 'assigned' ? 'primary' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $enquiry['status'])); ?>
                                            </span>
                                            <?php if ($enquiry['payment_status'] === 'completed'): ?>
                                                <span class="badge bg-success ms-1">Paid</span>
                                            <?php elseif ($enquiry['payment_status'] === 'pending'): ?>
                                                <span class="badge bg-warning ms-1">Payment Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($enquiry['lawyer_name']): ?>
                                                <?php echo htmlspecialchars($enquiry['lawyer_name']); ?>
                                            <?php else: ?>
                                                Not assigned
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($enquiry['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/client/enquiry.php?id=<?php echo $enquiry['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($enquiry['assignment_id'] && $enquiry['status'] === 'assigned' && $enquiry['payment_status'] !== 'completed'): ?>
                                                <button class="btn btn-sm btn-success pay-btn" data-assignment="<?php echo $enquiry['assignment_id']; ?>">Pay Now</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">You haven't made any enquiries yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="assignmentId">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (₹)</label>
                        <input type="number" class="form-control" id="amount" readonly>
                    </div>
                    <div id="razorpay-container"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
$(document).ready(function() {
    // Handle pay button click
    $('.pay-btn').click(function() {
        const assignmentId = $(this).data('assignment');
        
        // Show loading
        $('#paymentModal').modal('show');
        $('#paymentModal .modal-body').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        // Fetch payment details
        $.post('<?php echo SITE_URL; ?>/client/payments.php', { assignment_id: assignmentId }, function(response) {
            if (response.success) {
                $('#paymentModal .modal-body').html(`
                    <form id="paymentForm">
                        <input type="hidden" id="assignmentId" value="${assignmentId}">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (₹)</label>
                            <input type="number" class="form-control" id="amount" value="${response.amount}" readonly>
                        </div>
                        <button type="button" id="rzp-button" class="btn btn-primary w-100">Pay ₹${response.amount}</button>
                    </form>
                `);
                
                // Initialize Razorpay
                const options = {
                    key: '<?php echo RAZORPAY_KEY_ID; ?>',
                    amount: response.amount * 100,
                    currency: 'INR',
                    name: '<?php echo SITE_NAME; ?>',
                    description: 'Legal Consultation Payment',
                    order_id: response.order_id,
                    handler: function(response) {
                        // Handle payment success
                        $.post('<?php echo SITE_URL; ?>/process/verify-payment.php', {
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature,
                            assignment_id: assignmentId
                        }, function(verificationResponse) {
                            if (verificationResponse.success) {
                                alert('Payment successful!');
                                $('#paymentModal').modal('hide');
                                location.reload();
                            } else {
                                alert('Payment verification failed: ' + verificationResponse.error);
                            }
                        });
                    },
                    prefill: {
                        name: '<?php echo $_SESSION['email']; ?>',
                        email: '<?php echo $_SESSION['email']; ?>'
                    },
                    theme: {
                        color: '#4e73df'
                    }
                };
                
                const rzp = new Razorpay(options);
                document.getElementById('rzp-button').onclick = function(e) {
                    rzp.open();
                    e.preventDefault();
                };
            } else {
                $('#paymentModal .modal-body').html(`<div class="alert alert-danger">${response.error}</div>`);
            }
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>