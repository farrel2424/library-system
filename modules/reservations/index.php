<?php
// ✅ NO OUTPUT before this point - include database first
require_once '../../config/database.php';

// Only staff can access
requireStaff();

// Cancel expired reservations
cancelExpiredReservations();

// ✅ Handle ALL form submissions BEFORE any HTML output
if (isset($_POST['verify']) && !empty($_POST['verify'])) {
    $reservation_id = intval($_POST['verify']);
    
    // Get reservation details
    $check = $conn->prepare("SELECT r.*, b.book_id FROM reservations r JOIN books_data b ON r.book_id = b.book_id WHERE r.reservation_id = ? AND r.status = 'pending'");
    $check->bind_param("i", $reservation_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $reservation = $result->fetch_assoc();
        
        $conn->begin_transaction();
        try {
            // Update reservation status to collected
            $stmt = $conn->prepare("UPDATE reservations SET status = 'collected' WHERE reservation_id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();
            
            // Create borrowing transaction
            $borrow_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+14 days'));
            
            $stmt = $conn->prepare("INSERT INTO borrowing_transactions (member_id, book_id, reservation_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, ?, 'borrowed')");
            $stmt->bind_param("iiiss", $reservation['member_id'], $reservation['book_id'], $reservation_id, $borrow_date, $due_date);
            $stmt->execute();
            $stmt->close();
            
            // Decrease actual stock
            $stmt = $conn->prepare("UPDATE books_data SET stock = stock - 1, reserved_stock = reserved_stock - 1 WHERE book_id = ?");
            $stmt->bind_param("i", $reservation['book_id']);
            $stmt->execute();
            $stmt->close();
            
            // Update book status if needed
            $conn->query("UPDATE books_data SET status = 'unavailable' WHERE book_id = " . $reservation['book_id'] . " AND stock = 0");
            
            $conn->commit();
            $_SESSION['success'] = 'Reservation verified! Book has been borrowed successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to verify reservation: ' . $e->getMessage();
        }
    }
    $check->close();
    
    // ✅ REDIRECT BEFORE including header.php
    header("Location: index.php");
    exit();
}

// Handle cancellation
if (isset($_GET['cancel']) && !empty($_GET['cancel'])) {
    $reservation_id = intval($_GET['cancel']);
    
    // Verify ownership and status
    $check = $conn->prepare("SELECT book_id FROM reservations WHERE reservation_id = ? AND status = 'pending'");
    $check->bind_param("i", $reservation_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $conn->begin_transaction();
        try {
            // Update reservation status
            $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();
            
            // Restore reserved stock
            $stmt = $conn->prepare("UPDATE books_data SET reserved_stock = reserved_stock - 1 WHERE book_id = ?");
            $stmt->bind_param("i", $row['book_id']);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $_SESSION['success'] = 'Reservation cancelled successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Failed to cancel reservation.';
        }
    }
    $check->close();
    
    header("Location: index.php");
    exit();
}

// ✅ NOW include header (after all processing)
$pageTitle = 'Manage Reservations';
require_once '../../includes/header.php';

// Get all pending reservations
$pending = $conn->query("
    SELECT r.*, m.name as member_name, m.email, m.phone, b.title, b.author,
           TIMESTAMPDIFF(HOUR, NOW(), r.pickup_deadline) as hours_left
    FROM reservations r
    JOIN members_data m ON r.member_id = m.member_id
    JOIN books_data b ON r.book_id = b.book_id
    WHERE r.status = 'pending'
    ORDER BY r.pickup_deadline ASC
");

// Get today's collected reservations
$today_collected = $conn->query("
    SELECT r.*, m.name as member_name, b.title
    FROM reservations r
    JOIN members_data m ON r.member_id = m.member_id
    JOIN books_data b ON r.book_id = b.book_id
    WHERE r.status = 'collected' AND DATE(r.updated_at) = CURDATE()
    ORDER BY r.updated_at DESC
");

// Get statistics
$stats = $conn->query("
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'collected' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) as collected_today,
        SUM(CASE WHEN status = 'expired' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) as expired_today
    FROM reservations
")->fetch_assoc();
?>

<h1>📌 Reservation Management</h1>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card orange">
        <h4>Pending Pickup</h4>
        <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
    </div>
    
    <div class="stat-card green">
        <h4>Collected Today</h4>
        <div class="stat-number"><?php echo $stats['collected_today'] ?? 0; ?></div>
    </div>
    
    <div class="stat-card red">
        <h4>Expired Today</h4>
        <div class="stat-number"><?php echo $stats['expired_today'] ?? 0; ?></div>
    </div>
</div>

<!-- Pending Reservations -->
<div class="card">
    <div class="card-header">
        <h3>⏰ Pending Pickups (Awaiting Collection)</h3>
    </div>
    
    <?php if ($pending->num_rows > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Member</th>
                        <th>Book</th>
                        <th>Reserved On</th>
                        <th>Deadline</th>
                        <th>Time Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $pending->fetch_assoc()): ?>
                        <?php $is_urgent = $row['hours_left'] < 6; ?>
                        <tr style="<?php echo $is_urgent ? 'background: #fff3cd;' : ''; ?>">
                            <td>
                                <strong style="font-size: 1.3rem; color: #667eea; letter-spacing: 0.2rem;">
                                    <?php echo htmlspecialchars($row['reservation_number']); ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['member_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['email']); ?></small><br>
                                <small style="color: #666;">📞 <?php echo htmlspecialchars($row['phone']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['author']); ?></small>
                            </td>
                            <td><?php echo date('d M Y, H:i', strtotime($row['reservation_date'])); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($row['pickup_deadline'])); ?></td>
                            <td>
                                <strong style="color: <?php echo $is_urgent ? '#dc3545' : '#28a745'; ?>;">
                                    <?php 
                                    if ($row['hours_left'] < 1) {
                                        echo '<1 hour';
                                    } else {
                                        echo number_format($row['hours_left'], 1) . ' hrs';
                                    }
                                    ?>
                                </strong>
                                <?php if ($is_urgent): ?>
                                    <br><span class="badge badge-danger">⚠️ Expiring Soon!</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="verify" value="<?php echo $row['reservation_id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" 
                                            onclick="return confirm('Verify that member has arrived and hand over the book?\n\nThis will:\n- Mark reservation as collected\n- Create borrowing transaction\n- Reduce book stock')">
                                        ✓ Verify & Hand Over
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <p style="font-size: 1.1rem; color: #666;">No pending reservations at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Today's Collected -->
<div class="card">
    <div class="card-header">
        <h3>✅ Collected Today</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Collected At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($today_collected->num_rows > 0): ?>
                    <?php while ($row = $today_collected->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['reservation_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($row['updated_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No collections today yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info">
    <strong>📋 Staff Instructions:</strong><br>
    1. When a member arrives, ask for their reservation code<br>
    2. Find the reservation in the "Pending Pickups" table<br>
    3. Verify the member's identity (name, email, or phone)<br>
    4. Click "Verify & Hand Over" button<br>
    5. Give the book to the member<br>
    6. Inform them of the 14-day borrowing period<br><br>
    <strong>Note:</strong> The system automatically handles stock updates and creates borrowing records.
</div>

<?php require_once '../../includes/footer.php'; ?>