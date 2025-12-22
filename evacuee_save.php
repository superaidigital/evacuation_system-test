<?php
// evacuee_save.php
// สคริปต์บันทึกข้อมูลผู้อพยพ (แก้ไขปัญหา Foreign Key Constraint)

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

// 2. CSRF Protection Check
$csrf_token = $_POST['csrf_token'] ?? '';
if (function_exists('validateCSRFToken')) {
    validateCSRFToken($csrf_token);
}

// รับค่าและ Clean Input
$mode        = isset($_POST['mode']) ? cleanInput($_POST['mode']) : 'add';
$id          = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);

// จัดการ Prefix
$prefix_select = $_POST['prefix_select'] ?? '';
$prefix = ($prefix_select === 'other') ? cleanInput($_POST['prefix_custom']) : cleanInput($prefix_select);

$first_name  = cleanInput($_POST['first_name'] ?? '');
$last_name   = cleanInput($_POST['last_name'] ?? '');
$id_card     = cleanInput($_POST['id_card'] ?? '');
$phone       = cleanInput($_POST['phone'] ?? '');
$age         = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender      = cleanInput($_POST['gender'] ?? '');
$health      = cleanInput($_POST['health_condition'] ?? '');
$address_card = cleanInput($_POST['address_card'] ?? '');

// จัดการ Stay Type (ที่พัก)
$stay_type   = cleanInput($_POST['stay_type'] ?? 'shelter');
$stay_detail = cleanInput($_POST['stay_detail'] ?? '');

// --- [จุดที่แก้ไข] ---
// ถ้าพักนอกศูนย์ หรือไม่ได้เลือกศูนย์ ให้ค่าเป็น NULL (อย่าใช้ 0 เพราะจะติด Foreign Key)
$shelter_input = filter_input(INPUT_POST, 'shelter_id', FILTER_VALIDATE_INT);
if ($stay_type == 'shelter' && $shelter_input) {
    $shelter_id = $shelter_input;
} else {
    $shelter_id = null; // ส่งค่า NULL ลงฐานข้อมูล
}

// Validation เบื้องต้น
if (!$incident_id || empty($first_name) || empty($last_name)) {
    echo "<script>alert('กรุณากรอกข้อมูลสำคัญให้ครบถ้วน'); window.history.back();</script>";
    exit();
}

if ($stay_type == 'shelter' && empty($shelter_id)) {
    echo "<script>alert('กรุณาเลือกศูนย์พักพิง'); window.history.back();</script>";
    exit();
}

