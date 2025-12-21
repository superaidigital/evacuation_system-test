<?php
// config/db.php

// ควรเก็บค่าเหล่านี้ใน Environment Variable (.env) ใน Production
// แต่สำหรับการทดสอบหรือระบบภายใน สามารถกำหนดค่าตรงนี้ได้ (อย่าลืมเปลี่ยนรหัสผ่าน)
define('DB_HOST', 'localhost');
define('DB_NAME', 'evacuation_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ให้โยน Exception เมื่อเกิด Error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // ดึงข้อมูลเป็น Array Associative
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ใช้ Prepared Statements จริงๆ
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Security: ห้าม Echo Error ของ DB ออกหน้าจอ User เพราะจะเผยโครงสร้างระบบ
    error_log($e->getMessage());
    die("ขออภัย ระบบฐานข้อมูลขัดข้อง (Database Connection Error)");
}

// Function Helper สำหรับการเช็ค Login
function checkLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>