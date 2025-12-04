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

// Handle late return fine payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_fine'])) {
    $return_id = intval($_POST['return_id']);
    $payment_method = clean($_POST['payment_method']);
    
    $check = $conn->prepare("
        SELECT rt.* FROM returning_transactions rt
        JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
        WHERE rt.return_id = ? AND bt.member_id = ? AND rt.payment_status = 'unpaid'
    ");
    $check->bind_param("ii", $return_id, $member_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $payment_date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE returning_transactions SET payment_status = 'paid', payment_method = ?, payment_date = ? WHERE return_id = ?");
        $stmt->bind_param("ssi", $payment_method, $payment_date, $return_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Late return fine payment confirmed! Thank you.';
        } else {
            $_SESSION['error'] = 'Failed to process payment.';
        }
        $stmt->close();
    }
    $check->close();
    
    header("Location: payment.php");
    exit();
}

// Handle damage fine payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_damage'])) {
    $damage_id = intval($_POST['damage_id']);
    $payment_method = clean($_POST['payment_method']);
    
    $check = $conn->prepare("
        SELECT bdr.* FROM book_damage_records bdr
        JOIN borrowing_transactions bt ON bdr.borrow_id = bt.borrow_id
        WHERE bdr.damage_id = ? AND bt.member_id = ? AND bdr.payment_status = 'unpaid'
    ");
    $check->bind_param("ii", $damage_id, $member_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $payment_date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE book_damage_records SET payment_status = 'paid', payment_method = ?, payment_date = ? WHERE damage_id = ?");
        $stmt->bind_param("ssi", $payment_method, $payment_date, $damage_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Damage fine payment confirmed! Thank you.';
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
    
    $check = $conn->prepare("SELECT * FROM suspension_penalties WHERE penalty_id = ? AND member_id = ? AND payment_status = 'unpaid'");
    $check->bind_param("ii", $penalty_id, $member_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $conn->begin_transaction();
        
        try {
            $payment_date = date('Y-m-d H:i:s');
            $unsuspension_date = date('Y-m-d H:i:s');
            
            $stmt = $conn->prepare("UPDATE suspension_penalties SET payment_status = 'paid', payment_method = ?, payment_date = ?, unsuspension_date = ? WHERE penalty_id = ?");
            $stmt->bind_param("sssi", $payment_method, $payment_date, $unsuspension_date, $penalty_id);
            $stmt->execute();
            $stmt->close();
            
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

// Get CURRENT OVERDUE BOOKS (not yet returned)
$current_overdue = $conn->prepare("
    SELECT bt.borrow_id, b.title, b.author, bt.borrow_date, bt.due_date,
           DATEDIFF(CURDATE(), bt.due_date) as days_overdue
    FROM borrowing_transactions bt
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND bt.status = 'borrowed' AND bt.due_date < CURDATE()
    ORDER BY bt.due_date ASC
");
$current_overdue->bind_param("i", $member_id);
$current_overdue->execute();
$current_overdue_result = $current_overdue->get_result();

// Get unpaid LATE RETURN fines
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

// Get unpaid DAMAGE fines
$unpaid_damage = $conn->prepare("
    SELECT bdr.*, bt.borrow_id, b.title, b.author, dt.damage_name,
           DATEDIFF(CURDATE(), bdr.damage_date) as days_since_damage
    FROM book_damage_records bdr
    JOIN borrowing_transactions bt ON bdr.borrow_id = bt.borrow_id
    JOIN books_data b ON bt.book_id = b.book_id
    JOIN damage_types dt ON bdr.damage_type_id = dt.damage_type_id
    WHERE bt.member_id = ? AND bdr.payment_status = 'unpaid'
    ORDER BY bdr.damage_date ASC
");
$unpaid_damage->bind_param("i", $member_id);
$unpaid_damage->execute();
$unpaid_damage_result = $unpaid_damage->get_result();

// Get paid fines history (both late and damage)
$paid_history = $conn->prepare("
    SELECT 'late_return' as type, rt.fine_amount as amount, rt.payment_date, b.title
    FROM returning_transactions rt
    JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND rt.payment_status = 'paid' AND rt.fine_amount > 0
    
    UNION ALL
    
    SELECT 'damage' as type, bdr.damage_fine as amount, bdr.payment_date, b.title
    FROM book_damage_records bdr
    JOIN borrowing_transactions bt ON bdr.borrow_id = bt.borrow_id
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND bdr.payment_status = 'paid'
    
    ORDER BY payment_date DESC
    LIMIT 10
");
$paid_history->bind_param("ii", $member_id, $member_id);
$paid_history->execute();
$paid_result = $paid_history->get_result();

// Get suspension penalties
$penalties = $conn->prepare("SELECT * FROM suspension_penalties WHERE member_id = ? ORDER BY suspension_date DESC");
$penalties->bind_param("i", $member_id);
$penalties->execute();
$penalties_result = $penalties->get_result();

// Calculate totals
$total_unpaid_late = 0;
$total_unpaid_damage = 0;
$total_estimated_overdue = 0;

mysqli_data_seek($unpaid_result, 0);
while ($row = $unpaid_result->fetch_assoc()) {
    $total_unpaid_late += $row['fine_amount'];
}
mysqli_data_seek($unpaid_result, 0);

mysqli_data_seek($unpaid_damage_result, 0);
while ($row = $unpaid_damage_result->fetch_assoc()) {
    $total_unpaid_damage += $row['damage_fine'];
}
mysqli_data_seek($unpaid_damage_result, 0);

mysqli_data_seek($current_overdue_result, 0);
while ($row = $current_overdue_result->fetch_assoc()) {
    if ($row['days_overdue'] > 0) {
        $total_estimated_overdue += calculateFine($row['days_overdue']);
    }
}
mysqli_data_seek($current_overdue_result, 0);

$total_paid = $conn->prepare("
    SELECT 
        COALESCE(SUM(rt.fine_amount), 0) + COALESCE(SUM(bdr.damage_fine), 0) as total
    FROM borrowing_transactions bt
    LEFT JOIN returning_transactions rt ON bt.borrow_id = rt.borrow_id AND rt.payment_status = 'paid'
    LEFT JOIN book_damage_records bdr ON bt.borrow_id = bdr.borrow_id AND bdr.payment_status = 'paid'
    WHERE bt.member_id = ?
");
$total_paid->bind_param("i", $member_id);
$total_paid->execute();
$total_paid_amount = $total_paid->get_result()->fetch_assoc()['total'];
?>

<h1>💳 My Payments</h1>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card orange">
        <h4>Estimated Overdue Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_estimated_overdue, 0, ',', '.'); ?>
        </div>
        <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">From books not yet returned</p>
    </div>
    
    <div class="stat-card red">
        <h4>Unpaid Late Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_unpaid_late, 0, ',', '.'); ?>
        </div>
    </div>
    
    <div class="stat-card red">
        <h4>Unpaid Damage Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_unpaid_damage, 0, ',', '.'); ?>
        </div>
    </div>
    
    <div class="stat-card green">
        <h4>Total Paid</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_paid_amount, 0, ',', '.'); ?>
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
                <p><strong>Unpaid Late Fines:</strong> Rp <?php echo number_format($penalty['total_unpaid_fines'], 0, ',', '.'); ?></p>
                <p><strong>Unpaid Damage Fines:</strong> Rp <?php echo number_format($penalty['total_damage_fines'], 0, ',', '.'); ?></p>
                <p><strong>Penalty Amount:</strong> <span style="font-size: 1.2rem; color: #dc3545;">Rp <?php echo number_format($penalty['penalty_amount'], 0, ',', '.'); ?></span></p>
                
                <?php if ($penalty['payment_status'] == 'unpaid'): ?>
                    <form method="POST" action="" style="margin-top: 1rem;">
                        <input type="hidden" name="penalty_id" value="<?php echo $penalty['penalty_id']; ?>">
                        
                        <div class="form-group">
                            <label>Select Payment Method:</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="">-- Choose Method --</option>
                                <option value="Cash">Cash at Library</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="GoPay">GoPay</option>
                                <option value="OVO">OVO</option>
                                <option value="Dana">Dana</option>
                                <option value="QRIS">QRIS</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="pay_penalty" class="btn btn-success">💳 Pay Penalty</button>
                    </form>
                <?php else: ?>
                    <p style="margin-top: 1rem; color: #155724;">
                        <strong>✓ Paid on:</strong> <?php echo date('d M Y, H:i', strtotime($penalty['payment_date'])); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<!-- CURRENT OVERDUE BOOKS -->
<?php if ($current_overdue_result->num_rows > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3>⏰ Current Overdue Books (Estimated Fines)</h3>
        </div>
        
        <div class="alert alert-warning">
            <strong>📚 Books Not Yet Returned:</strong> These fines will be charged when you return the books.
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Estimated Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $current_overdue_result->fetch_assoc()): ?>
                        <?php $estimated_fine = calculateFine($row['days_overdue']); ?>
                        <tr style="background: #fff3cd;">
                            <td>
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <small><?php echo htmlspecialchars($row['author']); ?></small>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['due_date'])); ?></td>
                            <td><strong style="color: #dc3545;"><?php echo $row['days_overdue']; ?> days</strong></td>
                            <td><strong style="color: #dc3545;">Rp <?php echo number_format($estimated_fine, 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Unpaid Late Return Fines -->
<?php if ($unpaid_result->num_rows > 0): ?>
<div class="card">
    <div class="card-header">
        <h3>⚠️ Unpaid Late Return Fines</h3>
    </div>
    
    <div class="alert alert-danger">
        <strong>⚠️ Payment Required:</strong> Pay within 14 days to avoid suspension + Rp 100,000 penalty.
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
                        <td><strong style="color: #dc3545;">Rp <?php echo number_format($row['fine_amount'], 0, ',', '.'); ?></strong></td>
                        <td>
                            <strong style="color: <?php echo $is_critical ? '#dc3545' : '#856404'; ?>;"><?php echo $row['days_since_return']; ?> days</strong>
                            <?php if ($is_critical): ?><br><span class="badge badge-danger">⚠️ Critical!</span><?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-success btn-sm" onclick="openPaymentModal(<?php echo $row['return_id']; ?>, <?php echo $row['fine_amount']; ?>, '<?php echo htmlspecialchars($row['title']); ?>', 'late')">💳 Pay Now</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Unpaid Damage Fines -->
<?php if ($unpaid_damage_result->num_rows > 0): ?>
<div class="card">
    <div class="card-header">
        <h3>🔨 Unpaid Book Damage Fines</h3>
    </div>
    
    <div class="alert alert-danger">
        <strong>⚠️ Book Damage Charges:</strong> Pay within 14 days to avoid suspension.
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Damage Type</th>
                    <th>Damage Date</th>
                    <th>Fine Amount</th>
                    <th>Days Unpaid</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $unpaid_damage_result->fetch_assoc()): ?>
                    <?php $is_critical = $row['days_since_damage'] >= 14; ?>
                    <tr style="<?php echo $is_critical ? 'background: #f8d7da;' : 'background: #fff3cd;'; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                            <small><?php echo htmlspecialchars($row['author']); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-warning"><?php echo htmlspecialchars($row['damage_name']); ?></span>
                            <?php if (!empty($row['damage_notes'])): ?>
                                <br><small style="color: #666;" title="<?php echo htmlspecialchars($row['damage_notes']); ?>">
                                    📝 <?php echo htmlspecialchars(substr($row['damage_notes'], 0, 30)); ?>...
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($row['damage_date'])); ?></td>
                        <td><strong style="color: #dc3545; font-size: 1.1rem;">Rp <?php echo number_format($row['damage_fine'], 0, ',', '.'); ?></strong></td>
                        <td>
                            <strong style="color: <?php echo $is_critical ? '#dc3545' : '#856404'; ?>;"><?php echo $row['days_since_damage']; ?> days</strong>
                            <?php if ($is_critical): ?><br><span class="badge badge-danger">⚠️ Critical!</span><?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="openPaymentModal(<?php echo $row['damage_id']; ?>, <?php echo $row['damage_fine']; ?>, '<?php echo htmlspecialchars($row['title']); ?>', 'damage')">💳 Pay Now</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        <h3>✓ Payment History</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Book</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paid_result->num_rows > 0): ?>
                    <?php while ($row = $paid_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($row['type'] == 'late_return'): ?>
                                    <span class="badge badge-warning">Late Return</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Book Damage</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td>Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($row['payment_date'])); ?></td>
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
        
        <div id="paymentDetails" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"></div>
        
        <form method="POST" action="" id="paymentForm">
            <input type="hidden" name="return_id" id="modalReturnId">
            <input type="hidden" name="damage_id" id="modalDamageId">
            <input type="hidden" id="paymentType">
            
            <div class="form-group">
                <label>Select Payment Method: *</label>
                <select name="payment_method" class="form-control" required>
                    <option value="">-- Choose Method --</option>
                    <option value="Cash">💵 Cash at Library</option>
                    <option value="Bank Transfer">🏦 Bank Transfer</option>
                    <option value="GoPay">📱 GoPay</option>
                    <option value="OVO">📱 OVO</option>
                    <option value="Dana">📱 Dana</option>
                    <option value="QRIS">📱 QRIS</option>
                </select>
            </div>
            
            <div class="alert alert-info" style="font-size: 0.875rem;">
                <strong>📝 Note:</strong> This is a dummy payment system for demonstration.
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" name="pay_fine" id="submitBtn" class="btn btn-success" style="flex: 1;">✓ Confirm Payment</button>
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(id, amount, bookTitle, type) {
    if (type === 'late') {
        document.getElementById('modalReturnId').value = id;
        document.getElementById('modalDamageId').value = '';
        document.getElementById('submitBtn').name = 'pay_fine';
    } else {
        document.getElementById('modalDamageId').value = id;
        document.getElementById('modalReturnId').value = '';
        document.getElementById('submitBtn').name = 'pay_damage';
    }
    
    document.getElementById('paymentType').value = type;
    document.getElementById('paymentDetails').innerHTML = `
        <p style="margin: 0 0 0.5rem 0;"><strong>Type:</strong> ${type === 'late' ? 'Late Return Fine' : 'Book Damage Fine'}</p>
        <p style="margin: 0 0 0.5rem 0;"><strong>Book:</strong> ${bookTitle}</p>
        <p style="margin: 0;"><strong>Amount:</strong> <span style="color: #dc3545; font-size: 1.2rem; font-weight: bold;">Rp ${amount.toLocaleString('id-ID')}</span></p>
    `;
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>