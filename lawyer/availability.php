 <?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

$auth->requireRole('lawyer');
$pageTitle = "Update Availability";

// Get current availability
$stmt = $pdo->prepare("SELECT availability FROM lawyers WHERE user_id = ?");
$stmt->execute([$auth->getUserId()]);
$availability = $stmt->fetchColumn();

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newAvailability = isset($_POST['availability']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE lawyers SET availability = ? WHERE user_id = ?");
        $stmt->execute([$newAvailability, $auth->getUserId()]);
        $availability = $newAvailability;
        $success = 'Availability updated successfully!';
    } catch (PDOException $e) {
        $error = 'Error updating availability: ' . $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="card-title mb-4">Update Availability</h3>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="availability" name="availability" 
                               <?php echo $availability ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="availability">
                            I'm available for new cases
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Update Availability</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
