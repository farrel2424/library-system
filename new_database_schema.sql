-- ============================================
-- MIGRATION SCRIPT
-- From old structure to new structure
-- ============================================

USE library_system;

-- ============================================
-- BACKUP YOUR DATA FIRST!
-- ============================================
-- Run these to backup:
-- CREATE TABLE members_backup AS SELECT * FROM members;
-- CREATE TABLE books_backup AS SELECT * FROM books;
-- CREATE TABLE staff_backup AS SELECT * FROM staff;

-- ============================================
-- OPTION 1: RENAME EXISTING TABLES (Preserve data)
-- ============================================

-- Rename members to members_data
RENAME TABLE members TO members_data;

-- Rename books to books_data  
RENAME TABLE books TO books_data;

-- Add reserved_stock column to books_data
ALTER TABLE books_data ADD COLUMN reserved_stock INT NOT NULL DEFAULT 0 AFTER stock;
ALTER TABLE books_data ADD CHECK (reserved_stock >= 0);

-- Drop and recreate staff table with new structure as staff_data
DROP TABLE IF EXISTS staff_data;
DROP TABLE IF EXISTS staff;
CREATE TABLE staff_data (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_number VARCHAR(50) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    email VARCHAR(100) NOT NULL UNIQUE,
    hire_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert staff data
INSERT INTO staff_data (staff_number, username, password, phone_number, email, hire_date) VALUES
('STF001', 'admin', 'admin123', '081234567890', 'admin@library.com', '2024-01-01'),
('STF002', 'staff1', 'staff123', '081234567891', 'john@library.com', '2024-02-01'),
('STF003', 'staff2', 'staff123', '081234567892', 'jane@library.com', '2024-03-01');

-- Create reservations table
CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_number VARCHAR(4) NOT NULL UNIQUE,
    member_id INT NOT NULL,
    book_id INT NOT NULL,
    reservation_date DATETIME NOT NULL,
    expiry_date DATETIME NOT NULL,
    pickup_deadline DATETIME NOT NULL,
    status ENUM('pending', 'collected', 'expired', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members_data(member_id) ON DELETE RESTRICT,
    FOREIGN KEY (book_id) REFERENCES books_data(book_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Drop and recreate borrowing_transactions with reservation_id
DROP TABLE IF EXISTS returning_transactions;
DROP TABLE IF EXISTS borrowing_transactions;

CREATE TABLE borrowing_transactions (
    borrow_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    book_id INT NOT NULL,
    reservation_id INT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('borrowed', 'returned') DEFAULT 'borrowed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members_data(member_id) ON DELETE RESTRICT,
    FOREIGN KEY (book_id) REFERENCES books_data(book_id) ON DELETE RESTRICT,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE returning_transactions (
    return_id INT AUTO_INCREMENT PRIMARY KEY,
    borrow_id INT NOT NULL UNIQUE,
    return_date DATE NOT NULL,
    late_days INT DEFAULT 0,
    fine_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    payment_method VARCHAR(50) NULL,
    payment_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrow_id) REFERENCES borrowing_transactions(borrow_id) ON DELETE RESTRICT,
    CHECK (late_days >= 0),
    CHECK (fine_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create suspension penalties table
CREATE TABLE suspension_penalties (
    penalty_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    total_unpaid_fines DECIMAL(10, 2) NOT NULL,
    suspension_date DATETIME NOT NULL,
    penalty_amount DECIMAL(10, 2) DEFAULT 100000.00,
    payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    payment_method VARCHAR(50) NULL,
    payment_date DATETIME NULL,
    unsuspension_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members_data(member_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes
CREATE INDEX idx_staff_number ON staff_data(staff_number);
CREATE INDEX idx_staff_username ON staff_data(username);
CREATE INDEX idx_reservations_number ON reservations(reservation_number);
CREATE INDEX idx_reservations_status ON reservations(status);
CREATE INDEX idx_reservations_expiry ON reservations(expiry_date);

-- ============================================
-- VERIFICATION
-- ============================================
SELECT 'Tables successfully migrated!' AS status;
SELECT 'members_data' AS table_name, COUNT(*) AS row_count FROM members_data
UNION ALL
SELECT 'books_data', COUNT(*) FROM books_data
UNION ALL
SELECT 'staff_data', COUNT(*) FROM staff_data
UNION ALL
SELECT 'reservations', COUNT(*) FROM reservations;

-- ============================================
-- End of Migration
-- ============================================