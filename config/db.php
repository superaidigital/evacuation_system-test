<?php
// config/db.php

// แนะนำ: ใน Production ควรย้ายค่าเหล่านี้ไปไว้ใน .env file นอก web root
// ตัวอย่าง: $db_host = getenv('DB_HOST') ?: 'localhost';

define('DB_HOST', 'localhost');
define('DB_NAME', 'evacuation_db');
define('DB_USER', 'root');
define('DB_PASS', ''); 
define('DB_CHARSET', 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // สำคัญ: ป้องกัน SQL Injection ขั้นสูง
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Security: บันทึก Error ลง Server Log แทนการแสดงหน้าเว็บ
    error_log("Database Connection Error: " . $e->getMessage());
    // แสดงข้อความทั่วไปแก่ผู้ใช้
    die("ระบบฐานข้อมูลขัดข้อง กรุณาติดต่อผู้ดูแลระบบ (Error Code: DB001)");
}

// Security Header: ป้องกัน Clickjacking และ XSS
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

function checkLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // ป้องกัน Session Fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>