 <?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pageTitle = "Find Lawyers";

// Get all categories for filter
$categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);

// Build query for lawyers with filters
$where = "WHERE u.role = 'lawyer' AND u.status = 1";
$params = [];

// Filter by category
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $where .= " AND l.category_id = ?";
    $params[] = $_GET['category'];
}

// Filter by search query
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where .= " AND (ud.first_name LIKE ? OR ud.last_name LIKE ? OR c.name LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Get all lawyers with filters
$stmt = $pdo->prepare("
    SELECT u.id, CONCAT(ud.first_name, ' ', ud.last_name) as name, 
           ud.photo, l.consultation_fee, c.name as category,
           l.rating, l.experience, l.bio, l.availability
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    JOIN lawyers l ON u.id = l.user_id
    JOIN categories c ON l.category_id = c.id
    $where
    ORDER BY l.rating DESC
");
$stmt->execute($params);
$lawyers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-4">Find a Lawyer</h1>
            
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or specialty..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                <div class="col-md-5">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row">
        <?php if (count($lawyers) > 0): ?>
            <?php foreach ($lawyers as $lawyer): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <img src="<?php echo SITE_URL; ?>/assets/uploads/<?php echo $lawyer['photo'] ?? 'default-avatar.jpg'; ?>" 
                                         class="rounded-circle mb-3" width="120" height="120" alt="<?php echo htmlspecialchars($lawyer['name']); ?>">
                                    <?php if ($lawyer['availability']): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Available</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
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
                                    
                                    <p class="mb-3"><?php echo htmlspecialchars(substr($lawyer['bio'], 0, 150)); ?>...</p>
                                    
                                    <?php if ($auth->isLoggedIn() && $auth->getUserRole() === 'client'): ?>
                                        <a href="<?php echo SITE_URL; ?>/client/new-enquiry.php?lawyer=<?php echo $lawyer['id']; ?>" class="btn btn-primary">Request Consultation</a>
                                    <?php elseif (!$auth->isLoggedIn()): ?>
                                        <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">Request Consultation</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No lawyers found matching your criteria.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
