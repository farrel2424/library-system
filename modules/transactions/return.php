<?php
$pageTitle = 'Return Book';
require_once '../../includes/header.php';

// Only staff can access
requireStaff();

// Auto-suspend members
checkAndSuspendMembers();

// Get all borrowed books (not yet returned)
$borrowed = $conn->query("
    SELECT bt.borrow_id, m.name as member_name, b.title as book_title,
           bt.borrow_date, bt.due_date, DATEDIFF(CURDATE(), bt.due_date) as days_overdue
    FROM borrowing_transactions bt
    JOIN members_data m ON bt.member_id = m.member_id
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.status = 'borrowed'
    ORDER BY bt.due_date ASC
");
?>

<div class="card">
    <div class="card-header">
        <h3>📚 Return Book</h3>
    </div>
    
    <form method="POST" action="process_return.php">
        <div class="form-group">
            <label for="borrow_id">Select Borrowing Transaction *</label>
            <select name="borrow_id" id="borrow_id" class="form-control" required onchange="calculateFine()">
                <option value="">-- Choose Transaction --</option>
                <?php while ($row = $borrowed->fetch_assoc()): ?>
                    <?php
                    $late_days = max(0, $row['days_overdue']);
                    $fine = $late_days * 5000; // 5000 IDR per day
                    $display_class = ($late_days > 0) ? 'style="color: red; font-weight: bold;"' : '';
                    ?>
                    <option value="<?php echo $row['borrow_id']; ?>" 
                            data-late="<?php echo $late_days; ?>"
                            data-fine="<?php echo $fine; ?>"
                            data-duedate="<?php echo $row['due_date']; ?>"
                            <?php echo $display_class; ?>>
                        ID #<?php echo $row['borrow_id']; ?> - 
                        <?php echo htmlspecialchars($row['member_name']); ?> - 
                        <?php echo htmlspecialchars($row['book_title']); ?>
                        <?php if ($late_days > 0): ?>
                            (OVERDUE: <?php echo $late_days; ?> days - Fine: Rp <?php echo number_format($fine, 0, ',', '.'); ?>)
                        <?php else: ?>
                            (Due: <?php echo date('d M Y', strtotime($row['due_date'])); ?>)
                        <?php endif; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="return_date">Return Date *</label>
            <input type="date" name="return_date" id="return_date" class="form-control" 
                   value="<?php echo date('Y-m-d'); ?>" required onchange="calculateFine()">
        </div>
        
        <div id="fineInfo" style="display: none; padding: 1rem; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 1rem; border-radius: 5px;">
            <h4 style="margin: 0 0 0.5rem 0; color: #856404;">⚠️ Late Return - Fine Details</h4>
            <p style="margin: 0;"><strong>Days Late:</strong> <span id="lateDays">0</span> days</p>
            <p style="margin: 0;"><strong>Fine Amount:</strong> Rp <span id="fineAmount">0</span></p>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #856404;">
                <strong>Fine Rate:</strong> Rp 5,000 per day
            </p>
            
            <div style="background: white; padding: 1rem; margin-top: 1rem; border-radius: 5px;">
                <h5 style="margin: 0 0 0.5rem 0;">💳 Payment Methods Available:</h5>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.875rem;">
                    <li>Cash - Pay at library counter</li>
                    <li>Bank Transfer - BCA 1234567890 (Library Account)</li>
                    <li>E-Wallet - GoPay/OVO/Dana (0812-3456-7890)</li>
                    <li>QRIS - Available at counter</li>
                </ul>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #dc3545;">
                    <strong>⚠️ Important:</strong> Unpaid fines for more than 14 days will result in automatic account suspension + Rp 100,000 penalty.
                </p>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Process Return</button>
            <a href="/library-system/index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>Recent Returns</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Return ID</th>
                    <th>Borrow ID</th>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Return Date</th>
                    <th>Late Days</th>
                    <th>Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $returns = $conn->query("
                    SELECT rt.return_id, rt.borrow_id, m.name as member_name, 
                           b.title as book_title, rt.return_date, rt.late_days, rt.fine_amount
                    FROM returning_transactions rt
                    JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
                    JOIN members_data m ON bt.member_id = m.member_id
                    JOIN books_data b ON bt.book_id = b.book_id
                    ORDER BY rt.return_id DESC
                    LIMIT 10
                ");
                
                if ($returns->num_rows > 0):
                    while ($row = $returns->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $row['return_id']; ?></td>
                        <td>#<?php echo $row['borrow_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['return_date'])); ?></td>
                        <td>
                            <?php if ($row['late_days'] > 0): ?>
                                <span style="color: red; font-weight: bold;"><?php echo $row['late_days']; ?> days</span>
                            <?php else: ?>
                                <span class="badge badge-success">On Time</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['fine_amount'] > 0): ?>
                                <span style="color: red; font-weight: bold;">Rp <?php echo number_format($row['fine_amount'], 0, ',', '.'); ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="7" class="text-center">No return transactions yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function calculateFine() {
    const borrowSelect = document.getElementById('borrow_id');
    const returnDateInput = document.getElementById('return_date');
    const fineInfo = document.getElementById('fineInfo');
    const lateDaysSpan = document.getElementById('lateDays');
    const fineAmountSpan = document.getElementById('fineAmount');
    
    if (!borrowSelect.value || !returnDateInput.value) {
        fineInfo.style.display = 'none';
        return;
    }
    
    const selectedOption = borrowSelect.options[borrowSelect.selectedIndex];
    const dueDate = new Date(selectedOption.dataset.duedate);
    const returnDate = new Date(returnDateInput.value);
    
    // Calculate days late
    const diffTime = returnDate - dueDate;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    const lateDays = Math.max(0, diffDays);
    
    // Calculate fine (5000 per day)
    const fine = lateDays * 5000;
    
    if (lateDays > 0) {
        fineInfo.style.display = 'block';
        lateDaysSpan.textContent = lateDays;
        fineAmountSpan.textContent = fine.toLocaleString('id-ID');
    } else {
        fineInfo.style.display = 'none';
    }
}

// Set max date to today
document.getElementById('return_date').max = new Date().toISOString().split('T')[0];
</script>

<?php require_once '../../includes/footer.php'; ?>