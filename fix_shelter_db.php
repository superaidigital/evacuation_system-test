<?php
// fix_shelter_db.php
// สคริปต์สำหรับเพิ่มคอลัมน์ที่ขาดหายไปในตาราง shelters

include('config/db.php');

echo "<h2>กำลังตรวจสอบและแก้ไขโครงสร้างฐานข้อมูล...</h2>";

// รายการคอลัมน์ที่ต้องการเพิ่ม
$columns_to_add = [
    'contact_person' => "ADD COLUMN contact_person VARCHAR(255) AFTER capacity",
    'contact_phone' => "ADD COLUMN contact_phone VARCHAR(50) AFTER contact_person", // กรณีที่ยังไม่มี
    'district' => "ADD COLUMN district VARCHAR(100) AFTER location",
    'province' => "ADD COLUMN province VARCHAR(100) AFTER district",
    'latitude' => "ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER location",
    'longitude' => "ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude",
    'incident_id' => "ADD COLUMN incident_id INT NULL AFTER id",
    'status' => "ADD COLUMN status ENUM('Open', 'Full', 'Closed') DEFAULT 'Open' AFTER capacity" // กรณีที่ยังไม่มีหรือ type ผิด
];

// ดึงรายชื่อคอลัมน์ที่มีอยู่ปัจจุบัน
$existing_columns = [];
$result = $conn->query("SHOW COLUMNS FROM shelters");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
} else {
    die("<div style='color:red'>ไม่พบตาราง shelters หรือเกิดข้อผิดพลาด: " . $conn->error . "</div>");
}

$success_count = 0;

foreach ($columns_to_add as $col_name => $sql_command) {
    if (!in_array($col_name, $existing_columns)) {
        echo "กำลังเพิ่มคอลัมน์ <b>$col_name</b>... ";
        if ($conn->query("ALTER TABLE shelters $sql_command")) {
            echo "<span style='color:green'>สำเร็จ</span><br>";
            $success_count++;
        } else {
            // กรณี contact_phone อาจจะมีอยู่แล้วแต่ error เพราะเช็คไม่เจอ หรือ error อื่นๆ
            echo "<span style='color:red'>ผิดพลาด: " . $conn->error . "</span><br>";
        }
    } else {
        echo "คอลัมน์ <b>$col_name</b> มีอยู่แล้ว <span style='color:gray'>(ข้าม)</span><br>";
    }
}

echo "<hr>";
if ($success_count > 0) {
    echo "<h3>ปรับปรุงฐานข้อมูลเรียบร้อยแล้ว! ($success_count คอลัมน์)</h3>";
    echo "<p>ตอนนี้คุณสามารถกลับไปบันทึกข้อมูลได้แล้วครับ</p>";
} else {
    echo "<h3>ฐานข้อมูลเป็นเวอร์ชันล่าสุดแล้ว ไม่มีการเปลี่ยนแปลง</h3>";
}

echo '<a href="shelter_list.php" style="background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;">กลับหน้ารายการ</a>';
?>