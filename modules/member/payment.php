<?php
$pageTitle = 'My Payments';
require_once '../../includes/header.php';

// Ensure only members can access
if (!isMember()) {
    $_SESSION['error'] = 'Access denied.';
    header("Location: /library-system/index.php");
    exit();
}

$member_id = $_SESSION['member_id'];

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_fine'])) {
    $return_id = intval($_POST['return_id']);
    $payment_method = clean($_POST['payment_method']);
    
    // Verify ownership
    $check = $conn->prepare("
        SELECT rt.* FROM returning_transactions rt
        JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
        WHERE rt.return_id = ? AND bt.member_id = ? AND rt.payment_status = 'unpaid'
    ");
    $check->bind_param("ii", $return_id, $member_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update payment status
        $payment_date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE returning_transactions SET payment_status = 'paid', payment_method = ?, payment_date = ? WHERE return_id = ?");
        $stmt->bind_param("ssi", $payment_method, $payment_date, $return_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Payment confirmed! Thank you for paying your fine.';
        } else {
            $_SESSION['error'] = 'Failed to process payment.';
        }
        $stmt->close();
    }
    $check->close();
    
    header("Location: payment.php");
    exit();
}

// Handle suspension penalty payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_penalty'])) {
    $penalty_id = intval($_POST['penalty_id']);
    $payment_method = clean($_POST['payment_method']);
    
    // Verify ownership
    $check = $conn->prepare("SELECT * FROM suspension_penalties WHERE penalty_id = ? AND member_id = ? AND payment_status = 'unpaid'");
    $check->bind_param("ii", $penalty_id, $member_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $conn->begin_transaction();
        
        try {
            // Update penalty payment
            $payment_date = date('Y-m-d H:i:s');
            $unsuspension_date = date('Y-m-d H:i:s');
            
            $stmt = $conn->prepare("UPDATE suspension_penalties SET payment_status = 'paid', payment_method = ?, payment_date = ?, unsuspension_date = ? WHERE penalty_id = ?");
            $stmt->bind_param("sssi", $payment_method, $payment_date, $unsuspension_date, $penalty_id);
            $stmt->execute();
            $stmt->close();
            
            // Unsuspend member account
            $stmt = $conn->prepare("UPDATE members_data SET status = 'active' WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            $_SESSION['success'] = 'Suspension penalty paid! Your account is now active again.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to process penalty payment.';
        }
    }
    $check->close();
    
    header("Location: payment.php");
    exit();
}

