<?php
// fix_database_role.php
// สคริปต์สำหรับแก้ไขฐานข้อมูลให้รองรับ Role ใหม่ (Donation Officer)
require_once 'config/db.php';

echo "<h3>กำลังตรวจสอบและแก้ไขฐานข้อมูล...</h3>";

try {
    // วิธีที่ 1: เปลี่ยนคอลัมน์ role เป็น VARCHAR(50) เพื่อความยืดหยุ่นสูงสุด (แนะนำ)
    // การใช้ VARCHAR จะทำให้เราเพิ่ม Role ใหม่ๆ ได้ในอนาคตโดยไม่ต้องแก้ Database อีก
    $sql = "ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'staff'";
    
    $pdo->exec($sql);
    
    echo "<div style='color: green; border: 1px solid green; padding: 10px; margin: 10px 0;'>";
    echo "<strong>✅ สำเร็จ!</strong> แก้ไขตาราง 'users' เรียบร้อยแล้ว<br>";
    echo "ตอนนี้ระบบรองรับสิทธิ์ 'donation_officer' แล้ว";
    echo "</div>";
    
    echo "<a href='user_manager.php' style='display:inline-block; padding:10px 20px; background:blue; color:white; text-decoration:none; border-radius:5px;'>กลับไปหน้าจัดการผู้ใช้งาน</a>";

} catch (PDOException $e) {
    echo "<div style='color: red; border: 1px solid red; padding: 10px; margin: 10px 0;'>";
    echo "<strong>❌ เกิดข้อผิดพลาด:</strong> " . $e->getMessage() . "<br>";
    echo "หาก Error แจ้งว่า Data truncated อาจต้องเคลียร์ข้อมูลในคอลัมน์ role ก่อน หรือแก้ไขผ่าน phpMyAdmin โดยตรง";
    echo "</div>";
}
?>