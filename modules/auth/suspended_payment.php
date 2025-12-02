<?php
require_once '../../config/database.php';

// Check if coming from suspended login
if (!isset($_SESSION['suspended_member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['suspended_member_id'];
$member_email = $_SESSION['suspended_member_email'];

// Get member and penalty details
$member_query = $conn->prepare("SELECT * FROM members_data WHERE member_id = ?");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();
$member_query->close();

$penalty_query = $conn->prepare("
    SELECT * FROM suspension_penalties 
    WHERE member_id = ? AND payment_status = 'unpaid' 
    ORDER BY suspension_date DESC LIMIT 1
");
$penalty_query->bind_param("i", $member_id);
$penalty_query->execute();
$penalty = $penalty_query->get_result()->fetch_assoc();
$penalty_query->close();

if (!$penalty) {
    $_SESSION['error'] = 'No pending suspension penalty found.';
    header("Location: login.php");
    exit();
}

// Get unpaid fines
$fines_query = $conn->prepare("
    SELECT rt.*, b.title
    FROM returning_transactions rt
    JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND rt.payment_status = 'unpaid' AND rt.fine_amount > 0
");
$fines_query->bind_param("i", $member_id);
$fines_query->execute();
$fines = $fines_query->get_result();

// Handle payment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = clean($_POST['payment_method']);
    
    $conn->begin_transaction();
    
    try {
        // Update penalty payment
        $payment_date = date('Y-m-d H:i:s');
        $unsuspension_date = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("
            UPDATE suspension_penalties 
            SET payment_status = 'paid', payment_method = ?, payment_date = ?, unsuspension_date = ? 
            WHERE penalty_id = ?
        ");
        $stmt->bind_param("sssi", $payment_method, $payment_date, $unsuspension_date, $penalty['penalty_id']);
        $stmt->execute();
        $stmt->close();
        
        // Unsuspend member
        $stmt = $conn->prepare("UPDATE members_data SET status = 'active' WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Clear suspension session
        unset($_SESSION['suspended_member_id']);
        unset($_SESSION['suspended_member_email']);
        unset($_SESSION['penalty_amount']);
        
        $_SESSION['success'] = 'Payment successful! Your account has been reactivated. You can now login.';
        header("Location: login.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Payment failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Suspension Penalty - Library System</title>
    <link rel="stylesheet" href="/library-system/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box" style="max-width: 600px;">
            <h2>📚 Library System</h2>
            <h3 style="text-align: center; margin-bottom: 2rem; color: #dc3545;">⚠️ Account Suspended</h3>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <!-- Member Info -->
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
                <p style="margin: 0 0 0.5rem 0;"><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                <p style="margin: 0;"><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
            </div>
            
            <!-- Suspension Details -->
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 0.5rem 0; color: #856404;">Suspension Details</h4>
                <p style="margin: 0 0 0.5rem 0;">
                    <strong>Suspended On:</strong> <?php echo date('d M Y, H:i', strtotime($penalty['suspension_date'])); ?>
                </p>
                <p style="margin: 0;">
                    <strong>Total Unpaid Fines at Suspension:</strong> 
                    Rp <?php echo number_format($penalty['total_unpaid_fines'], 0, ',', '.'); ?>
                </p>
            </div>
            
            <!-- Unpaid Fines List -->
            <?php if ($fines->num_rows > 0): ?>
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 1rem; margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 0.5rem 0; color: #721c24;">Outstanding Fines</h4>
                    <ul style="margin: 0; padding-left: 20px; font-size: 0.875rem;">
                        <?php while ($fine = $fines->fetch_assoc()): ?>
                            <li><?php echo htmlspecialchars($fine['title']); ?>: 
                                Rp <?php echo number_format($fine['fine_amount'], 0, ',', '.'); ?></li>
                        <?php endwhile; ?>
                    </ul>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #721c24;">
                        <strong>Note:</strong> These fines must still be paid separately after reactivation.
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Penalty Payment -->
            <div style="background: #dc3545; color: white; padding: 1.5rem; border-radius: 5px; margin-bottom: 1.5rem; text-align: center;">
                <h3 style="margin: 0 0 0.5rem 0;">Suspension Penalty</h3>
                <div style="font-size: 2.5rem; font-weight: bold; margin: 1rem 0;">
                    Rp <?php echo number_format($penalty['penalty_amount'], 0, ',', '.'); ?>
                </div>
                <p style="margin: 0; font-size: 0.875rem; opacity: 0.9;">
                    Pay this amount to restore your account access
                </p>
            </div>
            
            <!-- Payment Form -->
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Payment Method: *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="">-- Choose Method --</option>
                        <option value="Cash">💵 Cash at Library Counter</option>
                        <option value="Bank Transfer">🏦 Bank Transfer (BCA 1234567890)</option>
                        <option value="GoPay">📱 GoPay (0812-3456-7890)</option>
                        <option value="OVO">📱 OVO (0812-3456-7890)</option>
                        <option value="Dana">📱 Dana (0812-3456-7890)</option>
                        <option value="QRIS">📱 QRIS (Scan at Counter)</option>
                    </select>
                </div>
                
                <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.875rem;">
                    <strong>📝 Important:</strong><br>
                    • This is a dummy payment system for assignment purposes<br>
                    • Click "Pay Now" to simulate successful payment<br>
                    • Your account will be reactivated immediately<br>
                    • You can then login normally
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                    ✓ Pay Now & Reactivate Account
                </button>
                
                <a href="login.php" class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block; padding: 0.75rem;">
                    ← Back to Login
                </a>
            </form>
        </div>
    </div>
</body>
</html>