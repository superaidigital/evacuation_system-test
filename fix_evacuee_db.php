<?php
// fix_evacuee_db.php
// สคริปต์ตรวจสอบและซ่อมแซมตาราง evacuees

include('config/db.php');

echo "<h2>กำลังตรวจสอบตาราง evacuees...</h2>";

// 1. ตรวจสอบว่ามีตารางหรือไม่
$check_table = $conn->query("SHOW TABLES LIKE 'evacuees'");
if ($check_table->num_rows == 0) {
    // ถ้าไม่มีตาราง ให้สร้างใหม่
    $sql_create = "CREATE TABLE evacuees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shelter_id INT NOT NULL,
        id_card VARCHAR(13),
        prefix VARCHAR(20),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        age INT,
        gender ENUM('Male', 'Female', 'Other'),
        phone VARCHAR(20),
        address TEXT,
        health_status VARCHAR(100) DEFAULT 'Normal',
        needs TEXT,
        status ENUM('Registered', 'CheckedOut') DEFAULT 'Registered',
        check_in_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        check_out_date DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql_create)) {
        echo "<span style='color:green'>สร้างตาราง 'evacuees' สำเร็จ</span><br>";
    } else {
        die("<span style='color:red'>สร้างตารางผิดพลาด: " . $conn->error . "</span>");
    }
} else {
    echo "พบตาราง 'evacuees' แล้ว<br>";
}

// 2. ดึงรายชื่อคอลัมน์ที่มีอยู่
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM evacuees");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

// 3. รายการคอลัมน์ที่ต้องมี (เพิ่ม health_status และอื่นๆ ที่สำคัญ)
$required_cols = [
    'address' => "ALTER TABLE evacuees ADD COLUMN address TEXT AFTER phone", // Add address first if missing
    'health_status' => "ALTER TABLE evacuees ADD COLUMN health_status VARCHAR(100) DEFAULT 'Normal' AFTER address",
    'needs' => "ALTER TABLE evacuees ADD COLUMN needs TEXT AFTER health_status",
    'check_in_date' => "ALTER TABLE evacuees ADD COLUMN check_in_date DATETIME DEFAULT CURRENT_TIMESTAMP",
    'check_out_date' => "ALTER TABLE evacuees ADD COLUMN check_out_date DATETIME NULL"
];

$fixed = false;
foreach ($required_cols as $col => $sql) {
    if (!in_array($col, $columns)) {
        echo "ไม่พบคอลัมน์ <b>$col</b>... กำลังเพิ่ม... ";
        if ($conn->query($sql)) {
            echo "<span style='color:green'>สำเร็จ</span><br>";
            $fixed = true;
            // Update columns array to include the newly added column so subsequent checks don't fail (e.g. AFTER address)
            $columns[] = $col; 
        } else {
            echo "<span style='color:red'>ผิดพลาด: " . $conn->error . "</span><br>";
        }
    }
}

echo "<hr>";
if ($fixed) {
    echo "<h3>ปรับปรุงฐานข้อมูลเสร็จสมบูรณ์!</h3>";
    echo "<p>กรุณาลองเข้าหน้า Dashboard ใหม่อีกครั้ง</p>";
} else {
    echo "<h3>ฐานข้อมูลปกติ ไม่มีการเปลี่ยนแปลง</h3>";
}

echo "<a href='shelter_list.php' class='btn btn-primary'>กลับไปหน้ารายการศูนย์พักพิง</a>";
?>