// Get unpaid fines
$unpaid_fines = $conn->prepare("
    SELECT rt.*, bt.borrow_id, b.title, b.author, bt.borrow_date, bt.due_date,
           DATEDIFF(CURDATE(), rt.return_date) as days_since_return
    FROM returning_transactions rt
    JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND rt.payment_status = 'unpaid' AND rt.fine_amount > 0
    ORDER BY rt.return_date ASC
");
$unpaid_fines->bind_param("i", $member_id);
$unpaid_fines->execute();
$unpaid_result = $unpaid_fines->get_result();

// Get paid fines history
$paid_fines = $conn->prepare("
    SELECT rt.*, bt.borrow_id, b.title, b.author
    FROM returning_transactions rt
    JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND rt.payment_status = 'paid' AND rt.fine_amount > 0
    ORDER BY rt.payment_date DESC
    LIMIT 10
");
$paid_fines->bind_param("i", $member_id);
$paid_fines->execute();
$paid_result = $paid_fines->get_result();

// Get suspension penalties
$penalties = $conn->prepare("
    SELECT * FROM suspension_penalties 
    WHERE member_id = ? 
    ORDER BY suspension_date DESC
");
$penalties->bind_param("i", $member_id);
$penalties->execute();
$penalties_result = $penalties->get_result();

// Calculate totals
$total_unpaid = 0;
mysqli_data_seek($unpaid_result, 0);
while ($row = $unpaid_result->fetch_assoc()) {
    $total_unpaid += $row['fine_amount'];
}
mysqli_data_seek($unpaid_result, 0);
?>

<h1>💳 My Payments</h1>

<!-- Statistics -->
<div class="stats-grid">
    <?php
    $stats = $conn->prepare("
        SELECT 
            SUM(CASE WHEN payment_status = 'unpaid' THEN fine_amount ELSE 0 END) as total_unpaid,
            SUM(CASE WHEN payment_status = 'paid' THEN fine_amount ELSE 0 END) as total_paid,
            COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_count
        FROM returning_transactions rt
        JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
        WHERE bt.member_id = ? AND fine_amount > 0
    ");
    $stats->bind_param("i", $member_id);
    $stats->execute();
    $stats_data = $stats->get_result()->fetch_assoc();
    ?>
    
    <div class="stat-card red">
        <h4>Unpaid Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($stats_data['total_unpaid'] ?? 0, 0, ',', '.'); ?>
        </div>
        <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;"><?php echo $stats_data['unpaid_count']; ?> transaction(s)</p>
    </div>
    
    <div class="stat-card green">
        <h4>Total Paid</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($stats_data['total_paid'] ?? 0, 0, ',', '.'); ?>
        </div>
    </div>
</div>

<!-- Suspension Penalties -->
<?php if ($penalties_result->num_rows > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3>⚠️ Suspension Penalties</h3>
        </div>
        
        <?php while ($penalty = $penalties_result->fetch_assoc()): ?>
            <div style="background: <?php echo $penalty['payment_status'] == 'paid' ? '#d4edda' : '#f8d7da'; ?>; padding: 1.5rem; margin-bottom: 1rem; border-radius: 5px;">
                <h4 style="margin: 0 0 1rem 0; color: <?php echo $penalty['payment_status'] == 'paid' ? '#155724' : '#721c24'; ?>;">
                    <?php echo $penalty['payment_status'] == 'paid' ? '✓ Penalty Paid' : '⚠️ Account Suspended'; ?>
                </h4>
                
                <p><strong>Suspension Date:</strong> <?php echo date('d M Y, H:i', strtotime($penalty['suspension_date'])); ?></p>
                <p><strong>Unpaid Fines at Suspension:</strong> Rp <?php echo number_format($penalty['total_unpaid_fines'], 0, ',', '.'); ?></p>
                <p><strong>Penalty Amount:</strong> <span style="font-size: 1.2rem; color: #dc3545;">Rp <?php echo number_format($penalty['penalty_amount'], 0, ',', '.'); ?></span></p>
                
                <?php if ($penalty['payment_status'] == 'unpaid'): ?>
                    <form method="POST" action="" style="margin-top: 1rem;">
                        <input type="hidden" name="penalty_id" value="<?php echo $penalty['penalty_id']; ?>">
                        
                        <div class="form-group">
                            <label>Select Payment Method:</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="">-- Choose Method --</option>
                                <option value="Cash">Cash at Library</option>
                                <option value="Bank Transfer">Bank Transfer (BCA 1234567890)</option>
                                <option value="GoPay">GoPay (0812-3456-7890)</option>
                                <option value="OVO">OVO (0812-3456-7890)</option>
                                <option value="Dana">Dana (0812-3456-7890)</option>
                                <option value="QRIS">QRIS</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="pay_penalty" class="btn btn-success" 
                                onclick="return confirm('Confirm payment of Rp 100,000?\n\nNote: This is a dummy payment. In real system, this would require actual payment verification.')">
                            💳 Pay Penalty (Rp 100,000)
                        </button>
                    </form>
                <?php else: ?>
                    <p style="margin-top: 1rem; color: #155724;">
                        <strong>✓ Paid on:</strong> <?php echo date('d M Y, H:i', strtotime($penalty['payment_date'])); ?> 
                        via <?php echo $penalty['payment_method']; ?>
                    </p>
                    <p><strong>Account Reactivated:</strong> <?php echo date('d M Y, H:i', strtotime($penalty['unsuspension_date'])); ?></p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<!-- Unpaid Fines -->
<div class="card">
    <div class="card-header">
        <h3>⚠️ Unpaid Fines (Action Required)</h3>
    </div>
    
    <?php if ($unpaid_result->num_rows > 0): ?>
        <div class="alert alert-danger">
            <strong>⚠️ Payment Required:</strong> Please pay your fines within 14 days to avoid automatic suspension + Rp 100,000 penalty.
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Return Date</th>
                        <th>Days Late</th>
                        <th>Fine Amount</th>
                        <th>Days Unpaid</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $unpaid_result->fetch_assoc()): ?>
                        <?php $is_critical = $row['days_since_return'] >= 14; ?>
                        <tr style="<?php echo $is_critical ? 'background: #f8d7da;' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <small><?php echo htmlspecialchars($row['author']); ?></small>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['return_date'])); ?></td>
                            <td><?php echo $row['late_days']; ?> days</td>
                            <td>
                                <strong style="color: #dc3545; font-size: 1.1rem;">
                                    Rp <?php echo number_format($row['fine_amount'], 0, ',', '.'); ?>
                                </strong>
                            </td>
                            <td>
                                <strong style="color: <?php echo $is_critical ? '#dc3545' : '#856404'; ?>;">
                                    <?php echo $row['days_since_return']; ?> days
                                </strong>
                                <?php if ($is_critical): ?>
                                    <br><span class="badge badge-danger">⚠️ Critical!</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="openPaymentModal(<?php echo $row['return_id']; ?>, <?php echo $row['fine_amount']; ?>, '<?php echo htmlspecialchars($row['title']); ?>')">
                                    💳 Pay Now
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div style="background: #fff3cd; padding: 1rem; margin-top: 1rem; border-radius: 5px;">
            <h5 style="margin: 0 0 0.5rem 0;">💳 Available Payment Methods:</h5>
            <ul style="margin: 0; font-size: 0.875rem;">
                <li><strong>Cash:</strong> Pay directly at library counter</li>
                <li><strong>Bank Transfer:</strong> BCA 1234567890 (a.n. Perpustakaan)</li>
                <li><strong>E-Wallet:</strong> GoPay/OVO/Dana 0812-3456-7890</li>
                <li><strong>QRIS:</strong> Available at library counter</li>
            </ul>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <p style="font-size: 1.1rem; color: #28a745;">✓ No unpaid fines. All clear!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        <h3>✓ Payment History</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Fine Amount</th>
                    <th>Payment Date</th>
                    <th>Payment Method</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paid_result->num_rows > 0): ?>
                    <?php while ($row = $paid_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td>Rp <?php echo number_format($row['fine_amount'], 0, ',', '.'); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($row['payment_date'])); ?></td>
                            <td><span class="badge badge-success"><?php echo htmlspecialchars($row['payment_method']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No payment history yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
        <h3 style="margin: 0 0 1rem 0;">💳 Payment Confirmation</h3>
        
        <div id="paymentDetails" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
            <!-- Will be filled by JavaScript -->
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="return_id" id="modalReturnId">
            
            <div class="form-group">
                <label>Select Payment Method: *</label>
                <select name="payment_method" class="form-control" required>
                    <option value="">-- Choose Method --</option>
                    <option value="Cash">💵 Cash at Library</option>
                    <option value="Bank Transfer">🏦 Bank Transfer (BCA 1234567890)</option>
                    <option value="GoPay">📱 GoPay (0812-3456-7890)</option>
                    <option value="OVO">📱 OVO (0812-3456-7890)</option>
                    <option value="Dana">📱 Dana (0812-3456-7890)</option>
                    <option value="QRIS">📱 QRIS</option>
                </select>
            </div>
            
            <div class="alert alert-info" style="font-size: 0.875rem;">
                <strong>📝 Note:</strong> This is a dummy payment system for assignment purposes. 
                In reality, you would need actual payment verification.
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" name="pay_fine" class="btn btn-success" style="flex: 1;">
                    ✓ Confirm Payment
                </button>
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(returnId, amount, bookTitle) {
    document.getElementById('modalReturnId').value = returnId;
    document.getElementById('paymentDetails').innerHTML = `
        <p style="margin: 0 0 0.5rem 0;"><strong>Book:</strong> ${bookTitle}</p>
        <p style="margin: 0;"><strong>Amount to Pay:</strong> <span style="color: #dc3545; font-size: 1.2rem; font-weight: bold;">Rp ${amount.toLocaleString('id-ID')}</span></p>
    `;
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>