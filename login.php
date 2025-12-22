<?php
// login.php
session_start();
require_once 'config/db.php';

// ถ้าล็อกอินอยู่แล้ว ให้ไปหน้า Dashboard ตามสิทธิ์
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: index.php");
    } else {
        header("Location: shelter_list.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        // ดึงข้อมูล users รวม shelter_id
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login สำเร็จ
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // [สำคัญ] เก็บ shelter_id เข้า Session (ถ้ามี)
            $_SESSION['shelter_id'] = isset($user['shelter_id']) ? $user['shelter_id'] : null;

            // Log การเข้าสู่ระบบ
            $ip = $_SERVER['REMOTE_ADDR'];
            $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, 'Login', 'เข้าสู่ระบบสำเร็จ', ?)");
            $logStmt->execute([$user['id'], $ip]);

            // Redirect ตามสิทธิ์
            if ($user['role'] == 'admin') {
                header("Location: index.php");
            } else {
                // Staff/Volunteer ไปหน้าศูนย์พักพิงเลย
                header("Location: shelter_list.php");
            }
            exit();
        } else {
            $error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
        }
    } catch (PDOException $e) {
        $error = "System Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบริหารจัดการศูนย์พักพิง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            background: #0f172a;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .btn-login {
            background: #fbbf24;
            color: #0f172a;
            font-weight: bold;
            border: none;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #f59e0b;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <i class="fas fa-hands-helping fa-3x mb-3"></i>
        <h4 class="mb-0 fw-bold">ระบบบริหารจัดการศูนย์พักพิง</h4>
        <small class="opacity-75">Disaster Evacuation Management System</small>
    </div>
    <div class="p-4">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger text-center py-2 mb-3 small">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold text-secondary">ชื่อผู้ใช้งาน</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control border-start-0 ps-0" required placeholder="Username" autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold text-secondary">รหัสผ่าน</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 ps-0" required placeholder="Password">
                </div>
            </div>
            <button type="submit" class="btn btn-login w-100 py-2 rounded-pill shadow-sm">
                เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-1"></i>
            </button>
        </form>
    </div>
    <div class="bg-light text-center py-3 text-muted small border-top">
        &copy; <?php echo date('Y'); ?> Local Government Organization
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>