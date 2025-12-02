<?php
$pageTitle = 'Reservation Confirmed';
require_once '../../includes/header.php';

// Ensure only members can access
if (!isMember()) {
    $_SESSION['error'] = 'Access denied.';
    header("Location: /library-system/index.php");
    exit();
}

$code = isset($_GET['code']) ? clean($_GET['code']) : '';

if (empty($code)) {
    $_SESSION['error'] = 'Invalid reservation code';
    header("Location: books.php");
    exit();
}

// Get reservation details
$stmt = $conn->prepare("
    SELECT r.*, b.title, b.author, m.name as member_name
    FROM reservations r
    JOIN books_data b ON r.book_id = b.book_id
    JOIN members_data m ON r.member_id = m.member_id
    WHERE r.reservation_number = ? AND r.member_id = ?
");
$stmt->bind_param("si", $code, $_SESSION['member_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = 'Reservation not found';
    header("Location: books.php");
    exit();
}

$reservation = $result->fetch_assoc();
$stmt->close();
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div style="text-align: center; padding: 2rem; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-radius: 10px 10px 0 0;">
        <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
        <h2 style="margin: 0 0 0.5rem 0;">Reservation Confirmed!</h2>
        <p style="margin: 0; font-size: 1.1rem; opacity: 0.9;">Your book is ready for pickup</p>
    </div>
    
    <div style="padding: 2rem;">
        <!-- Reservation Code -->
        <div style="text-align: center; margin-bottom: 2rem; padding: 2rem; background: #f8f9fa; border-radius: 10px; border: 3px dashed #667eea;">
            <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #666;">Your Reservation Code</p>
            <div style="font-size: 3rem; font-weight: bold; color: #667eea; letter-spacing: 0.5rem;">
                <?php echo htmlspecialchars($reservation['reservation_number']); ?>
            </div>
            <p style="margin: 1rem 0 0 0; font-size: 0.875rem; color: #666;">
                Show this code to library staff when picking up
            </p>
        </div>
        
        <!-- Book Details -->
        <div style="background: #e7f3ff; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 1rem 0; color: #1976D2;">📚 Book Details</h4>
            <p style="margin: 0 0 0.5rem 0;"><strong>Title:</strong> <?php echo htmlspecialchars($reservation['title']); ?></p>
            <p style="margin: 0;"><strong>Author:</strong> <?php echo htmlspecialchars($reservation['author']); ?></p>
        </div>
        
        <!-- Pickup Information -->
        <div style="background: #fff3cd; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 1rem 0; color: #856404;">⏰ Pickup Information</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.875rem;">Reserved On:</p>
                    <p style="margin: 0; font-weight: bold;">
                        <?php echo date('d M Y, H:i', strtotime($reservation['reservation_date'])); ?>
                    </p>
                </div>
                <div>
                    <p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.875rem;">Pickup Deadline:</p>
                    <p style="margin: 0; font-weight: bold; color: #dc3545;">
                        <?php echo date('d M Y, H:i', strtotime($reservation['pickup_deadline'])); ?>
                    </p>
                </div>
            </div>
            
            <?php
            $now = time();
            $deadline = strtotime($reservation['pickup_deadline']);
            $hours_left = round(($deadline - $now) / 3600, 1);
            ?>
            
            <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 5px; text-align: center;">
                <p style="margin: 0; font-size: 1.2rem;">
                    <strong>⏳ Time Remaining: 
                        <span style="color: <?php echo $hours_left < 6 ? '#dc3545' : '#28a745'; ?>;">
                            <?php echo $hours_left; ?> hours
                        </span>
                    </strong>
                </p>
            </div>
        </div>
        
        <!-- Instructions -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 1rem 0;">📋 Next Steps:</h4>
            <ol style="margin: 0; padding-left: 20px;">
                <li style="margin-bottom: 0.5rem;"><strong>Visit the library</strong> during operating hours within 24 hours</li>
                <li style="margin-bottom: 0.5rem;"><strong>Show your reservation code</strong> (<?php echo $reservation['reservation_number']; ?>) to the staff at the front desk</li>
                <li style="margin-bottom: 0.5rem;"><strong>Staff will verify</strong> your reservation and retrieve the book</li>
                <li style="margin-bottom: 0.5rem;"><strong>Complete the borrowing</strong> process with staff</li>
                <li><strong>Enjoy reading!</strong> You'll have 14 days to return the book</li>
            </ol>
        </div>
        
        <!-- Important Notice -->
        <div class="alert alert-warning">
            <strong>⚠️ Important:</strong> If you don't pick up the book within 24 hours, your reservation will be automatically cancelled and the book will become available for others.
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
            <a href="reservations.php" class="btn btn-primary">View My Reservations</a>
            <a href="books.php" class="btn btn-secondary">Browse More Books</a>
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
        </div>
    </div>
</div>

<style>
@media print {
    .navbar, .footer, .btn, .alert {
        display: none !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>