<?php
// report_export.php
require_once 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

$incident_id = isset($_GET['incident_id']) ? $_GET['incident_id'] : '';

if (!$incident_id) {
    die("กรุณาระบุเหตุการณ์ที่ต้องการส่งออกข้อมูล");
}

// 1. ดึงข้อมูลเหตุการณ์เพื่อตั้งชื่อไฟล์
$stmt = $pdo->prepare("SELECT name FROM incidents WHERE id = ?");
$stmt->execute([$incident_id]);
$incident_name = $stmt->fetchColumn();
$filename = "Export_" . str_replace(' ', '_', $incident_name) . "_" . date('Y-m-d') . ".csv";

// 2. ตั้งค่า Header ดาวน์โหลด
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 3. สร้าง Output Stream
$output = fopen('php://output', 'w');

// 4. เขียน BOM เพื่อให้ Excel รองรับภาษาไทย (UTF-8)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 5. เขียนหัวคอลัมน์
fputcsv($output, [
    'ลำดับ', 
    'ศูนย์พักพิง', 
    'เลขบัตรประชาชน', 
    'คำนำหน้า', 
    'ชื่อ', 
    'นามสกุล', 
    'เพศ', 
    'อายุ', 
    'เบอร์โทร', 
    'ปัญหาสุขภาพ', 
    'วันที่เข้าพัก', 
    'สถานะ'
]);

// 6. ดึงข้อมูลผู้ประสบภัยทั้งหมดในเหตุการณ์นี้
$sql = "SELECT e.*, s.name as shelter_name 
        FROM evacuees e 
        LEFT JOIN shelters s ON e.shelter_id = s.id
        WHERE e.incident_id = ? 
        ORDER BY s.name, e.first_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$incident_id]);

$i = 1;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // จัดการข้อมูลก่อนเขียน
    $status = $row['check_out_date'] ? 'ออกแล้ว (' . date('d/m/Y', strtotime($row['check_out_date'])) . ')' : 'พักอยู่';
    $gender = ($row['gender'] == 'male') ? 'ชาย' : (($row['gender'] == 'female') ? 'หญิง' : '-');
    $check_in = date('d/m/Y', strtotime($row['created_at'])); // หรือ check_in_date ถ้ามี

    // ใส่ ' หน้าตัวเลขยาวๆ เพื่อให้ Excel ไม่มองเป็น Scientific Notation
    $id_card = "'" . $row['id_card'];
    $phone = "'" . $row['phone'];

    fputcsv($output, [
        $i++,
        $row['shelter_name'],
        $id_card,
        $row['prefix'],
        $row['first_name'],
        $row['last_name'],
        $gender,
        $row['age'],
        $phone,
        $row['health_condition'],
        $check_in,
        $status
    ]);
}

fclose($output);
exit();
?>