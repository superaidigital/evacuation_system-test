<?php
// import_process.php
// Script สำหรับนำเข้าข้อมูลผู้ประสบภัยจาก CSV (Batch Processing)
// Refactored: แก้ไขจากโค้ดเดิมที่ผิด (เป็น incident_manage) ให้เป็น logic การ import จริงๆ

require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: import_csv.php");
    exit();
}

// 2. Setup Variables
$incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
$shelter_id  = filter_input(INPUT_POST, 'shelter_id', FILTER_VALIDATE_INT);

if (!$incident_id || !$shelter_id) {
    $_SESSION['swal_error'] = "ข้อมูลไม่ครบถ้วน (ขาด Incident หรือ Shelter ID)";
    header("Location: import_csv.php");
    exit();
}

// 3. File Validation
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['swal_error'] = "กรุณาอัปโหลดไฟล์ CSV";
    header("Location: import_csv.php");
    exit();
}

$file = $_FILES['csv_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    $_SESSION['swal_error'] = "รองรับเฉพาะไฟล์ .csv เท่านั้น";
    header("Location: import_csv.php");
    exit();
}

try {
    $handle = fopen($file['tmp_name'], "r");
    
    // Skip BOM if exists
    $bom = fread($handle, 3);
    if ($bom != "\xEF\xBB\xBF") {
        rewind($handle);
    }

    // Skip Header Row
    fgetcsv($handle); 

    $success_count = 0;
    $error_count = 0;
    $error_data = []; // เก็บแถวที่มีปัญหา

    $pdo->beginTransaction();

    // Prepared Statement สำหรับ Insert ข้อมูลคน
    $sql = "INSERT INTO evacuees 
            (incident_id, shelter_id, id_card, prefix, first_name, last_name, gender, age, phone, address_card, health_condition, registered_by, created_at) 
            VALUES 
            (:incident, :shelter, :id_card, :prefix, :fname, :lname, :gender, :age, :phone, :addr, :health, :user, NOW())";
    $stmt = $pdo->prepare($sql);

    // Prepared Statement สำหรับตรวจสอบข้อมูลซ้ำ (ใช้ id_card และ incident_id)
    $stmtCheck = $pdo->prepare("SELECT id FROM evacuees WHERE id_card = ? AND incident_id = ? AND check_out_date IS NULL");

    $row_index = 1; // เริ่มแถวที่ 2 (เพราะข้าม Header)

    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
        $row_index++;
        
        // CSV Format Mapping (ตาม Template):
        // [0]ID Card, [1]Prefix, [2]First, [3]Last, [4]Gender, [5]Age, [6]Phone, [7]Addr, [8]Moo, [9]Tambon, [10]Amphoe, [11]Prov, [12]Health
        
        // 4. Data Sanitization & Validation
        $id_card = isset($data[0]) ? trim(str_replace(['-', ' '], '', $data[0])) : ''; // ลบขีด/ช่องว่าง
        $prefix  = isset($data[1]) ? cleanInput($data[1]) : '';
        $fname   = isset($data[2]) ? cleanInput($data[2]) : '';
        $lname   = isset($data[3]) ? cleanInput($data[3]) : '';
        
        // Gender normalization
        $gender_raw = isset($data[4]) ? trim($data[4]) : '';
        $gender = 'other';
        if (in_array($gender_raw, ['ชาย', 'Male', 'male', 'M'])) $gender = 'male';
        if (in_array($gender_raw, ['หญิง', 'Female', 'female', 'F'])) $gender = 'female';

        $age     = isset($data[5]) ? (int)$data[5] : 0;
        $phone   = isset($data[6]) ? cleanInput($data[6]) : '';
        
        // รวมที่อยู่
        $addr_parts = [];
        if (!empty($data[7])) $addr_parts[] = "เลขที่ " . cleanInput($data[7]);
        if (!empty($data[8])) $addr_parts[] = "หมู่ " . cleanInput($data[8]);
        if (!empty($data[9])) $addr_parts[] = "ต." . cleanInput($data[9]);
        if (!empty($data[10])) $addr_parts[] = "อ." . cleanInput($data[10]);
        if (!empty($data[11])) $addr_parts[] = "จ." . cleanInput($data[11]);
        $address = implode(' ', $addr_parts);

        $health  = isset($data[12]) ? cleanInput($data[12]) : '';

        // --- Logic การตรวจสอบ ---
        
        // 1. เช็คข้อมูลจำเป็น
        if (empty($fname) || empty($lname)) {
            $data[] = "ไม่ระบุชื่อ-นามสกุล"; // เพิ่มคอลัมน์เหตุผลท้ายแถว
            $error_data[] = $data;
            $error_count++;
            continue;
        }

        // 2. เช็คเลขบัตร (ถ้ามี)
        if (!empty($id_card)) {
            if (function_exists('validateThaiID') && !validateThaiID($id_card)) {
                $data[] = "เลขบัตรประชาชนไม่ถูกต้อง";
                $error_data[] = $data;
                $error_count++;
                continue;
            }

            // 3. เช็คซ้ำใน DB
            $stmtCheck->execute([$id_card, $incident_id]);
            if ($stmtCheck->fetchColumn()) {
                $data[] = "มีรายชื่อในระบบแล้ว";
                $error_data[] = $data;
                $error_count++;
                continue;
            }
        }

        // 5. Execute Insert
        try {
            $stmt->execute([
                ':incident' => $incident_id,
                ':shelter'  => $shelter_id,
                ':id_card'  => $id_card,
                ':prefix'   => $prefix,
                ':fname'    => $fname,
                ':lname'    => $lname,
                ':gender'   => $gender,
                ':age'      => $age,
                ':phone'    => $phone,
                ':addr'     => $address,
                ':health'   => $health,
                ':user'     => $_SESSION['user_id']
            ]);
            $success_count++;
        } catch (Exception $e) {
            $data[] = "Database Error: " . $e->getMessage();
            $error_data[] = $data;
            $error_count++;
        }
    }

    fclose($handle);
    $pdo->commit();

    // 6. Summary & Error Handling
    $msg = "นำเข้าสำเร็จ: $success_count ราย";
    if ($error_count > 0) {
        $msg .= " | ไม่ผ่าน: $error_count ราย (กรุณาดาวน์โหลดไฟล์เพื่อแก้ไข)";
        $_SESSION['swal_error'] = $msg;
        $_SESSION['import_error_data'] = $error_data; // เก็บ Array Error ไว้ใน Session เพื่อ Download
        
        // สร้างปุ่ม Download ในหน้าถัดไปผ่าน Flash Message หรือ Logic อื่น (ในที่นี้เราใช้ session check ที่หน้า import_csv.php)
    } else {
        $_SESSION['swal_success'] = $msg;
    }

    // Update Shelter Status Last Updated
    $pdo->prepare("UPDATE shelters SET last_updated = NOW() WHERE id = ?")->execute([$shelter_id]);

    logActivity($pdo, $_SESSION['user_id'], 'Import Evacuees', "นำเข้าไฟล์ CSV สำเร็จ $success_count ราย (Shelter ID: $shelter_id)");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("CSV Import Error: " . $e->getMessage());
    $_SESSION['swal_error'] = "เกิดข้อผิดพลาดร้ายแรง: " . $e->getMessage();
}

header("Location: import_csv.php");
exit();
?>