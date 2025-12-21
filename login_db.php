<?php
// login_db.php
session_start();
require_once 'config/db.php';

function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        header("Location: login.php");
        exit();
    }

    try {
        // ตัด first_name, last_name ออกจาก SQL
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            // ใช้ Username แทนชื่อจริง
            $_SESSION['fullname'] = $user['username']; 
            $_SESSION['login_time'] = time();

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = "เกิดข้อผิดพลาดทางเทคนิค";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>