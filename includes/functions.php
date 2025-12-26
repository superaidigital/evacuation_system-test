<?php
/**
 * includes/functions.php
 * รวมฟังก์ชันใช้งานทั่วไป (Helper Functions) สำหรับระบบบริหารจัดการศูนย์พักพิง
 * ครอบคลุมด้าน Security, Privacy, Validation และ UI Components
 */

/**
 * [SECURITY] Clean Input: สำหรับเตรียมข้อมูลก่อนนำไปประมวลผล (ป้องกัน Null Byte และตัดช่องว่าง)
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return trim(str_replace(chr(0), '', $data));
}

/**
 * [SECURITY] Escape Output: สำหรับแสดงผลหน้าเว็บเพื่อป้องกัน XSS (Cross-Site Scripting)
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * [PRIVACY] Mask ID Card: ปิดบังเลขบัตรประชาชนเพื่อความเป็นส่วนตัว
 * ตัวอย่าง: 1100101xxxxx1
 */
function maskIDCard($id_card) {
    if (strlen($id_card) < 13) return $id_card; 
    return substr($id_card, 0, 7) . 'xxxxx-' . substr($id_card, 11, 2);
}

/**
 * [SECURITY] CSRF Protection: สร้างและตรวจสอบ Token เพื่อป้องกันการโจมตีข้ามไซต์
 */
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        error_log("CSRF Token Mismatch IP: " . $_SERVER['REMOTE_ADDR']);
        die("Security Warning: CSRF Token Validation Failed.");
    }
}

/**
 * [LOGGING] บันทึกกิจกรรมการใช้งาน (MySQLi)
 */
function logActivity($conn, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'];
    // ตรวจสอบว่าตาราง system_logs มีอยู่จริงหรือไม่ก่อนบันทึก
    $check = $conn->query("SHOW TABLES LIKE 'system_logs'");
    if($check && $check->num_rows > 0) {
        $sql = "INSERT INTO system_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isss", $user_id, $action, $description, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * [VALIDATION] ตรวจสอบความถูกต้องของเลขบัตรประชาชนไทย
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

/**
 * [UI] คำนวณอายุจากวันเกิด
 */
function calculateAge($birthDate) {
    if (empty($birthDate) || $birthDate == '0000-00-00') {
        return 0;
    }
    try {
        $today = new DateTime('today');
        $birth = new DateTime($birthDate);
        return $birth->diff($today)->y;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * [UI] สร้างแถบนำทางเลขหน้า (Pagination)
 */
function renderPagination($currentPage, $totalPages, $queryParams = []) {
    if ($totalPages <= 1) return '';
    $queryString = '';
    foreach ($queryParams as $key => $value) {
        if ($key !== 'page' && $value !== '') {
            $queryString .= '&' . urlencode($key) . '=' . urlencode($value);
        }
    }
    $baseUrl = $_SERVER['PHP_SELF'];
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-4">';
    
    // ปุ่มก่อนหน้า
    $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
    $prevPage = max(1, $currentPage - 1);
    $html .= '<li class="page-item ' . $prevDisabled . '"><a class="page-link shadow-sm" href="' . $baseUrl . '?page=' . $prevPage . $queryString . '">ก่อนหน้า</a></li>';
    
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if($start > 1) {
        $html .= '<li class="page-item"><a class="page-link shadow-sm" href="' . $baseUrl . '?page=1' . $queryString . '">1</a></li>';
        if($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link shadow-sm" href="' . $baseUrl . '?page=' . $i . $queryString . '">' . $i . '</a></li>';
    }

    if($end < $totalPages) {
        if($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link shadow-sm" href="' . $baseUrl . '?page=' . $totalPages . $queryString . '">' . $totalPages . '</a></li>';
    }

    // ปุ่มถัดไป
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $currentPage + 1);
    $html .= '<li class="page-item ' . $nextDisabled . '"><a class="page-link shadow-sm" href="' . $baseUrl . '?page=' . $nextPage . $queryString . '">ถัดไป</a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}

// --- Date Functions ---

/**
 * แปลงวันที่เป็นรูปแบบไทยย่อ (เช่น 26 ธ.ค. 68)
 */
function thai_date($strDate, $showTime = false) {
    if (!$strDate || $strDate == '0000-00-00' || $strDate == '0000-00-00 00:00:00') return '-';
    if (!is_numeric($strDate)) { $strDate = strtotime($strDate); }
    
    $strYear = date("Y", $strDate) + 543;
    $strMonth = date("n", $strDate);
    $strDay = date("j", $strDate);
    $strHour = date("H", $strDate);
    $strMinute = date("i", $strDate);
    
    $strMonthCut = Array("", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค.");
    $strMonthThai = $strMonthCut[$strMonth];
    $strYearShort = substr($strYear, 2, 2);

    $output = "$strDay $strMonthThai $strYearShort";
    if ($showTime) { $output .= " $strHour:$strMinute น."; }
    return $output;
}

/**
 * แปลงวันที่เป็นรูปแบบไทยเต็ม (เช่น 26 ธันวาคม 2568)
 */
function thai_date_full($strDate, $showTime = false) {
    if (!$strDate || $strDate == '0000-00-00') return '-';
    if (!is_numeric($strDate)) { $strDate = strtotime($strDate); }
    
    $strYear = date("Y", $strDate) + 543;
    $strMonth = date("n", $strDate);
    $strDay = date("j", $strDate);
    $strHour = date("H", $strDate);
    $strMinute = date("i", $strDate);
    
    $strMonthCut = Array("", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม");
    $strMonthThai = $strMonthCut[$strMonth];
    
    $output = "$strDay $strMonthThai $strYear";
    if ($showTime) { $output .= " เวลา $strHour:$strMinute น."; }
    return $output;
}

/**
 * ฟังก์ชัน Wrapper สำหรับหน้า request_manager.php
 */
function thaiDate($date) {
    return thai_date($date, false);
}