<?php
/**
 * distribution_process.php
 * ประมวลผลการแจกจ่ายของบรรเทาทุกข์ พร้อมระบบตัดสต็อกอัตโนมัติ
 * ใช้ Database Transaction เพื่อความถูกต้องของข้อมูล 100%
 */
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evacuee_id = (int)$_POST['evacuee_id'];
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $note = $_POST['note'] ?? '';

    if ($evacuee_id <= 0 || $item_id <= 0 || $quantity <= 0) {
        $_SESSION['swal_error'] = "ข้อมูลไม่ถูกต้อง กรุณาลองใหม่";
        header("Location: smart_distribution.php?evacuee_id=$evacuee_id");
        exit();
    }

    try {
        // เริ่ม Transaction เพื่อป้องกันปัญหาเน็ตหลุด/ไฟดับแล้วสต็อกเพี้ยน
        $pdo->beginTransaction();

        // 1. ตรวจสอบสต็อกปัจจุบัน
        $stmt_check = $pdo->prepare("SELECT name, quantity FROM inventory WHERE id = ? FOR UPDATE");
        $stmt_check->execute([$item_id]);
        $item = $stmt_check->fetch();

        if (!$item || $item['quantity'] < $quantity) {
            throw new Exception("สินค้า '" . ($item['name'] ?? 'ไม่ทราบชื่อ') . "' มีไม่พอแจก (คงเหลือ: " . ($item['quantity'] ?? 0) . ")");
        }

        // 2. ตัดสต็อกออกจาก inventory
        $stmt_update = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        $stmt_update->execute([$quantity, $item_id]);

        // 3. บันทึกลงตารางการแจกจ่าย (distribution)
        $stmt_insert = $pdo->prepare("INSERT INTO distribution (evacuee_id, item_id, quantity, distributed_at, note, distributed_by) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt_insert->execute([$evacuee_id, $item_id, $quantity, $note, $_SESSION['user_id']]);

        // บันทึก Log การทำงาน
        // (สมมติว่าคุณมีฟังก์ชัน logAction หรือบันทึกใส่ system_logs)

        // ยืนยันการทำงานทั้งหมด
        $pdo->commit();

        $_SESSION['swal_success'] = "บันทึกการแจกจ่ายเรียบร้อยแล้ว";
        header("Location: qr_scanner.php"); // กลับไปหน้าสแกนเพื่อรอรับคนถัดไป

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกการเปลี่ยนแปลงทั้งหมด (Rollback)
        $pdo->rollBack();
        $_SESSION['swal_error'] = $e->getMessage();
        header("Location: smart_distribution.php?evacuee_id=$evacuee_id");
    }
    exit();
}