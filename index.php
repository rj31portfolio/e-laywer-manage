 <?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pageTitle = "Home";

// Get featured lawyers
$stmt = $pdo->query("
    SELECT u.id, CONCAT(ud.first_name, ' ', ud.last_name) as name, 
           ud.photo, l.consultation_fee, c.name as category,
           l.rating, l.experience
    FROM users u
    JOIN user_details ud ON u.id = ud.user_id
    JOIN lawyers l ON u.id = l.user_id
    JOIN categories c ON l.category_id = c.id
    WHERE u.role = 'lawyer' AND u.status = 1
    ORDER BY l.rating DESC LIMIT 6
");
$featuredLawyers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<div class="hero-section bg-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Find the Right Lawyer for Your Legal Issue</h1>
                <p class="lead mb-4">Connect with experienced legal professionals who can help you with your specific needs.</p>
                <a href="<?php echo SITE_URL; ?>/lawyers.php" class="btn btn-light btn-lg me-2">Browse Lawyers</a>
                <?php if (!$auth->isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-outline-light btn-lg">Get Started</a>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <img src="<?php echo SITE_URL; ?>/assets/images/law-hero.png" alt="Legal Consultation" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<div class="container">
    <section class="mb-5">
        <h2 class="text-center mb-5">How It Works</h2>
        <div class="row text-center">
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-3 mx-auto" style="width: 70px; height: 70px;">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                        <h4>Find a Lawyer</h4>
                        <p>Browse our directory of qualified legal professionals by specialty, location, or rating.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-3 mx-auto" style="width: 70px; height: 70px;">
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                        <h4>Book Consultation</h4>
                        <p>Select a lawyer that fits your needs and budget, then book a consultation.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-3 mx-auto" style="width: 70px; height: 70px;">
                            <i class="fas fa-gavel fa-2x"></i>
                        </div>
                        <h4>Get Legal Help</h4>
                        <p>Connect with your lawyer and get the legal assistance you need.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Featured Lawyers</h2>
            <a href="<?php echo SITE_URL; ?>/lawyers.php" class="btn btn-outline-primary">View All</a>
        </div>
        
        <div class="row">
            <?php foreach ($featuredLawyers as $lawyer): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <img src="<?php echo SITE_URL; ?>/assets/uploads/<?php echo $lawyer['photo'] ?? 'default-avatar.jpg'; ?>" 
                                 class="rounded-circle mb-3" width="120" height="120" alt="<?php echo htmlspecialchars($lawyer['name']); ?>">
                            <h5><?php echo htmlspecialchars($lawyer['name']); ?></h5>
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
                            
                            <?php if ($auth->isLoggedIn() && $auth->getUserRole() === 'client'): ?>
                                <a href="<?php echo SITE_URL; ?>/client/new-enquiry.php?lawyer=<?php echo $lawyer['id']; ?>" class="btn btn-primary w-100">Request Consultation</a>
                            <?php elseif (!$auth->isLoggedIn()): ?>
                                <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary w-100">Request Consultation</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-light rounded p-5 mb-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2>Are You a Lawyer?</h2>
                <p class="lead">Join our platform to connect with clients who need your expertise.</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-primary me-2"></i> Grow your client base</li>
                    <li class="mb-2"><i class="fas fa-check text-primary me-2"></i> Set your own schedule</li>
                    <li class="mb-2"><i class="fas fa-check text-primary me-2"></i> Get paid for your services</li>
                </ul>
                <a href="<?php echo SITE_URL; ?>/register.php?role=lawyer" class="btn btn-primary btn-lg">Join as a Lawyer</a>
            </div>
            <div class="col-lg-6">
                <img src="<?php echo SITE_URL; ?>/assets/images/lawyer-join.png" alt="Join as Lawyer" class="img-fluid rounded">
            </div>
        </div>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?>
