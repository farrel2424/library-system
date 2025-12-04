<?php
$pageTitle = 'Report Book Damage';
require_once '../../includes/header.php';

// Only staff can access
requireStaff();

$errors = [];
$damagePreview = null;

// Get borrow_id from query parameter or form
$borrow_id = isset($_GET['borrow_id']) ? intval($_GET['borrow_id']) : (isset($_POST['borrow_id']) ? intval($_POST['borrow_id']) : 0);

// Get borrowing transaction details if borrow_id provided
$transaction = null;
if ($borrow_id > 0) {
    $stmt = $conn->prepare("
        SELECT bt.borrow_id, bt.member_id, bt.book_id, bt.borrow_date,
               m.name as member_name, m.email, m.phone,
               b.title, b.author, b.book_value,
               rt.return_date, rt.damage_recorded
        FROM borrowing_transactions bt
        JOIN members_data m ON bt.member_id = m.member_id
        JOIN books_data b ON bt.book_id = b.book_id
        LEFT JOIN returning_transactions rt ON bt.borrow_id = rt.borrow_id
        WHERE bt.borrow_id = ? AND bt.status = 'returned'
    ");
    $stmt->bind_param("i", $borrow_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $transaction = $result->fetch_assoc();
        
        // Check if damage already recorded
        if ($transaction['damage_recorded']) {
            $_SESSION['error'] = 'Damage has already been recorded for this transaction.';
            $transaction = null;
        }
    }
    $stmt->close();
}

// Preview damage fine calculation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['preview'])) {
    $damage_type_id = intval($_POST['damage_type_id']);
    $damage_notes = clean($_POST['damage_notes']);
    
    if (empty($damage_type_id)) {
        $errors[] = 'Please select damage type';
    }
    
    if (empty($errors) && $transaction) {
        $damageType = getDamageTypeById($damage_type_id);
        $calculatedFine = calculateDamageFine($transaction['book_value'], $damage_type_id);
        
        $damagePreview = [
            'damage_type' => $damageType,
            'calculated_fine' => $calculatedFine,
            'damage_notes' => $damage_notes
        ];
    }
}

// Process damage report submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    $damage_type_id = intval($_POST['damage_type_id']);
    $damage_notes = clean($_POST['damage_notes']);
    $staff_id = $_SESSION['staff_id'];
    
    // Validation
    if (empty($damage_type_id)) $errors[] = 'Please select damage type';
    if (empty($damage_notes)) $errors[] = 'Please provide damage description';
    if (!$transaction) $errors[] = 'Invalid transaction';
    
    // Record damage
    if (empty($errors)) {
        $damageFine = recordBookDamage(
            $borrow_id,
            $damage_type_id,
            $damage_notes,
            $transaction['book_value'],
            $staff_id
        );
        
        if ($damageFine !== false) {
            $damageType = getDamageTypeById($damage_type_id);
            $_SESSION['success'] = sprintf(
                'Book damage recorded successfully! %s damage charged: Rp %s. Member must pay this damage fine.',
                $damageType['damage_name'],
                number_format($damageFine, 0, ',', '.')
            );
            header("Location: return.php");
            exit();
        } else {
            $errors[] = 'Failed to record damage. Please try again.';
        }
    }
}

