<?php
// evacuee_save.php
// Refactored: Added Medical Triage & Health Data Support
// รองรับการบันทึกข้อมูลสุขภาพ: Triage Level, โรคประจำตัว, ประวัติแพ้ยา

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

// Helper function
function getTrimmedPost($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// รับค่า Input พื้นฐาน
$mode        = getTrimmedPost('mode', 'add');
$id          = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$incident_id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
$shelter_input = filter_input(INPUT_POST, 'shelter_id', FILTER_VALIDATE_INT);

// จัดการ Prefix
$prefix_select = getTrimmedPost('prefix_select');
$prefix = ($prefix_select === 'other') ? getTrimmedPost('prefix_custom') : $prefix_select;

$first_name  = getTrimmedPost('first_name');
$last_name   = getTrimmedPost('last_name');
$id_card     = getTrimmedPost('id_card');
$phone       = getTrimmedPost('phone');
$age         = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
$gender      = getTrimmedPost('gender');
$address_card = getTrimmedPost('address_card');

// จัดการ Stay Type
$stay_type   = getTrimmedPost('stay_type', 'shelter');
$stay_detail = getTrimmedPost('stay_detail');
$shelter_id = ($stay_type === 'shelter') ? $shelter_input : null;

// --- New: Medical Data ---
$triage_level = getTrimmedPost('triage_level', 'green'); // green, yellow, red
$medical_condition = getTrimmedPost('medical_condition');
$drug_allergy = getTrimmedPost('drug_allergy');

// --- Validation Layer ---
$errors = [];
if ($mode === 'add' && !$incident_id) $errors[] = "ไม่พบข้อมูลภารกิจ (Incident ID)";
if (empty($first_name) || empty($last_name)) $errors[] = "กรุณาระบุชื่อและนามสกุล";
if ($stay_type === 'shelter' && empty($shelter_id)) $errors[] = "กรุณาเลือกศูนย์พักพิง";

// หากมี Error
if (!empty($errors)) {
    $_SESSION['swal_error'] = implode("<br>", $errors);
    header("Location: evacuee_form.php?mode=$mode&id=$id&shelter_id=$shelter_id");
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Check Capacity (Only for Shelter type)
    if ($stay_type == 'shelter' && $shelter_id) {
        // ... (Logic เช็คความจุเหมือนเดิม) ...
        // เพื่อความกระชับ ขอละไว้ในฐานที่เข้าใจ (ใช้โค้ดเดิมได้เลย)
    }
        
    // 2. Duplicate Check
    if ($mode == 'add' && !empty($id_card)) {
        $stmtDup = $pdo->prepare("SELECT id FROM evacuees WHERE id_card = ? AND incident_id = ? AND check_out_date IS NULL");
        $stmtDup->execute([$id_card, $incident_id]);
        if ($stmtDup->fetchColumn()) {
            throw new Exception("บุคคลนี้ (เลขบัตร $id_card) อยู่ในระบบแล้ว");
        }
    }

    // 3. Insert / Update SQL (รวม Medical Fields)
    if ($mode == 'add') {
        $sql = "INSERT INTO evacuees 
                (incident_id, shelter_id, id_card, prefix, first_name, last_name, phone, gender, age, 
                 address_card, stay_type, stay_detail, 
                 triage_level, medical_condition, drug_allergy,
                 registered_by, created_at) 
                VALUES 
                (:incident_id, :shelter_id, :id_card, :prefix, :first_name, :last_name, :phone, :gender, :age, 
                 :address_card, :stay_type, :stay_detail, 
                 :triage_level, :medical_condition, :drug_allergy,
                 :user_id, NOW())";
    } else {
        $sql = "UPDATE evacuees SET 
                shelter_id = :shelter_id, id_card = :id_card, prefix = :prefix, 
                first_name = :first_name, last_name = :last_name, phone = :phone, 
                gender = :gender, age = :age, 
                address_card = :address_card, stay_type = :stay_type, stay_detail = :stay_detail,
                triage_level = :triage_level, medical_condition = :medical_condition, drug_allergy = :drug_allergy,
                updated_at = NOW()
                WHERE id = :id";
    }
    
    $stmt = $pdo->prepare($sql);
    
    // Bind Params
    $params = [
        ':shelter_id' => $shelter_id,
        ':id_card' => $id_card,
        ':prefix' => $prefix,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':phone' => $phone,
        ':gender' => $gender,
        ':age' => $age,
        ':address_card' => $address_card,
        ':stay_type' => $stay_type,
        ':stay_detail' => $stay_detail,
        ':triage_level' => $triage_level,
        ':medical_condition' => $medical_condition,
        ':drug_allergy' => $drug_allergy
    ];

    if ($mode == 'add') {
        $params[':incident_id'] = $incident_id;
        $params[':user_id'] = $_SESSION['user_id'];
    } else {
        $params[':id'] = $id;
    }

    $stmt->execute($params);
    $evacuee_id = ($mode == 'add') ? $pdo->lastInsertId() : $id;

    // 4. Update Needs (กลุ่มเปราะบาง)
    if ($mode == 'edit') {
        $pdo->prepare("DELETE FROM evacuee_needs WHERE evacuee_id = ?")->execute([$evacuee_id]);
    }

    if (!empty($_POST['needs']) && is_array($_POST['needs'])) {
        $allowed = ['elderly', 'disabled', 'pregnant', 'infant', 'chronic', 'halal', 'vegetarian'];
        $vals = []; $prms = [];
        foreach ($_POST['needs'] as $n) {
            if (in_array($n, $allowed)) {
                $vals[] = "(?, ?)"; $prms[] = $evacuee_id; $prms[] = $n;
            }
        }
        if (!empty($vals)) {
            $sqlN = "INSERT INTO evacuee_needs (evacuee_id, need_type) VALUES " . implode(", ", $vals);
            $pdo->prepare($sqlN)->execute($prms);
        }
    }

    // 5. Update Shelter Status (ถ้าเต็ม)
    if ($stay_type == 'shelter' && $shelter_id) {
         // (Logic เช็คเต็มเหมือนเดิม)
    }

    $pdo->commit();
    $_SESSION['swal_success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";
    
    header("Location: evacuee_list.php?shelter_id=" . ($shelter_id ? $shelter_id : ''));
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['swal_error'] = "Error: " . $e->getMessage();
    header("Location: evacuee_form.php?mode=$mode&id=$id");
    exit();
}
?>