// Validate Thai ID Card
if (!empty($id_card) && function_exists('validateThaiID') && !validateThaiID($id_card)) {
    echo "<script>alert('เลขบัตรประชาชนไม่ถูกต้องตามรูปแบบ'); window.history.back();</script>";
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. ตรวจสอบสถานะ Incident
    $stmtCheck = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
    $stmtCheck->execute([$incident_id]);
    if ($stmtCheck->fetchColumn() !== 'active') {
        throw new Exception("ไม่สามารถบันทึกข้อมูลในภารกิจที่ปิดไปแล้ว");
    }

    // 2. ตรวจสอบความจุศูนย์ (เฉพาะกรณีพักในศูนย์ที่มี ID ชัดเจน)
    $shelterInfo = null;
    if ($stay_type == 'shelter' && $shelter_id) {
        $stmtCap = $pdo->prepare("SELECT capacity, 
            (SELECT COUNT(*) FROM evacuees WHERE shelter_id = shelters.id AND check_out_date IS NULL) as used 
            FROM shelters WHERE id = ? FOR UPDATE");
        $stmtCap->execute([$shelter_id]);
        $shelterInfo = $stmtCap->fetch();

        if ($mode == 'add') {
            if ($shelterInfo && $shelterInfo['used'] >= $shelterInfo['capacity']) {
                throw new Exception("บันทึกไม่สำเร็จ! ศูนย์พักพิงนี้เต็มแล้ว");
            }
        }
    }
        
    // 3. เช็คซ้ำ (Duplicate Check)
    if ($mode == 'add' && !empty($id_card)) {
        $stmtDup = $pdo->prepare("SELECT id FROM evacuees WHERE id_card = ? AND incident_id = ? AND check_out_date IS NULL");
        $stmtDup->execute([$id_card, $incident_id]);
        if ($stmtDup->rowCount() > 0) {
            throw new Exception("บุคคลนี้ (เลขบัตร $id_card) ลงทะเบียนอยู่ในระบบแล้ว");
        }
    }

    // 4. เตรียม SQL
    if ($mode == 'add') {
        $sql = "INSERT INTO evacuees 
                (incident_id, shelter_id, id_card, prefix, first_name, last_name, phone, gender, age, health_condition, address_card, stay_type, stay_detail, registered_by, created_at) 
                VALUES 
                (:incident_id, :shelter_id, :id_card, :prefix, :first_name, :last_name, :phone, :gender, :age, :health, :address_card, :stay_type, :stay_detail, :user_id, NOW())";
        $logAction = "Add Evacuee";
    } else {
        $sql = "UPDATE evacuees SET 
                shelter_id = :shelter_id, id_card = :id_card, prefix = :prefix, 
                first_name = :first_name, last_name = :last_name, phone = :phone, 
                gender = :gender, age = :age, health_condition = :health, 
                address_card = :address_card, stay_type = :stay_type, stay_detail = :stay_detail,
                updated_at = NOW()
                WHERE id = :id";
        $logAction = "Edit Evacuee";
    }
    
    $stmt = $pdo->prepare($sql);
    
    // Bind Params
    $stmt->bindValue(':shelter_id', $shelter_id, $shelter_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':id_card', $id_card);
    $stmt->bindValue(':prefix', $prefix);
    $stmt->bindValue(':first_name', $first_name);
    $stmt->bindValue(':last_name', $last_name);
    $stmt->bindValue(':phone', $phone);
    $stmt->bindValue(':gender', $gender);
    $stmt->bindValue(':age', $age);
    $stmt->bindValue(':health', $health);
    $stmt->bindValue(':address_card', $address_card);
    $stmt->bindValue(':stay_type', $stay_type);
    $stmt->bindValue(':stay_detail', $stay_detail);

    if ($mode == 'add') {
        $stmt->bindValue(':incident_id', $incident_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    }

    $stmt->execute();

    // หา evacuee_id ที่เพิ่งทำรายการ
    $evacuee_id = ($mode == 'add') ? $pdo->lastInsertId() : $id;

    // จัดการข้อมูลกลุ่มเปราะบาง (Vulnerable Groups)
    if ($mode == 'edit') {
        $stmtDelNeeds = $pdo->prepare("DELETE FROM evacuee_needs WHERE evacuee_id = ?");
        $stmtDelNeeds->execute([$evacuee_id]);
    }

    if (isset($_POST['needs']) && is_array($_POST['needs'])) {
        $stmtInsertNeed = $pdo->prepare("INSERT INTO evacuee_needs (evacuee_id, need_type) VALUES (?, ?)");
        $allowed_needs = ['elderly', 'disabled', 'pregnant', 'infant', 'chronic', 'halal', 'vegetarian'];
        foreach ($_POST['needs'] as $need) {
            if (in_array($need, $allowed_needs)) {
                $stmtInsertNeed->execute([$evacuee_id, $need]);
            }
        }
    }

    // Update Shelter Status ถ้าเต็ม
    if ($stay_type == 'shelter' && $shelterInfo && ($shelterInfo['used'] + 1) >= $shelterInfo['capacity']) {
        $pdo->prepare("UPDATE shelters SET status = 'full' WHERE id = ?")->execute([$shelter_id]);
    }

    // บันทึก Log
    if (function_exists('logActivity')) {
        $s_log = $shelter_id ? $shelter_id : "N/A";
        logActivity($pdo, $_SESSION['user_id'], $logAction, "ชื่อ: $first_name $last_name (Shelter ID: $s_log)");
    }

    $pdo->commit();

    $_SESSION['swal_success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
    
    if ($shelter_id > 0) {
        header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
    } else {
        header("Location: search_evacuee.php?keyword=" . urlencode($first_name));
    }
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    header("Location: evacuee_form.php?shelter_id=$shelter_id&mode=$mode&id=$id");
    exit();
}
?>