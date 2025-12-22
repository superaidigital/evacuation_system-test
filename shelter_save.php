<?php
// shelter_save.php
// สคริปต์บันทึกข้อมูลศูนย์พักพิง
// แก้ไข: ตัด 'code' ออก และแก้ Warning: Undefined array key สำหรับฟิลด์ที่อาจไม่มีค่าส่งมา

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

// 3. รับค่าและ Clean Input
// ใช้ ?? '' (Null Coalescing) เพื่อป้องกัน Warning หากฟอร์มไม่ได้ส่งค่ามา

$mode = cleanInput($_POST['mode'] ?? 'add');
$id   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// ข้อมูลหลัก
$name     = cleanInput($_POST['name'] ?? '');
$capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
$status   = cleanInput($_POST['status'] ?? 'open');

// ข้อมูลที่อยู่ (ใช้ ?? '' เพื่อแก้ปัญหา Warning: Undefined array key "subdistrict" ฯลฯ)
$address     = cleanInput($_POST['address'] ?? '');
$subdistrict = cleanInput($_POST['subdistrict'] ?? ''); 
$district    = cleanInput($_POST['district'] ?? '');    
$province    = cleanInput($_POST['province'] ?? '');    
$latitude    = $_POST['latitude'] ?? null;
$longitude   = $_POST['longitude'] ?? null;

// ข้อมูลติดต่อ
$contact_person = cleanInput($_POST['contact_person'] ?? '');
$phone          = cleanInput($_POST['phone'] ?? '');

// Validation
if (empty($name) || empty($capacity)) {
    echo "<script>alert('กรุณากรอกชื่อศูนย์และความจุ'); window.history.back();</script>";
    exit();
}

try {
    // ตัดฟิลด์ 'code' ออกจาก SQL โดยสิ้นเชิง เพราะฐานข้อมูลไม่มีคอลัมน์นี้
    if ($mode == 'add') {
        $sql = "INSERT INTO shelters 
                (name, address, subdistrict, district, province, latitude, longitude, capacity, status, contact_person, phone, created_at) 
                VALUES 
                (:name, :address, :subdistrict, :district, :province, :latitude, :longitude, :capacity, :status, :contact_person, :phone, NOW())";
        $logAction = "Add Shelter";
    } else {
        $sql = "UPDATE shelters SET 
                name = :name, 
                address = :address,
                subdistrict = :subdistrict,
                district = :district,
                province = :province,
                latitude = :latitude,
                longitude = :longitude,
                capacity = :capacity, 
                status = :status,
                contact_person = :contact_person,
                phone = :phone,
                updated_at = NOW()
                WHERE id = :id";
        $logAction = "Edit Shelter";
    }

    $stmt = $pdo->prepare($sql);
    
    // Bind Params
    $params = [
        'name'           => $name,
        'address'        => $address,
        'subdistrict'    => $subdistrict,
        'district'       => $district,
        'province'       => $province,
        'latitude'       => !empty($latitude) ? $latitude : null,
        'longitude'      => !empty($longitude) ? $longitude : null,
        'capacity'       => $capacity,
        'status'         => $status,
        'contact_person' => $contact_person,
        'phone'          => $phone
    ];

    if ($mode == 'edit') {
        $params['id'] = $id;
    }

    $stmt->execute($params);

    // บันทึก Log
    if (function_exists('logActivity')) {
        logActivity($pdo, $_SESSION['user_id'], $logAction, "ชื่อศูนย์: $name");
    }

    $_SESSION['swal_success'] = "บันทึกข้อมูลศูนย์พักพิงเรียบร้อยแล้ว";
    header("Location: shelter_list.php");
    exit();

} catch (PDOException $e) {
    // ดักจับ Error กรณี Column ไม่ครบ เพื่อแจ้งเตือนที่ชัดเจน
    if ($e->getCode() == '42S22') {
         // หากยังเจอ Error นี้ แสดงว่าอาจจะไม่มีคอลัมน์ subdistrict, district หรือ province ใน DB
         $error_msg = "Database Error: ชื่อคอลัมน์ไม่ถูกต้อง (ตรวจสอบว่าในฐานข้อมูลมีคอลัมน์ subdistrict, district, province หรือไม่)";
    } else {
         $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
    
    error_log($e->getMessage());
    $_SESSION['swal_error'] = $error_msg;
    
    // ส่งกลับไปหน้าฟอร์มพร้อม Error
    header("Location: shelter_form.php?mode=$mode&id=$id");
    exit();
}
?>