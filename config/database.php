<?php
/**
 * Database Configuration
 * Configure database connection for XAMPP localhost
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to Asia/Jakarta (WIB - UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_system');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

/**
 * Check if user is logged in (staff or member)
 */
function isLoggedIn() {
    return (isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id'])) || 
           (isset($_SESSION['member_id']) && !empty($_SESSION['member_id']));
}

/**
 * Check if logged in user is staff
 */
function isStaff() {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

/**
 * Check if logged in user is member
 */
function isMember() {
    return isset($_SESSION['member_id']) && !empty($_SESSION['member_id']);
}

/**
 * Get user type
 */
function getUserType() {
    if (isStaff()) {
        return 'staff';
    } elseif (isMember()) {
        return 'member';
    }
    return null;
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /library-system/modules/auth/login.php");
        exit();
    }
}

/**
 * Require staff access
 */
function requireStaff() {
    if (!isStaff()) {
        $_SESSION['error'] = 'Access denied. Staff privileges required.';
        header("Location: /library-system/index.php");
        exit();
    }
}

/**
 * Sanitize input data
 */
function clean($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Display alert message
 */
function showAlert($message, $type = 'info') {
    $alertClass = '';
    switch($type) {
        case 'success':
            $alertClass = 'alert-success';
            break;
        case 'error':
        case 'danger':
            $alertClass = 'alert-danger';
            break;
        case 'warning':
            $alertClass = 'alert-warning';
            break;
        default:
            $alertClass = 'alert-info';
    }
    
    return "<div class='alert {$alertClass}'>{$message}</div>";
}

/**
 * Get current date/time (with time manipulation for testing)
 * Only staff can manipulate time
 */
function getCurrentDateTime() {
    if (isStaff() && isset($_SESSION['time_offset'])) {
        return date('Y-m-d H:i:s', strtotime($_SESSION['time_offset']));
    }
    return date('Y-m-d H:i:s');
}

function getCurrentDate() {
    if (isStaff() && isset($_SESSION['time_offset'])) {
        return date('Y-m-d', strtotime($_SESSION['time_offset']));
    }
    return date('Y-m-d');
}

/**
 * Calculate fine for late returns
 * Fine rate: 5000 IDR per day
 */
function calculateFine($lateDays) {
    $finePerDay = 5000;
    return $lateDays * $finePerDay;
}

/**
 * Generate unique 4-character reservation number
 */
function generateReservationNumber() {
    global $conn;
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
    do {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Check if code already exists
        $check = $conn->prepare("SELECT reservation_id FROM reservations WHERE reservation_number = ?");
        $check->bind_param("s", $code);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        
    } while ($exists);
    
    return $code;
}

/**
 * Check and suspend members with unpaid fines over 2 weeks
 */
function checkAndSuspendMembers() {
    global $conn;
    
    // Find members with unpaid fines older than 2 weeks
    $query = "
        SELECT rt.borrow_id, bt.member_id, m.status, 
               SUM(rt.fine_amount) as total_fines,
               MIN(rt.return_date) as oldest_return
        FROM returning_transactions rt
        JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
        JOIN members_data m ON bt.member_id = m.member_id
        WHERE rt.payment_status = 'unpaid' 
        AND rt.fine_amount > 0
        AND rt.return_date <= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        AND m.status = 'active'
        GROUP BY bt.member_id
        HAVING total_fines > 0
    ";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Suspend member
            $stmt = $conn->prepare("UPDATE members_data SET status = 'suspended' WHERE member_id = ?");
            $stmt->bind_param("i", $row['member_id']);
            $stmt->execute();
            $stmt->close();
            
            // Create suspension penalty record
            $suspension_date = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("
                INSERT INTO suspension_penalties (member_id, total_unpaid_fines, suspension_date, penalty_amount)
                VALUES (?, ?, ?, 100000.00)
            ");
            $stmt->bind_param("ids", $row['member_id'], $row['total_fines'], $suspension_date);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Check and cancel expired reservations
 */
function cancelExpiredReservations() {
    global $conn;
    
    // Find expired reservations
    $query = "SELECT r.reservation_id, r.book_id 
              FROM reservations r 
              WHERE r.status = 'pending' 
              AND r.expiry_date < NOW()";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Update reservation status
            $stmt = $conn->prepare("UPDATE reservations SET status = 'expired' WHERE reservation_id = ?");
            $stmt->bind_param("i", $row['reservation_id']);
            $stmt->execute();
            $stmt->close();
            
            // Restore reserved stock
            $stmt = $conn->prepare("UPDATE books_data SET reserved_stock = reserved_stock - 1 WHERE book_id = ?");
            $stmt->bind_param("i", $row['book_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>