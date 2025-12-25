<?php
// fix_db_update.php
// สคริปต์ตรวจสอบและเพิ่มคอลัมน์ที่ขาดหายไปในตาราง users
require_once 'config/db.php';

echo "<h3>กำลังตรวจสอบโครงสร้างฐานข้อมูล...</h3>";

try {
    // 1. ตรวจสอบและเพิ่มคอลัมน์ first_name
    try {
        $pdo->query("SELECT first_name FROM users LIMIT 1");
        echo "<div style='color:green;'>- คอลัมน์ 'first_name' มีอยู่แล้ว</div>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) DEFAULT '' AFTER password");
        echo "<div style='color:blue;'>+ เพิ่มคอลัมน์ 'first_name' สำเร็จ</div>";
    }

    // 2. ตรวจสอบและเพิ่มคอลัมน์ last_name
    try {
        $pdo->query("SELECT last_name FROM users LIMIT 1");
        echo "<div style='color:green;'>- คอลัมน์ 'last_name' มีอยู่แล้ว</div>";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) DEFAULT '' AFTER first_name");
        echo "<div style='color:blue;'>+ เพิ่มคอลัมน์ 'last_name' สำเร็จ</div>";
    }

    // 3. ตรวจสอบและแก้ไขคอลัมน์ role ให้รองรับ donation_officer
    // ใช้ MODIFY เพื่อขยาย ENUM หรือเปลี่ยนเป็น VARCHAR (แนะนำ VARCHAR เพื่อความยืดหยุ่น)
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'staff'");
    echo "<div style='color:blue;'>* อัปเดตคอลัมน์ 'role' ให้รองรับทุกสิทธิ์ สำเร็จ</div>";

    echo "<hr>";
    echo "<div style='padding: 15px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<strong>✅ ปรับปรุงฐานข้อมูลเสร็จสมบูรณ์!</strong> คุณสามารถกลับไปใช้งานหน้าจัดการผู้ใช้ได้แล้ว";
    echo "</div>";
    
    echo "<br><a href='user_manager.php' style='display:inline-block; padding:10px 20px; background:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>กลับไปหน้าจัดการผู้ใช้งาน</a>";

} catch (PDOException $e) {
    echo "<div style='padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<strong>❌ เกิดข้อผิดพลาด SQL:</strong> " . $e->getMessage();
    echo "</div>";
}
?>