
<?php
// ✅ STEP 1: Include only database config (no HTML output)
require_once '../../config/database.php';

$errors = [];
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get book data
$stmt = $conn->prepare("SELECT * FROM books_data WHERE book_id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = 'Book not found';
    $stmt->close();
    header("Location: index.php");
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

// ✅ STEP 2: Process form submission BEFORE including header
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = clean($_POST['title']);
    $author = clean($_POST['author']);
    $category = clean($_POST['category']);
    $isbn = clean($_POST['isbn']);
    $book_value = floatval($_POST['book_value']);
    $stock = intval($_POST['stock']);
    
    // Validation
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($author)) $errors[] = 'Author is required';
    if (empty($category)) $errors[] = 'Category is required';
    if ($stock < 0) $errors[] = 'Stock cannot be negative';
    if ($book_value <= 0) $errors[] = 'Book value must be greater than 0';
    
    // Auto-set status based on stock
    if ($stock == 0) {
        $status = 'unavailable';
    } else {
        $status = 'available';
    }
    
    // Update if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE books_data SET title = ?, author = ?, category = ?, isbn = ?, book_value = ?, stock = ?, status = ? WHERE book_id = ?");
        $stmt->bind_param("ssssdisi", $title, $author, $category, $isbn, $book_value, $stock, $status, $book_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Book updated successfully!';
            $stmt->close();
            // ✅ REDIRECT BEFORE including header.php
            header("Location: index.php");
            exit();
        } else {
            $errors[] = 'Failed to update book: ' . $conn->error;
            $stmt->close();
        }
    }
}

// ✅ STEP 3: NOW include header (after all redirects)
$pageTitle = 'Edit Book';
require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>Edit Book</h3>
        <a href="index.php" class="btn btn-secondary">← Back to List</a>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="title">Book Title *</label>
            <input type="text" name="title" id="title" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['title']) ? $_POST['title'] : $book['title']); ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="author">Author *</label>
            <input type="text" name="author" id="author" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['author']) ? $_POST['author'] : $book['author']); ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="category">Category *</label>
            <input type="text" name="category" id="category" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['category']) ? $_POST['category'] : $book['category']); ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="isbn">ISBN</label>
            <input type="text" name="isbn" id="isbn" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['isbn']) ? $_POST['isbn'] : $book['isbn']); ?>">
        </div>
        
        <div class="form-group">
            <label for="book_value">Book Value / Price (Rp) *</label>
            <input type="number" name="book_value" id="book_value" class="form-control" 
                   value="<?php echo isset($_POST['book_value']) ? $_POST['book_value'] : $book['book_value']; ?>" 
                   min="1000" step="1000" required>
            <small style="color: #666;">
                Current value: Rp <?php echo number_format($book['book_value'], 0, ',', '.'); ?>. 
                This is used to calculate damage fines.
            </small>
        </div>
        
        <div class="form-group">
            <label for="stock">Stock Quantity *</label>
            <input type="number" name="stock" id="stock" class="form-control" 
                   value="<?php echo isset($_POST['stock']) ? $_POST['stock'] : $book['stock']; ?>" 
                   min="0" required>
            <small style="color: #666;">Current stock: <?php echo $book['stock']; ?>. Status will update automatically based on stock.</small>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Update Book</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>