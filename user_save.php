<?php
// user_save.php
// ระบบบันทึกข้อมูลผู้ใช้งาน (Backend)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); exit(); 
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        // --- เพิ่มผู้ใช้ ---
        $username = cleanInput($_POST['username']);
        $password = $_POST['password']; // Raw password
        $first_name = cleanInput($_POST['first_name']);
        $last_name = cleanInput($_POST['last_name']);
        $role = cleanInput($_POST['role']);
        $shelter_id = !empty($_POST['shelter_id']) ? $_POST['shelter_id'] : null;

        // 1. Check Duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Username '$username' มีอยู่ในระบบแล้ว");
        }

        // 2. Hash Password
        if (empty($password)) { throw new Exception("กรุณาระบุรหัสผ่าน"); }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. Insert
        $sql = "INSERT INTO users (username, password, first_name, last_name, role, shelter_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $hashed_password, $first_name, $last_name, $role, $shelter_id]);

        $_SESSION['success'] = "เพิ่มผู้ใช้ '$username' เรียบร้อยแล้ว";

    } elseif ($action === 'edit') {
        // --- แก้ไขผู้ใช้ ---
        $id = (int)$_POST['id'];
        // Username มักไม่ให้แก้ (readOnly) แต่รับค่ามาเพื่อเช็ค
        $password = $_POST['password'];
        $first_name = cleanInput($_POST['first_name']);
        $last_name = cleanInput($_POST['last_name']);
        $role = cleanInput($_POST['role']);
        $shelter_id = !empty($_POST['shelter_id']) ? $_POST['shelter_id'] : null;

        // Build SQL Dynamic (เปลี่ยนรหัสผ่านเฉพาะเมื่อมีการกรอก)
        $sql = "UPDATE users SET first_name = ?, last_name = ?, role = ?, shelter_id = ? ";
        $params = [$first_name, $last_name, $role, $shelter_id];

        if (!empty($password)) {
            $sql .= ", password = ? ";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['success'] = "แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว";

    } elseif ($action === 'delete') {
        // --- ลบผู้ใช้ ---
        $id = (int)$_POST['id'];
        
        // Prevent deleting self
        if ($id == $_SESSION['user_id']) {
            throw new Exception("ไม่สามารถลบบัญชีของตนเองได้");
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['success'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
    }

} catch (Exception $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

header("Location: user_manager.php");
exit();
?>