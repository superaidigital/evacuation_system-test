<?php
// monitor_dashboard.php
// ศูนย์ปฏิบัติการ (War Room) - เพิ่มข้อมูลสุขภาพ + ระบบกรองข้อมูล (Filter)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// --- 1. Filter Logic (ระบบกรอง) ---
// ถ้าเป็น Admin เลือกกรองได้, ถ้าเป็น Staff บังคับศูนย์ตัวเอง, ถ้าไม่เลือก = ดูภาพรวม
$filter_shelter_id = null;

if ($role === 'admin') {
    if (isset($_GET['shelter_id']) && $_GET['shelter_id'] != '') {
        $filter_shelter_id = (int)$_GET['shelter_id'];
    }
} else {
    // Staff/Donation Officer เห็นแค่ศูนย์ตัวเอง (ถ้ามี)
    if ($my_shelter_id) {
        $filter_shelter_id = $my_shelter_id;
    }
}

// สร้าง Condition สำหรับ SQL
$shelter_cond = $filter_shelter_id ? " AND shelter_id = $filter_shelter_id " : "";
// สำหรับตารางที่มี prefix ต่างกัน
$evac_cond    = $filter_shelter_id ? " WHERE shelter_id = $filter_shelter_id " : ""; 
$shelter_cond_s = $filter_shelter_id ? " AND s.id = $filter_shelter_id " : ""; 

// --- 2. Statistics Calculation ---

// 2.1 Evacuee Stats (Population)
$sql_ev = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN gender='male' THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN gender='female' THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as children,
    SUM(CASE WHEN age BETWEEN 15 AND 59 THEN 1 ELSE 0 END) as adults
FROM evacuees $evac_cond"; // Apply Filter
$evac_stats = $pdo->query($sql_ev)->fetch(PDO::FETCH_ASSOC);

// 2.2 Shelter Capacity
// ถ้าเลือกศูนย์เดียว total_shelters จะเป็น 1
$sql_sh = "SELECT 
    COUNT(s.id) as total_shelters,
    SUM(s.capacity) as total_capacity,
    (SELECT COUNT(*) FROM evacuees e WHERE 1=1 " . ($filter_shelter_id ? "AND e.shelter_id = $filter_shelter_id" : "") . ") as total_occupied
FROM shelters s WHERE s.status = 'open' $shelter_cond_s";
$shelter_stats = $pdo->query($sql_sh)->fetch(PDO::FETCH_ASSOC);

$global_cap_percent = 0;
if ($shelter_stats['total_capacity'] > 0) {
    $global_cap_percent = ($shelter_stats['total_occupied'] / $shelter_stats['total_capacity']) * 100;
}

// 2.3 Health & Vulnerable Stats (กู้คืนข้อมูลสุขภาพ)
$health_stats = ['chronic' => 0, 'pregnant' => 0, 'disabled' => 0, 'bedridden' => 0];
try {
    // ต้อง Join กับ evacuees เพื่อกรองตาม shelter_id
    $sql_health = "SELECT 
        SUM(CASE WHEN n.description LIKE '%เบาหวาน%' OR n.description LIKE '%ความดัน%' OR n.description LIKE '%หัวใจ%' THEN 1 ELSE 0 END) as chronic,
        SUM(CASE WHEN n.description LIKE '%ครรภ์%' OR n.description LIKE '%ท้อง%' THEN 1 ELSE 0 END) as pregnant,
        SUM(CASE WHEN n.description LIKE '%พิการ%' THEN 1 ELSE 0 END) as disabled,
        SUM(CASE WHEN n.description LIKE '%ติดเตียง%' THEN 1 ELSE 0 END) as bedridden
    FROM evacuee_needs n
    JOIN evacuees e ON n.evacuee_id = e.id
    WHERE n.status = 'pending' " . ($filter_shelter_id ? "AND e.shelter_id = $filter_shelter_id" : "");
    
    $res_h = $pdo->query($sql_health)->fetch(PDO::FETCH_ASSOC);
    if ($res_h) {
        $health_stats['chronic'] = (int)$res_h['chronic'];
        $health_stats['pregnant'] = (int)$res_h['pregnant'];
        $health_stats['disabled'] = (int)$res_h['disabled'];
        $health_stats['bedridden'] = (int)$res_h['bedridden'];
    }
} catch (Exception $e) { /* Ignore if table missing */ }

