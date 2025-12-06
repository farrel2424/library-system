<?php
$pageTitle = 'Manage Books';
require_once '../../includes/header.php';

// Get all books
$query = "SELECT * FROM books_data ORDER BY book_id DESC";
$result = $conn->query($query);
?>

<div class="card">
    <div class="card-header">
        <h3>Books Catalog</h3>
        <a href="add.php" class="btn btn-primary">+ Add New Book</a>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>ISBN</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['book_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['author']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['isbn']); ?></td>
                            <td>
                                <strong style="color: #667eea;">
                                    Rp <?php echo number_format($row['book_value'], 0, ',', '.'); ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?php echo $row['stock']; ?></strong>
                                <?php if ($row['stock'] == 0): ?>
                                    <span style="color: red; font-size: 0.875rem;"> (Out of stock)</span>
                                <?php elseif ($row['stock'] <= 2): ?>
                                    <span style="color: orange; font-size: 0.875rem;"> (Low stock)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'available'): ?>
                                    <span class="badge badge-success">Available</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="edit.php?id=<?php echo $row['book_id']; ?>" 
                                   class="btn btn-warning btn-sm">Edit</a>
                                <a href="delete.php?id=<?php echo $row['book_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirmDelete('Are you sure you want to delete this book?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No books found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info">
    <strong>💡 About Book Prices:</strong><br>
    The price field represents the replacement value of the book. This is used to calculate damage fines when books are returned in damaged condition. Damage fines are calculated as a percentage of this value based on the severity of the damage.
</div>

<?php require_once '../../includes/footer.php'; ?>