<?php
// shelter_import_process.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // สำหรับ logActivity

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    
    // 1. รับค่าและ Validate เบื้องต้น
    $incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
    
    if (empty($incident_id)) {
        $_SESSION['swal_error'] = "กรุณาเลือกเหตุการณ์";
        header("Location: shelter_import.php");
        exit();
    }

    // 2. ตรวจสอบไฟล์
    $file = $_FILES['csv_file'];
    
    // Check Error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['swal_error'] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (Code: " . $file['error'] . ")";
        header("Location: shelter_import.php");
        exit();
    }

    // Check Extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        $_SESSION['swal_error'] = "รองรับเฉพาะไฟล์ .csv เท่านั้น";
        header("Location: shelter_import.php");
        exit();
    }

    // Check MIME Type (เพื่อความชัวร์)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // MIME types ของ CSV บางทีอาจเป็น text/plain หรือ application/vnd.ms-excel
    $allowed_mimes = ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'text/x-csv'];
    if (!in_array($mime, $allowed_mimes)) {
        $_SESSION['swal_error'] = "รูปแบบไฟล์ไม่ถูกต้อง (MIME type mismatch)";
        header("Location: shelter_import.php");
        exit();
    }

    try {
        $handle = fopen($file['tmp_name'], "r");
        
        // ตรวจสอบ BOM (Byte Order Mark) เพื่อแก้ปัญหาภาษาไทยอ่านไม่ออก
        $bom = fread($handle, 3);
        if ($bom != "\xEF\xBB\xBF") {
            rewind($handle); // ถ้าไม่ใช่ BOM ให้กลับไปเริ่มต้นไฟล์
        }

        // อ่าน Header ข้ามไป
        fgetcsv($handle); 

        // เริ่ม Transaction
        $pdo->beginTransaction();

        $sql = "INSERT INTO shelters (incident_id, name, location, capacity, contact_phone, status, last_updated) 
                VALUES (?, ?, ?, ?, ?, 'open', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $count = 0;
        $row_num = 1; // เริ่มนับแถวข้อมูล (ไม่รวม Header)

        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            $row_num++;
            
            // CSV Template: [0]ชื่อ, [1]ที่ตั้ง/ตำบล/อำเภอ, [2]ความจุ, [3]เบอร์โทร
            
            // Sanitize Data
            $name = isset($data[0]) ? cleanInput($data[0]) : '';
            $location = isset($data[1]) ? cleanInput($data[1]) : '';
            $capacity = isset($data[2]) ? intval($data[2]) : 0;
            $phone = isset($data[3]) ? cleanInput($data[3]) : '';

            // Validation per row
            if (empty($name)) {
                // ข้ามแถวที่ไม่มีชื่อศูนย์ หรืออาจจะเก็บ Error Log ไว้แจ้ง User
                continue; 
            }

            if ($capacity <= 0) $capacity = 100; // Default

            $stmt->execute([
                $incident_id,
                $name,
                $location,
                $capacity,
                $phone
            ]);
            $count++;
        }

        fclose($handle);
        
        // Commit Transaction
        $pdo->commit();

        logActivity($pdo, $_SESSION['user_id'], 'Import Shelters', "นำเข้าศูนย์พักพิง $count แห่ง (Incident ID: $incident_id)");

        $_SESSION['swal_success'] = "นำเข้าข้อมูลศูนย์พักพิงสำเร็จจำนวน $count แห่ง";

    } catch (Exception $e) {
        $pdo->rollBack(); // ย้อนกลับทั้งหมดถ้ามี Error
        error_log("Import Error: " . $e->getMessage());
        $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

    header("Location: shelter_import.php");
    exit();

} else {
    header("Location: shelter_import.php");
    exit();
}
?>