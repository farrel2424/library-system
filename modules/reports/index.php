<?php
$pageTitle = 'Reports & History';
require_once '../../includes/header.php';

// Get filter parameters
$start_date = isset($_GET['start_date']) ? clean($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean($_GET['end_date']) : date('Y-m-d');
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : 'all';

// Build query
$query = "
    SELECT bt.borrow_id, m.name as member_name, m.email, b.title as book_title, b.author,
           bt.borrow_date, bt.due_date, bt.status,
           rt.return_date, rt.late_days, rt.fine_amount
    FROM borrowing_transactions bt
    JOIN members_data m ON bt.member_id = m.member_id
    JOIN books_data b ON bt.book_id = b.book_id
    LEFT JOIN returning_transactions rt ON bt.borrow_id = rt.borrow_id
    WHERE bt.borrow_date BETWEEN ? AND ?
";

if ($status_filter != 'all') {
    $query .= " AND bt.status = ?";
}

$query .= " ORDER BY bt.borrow_id DESC";

// Execute query
if ($status_filter != 'all') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $start_date, $end_date, $status_filter);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$total_transactions = $result->num_rows;
$total_fines = 0;
$total_borrowed = 0;
$total_returned = 0;

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
    if ($row['status'] == 'borrowed') $total_borrowed++;
    if ($row['status'] == 'returned') $total_returned++;
    $total_fines += $row['fine_amount'];
}

$stmt->close();
?>

<div class="card">
    <div class="card-header">
        <h3>📊 Borrowing Reports & History</h3>
    </div>
    
    <form method="GET" action="" style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" 
                       value="<?php echo $start_date; ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="end_date">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" 
                       value="<?php echo $end_date; ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="borrowed" <?php echo $status_filter == 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                    <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>Returned</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Filter</button>
            </div>
        </div>
    </form>
    
    <!-- Statistics Summary -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <h4>Total Transactions</h4>
            <div class="stat-number"><?php echo $total_transactions; ?></div>
        </div>
        
        <div class="stat-card orange">
            <h4>Currently Borrowed</h4>
            <div class="stat-number"><?php echo $total_borrowed; ?></div>
        </div>
        
        <div class="stat-card green">
            <h4>Returned</h4>
            <div class="stat-number"><?php echo $total_returned; ?></div>
        </div>
        
        <div class="stat-card red">
            <h4>Total Fines Collected</h4>
            <div class="stat-number" style="font-size: 1.5rem;">
                Rp <?php echo number_format($total_fines, 0, ',', '.'); ?>
            </div>
        </div>
    </div>
    
    <!-- Transaction Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Return Date</th>
                    <th>Late Days</th>
                    <th>Fine</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transactions) > 0): ?>
                    <?php foreach ($transactions as $row): ?>
                        <?php
                        $is_overdue = ($row['status'] == 'borrowed' && strtotime($row['due_date']) < strtotime(date('Y-m-d')));
                        ?>
                        <tr>
                            <td><?php echo $row['borrow_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['member_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['email']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['book_title']); ?></strong><br>
                                <small style="color: #666;">by <?php echo htmlspecialchars($row['author']); ?></small>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['borrow_date'])); ?></td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span style="color: red; font-weight: bold;">
                                        <?php echo date('d M Y', strtotime($row['due_date'])); ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo date('d M Y', strtotime($row['due_date'])); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $row['return_date'] ? date('d M Y', strtotime($row['return_date'])) : '-'; ?>
                            </td>
                            <td>
                                <?php if ($row['late_days'] > 0): ?>
                                    <span style="color: red; font-weight: bold;"><?php echo $row['late_days']; ?> days</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['fine_amount'] > 0): ?>
                                    <span style="color: red; font-weight: bold;">
                                        Rp <?php echo number_format($row['fine_amount'], 0, ',', '.'); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'borrowed'): ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Borrowed</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-success">Returned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No transactions found for the selected period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (count($transactions) > 0): ?>
        <div style="margin-top: 1.5rem; text-align: right;">
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Report</button>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .navbar, .footer, .btn, form {
        display: none !important;
    }
    
    .main-content {
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
    
    body {
        background: white !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>