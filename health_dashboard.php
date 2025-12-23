<?php
// health_dashboard.php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// Query Stats
$sql = "SELECT triage_level, COUNT(*) as count FROM evacuees WHERE check_out_date IS NULL GROUP BY triage_level";
$stats = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

$red = $stats['red'] ?? 0;
$yellow = $stats['yellow'] ?? 0;
$green = $stats['green'] ?? 0;

// Get Red List (Critical Patients)
$sqlRed = "SELECT e.*, s.name as shelter_name FROM evacuees e JOIN shelters s ON e.shelter_id = s.id WHERE e.triage_level = 'red' AND e.check_out_date IS NULL";
$redList = $pdo->query($sqlRed)->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>Health Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
    <h3 class="mb-4"><i class="fas fa-user-md text-primary me-2"></i>สถานการณ์สุขภาพผู้ประสบภัย</h3>
    
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-danger text-white h-100">
                <div class="card-body text-center">
                    <h1><?php echo $red; ?></h1>
                    <div>วิกฤต (สีแดง)</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body text-center">
                    <h1><?php echo $yellow; ?></h1>
                    <div>เฝ้าระวัง (สีเหลือง)</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h1><?php echo $green; ?></h1>
                    <div>ทั่วไป (สีเขียว)</div>
                </div>
            </div>
        </div>
    </div>

    <?php if(count($redList) > 0): ?>
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-ambulance me-2"></i>รายชื่อผู้ป่วยวิกฤต (ต้องดูแลด่วน)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ชื่อ-สกุล</th>
                        <th>อาการ</th>
                        <th>ศูนย์พักพิง</th>
                        <th>เบอร์โทร</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($redList as $p): ?>
                    <tr>
                        <td class="fw-bold text-danger"><?php echo $p['first_name'].' '.$p['last_name']; ?></td>
                        <td><?php echo $p['medical_condition']; ?></td>
                        <td><?php echo $p['shelter_name']; ?></td>
                        <td><?php echo $p['phone']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>