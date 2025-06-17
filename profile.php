 <?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pageTitle = "My Profile";
$error = '';
$success = '';

// Get user details
$stmt = $pdo->prepare("
    SELECT u.email, u.role, u.created_at,
           ud.first_name, ud.last_name, ud.phone, ud.address, ud.photo
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    WHERE u.id = ?
");
$stmt->execute([$auth->getUserId()]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Handle file upload
    $photo = $user['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/uploads/';
        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $targetPath = $uploadDir . $fileName;
        
        // Check file type
        $imageFileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $error = 'Only JPG, JPEG, PNG & GIF files are allowed.';
        } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
            // Delete old photo if it exists and isn't the default
            if ($photo && $photo !== 'default-avatar.jpg') {
                @unlink($uploadDir . $photo);
            }
            $photo = $fileName;
        } else {
            $error = 'Error uploading file.';
        }
    }
    
    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                UPDATE user_details 
                SET first_name = ?, last_name = ?, phone = ?, address = ?, photo = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $firstName,
                $lastName,
                $phone,
                $address,
                $photo,
                $auth->getUserId()
            ]);
            
            $success = 'Profile updated successfully!';
            // Refresh user data
            $stmt = $pdo->prepare("
                SELECT u.email, u.role, u.created_at,
                       ud.first_name, ud.last_name, ud.phone, ud.address, ud.photo
                FROM users u
                JOIN user_details ud ON u.id = ud.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$auth->getUserId()]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-body">
                <h3 class="card-title mb-4">My Profile</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <img src="<?php echo SITE_URL; ?>/assets/uploads/<?php echo htmlspecialchars($user['photo'] ?? 'default-avatar.jpg'); ?>" 
                                 class="rounded-circle mb-3" width="150" height="150" id="profilePhoto">
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <small class="text-muted">Max size: 2MB</small>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo ucfirst($user['role']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Preview image before upload
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePhoto').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>
