<?php
$pageTitle = 'Add New Book';
require_once '../../includes/header.php';

$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = clean($_POST['title']);
    $author = clean($_POST['author']);
    $category = clean($_POST['category']);
    $isbn = clean($_POST['isbn']);
    $stock = intval($_POST['stock']);
    $status = clean($_POST['status']);
    
    // Validation
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($author)) $errors[] = 'Author is required';
    if (empty($category)) $errors[] = 'Category is required';
    if ($stock < 0) $errors[] = 'Stock cannot be negative';
    
    // Auto-set status based on stock
    if ($stock == 0) {
        $status = 'unavailable';
    } else {
        $status = 'available';
    }
    
    // Insert if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO books_data (title, author, category, isbn, stock, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssis", $title, $author, $category, $isbn, $stock, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Book added successfully!';
            header("Location: index.php");
            exit();
        } else {
            $errors[] = 'Failed to add book: ' . $conn->error;
        }
        $stmt->close();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3>Add New Book</h3>
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
                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="author">Author *</label>
            <input type="text" name="author" id="author" class="form-control" 
                   value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="category">Category *</label>
            <input type="text" name="category" id="category" class="form-control" 
                   value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>" 
                   placeholder="e.g., Programming, Database, Networking"
                   required>
        </div>
        
        <div class="form-group">
            <label for="isbn">ISBN</label>
            <input type="text" name="isbn" id="isbn" class="form-control" 
                   value="<?php echo isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''; ?>"
                   placeholder="978-0132350884">
        </div>
        
        <div class="form-group">
            <label for="stock">Stock Quantity *</label>
            <input type="number" name="stock" id="stock" class="form-control" 
                   value="<?php echo isset($_POST['stock']) ? $_POST['stock'] : '0'; ?>" 
                   min="0" required>
            <small style="color: #666;">Stock will automatically set book status to Available/Unavailable</small>
        </div>
        
        <input type="hidden" name="status" value="available">
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Add Book</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>