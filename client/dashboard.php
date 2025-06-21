<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('client');
$pageTitle = "Client Dashboard";

// Generate CSRF token for payment form
if (empty($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
}

// Get client details
$stmt = $pdo->prepare("SELECT ud.first_name, ud.last_name, ud.phone FROM user_details ud WHERE ud.user_id = ?");
$stmt->execute([$auth->getUserId()]);
$client = $stmt->fetch();

// Get client enquiries
$stmt = $pdo->prepare("
    SELECT e.id, e.subject, e.description, e.budget, e.status, e.created_at, 
           c.name as category_name, a.id as assignment_id, 
           CONCAT(ud.first_name, ' ', ud.last_name) as lawyer_name,
           p.status as payment_status,
           p.amount as payment_amount
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
                                                <button class="btn btn-sm btn-success pay-btn" 
                                                        data-assignment="<?php echo $enquiry['assignment_id']; ?>"
                                                        data-amount="<?php echo $enquiry['payment_amount'] ?? $enquiry['budget']; ?>">
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

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Payment</h5>
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
        
        // Show loading state
        $('#paymentModal').modal('show');
        $('#paymentModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Preparing payment details...</p>
            </div>
        `);
        
        // Fetch payment details
        $.ajax({
            url: '<?php echo SITE_URL; ?>/process/get-payment-details.php',
            type: 'POST',
            data: { 
                assignment_id: assignmentId,
                csrf_token: '<?php echo $_SESSION['payment_csrf']; ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderPaymentForm(response, assignmentId);
                } else {
                    showPaymentError(response.error || 'Failed to load payment details');
                }
            },
            error: function(xhr, status, error) {
                showPaymentError('Network error occurred. Please try again.');
                console.error('Payment details error:', error);
            }
        });
    });
    
    function renderPaymentForm(response, assignmentId) {
        $('#paymentModalBody').html(`
            <form id="paymentForm">
                <input type="hidden" id="assignmentId" value="${assignmentId}">
                <input type="hidden" id="csrfToken" value="<?php echo $_SESSION['payment_csrf']; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Service</label>
                    <input type="text" class="form-control" value="Legal Consultation" readonly>
                </div>
                
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount (₹)</label>
                    <input type="number" class="form-control" id="amount" value="${response.amount}" readonly>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You will be redirected to Razorpay's secure payment page
                </div>
                
                <button type="button" id="rzp-button" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-lock me-2"></i> Pay Securely ₹${response.amount}
                </button>
                
                <div class="text-center mt-3">
                    <img src="<?php echo SITE_URL; ?>/assets/images/razorpay-logo.png" alt="Razorpay" style="height: 30px;">
                </div>
            </form>
        `);
        
        initializeRazorpay(response, assignmentId);
    }
    
    function initializeRazorpay(response, assignmentId) {
        try {
            const options = {
                key: '<?php echo RAZORPAY_KEY_ID; ?>',
                amount: response.amount * 100,
                currency: 'INR',
                name: '<?php echo htmlspecialchars(SITE_NAME); ?>',
                description: 'Legal Consultation Payment',
                order_id: response.order_id,
                handler: function(razorpayResponse) {
                    verifyPayment(razorpayResponse, assignmentId);
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
                        console.log('Payment modal dismissed');
                    }
                },
                notes: {
                    assignment_id: assignmentId,
                    client_id: '<?php echo $auth->getUserId(); ?>'
                }
            };
            
            const rzp = new Razorpay(options);
            
            document.getElementById('rzp-button').onclick = function(e) {
                rzp.open();
                e.preventDefault();
            };
            
            // Close Razorpay checkout when modal is closed
            $('#paymentModal').on('hidden.bs.modal', function() {
                if (rzp && typeof rzp.close === 'function') {
                    rzp.close();
                }
            });
            
        } catch (error) {
            console.error('Razorpay initialization error:', error);
            showPaymentError('Failed to initialize payment gateway. Please try again.');
        }
    }
    
    function verifyPayment(razorpayResponse, assignmentId) {
        $('#paymentModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Verifying your payment...</p>
            </div>
        `);
        
        $.ajax({
            url: '<?php echo SITE_URL; ?>/process/verify-payment.php',
            type: 'POST',
            data: {
                razorpay_payment_id: razorpayResponse.razorpay_payment_id,
                razorpay_order_id: razorpayResponse.razorpay_order_id,
                razorpay_signature: razorpayResponse.razorpay_signature,
                assignment_id: assignmentId,
                csrf_token: $('#csrfToken').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showPaymentSuccess();
                } else {
                    showPaymentError(response.error || 'Payment verification failed');
                }
            },
            error: function(xhr, status, error) {
                showPaymentError('Network error during verification. Please contact support.');
                console.error('Verification error:', error);
            }
        });
    }
    
    function showPaymentSuccess() {
        $('#paymentModalBody').html(`
            <div class="text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <h4 class="text-success">Payment Successful!</h4>
                <p>Thank you for your payment. Your consultation will proceed as scheduled.</p>
                <button class="btn btn-success" data-bs-dismiss="modal" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-2"></i> Refresh Page
                </button>
            </div>
        `);
    }
    
    function showPaymentError(message) {
        $('#paymentModalBody').html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            </div>
            <button class="btn btn-outline-secondary w-100 mt-2" data-bs-dismiss="modal">
                Close
            </button>
        `);
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>