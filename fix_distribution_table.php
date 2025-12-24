<?php
/**
 * Database Repair Tool
 * ใช้สำหรับสร้างตาราง distribution ที่ขาดหายไป
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// เรียกใช้การเชื่อมต่อฐานข้อมูล
require_once 'config/db.php';

// ตรวจสอบตัวแปรเชื่อมต่อ (รองรับทั้ง $pdo และ $conn)
if (!isset($pdo)) {
    if (isset($db) && property_exists($db, 'pdo')) {
        $pdo = $db->pdo;
    } elseif (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    } else {
        die("<h3>Error:</h3> ไม่พบตัวแปรเชื่อมต่อฐานข้อมูล (\$pdo) กรุณาตรวจสอบ config/db.php");
    }
}

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>🛠️ Database Repair Tool</h2>";

try {
    // 1. ตรวจสอบว่ามีตารางชื่อ distributions (เติม s) หรือไม่ (เผื่อตั้งชื่อผิด)
    $stmt = $pdo->query("SHOW TABLES LIKE 'distributions'");
    if ($stmt->rowCount() > 0) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
        echo "<strong>⚠️ ข้อสังเกต:</strong> พบตารางชื่อ <code>distributions</code> (มี s) ในฐานข้อมูล <br>";
        echo "แต่โค้ดปัจจุบันเรียกใช้ <code>distribution</code> (ไม่มี s) <br>";
        echo "ระบบจะทำการสร้างตาราง <code>distribution</code> (ไม่มี s) เพิ่มให้ เพื่อให้โค้ดทำงานได้";
        echo "</div>";
    }

    // 2. คำสั่ง SQL สำหรับสร้างตาราง distribution
    $sql = "CREATE TABLE IF NOT EXISTS `distribution` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `inventory_id` int(11) NOT NULL COMMENT 'รหัสสินค้าในคลัง',
      `item_name` varchar(255) NOT NULL COMMENT 'ชื่อสิ่งของ (Cache)',
      `quantity` int(11) NOT NULL COMMENT 'จำนวนที่แจก',
      `unit` varchar(50) DEFAULT NULL COMMENT 'หน่วยนับ',
      `recipient_name` varchar(255) NOT NULL COMMENT 'ชื่อผู้รับ',
      `shelter_id` int(11) DEFAULT NULL COMMENT 'ศูนย์พักพิง (ถ้ามี)',
      `distributed_by` int(11) DEFAULT NULL COMMENT 'ผู้บันทึก (User ID)',
      `distribution_date` datetime DEFAULT current_timestamp() COMMENT 'วันที่แจกจ่าย',
      `notes` text DEFAULT NULL COMMENT 'หมายเหตุ',
      PRIMARY KEY (`id`),
      KEY `shelter_id` (`shelter_id`),
      KEY `distributed_by` (`distributed_by`),
      KEY `inventory_id` (`inventory_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    // 3. รันคำสั่งสร้างตาราง
    $pdo->exec($sql);

    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<strong>✅ Success!</strong> สร้างตาราง <code>distribution</code> เรียบร้อยแล้ว<br><br>";
    echo "<a href='distribution_manager.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>กลับไปหน้าจัดการการแจกจ่าย</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div>";
?>