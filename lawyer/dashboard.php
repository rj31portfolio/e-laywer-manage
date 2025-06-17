 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('lawyer');
$pageTitle = "Lawyer Dashboard";

// Get lawyer details
$stmt = $pdo->prepare("
    SELECT u.id, CONCAT(ud.first_name, ' ', ud.last_name) as name, 
           ud.photo, l.consultation_fee, c.name as category,
           l.rating, l.experience, l.availability
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    JOIN lawyers l ON u.id = l.user_id
    JOIN categories c ON l.category_id = c.id
    WHERE u.id = ?
");
$stmt->execute([$auth->getUserId()]);
$lawyer = $stmt->fetch();

// Get assigned cases
$stmt = $pdo->prepare("
    SELECT a.id as assignment_id, e.id as enquiry_id, e.subject, e.status, 
           e.created_at, CONCAT(ud.first_name, ' ', ud.last_name) as client_name,
           p.status as payment_status
    FROM assignments a
    JOIN enquiries e ON a.enquiry_id = e.id
    JOIN users u ON e.client_id = u.id
    JOIN user_details ud ON u.id = ud.user_id
    LEFT JOIN payments p ON a.id = p.assignment_id
    WHERE a.lawyer_id = ? AND a.status = 'active'
    ORDER BY e.created_at DESC
");
$stmt->execute([$auth->getUserId()]);
$cases = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <img src="<?php echo SITE_URL; ?>/assets/uploads/<?php echo $lawyer['photo'] ?? 'default-avatar.jpg'; ?>" 
                     class="rounded-circle mb-3" width="150" height="150">
                <h4><?php echo htmlspecialchars($lawyer['name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($lawyer['category']); ?></p>
                
                <div class="mb-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?php echo $i <= round($lawyer['rating']) ? 'text-warning' : 'text-secondary'; ?>"></i>
                    <?php endfor; ?>
                    <span class="ms-1">(<?php echo $lawyer['rating']; ?>)</span>
                </div>
                
                <p class="text-muted mb-2">
                    <i class="fas fa-briefcase me-1"></i> <?php echo $lawyer['experience']; ?>+ years experience
                </p>
                
                <p class="h5 text-primary mb-3">₹<?php echo number_format($lawyer['consultation_fee'], 2); ?> <small class="text-muted">/consultation</small></p>
                
                <p class="mb-3">
                    Status: 
                    <span class="badge bg-<?php echo $lawyer['availability'] ? 'success' : 'danger'; ?>">
                        <?php echo $lawyer['availability'] ? 'Available' : 'Not Available'; ?>
                    </span>
                </p>
                
                <a href="<?php echo SITE_URL; ?>/lawyer/profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">My Cases</h5>
            </div>
            <div class="card-body">
                <?php if (count($cases) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Case</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($case['client_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $case['status'] === 'completed' ? 'success' : 
                                                     ($case['status'] === 'in_progress' ? 'primary' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $case['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($case['payment_status'] === 'completed'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($case['payment_status'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($case['created_at'])); ?></td>
                                        <td>
                                            <a href="<?php echo SITE_URL; ?>/lawyer/case.php?id=<?php echo $case['enquiry_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">You don't have any assigned cases yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
