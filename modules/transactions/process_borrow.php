<?php
require_once '../../config/database.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member_id = intval($_POST['member_id']);
    $book_id = intval($_POST['book_id']);
    $borrow_date = clean($_POST['borrow_date']);
    $due_date = clean($_POST['due_date']);
    
    $errors = [];
    
    // Validate member
    $member_check = $conn->prepare("SELECT status FROM members_data WHERE member_id = ?");
    $member_check->bind_param("i", $member_id);
    $member_check->execute();
    $member_result = $member_check->get_result();
    
    if ($member_result->num_rows == 0) {
        $errors[] = 'Invalid member selected';
    } else {
        $member = $member_result->fetch_assoc();
        if ($member['status'] != 'active') {
            $errors[] = 'Member account is suspended. Cannot borrow books.';
        }
    }
    $member_check->close();
    
    // Validate book availability
    $book_check = $conn->prepare("SELECT stock FROM books_data WHERE book_id = ?");
    $book_check->bind_param("i", $book_id);
    $book_check->execute();
    $book_result = $book_check->get_result();
    
    if ($book_result->num_rows == 0) {
        $errors[] = 'Invalid book selected';
    } else {
        $book = $book_result->fetch_assoc();
        if ($book['stock'] <= 0) {
            $errors[] = 'Book is out of stock. Cannot process borrowing.';
        }
    }
    $book_check->close();
    
    // Validate dates
    if (strtotime($due_date) <= strtotime($borrow_date)) {
        $errors[] = 'Due date must be after borrow date';
    }
    
    // Process if no errors
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert borrowing transaction
            $stmt = $conn->prepare("INSERT INTO borrowing_transactions (member_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')");
            $stmt->bind_param("iiss", $member_id, $book_id, $borrow_date, $due_date);
            $stmt->execute();
            $stmt->close();
            
            // Update book stock
            $update = $conn->prepare("UPDATE books_data SET stock = stock - 1 WHERE book_id = ?");
            $update->bind_param("i", $book_id);
            $update->execute();
            $update->close();
            
            // Update book status if stock becomes 0
            $conn->query("UPDATE books_data SET status = 'unavailable' WHERE book_id = $book_id AND stock = 0");
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = 'Book borrowed successfully! Transaction recorded.';
            header("Location: borrow.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = 'Failed to process borrowing: ' . $e->getMessage();
            header("Location: borrow.php");
            exit();
        }
        
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: borrow.php");
        exit();
    }
    
} else {
    header("Location: borrow.php");
    exit();
}
?>