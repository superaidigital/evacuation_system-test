<?php
// download_errors.php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['import_error_data'])) {
    die("ไม่พบข้อมูลรายการผิดพลาด หรือ Session หมดอายุ");
}

$errors = $_SESSION['import_error_data'];
$filename = "import_errors_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM

// Header (เพิ่มคอลัมน์ "สาเหตุที่ผิดพลาด" ต่อท้าย)
$headers = [
    'เลขบัตรประชาชน', 'คำนำหน้า', 'ชื่อจริง', 'นามสกุล', 'เพศ', 'อายุ', 'เบอร์โทรศัพท์', 
    'บ้านเลขที่', 'หมู่ที่', 'ตำบล', 'อำเภอ', 'จังหวัด', 'ปัญหาสุขภาพ', 
    'สาเหตุที่นำเข้าไม่ได้ (Error Reason)'
];
fputcsv($output, $headers);

// Data
foreach ($errors as $row) {
    // ป้องกันการแสดงผลเลขบัตรเป็น Scientific Notation
    if(isset($row[0])) $row[0] = "'" . $row[0];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>