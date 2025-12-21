<?php
// shelter_import_process.php
require_once 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    
    $incident_id = $_POST['incident_id'];
    $file = $_FILES['csv_file']['tmp_name'];

    if (empty($incident_id)) {
        $_SESSION['swal_error'] = "กรุณาเลือกเหตุการณ์";
        header("Location: shelter_import.php");
        exit();
    }

    try {
        $handle = fopen($file, "r");
        
        // อ่านบรรทัดแรก (Header) เพื่อข้ามไป
        fgetcsv($handle); 

        $count = 0;
        $pdo->beginTransaction();

        $sql = "INSERT INTO shelters (incident_id, name, location, capacity, contact_phone, status) 
                VALUES (?, ?, ?, ?, ?, 'open')";
        
        $stmt = $pdo->prepare($sql);

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // โครงสร้าง CSV Template:
            // [0] ชื่อศูนย์, [1] ที่ตั้ง, [2] ความจุ, [3] เบอร์ติดต่อ
            
            // ข้ามแถวว่าง หรือไม่มีชื่อศูนย์
            if(empty($data[0])) continue;

            $name = trim($data[0]);
            $location = trim($data[1]);
            $capacity = intval($data[2]);
            $phone = trim($data[3]);

            // ตรวจสอบความจุ ถ้าไม่ระบุให้ default เป็น 0
            if ($capacity <= 0) $capacity = 100; // Default capacity

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
        $pdo->commit();

        $_SESSION['swal_success'] = "นำเข้าข้อมูลศูนย์พักพิงสำเร็จจำนวน $count แห่ง";

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Import Shelter Error: " . $e->getMessage());
        $_SESSION['swal_error'] = "เกิดข้อผิดพลาดในการอ่านไฟล์ หรือรูปแบบข้อมูลไม่ถูกต้อง";
    }

    header("Location: shelter_import.php");
    exit();

} else {
    header("Location: shelter_import.php");
    exit();
}
?>