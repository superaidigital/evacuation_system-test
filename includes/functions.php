<?php
// includes/functions.php

function logActivity($pdo, $user_id, $action, $description) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    } catch (PDOException $e) {
        // กรณีบันทึก Log ไม่สำเร็จ ให้ปล่อยผ่าน (Silent Fail) เพื่อไม่ให้กระทบการทำงานหลัก
        // หรือบันทึกลง Error Log ของ Server แทน
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย (แถมให้ เพื่อใช้ในรายงาน)
function thaiDate($date) {
    if(!$date) return '-';
    $months = [
        1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.',
        7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'
    ];
    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp) + 543;
    return "$d $m $y";
}
?>