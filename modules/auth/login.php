<?php
require_once '../../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /library-system/index.php");
    exit();
}

$error = '';
$login_type = isset($_POST['login_type']) ? $_POST['login_type'] : 'staff';

// Process login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean($_POST['username']);
    $password = $_POST['password'];
    $login_type = clean($_POST['login_type']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        if ($login_type === 'staff') {
            // Staff Login
            $query = "SELECT staff_id, username, password, email FROM staff_data WHERE username = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $staff = $result->fetch_assoc();
                
                // Compare plain text password
                if ($password === $staff['password']) {
                    // Set session variables
                    $_SESSION['staff_id'] = $staff['staff_id'];
                    $_SESSION['staff_username'] = $staff['username'];
                    $_SESSION['staff_email'] = $staff['email'];
                    $_SESSION['user_type'] = 'staff';
                    
                    // Redirect to dashboard
                    header("Location: /library-system/index.php");
                    exit();
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
            $stmt->close();
            
        } else {
            // Member Login
            $query = "SELECT member_id, name, email, password, status FROM members_data WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $member = $result->fetch_assoc();
                
                // Check if account is active
                if ($member['status'] !== 'active') {
                    // Check if has unpaid suspension penalty
                    $penalty_check = $conn->prepare("SELECT penalty_id, penalty_amount FROM suspension_penalties WHERE member_id = ? AND payment_status = 'unpaid' ORDER BY suspension_date DESC LIMIT 1");
                    $penalty_check->bind_param("i", $member['member_id']);
                    $penalty_check->execute();
                    $penalty_result = $penalty_check->get_result();
                    
                    if ($penalty_result->num_rows > 0) {
                        $penalty = $penalty_result->fetch_assoc();
                        // Set session for payment page access
                        $_SESSION['suspended_member_id'] = $member['member_id'];
                        $_SESSION['suspended_member_email'] = $member['email'];
                        $_SESSION['penalty_amount'] = $penalty['penalty_amount'];
                        
                        $error = 'suspended_with_penalty';
                    } else {
                        $error = 'Your account has been suspended. Please contact library staff.';
                    }
                    $penalty_check->close();
                } elseif ($password === $member['password']) {
                    // Set session variables
                    $_SESSION['member_id'] = $member['member_id'];
                    $_SESSION['member_name'] = $member['name'];
                    $_SESSION['member_email'] = $member['email'];
                    $_SESSION['user_type'] = 'member';
                    
                    // Redirect to dashboard
                    header("Location: /library-system/index.php");
                    exit();
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library System</title>
    <link rel="stylesheet" href="/library-system/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>📚 Library System</h2>
            <h3 style="text-align: center; margin-bottom: 2rem; color: #666;">User Login</h3>
            
            <?php if (!empty($error)): ?>
                <?php if ($error === 'suspended_with_penalty'): ?>
                    <!-- Suspended Account with Payment Option -->
                    <div class="alert alert-danger" style="text-align: center;">
                        <h4 style="margin: 0 0 1rem 0; color: #721c24;">⚠️ Account Suspended</h4>
                        <p style="margin: 0 0 1rem 0;">
                            Your account has been suspended due to unpaid fines.<br>
                            <strong>Suspension Penalty: Rp <?php echo number_format($_SESSION['penalty_amount'], 0, ',', '.'); ?></strong>
                        </p>
                        <a href="../../modules/auth/suspended_payment.php" class="btn btn-warning" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                            💳 Pay Fine to Restore Access
                        </a>
                        <p style="margin: 1rem 0 0 0; font-size: 0.875rem; color: #721c24;">
                            After payment, you can login immediately.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Login Type Tabs -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
                <button type="button" onclick="switchTab('staff')" id="staffTab" 
                        style="flex: 1; padding: 0.75rem; border: 2px solid #667eea; background: #667eea; color: white; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    👤 Staff Login
                </button>
                <button type="button" onclick="switchTab('member')" id="memberTab"
                        style="flex: 1; padding: 0.75rem; border: 2px solid #667eea; background: white; color: #667eea; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    👥 Member Login
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="login_type" id="login_type" value="<?php echo $login_type; ?>">
                
                <div class="form-group">
                    <label for="username" id="usernameLabel">Username</label>
                    <input type="text" name="username" id="username" class="form-control" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(type) {
            const staffTab = document.getElementById('staffTab');
            const memberTab = document.getElementById('memberTab');
            const loginType = document.getElementById('login_type');
            const usernameLabel = document.getElementById('usernameLabel');
            const usernameInput = document.getElementById('username');
            const staffCredentials = document.getElementById('staffCredentials');
            const memberCredentials = document.getElementById('memberCredentials');
            
            if (type === 'staff') {
                staffTab.style.background = '#667eea';
                staffTab.style.color = 'white';
                memberTab.style.background = 'white';
                memberTab.style.color = '#667eea';
                loginType.value = 'staff';
                usernameLabel.textContent = 'Username';
                usernameInput.placeholder = '';
                staffCredentials.style.display = 'block';
                memberCredentials.style.display = 'none';
            } else {
                memberTab.style.background = '#667eea';
                memberTab.style.color = 'white';
                staffTab.style.background = 'white';
                staffTab.style.color = '#667eea';
                loginType.value = 'member';
                usernameLabel.textContent = 'Email Address';
                usernameInput.placeholder = 'your@email.com';
                staffCredentials.style.display = 'none';
                memberCredentials.style.display = 'block';
            }
        }
        
        // Set initial tab based on PHP value
        window.onload = function() {
            switchTab('<?php echo $login_type; ?>');
        };
    </script>
</body>
</html>