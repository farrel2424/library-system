<?php
// ✅ NO OUTPUT before this point - include database first
require_once '../../config/database.php';

// Only staff can access
requireStaff();

// ✅ Handle ALL form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staff_id = $_SESSION['staff_id'];
    
    if (isset($_POST['reset_time'])) {
        // Reset to real time - set NULL in database
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = NULL, updated_by = ? WHERE setting_key = 'system_time_offset'");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = '✅ Time reset to real-time! All users (staff & members) now see real time.';
        
    } elseif (isset($_POST['set_time'])) {
        $custom_date = clean($_POST['custom_date']);
        $custom_time = clean($_POST['custom_time']);
        
        if (!empty($custom_date) && !empty($custom_time)) {
            $new_time = $custom_date . ' ' . $custom_time;
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'system_time_offset'");
            $stmt->bind_param("si", $new_time, $staff_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success'] = '✅ Time set to: ' . $new_time . ' (affects ALL users)';
        }
        
    } elseif (isset($_POST['add_hours'])) {
        $hours = intval($_POST['hours']);
        $current = getCurrentDateTime();
        $new_time = date('Y-m-d H:i:s', strtotime($current . " +{$hours} hours"));
        
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'system_time_offset'");
        $stmt->bind_param("si", $new_time, $staff_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "✅ Added {$hours} hours. New time: " . $new_time . ' (affects ALL users)';
        
    } elseif (isset($_POST['add_days'])) {
        $days = intval($_POST['days']);
        $current = getCurrentDateTime();
        $new_time = date('Y-m-d H:i:s', strtotime($current . " +{$days} days"));
        
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'system_time_offset'");
        $stmt->bind_param("si", $new_time, $staff_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "✅ Added {$days} days. New time: " . $new_time . ' (affects ALL users)';
    }
    
    // ✅ Redirect BEFORE any HTML output
    header("Location: time_control.php");
    exit();
}

// ✅ NOW include header (after all processing)
$pageTitle = 'Time Control (Testing)';
require_once '../../includes/header.php';

$current_real_time = date('Y-m-d H:i:s');
$current_system_time = getCurrentDateTime();
$is_manipulated = isTimeManipulated();

// Calculate time difference if manipulated
$time_diff = '';
if ($is_manipulated) {
    $real = strtotime($current_real_time);
    $system = strtotime($current_system_time);
    $diff_seconds = $system - $real;
    
    $days = floor(abs($diff_seconds) / 86400);
    $hours = floor((abs($diff_seconds) % 86400) / 3600);
    
    if ($diff_seconds > 0) {
        $time_diff = "+{$days} days, {$hours} hours ahead";
    } else {
        $time_diff = "-{$days} days, {$hours} hours behind";
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3>⏰ Global Time Control Panel</h3>
    </div>
    
    <div class="alert alert-warning">
        <strong>⚠️ GLOBAL Testing Feature:</strong><br>
        • Time changes affect <strong>ALL USERS</strong> (both staff and members)<br>
        • Stored in database, not session - persists across logins<br>
        • Members will see manipulated time for loans, fines, and suspensions<br>
        • Only staff can control time manipulation<br>
        • <strong>Remember to reset before production!</strong>
    </div>
    
    <!-- Current Time Display -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div style="background: #e3f2fd; padding: 1.5rem; border-radius: 5px;">
            <h4 style="margin: 0 0 0.5rem 0; color: #1976d2;">🌍 Real Server Time</h4>
            <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">
                <?php echo date('d M Y, H:i:s', strtotime($current_real_time)); ?>
            </p>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #666;">
                Actual server time (PHP date)
            </p>
        </div>
        
        <div style="background: <?php echo $is_manipulated ? '#fff3e0' : '#e8f5e9'; ?>; padding: 1.5rem; border-radius: 5px; border: 3px solid <?php echo $is_manipulated ? '#ff9800' : '#4caf50'; ?>;">
            <h4 style="margin: 0 0 0.5rem 0; color: <?php echo $is_manipulated ? '#f57c00' : '#388e3c'; ?>;">
                <?php echo $is_manipulated ? '🔧 System Time (MANIPULATED)' : '✓ System Time (Real)'; ?>
            </h4>
            <p style="margin: 0; font-size: 1.5rem; font-weight: bold;">
                <?php echo date('d M Y, H:i:s', strtotime($current_system_time)); ?>
            </p>
            <?php if ($is_manipulated): ?>
                <p style="margin: 0.5rem 0 0 0; color: #f57c00; font-size: 0.875rem; font-weight: bold;">
                    ⚠️ <?php echo $time_diff; ?>
                </p>
            <?php else: ?>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: #388e3c;">
                    All users see real time
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Reset Time -->
    <?php if ($is_manipulated): ?>
        <form method="POST" action="" style="margin-bottom: 2rem;">
            <button type="submit" name="reset_time" class="btn btn-danger" style="width: 100%; font-size: 1.1rem; padding: 1rem;"
                    onclick="return confirm('Reset time to real-time? This will affect ALL users immediately.')">
                🔄 Reset to Real Time (Affects Everyone)
            </button>
        </form>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem; border: 2px solid #dee2e6;">
        <h4 style="margin: 0 0 1rem 0;">⚡ Quick Time Jump</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Add Hours</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="hours" class="form-control" value="1" min="1" max="720" required>
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
    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; border: 2px solid #dee2e6;">
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
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <div style="background: #e3f2fd; padding: 1.5rem; border-radius: 5px; border-left: 4px solid #2196F3;">
            <h5 style="margin: 0 0 0.5rem 0; color: #1976d2;">Test Reservation Expiry</h5>
            <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #666;">
                Create a reservation, then add +25 hours to see it expire
            </p>
            <p style="margin: 0; font-size: 0.875rem; font-weight: bold; color: #1976d2;">
                Add: 25 hours
            </p>
        </div>
        
        <div style="background: #fff3e0; padding: 1.5rem; border-radius: 5px; border-left: 4px solid #ff9800;">
            <h5 style="margin: 0 0 0.5rem 0; color: #f57c00;">Test Overdue Books</h5>
            <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #666;">
                Borrow a book, then add +15 days to make it overdue
            </p>
            <p style="margin: 0; font-size: 0.875rem; font-weight: bold; color: #f57c00;">
                Add: 15+ days
            </p>
        </div>
        
        <div style="background: #ffebee; padding: 1.5rem; border-radius: 5px; border-left: 4px solid #f44336;">
            <h5 style="margin: 0 0 0.5rem 0; color: #c62828;">Test Auto Suspension</h5>
            <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #666;">
                Return book late, then add +14 days to trigger suspension
            </p>
            <p style="margin: 0; font-size: 0.875rem; font-weight: bold; color: #c62828;">
                Add: 14+ days after return
            </p>
        </div>
    </div>
</div>

<!-- How It Works -->
<div class="alert alert-info">
    <strong>💡 How Global Time Manipulation Works:</strong><br>
    <ul style="margin: 0.5rem 0 0 0; padding-left: 20px;">
        <li><strong>Database Storage:</strong> Time offset is stored in <code>system_settings</code> table</li>
        <li><strong>Affects Everyone:</strong> ALL users (staff + members) see the manipulated time</li>
        <li><strong>Used Everywhere:</strong> Due dates, fines, reservations, suspensions all use manipulated time</li>
        <li><strong>Persistent:</strong> Time setting persists even after logout/login</li>
        <li><strong>Only Staff Control:</strong> Only staff can change time, members just experience it</li>
        <li><strong>Real Timestamps:</strong> Database timestamps still record real server time for audit</li>
    </ul>
</div>

<!-- Test Link -->
<div style="text-align: center; margin-top: 2rem;">
    <a href="test_suspension.php" class="btn btn-warning" style="font-size: 1.1rem; padding: 1rem 2rem;">
        🧪 Open Suspension Testing Tool
    </a>
</div>

<?php require_once '../../includes/footer.php'; ?>