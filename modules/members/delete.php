<?php
require_once '../../config/database.php';
requireLogin();

$member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($member_id > 0) {
    // Check if member has active borrowing transactions
    $check = $conn->prepare("SELECT COUNT(*) as count FROM borrowing_transactions WHERE member_id = ? AND status = 'borrowed'");
    $check->bind_param("i", $member_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = 'Cannot delete member with active borrowing transactions. Please return all books first.';
    } else {
        $stmt = $conn->prepare("DELETE FROM members_data WHERE member_id = ?");
        $stmt->bind_param("i", $member_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Member deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete member: ' . $conn->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error'] = 'Invalid member ID';
}

header("Location: index.php");
exit();
?>