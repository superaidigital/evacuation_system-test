<?php
// user_save.php
// Handle User Add/Edit/Delete

require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

// CSRF Check (รับทั้ง POST และ GET สำหรับ Delete)
$token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? '');
if (function_exists('validateCSRFToken')) {
    validateCSRFToken($token);
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    if ($action == 'delete') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id && $id != $_SESSION['user_id']) { // ห้ามลบตัวเอง
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $_SESSION['swal_success'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
        }
    } 
    elseif ($action == 'add' || $action == 'edit') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $username = cleanInput($_POST['username']);
        $password = $_POST['password'];
        $role = cleanInput($_POST['role']);
        $shelter_id = filter_input(INPUT_POST, 'shelter_id', FILTER_VALIDATE_INT) ?: null;

        if ($action == 'add') {
            // Check Duplicate
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                die("Error: ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว");
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, role, shelter_id, created_at) VALUES (?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute([$username, $hash, $role, $shelter_id]);
            $_SESSION['swal_success'] = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว";

        } elseif ($action == 'edit') {
            $params = [$username, $role, $shelter_id];
            $sql = "UPDATE users SET username = ?, role = ?, shelter_id = ?";
            
            if (!empty($password)) {
                $sql .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $user_id;
            
            $pdo->prepare($sql)->execute($params);
            $_SESSION['swal_success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

header("Location: user_manager.php");
exit();
?>