<?php
$pageTitle = 'Edit Member';
require_once '../../includes/header.php';

$errors = [];
$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get member data
$stmt = $conn->prepare("SELECT * FROM members_data WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = 'Member not found';
    header("Location: index.php");
    exit();
}

$member = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $phone = clean($_POST['phone']);
    $address = clean($_POST['address']);
    $password = clean($_POST['password']);
    $status = clean($_POST['status']);
    
    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Check if email exists for other members
    if (empty($errors)) {
        $check = $conn->prepare("SELECT member_id FROM members_data WHERE email = ? AND member_id != ?");
        $check->bind_param("si", $email, $member_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
        $check->close();
    }
    
    // Update if no errors
    if (empty($errors)) {
        if (!empty($password)) {
            // Update with new password
            $stmt = $conn->prepare("UPDATE members_data SET name = ?, email = ?, phone = ?, address = ?, password = ?, status = ? WHERE member_id = ?");
            $stmt->bind_param("ssssssi", $name, $email, $phone, $address, $password, $status, $member_id);
        } else {
            // Update without changing password
            $stmt = $conn->prepare("UPDATE members_data SET name = ?, email = ?, phone = ?, address = ?, status = ? WHERE member_id = ?");
            $stmt->bind_param("sssssi", $name, $email, $phone, $address, $status, $member_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Member updated successfully!';
            header("Location: index.php");
            exit();
        } else {
            $errors[] = 'Failed to update member: ' . $conn->error;
        }
        $stmt->close();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3>Edit Member</h3>
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
            <label for="name">Full Name *</label>
            <input type="text" name="name" id="name" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['name']) ? $_POST['name'] : $member['name']); ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" name="email" id="email" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : $member['email']); ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="text" name="phone" id="phone" class="form-control" 
                   value="<?php echo htmlspecialchars(isset($_POST['phone']) ? $_POST['phone'] : $member['phone']); ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" class="form-control"><?php echo htmlspecialchars(isset($_POST['address']) ? $_POST['address'] : $member['address']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="password">Password (leave empty to keep current)</label>
            <input type="text" name="password" id="password" class="form-control" 
                   placeholder="Enter new password or leave empty">
            <small style="color: #666;">Leave empty if you don't want to change the password.</small>
        </div>
        
        <div class="form-group">
            <label for="status">Status *</label>
            <select name="status" id="status" class="form-control" required>
                <?php $current_status = isset($_POST['status']) ? $_POST['status'] : $member['status']; ?>
                <option value="active" <?php echo $current_status == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo $current_status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Update Member</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>