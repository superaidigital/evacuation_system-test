<?php
// login_db.php
// Refactored: High Security Authentication (Prevent SQL Injection, Session Fixation, Brute Force)

require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// 1. รับค่าและ Clean Data
$username = cleanInput($_POST['username']);
$password = $_POST['password']; // รหัสผ่านไม่ต้อง Clean แต่ต้องตรวจสอบ

// 2. Brute Force Protection (Simple Mechanism)
// ตรวจสอบว่า IP นี้ Login ผิดเกิน 5 ครั้งใน 1 นาทีหรือไม่
// (ในระบบจริงควรใช้ Redis หรือ Database Table แยกเก็บ failed_logins)
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
    if (time() - $_SESSION['last_attempt_time'] < 60) {
        $_SESSION['swal_error'] = "คุณทำรายการผิดพลาดเกินกำหนด กรุณารอ 1 นาที";
        header("Location: login.php");
        exit();
    } else {
        // Reset หลังจากครบ 1 นาที
        $_SESSION['login_attempts'] = 0;
    }
}

try {
    // 3. Secure Query
    $stmt = $pdo->prepare("SELECT id, username, password, role, shelter_id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Password Verification
    // หมายเหตุ: ในฐานข้อมูล รหัสผ่านต้องถูก Hash ด้วย password_hash($pass, PASSWORD_DEFAULT) มาก่อน
    // หากระบบเก่ายังเป็น MD5 ต้องมี Logic การ Migrate (Auto-upgrade hash) ตรงนี้
    
    if ($user && password_verify($password, $user['password'])) {
        // --- Login Success ---
        
        // 4.1 Prevent Session Fixation (สำคัญมาก!)
        session_regenerate_id(true);
        
        // 4.2 Setup User Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['shelter_id'] = $user['shelter_id'];
        
        // Reset login attempts
        unset($_SESSION['login_attempts']);
        
        // 4.3 Log Login Activity
        logActivity($pdo, $user['id'], 'Login', 'เข้าสู่ระบบสำเร็จ');
        
        // Redirect ตาม Role หรือไปหน้า Dashboard
        header("Location: index.php");
        exit();
        
    } else {
        // --- Login Failed ---
        
        // Increment attempts
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt_time'] = time();
        
        // Security: ไม่ควรบอกละเอียดว่า "User ผิด" หรือ "Pass ผิด" เพื่อป้องกัน User Enumeration
        $_SESSION['swal_error'] = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
        header("Location: login.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['swal_error'] = "เกิดข้อผิดพลาดของระบบ";
    header("Location: login.php");
    exit();
}
?>