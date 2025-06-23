<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'client') {
    header('Location: /login.php');
    exit;
}

// Check if enquiry ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /client/dashboard.php');
    exit;
}

$enquiryId = (int)$_GET['id'];

// Get enquiry details
$stmt = $pdo->prepare("
    SELECT e.id, e.subject, e.description, e.budget, e.status, e.created_at, e.updated_at,
           c.name as category_name, c.description as category_description,
           a.id as assignment_id, a.status as assignment_status, a.amount,
           CONCAT(ud.first_name, ' ', ud.last_name) as lawyer_name,
           ud.phone as lawyer_phone, u.email as lawyer_email,
           ud.bio as lawyer_bio, ud.experience as lawyer_experience
    FROM enquiries e
    JOIN categories c ON e.category_id = c.id
    LEFT JOIN assignments a ON e.id = a.enquiry_id AND a.status = 'active'
    LEFT JOIN users u ON a.lawyer_id = u.id
    LEFT JOIN user_details ud ON u.id = ud.user_id
    WHERE e.id = ? AND e.client_id = ?
");
$stmt->execute([$enquiryId, $auth->getUserId()]);
$enquiry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enquiry) {
    header('Location: /client/dashboard.php');
    exit;
}

// Get messages for this enquiry
$stmt = $pdo->prepare("
    SELECT m.id, m.message, m.created_at, m.sender_type,
           CASE 
               WHEN m.sender_type = 'client' THEN CONCAT(cd.first_name, ' ', cd.last_name)
               WHEN m.sender_type = 'lawyer' THEN CONCAT(ld.first_name, ' ', ld.last_name)
           END as sender_name,
           CASE 
               WHEN m.sender_type = 'client' THEN 'You'
               WHEN m.sender_type = 'lawyer' THEN CONCAT(ld.first_name, ' ', ld.last_name)
           END as display_name
    FROM messages m
    LEFT JOIN users cu ON m.sender_type = 'client' AND m.sender_id = cu.id
    LEFT JOIN user_details cd ON cu.id = cd.user_id
    LEFT JOIN users lu ON m.sender_type = 'lawyer' AND m.sender_id = lu.id
    LEFT JOIN user_details ld ON lu.id = ld.user_id
    WHERE m.enquiry_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$enquiryId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><?php echo htmlspecialchars($enquiry['subject']); ?></h4>
                    <span class="badge bg-<?php 
                        echo $enquiry['status'] === 'completed' ? 'success' : 
                             ($enquiry['status'] === 'assigned' ? 'primary' : 'warning'); 
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $enquiry['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Category</h5>
                        <p><?php echo htmlspecialchars($enquiry['category_name']); ?></p>
                        <p class="text-muted"><?php echo htmlspecialchars($enquiry['category_description']); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($enquiry['description'])); ?></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Budget</h5>
                            <p>₹<?php echo number_format($enquiry['budget'], 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Created</h5>
                            <p><?php echo date('d M Y, h:i A', strtotime($enquiry['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($enquiry['status'] === 'assigned' && $enquiry['assignment_id']): ?>
                        <div class="alert alert-info mt-4">
                            <h5>Assigned Lawyer</h5>
                            <p><strong><?php echo htmlspecialchars($enquiry['lawyer_name']); ?></strong></p>
                            <p>Email: <?php echo htmlspecialchars($enquiry['lawyer_email']); ?></p>
                            <p>Phone: <?php echo htmlspecialchars($enquiry['lawyer_phone'] ?? 'Not provided'); ?></p>
                            <p>Experience: <?php echo htmlspecialchars($enquiry['lawyer_experience'] ?? 'Not provided'); ?> years</p>
                            <p class="mt-2"><?php echo nl2br(htmlspecialchars($enquiry['lawyer_bio'] ?? 'No bio provided')); ?></p>
                            
                            <?php if ($enquiry['assignment_status'] === 'active' && !empty($enquiry['amount'])): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <h6 class="mb-0">Agreed Amount: ₹<?php echo number_format($enquiry['amount'], 2); ?></h6>
                                    <button class="btn btn-success pay-btn" data-assignment="<?php echo $enquiry['assignment_id']; ?>">Pay Now</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Messages Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Messages</h5>
                </div>
                <div class="card-body">
                    <?php if (count($messages) > 0): ?>
                        <div class="chat-messages" style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($messages as $message): ?>
                                <div class="mb-3 d-flex <?php echo $message['sender_type'] === 'client' ? 'justify-content-end' : 'justify-content-start'; ?>">
                                    <div class="<?php echo $message['sender_type'] === 'client' ? 'bg-primary text-white' : 'bg-light'; ?> rounded p-3" style="max-width: 70%;">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <strong><?php echo htmlspecialchars($message['display_name']); ?></strong>
                                            <span class="text-muted"><?php echo date('d M Y, h:i A', strtotime($message['created_at'])); ?></span>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No messages yet.</div>
                    <?php endif; ?>
                    
                    <?php if ($enquiry['status'] === 'assigned'): ?>
                        <form id="messageForm" class="mt-3">
                            <input type="hidden" name="enquiry_id" value="<?php echo $enquiryId; ?>">
                            <div class="input-group">
                                <textarea name="message" class="form-control" placeholder="Type your message..." required></textarea>
                                <button type="submit" class="btn btn-primary">Send</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Enquiry Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/client/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                        
                        <?php if ($enquiry['status'] === 'pending'): ?>
                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel Enquiry</button>
                        <?php endif; ?>
                        
                        <?php if ($enquiry['status'] === 'completed'): ?>
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#reviewModal">Leave Review</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($enquiry['status'] === 'assigned'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Case Documents</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get documents for this assignment
                        $stmt = $pdo->prepare("
                            SELECT id, filename, filepath, created_at 
                            FROM documents 
                            WHERE assignment_id = ? 
                            ORDER BY created_at DESC
                        ");
                        $stmt->execute([$enquiry['assignment_id']]);
                        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php if (count($documents) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($documents as $doc): ?>
                                    <a href="<?php echo htmlspecialchars($doc['filepath']); ?>" class="list-group-item list-group-item-action" target="_blank" download>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($doc['filename']); ?></span>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($doc['created_at'])); ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No documents uploaded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Enquiry Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Enquiry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this enquiry?</p>
                <form id="cancelForm">
                    <input type="hidden" name="enquiry_id" value="<?php echo $enquiryId; ?>">
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Reason for cancellation</label>
                        <textarea class="form-control" id="cancel_reason" name="reason" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancel">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reviewForm">
                    <input type="hidden" name="assignment_id" value="<?php echo $enquiry['assignment_id']; ?>">
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating</label>
                        <select class="form-select" id="rating" name="rating" required>
                            <option value="">Select rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Very Good</option>
                            <option value="3">3 - Good</option>
                            <option value="2">2 - Fair</option>
                            <option value="1">1 - Poor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="review" class="form-label">Review</label>
                        <textarea class="form-control" id="review" name="review" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="submitReview">Submit Review</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal (same as in dashboard) -->
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
        $.post('/process/get-payment-details.php', { assignment_id: assignmentId }, function(response) {
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
                    name: 'LegalConnect',
                    description: 'Legal Consultation Payment',
                    order_id: response.order_id,
                    handler: function(response) {
                        // Handle payment success
                        $.post('/process/verify-payment.php', {
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
    
    // Handle message submission
    $('#messageForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        $.post('/process/send-message.php', formData, function(response) {
            if (response.success) {
                $('#messageForm textarea').val('');
                location.reload();
            } else {
                alert('Error: ' + response.error);
            }
        });
    });
    
    // Handle enquiry cancellation
    $('#confirmCancel').click(function() {
        const formData = $('#cancelForm').serialize();
        
        $.post('/process/cancel-enquiry.php', formData, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.error);
            }
        });
    });
    
    // Handle review submission
    $('#submitReview').click(function() {
        const formData = $('#reviewForm').serialize();
        
        $.post('/process/submit-review.php', formData, function(response) {
            if (response.success) {
                $('#reviewModal').modal('hide');
                alert('Thank you for your review!');
                location.reload();
            } else {
                alert('Error: ' + response.error);
            }
        });
    });
    
    // Auto-scroll messages to bottom
    $('.chat-messages').scrollTop($('.chat-messages')[0].scrollHeight);
});
</script>

<?php
require_once '../includes/footer.php';
?>