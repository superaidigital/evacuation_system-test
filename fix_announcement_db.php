<?php
// fix_announcement_db.php
// สคริปต์ซ่อมแซมตาราง announcements (เพิ่มคอลัมน์ที่ขาดหาย)

include('config/db.php');

echo "<h2>กำลังตรวจสอบโครงสร้างตาราง announcements...</h2>";

// 1. ตรวจสอบว่ามีตารางหรือไม่
$check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($check_table->num_rows == 0) {
    // ถ้าไม่มีตาราง ให้สร้างใหม่เลย
    $sql_create = "CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type ENUM('General', 'Urgent', 'Alert') DEFAULT 'General',
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql_create)) {
        echo "<span style='color:green'>สร้างตาราง 'announcements' เรียบร้อยแล้ว</span><br>";
    } else {
        die("<span style='color:red'>สร้างตารางผิดพลาด: " . $conn->error . "</span>");
    }
} else {
    echo "พบตาราง 'announcements' แล้ว กำลังตรวจสอบคอลัมน์...<br>";
}

// 2. ดึงรายชื่อคอลัมน์ที่มีอยู่
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM announcements");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

// 3. ตรวจสอบและเพิ่มคอลัมน์ที่ขาดหาย
// เรียงลำดับการเพิ่มเพื่อให้โครงสร้างสวยงาม
$missing_cols = [
    'type' => "ALTER TABLE announcements ADD COLUMN type ENUM('General', 'Urgent', 'Alert') DEFAULT 'General' AFTER content",
    'status' => "ALTER TABLE announcements ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER type",
    'created_by' => "ALTER TABLE announcements ADD COLUMN created_by INT NULL AFTER status",
    'created_at' => "ALTER TABLE announcements ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "ALTER TABLE announcements ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

$fixed = false;
foreach ($missing_cols as $col => $sql) {
    if (!in_array($col, $columns)) {
        echo "ไม่พบคอลัมน์ <b>$col</b>... กำลังเพิ่ม... ";
        if ($conn->query($sql)) {
            echo "<span style='color:green'>สำเร็จ</span><br>";
            $fixed = true;
        } else {
            echo "<span style='color:red'>ผิดพลาด: " . $conn->error . "</span><br>";
        }
    } else {
        // echo "คอลัมน์ $col มีอยู่แล้ว<br>";
    }
}

echo "<hr>";
if ($fixed) {
    echo "<h3>ปรับปรุงฐานข้อมูลเสร็จสมบูรณ์!</h3>";
    echo "<p>กรุณาลองบันทึกข้อมูลใหม่อีกครั้ง</p>";
} else {
    echo "<h3>ฐานข้อมูลปกติ ไม่มีการเปลี่ยนแปลง</h3>";
}

echo "<a href='announcement_manager.php' class='btn btn-primary'>กลับไปหน้าจัดการประกาศ</a>";
?>