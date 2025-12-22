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

/**
 * [UI] สร้าง Pagination Links แบบ Smart Sliding (Bootstrap 5)
 * รูปแบบ: 1 2 3 4 5 ... 20
 */
function renderPagination($currentPage, $totalPages, $queryParams = []) {
    if ($totalPages <= 1) return '';

    // สร้าง Query String
    $queryString = '';
    foreach ($queryParams as $key => $value) {
        if ($key !== 'page' && $value !== '') {
            $queryString .= '&' . urlencode($key) . '=' . urlencode($value);
        }
    }
    $baseUrl = $_SERVER['PHP_SELF'];
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mt-4">';
    
    // ปุ่ม "ก่อนหน้า"
    $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
    $prevPage = max(1, $currentPage - 1);
    $html .= '<li class="page-item ' . $prevDisabled . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $prevPage . $queryString . '"><i class="fas fa-chevron-left"></i> ก่อนหน้า</a></li>';
    
    // Logic การแสดงเลขหน้า
    if ($totalPages <= 7) {
        // กรณีหน้าน้อย (<= 7) แสดงทั้งหมด: [1] [2] [3] [4] [5] [6] [7]
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . $queryString . '">' . $i . '</a></li>';
        }
    } else {
        // กรณีหน้าเยอะ (> 7) ใช้สูตร Sliding Window
        
        // 1. หน้าแรกสุดเสมอ
        $active = ($currentPage == 1) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=1' . $queryString . '">1</a></li>';

        // คำนวณช่วงที่จะแสดง (Window)
        $start = max(2, $currentPage - 1);
        $end = min($totalPages - 1, $currentPage + 1);

        if ($currentPage <= 4) {
            // ช่วงต้น: 1 [2] [3] [4] [5] ... Last
            $start = 2;
            $end = 5;
        } elseif ($currentPage >= $totalPages - 3) {
            // ช่วงท้าย: 1 ... [96] [97] [98] [99] Last
            $start = $totalPages - 4;
            $end = $totalPages - 1;
        }

        // Ellipsis ตัวหน้า (...)
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        // Loop แสดงช่วงกลาง
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . $queryString . '">' . $i . '</a></li>';
        }

        // Ellipsis ตัวหลัง (...)
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        // หน้าสุดท้ายเสมอ
        $active = ($currentPage == $totalPages) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . $queryString . '">' . $totalPages . '</a></li>';
    }

    // ปุ่ม "ถัดไป"
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPage = min($totalPages, $currentPage + 1);
    $html .= '<li class="page-item ' . $nextDisabled . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $nextPage . $queryString . '">ถัดไป <i class="fas fa-chevron-right"></i></a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}
?>