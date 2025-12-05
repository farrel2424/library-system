<?php
// ✅ STEP 1: Include only database config (no HTML output)
require_once '../../config/database.php';

$errors = [];

// ✅ STEP 2: Process form submission BEFORE including header
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
    if (empty($password)) $errors[] = 'Password is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Check if email already exists
    if (empty($errors)) {
        $check = $conn->prepare("SELECT member_id FROM members_data WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
        $check->close();
    }
    
    // Insert if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO members_data (name, email, phone, address, password, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $phone, $address, $password, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Member added successfully!';
            $stmt->close();
            // ✅ REDIRECT BEFORE including header.php
            header("Location: index.php");
            exit();
        } else {
            $errors[] = 'Failed to add member: ' . $conn->error;
            $stmt->close();
        }
    }
}

// ✅ STEP 3: NOW include header (after all redirects)
$pageTitle = 'Add New Member';
require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3>Add New Member</h3>
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
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" name="email" id="email" class="form-control" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="text" name="phone" id="phone" class="form-control" 
                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" class="form-control"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="password">Password (for member login) *</label>
            <input type="text" name="password" id="password" class="form-control" 
                   value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>" 
                   placeholder="Enter password for member account"
                   required>
            <small style="color: #666;">This password will be used by the member to login to the system.</small>
        </div>
        
        <div class="form-group">
            <label for="status">Status *</label>
            <select name="status" id="status" class="form-control" required>
                <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo (isset($_POST['status']) && $_POST['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Add Member</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>