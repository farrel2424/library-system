<?php
require_once '../../config/database.php';
requireLogin();

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id > 0) {
    // Check if book has active borrowing transactions
    $check = $conn->prepare("SELECT COUNT(*) as count FROM borrowing_transactions WHERE book_id = ? AND status = 'borrowed'");
    $check->bind_param("i", $book_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = 'Cannot delete book with active borrowing transactions. Please wait for all books to be returned.';
    } else {
        $stmt = $conn->prepare("DELETE FROM books_data WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Book deleted successfully!';
        } else {
            $_SESSION['error'] = 'Failed to delete book: ' . $conn->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error'] = 'Invalid book ID';
}

header("Location: index.php");
exit();
?>