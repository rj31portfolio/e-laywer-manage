<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Authenticate as Super Admin
$auth->requireRole('superadmin');

// Get payment ID from request
$paymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($paymentId <= 0) {
    die('Invalid payment ID');
}

// Fetch payment details
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        a.id as assignment_id,
        e.subject as case_subject,
        e.description as case_description,
        CONCAT(ud1.first_name, ' ', ud1.last_name) as client_name,
        u1.email as client_email,
        ud1.phone as client_phone,
        CONCAT(ud2.first_name, ' ', ud2.last_name) as lawyer_name,
        u2.email as lawyer_email,
        ud2.phone as lawyer_phone,
        c.name as category_name
    FROM 
        payments p
    JOIN 
        assignments a ON p.assignment_id = a.id
    JOIN 
        enquiries e ON a.enquiry_id = e.id
    JOIN 
        categories c ON e.category_id = c.id
    JOIN 
        users u1 ON e.client_id = u1.id
    JOIN 
        user_details ud1 ON u1.id = ud1.user_id
    JOIN 
        users u2 ON a.lawyer_id = u2.id
    JOIN 
        user_details ud2 ON u2.id = ud2.user_id
    WHERE 
        p.id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    die('Payment not found');
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6>Payment Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Payment ID</th>
                        <td>#<?php echo $payment['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $payment['status'] === 'completed' ? 'success' : 
                                     ($payment['status'] === 'pending' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Razorpay Order ID</th>
                        <td><?php echo htmlspecialchars($payment['razorpay_order_id']); ?></td>
                    </tr>
                    <tr>
                        <th>Razorpay Payment ID</th>
                        <td><?php echo htmlspecialchars($payment['razorpay_payment_id'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?php echo date('M j, Y H:i:s', strtotime($payment['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Updated At</th>
                        <td><?php echo date('M j, Y H:i:s', strtotime($payment['updated_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6>Case Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Assignment ID</th>
                        <td>#<?php echo $payment['assignment_id']; ?></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Subject</th>
                        <td><?php echo htmlspecialchars($payment['case_subject']); ?></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?php echo nl2br(htmlspecialchars($payment['case_description'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>Client Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Name</th>
                        <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars($payment['client_email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars($payment['client_phone']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>Lawyer Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>Name</th>
                        <td><?php echo htmlspecialchars($payment['lawyer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?php echo htmlspecialchars($payment['lawyer_email']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars($payment['lawyer_phone']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>