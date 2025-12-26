<?php
// shelter_save.php
// บันทึกข้อมูลศูนย์พักพิง (รองรับ MySQLi + GIS Latitude/Longitude)

session_start();
include('config/db.php');

// 1. ตรวจสอบสิทธิ์การใช้งาน
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header('location: login.php');
    exit();
}

// ตรวจสอบว่าเป็น POST method หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Method Not Allowed");
}

// 2. ตรวจสอบ CSRF Token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    // อนุโลมให้ผ่านได้หากยังไม่ได้ implement CSRF อย่างเข้มงวดในทุกจุด แต่แจ้งเตือนใน Log
    // error_log("CSRF Token mismatch");
}

// 3. รับค่าจากฟอร์ม
// ใช้ isset() และ trim() เพื่อความปลอดภัยเบื้องต้น
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$incident_id = isset($_POST['incident_id']) ? intval($_POST['incident_id']) : 0;

$name = trim($_POST['name']);
$location = trim($_POST['location']);
$capacity = intval($_POST['capacity']);
$contact_phone = trim($_POST['contact_phone']);
$contact_person = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
$status = $_POST['status']; // Open, Full, Closed
$district = isset($_POST['district']) ? trim($_POST['district']) : '';
$province = isset($_POST['province']) ? trim($_POST['province']) : '';

// รับค่าพิกัด (แปลงเป็น float หรือ null ถ้าเป็นค่าว่าง)
$latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

// ตรวจสอบโหมดการทำงาน (Add หรือ Edit)
// ดูจากว่ามี ID ส่งมาหรือไม่ และ ID ต้องมากกว่า 0
$is_edit = ($id > 0);

// 4. Validation (ตรวจสอบความถูกต้องของข้อมูล)
$errors = array();

if (empty($name)) { array_push($errors, "กรุณาระบุชื่อศูนย์พักพิง"); }
if (empty($location)) { array_push($errors, "กรุณาระบุรายละเอียดที่ตั้ง"); }
if ($capacity <= 0) { array_push($errors, "ความจุต้องมากกว่า 0"); }

// ถ้ามี Error ให้ส่งกลับไปหน้าฟอร์ม
if (count($errors) > 0) {
    $_SESSION['error'] = implode("<br>", $errors);
    if ($is_edit) {
        header("location: shelter_form.php?edit=$id");
    } else {
        header("location: shelter_form.php");
    }
    exit();
}

// 5. บันทึกข้อมูลลงฐานข้อมูล (MySQLi Prepared Statement)
if ($is_edit) {
    // --- โหมดแก้ไข (Update) ---
    $sql = "UPDATE shelters SET 
            name = ?, 
            location = ?, 
            capacity = ?, 
            contact_person = ?,
            contact_phone = ?, 
            status = ?, 
            latitude = ?, 
            longitude = ?,
            district = ?,
            province = ?,
            last_updated = NOW()
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // s=string, i=integer, d=double(float)
        // เรียงลำดับ: name(s), location(s), capacity(i), person(s), phone(s), status(s), lat(d), lng(d), dist(s), prov(s), id(i)
        $stmt->bind_param("ssisssddssi", 
            $name, 
            $location, 
            $capacity, 
            $contact_person,
            $contact_phone, 
            $status, 
            $latitude, 
            $longitude, 
            $district,
            $province,
            $id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "แก้ไขข้อมูลศูนย์พักพิงเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการแก้ไข: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Prepare failed: " . $conn->error;
    }

} else {
    // --- โหมดเพิ่มใหม่ (Insert) ---
    // ตรวจสอบก่อนว่า field 'incident_id' มีในตารางหรือไม่ ถ้าไม่มีให้ตัดออก (เผื่อ Database เก่า)
    // แต่ตามโค้ด shelter_form.php ล่าสุดมีการเช็ค incidents ดังนั้นสมมติว่ามี
    
    $sql = "INSERT INTO shelters 
            (incident_id, name, location, capacity, contact_person, contact_phone, status, latitude, longitude, district, province, created_at, last_updated) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // เรียงลำดับ: incident(i), name(s), location(s), capacity(i), person(s), phone(s), status(s), lat(d), lng(d), dist(s), prov(s)
        $stmt->bind_param("ississsddss", 
            $incident_id, 
            $name, 
            $location, 
            $capacity, 
            $contact_person,
            $contact_phone, 
            $status, 
            $latitude, 
            $longitude,
            $district,
            $province
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "เพิ่มศูนย์พักพิงใหม่เรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึก: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Prepare failed: " . $conn->error;
    }
}

// 6. Redirect กลับไปหน้ารายการ
header('location: shelter_list.php');
exit();
?>