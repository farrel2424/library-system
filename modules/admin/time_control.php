<?php
$pageTitle = 'Time Control (Testing)';
require_once '../../includes/header.php';

// Only staff can access
requireStaff();

// Handle time manipulation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['reset_time'])) {
        // Reset to real time
        unset($_SESSION['time_offset']);
        $_SESSION['success'] = 'Time reset to real-time!';
    } elseif (isset($_POST['set_time'])) {
        $custom_date = clean($_POST['custom_date']);
        $custom_time = clean($_POST['custom_time']);
        
        if (!empty($custom_date) && !empty($custom_time)) {
            $_SESSION['time_offset'] = $custom_date . ' ' . $custom_time;
            $_SESSION['success'] = 'Time set to: ' . $_SESSION['time_offset'];
        }
    } elseif (isset($_POST['add_hours'])) {
        $hours = intval($_POST['hours']);
        $current = isset($_SESSION['time_offset']) ? $_SESSION['time_offset'] : date('Y-m-d H:i:s');
        $_SESSION['time_offset'] = date('Y-m-d H:i:s', strtotime($current . " +{$hours} hours"));
        $_SESSION['success'] = "Added {$hours} hours. New time: " . $_SESSION['time_offset'];
    } elseif (isset($_POST['add_days'])) {
        $days = intval($_POST['days']);
        $current = isset($_SESSION['time_offset']) ? $_SESSION['time_offset'] : date('Y-m-d H:i:s');
        $_SESSION['time_offset'] = date('Y-m-d H:i:s', strtotime($current . " +{$days} days"));
        $_SESSION['success'] = "Added {$days} days. New time: " . $_SESSION['time_offset'];
    }
    
    header("Location: time_control.php");
    exit();
}

$current_real_time = date('Y-m-d H:i:s');
$current_system_time = getCurrentDateTime();
$is_manipulated = isset($_SESSION['time_offset']);
?>

<div class="card">
    <div class="card-header">
        <h3>⏰ Time Control Panel (For Testing)</h3>
    </div>
    
    <div class="alert alert-warning">
        <strong>⚠️ Testing Feature:</strong> This feature is for testing due dates, fines, and reservations. 
        Only staff can access this page.
    </div>
    
    <!-- Current Time Display -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div style="background: #e3f2fd; padding: 1.5rem; border-radius: 5px;">
            <h4 style="margin: 0 0 0.5rem 0; color: #1976d2;">🌍 Real Time</h4>
            <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">
                <?php echo date('d M Y, H:i:s', strtotime($current_real_time)); ?>
            </p>
        </div>
        
        <div style="background: <?php echo $is_manipulated ? '#fff3e0' : '#e8f5e9'; ?>; padding: 1.5rem; border-radius: 5px;">
            <h4 style="margin: 0 0 0.5rem 0; color: <?php echo $is_manipulated ? '#f57c00' : '#388e3c'; ?>;">
                <?php echo $is_manipulated ? '🔧 Manipulated Time' : '✓ System Time'; ?>
            </h4>
            <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">
                <?php echo date('d M Y, H:i:s', strtotime($current_system_time)); ?>
            </p>
            <?php if ($is_manipulated): ?>
                <p style="margin: 0.5rem 0 0 0; color: #f57c00; font-size: 0.875rem;">
                    ⚠️ Time is being manipulated
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Reset Time -->
    <?php if ($is_manipulated): ?>
        <form method="POST" action="" style="margin-bottom: 2rem;">
            <button type="submit" name="reset_time" class="btn btn-danger" style="width: 100%;">
                🔄 Reset to Real Time
            </button>
        </form>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
        <h4 style="margin: 0 0 1rem 0;">⚡ Quick Time Jump</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Add Hours</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="hours" class="form-control" value="1" min="1" max="168" required>
                        <button type="submit" name="add_hours" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </form>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Add Days</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="days" class="form-control" value="1" min="1" max="365" required>
                        <button type="submit" name="add_days" class="btn btn-primary">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Custom Date/Time -->
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px;">
        <h4 style="margin: 0 0 1rem 0;">📅 Set Custom Date & Time</h4>
        
        <form method="POST" action="">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label>Date</label>
                    <input type="date" name="custom_date" class="form-control" 
                           value="<?php echo date('Y-m-d', strtotime($current_system_time)); ?>" required>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label>Time</label>
                    <input type="time" name="custom_time" class="form-control" 
                           value="<?php echo date('H:i', strtotime($current_system_time)); ?>" required>
                </div>
                
                <button type="submit" name="set_time" class="btn btn-primary">Set Time</button>
            </div>
        </form>
    </div>
</div>

<!-- Testing Scenarios -->
<div class="card">
    <div class="card-header">
        <h3>🧪 Common Testing Scenarios</h3>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
        <div style="background: #e3f2fd; padding: 1rem; border-radius: 5px;">
            <h5 style="margin: 0 0 0.5rem 0;">Test Reservation Expiry</h5>
            <p style="margin: 0; font-size: 0.875rem; color: #666;">
                Set time +25 hours to see expired reservations
            </p>
        </div>
        
        <div style="background: #fff3e0; padding: 1rem; border-radius: 5px;">
            <h5 style="margin: 0 0 0.5rem 0;">Test Overdue Books</h5>
            <p style="margin: 0; font-size: 0.875rem; color: #666;">
                Set time +15 days from borrow date
            </p>
        </div>
        
        <div style="background: #ffebee; padding: 1rem; border-radius: 5px;">
            <h5 style="margin: 0 0 0.5rem 0;">Test Auto Suspension</h5>
            <p style="margin: 0; font-size: 0.875rem; color: #666;">
                Set time +14 days after unpaid fine return
            </p>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>💡 How it works:</strong><br>
    • Time manipulation only affects the current staff session<br>
    • Members will see real time<br>
    • System uses manipulated time for: due dates, fines, reservations, suspensions<br>
    • Reset to real time before production use<br>
    • All timestamps in database still use real server time
</div>

<?php require_once '../../includes/footer.php'; ?>