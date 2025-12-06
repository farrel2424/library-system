<?php
$pageTitle = 'Test Suspension System';
require_once '../../includes/header.php';

// Only staff can access
requireStaff();

// Manual trigger suspension check
if (isset($_POST['trigger_check'])) {
    $suspended_count = checkAndSuspendMembers();
    $_SESSION['success'] = "Suspension check completed! {$suspended_count} member(s) suspended.";
    header("Location: test_suspension.php");
    exit();
}

// Get current system time
$currentDate = getCurrentDate();
$cutoffDate = date('Y-m-d', strtotime($currentDate . ' -14 days'));

// Get members at risk of suspension
$at_risk = $conn->query("
    SELECT DISTINCT bt.member_id, m.name, m.email, m.status,
           COALESCE(SUM(rt.fine_amount), 0) as total_late_fines,
           COALESCE((
               SELECT SUM(bdr.damage_fine) 
               FROM book_damage_records bdr
               JOIN borrowing_transactions bt2 ON bdr.borrow_id = bt2.borrow_id
               WHERE bt2.member_id = bt.member_id 
               AND bdr.payment_status = 'unpaid'
               AND bdr.damage_date <= '$cutoffDate'
           ), 0) as total_damage_fines,
           MIN(COALESCE(rt.return_date, bdr.damage_date)) as oldest_fine_date,
           DATEDIFF('$currentDate', MIN(COALESCE(rt.return_date, bdr.damage_date))) as days_unpaid
    FROM borrowing_transactions bt
    JOIN members_data m ON bt.member_id = m.member_id
    LEFT JOIN returning_transactions rt ON bt.borrow_id = rt.borrow_id 
        AND rt.payment_status = 'unpaid' 
        AND rt.fine_amount > 0
        AND rt.return_date <= '$cutoffDate'
    LEFT JOIN book_damage_records bdr ON bt.borrow_id = bdr.borrow_id
        AND bdr.payment_status = 'unpaid'
        AND bdr.damage_date <= '$cutoffDate'
    WHERE (
        (rt.payment_status = 'unpaid' AND rt.fine_amount > 0 AND rt.return_date <= '$cutoffDate')
        OR
        (bdr.payment_status = 'unpaid' AND bdr.damage_date <= '$cutoffDate')
    )
    GROUP BY bt.member_id, m.name, m.email, m.status
    HAVING (total_late_fines + total_damage_fines) > 0
    ORDER BY days_unpaid DESC
");

// Get already suspended members
$suspended = $conn->query("
    SELECT m.member_id, m.name, m.email, sp.suspension_date, sp.total_unpaid_fines, 
           sp.total_damage_fines, sp.penalty_amount, sp.payment_status
    FROM members_data m
    JOIN suspension_penalties sp ON m.member_id = sp.member_id
    WHERE m.status = 'suspended'
    ORDER BY sp.suspension_date DESC
");
?>

<h1>🧪 Test Suspension System</h1>

<!-- Current Time Info -->
<div class="card">
    <div class="card-header">
        <h3>⏰ Current System Time</h3>
    </div>
    
    <div style="background: #e3f2fd; padding: 1.5rem; border-radius: 5px;">
        <p style="margin: 0 0 0.5rem 0;"><strong>Current System Date:</strong> <?php echo date('l, d M Y H:i:s', strtotime(getCurrentDateTime())); ?></p>
        <p style="margin: 0;"><strong>14-Day Cutoff Date:</strong> <?php echo date('d M Y', strtotime($cutoffDate)); ?></p>
        <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #666;">
            Members with unpaid fines from before this cutoff date will be suspended.
        </p>
    </div>
</div>

<!-- Manual Trigger -->
<div class="card">
    <div class="card-header">
        <h3>🔄 Manual Suspension Check</h3>
    </div>
    
    <div class="alert alert-info">
        <strong>ℹ️ How Auto-Suspension Works:</strong><br>
        • Suspension check runs automatically when members log in or access any page<br>
        • Members with unpaid fines (late returns or damage) for 14+ days are suspended<br>
        • Suspended members must pay a Rp 100,000 penalty plus all outstanding fines<br>
        • You can manually trigger the check below for testing
    </div>
    
    <form method="POST" action="">
        <button type="submit" name="trigger_check" class="btn btn-warning" style="width: 100%;">
            🔄 Manually Trigger Suspension Check Now
        </button>
    </form>
</div>

<!-- Members at Risk -->
<div class="card">
    <div class="card-header">
        <h3>⚠️ Members at Risk of Suspension</h3>
    </div>
    
    <?php if ($at_risk->num_rows > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Current Status</th>
                        <th>Unpaid Late Fines</th>
                        <th>Unpaid Damage Fines</th>
                        <th>Total Unpaid</th>
                        <th>Days Unpaid</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $at_risk->fetch_assoc()): ?>
                        <?php
                        $total = $row['total_late_fines'] + $row['total_damage_fines'];
                        $should_suspend = ($row['status'] == 'active' && $row['days_unpaid'] >= 14);
                        ?>
                        <tr style="<?php echo $should_suspend ? 'background: #fff3cd;' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['email']); ?></small>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?php echo number_format($row['total_late_fines'], 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($row['total_damage_fines'], 0, ',', '.'); ?></td>
                            <td><strong style="color: #dc3545;">Rp <?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                            <td>
                                <strong style="color: <?php echo $should_suspend ? '#dc3545' : '#856404'; ?>;">
                                    <?php echo $row['days_unpaid']; ?> days
                                </strong>
                            </td>
                            <td>
                                <?php if ($should_suspend): ?>
                                    <span class="badge badge-danger">⚠️ Will be suspended</span>
                                <?php elseif ($row['status'] == 'suspended'): ?>
                                    <span class="badge badge-info">Already suspended</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Monitoring</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <p style="font-size: 1.1rem; color: #28a745; font-weight: bold;">✓ No members at risk of suspension</p>
            <p style="color: #666;">All members have either paid their fines or don't have any unpaid fines older than 14 days.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Currently Suspended -->
<div class="card">
    <div class="card-header">
        <h3>🚫 Currently Suspended Members</h3>
    </div>
    
    <?php if ($suspended->num_rows > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Suspension Date</th>
                        <th>Late Fines</th>
                        <th>Damage Fines</th>
                        <th>Penalty</th>
                        <th>Total Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $suspended->fetch_assoc()): ?>
                        <?php $total_due = $row['total_unpaid_fines'] + $row['total_damage_fines'] + $row['penalty_amount']; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['email']); ?></small>
                            </td>
                            <td><?php echo date('d M Y, H:i', strtotime($row['suspension_date'])); ?></td>
                            <td>Rp <?php echo number_format($row['total_unpaid_fines'], 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($row['total_damage_fines'], 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($row['penalty_amount'], 0, ',', '.'); ?></td>
                            <td><strong style="color: #dc3545;">Rp <?php echo number_format($total_due, 0, ',', '.'); ?></strong></td>
                            <td>
                                <?php if ($row['payment_status'] == 'paid'): ?>
                                    <span class="badge badge-success">Paid - Should be Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Unpaid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <p style="font-size: 1.1rem; color: #28a745; font-weight: bold;">✓ No suspended members</p>
        </div>
    <?php endif; ?>
</div>

<!-- Testing Instructions -->
<div class="alert alert-info">
    <strong>📋 How to Test Suspension:</strong><br>
    1. Create a member account and borrow a book<br>
    2. As staff, return the book late (e.g., use Time Control to set date 20 days after borrow)<br>
    3. The return will create an unpaid fine<br>
    4. Use Time Control to advance 15+ days from the return date<br>
    5. Click "Manually Trigger Suspension Check" or have the member log in<br>
    6. The member should be automatically suspended<br>
    7. Member can then pay the suspension penalty to restore access
</div>

<?php require_once '../../includes/footer.php'; ?>