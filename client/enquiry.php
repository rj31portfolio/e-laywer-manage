 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('client');
$pageTitle = "New Enquiry";

// Get categories
$categories = $pdo->query("SELECT id, name FROM categories")->fetchAll();

// Get lawyer if specified
$lawyer = null;
if (isset($_GET['lawyer']) && is_numeric($_GET['lawyer'])) {
    $stmt = $pdo->prepare("
        SELECT u.id, CONCAT(ud.first_name, ' ', ud.last_name) as name, 
               l.consultation_fee, c.name as category
        FROM users u
        JOIN user_details ud ON u.id = ud.user_id
        JOIN lawyers l ON u.id = l.user_id
        JOIN categories c ON l.category_id = c.id
        WHERE u.id = ? AND u.role = 'lawyer' AND u.status = 1
    ");
    $stmt->execute([$_GET['lawyer']]);
    $lawyer = $stmt->fetch();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $categoryId = trim($_POST['category_id']);
    $description = trim($_POST['description']);
    $budget = trim($_POST['budget']);
    $lawyerId = trim($_POST['lawyer_id']);
    
    // Validate inputs
    if (empty($subject) || empty($categoryId) || empty($description)) {
        $error = 'Subject, category and description are required';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert enquiry
            $stmt = $pdo->prepare("
                INSERT INTO enquiries (client_id, category_id, subject, description, budget, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$auth->getUserId(), $categoryId, $subject, $description, $budget]);
            $enquiryId = $pdo->lastInsertId();
            
            // If lawyer was specified, create assignment
            if (!empty($lawyerId)) {
                $stmt = $pdo->prepare("
                    INSERT INTO assignments (enquiry_id, lawyer_id, assigned_by, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$enquiryId, $lawyerId, $auth->getUserId()]);
                
                // Update enquiry status
                $stmt = $pdo->prepare("UPDATE enquiries SET status = 'assigned' WHERE id = ?");
                $stmt->execute([$enquiryId]);
            }
            
            $pdo->commit();
            $success = 'Enquiry submitted successfully!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error submitting enquiry: ' . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="card-title mb-4">New Legal Enquiry</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <div class="text-center mt-3">
                        <a href="<?php echo SITE_URL; ?>/client/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <?php if ($lawyer): ?>
                            <div class="alert alert-info">
                                <h5>You're requesting consultation with:</h5>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($lawyer['name']); ?></strong></p>
                                <p class="mb-1">Specialty: <?php echo htmlspecialchars($lawyer['category']); ?></p>
                                <p>Consultation Fee: ₹<?php echo number_format($lawyer['consultation_fee'], 2); ?></p>
                                <input type="hidden" name="lawyer_id" value="<?php echo $lawyer['id']; ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($lawyer && $lawyer['category'] == $category['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="budget" class="form-label">Budget (₹)</label>
                            <input type="number" class="form-control" id="budget" name="budget" 
                                   value="<?php echo $lawyer ? $lawyer['consultation_fee'] : ''; ?>">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Submit Enquiry</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
