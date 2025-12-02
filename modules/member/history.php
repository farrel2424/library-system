<?php
$pageTitle = 'My Borrowing History';
require_once '../../includes/header.php';

// Ensure only members can access
if (!isMember()) {
    $_SESSION['error'] = 'Access denied.';
    header("Location: /library-system/index.php");
    exit();
}

$member_id = $_SESSION['member_id'];

// Get current borrowings
$current_borrowings = $conn->prepare("
    SELECT bt.borrow_id, b.title, b.author, bt.borrow_date, bt.due_date,
           DATEDIFF(CURDATE(), bt.due_date) as days_overdue
    FROM borrowing_transactions bt
    JOIN books_data b ON bt.book_id = b.book_id
    WHERE bt.member_id = ? AND bt.status = 'borrowed'
    ORDER BY bt.borrow_date DESC
");
$current_borrowings->bind_param("i", $member_id);
$current_borrowings->execute();
$current = $current_borrowings->get_result();

// Get borrowing history
$history_query = $conn->prepare("
    SELECT bt.borrow_id, b.title, b.author, bt.borrow_date, bt.due_date, bt.status,
           rt.return_date, rt.late_days, rt.fine_amount
    FROM borrowing_transactions bt
    JOIN books_data b ON bt.book_id = b.book_id
    LEFT JOIN returning_transactions rt ON bt.borrow_id = rt.borrow_id
    WHERE bt.member_id = ? AND bt.status = 'returned'
    ORDER BY bt.borrow_date DESC
    LIMIT 20
");
$history_query->bind_param("i", $member_id);
$history_query->execute();
$history = $history_query->get_result();

// Calculate statistics
$total_borrowed = $current->num_rows;
$total_overdue = 0;
$total_fines = 0;

mysqli_data_seek($current, 0);
while ($row = $current->fetch_assoc()) {
    if ($row['days_overdue'] > 0) {
        $total_overdue++;
    }
}
mysqli_data_seek($current, 0);

// Calculate total fines from history
$fine_query = $conn->prepare("
    SELECT SUM(rt.fine_amount) as total_fines
    FROM returning_transactions rt
    JOIN borrowing_transactions bt ON rt.borrow_id = bt.borrow_id
    WHERE bt.member_id = ?
");
$fine_query->bind_param("i", $member_id);
$fine_query->execute();
$fine_result = $fine_query->get_result()->fetch_assoc();
$total_fines = $fine_result['total_fines'] ?? 0;
?>

<h1>My Borrowing History</h1>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card orange">
        <h4>Currently Borrowed</h4>
        <div class="stat-number"><?php echo $total_borrowed; ?></div>
    </div>
    
    <div class="stat-card <?php echo $total_overdue > 0 ? 'red' : 'green'; ?>">
        <h4>Overdue Books</h4>
        <div class="stat-number"><?php echo $total_overdue; ?></div>
    </div>
    
    <div class="stat-card">
        <h4>Total Fines Paid</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_fines, 0, ',', '.'); ?>
        </div>
    </div>
</div>

<?php if ($total_overdue > 0): ?>
    <div class="alert alert-danger">
        <strong>⚠️ Attention:</strong> You have <?php echo $total_overdue; ?> overdue book(s). 
        Please return them as soon as possible to avoid additional fines.
    </div>
<?php endif; ?>

<!-- Current Borrowings -->
<div class="card">
    <div class="card-header">
        <h3>📚 Currently Borrowed Books</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($current->num_rows > 0): ?>
                    <?php while ($row = $current->fetch_assoc()): ?>
                        <?php
                        $is_overdue = $row['days_overdue'] > 0;
                        $days_remaining = -$row['days_overdue'];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['author']); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['borrow_date'])); ?></td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span style="color: red; font-weight: bold;">
                                        <?php echo date('d M Y', strtotime($row['due_date'])); ?>
                                        <br>(<?php echo $row['days_overdue']; ?> days overdue)
                                    </span>
                                <?php else: ?>
                                    <?php echo date('d M Y', strtotime($row['due_date'])); ?>
                                    <?php if ($days_remaining <= 3): ?>
                                        <br><span style="color: orange; font-size: 0.875rem;">
                                            (<?php echo $days_remaining; ?> days remaining)
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Borrowed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">
                            You don't have any books borrowed at the moment.
                            <br><a href="books.php">Browse available books</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Borrowing History -->
<div class="card">
    <div class="card-header">
        <h3>📋 Past Borrowings</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Borrow Date</th>
                    <th>Return Date</th>
                    <th>Late Days</th>
                    <th>Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($history->num_rows > 0): ?>
                    <?php while ($row = $history->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['author']); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['borrow_date'])); ?></td>
                            <td><?php echo $row['return_date'] ? date('d M Y', strtotime($row['return_date'])) : '-'; ?></td>
                            <td>
                                <?php if ($row['late_days'] > 0): ?>
                                    <span style="color: red; font-weight: bold;">
                                        <?php echo $row['late_days']; ?> days
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">On Time</span>
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
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No borrowing history yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>