// 2.4 Requests (Filtered)
try {
    $req_sql = "SELECT COUNT(*) FROM shelter_requests WHERE status = 'pending' $shelter_cond";
    $req_count = $pdo->query($req_sql)->fetchColumn();
    
    $late_sql = "SELECT COUNT(*) FROM shelter_requests WHERE status = 'pending' AND created_at < NOW() - INTERVAL 1 DAY $shelter_cond";
    $req_late = $pdo->query($late_sql)->fetchColumn();
} catch (Exception $e) { $req_count = 0; $req_late = 0; }

// --- 3. Trend Analysis (Last 7 Days) ---
$dates = [];
$counts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('d/m', strtotime($date));
    
    // นับยอดลงทะเบียนรายวัน (Apply Filter)
    $sql_trend = "SELECT COUNT(*) FROM evacuees WHERE DATE(registered_at) = '$date'";
    if ($filter_shelter_id) {
        $sql_trend .= " AND shelter_id = $filter_shelter_id";
    }
    
    try {
        $count = $pdo->query($sql_trend)->fetchColumn();
    } catch (Exception $e) { $count = 0; }
    $counts[] = $count;
}
$chart_labels = json_encode($dates);
$chart_data = json_encode($counts);

// --- 4. Recent Logs (Filtered by Users in Shelter if filtered) ---
$log_cond = "";
if ($filter_shelter_id) {
    // กรอง log เฉพาะ user ที่สังกัด shelter นี้ (Subquery)
    $log_cond = " WHERE u.shelter_id = $filter_shelter_id ";
}

$sql_logs = "SELECT l.*, u.username, u.shelter_id 
             FROM system_logs l 
             LEFT JOIN users u ON l.user_id = u.id 
             $log_cond
             ORDER BY l.created_at DESC LIMIT 7";
try {
    $recent_logs = $pdo->query($sql_logs)->fetchAll();
} catch (Exception $e) { $recent_logs = []; }

