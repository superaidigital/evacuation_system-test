<?php
// public_dashboard.php
require_once 'config/db.php';

// 1. ดึงเหตุการณ์ปัจจุบัน (Active)
$stmt_inc = $pdo->query("SELECT * FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$current_incident = $stmt_inc->fetch();

$incident_id = $current_incident ? $current_incident['id'] : 0;
$incident_name = $current_incident ? $current_incident['name'] : 'ไม่มีสถานการณ์ภัยพิบัติในขณะนี้';

$stats = [
    'total_evacuees' => 0,
    'total_shelters' => 0,
    'last_updated' => date('d/m/Y H:i')
];

$shelters = [];

if ($incident_id) {
    // สถิติรวม
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE incident_id = ? AND check_out_date IS NULL");
    $stmt->execute([$incident_id]);
    $stats['total_evacuees'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ?");
    $stmt->execute([$incident_id]);
    $stats['total_shelters'] = $stmt->fetchColumn();

    // รายชื่อศูนย์และความหนาแน่น
    $sql_shelters = "SELECT s.*, 
                    (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_occupancy 
                    FROM shelters s WHERE s.incident_id = ? ORDER BY s.district, s.name";
    $stmt = $pdo->prepare($sql_shelters);
    $stmt->execute([$incident_id]);
    $shelters = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสถานการณ์ศูนย์พักพิง - Public Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%);
            color: white;
            padding: 60px 0;
            text-align: center;
        }
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-light">

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">ศูนย์ข้อมูลผู้ประสบภัย</h1>
            <h3>สถานการณ์: <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($incident_name); ?></span></h3>
            <p class="lead mt-3">ข้อมูล Real-time อัพเดทล่าสุด: <?php echo $stats['last_updated']; ?></p>
        </div>
    </div>

    <div class="container mt-n5" style="margin-top: -40px;">
        <!-- Stats Row -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-4 mb-3">
                <div class="card stat-card bg-white h-100 py-4">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2"><i class="fas fa-bed fa-3x"></i></div>
                        <h2 class="display-4 fw-bold text-dark"><?php echo number_format($stats['total_evacuees']); ?></h2>
                        <h5 class="text-secondary">ผู้พักพิงปัจจุบัน (คน)</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card bg-white h-100 py-4">
                    <div class="card-body text-center">
                        <div class="text-success mb-2"><i class="fas fa-campground fa-3x"></i></div>
                        <h2 class="display-4 fw-bold text-dark"><?php echo number_format($stats['total_shelters']); ?></h2>
                        <h5 class="text-secondary">ศูนย์ที่เปิดให้บริการ (แห่ง)</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shelters List -->
        <?php if ($incident_id && count($shelters) > 0): ?>
            <h3 class="mb-4 border-start border-5 border-primary ps-3">รายชื่อศูนย์พักพิงที่เปิดให้บริการ</h3>
            <div class="row">
                <?php foreach ($shelters as $s): ?>
                    <?php 
                        // คำนวณเปอร์เซ็นต์ความจุ
                        $percent = ($s['capacity'] > 0) ? ($s['current_occupancy'] / $s['capacity']) * 100 : 0;
                        $bg_color = ($percent >= 90) ? 'bg-danger' : (($percent >= 70) ? 'bg-warning' : 'bg-success');
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card shadow-sm h-100 border-0">
                            <div class="card-body">
                                <h5 class="card-title fw-bold text-primary"><?php echo htmlspecialchars($s['name']); ?></h5>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-map-marker-alt"></i> ต.<?php echo $s['subdistrict']; ?> อ.<?php echo $s['district']; ?>
                                </p>
                                <p class="mb-1"><strong>เบอร์ติดต่อ:</strong> <?php echo $s['phone'] ? $s['phone'] : '-'; ?></p>
                                
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>ความหนาแน่น</small>
                                        <small class="fw-bold"><?php echo number_format($s['current_occupancy']); ?> / <?php echo number_format($s['capacity']); ?></small>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar <?php echo $bg_color; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                    <small class="text-<?php echo ($percent >= 100) ? 'danger' : 'success'; ?> mt-1 d-block">
                                        <?php echo ($percent >= 100) ? 'เต็มแล้ว' : 'ว่าง'; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-center pb-3">
                                <a href="https://maps.google.com/?q=<?php echo urlencode($s['name'] . ' ' . $s['district'] . ' ' . $s['province']); ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-location-arrow"></i> นำทาง
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary text-center">
                ยังไม่มีข้อมูลศูนย์พักพิงในขณะนี้
            </div>
        <?php endif; ?>

        <div class="text-center mt-5 mb-5 text-muted">
            <small>ระบบบริหารจัดการศูนย์พักพิงชั่วคราว &copy; <?php echo date('Y'); ?></small><br>
            <a href="login.php" class="text-decoration-none text-muted"><i class="fas fa-lock"></i> เจ้าหน้าที่เข้าสู่ระบบ</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>