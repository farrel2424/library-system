<?php
$pageTitle = 'Borrow Book';
require_once '../../includes/header.php';

// Get active members
$members = $conn->query("SELECT member_id, name, email FROM members_data WHERE status = 'active' ORDER BY name");

// Get available books (stock > 0)
$books_data = $conn->query("SELECT book_id, title, author, stock FROM books_data WHERE stock > 0 ORDER BY title");
?>

<div class="card">
    <div class="card-header">
        <h3>📖 Borrow Book</h3>
    </div>
    
    <form method="POST" action="process_borrow.php" id="borrowForm">
        <div class="form-group">
            <label for="member_id">Select Member *</label>
            <select name="member_id" id="member_id" class="form-control" required>
                <option value="">-- Choose Member --</option>
                <?php while ($member = $members->fetch_assoc()): ?>
                    <option value="<?php echo $member['member_id']; ?>">
                        <?php echo htmlspecialchars($member['name']); ?> (<?php echo htmlspecialchars($member['email']); ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="book_id">Select Book *</label>
            <select name="book_id" id="book_id" class="form-control" required>
                <option value="">-- Choose Book --</option>
                <?php while ($book = $books_data->fetch_assoc()): ?>
                    <option value="<?php echo $book['book_id']; ?>">
                        <?php echo htmlspecialchars($book['title']); ?> by <?php echo htmlspecialchars($book['author']); ?> 
                        (Stock: <?php echo $book['stock']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="borrow_date">Borrow Date *</label>
            <input type="date" name="borrow_date" id="borrow_date" class="form-control" 
                   value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="due_date">Due Date *</label>
            <input type="date" name="due_date" id="due_date" class="form-control" 
                   value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
            <small style="color: #666;">Default loan period is 14 days</small>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Process Borrowing</button>
            <a href="/library-system/index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>Currently Borrowed Books</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $current = $conn->query("
                    SELECT bt.borrow_id, m.name as member_name, b.title as book_title,
                           bt.borrow_date, bt.due_date, bt.status
                    FROM borrowing_transactions bt
                    JOIN members_data m ON bt.member_id = m.member_id
                    JOIN books_data b ON bt.book_id = b.book_id
                    WHERE bt.status = 'borrowed'
                    ORDER BY bt.borrow_id DESC
                ");
                
                if ($current->num_rows > 0):
                    while ($row = $current->fetch_assoc()):
                        $is_overdue = (strtotime($row['due_date']) < strtotime(date('Y-m-d')));
                ?>
                    <tr>
                        <td><?php echo $row['borrow_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['borrow_date'])); ?></td>
                        <td>
                            <?php if ($is_overdue): ?>
                                <span style="color: red; font-weight: bold;">
                                    <?php echo date('d M Y', strtotime($row['due_date'])); ?> (OVERDUE)
                                </span>
                            <?php else: ?>
                                <?php echo date('d M Y', strtotime($row['due_date'])); ?>
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
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="6" class="text-center">No active borrowing transactions</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Set minimum date for borrow_date to today
document.getElementById('borrow_date').min = new Date().toISOString().split('T')[0];

// Auto-calculate due date (14 days after borrow date)
document.getElementById('borrow_date').addEventListener('change', function() {
    let borrowDate = new Date(this.value);
    borrowDate.setDate(borrowDate.getDate() + 14);
    document.getElementById('due_date').value = borrowDate.toISOString().split('T')[0];
});

// Set minimum due date
document.getElementById('borrow_date').addEventListener('change', function() {
    document.getElementById('due_date').min = this.value;
});
</script>

<?php require_once '../../includes/footer.php'; ?>