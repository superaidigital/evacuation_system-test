<?php
// fix_db_caretakers.php
require_once 'config/db.php';

echo "<h3>กำลังตรวจสอบและซ่อมแซมโครงสร้างตาราง Caretakers...</h3>";

try {
    // 1. ตรวจสอบคอลัมน์ที่มีอยู่
    $stmt = $pdo->query("SHOW COLUMNS FROM caretakers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "คอลัมน์ปัจจุบัน: " . implode(", ", $columns) . "<br><br>";

    // 2. ถ้าไม่มี first_name ให้เพิ่มเข้าไป
    if (!in_array('first_name', $columns)) {
        echo "ไม่พบคอลัมน์ first_name, กำลังดำเนินการเพิ่ม...<br>";
        
        // เพิ่มคอลัมน์ prefix, first_name, last_name
        $sql_alter = "ALTER TABLE caretakers 
                      ADD COLUMN prefix VARCHAR(50) DEFAULT NULL AFTER id,
                      ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT '' AFTER prefix,
                      ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name";
        
        // ตรวจสอบว่ามี position หรือไม่ ถ้าไม่มีก็เพิ่ม
        if (!in_array('position', $columns)) {
            $sql_alter .= ", ADD COLUMN position VARCHAR(100) DEFAULT NULL";
        }

        $pdo->exec($sql_alter);
        echo "✅ เพิ่มคอลัมน์สำเร็จ<br>";

        // 3. ย้ายข้อมูลจาก 'name' (ถ้ามี) ไปยัง first_name/last_name
        if (in_array('name', $columns)) {
            echo "พบคอลัมน์ 'name' เก่า, กำลังย้ายข้อมูล...<br>";
            $rows = $pdo->query("SELECT id, name FROM caretakers")->fetchAll();
            foreach ($rows as $row) {
                $parts = explode(' ', trim($row['name']), 2);
                $fname = $parts[0];
                $lname = isset($parts[1]) ? $parts[1] : '';
                
                $upd = $pdo->prepare("UPDATE caretakers SET first_name = ?, last_name = ? WHERE id = ?");
                $upd->execute([$fname, $lname, $row['id']]);
            }
            echo "✅ ย้ายข้อมูลเสร็จสิ้น<br>";
        }
    } else {
        echo "✅ โครงสร้างตารางถูกต้องอยู่แล้ว ไม่ต้องแก้ไข<br>";
    }

    echo "<hr><h4 style='color:green'>เสร็จสิ้น! คุณสามารถกลับไปใช้งานหน้ารายชื่อผู้ดูแลได้แล้ว</h4>";
    echo "<a href='caretaker_list.php'>ไปที่หน้าทำเนียบผู้ดูแล</a>";

} catch (PDOException $e) {
    echo "<h4 style='color:red'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</h4>";
}
?>