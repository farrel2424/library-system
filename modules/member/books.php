<?php
$pageTitle = 'Browse Books';
require_once '../../includes/header.php';

// Ensure only members can access
if (!isMember()) {
    $_SESSION['error'] = 'Access denied.';
    header("Location: /library-system/index.php");
    exit();
}

// Cancel expired reservations on page load
cancelExpiredReservations();

// Get search and filter parameters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$category = isset($_GET['category']) ? clean($_GET['category']) : '';

// Build query
$query = "SELECT * FROM books_data WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR author LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

$query .= " ORDER BY title ASC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Get all categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM books_data ORDER BY category");
?>

<div class="card">
    <div class="card-header">
        <h3>📖 Available Books</h3>
    </div>
    
    <!-- Search and Filter -->
    <form method="GET" action="" style="background: rgba(255, 255, 255, 0.95); padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555;">Search by Title or Author</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Enter book title or author name..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555;">Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;">Search</button>
                <?php if (!empty($search) || !empty($category)): ?>
                    <a href="books.php" class="btn btn-secondary" style="white-space: nowrap;">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
    
    <!-- Books Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($book = $result->fetch_assoc()): ?>
                <?php 
                $available_stock = $book['stock'] - $book['reserved_stock'];
                ?>
                <div class="card" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="padding: 1.5rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #667eea;">
                            <?php echo htmlspecialchars($book['title']); ?>
                        </h4>
                        <p style="margin: 0 0 0.5rem 0; color: #666;">
                            <strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?>
                        </p>
                        <p style="margin: 0 0 0.5rem 0; color: #666;">
                            <strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?>
                        </p>
                        <?php if (!empty($book['isbn'])): ?>
                            <p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.875rem;">
                                <strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                            <?php if ($available_stock > 0): ?>
                                <span class="badge badge-success" style="font-size: 1rem;">
                                    ✓ Available (<?php echo $available_stock; ?> ready to reserve)
                                </span>
                                <div style="margin-top: 1rem;">
                                    <a href="reserve.php?book_id=<?php echo $book['book_id']; ?>" 
                                       class="btn btn-primary btn-sm" style="width: 100%;">
                                        📌 Reserve Now
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="badge badge-danger" style="font-size: 1rem;">
                                    ✗ Not Available
                                </span>
                                <?php if ($book['reserved_stock'] > 0): ?>
                                    <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #666;">
                                        (<?php echo $book['reserved_stock']; ?> reserved by others)
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: #f8f9fa; border-radius: 5px;">
                <p style="font-size: 1.2rem; color: #666;">No books found matching your search.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info" style="margin-top: 2rem;">
    <strong>📌 How Click & Collect Works:</strong><br>
    1. Click "Reserve Now" on any available book<br>
    2. You'll receive a unique 4-character reservation code<br>
    3. Visit the library within 24 hours<br>
    4. Show your reservation code to staff<br>
    5. Pick up your book and start reading!<br><br>
    <strong>Note:</strong> Reservations expire after 24 hours if not collected.
</div>

<?php require_once '../../includes/footer.php'; ?>