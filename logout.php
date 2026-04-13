<?php
// dashboard.php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

switch($role) {
    case 'student':    header("Location: student/dashboard.php"); break;
    case 'instructor': header("Location: instructor/dashboard.php"); break;
    case 'supervisor': header("Location: supervisor/dashboard.php"); break;
    case 'manager':    header("Location: manager/dashboard.php"); break;
    default:           header("Location: login.php");
}
exit();
?>