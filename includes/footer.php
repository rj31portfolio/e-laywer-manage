     </div> <!-- Close container div from header -->

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p>Connecting clients with qualified legal professionals.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/" class="text-white">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/lawyers.php" class="text-white">Find Lawyers</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/login.php" class="text-white">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/register.php" class="text-white">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <address>
                        <i class="fas fa-map-marker-alt me-2"></i> 123 Legal Street, Law City<br>
                        <i class="fas fa-phone me-2"></i> +1 234 567 890<br>
                        <i class="fas fa-envelope me-2"></i> contact@legalconnect.com
                    </address>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
</body>
</html>
