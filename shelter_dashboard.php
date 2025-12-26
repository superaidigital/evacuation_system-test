<?php
// shelter_dashboard.php
// แสดงภาพรวมข้อมูลเฉพาะของศูนย์พักพิงที่เลือก (Real-time Dashboard)

require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบ Session
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) { 
    header("Location: login.php"); 
    exit(); 
}

// 1. รับค่า Shelter ID
$shelter_id = isset($_GET['shelter_id']) ? intval($_GET['shelter_id']) : 0;

if ($shelter_id <= 0) {
    $_SESSION['error'] = "ไม่พบรหัสศูนย์พักพิง";
    header("Location: shelter_list.php");
    exit();
}

// 2. ดึงข้อมูลศูนย์พักพิง (Shelter Info)
$sql_shelter = "SELECT * FROM shelters WHERE id = ?";
$stmt = $conn->prepare($sql_shelter);
$stmt->bind_param("i", $shelter_id);
$stmt->execute();
$res_shelter = $stmt->get_result();
$shelter = $res_shelter->fetch_assoc();

if (!$shelter) {
    $_SESSION['error'] = "ไม่พบข้อมูลศูนย์พักพิง";
    header("Location: shelter_list.php");
    exit();
}

// 3. คำนวณสถิติ (Statistics)
// 3.1 จำนวนผู้อพยพทั้งหมด
$sql_total = "SELECT COUNT(*) as total FROM evacuees WHERE shelter_id = ?";
$stmt = $conn->prepare($sql_total);
$stmt->bind_param("i", $shelter_id);
$stmt->execute();
$total_evacuees = $stmt->get_result()->fetch_assoc()['total'];

// 3.2 แยกตามเพศ (Gender)
$sql_gender = "SELECT gender, COUNT(*) as count FROM evacuees WHERE shelter_id = ? GROUP BY gender";
$stmt = $conn->prepare($sql_gender);
$stmt->bind_param("i", $shelter_id);
$stmt->execute();
$res_gender = $stmt->get_result();
$gender_data = ['Male' => 0, 'Female' => 0, 'Other' => 0];
while($row = $res_gender->fetch_assoc()) {
    $g = ucfirst(strtolower($row['gender'])); // ปรับให้เป็นตัวพิมพ์ใหญ่ตัวแรก
    if (isset($gender_data[$g])) {
        $gender_data[$g] = $row['count'];
    } else {
        $gender_data['Other'] += $row['count'];
    }
}

// 3.3 กลุ่มเปราะบาง (Vulnerable Groups) - คำนวณจากอายุและสุขภาพ
// เด็ก (<12), ผู้สูงอายุ (>60), ผู้ป่วย (health_status != 'Normal'/'Healthy')
$sql_vul = "SELECT 
    SUM(CASE WHEN age < 12 THEN 1 ELSE 0 END) as kids,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN health_status NOT IN ('Normal', 'Healthy', 'แข็งแรง', 'ปกติ') AND health_status IS NOT NULL AND health_status != '' THEN 1 ELSE 0 END) as sick
    FROM evacuees WHERE shelter_id = ?";
$stmt = $conn->prepare($sql_vul);
$stmt->bind_param("i", $shelter_id);
$stmt->execute();
$vul_stats = $stmt->get_result()->fetch_assoc();

// คำนวณเปอร์เซ็นต์ความจุ
$capacity = $shelter['capacity'] > 0 ? $shelter['capacity'] : 1;
$percent_full = ($total_evacuees / $capacity) * 100;

