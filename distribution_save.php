<?php
// distribution_save.php
// ระบบประมวลผลการบันทึกสต็อกและแจกจ่าย (Backend Logic)
// Refactored: รองรับ Add Stock, Distribute และจัดการ Error Schema Database

require_once 'config/db.php';
require_once 'includes/functions.php';

// [CRITICAL FIX] Map Database Object to PDO
// ดึง PDO object จาก Class Database (ที่สร้างใน config/db.php) มาใส่ตัวแปร $pdo
try {
    $pdo = $db->pdo;
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

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

// Default Redirect URL
$redirect_url = "distribution_manager.php?shelter_id=" . ($shelter_id ? $shelter_id : '');

try {
    if (!$shelter_id) throw new Exception("ไม่ระบุศูนย์พักพิง (Shelter ID missing)");

    // เริ่ม Transaction
    $pdo->beginTransaction();

    // ---------------------------------------------------------
    // CASE A: Add Stock (รับบริจาค / เพิ่มของ)
    // ---------------------------------------------------------
    if ($action === 'add_stock') {
        // Sanitize Input (ถ้าไม่มี function cleanInput ให้ใช้ trim/htmlspecialchars)
        $item_name = function_exists('cleanInput') ? cleanInput($_POST['item_name']) : trim($_POST['item_name']);
        $category  = function_exists('cleanInput') ? cleanInput($_POST['category'] ?? 'general') : trim($_POST['category'] ?? 'general');
        $unit      = function_exists('cleanInput') ? cleanInput($_POST['unit']) : trim($_POST['unit']);
        $unit      = $unit ?: 'ชิ้น';
        $quantity  = (int)$_POST['quantity'];
        $source    = function_exists('cleanInput') ? cleanInput($_POST['source']) : trim($_POST['source']);

        if (empty($item_name)) throw new Exception("กรุณาระบุชื่อสิ่งของ");
        if ($quantity <= 0) throw new Exception("จำนวนต้องมากกว่า 0");

        // 1. Check existing item in this shelter
        $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE shelter_id = :sid AND item_name = :name");
        $stmt->execute([':sid' => $shelter_id, ':name' => $item_name]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update Existing Item
            $inv_id = $existing['id'];
            $new_qty = $existing['quantity'] + $quantity;
            // ใช้ prepared statement อัปเดต
            $pdo->prepare("UPDATE inventory SET quantity = ?, last_updated = NOW() WHERE id = ?")
                ->execute([$new_qty, $inv_id]);
        } else {
            // Insert New Item
            // ใช้ try-catch ย่อย เพื่อดักจับกรณีตาราง inventory ยังไม่มี column 'category'
            try {
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
            } catch (PDOException $e) {
                // Fallback: ถ้า Error เพราะไม่มี column category ให้ลอง Insert แบบไม่มี category
                if (strpos($e->getMessage(), "Unknown column 'category'") !== false) {
                    $sql = "INSERT INTO inventory (shelter_id, item_name, quantity, unit, last_updated) 
                            VALUES (:sid, :name, :qty, :unit, NOW())";
                    $stmtInsert = $pdo->prepare($sql);
                    $stmtInsert->execute([
                        ':sid' => $shelter_id, 
                        ':name' => $item_name, 
                        ':qty' => $quantity, 
                        ':unit' => $unit
                    ]);
                } else {
                    throw $e; // ถ้าเป็น error อื่นให้โยนต่อไป
                }
            }
            $inv_id = $pdo->lastInsertId();
        }

        // 2. Log Transaction (รับเข้า)
        // ตรวจสอบว่ามีตาราง inventory_transactions หรือไม่ ถ้าไม่มีข้ามไป
        try {
            $stmtLog = $pdo->prepare("INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, source, user_id, created_at) VALUES (?, 'in', ?, ?, ?, NOW())");
            $stmtLog->execute([$inv_id, $quantity, $source, $user_id]);
        } catch (PDOException $e) {
            // ถ้าตาราง Log ยังไม่พร้อม ไม่ต้อง Rollback transaction หลัก (หยวนๆ ได้ เพื่อให้งานเดินต่อ)
            error_log("Inventory Log Error: " . $e->getMessage());
        }

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

        // 1. Check Stock & Lock Row (ป้องกัน Race Condition)
        $stmtCheck = $pdo->prepare("SELECT quantity, item_name FROM inventory WHERE id = ? FOR UPDATE");
        $stmtCheck->execute([$inventory_id]);
        $item = $stmtCheck->fetch();

        if (!$item) throw new Exception("ไม่พบรายการสินค้านี้ในระบบ");
        if ($item['quantity'] < $quantity) throw new Exception("สินค้าไม่พอจ่าย (เหลือ {$item['quantity']})");

        // 2. Deduct Stock
        $new_qty = $item['quantity'] - $quantity;
        $pdo->prepare("UPDATE inventory SET quantity = ?, last_updated = NOW() WHERE id = ?")
            ->execute([$new_qty, $inventory_id]);

        // 3. Log Transaction (ตัดสต็อก)
        try {
            $stmtLog = $pdo->prepare("INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity, source, user_id, created_at) VALUES (?, 'out', ?, 'Distribution', ?, NOW())");
            $stmtLog->execute([$inventory_id, $quantity, $user_id]);
        } catch (PDOException $e) { error_log("Trans Log Error: " . $e->getMessage()); }

        // 4. Log Distribution (บันทึกว่าแจกให้ใคร)
        // ใช้ตาราง distributions (ตาม Schema เดิม) หรือ distribution_logs (ตามโค้ดใหม่)
        // เพื่อความชัวร์ ลอง Insert ลง distributions ก่อน (Schema มาตรฐานที่เราคุยกันตอนแรก)
        try {
            // ลองใช้ Table Name 'distributions' ก่อน
            $sqlDist = "INSERT INTO distributions (evacuee_id, inventory_id, quantity, user_id, created_at, shelter_id) VALUES (?, ?, ?, ?, NOW(), ?)";
            $stmtDist = $pdo->prepare($sqlDist);
            $stmtDist->execute([$evacuee_id, $inventory_id, $quantity, $user_id, $shelter_id]);
        } catch (PDOException $e) {
            // ถ้า Error อาจจะเป็นเพราะใช้ชื่อตาราง distribution_logs หรือไม่มี column shelter_id
            if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
                 // Fallback to distribution_logs
                 $sqlDist = "INSERT INTO distribution_logs (evacuee_id, inventory_id, quantity, user_id, created_at) VALUES (?, ?, ?, ?, NOW())";
                 $stmtDist = $pdo->prepare($sqlDist);
                 $stmtDist->execute([$evacuee_id, $inventory_id, $quantity, $user_id]);
            } else {
                throw $e;
            }
        }

        $logAction = "Distribute: {$item['item_name']} (-$quantity)";
    } 
    else {
        throw new Exception("Unknown Action");
    }

    // System Log (Activity Log)
    if (function_exists('logActivity')) {
        // logActivity($pdo, $user_id, $action, $logAction);
    }

    // Commit Transaction
    $pdo->commit();
    $_SESSION['success'] = "บันทึกข้อมูลเรียบร้อยแล้ว"; // ใช้ key success เพื่อให้แสดงผลใน distribution_manager.php

} catch (Exception $e) {
    // Rollback หากเกิดข้อผิดพลาด
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMsg = $e->getMessage();
    
    // แปลง Error Database ให้เข้าใจง่ายสำหรับ User/Admin
    if (strpos($errorMsg, "Unknown column 'category'") !== false) {
        $errorMsg = "Database Error: ตาราง inventory ขาดคอลัมน์ 'category' กรุณาแจ้ง Admin";
    }
    elseif (strpos($errorMsg, "Unknown column 'inventory_id'") !== false) {
        $errorMsg = "Database Error: ตาราง log ขาดคอลัมน์เชื่อมโยง inventory_id กรุณาแจ้ง Admin";
    }
    
    error_log("Distribution Save Error: " . $errorMsg);
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $errorMsg; // ใช้ key error
}

// Redirect back
header("Location: " . $redirect_url);
exit();
?>