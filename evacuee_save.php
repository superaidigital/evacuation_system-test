<?php
// evacuee_save.php
require_once 'config/db.php';

// 1. Authentication Check
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Method Not Allowed");
}

// รับค่าและ Validate
$incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
$shelter_id  = filter_input(INPUT_POST, 'shelter_id', FILTER_VALIDATE_INT);
$first_name  = trim($_POST['first_name'] ?? '');
$last_name   = trim($_POST['last_name'] ?? '');
$id_card     = trim($_POST['id_card'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$age         = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender      = $_POST['gender'] ?? '';
$health      = trim($_POST['health_condition'] ?? '');

// Validation เบื้องต้น
// ถ้า incident_id ไม่ส่งมา ให้ลองเอาจาก session
if (!$incident_id && isset($_SESSION['current_incident_id'])) {
    $incident_id = $_SESSION['current_incident_id'];
}

if (!$incident_id || !$shelter_id || empty($first_name) || empty($last_name)) {
    $_SESSION['error'] = "กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (ชื่อ, นามสกุล, ศูนย์พักพิง)";
    // header("Location: evacuee_form.php"); // ส่งกลับไปหน้าฟอร์ม (ต้องแก้ path ให้ถูกต้อง)
    echo "<script>alert('กรุณากรอกข้อมูลให้ครบถ้วน'); window.history.back();</script>";
    exit();
}

try {
    // 3. ตรวจสอบสถานะ Incident (Double Check)
    $stmtCheck = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
    $stmtCheck->execute([$incident_id]);
    $incidentStatus = $stmtCheck->fetchColumn();

    if ($incidentStatus !== 'active') {
        throw new Exception("ไม่สามารถบันทึกข้อมูลในเหตุการณ์ที่ปิดไปแล้ว หรือไม่พบเหตุการณ์");
    }

    // 4. Prepared Statement สำหรับ Insert
    $sql = "INSERT INTO evacuees 
            (incident_id, shelter_id, id_card, first_name, last_name, phone, gender, age, health_condition, registered_by) 
            VALUES 
            (:incident_id, :shelter_id, :id_card, :first_name, :last_name, :phone, :gender, :age, :health, :user_id)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'incident_id' => $incident_id,
        'shelter_id'  => $shelter_id,
        'id_card'     => $id_card,
        'first_name'  => $first_name,
        'last_name'   => $last_name,
        'phone'       => $phone,
        'gender'      => $gender,
        'age'         => $age,
        'health'      => $health,
        'user_id'     => $_SESSION['user_id']
    ]);

    if ($result) {
        $_SESSION['success'] = "บันทึกข้อมูลผู้ประสบภัยเรียบร้อยแล้ว";
        header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
        exit();
    } else {
        throw new Exception("ไม่สามารถบันทึกข้อมูลลงฐานข้อมูลได้");
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    echo "<script>alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "'); window.history.back();</script>";
    exit();
}
?>