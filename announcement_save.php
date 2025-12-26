<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์ (Security Check)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. Logic สำหรับการเพิ่มประกาศใหม่ (Create)
if (isset($_POST['save_announcement'])) {
    // รับค่าและ Sanitize ข้อมูล
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type = $_POST['type'];
    // ถ้าเลือก "ทุกศูนย์" (value="") ให้เก็บเป็น NULL ใน DB
    $target_shelter_id = !empty($_POST['target_shelter_id']) ? $_POST['target_shelter_id'] : null;
    $created_by = $_SESSION['user_id'];

    // Validation
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "กรุณากรอกหัวข้อและเนื้อหาให้ครบถ้วน";
        header("Location: announcement_manager.php");
        exit();
    }

    // Clean Code: Use Prepared Statement to prevent SQL Injection
    $sql = "INSERT INTO announcements (title, content, type, target_shelter_id, created_by, is_active) VALUES (?, ?, ?, ?, ?, 1)";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssis", $title, $content, $type, $target_shelter_id, $created_by);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "บันทึกประกาศเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    }

    header("Location: announcement_manager.php");
    exit();
}

// 2. Logic สำหรับการลบ (Delete)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $sql = "DELETE FROM announcements WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "ลบประกาศเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ";
        }
        mysqli_stmt_close($stmt);
    }
    
    header("Location: announcement_manager.php");
    exit();
}

// 3. Logic สำหรับการเปลี่ยนสถานะ แสดง/ซ่อน (Toggle Status)
if (isset($_GET['toggle']) && isset($_GET['status'])) {
    $id = intval($_GET['toggle']);
    $current_status = intval($_GET['status']);
    $new_status = ($current_status == 1) ? 0 : 1;
    
    $sql = "UPDATE announcements SET is_active = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $new_status, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "อัปเดตสถานะเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดต";
        }
        mysqli_stmt_close($stmt);
    }
    
    header("Location: announcement_manager.php");
    exit();
}

// ถ้าเข้ามาโดยไม่มี Action
header("Location: announcement_manager.php");
exit();
?>