<?php
// login.php
session_start();
// ตรวจสอบว่าถ้า Login อยู่แล้วให้เด้งไปหน้า Dashboard เลย
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบริหารจัดการศูนย์พักพิง</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border: none;
        }
        .card-body {
            padding: 40px 30px;
            background: white;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
            background-color: white;
        }
        .btn-login {
            border-radius: 8px;
            padding: 12px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .brand-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card login-card">
                <div class="card-header">
                    <div class="brand-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4 class="mb-0 fw-bold">ระบบบริหารจัดการ<br>ศูนย์พักพิงชั่วคราว</h4>
                    <small class="opacity-75">Disaster Evacuation System</small>
                </div>
                <div class="card-body">
                    
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div>
                                <?php 
                                    echo $_SESSION['error']; 
                                    unset($_SESSION['error']);
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="login_db.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-secondary fw-bold small">ชื่อผู้ใช้งาน</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light"><i class="fas fa-user text-muted"></i></span>
                                <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Username" required autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-bold small">รหัสผ่าน</label>
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-light"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Password" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-login">
                                เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-2"></i>
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">&copy; <?php echo date("Y"); ?> Disaster Management Center</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>