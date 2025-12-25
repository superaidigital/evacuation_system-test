<?php
// inventory_save.php
// ระบบบันทึกข้อมูลคลังสินค้า (Backend)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'add_item') {
        // --- เพิ่มสินค้าใหม่ ---
        $shelter_id = $_POST['shelter_id'];
        $item_name = cleanInput($_POST['item_name']);
        $category = cleanInput($_POST['category']);
        $unit = cleanInput($_POST['unit']);
        $quantity = (int)$_POST['quantity'];
        $note = cleanInput($_POST['note']);

        // Insert Inventory
        $sql = "INSERT INTO inventory (shelter_id, item_name, category, unit, quantity, last_updated) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$shelter_id, $item_name, $category, $unit, $quantity]);
        $inventory_id = $pdo->lastInsertId();

        // Log Transaction (ถ้ามีการใส่จำนวนเริ่มต้น)
        if ($quantity > 0) {
            $log_sql = "INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, user_id, note) 
                        VALUES (?, 'in', ?, ?, ?)";
            $stmt_log = $pdo->prepare($log_sql);
            $note_text = "Initial Stock" . ($note ? ": $note" : "");
            $stmt_log->execute([$inventory_id, $quantity, $user_id, $note_text]);
        }

        $_SESSION['success'] = "เพิ่มสินค้า '$item_name' เรียบร้อยแล้ว";

    } elseif ($action === 'restock') {
        // --- รับของเข้า (Restock) ---
        $id = (int)$_POST['id'];
        $quantity = (int)$_POST['quantity'];
        $source = cleanInput($_POST['source']);

        if ($quantity <= 0) { throw new Exception("จำนวนต้องมากกว่า 0"); }

        // Update Inventory
        $sql = "UPDATE inventory SET quantity = quantity + ?, last_updated = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quantity, $id]);

        // Log Transaction
        $log_sql = "INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, user_id, note) 
                    VALUES (?, 'in', ?, ?, ?)";
        $stmt_log = $pdo->prepare($log_sql);
        $note_text = $source ? "รับจาก: $source" : "Restock";
        $stmt_log->execute([$id, $quantity, $user_id, $note_text]);

        $_SESSION['success'] = "บันทึกรับของเพิ่ม $quantity รายการเรียบร้อยแล้ว";

    } elseif ($action === 'edit_item') {
        // --- แก้ไขข้อมูลสินค้า ---
        $id = (int)$_POST['id'];
        $item_name = cleanInput($_POST['item_name']);
        $category = cleanInput($_POST['category']);
        $unit = cleanInput($_POST['unit']);
        $new_quantity = (int)$_POST['quantity'];

        // Get old quantity for logging manual adjustment
        $old_data = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
        $old_data->execute([$id]);
        $current_qty = $old_data->fetchColumn();

        // Update Inventory
        $sql = "UPDATE inventory SET item_name = ?, category = ?, unit = ?, quantity = ?, last_updated = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_name, $category, $unit, $new_quantity, $id]);

        // Log if quantity changed manually
        if ($new_quantity != $current_qty) {
            $diff = $new_quantity - $current_qty;
            $type = ($diff > 0) ? 'in' : 'out';
            $log_sql = "INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, user_id, note) 
                        VALUES (?, ?, ?, ?, 'Manual Correction (Edit)')";
            $stmt_log = $pdo->prepare($log_sql);
            $stmt_log->execute([$id, $type, abs($diff), $user_id]);
        }

        $_SESSION['success'] = "แก้ไขข้อมูลสินค้าเรียบร้อยแล้ว";
    }

} catch (Exception $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

header("Location: inventory_list.php");
exit();
?>