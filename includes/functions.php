<?php
// includes/functions.php

/**
 * บันทึก Log การใช้งานระบบ
 */
function logActivity($pdo, $user_id, $action, $description) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * แปลงวันที่เป็นภาษาไทย
 */
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

/**
 * [SECURITY] สร้าง CSRF Token
 */
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * [SECURITY] ตรวจสอบ CSRF Token
 */
function validateCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die("Security Warning: CSRF Token Validation Failed. กรุณารีเฟรชหน้าจอแล้วลองใหม่อีกครั้ง");
    }
}

/**
 * [SECURITY] Clean Input ป้องกัน XSS
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * [VALIDATION] ตรวจสอบเลขบัตรประชาชน 13 หลัก
 */
function validateThaiID($id) {
    if(strlen($id) != 13 || !ctype_digit($id)) return false;
    $digits = str_split($id);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += $digits[$i] * (13 - $i);
    }
    $checkDigit = (11 - ($sum % 11)) % 10;
    return $checkDigit == $digits[12];
}
?>