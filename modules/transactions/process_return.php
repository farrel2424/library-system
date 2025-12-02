<?php
require_once '../../config/database.php';
requireStaff();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $borrow_id = intval($_POST['borrow_id']);
    $return_date = clean($_POST['return_date']);
    
    $errors = [];
    
    // Validate borrowing transaction
    $borrow_check = $conn->prepare("SELECT bt.*, b.book_id FROM borrowing_transactions bt 
                                     JOIN books_data b ON bt.book_id = b.book_id 
                                     WHERE bt.borrow_id = ? AND bt.status = 'borrowed'");
    $borrow_check->bind_param("i", $borrow_id);
    $borrow_check->execute();
    $borrow_result = $borrow_check->get_result();
    
    if ($borrow_result->num_rows == 0) {
        $errors[] = 'Invalid borrowing transaction or book already returned';
    } else {
        $borrow = $borrow_result->fetch_assoc();
        
        // Validate return date
        if (strtotime($return_date) < strtotime($borrow['borrow_date'])) {
            $errors[] = 'Return date cannot be before borrow date';
        }
        
        // Calculate late days and fine
        $due_date = strtotime($borrow['due_date']);
        $return_timestamp = strtotime($return_date);
        
        $days_diff = floor(($return_timestamp - $due_date) / (60 * 60 * 24));
        $late_days = max(0, $days_diff);
        $fine_amount = calculateFine($late_days);
        
        // Determine payment status (auto-paid if no fine, unpaid if has fine)
        $payment_status = ($fine_amount > 0) ? 'unpaid' : 'paid';
    }
    $borrow_check->close();
    
    // Process if no errors
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert return transaction
            $stmt = $conn->prepare("INSERT INTO returning_transactions (borrow_id, return_date, late_days, fine_amount, payment_status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isids", $borrow_id, $return_date, $late_days, $fine_amount, $payment_status);
            $stmt->execute();
            $stmt->close();
            
            // Update borrowing transaction status
            $update_borrow = $conn->prepare("UPDATE borrowing_transactions SET status = 'returned' WHERE borrow_id = ?");
            $update_borrow->bind_param("i", $borrow_id);
            $update_borrow->execute();
            $update_borrow->close();
            
            // Update book stock
            $update_book = $conn->prepare("UPDATE books_data SET stock = stock + 1 WHERE book_id = ?");
            $update_book->bind_param("i", $borrow['book_id']);
            $update_book->execute();
            $update_book->close();
            
            // Update book status to available
            $conn->query("UPDATE books_data SET status = 'available' WHERE book_id = " . $borrow['book_id']);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = 'Book returned successfully!';
            if ($fine_amount > 0) {
                $success_message .= ' Late fee: Rp ' . number_format($fine_amount, 0, ',', '.') . ' (' . $late_days . ' days late). Payment status: UNPAID. Member must pay within 14 days to avoid suspension.';
            }
            
            $_SESSION['success'] = $success_message;
            header("Location: return.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error'] = 'Failed to process return: ' . $e->getMessage();
            header("Location: return.php");
            exit();
        }
        
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: return.php");
        exit();
    }
    
} else {
    header("Location: return.php");
    exit();
}
?>