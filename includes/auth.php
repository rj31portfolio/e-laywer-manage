 <?php
require_once 'config.php';
require_once 'db.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function register($email, $password, $role, $firstName, $lastName, $phone = null) {
        // Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        // Check if email exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        try {
            $this->pdo->beginTransaction();
            
            // Insert into users table
            $stmt = $this->pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$email, $hashedPassword, $role]);
            $userId = $this->pdo->lastInsertId();
            
            // Insert into user_details table
            $stmt = $this->pdo->prepare("INSERT INTO user_details (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $firstName, $lastName, $phone]);
            
            // If role is lawyer, insert into lawyers table
            if ($role == 'lawyer') {
                $stmt = $this->pdo->prepare("INSERT INTO lawyers (user_id, category_id, consultation_fee) VALUES (?, ?, ?)");
                $stmt->execute([$userId, 1, 0]); // Default category and fee
            }
            
            $this->pdo->commit();
            
            return ['success' => true, 'user_id' => $userId];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        $stmt = $this->pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ? AND status = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found or account disabled'];
        }
        
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            return ['success' => true, 'role' => $user['role']];
        } else {
            return ['success' => false, 'message' => 'Incorrect password'];
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function logout() {
        $_SESSION = array();
        session_destroy();
    }
    
    public function redirectBasedOnRole() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . SITE_URL . '/login.php');
            exit;
        }
        
        $role = $this->getUserRole();
        switch ($role) {
            case 'superadmin':
                header('Location: ' . SITE_URL . '/superadmin/dashboard.php');
                break;
            case 'admin':
                header('Location: ' . SITE_URL . '/admin/dashboard.php');
                break;
            case 'lawyer':
                header('Location: ' . SITE_URL . '/lawyer/dashboard.php');
                break;
            case 'client':
                header('Location: ' . SITE_URL . '/client/dashboard.php');
                break;
            default:
                header('Location: ' . SITE_URL . '/index.php');
        }
        exit;
    }
    
    public function requireRole($requiredRole) {
        if (!$this->isLoggedIn() || $this->getUserRole() !== $requiredRole) {
            header('Location: ' . SITE_URL . '/login.php');
            exit;
        }
    }
}

// Initialize Auth
$auth = new Auth($pdo);
?>
