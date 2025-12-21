<?php
// shelter_status.php
require_once 'config/db.php';
require_once 'includes/functions.php';
session_start();

// Security Check: เฉพาะ Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['swal_error'] = "คุณไม่มีสิทธิ์ดำเนินการนี้";
    header("Location: shelter_list.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0 && $action == 'delete') {
    try {
        // 1. ตรวจสอบว่ามีคนพักอยู่หรือไม่
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['swal_error'] = "ไม่สามารถลบศูนย์นี้ได้ เนื่องจากยังมีผู้พักอาศัยอยู่ $count คน";
        } else {
            // 2. ดึงชื่อศูนย์มาเก็บไว้ทำ Log ก่อนลบ
            $stmtInfo = $pdo->prepare("SELECT name FROM shelters WHERE id = ?");
            $stmtInfo->execute([$id]);
            $shelterName = $stmtInfo->fetchColumn();

            // 3. ลบศูนย์
            $stmtDel = $pdo->prepare("DELETE FROM shelters WHERE id = ?");
            $stmtDel->execute([$id]);

            // 4. บันทึก Log
            logActivity($pdo, $_SESSION['user_id'], 'Delete Shelter', "ลบศูนย์พักพิง: $shelterName (ID: $id)");

            $_SESSION['swal_success'] = "ลบศูนย์พักพิงเรียบร้อยแล้ว";
        }

    } catch (PDOException $e) {
        // กรณีติด Foreign Key Constraint (เช่น มีประวัติเก่าๆ)
        if ($e->getCode() == '23000') {
            $_SESSION['swal_error'] = "ไม่สามารถลบได้ เนื่องจากมีประวัติข้อมูลเชื่อมโยงอยู่ (แนะนำให้เปลี่ยนสถานะเป็นปิดใช้งานแทน)";
        } else {
            error_log($e->getMessage());
            $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

header("Location: shelter_list.php");
exit();
?>