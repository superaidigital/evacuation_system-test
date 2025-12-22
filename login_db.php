<?php
// login_db.php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Security Check: CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    validateCSRFToken($csrf_token);

    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        header("Location: login.php");
        exit();
    }

    try {
        // 2. ดึงข้อมูล User
        $stmt = $pdo->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // 3. Verify Password
        if ($user && password_verify($password, $user['password'])) {
            
            // 4. Session Security: Regenerate ID ป้องกัน Session Fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fullname'] = $user['full_name'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time(); // สำหรับ Auto Logout

            // บันทึก Log
            logActivity($pdo, $user['id'], 'Login', 'เข้าสู่ระบบสำเร็จ');

            header("Location: index.php");
            exit();
        } else {
            // Brute Force Prevention (Optional: ควรเพิ่มการ Delay หรือนับครั้งที่ผิด)
            sleep(1); // หน่วงเวลาเล็กน้อยเพื่อป้องกัน Brute Force เร็วเกินไป
            $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        $_SESSION['error'] = "เกิดข้อผิดพลาดทางเทคนิค กรุณาลองใหม่ภายหลัง";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>