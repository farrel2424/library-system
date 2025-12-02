<?php
$pageTitle = 'My Reservations';
require_once '../../includes/header.php';

// Ensure only members can access
if (!isMember()) {
    $_SESSION['error'] = 'Access denied.';
    header("Location: /library-system/index.php");
    exit();
}

// Cancel expired reservations
cancelExpiredReservations();

$member_id = $_SESSION['member_id'];

// Handle cancellation
if (isset($_GET['cancel']) && !empty($_GET['cancel'])) {
    $reservation_id = intval($_GET['cancel']);
    
    // Verify ownership and status
    $check = $conn->prepare("SELECT book_id FROM reservations WHERE reservation_id = ? AND member_id = ? AND status = 'pending'");
    $check->bind_param("ii", $reservation_id, $member_id);
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
    
    header("Location: reservations.php");
    exit();
}

// Get pending reservations
$pending = $conn->prepare("
    SELECT r.*, b.title, b.author,
           TIMESTAMPDIFF(HOUR, NOW(), r.pickup_deadline) as hours_left
    FROM reservations r
    JOIN books_data b ON r.book_id = b.book_id
    WHERE r.member_id = ? AND r.status = 'pending'
    ORDER BY r.reservation_date DESC
");
$pending->bind_param("i", $member_id);
$pending->execute();
$pending_result = $pending->get_result();

// Get history (collected, expired, cancelled)
$history = $conn->prepare("
    SELECT r.*, b.title, b.author
    FROM reservations r
    JOIN books_data b ON r.book_id = b.book_id
    WHERE r.member_id = ? AND r.status IN ('collected', 'expired', 'cancelled')
    ORDER BY r.reservation_date DESC
    LIMIT 20
");
$history->bind_param("i", $member_id);
$history->execute();
$history_result = $history->get_result();
?>

<h1>📌 My Reservations</h1>

<!-- Statistics -->
<div class="stats-grid">
    <?php
    $stats_query = $conn->prepare("
        SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'collected' THEN 1 ELSE 0 END) as collected,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
        FROM reservations
        WHERE member_id = ?
    ");
    $stats_query->bind_param("i", $member_id);
    $stats_query->execute();
    $stats = $stats_query->get_result()->fetch_assoc();
    ?>
    
    <div class="stat-card orange">
        <h4>Pending Pickup</h4>
        <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
    </div>
    
    <div class="stat-card green">
        <h4>Successfully Collected</h4>
        <div class="stat-number"><?php echo $stats['collected'] ?? 0; ?></div>
    </div>
    
    <div class="stat-card red">
        <h4>Expired</h4>
        <div class="stat-number"><?php echo $stats['expired'] ?? 0; ?></div>
    </div>
</div>

<!-- Pending Reservations -->
<div class="card">
    <div class="card-header">
        <h3>⏰ Pending Pickup</h3>
    </div>
    
    <?php if ($pending_result->num_rows > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Reservation Code</th>
                        <th>Book</th>
                        <th>Reserved On</th>
                        <th>Pickup Deadline</th>
                        <th>Time Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $pending_result->fetch_assoc()): ?>
                        <?php
                        $is_urgent = $row['hours_left'] < 6;
                        ?>
                        <tr style="<?php echo $is_urgent ? 'background: #fff3cd;' : ''; ?>">
                            <td>
                                <strong style="font-size: 1.2rem; color: #667eea; letter-spacing: 0.1rem;">
                                    <?php echo htmlspecialchars($row['reservation_number']); ?>
                                </strong>
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
                                        echo number_format($row['hours_left'], 1) . ' hours';
                                    }
                                    ?>
                                </strong>
                                <?php if ($is_urgent): ?>
                                    <br><span class="badge badge-danger">⚠️ Urgent!</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="reservation_success.php?code=<?php echo $row['reservation_number']; ?>" 
                                   class="btn btn-primary btn-sm">View Details</a>
                                <a href="?cancel=<?php echo $row['reservation_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <p style="font-size: 1.1rem; color: #666;">You don't have any pending reservations.</p>
            <a href="books.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Books to Reserve</a>
        </div>
    <?php endif; ?>
</div>

<!-- Reservation History -->
<div class="card">
    <div class="card-header">
        <h3>📋 Reservation History</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Book</th>
                    <th>Reserved On</th>
                    <th>Deadline</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($history_result->num_rows > 0): ?>
                    <?php while ($row = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['reservation_number']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['author']); ?></small>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['reservation_date'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['pickup_deadline'])); ?></td>
                            <td>
                                <?php if ($row['status'] == 'collected'): ?>
                                    <span class="badge badge-success">✓ Collected</span>
                                <?php elseif ($row['status'] == 'expired'): ?>
                                    <span class="badge badge-danger">⌛ Expired</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">✗ Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No reservation history yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info">
    <strong>💡 Tips:</strong><br>
    • Reservations expire after 24 hours if not collected<br>
    • Show your reservation code to staff when picking up<br>
    • You can cancel pending reservations anytime<br>
    • After collecting, the book is borrowed for 14 days
</div>

<?php require_once '../../includes/footer.php'; ?>