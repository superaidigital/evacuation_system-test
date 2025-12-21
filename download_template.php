<?php
// download_template.php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$filename = "evacuee_import_template.csv";

// ตั้งค่า Header สำหรับดาวน์โหลดไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// เปิด Output Stream
$output = fopen('php://output', 'w');

// เขียน BOM เพื่อรองรับภาษาไทยใน Excel (UTF-8)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// เขียนหัวตาราง (Header Row) - ลำดับต้องตรงกับ import_process.php
$headers = [
    'เลขบัตรประชาชน (13หลัก)', // [0]
    'คำนำหน้า (นาย/นาง/นางสาว)', // [1]
    'ชื่อจริง',                 // [2]
    'นามสกุล',                // [3]
    'เพศ (ชาย/หญิง)',          // [4]
    'อายุ',                   // [5]
    'เบอร์โทรศัพท์',            // [6]
    'บ้านเลขที่',               // [7]
    'หมู่ที่',                  // [8]
    'ตำบล',                   // [9]
    'อำเภอ',                  // [10]
    'จังหวัด',                 // [11]
    'ปัญหาสุขภาพ/โรคประจำตัว'    // [12]
];
fputcsv($output, $headers);

// เขียนข้อมูลตัวอย่าง (Example Row 1)
$example_row1 = [
    '1234567890123',
    'นาย',
    'สมชาย',
    'ใจดี',
    'ชาย',
    '45',
    '0812345678',
    '99/1',
    '1',
    'แม่สาย',
    'แม่สาย',
    'เชียงราย',
    '-'
];
fputcsv($output, $example_row1);

// เขียนข้อมูลตัวอย่าง (Example Row 2)
$example_row2 = [
    '9876543210987',
    'นาง',
    'สมหญิง',
    'รักสงบ',
    'หญิง',
    '62',
    '0898765432',
    '102',
    '5',
    'เวียง',
    'เมือง',
    'เชียงราย',
    'ความดันโลหิตสูง'
];
fputcsv($output, $example_row2);

fclose($output);
exit();
?>