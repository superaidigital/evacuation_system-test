<?php
// distribution_save.php
// ระบบบันทึกการตัดสต็อก (รองรับฟอร์มแบบละเอียด: Ref No, ผู้รับแทน, หน่วยงาน)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าพื้นฐาน
    $inventory_id = (int)$_POST['inventory_id'];
    $quantity = (int)$_POST['quantity'];
    $recipient_type = $_POST['recipient_type']; // evacuee หรือ general
    
    // 2. รับค่าเพิ่มเติม (Optional)
    $ref_no = cleanInput($_POST['ref_no'] ?? '');
    $user_note = cleanInput($_POST['note'] ?? '');
    
    // Validation พื้นฐาน
    if (!$inventory_id || $quantity <= 0) {
        $_SESSION['error'] = "ข้อมูลไม่ถูกต้อง กรุณาเลือกสินค้าและระบุจำนวนที่มากกว่า 0";
        header("Location: distribution_manager.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 3. ตรวจสอบสต็อก (Lock Row for Update เพื่อกันการตัดซ้อน)
        $stmt = $pdo->prepare("SELECT item_name, quantity, shelter_id, unit FROM inventory WHERE id = ? FOR UPDATE");
        $stmt->execute([$inventory_id]);
        $item = $stmt->fetch();

        if (!$item) { throw new Exception("ไม่พบสินค้าในระบบ"); }
        if ($item['quantity'] < $quantity) { 
            throw new Exception("ยอดคงเหลือไม่พอ (มี {$item['quantity']} {$item['unit']}, ต้องการ $quantity)"); 
        }

        $item_name = $item['item_name'];

        // 4. เตรียมข้อความบันทึก (Construct Log Note)
        // สร้างข้อความสรุปเพื่อเก็บใน inventory_transactions.note
        $final_note = "";
        $evacuee_id = null;
        
        // ใส่เลขที่เอกสารนำหน้า (ถ้ามี)
        if ($ref_no) {
            $final_note .= "[Ref: $ref_no] ";
        }

        if ($recipient_type === 'evacuee') {
            // --- กรณี A: แจกให้ผู้ประสบภัยรายบุคคล ---
            $evacuee_id = $_POST['evacuee_id'] ? (int)$_POST['evacuee_id'] : null;
            if (!$evacuee_id) { throw new Exception("กรุณาค้นหาและเลือกชื่อผู้ประสบภัย"); }

            // ดึงชื่อผู้ประสบภัยมาเก็บไว้ใน Note
            $stmt_ev = $pdo->prepare("SELECT first_name, last_name FROM evacuees WHERE id = ?");
            $stmt_ev->execute([$evacuee_id]);
            $ev = $stmt_ev->fetch();
            $ev_name = $ev ? $ev['first_name'] . " " . $ev['last_name'] : "Unknown";

            // ชื่อผู้รับแทน (ถ้ามี)
            $receiver_name = cleanInput($_POST['receiver_name_ev'] ?? '');
            
            $final_note .= "แจกให้: $ev_name";
            if ($receiver_name) {
                $final_note .= " (รับแทนโดย: $receiver_name)";
            }

            // บันทึกลงตาราง distribution_logs (สำหรับดูประวัติรายคน)
            $log_dist = $pdo->prepare("INSERT INTO distribution_logs (evacuee_id, item_name, quantity, given_at, given_by) 
                                       VALUES (?, ?, ?, NOW(), ?)");
            $log_dist->execute([$evacuee_id, $item_name, $quantity, $user_id]);

        } else {
            // --- กรณี B: แจกให้ส่วนกลาง/หน่วยงาน ---
            $recipient_group = cleanInput($_POST['recipient_group'] ?? '');
            if (empty($recipient_group)) { throw new Exception("กรุณาระบุหน่วยงานหรือกลุ่มเป้าหมาย"); }
            
            $receiver_name = cleanInput($_POST['receiver_name_gen'] ?? '');
            
            $final_note .= "แจกส่วนกลาง: $recipient_group";
            if ($receiver_name) {
                $final_note .= " (ผู้เบิก: $receiver_name)";
            }
        }
        
        // ต่อท้ายด้วยหมายเหตุเพิ่มเติมจากผู้ใช้
        if ($user_note) {
            $final_note .= " - หมายเหตุ: $user_note";
        }

        // 5. ตัดสต็อก (Update Inventory)
        $update = $pdo->prepare("UPDATE inventory SET quantity = quantity - ?, last_updated = NOW() WHERE id = ?");
        $update->execute([$quantity, $inventory_id]);

        // 6. บันทึก Transaction (Master Log)
        // บันทึก Note ที่รวมข้อมูล Ref No, ผู้รับ, ผู้เบิก ไว้ที่นี่
        $log_inv = $pdo->prepare("INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, user_id, note) 
                                  VALUES (?, 'out', ?, ?, ?)");
        $log_inv->execute([$inventory_id, $quantity, $user_id, $final_note]);

        $pdo->commit();
        
        $_SESSION['success'] = "บันทึกการแจกจ่าย '$item_name' จำนวน $quantity {$item['unit']} สำเร็จ";
        header("Location: distribution_history.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        header("Location: distribution_manager.php");
        exit();
    }
}
?>