<?php
// distribution_save.php
// ระบบประมวลผลการบันทึกสต็อกและแจกจ่าย (Backend Logic)
// Fixed: Added robust error handling for missing DB columns

require_once 'config/db.php';
require_once 'includes/functions.php';

// 1. Security Check
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: distribution_manager.php");
    exit();
}

// 2. CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (function_exists('validateCSRFToken')) {
    validateCSRFToken($csrf_token);
}

// 3. Prepare Variables
$action = $_POST['action'] ?? '';
$shelter_id = filter_input(INPUT_POST, 'shelter_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

// Default Redirect
$redirect_url = "distribution_manager.php?shelter_id=" . ($shelter_id ? $shelter_id : '');

try {
    if (!$shelter_id) throw new Exception("ไม่ระบุศูนย์พักพิง (Shelter ID missing)");

    $pdo->beginTransaction();

    // ---------------------------------------------------------
    // CASE A: Add Stock (รับบริจาค / เพิ่มของ)
    // ---------------------------------------------------------
    if ($action === 'add_stock') {
        $item_name = cleanInput($_POST['item_name']);
        $category  = cleanInput($_POST['category'] ?? 'general');
        $unit      = cleanInput($_POST['unit']) ?: 'ชิ้น';
        $quantity  = (int)$_POST['quantity'];
        $source    = cleanInput($_POST['source']);

        if (empty($item_name)) throw new Exception("กรุณาระบุชื่อสิ่งของ");
        if ($quantity <= 0) throw new Exception("จำนวนต้องมากกว่า 0");

        // 1. Check existing item
        // Note: Using named parameters for safety
        $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE shelter_id = :sid AND item_name = :name");
        $stmt->execute([':sid' => $shelter_id, ':name' => $item_name]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $inv_id = $existing['id'];
            $new_qty = $existing['quantity'] + $quantity;
            $pdo->prepare("UPDATE inventory SET quantity = ?, last_updated = NOW() WHERE id = ?")
                ->execute([$new_qty, $inv_id]);
        } else {
            // Insert New
            // Note: If 'category' column missing, this will throw PDOException caught below
            $sql = "INSERT INTO inventory (shelter_id, item_name, category, quantity, unit, last_updated) 
                    VALUES (:sid, :name, :cat, :qty, :unit, NOW())";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([
                ':sid' => $shelter_id, 
                ':name' => $item_name, 
                ':cat' => $category, 
                ':qty' => $quantity, 
                ':unit' => $unit
            ]);
            $inv_id = $pdo->lastInsertId();
        }

        // 2. Log Transaction
        $stmtLog = $pdo->prepare("INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, source, user_id, created_at) VALUES (?, 'in', ?, ?, ?, NOW())");
        $stmtLog->execute([$inv_id, $quantity, $source, $user_id]);

        $logAction = "Add Stock: $item_name (+$quantity)";
    } 
    
    // ---------------------------------------------------------
    // CASE B: Distribute (แจกจ่าย)
    // ---------------------------------------------------------
    elseif ($action === 'distribute') {
        $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_VALIDATE_INT);
        $evacuee_id   = filter_input(INPUT_POST, 'evacuee_id', FILTER_VALIDATE_INT);
        $quantity     = (int)$_POST['quantity'];

        if (!$inventory_id) throw new Exception("ไม่พบรหัสสินค้า (Inventory ID invalid)");
        if ($quantity <= 0) throw new Exception("จำนวนต้องมากกว่า 0");

        // 1. Check Stock (Lock Row)
        $stmtCheck = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE id = ? FOR UPDATE");
        $stmtCheck->execute([$inventory_id]);
        $item = $stmtCheck->fetch();

        if (!$item) throw new Exception("ไม่พบรายการสินค้านี้ในระบบ");
        if ($item['quantity'] < $quantity) throw new Exception("สินค้าไม่พอจ่าย (เหลือ {$item['quantity']})");

        // 2. Deduct Stock
        $new_qty = $item['quantity'] - $quantity;
        $pdo->prepare("UPDATE inventory SET quantity = ?, last_updated = NOW() WHERE id = ?")
            ->execute([$new_qty, $inventory_id]);

        // 3. Log Transaction
        $stmtLog = $pdo->prepare("INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, source, user_id, created_at) VALUES (?, 'out', ?, 'Distribution', ?, NOW())");
        $stmtLog->execute([$inventory_id, $quantity, $user_id]);

        // 4. Log Distribution (To Evacuee)
        // Note: If 'inventory_id' column missing in distribution_logs, this throws Exception
        $sqlDist = "INSERT INTO distribution_logs (evacuee_id, inventory_id, quantity, user_id, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmtDist = $pdo->prepare($sqlDist);
        $stmtDist->execute([$evacuee_id, $inventory_id, $quantity, $user_id]);

        $logAction = "Distribute: {$item['item_name']} (-$quantity)";
    } 
    else {
        throw new Exception("Unknown Action");
    }

    // System Log
    if (function_exists('logActivity')) {
        logActivity($pdo, $user_id, $action, $logAction);
    }

    $pdo->commit();
    $_SESSION['swal_success'] = "บันทึกข้อมูลเรียบร้อยแล้ว";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMsg = $e->getMessage();
    
    // แปลง Error Database ให้เข้าใจง่าย
    if (strpos($errorMsg, "Unknown column 'category'") !== false) {
        $errorMsg = "ระบบฐานข้อมูลยังไม่รองรับ 'หมวดหมู่' (Missing category column). กรุณาแจ้ง Admin ให้รัน SQL Update.";
    }
    elseif (strpos($errorMsg, "Unknown column 'inventory_id'") !== false) {
        $errorMsg = "ระบบฐานข้อมูลยังไม่รองรับการเชื่อมโยงสินค้า (Missing inventory_id). กรุณาแจ้ง Admin ให้รัน SQL Update.";
    }
    
    error_log("Distribution Save Error: " . $errorMsg);
    $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $errorMsg;
}

// Redirect back
header("Location: " . $redirect_url);
exit();
?>