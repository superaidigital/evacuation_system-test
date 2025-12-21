<?php
// shelter_save.php
require_once 'config/db.php';
session_start();

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $mode = $_POST['mode'];
    $id = $_POST['id'];
    $incident_id = $_POST['incident_id']; // รับค่า incident_id
    
    $name = clean_input($_POST['name']);
    $code = clean_input($_POST['code']);
    $capacity = intval($_POST['capacity']);
    $subdistrict = clean_input($_POST['subdistrict']);
    $district = clean_input($_POST['district']);
    $province = clean_input($_POST['province']);
    $contact_person = clean_input($_POST['contact_person']);
    $phone = clean_input($_POST['phone']);

    try {
        if ($mode == 'add') {
            $sql = "INSERT INTO shelters (incident_id, name, code, capacity, subdistrict, district, province, contact_person, phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$incident_id, $name, $code, $capacity, $subdistrict, $district, $province, $contact_person, $phone]);
            
            $_SESSION['swal_success'] = "เพิ่มศูนย์พักพิงเรียบร้อยแล้ว";

        } else {
            $sql = "UPDATE shelters SET name=?, code=?, capacity=?, subdistrict=?, district=?, province=?, contact_person=?, phone=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $code, $capacity, $subdistrict, $district, $province, $contact_person, $phone, $id]);
            
            $_SESSION['swal_success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";
        }

        header("Location: shelter_list.php?filter_incident=" . $incident_id);
        exit();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>