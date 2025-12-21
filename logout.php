<?php
// logout.php
require_once 'config/db.php';
require_once 'includes/functions.php';
session_start();

// ถ้ามีการ Login อยู่ ให้บันทึก Log ว่าออกจากระบบ
if (isset($_SESSION['user_id'])) {
    logActivity($pdo, $_SESSION['user_id'], 'Logout', 'ออกจากระบบ');
}

// ล้างค่า Session ทั้งหมด
$_SESSION = array();

// ทำลาย Cookie Session (ถ้ามี)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย Session
session_destroy();

// ส่งกลับหน้า Login
header("Location: login.php");
exit();
?>