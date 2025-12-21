<?php
// evacuee_status.php
require_once 'config/db.php';
require_once 'includes/functions.php';
session_start();

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$shelter_id = isset($_GET['shelter_id']) ? intval($_GET['shelter_id']) : 0;

if ($id > 0) {
    try {
        if ($action == 'checkout') {
            // --- กรณีจำหน่ายออก (Check Out) ---
            $sql = "UPDATE evacuees SET check_out_date = NOW(), status = 'checked_out' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // บันทึก Log
            logActivity($pdo, $_SESSION['user_id'], 'Checkout', "จำหน่ายผู้ประสบภัยออก (ID: $id)");
            
            $_SESSION['swal_success'] = "จำหน่ายออกเรียบร้อยแล้ว";

        } elseif ($action == 'delete') {
            // --- กรณีลบข้อมูล (Delete) ---
            // ตรวจสอบสิทธิ์ก่อน (เช่น Admin เท่านั้นที่ลบได้ หรือ Staff ก็ได้แล้วแต่นโยบาย)
            if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
                $_SESSION['swal_error'] = "คุณไม่มีสิทธิ์ลบข้อมูลนี้";
                header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
                exit();
            }

            $sql = "DELETE FROM evacuees WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // บันทึก Log
            logActivity($pdo, $_SESSION['user_id'], 'Delete Evacuee', "ลบข้อมูลผู้ประสบภัยถาวร (ID: $id)");

            $_SESSION['swal_success'] = "ลบข้อมูลเรียบร้อยแล้ว";
        }

        // อัปเดตเวลาล่าสุดของศูนย์
        if ($shelter_id > 0) {
            $pdo->prepare("UPDATE shelters SET last_updated = NOW() WHERE id = ?")->execute([$shelter_id]);
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ส่งกลับหน้ารายชื่อ
if ($shelter_id > 0) {
    header("Location: evacuee_list.php?shelter_id=" . $shelter_id);
} else {
    header("Location: search_evacuee.php"); // กรณีลบจากหน้าค้นหา
}
exit();
?>