<?php
$pageTitle = 'Manage Members';
require_once '../../includes/header.php';

// Only staff can access this page
requireStaff();

// Get all members
$query = "SELECT * FROM members_data ORDER BY member_id DESC";
$result = $conn->query($query);
?>

<div class="card">
    <div class="card-header">
        <h3>Members List</h3>
        <a href="add.php" class="btn btn-primary">+ Add New Member</a>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['member_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                            <td>
                                <?php if ($row['status'] == 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="edit.php?id=<?php echo $row['member_id']; ?>" 
                                   class="btn btn-warning btn-sm">Edit</a>
                                <a href="delete.php?id=<?php echo $row['member_id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirmDelete('Are you sure you want to delete this member?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No members found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>