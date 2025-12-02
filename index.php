<?php
$pageTitle = 'Dashboard - Library System';
require_once 'includes/header.php';

if (isStaff()) {
    // STAFF/ADMIN DASHBOARD
    // Get statistics
    $stats = [];

    // Total Members
    $result = $conn->query("SELECT COUNT(*) as total FROM members_data WHERE status = 'active'");
    $stats['members'] = $result->fetch_assoc()['total'];

    // Total Books
    $result = $conn->query("SELECT COUNT(*) as total FROM books_data");
    $stats['books'] = $result->fetch_assoc()['total'];

    // Available Books
    $result = $conn->query("SELECT SUM(stock) as total FROM books_data WHERE status = 'available'");
    $stats['available_books'] = $result->fetch_assoc()['total'] ?? 0;

    // Currently Borrowed Books
    $result = $conn->query("SELECT COUNT(*) as total FROM borrowing_transactions WHERE status = 'borrowed'");
    $stats['borrowed'] = $result->fetch_assoc()['total'];

    // Overdue Books
    $result = $conn->query("SELECT COUNT(*) as total FROM borrowing_transactions 
                            WHERE status = 'borrowed' AND due_date < CURDATE()");
    $stats['overdue'] = $result->fetch_assoc()['total'];

    // Recent Borrowing Transactions
    $recent_borrows = $conn->query("
        SELECT bt.borrow_id, m.name as member_name, b.title as book_title, 
               bt.borrow_date, bt.due_date, bt.status
        FROM borrowing_transactions bt
        JOIN members_data m ON bt.member_id = m.member_id
        JOIN books_data b ON bt.book_id = b.book_id
        ORDER BY bt.borrow_id DESC
        LIMIT 10
    ");
    ?>

    <h1>Staff Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Active Members</h4>
            <div class="stat-number"><?php echo $stats['members']; ?></div>
        </div>
        
        <div class="stat-card green">
            <h4>Total Books</h4>
            <div class="stat-number"><?php echo $stats['books']; ?></div>
        </div>
        
        <div class="stat-card orange">
            <h4>Available Stock</h4>
            <div class="stat-number"><?php echo $stats['available_books']; ?></div>
        </div>
        
        <div class="stat-card red">
            <h4>Currently Borrowed</h4>
            <div class="stat-number"><?php echo $stats['borrowed']; ?></div>
        </div>
    </div>

    <?php if ($stats['overdue'] > 0): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Attention:</strong> There are <?php echo $stats['overdue']; ?> overdue book(s) that need to be returned.
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Recent Borrowing Transactions</h3>
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
                    <?php if ($recent_borrows->num_rows > 0): ?>
                        <?php while ($row = $recent_borrows->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['borrow_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['borrow_date'])); ?></td>
                                <td>
                                    <?php 
                                    $due_date = strtotime($row['due_date']);
                                    $today = strtotime(date('Y-m-d'));
                                    $is_overdue = ($row['status'] == 'borrowed' && $due_date < $today);
                                    
                                    if ($is_overdue) {
                                        echo '<span style="color: red; font-weight: bold;">';
                                        echo date('d M Y', $due_date);
                                        echo ' (OVERDUE)</span>';
                                    } else {
                                        echo date('d M Y', $due_date);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'borrowed'): ?>
                                        <span class="badge badge-warning">Borrowed</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Returned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No transactions yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php } else {
    // MEMBER DASHBOARD
    $member_id = $_SESSION['member_id'];
    
    // Get member statistics
    $current_borrowed = $conn->prepare("
        SELECT COUNT(*) as total FROM borrowing_transactions 
        WHERE member_id = ? AND status = 'borrowed'
    ");
    $current_borrowed->bind_param("i", $member_id);
    $current_borrowed->execute();
    $borrowed_count = $current_borrowed->get_result()->fetch_assoc()['total'];
    
    // Get overdue count
    $overdue_query = $conn->prepare("
        SELECT COUNT(*) as total FROM borrowing_transactions 
        WHERE member_id = ? AND status = 'borrowed' AND due_date < CURDATE()
    ");
    $overdue_query->bind_param("i", $member_id);
    $overdue_query->execute();
    $overdue_count = $overdue_query->get_result()->fetch_assoc()['total'];
    
    // Get total books available
    $available_books = $conn->query("SELECT COUNT(*) as total FROM books_data WHERE stock > 0")->fetch_assoc()['total'];
    
    // Get recent borrowings
    $recent = $conn->prepare("
        SELECT bt.borrow_id, b.title, b.author, bt.borrow_date, bt.due_date,
               DATEDIFF(CURDATE(), bt.due_date) as days_overdue
        FROM borrowing_transactions bt
        JOIN books_data b ON bt.book_id = b.book_id
        WHERE bt.member_id = ? AND bt.status = 'borrowed'
        ORDER BY bt.borrow_date DESC
        LIMIT 5
    ");
    $recent->bind_param("i", $member_id);
    $recent->execute();
    $recent_borrowings = $recent->get_result();
    ?>
    
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['member_name']); ?>! 👋</h1>
    
    <div class="stats-grid">
        <div class="stat-card orange">
            <h4>Books I'm Borrowing</h4>
            <div class="stat-number"><?php echo $borrowed_count; ?></div>
        </div>
        
        <div class="stat-card <?php echo $overdue_count > 0 ? 'red' : 'green'; ?>">
            <h4>Overdue Books</h4>
            <div class="stat-number"><?php echo $overdue_count; ?></div>
        </div>
        
        <div class="stat-card">
            <h4>Available Books</h4>
            <div class="stat-number"><?php echo $available_books; ?></div>
        </div>
    </div>
    
    <?php if ($overdue_count > 0): ?>
    <div class="alert alert-danger">
        <strong>⚠️ Attention:</strong> You have <?php echo $overdue_count; ?> overdue book(s). 
        Please return them to the library as soon as possible to avoid additional fines.
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <a href="/library-system/modules/member/books.php" style="text-decoration: none;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; padding: 2rem;">
                <h3 style="margin: 0 0 0.5rem 0;">📖 Browse Books</h3>
                <p style="margin: 0; opacity: 0.9;">Explore our collection</p>
            </div>
        </a>
        
        <a href="/library-system/modules/member/history.php" style="text-decoration: none;">
            <div class="card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; text-align: center; padding: 2rem;">
                <h3 style="margin: 0 0 0.5rem 0;">📋 My History</h3>
                <p style="margin: 0; opacity: 0.9;">View borrowing records</p>
            </div>
        </a>
    </div>
    
    <!-- Current Borrowings -->
    <div class="card">
        <div class="card-header">
            <h3>My Current Borrowings</h3>
            <a href="/library-system/modules/member/history.php" class="btn btn-primary">View Full History</a>
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
                    <?php if ($recent_borrowings->num_rows > 0): ?>
                        <?php while ($row = $recent_borrowings->fetch_assoc()): ?>
                            <?php $is_overdue = $row['days_overdue'] > 0; ?>
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
                                You don't have any books borrowed currently.
                                <br><a href="/library-system/modules/member/books.php">Browse available books</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="alert alert-info" style="position: relative; z-index: 100; background: linear-gradient(135deg, rgba(23, 162, 184, 0.15) 0%, rgba(13, 202, 240, 0.15) 100%), rgba(255, 255, 255, 0.98); border: 2px solid #17a2b8; box-shadow: 0 6px 20px rgba(23, 162, 184, 0.3);">
        <strong style="color: #004085; font-size: 1.3rem; display: block; margin-bottom: 0.75rem; text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.9);">📌 How to Borrow Books:</strong>
        <div style="color: #004085; font-weight: 600; line-height: 1.8; font-size: 1.05rem;">
            1. Browse available books in our catalog<br>
            2. Visit the library counter<br>
            3. Request your desired book from staff<br>
            4. Staff will process your borrowing and provide the due date
        </div>
    </div>

<?php } ?>

<?php require_once 'includes/footer.php'; ?>