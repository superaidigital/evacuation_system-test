<?php
// fix_shelter_columns.php
// สคริปต์สำหรับเพิ่มคอลัมน์ created_at และ last_updated ที่ขาดหายไป

include('config/db.php');

echo "<h2>กำลังตรวจสอบและซ่อมแซมตาราง shelters...</h2>";

// รายการคอลัมน์ที่ต้องการเพิ่ม
$columns = [
    'created_at' => "ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
    'last_updated' => "ADD COLUMN last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

// ดึงคอลัมน์ที่มีอยู่ปัจจุบัน
$existing = [];
$result = $conn->query("SHOW COLUMNS FROM shelters");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existing[] = $row['Field'];
    }
} else {
    die("<div style='color:red'>ไม่พบตาราง shelters</div>");
}

$updated = false;
foreach ($columns as $col => $sql) {
    if (!in_array($col, $existing)) {
        echo "กำลังเพิ่มคอลัมน์ <b>$col</b>... ";
        if ($conn->query("ALTER TABLE shelters $sql")) {
            echo "<span style='color:green'>สำเร็จ</span><br>";
            $updated = true;
        } else {
            echo "<span style='color:red'>ล้มเหลว: " . $conn->error . "</span><br>";
        }
    } else {
        echo "คอลัมน์ <b>$col</b> มีอยู่แล้ว <span style='color:gray'>(ข้าม)</span><br>";
    }
}

echo "<hr>";
if ($updated) {
    echo "<h3>แก้ไขเรียบร้อยแล้ว!</h3>";
    echo "<p>กรุณาลองบันทึกข้อมูลใหม่อีกครั้ง</p>";
} else {
    echo "<h3>ฐานข้อมูลสมบูรณ์แล้ว ไม่มีการเปลี่ยนแปลง</h3>";
}

echo '<a href="shelter_list.php" class="btn">กลับหน้ารายการ</a>';
?>