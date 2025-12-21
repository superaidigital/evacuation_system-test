<?php
// download_shelter_template.php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$filename = "shelter_import_template.csv";

// Header
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for Excel Thai support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column Headers
fputcsv($output, ['ชื่อศูนย์พักพิง', 'ที่ตั้ง/ตำบล/อำเภอ', 'ความจุ (คน)', 'เบอร์โทรศัพท์ติดต่อ']);

// Example Data (เพื่อให้เห็นภาพ)
fputcsv($output, ['โรงเรียนเทศบาล 1', 'ต.เวียง อ.เมือง จ.เชียงราย', '500', '053-123456']);
fputcsv($output, ['วัดพระธาตุ', 'ต.แม่สาย อ.แม่สาย จ.เชียงราย', '200', '081-9876543']);

fclose($output);
exit();
?>