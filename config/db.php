<?php
/**
 * Database Configuration & Connection
 * Updated: รวม Code ของคุณเข้ากับ Class Wrapper เพื่อให้รองรับไฟล์ระบบเก่าและใหม่
 */

// 1. Configuration Constants (ย้ายมาไว้ที่นี่เพื่อให้แก้ไขจุดเดียว)
define('DB_HOST', 'localhost');
define('DB_NAME', 'evacuation_db');
define('DB_USER', 'root');
define('DB_PASS', ''); 
define('DB_CHARSET', 'utf8mb4');

// 2. Security Headers: ป้องกัน Clickjacking และ XSS
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// ตั้งค่า Timezone เป็นไทย
date_default_timezone_set('Asia/Bangkok');

// ==================================================================================
// SECTION A: MySQLi Connection (Legacy Support for Existing System)
// ==================================================================================
// จำเป็นต้องมีส่วนนี้เพื่อให้ไฟล์เดิม (เช่น public_dashboard.php, user_manager.php) ทำงานได้
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("MySQLi Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, DB_CHARSET);

// ==================================================================================
// SECTION B: PDO Connection (New System & Advanced Features)
// ==================================================================================
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // สำคัญ: ป้องกัน SQL Injection ขั้นสูง
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    // สร้างตัวแปร $pdo หลักสำหรับใช้งานทั่วไป
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Security: บันทึก Error ลง Server Log แทนการแสดงหน้าเว็บ
    error_log("Database Connection Error: " . $e->getMessage());
    // แสดงข้อความทั่วไปแก่ผู้ใช้
    die("ระบบฐานข้อมูลขัดข้อง (PDO Error) กรุณาติดต่อผู้ดูแลระบบ (Error Code: DB001)");
}

// ==================================================================================
// SECTION C: Helper Functions
// ==================================================================================

// 4. Helper Function: Check Login
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

// ==================================================================================
// SECTION D: Compatibility Layer (Database Class)
// ==================================================================================
// ส่วนเสริมเพื่อรองรับ distribution_manager.php และไฟล์ระบบใหม่ที่เรียกใช้ Class Database

class Database {
    public $pdo;
    public $error;

    public function __construct() {
        // ดึงตัวแปร $pdo ที่สร้างไว้ข้างบนมาใช้เลย (ไม่ต้อง connect ใหม่)
        global $pdo;
        if (isset($pdo)) {
            $this->pdo = $pdo;
        } else {
            // กรณี $pdo ข้างบนพัง (เผื่อไว้)
            $this->error = "Global PDO not found.";
        }
    }

    // Helper สำหรับ query แบบเดิมที่ใช้ $db->query()
    public function query($sql, $params = []) {
        if (!$this->pdo) return false;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt; // คืนค่าเป็น PDOStatement object
        } catch (PDOException $e) {
            error_log("DB Query Error: " . $e->getMessage());
            return false;
        }
    }
}

// สร้าง Instance $db ไว้ให้ไฟล์อื่นเรียกใช้
$db = new Database();

?>