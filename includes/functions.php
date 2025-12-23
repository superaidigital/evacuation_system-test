<?php
// includes/functions.php
// Refactored: แยกการจัดการ Input และ Output ออกจากกันอย่างชัดเจน

/**
 * [SECURITY] Clean Input: สำหรับเตรียมข้อมูลก่อนนำไปประมวลผลหรือลง DB
 * หน้าที่: Trim ช่องว่าง, ลบ Null byte (ไม่แปลง HTML Entities ที่นี่!)
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    // ลบ Null bytes และ Trim ช่องว่างหัวท้าย
    return trim(str_replace(chr(0), '', $data));
}

/**
 * [SECURITY] Escape Output: สำหรับแสดงผลหน้าเว็บป้องกัน XSS
 * ใช้แทน htmlspecialchars(...) ที่ยาวเหยียด
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * [PRIVACY] Mask ID Card: ปิดบังเลขบัตรประชาชน แสดงเฉพาะ 4 ตัวท้าย
 * ตัวอย่าง: 1-2345-xxxxx-89-0
 */
function maskIDCard($id_card) {
    if (strlen($id_card) < 13) return $id_card; // ถ้าไม่ครบ 13 หลัก ให้แสดงปกติ (หรือแสดง -)
    return substr($id_card, 0, 7) . 'xxxxx-' . substr($id_card, 11, 2);
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
        // Log error and stop execution
        error_log("CSRF Token Mismatch from IP: " . $_SERVER['REMOTE_ADDR']);
        die("Security Warning: CSRF Token Validation Failed. กรุณารีเฟรชหน้าจอแล้วลองใหม่อีกครั้ง");
    }
}

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
    if(!$date || $date == '0000-00-00') return '-';
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

/**
 * [UI] Render Pagination
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
    
    // Previous Button
    $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
    $prevPage = max(1, $currentPage - 1);
    $html .= '<li class="page-item ' . $prevDisabled . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $prevPage . $queryString . '"><i class="fas fa-chevron-left"></i> ก่อนหน้า</a></li>';
    
    // Page Numbers Logic
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1' . $queryString . '">1</a></li>';
        if($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . $queryString . '">' . $i . '</a></li>';
    }

    if($end < $totalPages) {
        if($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . $queryString . '">' . $totalPages . '</a></li>';
    }

    // Next Button
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $currentPage + 1);
    $html .= '<li class="page-item ' . $nextDisabled . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $nextPage . $queryString . '">ถัดไป <i class="fas fa-chevron-right"></i></a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}
?>