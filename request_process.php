<?php
/**
 * request_process.php
 * ประมวลผลการอนุมัติ/ปฏิเสธคำร้องขอพัสดุ
 * ความสามารถ: อัปเดตสถานะ, หักสต็อกอัตโนมัติ (กรณีอนุมัติ), และแจ้งเตือนผ่าน LINE
 */
require_once 'config/db.php';
require_once 'includes/functions.php';
// เรียกใช้ line_helper หากคุณมีการตั้งค่าไว้
if (file_exists('includes/line_helper.php')) {
    require_once 'includes/line_helper.php';
}

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

if ($request_id > 0 && in_array($status, ['approved', 'rejected'])) {
    
    // เริ่มต้น Transaction เพื่อความปลอดภัยของข้อมูล
    $conn->begin_transaction();

    try {
        // 2. ดึงข้อมูลคำร้องเพื่อตรวจสอบรายการและจำนวน
        $sql_fetch = "SELECT r.*, i.item_name, i.quantity as current_stock, i.unit, s.name as shelter_name 
                      FROM requests r 
                      JOIN inventory i ON r.item_id = i.id 
                      JOIN shelters s ON r.shelter_id = s.id 
                      WHERE r.id = ? FOR UPDATE"; // Lock แถวไว้ป้องกันการหักสต็อกซ้ำซ้อนพร้อมกัน
        $stmt_fetch = $conn->prepare($sql_fetch);
        $stmt_fetch->bind_param("i", $request_id);
        $stmt_fetch->execute();
        $req = $stmt_fetch->get_result()->fetch_assoc();

        if (!$req) throw new Exception("ไม่พบข้อมูลคำร้องในระบบ");
        if ($req['status'] !== 'pending') throw new Exception("คำร้องนี้ได้รับการประมวลผลไปแล้ว");

        if ($status === 'approved') {
            // 3. ตรวจสอบว่าสต็อกพอหรือไม่
            if ($req['current_stock'] < $req['quantity']) {
                throw new Exception("สินค้า '" . $req['item_name'] . "' ในคลังมีไม่พอ (คงเหลือ: " . $req['current_stock'] . ")");
            }

            // 4. หักสต็อกสินค้าออกจากตาราง inventory
            $sql_deduct = "UPDATE inventory SET quantity = quantity - ? WHERE id = ?";
            $stmt_deduct = $conn->prepare($sql_deduct);
            $stmt_deduct->bind_param("ii", $req['quantity'], $req['item_id']);
            if (!$stmt_deduct->execute()) throw new Exception("ไม่สามารถหักสต็อกสินค้าได้");

            // 5. บันทึกประวัติการจ่ายของลงตาราง distribution (ถ้ามีตารางนี้)
            $check_dist = $conn->query("SHOW TABLES LIKE 'distribution'");
            if ($check_dist && $check_dist->num_rows > 0) {
                $note = "อนุมัติจากคำร้องเลขที่ #" . $request_id . " (ศูนย์: " . $req['shelter_name'] . ")";
                // เนื่องจากคำร้องมักเป็นภาพรวมศูนย์ อาจไม่ได้ระบุ evacuee_id รายคน ให้ใส่ 0 หรือ ID กลาง
                $sql_dist = "INSERT INTO distribution (item_id, quantity, note, distributed_by, distributed_at) VALUES (?, ?, ?, ?, NOW())";
                $stmt_dist = $conn->prepare($sql_dist);
                $stmt_dist->bind_param("iisi", $req['item_id'], $req['quantity'], $note, $_SESSION['user_id']);
                $stmt_dist->execute();
            }
        }

        // 6. อัปเดตสถานะคำร้องในตาราง requests
        $sql_update = "UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $status, $request_id);
        if (!$stmt_update->execute()) throw new Exception("ไม่สามารถอัปเดตสถานะคำร้องได้");

        // ยืนยันการทำงานทั้งหมด
        $conn->commit();

        // 7. ส่งแจ้งเตือน LINE (Optional)
        if (function_exists('sendLineNotification')) {
            $status_msg = ($status === 'approved') ? "✅ อนุมัติและจ่ายของแล้ว" : "❌ ปฏิเสธคำร้อง";
            $msg = "\n📢 [อัปเดตสถานะคำร้อง]\n";
            $msg .= "ศูนย์: " . $req['shelter_name'] . "\n";
            $msg .= "รายการ: " . $req['item_name'] . " " . $req['quantity'] . " " . $req['unit'] . "\n";
            $msg .= "สถานะ: " . $status_msg . "\n";
            $msg .= "โดย: " . ($_SESSION['username'] ?? 'Admin');
            sendLineNotification($msg);
        }

        $_SESSION['swal_success'] = "ดำเนินการเรียบร้อยแล้ว";

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกทุกอย่างที่ทำมา (Rollback)
        $conn->rollback();
        $_SESSION['swal_error'] = $e->getMessage();
    }
}

// กลับไปยังหน้าจัดการคำร้อง
header("Location: request_admin.php");
exit();