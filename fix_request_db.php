<?php
// fix_request_db.php
// สคริปต์ซ่อมแซมตาราง requests (เพิ่มคอลัมน์ status)

include('config/db.php');

echo "<h2>กำลังตรวจสอบโครงสร้างตาราง requests...</h2>";

// 1. ตรวจสอบว่ามีตาราง requests หรือไม่
$check_table = $conn->query("SHOW TABLES LIKE 'requests'");
if ($check_table->num_rows == 0) {
    // ถ้าไม่มี ให้สร้างใหม่
    $sql_create = "CREATE TABLE requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shelter_id INT NOT NULL,
        requester_name VARCHAR(255),
        item_needed VARCHAR(255),
        quantity INT,
        unit VARCHAR(50),
        status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql_create)) {
        echo "<span style='color:green'>สร้างตาราง 'requests' เรียบร้อยแล้ว</span><br>";
    } else {
        die("<span style='color:red'>สร้างตารางผิดพลาด: " . $conn->error . "</span>");
    }
} else {
    echo "พบตาราง 'requests' แล้ว<br>";
}

// 2. ตรวจสอบคอลัมน์ที่มีอยู่
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM requests");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

// 3. เพิ่มคอลัมน์ที่ขาดหาย (โดยเฉพาะ status)
$missing_cols = [
    'status' => "ALTER TABLE requests ADD COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Pending' AFTER unit",
    'shelter_id' => "ALTER TABLE requests ADD COLUMN shelter_id INT NOT NULL AFTER id",
    'requester_name' => "ALTER TABLE requests ADD COLUMN requester_name VARCHAR(255) AFTER shelter_id",
    'item_needed' => "ALTER TABLE requests ADD COLUMN item_needed VARCHAR(255) AFTER requester_name",
    'quantity' => "ALTER TABLE requests ADD COLUMN quantity INT AFTER item_needed",
    'unit' => "ALTER TABLE requests ADD COLUMN unit VARCHAR(50) AFTER quantity"
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
    }
}

echo "<hr>";
if ($fixed) {
    echo "<h3>ปรับปรุงฐานข้อมูลเสร็จสมบูรณ์!</h3>";
    echo "<p>กรุณากลับไปที่หน้า Dashboard เพื่อตรวจสอบการทำงาน</p>";
} else {
    echo "<h3>ฐานข้อมูลปกติ (มีคอลัมน์ครบถ้วนแล้ว)</h3>";
}

echo "<a href='index.php' class='btn btn-primary'>กลับไปหน้า Dashboard</a>";
?>