<?php
// shelter_save.php
// Refactored: Support Latitude & Longitude Saving (GIS)
// จัดการการบันทึกข้อมูลศูนย์พักพิง รวมถึงพิกัดแผนที่

require_once 'config/db.php';
require_once 'includes/functions.php';

// 1. Authentication Check
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Method Not Allowed");
}

// 2. CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (function_exists('validateCSRFToken')) {
    validateCSRFToken($csrf_token);
}

// Helper: รับค่าและ Trim
function getTrimmed($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// 3. รับค่าจากฟอร์ม
$mode           = getTrimmed('mode', 'add');
$id             = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$incident_id    = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);

// ข้อมูลทั่วไป
$name           = getTrimmed('name');
$location       = getTrimmed('location');
$capacity       = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
$contact_phone  = getTrimmed('contact_phone');
$status         = getTrimmed('status', 'open');

// ข้อมูลพิกัด (GIS) - ใช้ filter เพื่อตรวจสอบว่าเป็นตัวเลขทศนิยมจริง
$latitude       = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude      = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

// แปลง false เป็น null (กรณีค่าไม่ถูกต้องหรือว่างเปล่า) เพื่อให้ Database เก็บเป็น NULL
if ($latitude === false) $latitude = null;
if ($longitude === false) $longitude = null;

// 4. Validation
$errors = [];
if ($mode == 'add' && !$incident_id) $errors[] = "กรุณาเลือกภารกิจภัยพิบัติ";
if (empty($name)) $errors[] = "กรุณาระบุชื่อศูนย์พักพิง";
if (empty($location)) $errors[] = "กรุณาระบุรายละเอียดที่ตั้ง";
if (!$capacity || $capacity <= 0) $errors[] = "กรุณาระบุความจุที่ถูกต้อง (ตัวเลขมากกว่า 0)";

// หากมี Error
if (!empty($errors)) {
    $_SESSION['swal_error'] = implode("<br>", $errors);
    header("Location: shelter_form.php?mode=$mode&id=$id");
    exit();
}

try {
    // 5. Database Operation
    if ($mode == 'add') {
        // เพิ่มข้อมูลใหม่
        $sql = "INSERT INTO shelters 
                (incident_id, name, location, latitude, longitude, capacity, contact_phone, status, last_updated) 
                VALUES 
                (:incident_id, :name, :location, :lat, :lng, :capacity, :contact_phone, :status, NOW())";
        $logAction = "Add Shelter";
    } else {
        // แก้ไขข้อมูลเดิม
        $sql = "UPDATE shelters SET 
                name = :name, 
                location = :location,
                latitude = :lat,
                longitude = :lng,
                capacity = :capacity, 
                contact_phone = :contact_phone,
                status = :status,
                last_updated = NOW()
                WHERE id = :id";
        $logAction = "Edit Shelter";
    }

    $stmt = $pdo->prepare($sql);
    
    // Bind Params
    $params = [
        ':name'          => $name,
        ':location'      => $location,
        ':lat'           => $latitude,
        ':lng'           => $longitude,
        ':capacity'      => $capacity,
        ':contact_phone' => $contact_phone,
        ':status'        => $status
    ];

    if ($mode == 'add') {
        $params[':incident_id'] = $incident_id;
    } else {
        $params[':id'] = $id;
    }

    $stmt->execute($params);

    // 6. Audit Log
    if (function_exists('logActivity')) {
        $coordLog = ($latitude && $longitude) ? " (Lat: $latitude, Lng: $longitude)" : "";
        logActivity($pdo, $_SESSION['user_id'], $logAction, "ชื่อศูนย์: $name" . $coordLog);
    }

    $_SESSION['swal_success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
    header("Location: shelter_list.php");
    exit();

} catch (PDOException $e) {
    error_log("Shelter Save Error: " . $e->getMessage());
    $_SESSION['swal_error'] = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
    header("Location: shelter_form.php?mode=$mode&id=$id");
    exit();
}
?>