<?php
// report_shelter.php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- Authorization Logic ---
$user_role = $_SESSION['role'];
$my_shelter_id = isset($_SESSION['shelter_id']) ? $_SESSION['shelter_id'] : null;

// Get Target Shelter ID
if ($user_role == 'staff' || $user_role == 'volunteer') {
    // Staff: บังคับดูเฉพาะของตัวเอง
    if (!$my_shelter_id) {
        die("คุณยังไม่มีศูนย์พักพิงในสังกัด");
    }
    $shelter_id = $my_shelter_id;
} else {
    // Admin: รับค่าจาก GET หรือ Default
    $shelter_id = isset($_GET['id']) ? $_GET['id'] : '';
    if (!$shelter_id) {
        // ถ้าไม่ระบุ ให้หาอันแรกสุด
        $stmt = $pdo->query("SELECT id FROM shelters ORDER BY id ASC LIMIT 1");
        $shelter_id = $stmt->fetchColumn();
    }
}

if (!$shelter_id) {
    die("ไม่พบข้อมูลศูนย์พักพิง");
}

// 1. ดึงข้อมูลศูนย์
$sql = "SELECT s.*, i.name as incident_name FROM shelters s JOIN incidents i ON s.incident_id = i.id WHERE s.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$shelter_id]);
$shelter = $stmt->fetch();

if (!$shelter) { die("Shelter not found."); }

// 2. สถิติภาพรวม
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN check_out_date IS NULL THEN 1 ELSE 0 END) as staying,
    SUM(CASE WHEN check_out_date IS NOT NULL THEN 1 ELSE 0 END) as checked_out,
    SUM(CASE WHEN check_out_date IS NULL AND gender='male' THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN check_out_date IS NULL AND gender='female' THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN check_out_date IS NULL AND age < 15 THEN 1 ELSE 0 END) as kids,
    SUM(CASE WHEN check_out_date IS NULL AND age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN check_out_date IS NULL AND (health_condition IS NOT NULL AND health_condition != '') THEN 1 ELSE 0 END) as sick
    FROM evacuees WHERE shelter_id = ?";
$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute([$shelter_id]);
$stats = $stmt_stats->fetch();

// 3. ข้อมูลกราฟ (ช่วงอายุ)
$sql_age = "SELECT 
    CASE 
        WHEN age < 15 THEN 'เด็ก (<15)'
        WHEN age BETWEEN 15 AND 59 THEN 'ผู้ใหญ่ (15-59)'
        ELSE 'ผู้สูงอายุ (60+)'
    END as age_group,
    COUNT(*) as count
    FROM evacuees 
    WHERE shelter_id = ? AND check_out_date IS NULL
    GROUP BY age_group";
$stmt_age = $pdo->prepare($sql_age);
$stmt_age->execute([$shelter_id]);
$age_data = $stmt_age->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. ข้อมูลกราฟ (ประชากร 7 วันย้อนหลัง)
$sql_trend = "SELECT DATE(created_at) as date, COUNT(*) as count 
              FROM evacuees 
              WHERE shelter_id = ? AND created_at >= DATE(NOW()) - INTERVAL 7 DAY
              GROUP BY DATE(created_at) ORDER BY date ASC";
$stmt_trend = $pdo->prepare($sql_trend);
$stmt_trend->execute([$shelter_id]);
$trend_data = $stmt_trend->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 20px; }
        .stat-card { border: none; border-radius: 10px; padding: 20px; text-align: center; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bg-blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .bg-green { background: linear-gradient(135deg, #10b981, #059669); }
        .bg-orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .bg-purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="report-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="fas fa-chart-pie me-2"></i>รายงานสรุปข้อมูลศูนย์พักพิง</h4>
            <div class="text-primary fw-bold fs-5"><?php echo htmlspecialchars($shelter['name']); ?></div>
            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($shelter['location']); ?></small>
        </div>
        
        <div class="d-flex gap-2">
            <!-- Dropdown เปลี่ยนศูนย์ (เฉพาะ Admin) -->
            <?php if ($user_role == 'admin'): 
                $all_shelters = $pdo->query("SELECT id, name FROM shelters ORDER BY name ASC")->fetchAll();
            ?>
            <form action="" method="GET">
                <select name="id" class="form-select form-select-sm fw-bold" onchange="this.form.submit()">
                    <?php foreach ($all_shelters as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $shelter_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <a href="report_print.php?id=<?php echo $shelter_id; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-print me-1"></i> พิมพ์
            </a>
            <a href="report_export.php?id=<?php echo $shelter_id; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-file-excel me-1"></i> Export
            </a>
        </div>
    </div>
</div>

<div class="container-fluid p-4">
    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card bg-blue">
                <h2 class="mb-0 fw-bold"><?php echo number_format($stats['staying']); ?></h2>
                <div class="opacity-75">ผู้พักอาศัยปัจจุบัน</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-green">
                <h2 class="mb-0 fw-bold"><?php echo number_format($shelter['capacity'] - $stats['staying']); ?></h2>
                <div class="opacity-75">ที่ว่างคงเหลือ</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-orange">
                <h2 class="mb-0 fw-bold"><?php echo number_format($stats['checked_out']); ?></h2>
                <div class="opacity-75">จำหน่ายออกแล้ว</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-purple">
                <h2 class="mb-0 fw-bold"><?php echo number_format($stats['total']); ?></h2>
                <div class="opacity-75">ยอดสะสมทั้งหมด</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Chart: Demographics -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">ข้อมูลประชากร (ปัจจุบัน)</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-male text-primary me-2"></i>ชาย</span>
                            <span class="fw-bold"><?php echo number_format($stats['male']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-female text-danger me-2"></i>หญิง</span>
                            <span class="fw-bold"><?php echo number_format($stats['female']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-child text-warning me-2"></i>เด็ก (<15)</span>
                            <span class="fw-bold"><?php echo number_format($stats['kids']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-user-clock text-secondary me-2"></i>ผู้สูงอายุ (60+)</span>
                            <span class="fw-bold"><?php echo number_format($stats['elderly']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between bg-danger bg-opacity-10">
                            <span class="text-danger"><i class="fas fa-procedures me-2"></i>ผู้ป่วย/โรคประจำตัว</span>
                            <span class="fw-bold text-danger"><?php echo number_format($stats['sick']); ?></span>
                        </li>
                    </ul>
                    <canvas id="ageChart" style="max-height: 200px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart: Trend -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold">สถิติการรับเข้าช่วง 7 วันล่าสุด</div>
                <div class="card-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // Age Chart
    const ctxAge = document.getElementById('ageChart').getContext('2d');
    new Chart(ctxAge, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_keys($age_data)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($age_data)); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Trend Chart
    const trendData = <?php echo json_encode($trend_data); ?>;
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.date),
            datasets: [{
                label: 'จำนวนผู้ลงทะเบียนใหม่',
                data: trendData.map(d => d.count),
                borderColor: '#6366f1',
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(99, 102, 241, 0.1)'
            }]
        },
        options: { responsive: true }
    });
</script>

</body>
</html>