// กำหนดสีสถานะ
$status_color = 'success';
if ($shelter['status'] == 'Full') $status_color = 'warning';
if ($shelter['status'] == 'Closed') $status_color = 'danger';

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard: <?php echo htmlspecialchars($shelter['name']); ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; background-color: #f4f6f9; }
        .card-dashboard { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); height: 100%; }
        .icon-box { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .bg-gradient-primary { background: linear-gradient(45deg, #0d6efd, #0a58ca); }
        .bg-gradient-success { background: linear-gradient(45deg, #198754, #157347); }
        .bg-gradient-warning { background: linear-gradient(45deg, #ffc107, #ffca2c); color: #000; }
        .bg-gradient-info { background: linear-gradient(45deg, #0dcaf0, #3dd5f3); color: #000; }
    </style>
</head>
<body>

<?php include('includes/header.php'); ?>

<div class="container mt-4 mb-5">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="text-muted mb-0">Dashboard ภาพรวม</h5>
            <h2 class="fw-bold text-primary mb-0">
                <i class="fas fa-campground me-2"></i> <?php echo htmlspecialchars($shelter['name']); ?>
            </h2>
            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($shelter['location']); ?></small>
        </div>
        <div>
            <a href="shelter_form.php?edit=<?php echo $shelter['id']; ?>" class="btn btn-outline-warning me-2">
                <i class="fas fa-edit me-1"></i> แก้ไขข้อมูล
            </a>
            <a href="shelter_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้ารายการ
            </a>
        </div>
    </div>

    <!-- Top Stats Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. ผู้อพยพปัจจุบัน -->
        <div class="col-md-3">
            <div class="card card-dashboard p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-muted small">ผู้อพยพปัจจุบัน</p>
                        <h4 class="mb-0 fw-bold"><?php echo number_format($total_evacuees); ?> <small class="fs-6 text-muted">คน</small></h4>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 2. ความจุ -->
        <div class="col-md-3">
            <div class="card card-dashboard p-3">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-box bg-success bg-opacity-10 text-success me-3">
                        <i class="fas fa-bed"></i>
                    </div>
                    <div class="flex-grow-1">
                        <p class="mb-0 text-muted small">ความหนาแน่น</p>
                        <div class="d-flex justify-content-between align-items-end">
                            <h4 class="mb-0 fw-bold"><?php echo number_format($percent_full, 1); ?>%</h4>
                            <small class="text-muted"><?php echo number_format($total_evacuees) . "/" . number_format($capacity); ?></small>
                        </div>
                    </div>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-<?php echo $percent_full > 90 ? 'danger' : ($percent_full > 70 ? 'warning' : 'success'); ?>" 
                         style="width: <?php echo $percent_full; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 3. กลุ่มเปราะบางรวม -->
        <div class="col-md-3">
            <div class="card card-dashboard p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-warning bg-opacity-10 text-warning me-3">
                        <i class="fas fa-wheelchair"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-muted small">กลุ่มเปราะบาง</p>
                        <h4 class="mb-0 fw-bold">
                            <?php echo number_format($vul_stats['kids'] + $vul_stats['elderly'] + $vul_stats['sick']); ?> 
                            <small class="fs-6 text-muted">คน</small>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. สถานะ -->
        <div class="col-md-3">
            <div class="card card-dashboard p-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-<?php echo $status_color; ?> bg-opacity-10 text-<?php echo $status_color; ?> me-3">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-muted small">สถานะศูนย์</p>
                        <h4 class="mb-0 fw-bold text-<?php echo $status_color; ?>">
                            <?php echo htmlspecialchars($shelter['status']); ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Gender Chart -->
        <div class="col-md-4">
            <div class="card card-dashboard">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-venus-mars text-info me-2"></i>สัดส่วน เพศ</h6>
                </div>
                <div class="card-body">
                    <canvas id="genderChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Vulnerable Groups Chart -->
        <div class="col-md-4">
            <div class="card card-dashboard">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-procedures text-danger me-2"></i>กลุ่มเปราะบาง</h6>
                </div>
                <div class="card-body">
                    <canvas id="vulChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions / Contact Info -->
        <div class="col-md-4">
            <div class="card card-dashboard">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-address-book text-success me-2"></i>ข้อมูลติดต่อ & สั่งการ</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-user-tie text-muted me-2"></i>ผู้ดูแลศูนย์</span>
                            <span class="fw-bold"><?php echo !empty($shelter['contact_person']) ? htmlspecialchars($shelter['contact_person']) : '-'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-phone text-muted me-2"></i>เบอร์โทรศัพท์</span>
                            <span class="fw-bold"><?php echo !empty($shelter['contact_phone']) ? htmlspecialchars($shelter['contact_phone']) : '-'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-map-marked text-muted me-2"></i>พิกัด</span>
                            <span>
                                <?php if($shelter['latitude'] && $shelter['longitude']): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo $shelter['latitude']; ?>,<?php echo $shelter['longitude']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="fas fa-location-arrow"></i> นำทาง
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">ไม่ระบุ</span>
                                <?php endif; ?>
                            </span>
                        </li>
                    </ul>
                    
                    <div class="d-grid gap-2">
                        <a href="evacuee_list.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-primary">
                            <i class="fas fa-list me-1"></i> จัดการรายชื่อผู้อพยพ
                        </a>
                        <a href="request_manager.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-outline-danger">
                            <i class="fas fa-hands-helping me-1"></i> ขอความช่วยเหลือ (Request)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include('includes/footer.php'); ?>

<!-- Chart Scripts -->
<script>
    // 1. Gender Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'doughnut',
        data: {
            labels: ['ชาย', 'หญิง', 'อื่นๆ'],
            datasets: [{
                data: [<?php echo $gender_data['Male']; ?>, <?php echo $gender_data['Female']; ?>, <?php echo $gender_data['Other']; ?>],
                backgroundColor: ['#36a2eb', '#ff6384', '#ffcd56'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 2. Vulnerable Chart
    const vulCtx = document.getElementById('vulChart').getContext('2d');
    new Chart(vulCtx, {
        type: 'bar',
        data: {
            labels: ['เด็ก (<12)', 'ผู้สูงอายุ (60+)', 'ผู้ป่วย/พิการ'],
            datasets: [{
                label: 'จำนวน (คน)',
                data: [<?php echo (int)$vul_stats['kids']; ?>, <?php echo (int)$vul_stats['elderly']; ?>, <?php echo (int)$vul_stats['sick']; ?>],
                backgroundColor: ['#20c997', '#fd7e14', '#dc3545'],
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
</script>

</body>
</html>