// Fetch All Shelters for Filter Dropdown
$all_shelters = [];
if ($role === 'admin') {
    $all_shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>ศูนย์ปฏิบัติการ (War Room)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0 0 20px 20px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            border: none; border-radius: 15px; transition: all 0.3s;
            overflow: hidden; position: relative;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .stat-icon-bg {
            position: absolute; right: -10px; bottom: -10px;
            font-size: 5rem; opacity: 0.1; transform: rotate(-15deg);
        }
        
        .bg-gradient-primary { background: linear-gradient(45deg, #4b6cb7, #182848); color: white; }
        .bg-gradient-danger { background: linear-gradient(45deg, #ff5370, #ff869a); color: white; }
        .bg-gradient-warning { background: linear-gradient(45deg, #ffb64d, #ffcb80); color: white; }
        .bg-gradient-success { background: linear-gradient(45deg, #2ed8b6, #59e0c5); color: white; }

        /* Health Badges */
        .health-badge {
            display: flex; align-items: center; justify-content: space-between;
            padding: 15px; border-radius: 12px; color: white; margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s;
        }
        .health-badge:hover { transform: scale(1.02); }
        .bg-chronic { background: linear-gradient(45deg, #f6d365, #fda085); }
        .bg-pregnant { background: linear-gradient(45deg, #ff9a9e, #fecfef); color: #555; }
        .bg-disabled { background: linear-gradient(45deg, #a18cd1, #fbc2eb); }
        .bg-bedridden { background: linear-gradient(45deg, #84fab0, #8fd3f4); color: #333; }

        .progress-thin { height: 6px; border-radius: 3px; }
        .table-logs td { font-size: 0.9rem; vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<!-- Custom Dashboard Header + Filter -->
<div class="dashboard-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <h2 class="fw-bold mb-1"><i class="fas fa-globe-asia me-2"></i>ศูนย์ปฏิบัติการ (War Room)</h2>
                <p class="mb-0 opacity-75">
                    <?php 
                        if ($filter_shelter_id) {
                            // Find shelter name
                            foreach($all_shelters as $s) {
                                if ($s['id'] == $filter_shelter_id) {
                                    echo "ข้อมูลเฉพาะ: " . htmlspecialchars($s['name']);
                                    break;
                                }
                            }
                        } else {
                            echo "ภาพรวมสถานการณ์ทั้งหมด (Global View)";
                        }
                    ?>
                </p>
            </div>
            
            <div class="col-md-6">
                <div class="d-flex justify-content-md-end align-items-center gap-3">
                    <?php if ($role === 'admin'): ?>
                        <!-- Filter Dropdown -->
                        <form action="" method="GET" class="d-flex bg-white rounded p-1">
                            <select name="shelter_id" class="form-select border-0 shadow-none" style="min-width: 250px;" onchange="this.form.submit()">
                                <option value="">-- ดูภาพรวมทั้งหมด (All Shelters) --</option>
                                <?php foreach($all_shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $filter_shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary rounded"><i class="fas fa-filter"></i></button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-end text-white d-none d-lg-block">
                        <div class="h5 mb-0"><?php echo date('d M'); ?></div>
                        <div class="small opacity-75"><?php echo date('H:i'); ?> น.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">

    <!-- 1. Key Metrics Cards -->
    <div class="row g-4 mb-4">
        <!-- Evacuees -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-gradient-primary h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2 opacity-75">ผู้ประสบภัย</h6>
                    <h2 class="fw-bold mb-0"><?php echo number_format($evac_stats['total']); ?> <span class="fs-6 fw-normal">คน</span></h2>
                    <div class="mt-3 small">
                        <i class="fas fa-male me-1"></i> ชาย: <?php echo number_format($evac_stats['male']); ?> 
                        <span class="mx-2">|</span> 
                        <i class="fas fa-female me-1"></i> หญิง: <?php echo number_format($evac_stats['female']); ?>
                    </div>
                    <i class="fas fa-users stat-icon-bg"></i>
                </div>
            </div>
        </div>

        <!-- Capacity -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-gradient-success h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2 opacity-75">ความจุศูนย์พักพิง</h6>
                    <h2 class="fw-bold mb-0"><?php echo number_format($shelter_stats['total_occupied']); ?> / <?php echo number_format($shelter_stats['total_capacity']); ?></h2>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>ใช้ไป <?php echo number_format($global_cap_percent, 1); ?>%</span>
                            <span><?php echo number_format($shelter_stats['total_shelters']); ?> แห่ง</span>
                        </div>
                        <div class="progress progress-thin bg-white bg-opacity-25">
                            <div class="progress-bar bg-white" role="progressbar" style="width: <?php echo $global_cap_percent; ?>%"></div>
                        </div>
                    </div>
                    <i class="fas fa-campground stat-icon-bg"></i>
                </div>
            </div>
        </div>

        <!-- Health Summary (Compact) -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-gradient-danger h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2 opacity-75">กลุ่มเปราะบาง (รวม)</h6>
                    <h2 class="fw-bold mb-0">
                        <?php echo number_format($health_stats['chronic'] + $health_stats['disabled'] + $health_stats['bedridden'] + $health_stats['pregnant']); ?> 
                        <span class="fs-6 fw-normal">ราย</span>
                    </h2>
                    <div class="mt-3 small">
                        ติดเตียง: <?php echo $health_stats['bedridden']; ?> | พิการ: <?php echo $health_stats['disabled']; ?>
                    </div>
                    <i class="fas fa-heartbeat stat-icon-bg"></i>
                </div>
            </div>
        </div>

        <!-- Requests -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-gradient-warning h-100">
                <div class="card-body">
                    <h6 class="text-uppercase mb-2 opacity-75">คำร้องขอความช่วยเหลือ</h6>
                    <h2 class="fw-bold mb-0"><?php echo number_format($req_count); ?> <span class="fs-6 fw-normal">รายการ</span></h2>
                    <div class="mt-3 small text-dark fw-bold">
                        <i class="fas fa-clock"></i> ล่าช้าเกิน 24ชม.: <?php echo $req_late; ?> เคส
                    </div>
                    <i class="fas fa-bullhorn stat-icon-bg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Health & Trend Analysis -->
    <div class="row g-4">
        
        <!-- Left: Health Details & Trend -->
        <div class="col-lg-8">
            <!-- Health Badges Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="health-badge bg-chronic">
                        <div><div class="h4 mb-0 fw-bold"><?php echo $health_stats['chronic']; ?></div><small>โรคเรื้อรัง</small></div>
                        <i class="fas fa-pills fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="health-badge bg-pregnant">
                        <div><div class="h4 mb-0 fw-bold"><?php echo $health_stats['pregnant']; ?></div><small>ตั้งครรภ์</small></div>
                        <i class="fas fa-baby-carriage fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="health-badge bg-disabled">
                        <div><div class="h4 mb-0 fw-bold"><?php echo $health_stats['disabled']; ?></div><small>ผู้พิการ</small></div>
                        <i class="fas fa-wheelchair fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="health-badge bg-bedridden">
                        <div><div class="h4 mb-0 fw-bold"><?php echo $health_stats['bedridden']; ?></div><small>ติดเตียง</small></div>
                        <i class="fas fa-procedures fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Trend Chart -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-chart-line me-2 text-primary"></i>แนวโน้มผู้เข้าพักพิง (7 วันล่าสุด)</h6>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" style="height: 250px;"></canvas>
                </div>
            </div>

            <!-- Recent Logs -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2"></i>ความเคลื่อนไหวล่าสุด</h6>
                    <a href="system_log_list.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-logs mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>เวลา</th>
                                <th>ผู้ใช้งาน</th>
                                <th>กิจกรรม</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_logs)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">ไม่มีประวัติการใช้งาน</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_logs as $log): ?>
                                <tr>
                                    <td class="text-muted" style="width: 150px;">
                                        <i class="far fa-clock me-1"></i> <?php echo date('d/m H:i', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="fw-bold text-primary">
                                        <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $action = $log['action'] ?? '-';
                                            $details = $log['details'] ?? ($log['description'] ?? '');
                                            echo htmlspecialchars($action);
                                        ?>
                                        <?php if($details): ?>
                                            <span class="text-muted small d-block"><?php echo htmlspecialchars($details); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Demographics & Links -->
        <div class="col-lg-4">
            
            <!-- Demographics Chart -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2"></i>โครงสร้างประชากร</h6>
                </div>
                <div class="card-body">
                    <canvas id="demoChart" style="height: 220px;"></canvas>
                    <div class="mt-3 text-center small text-muted">
                        <span class="me-2"><i class="fas fa-circle text-info"></i> เด็ก <?php echo number_format($evac_stats['children']); ?></span>
                        <span class="me-2"><i class="fas fa-circle text-success"></i> ผู้ใหญ่ <?php echo number_format($evac_stats['adults']); ?></span>
                        <span><i class="fas fa-circle text-danger"></i> สูงอายุ <?php echo number_format($evac_stats['elderly']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-link me-2"></i>เมนูด่วน</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="evacuee_form.php" class="btn btn-outline-primary text-start">
                            <i class="fas fa-user-plus me-2"></i> ลงทะเบียนผู้ประสบภัย
                        </a>
                        <a href="distribution_manager.php" class="btn btn-outline-success text-start">
                            <i class="fas fa-box-open me-2"></i> เบิกจ่ายสิ่งของ
                        </a>
                        <a href="shelter_list.php" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-search-location me-2"></i> ค้นหาศูนย์พักพิง
                        </a>
                        <a href="report.php" class="btn btn-outline-info text-start">
                            <i class="fas fa-file-pdf me-2"></i> ออกรายงานสถานการณ์
                        </a>
                        <a href="health_dashboard.php" class="btn btn-outline-danger text-start">
                            <i class="fas fa-heartbeat me-2"></i> ข้อมูลสุขภาพแบบละเอียด
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js Config -->
<script>
    // 1. Trend Chart
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?php echo $chart_labels; ?>,
            datasets: [{
                label: 'ผู้เข้าพักพิงรายใหม่',
                data: <?php echo $chart_data; ?>,
                borderColor: '#4b6cb7',
                backgroundColor: 'rgba(75, 108, 183, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });

    // 2. Demographics Chart
    const ctxDemo = document.getElementById('demoChart').getContext('2d');
    new Chart(ctxDemo, {
        type: 'doughnut',
        data: {
            labels: ['เด็ก', 'วัยทำงาน', 'ผู้สูงอายุ'],
            datasets: [{
                data: [
                    <?php echo $evac_stats['children']; ?>, 
                    <?php echo $evac_stats['adults']; ?>, 
                    <?php echo $evac_stats['elderly']; ?>
                ],
                backgroundColor: ['#36a2eb', '#4bc0c0', '#ff6384']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>