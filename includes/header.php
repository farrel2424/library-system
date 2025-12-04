<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Library Management System'; ?></title>
    <link rel="stylesheet" href="/library-system/assets/css/style.css">
    <style>
        /* Dropdown Menu Styles */
        .nav-menu {
            position: relative;
        }
        
        .nav-menu .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            cursor: pointer;
            user-select: none;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% - 0.25rem);
            left: 0;
            background: white;
            min-width: 220px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 0.75rem 0 0.5rem 0;
            z-index: 9999;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        /* Add padding to dropdown container to bridge the gap */
        .nav-menu .dropdown::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            height: 0.25rem;
            display: block;
        }
        
        /* Show dropdown on hover */
        .nav-menu .dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu li {
            margin: 0;
            list-style: none;
        }
        
        .dropdown-menu a {
            display: block;
            padding: 0.75rem 1.2rem;
            color: #333;
            text-decoration: none;
            transition: all 0.2s;
            border-radius: 0;
            white-space: nowrap;
            margin: 0 0.5rem;
            border-radius: 4px;
        }
        
        .dropdown-menu a:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: none;
            color: #667eea;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <h2>📚 Library System</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="/library-system/index.php">Dashboard</a></li>
                
                <?php if (isStaff()): ?>
                    <!-- Staff Menu -->
                    <li><a href="/library-system/modules/members/index.php">Members</a></li>
                    <li><a href="/library-system/modules/books/index.php">Books</a></li>
                    <li><a href="/library-system/modules/transactions/borrow.php">Borrow</a></li>
                    <li><a href="/library-system/modules/transactions/return.php">Return</a></li>
                    <li><a href="/library-system/modules/reservations/index.php">Reservations</a></li>
                    <li><a href="/library-system/modules/reports/index.php">Reports</a></li>
                    
                    <!-- Damage Dropdown -->
                    <li class="dropdown">
                        <a href="javascript:void(0)" class="dropdown-toggle" style="background: rgba(220,53,69,0.15); border-radius: 8px;">
                            🔨 Damage ▾
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="/library-system/modules/transactions/report_damage.php">Report Damage</a></li>
                            <li><a href="/library-system/modules/transactions/damage_records.php">Damage Records</a></li>
                        </ul>
                    </li>
                    
                    <li><a href="/library-system/modules/admin/time_control.php" style="background: rgba(255,193,7,0.2); border-radius: 8px;">⏰ Time</a></li>
                <?php elseif (isMember()): ?>
                    <!-- Member Menu -->
                    <li><a href="/library-system/modules/member/books.php">Browse Books</a></li>
                    <li><a href="/library-system/modules/member/reservations.php">My Reservations</a></li>
                    <li><a href="/library-system/modules/member/history.php">My Borrowings</a></li>
                    <li><a href="/library-system/modules/member/payment.php">💳 Payments</a></li>
                <?php endif; ?>
                
                <li class="user-info">
                    <?php if (isStaff()): ?>
                        👤 <?php echo htmlspecialchars($_SESSION['staff_username']); ?>
                        <span style="background: #17a2b8; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.75rem; margin-left: 0.5rem;">STAFF</span>
                    <?php else: ?>
                        👥 <?php echo htmlspecialchars($_SESSION['member_name']); ?>
                        <span style="background: #28a745; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.75rem; margin-left: 0.5rem;">MEMBER</span>
                    <?php endif; ?>
                    <a href="/library-system/modules/auth/logout.php" class="btn-logout">Logout</a>
                </li>
            </ul>
        </div>
    </nav>
    <div class="container main-content">
        <?php
        // Display session messages
        if (isset($_SESSION['success'])) {
            echo showAlert($_SESSION['success'], 'success');
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo showAlert($_SESSION['error'], 'error');
            unset($_SESSION['error']);
        }
        ?>