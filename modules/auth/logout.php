<?php
require_once '../../config/database.php';

// Destroy session and redirect to login
session_destroy();
header("Location: /library-system/modules/auth/login.php");
exit();
?>