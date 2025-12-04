<?php
$pageTitle = 'Book Damage Records';
require_once '../../includes/header.php';

// Only staff can access
requireStaff();

// Get filter parameters
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : 'all';
$start_date = isset($_GET['start_date']) ? clean($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean($_GET['end_date']) : date('Y-m-d');

// Build query using the view
$query = "
    SELECT * FROM damage_report_view
    WHERE damage_date BETWEEN ? AND ?
";

if ($status_filter != 'all') {
    $query .= " AND payment_status = ?";
}

$query .= " ORDER BY damage_date DESC";

// Execute query
if ($status_filter != 'all') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $start_date, $end_date, $status_filter);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();

// Calculate statistics
$total_damages = $result->num_rows;
$total_fines = 0;
$total_unpaid = 0;
$total_paid = 0;

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
    $total_fines += $row['damage_fine'];
    if ($row['payment_status'] == 'unpaid') {
        $total_unpaid += $row['damage_fine'];
    } else {
        $total_paid += $row['damage_fine'];
    }
}

$stmt->close();

// Get damage type statistics
$damage_stats = $conn->query("
    SELECT dt.damage_name, dt.damage_code, COUNT(*) as count, SUM(bdr.damage_fine) as total_fine
    FROM book_damage_records bdr
    JOIN damage_types dt ON bdr.damage_type_id = dt.damage_type_id
    WHERE bdr.damage_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY dt.damage_type_id
    ORDER BY count DESC
");
?>

<h1>📋 Book Damage Records</h1>

<!-- Statistics Dashboard -->
<div class="stats-grid">
    <div class="stat-card red">
        <h4>Total Damages</h4>
        <div class="stat-number"><?php echo $total_damages; ?></div>
    </div>
    
    <div class="stat-card orange">
        <h4>Total Damage Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_fines, 0, ',', '.'); ?>
        </div>
    </div>
    
    <div class="stat-card">
        <h4>Unpaid Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_unpaid, 0, ',', '.'); ?>
        </div>
    </div>
    
    <div class="stat-card green">
        <h4>Paid Fines</h4>
        <div class="stat-number" style="font-size: 1.5rem;">
            Rp <?php echo number_format($total_paid, 0, ',', '.'); ?>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card">
    <div class="card-header">
        <h3>🔍 Filter Damage Records</h3>
        <a href="report_damage.php" class="btn btn-danger">+ Report New Damage</a>
    </div>
    
    <form method="GET" action="" style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" 
                       value="<?php echo $start_date; ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="end_date">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" 
                       value="<?php echo $end_date; ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label for="status">Payment Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="unpaid" <?php echo $status_filter == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Filter</button>
            </div>
        </div>
    </form>
</div>

<!-- Damage Type Statistics -->
<?php if ($damage_stats->num_rows > 0): ?>
<div class="card">
    <div class="card-header">
        <h3>📊 Damage Type Statistics</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Damage Type</th>
                    <th>Total Cases</th>
                    <th>Total Fines</th>
                    <th>Average Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($stat = $damage_stats->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($stat['damage_name']); ?></strong></td>
                        <td><?php echo $stat['count']; ?> cases</td>
                        <td>Rp <?php echo number_format($stat['total_fine'], 0, ',', '.'); ?></td>
                        <td>Rp <?php echo number_format($stat['total_fine'] / $stat['count'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Damage Records Table -->
<div class="card">
    <div class="card-header">
        <h3>📝 Damage Records</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Damage ID</th>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Damage Type</th>
                    <th>Book Value</th>
                    <th>Fine Amount</th>
                    <th>Status</th>
                    <th>Reported By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($records) > 0): ?>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <td>#<?php echo $row['damage_id']; ?></td>
                            <td><?php echo date('d M Y', strtotime($row['damage_date'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['member_name']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($row['member_email']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['book_title']); ?></strong><br>
                                <small style="color: #666;">by <?php echo htmlspecialchars($row['author']); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-warning" style="font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($row['damage_name']); ?>
                                </span>
                                <?php if (!empty($row['damage_notes'])): ?>
                                    <br><small style="color: #666;" title="<?php echo htmlspecialchars($row['damage_notes']); ?>">
                                        📝 <?php echo htmlspecialchars(substr($row['damage_notes'], 0, 30)); ?>...
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?php echo number_format($row['book_value'], 0, ',', '.'); ?></td>
                            <td>
                                <strong style="color: #dc3545; font-size: 1.1rem;">
                                    Rp <?php echo number_format($row['damage_fine'], 0, ',', '.'); ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($row['payment_status'] == 'unpaid'): ?>
                                    <span class="badge badge-danger">Unpaid</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Paid</span>
                                    <br><small style="color: #666;">
                                        <?php echo date('d M Y', strtotime($row['payment_date'])); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="color: #666;">
                                    <?php echo htmlspecialchars($row['reported_by_staff']); ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No damage records found for the selected period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (count($records) > 0): ?>
        <div style="margin-top: 1.5rem; text-align: right;">
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Report</button>
        </div>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    <strong>ℹ️ About Damage Fines:</strong><br>
    • Damage fines are calculated as a percentage of the book's replacement value<br>
    • Members must pay damage fines within 14 days to avoid account suspension<br>
    • Unpaid damage fines count toward the total unpaid balance for suspension calculation<br>
    • Members can pay damage fines through the Payment page in their member portal
</div>

<style>
@media print {
    .navbar, .footer, .btn, form, .alert {
        display: none !important;
    }
    
    .main-content {
        padding: 0 !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd;
        margin-bottom: 2rem;
        page-break-inside: avoid;
    }
    
    body {
        background: white !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>