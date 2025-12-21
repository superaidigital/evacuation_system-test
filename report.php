<?php
// report.php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. ดึง Incident ทั้งหมดเพื่อทำตัวเลือก
$incidents = $pdo->query("SELECT * FROM incidents ORDER BY id DESC")->fetchAll();
$selected_incident = isset($_GET['incident_id']) ? $_GET['incident_id'] : ($incidents[0]['id'] ?? 1);

// 2. ดึงข้อมูลสรุป (Summary Stats) เฉพาะ Incident ที่เลือก
try {
    // จำนวนศูนย์
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ?");
    $stmt->execute([$selected_incident]);
    $total_shelters = $stmt->fetchColumn();

    // จำนวนผู้ประสบภัยทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE incident_id = ?");
    $stmt->execute([$selected_incident]);
    $total_evacuees = $stmt->fetchColumn();

    // แยกชาย/หญิง
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN prefix IN ('นาย','ด.ช.') THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN prefix IN ('นาง','นางสาว','ด.ญ.') THEN 1 ELSE 0 END) as female
        FROM evacuees WHERE incident_id = ?");
    $stmt->execute([$selected_incident]);
    $gender_stats = $stmt->fetch();

    // แยกกลุ่มเปราะบาง (ผู้สูงอายุ 60+ และเด็ก <15)
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
        SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as children
        FROM evacuees WHERE incident_id = ?");
    $stmt->execute([$selected_incident]);
    $vulnerable_stats = $stmt->fetch();

} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปสถานการณ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-pie text-primary"></i> รายงานสรุปสถานการณ์</h2>
        
        <!-- ตัวเลือกเหตุการณ์ -->
        <form action="" method="GET" class="d-flex align-items-center">
            <label class="me-2 text-nowrap">เลือกเหตุการณ์:</label>
            <select name="incident_id" class="form-select me-2" onchange="this.form.submit()">
                <?php foreach ($incidents as $inc): ?>
                    <option value="<?php echo $inc['id']; ?>" <?php echo $selected_incident == $inc['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($inc['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> พิมพ์
            </button>
        </form>
    </div>

    <div class="row g-4">
        <!-- Card: ผู้ประสบภัยรวม -->
        <div class="col-md-4">
            <div class="card h-100 border-primary border-2">
                <div class="card-body text-center">
                    <h5 class="text-secondary">ผู้ประสบภัยทั้งหมด</h5>
                    <h1 class="display-4 fw-bold text-primary"><?php echo number_format($total_evacuees); ?></h1>
                    <span class="text-muted">คน</span>
                </div>
            </div>
        </div>

        <!-- Card: ศูนย์พักพิง -->
        <div class="col-md-4">
            <div class="card h-100 border-success border-2">
                <div class="card-body text-center">
                    <h5 class="text-secondary">ศูนย์พักพิงที่เปิด</h5>
                    <h1 class="display-4 fw-bold text-success"><?php echo number_format($total_shelters); ?></h1>
                    <span class="text-muted">แห่ง</span>
                </div>
            </div>
        </div>

        <!-- Card: เพศ -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">สัดส่วนเพศ</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                        <span><i class="fas fa-male text-primary fa-lg"></i> ชาย</span>
                        <span class="fw-bold"><?php echo number_format($gender_stats['male'] ?? 0); ?> คน</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="fas fa-female text-danger fa-lg"></i> หญิง</span>
                        <span class="fw-bold"><?php echo number_format($gender_stats['female'] ?? 0); ?> คน</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- รายละเอียดกลุ่มเปราะบาง -->
    <div class="card mt-4 shadow-sm">
        <div class="card-header bg-warning text-dark fw-bold">
            <i class="fas fa-user-shield"></i> ข้อมูลกลุ่มเปราะบาง
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-6 border-end">
                    <h3><?php echo number_format($vulnerable_stats['elderly'] ?? 0); ?></h3>
                    <p class="text-muted">ผู้สูงอายุ (60 ปีขึ้นไป)</p>
                </div>
                <div class="col-6">
                    <h3><?php echo number_format($vulnerable_stats['children'] ?? 0); ?></h3>
                    <p class="text-muted">เด็ก (ต่ำกว่า 15 ปี)</p>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>