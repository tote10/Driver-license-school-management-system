<?php
if(session_status()===PHP_SESSION_NONE){
    session_start();
}
if(!isset($_SESSION['user_id']) || empty($_SESSION['role'])){
    header("Location: login.php");
    exit();
}
$role=$_SESSION['role'];
switch($role){
    case 'manager':
        header("Location: manager/enrollments.php");
        exit();
    case 'instructor':
        header("Location: instructor/dashboard.php");
        exit();
    case 'student':
        header("Location: student/dashboard.php");
        exit();
    case 'supervisor':
        header("Location: supervisor/dashboard.php");
        exit();
    default:
        session_unset();
        session_destroy();
        header("Location: login.php?error=Invalid role");
        exit();
}