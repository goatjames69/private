<?php
require_once '../config.php';

unset($_SESSION['admin']);
unset($_SESSION['admin_username']);
unset($_SESSION['role']);
unset($_SESSION['staff_id']);
unset($_SESSION['staff_username']);
header('Location: /admin/login.php');
exit;
?>
