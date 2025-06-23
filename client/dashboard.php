<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

$auth->requireRole('client');
$pageTitle = "Client Dashboard";

// Get client details
$stmt = $pdo->prepare("
    SELECT ud.first_name, ud.last_name, ud.phone 
    FROM user_details ud 
    WHERE ud.user_id = ?
");
$stmt->execute([$auth->getUserId()]);
$client = $stmt->fetch();

// Get client enquiries with payment status
$stmt = $pdo->prepare("
    SELECT 
        e.id, e.subject, e.description, e.budget, e.status, e.created_at, 
        c.name as category_name, a.id as assignment_id, 
        CONCAT(ud.first_name, ' ', ud.last_name) as lawyer_name,
        l.consultation_fee,
        (SELECT status FROM payments WHERE assignment_id = a.id ORDER BY id DESC LIMIT 1) as payment_status
    FROM enquiries e
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN assignments a ON e.id = a.enquiry_id AND a.status = 'active'
    LEFT JOIN lawyers l ON a.lawyer_id = l.user_id
    LEFT JOIN users u ON a.lawyer_id = u.id
    LEFT JOIN user_details ud ON u.id = ud.user_id
    WHERE e.client_id = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$auth->getUserId()]);
$enquiries = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="<?php echo SITE_URL; ?>/assets/images/default-avatar.jpg" 
                         class="rounded-circle mb-3" width="150" height="150" alt="Profile Image">
                    <h4><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($client['phone'] ?? 'Not provided'); ?></p>
                    <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Legal Enquiries</h5>
                    <a href="<?php echo SITE_URL; ?>/client/new-enquiry.php" class="btn btn-primary btn-sm">New Enquiry</a>
                </div>
                <div class="card-body">
                    <?php if (count($enquiries) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Lawyer</th>
                                        <th>Fee</th>
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
                                                    <?php echo ucfirst($enquiry['status']); ?>
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
                                            <td>
                                                <?php if ($enquiry['consultation_fee']): ?>
                                                    ₹<?php echo number_format($enquiry['consultation_fee'], 2); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($enquiry['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/client/enquiry.php?id=<?php echo $enquiry['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">View</a>
                                                <?php if ($enquiry['assignment_id'] && $enquiry['status'] === 'assigned' && $enquiry['payment_status'] !== 'completed'): ?>
                                                    <button class="btn btn-sm btn-success pay-btn" 
                                                            data-assignment="<?php echo $enquiry['assignment_id']; ?>"
                                                            data-amount="<?php echo $enquiry['consultation_fee']; ?>">
                                                        Pay Now
                                                    </button>
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
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pay Consultation Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="paymentModalBody">
                <!-- Content will be loaded dynamically -->
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
        const amount = $(this).data('amount');
        
        $('#paymentModal').modal('show');
        $('#paymentModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Preparing payment...</p>
            </div>
        `);
        
        // Fetch payment details
        $.ajax({
            url: '<?php echo SITE_URL; ?>/api/create-payment.php',
            method: 'POST',
            data: { 
                assignment_id: assignmentId,
                amount: amount
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#paymentModalBody').html(`
                        <div class="payment-details">
                            <div class="d-flex justify-content-between mb-3">
                                <span>Consultation Fee:</span>
                                <strong>₹${response.amount}</strong>
                            </div>
                            <button id="rzp-button" class="btn btn-primary w-100 py-2">
                                Pay ₹${response.amount}
                            </button>
                            <p class="text-muted small mt-2">
                                You will be redirected to Razorpay to complete your payment.
                            </p>
                        </div>
                    `);
                    
                    // Initialize Razorpay
                    const options = {
                        key: response.key,
                        amount: response.amount * 100,
                        currency: 'INR',
                        name: response.name,
                        description: response.description,
                        order_id: response.order_id,
                        handler: function(razorpayResponse) {
                            // Verify payment on server
                            $.ajax({
                                url: '<?php echo SITE_URL; ?>/api/verify-payment.php',
                                method: 'POST',
                                data: {
                                    razorpay_payment_id: razorpayResponse.razorpay_payment_id,
                                    razorpay_order_id: razorpayResponse.razorpay_order_id,
                                    razorpay_signature: razorpayResponse.razorpay_signature,
                                    assignment_id: assignmentId
                                },
                                dataType: 'json',
                                success: function(verificationResponse) {
                                    if (verificationResponse.success) {
                                        $('#paymentModalBody').html(`
                                            <div class="text-center py-4">
                                                <div class="text-success mb-3">
                                                    <i class="fas fa-check-circle fa-4x"></i>
                                                </div>
                                                <h4>Payment Successful!</h4>
                                                <p>Your payment of ₹${response.amount} has been received.</p>
                                                <button class="btn btn-primary" data-bs-dismiss="modal">
                                                    Close
                                                </button>
                                            </div>
                                        `);
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 3000);
                                    } else {
                                        showPaymentError(verificationResponse.error || 'Payment verification failed');
                                    }
                                },
                                error: function() {
                                    showPaymentError('Error verifying payment. Please contact support.');
                                }
                            });
                        },
                        prefill: {
                            name: '<?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>',
                            email: '<?php echo htmlspecialchars($_SESSION['email']); ?>',
                            contact: '<?php echo htmlspecialchars($client['phone'] ?? ''); ?>'
                        },
                        theme: {
                            color: '#4e73df'
                        },
                        modal: {
                            ondismiss: function() {
                                console.log('Payment cancelled');
                            }
                        }
                    };
                    
                    const rzp = new Razorpay(options);
                    
                    document.getElementById('rzp-button').onclick = function(e) {
                        rzp.open();
                        e.preventDefault();
                    };
                    
                } else {
                    showPaymentError(response.error || 'Failed to initialize payment');
                }
            },
            error: function(xhr, status, error) {
                showPaymentError('Error: ' + error);
            }
        });
    });
    
    function showPaymentError(message) {
        $('#paymentModalBody').html(`
            <div class="alert alert-danger">
                <h5 class="alert-heading">Payment Error</h5>
                <p>${message}</p>
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        `);
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>