<?php
$pageTitle = 'Reserve Book';
require_once '../../includes/header.php';

// Ensure only members can access
if (!isMember()) {
    $_SESSION['error'] = 'Access denied.';
    header("Location: /library-system/index.php");
    exit();
}

// Cancel expired reservations
cancelExpiredReservations();

$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$errors = [];
$success = false;

// Get book details
$stmt = $conn->prepare("SELECT * FROM books_data WHERE book_id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = 'Book not found';
    header("Location: books.php");
    exit();
}

$book = $result->fetch_assoc();
$available_stock = $book['stock'] - $book['reserved_stock'];
$stmt->close();

// Check if member already has pending reservation for this book
$check_existing = $conn->prepare("
    SELECT reservation_id FROM reservations 
    WHERE member_id = ? AND book_id = ? AND status = 'pending'
");
$check_existing->bind_param("ii", $_SESSION['member_id'], $book_id);
$check_existing->execute();
$has_existing = $check_existing->get_result()->num_rows > 0;
$check_existing->close();

// Process reservation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$has_existing) {
    $member_id = $_SESSION['member_id'];
    
    // Validate stock availability
    if ($available_stock <= 0) {
        $errors[] = 'Sorry, this book is currently not available for reservation.';
    }
    
    // Process if no errors
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Generate unique reservation number
            $reservation_number = generateReservationNumber();
            
            // Set reservation dates (24 hours pickup window)
            $reservation_date = date('Y-m-d H:i:s');
            $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $pickup_deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Insert reservation
            $stmt = $conn->prepare("
                INSERT INTO reservations 
                (reservation_number, member_id, book_id, reservation_date, expiry_date, pickup_deadline, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("siisss", $reservation_number, $member_id, $book_id, $reservation_date, $expiry_date, $pickup_deadline);
            $stmt->execute();
            $stmt->close();
            
            // Update reserved stock
            $update_stock = $conn->prepare("UPDATE books_data SET reserved_stock = reserved_stock + 1 WHERE book_id = ?");
            $update_stock->bind_param("i", $book_id);
            $update_stock->execute();
            $update_stock->close();
            
            $conn->commit();
            
            // Redirect to success page with reservation details
            header("Location: reservation_success.php?code=$reservation_number");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to create reservation: ' . $e->getMessage();
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3>📌 Reserve Book</h3>
        <a href="books.php" class="btn btn-secondary">← Back to Books</a>
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
    
    <!-- Book Details -->
    <div style="background: #f8f9fa; padding: 2rem; border-radius: 5px; margin-bottom: 2rem;">
        <h3 style="margin: 0 0 1rem 0; color: #667eea;">
            <?php echo htmlspecialchars($book['title']); ?>
        </h3>
        <p style="margin: 0 0 0.5rem 0;"><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
        <p style="margin: 0 0 0.5rem 0;"><strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?></p>
        <?php if (!empty($book['isbn'])): ?>
            <p style="margin: 0 0 0.5rem 0;"><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></p>
        <?php endif; ?>
        <p style="margin: 0;"><strong>Available for Reservation:</strong> 
            <span style="font-size: 1.2rem; color: <?php echo $available_stock > 0 ? '#28a745' : '#dc3545'; ?>;">
                <?php echo $available_stock; ?> copy(ies)
            </span>
        </p>
    </div>
    
    <?php if ($has_existing): ?>
        <!-- Already has reservation -->
        <div class="alert alert-warning">
            <strong>⚠️ Notice:</strong> You already have a pending reservation for this book. 
            Please check <a href="reservations.php">My Reservations</a> page.
        </div>
    <?php elseif ($available_stock <= 0): ?>
        <!-- Not available -->
        <div class="alert alert-danger">
            <strong>❌ Not Available:</strong> This book is currently not available for reservation. 
            Please try again later or choose another book.
        </div>
    <?php else: ?>
        <!-- Reservation form -->
        <div style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 1.5rem; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 1rem 0; color: #1976D2;">📋 Reservation Details</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>Pickup Window:</strong> 24 hours from reservation time</li>
                <li><strong>Pickup Location:</strong> Library Front Desk</li>
                <li><strong>What to Bring:</strong> Your reservation code (will be provided)</li>
                <li><strong>Borrowing Period:</strong> 14 days from pickup date</li>
            </ul>
        </div>
        
        <form method="POST" action="">
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1.5rem; margin-bottom: 2rem;">
                <h4 style="margin: 0 0 1rem 0; color: #856404;">⚠️ Important Notes:</h4>
                <ul style="margin: 0; padding-left: 20px; color: #856404;">
                    <li>Your reservation will <strong>expire after 24 hours</strong> if not collected</li>
                    <li>The book stock will be <strong>temporarily held</strong> for you</li>
                    <li>You will receive a <strong>unique 4-character code</strong> to show at pickup</li>
                    <li>Please arrive <strong>during library operating hours</strong></li>
                    <li>If you miss the deadline, you need to <strong>reserve again</strong></li>
                </ul>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" required style="margin-right: 0.5rem;">
                    I understand and agree to the reservation terms
                </label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                    ✓ Confirm Reservation
                </button>
                <a href="books.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>