// Get all returned books (not yet damage-checked)
$returned_books = $conn->query("
    SELECT bt.borrow_id, m.name as member_name, b.title, rt.return_date, rt.damage_recorded
    FROM borrowing_transactions bt
    JOIN members_data m ON bt.member_id = m.member_id
    JOIN books_data b ON bt.book_id = b.book_id
    JOIN returning_transactions rt ON bt.borrow_id = rt.borrow_id
    WHERE bt.status = 'returned'
    ORDER BY rt.return_date DESC
    LIMIT 20
");

// Get damage types
$damageTypes = getDamageTypes();
?>

<div class="card">
    <div class="card-header">
        <h3>📋 Report Book Damage</h3>
        <a href="return.php" class="btn btn-secondary">← Back to Returns</a>
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
    
    <div class="alert alert-info">
        <strong>ℹ️ When to Report Damage:</strong><br>
        Report damage only for books that have been returned. Inspect the book carefully and select the appropriate damage level. The system will automatically calculate the fine based on the book's value and damage severity.
    </div>
    
    <?php if (!$transaction): ?>
        <!-- Select Transaction Form -->
        <form method="GET" action="">
            <div class="form-group">
                <label for="borrow_id">Select Returned Book Transaction *</label>
                <select name="borrow_id" id="borrow_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">-- Choose Transaction --</option>
                    <?php while ($row = $returned_books->fetch_assoc()): ?>
                        <option value="<?php echo $row['borrow_id']; ?>"
                                <?php echo ($borrow_id == $row['borrow_id']) ? 'selected' : ''; ?>>
                            ID #<?php echo $row['borrow_id']; ?> - 
                            <?php echo htmlspecialchars($row['member_name']); ?> - 
                            <?php echo htmlspecialchars($row['title']); ?>
                            (Returned: <?php echo date('d M Y', strtotime($row['return_date'])); ?>)
                            <?php if ($row['damage_recorded']): ?>
                                - ✓ Damage Recorded
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>
    <?php else: ?>
        <!-- Damage Report Form -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 1rem 0;">📚 Book & Member Information</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <p style="margin: 0 0 0.5rem 0;"><strong>Book:</strong> <?php echo htmlspecialchars($transaction['title']); ?></p>
                    <p style="margin: 0 0 0.5rem 0;"><strong>Author:</strong> <?php echo htmlspecialchars($transaction['author']); ?></p>
                    <p style="margin: 0;"><strong>Book Value:</strong> Rp <?php echo number_format($transaction['book_value'], 0, ',', '.'); ?></p>
                </div>
                <div>
                    <p style="margin: 0 0 0.5rem 0;"><strong>Member:</strong> <?php echo htmlspecialchars($transaction['member_name']); ?></p>
                    <p style="margin: 0 0 0.5rem 0;"><strong>Email:</strong> <?php echo htmlspecialchars($transaction['email']); ?></p>
                    <p style="margin: 0;"><strong>Return Date:</strong> <?php echo date('d M Y', strtotime($transaction['return_date'])); ?></p>
                </div>
            </div>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="borrow_id" value="<?php echo $borrow_id; ?>">
            
            <div class="form-group">
                <label for="damage_type_id">Damage Type *</label>
                <select name="damage_type_id" id="damage_type_id" class="form-control" required>
                    <option value="">-- Select Damage Level --</option>
                    <?php 
                    mysqli_data_seek($damageTypes, 0);
                    while ($type = $damageTypes->fetch_assoc()): 
                        $selected = ($damagePreview && $damagePreview['damage_type']['damage_type_id'] == $type['damage_type_id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $type['damage_type_id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($type['damage_name']); ?> 
                            (<?php echo $type['fine_percentage']; ?>% - 
                            Rp <?php echo number_format(($transaction['book_value'] * $type['fine_percentage']) / 100, 0, ',', '.'); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="damage_notes">Damage Description *</label>
                <textarea name="damage_notes" id="damage_notes" class="form-control" rows="4" required 
                          placeholder="Describe the damage in detail (e.g., pages torn, water stains on cover, etc.)"><?php echo $damagePreview ? htmlspecialchars($damagePreview['damage_notes']) : ''; ?></textarea>
            </div>
            
            <?php if ($damagePreview): ?>
                <!-- Damage Preview -->
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 5px;">
                    <h4 style="margin: 0 0 1rem 0; color: #856404;">⚠️ Damage Fine Preview</h4>
                    
                    <div style="background: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                        <p style="margin: 0 0 0.5rem 0;"><strong>Damage Type:</strong> <?php echo htmlspecialchars($damagePreview['damage_type']['damage_name']); ?></p>
                        <p style="margin: 0 0 0.5rem 0;"><strong>Description:</strong> <?php echo htmlspecialchars($damagePreview['damage_type']['damage_description']); ?></p>
                        <p style="margin: 0 0 0.5rem 0;"><strong>Fine Percentage:</strong> <?php echo $damagePreview['damage_type']['fine_percentage']; ?>%</p>
                        <p style="margin: 0;"><strong>Book Value:</strong> Rp <?php echo number_format($transaction['book_value'], 0, ',', '.'); ?></p>
                    </div>
                    
                    <div style="background: #dc3545; color: white; padding: 1.5rem; border-radius: 5px; text-align: center;">
                        <p style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">CALCULATED DAMAGE FINE</p>
                        <p style="margin: 0; font-size: 2rem; font-weight: bold;">
                            Rp <?php echo number_format($damagePreview['calculated_fine'], 0, ',', '.'); ?>
                        </p>
                    </div>
                    
                    <p style="margin: 1rem 0 0 0; font-size: 0.875rem; color: #856404;">
                        <strong>Note:</strong> This fine will be added to the member's account as unpaid. The member must pay this within 14 days to avoid account suspension.
                    </p>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="confirm" class="btn btn-danger" 
                            onclick="return confirm('Are you sure you want to record this damage?\n\nFine: Rp <?php echo number_format($damagePreview['calculated_fine'], 0, ',', '.'); ?>\n\nThis action cannot be undone.')">
                        ✓ Confirm & Record Damage
                    </button>
                    <button type="submit" name="preview" class="btn btn-warning">
                        🔄 Update Preview
                    </button>
                    <a href="report_damage.php" class="btn btn-secondary">Cancel</a>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <button type="submit" name="preview" class="btn btn-primary">
                        👁️ Preview Damage Fine
                    </button>
                    <a href="report_damage.php" class="btn btn-secondary">Cancel</a>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<!-- Damage Type Reference -->
<div class="card">
    <div class="card-header">
        <h3>📖 Damage Type Reference Guide</h3>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Damage Type</th>
                    <th>Description</th>
                    <th>Fine Percentage</th>
                    <th>Example Fine<br/>(Based on Rp 150,000 book)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                mysqli_data_seek($damageTypes, 0);
                while ($type = $damageTypes->fetch_assoc()): 
                    $exampleFine = (150000 * $type['fine_percentage']) / 100;
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($type['damage_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($type['damage_description']); ?></td>
                        <td>
                            <span style="font-size: 1.1rem; font-weight: bold; color: #667eea;">
                                <?php echo $type['fine_percentage']; ?>%
                            </span>
                        </td>
                        <td>
                            <strong style="color: #dc3545;">
                                Rp <?php echo number_format($exampleFine, 0, ',', '.'); ?>
                            </strong>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>