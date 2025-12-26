<?php
// monitor_dashboard.php
// ศูนย์ปฏิบัติการ (War Room) - ฉบับสมบูรณ์ (Complete Dimensions: Human, Logistics, Spatial, Operation)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// --- 1. Filter Logic ---
$filter_shelter_id = null;
$shelter_name_display = "ภาพรวมทั้งระบบ (Global View)";

if ($role === 'admin') {
    if (isset($_GET['shelter_id']) && $_GET['shelter_id'] != '') {
        $filter_shelter_id = (int)$_GET['shelter_id'];
    }
} else {
    if ($my_shelter_id) {
        $filter_shelter_id = $my_shelter_id;
    }
}

// SQL Conditions
$shelter_cond   = $filter_shelter_id ? " AND shelter_id = $filter_shelter_id " : "";
$evac_cond      = $filter_shelter_id ? " WHERE shelter_id = $filter_shelter_id " : ""; 
$inv_cond       = $filter_shelter_id ? " AND i.shelter_id = $filter_shelter_id " : "";
$shelter_cond_s = $filter_shelter_id ? " AND s.id = $filter_shelter_id " : ""; 

// Shelter Name Display
if ($filter_shelter_id) {
    $stmt_name = $pdo->prepare("SELECT name FROM shelters WHERE id = ?");
    $stmt_name->execute([$filter_shelter_id]);
    $row_name = $stmt_name->fetch();
    if($row_name) $shelter_name_display = "ข้อมูลเฉพาะ: " . $row_name['name'];
}

// --- 2. Data Gathering (KPIs) ---

// 2.1 Human Dimension (Evacuees)
$sql_ev = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN gender='male' THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN gender='female' THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as children,
    SUM(CASE WHEN age BETWEEN 15 AND 59 THEN 1 ELSE 0 END) as adults
FROM evacuees $evac_cond";
$evac_stats = $pdo->query($sql_ev)->fetch(PDO::FETCH_ASSOC);

// 2.2 Spatial Dimension (Shelter Capacity)
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

// 2.3 Operational Dimension (Requests & Incidents)
$req_stats = ['pending' => 0, 'completed' => 0, 'total' => 0, 'rate' => 0];
try {
    $sql_req = "SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        COUNT(*) as total
    FROM shelter_requests WHERE 1=1 $shelter_cond";
    $req_res = $pdo->query($sql_req)->fetch(PDO::FETCH_ASSOC);
    if ($req_res) {
        $req_stats['pending'] = (int)$req_res['pending'];
        $req_stats['completed'] = (int)$req_res['completed'];
        $req_stats['total'] = (int)$req_res['total'];
        $req_stats['rate'] = $req_stats['total'] > 0 ? ($req_stats['completed'] / $req_stats['total']) * 100 : 0;
    }
} catch (Exception $e) { /* Ignore */ }

try {
    $incident_active = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'active'")->fetchColumn();
} catch (Exception $e) { $incident_active = 0; }

// 2.4 Logistics Dimension (Inventory Breakdown)
$inv_categories = [];
try {
    $sql_inv_cat = "SELECT category, COUNT(*) as count, SUM(quantity) as total_qty 
                    FROM inventory i 
                    WHERE quantity > 0 $inv_cond 
                    GROUP BY category 
                    ORDER BY total_qty DESC LIMIT 5";
    $inv_categories = $pdo->query($sql_inv_cat)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* Ignore */ }

// 2.5 Health Dimension (Vulnerable)
$health_stats = ['chronic' => 0, 'pregnant' => 0, 'disabled' => 0, 'bedridden' => 0];
try {
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
        foreach ($res_h as $k => $v) $health_stats[$k] = (int)$v;
    }
} catch (Exception $e) {}

// --- 3. Lists & Logs ---

// 3.1 Shelter Status List (Top 5 Crowded)
// คำนวณความจุแบบ Dynamic Subquery
$sql_shelter_status = "SELECT s.id, s.name, s.capacity, 
                        (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id) as current_population
                       FROM shelters s
                       WHERE s.status = 'open' $shelter_cond_s
                       ORDER BY (current_population / NULLIF(s.capacity, 0)) DESC LIMIT 5";
$shelter_status_list = $pdo->query($sql_shelter_status)->fetchAll();

// 3.2 Recent Logs
$log_cond = $filter_shelter_id ? " WHERE u.shelter_id = $filter_shelter_id " : "";
$sql_logs = "SELECT l.*, u.username FROM system_logs l LEFT JOIN users u ON l.user_id = u.id $log_cond ORDER BY l.created_at DESC LIMIT 5";
try {
    $recent_logs = $pdo->query($sql_logs)->fetchAll();
} catch (Exception $e) { $recent_logs = []; }

// 3.3 Trend Analysis (Last 7 Days)
$dates = []; $counts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('d/m', strtotime($d));
    $sql_t = "SELECT COUNT(*) FROM evacuees WHERE DATE(registered_at) = '$d'";
    if ($filter_shelter_id) $sql_t .= " AND shelter_id = $filter_shelter_id";
    try { $c = $pdo->query($sql_t)->fetchColumn(); } catch(Exception $e) { $c = 0; }
    $counts[] = $c;
}
$chart_labels = json_encode($dates);
$chart_data = json_encode($counts);

// Admin Selectors
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%);
            color: white; padding: 1.5rem; border-radius: 0 0 20px 20px; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .stat-card {
            border: none; border-radius: 12px; position: relative; overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s;
            background: white; height: 100%;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .stat-icon-bg {
            position: absolute; right: -10px; bottom: -10px; font-size: 4rem; opacity: 0.1; transform: rotate(-15deg);
        }
        
        /* Modern Cards */
        .card-kpi-1 { border-left: 5px solid #4b6cb7; }
        .card-kpi-2 { border-left: 5px solid #11998e; }
        .card-kpi-3 { border-left: 5px solid #FFB75E; }
        .card-kpi-4 { border-left: 5px solid #FF5370; }

        .kpi-value { font-size: 2rem; font-weight: 700; color: #333; }
        .kpi-label { font-size: 0.9rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .table-custom th { background-color: #f8f9fa; font-weight: 600; font-size: 0.85rem; }
        .table-custom td { font-size: 0.85rem; vertical-align: middle; }
        .progress-thin { height: 6px; border-radius: 3px; }
        
        .health-icon-box {
            width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: white;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<!-- Header & Filter -->
<div class="dashboard-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h3 class="fw-bold mb-1"><i class="fas fa-globe-americas me-2"></i>ศูนย์ปฏิบัติการ (War Room)</h3>
                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($shelter_name_display); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <?php if ($role === 'admin'): ?>
                    <form action="" method="GET" class="d-inline-block">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text border-0 bg-white text-primary"><i class="fas fa-filter"></i></span>
                            <select name="shelter_id" class="form-select border-0" onchange="this.form.submit()" style="min-width: 200px;">
                                <option value="">-- ภาพรวมทั้งระบบ --</option>
                                <?php foreach($all_shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $filter_shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
                <div class="small mt-1 opacity-75">อัปเดตข้อมูล: <?php echo date('H:i'); ?> น.</div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 mb-5">

    <!-- Row 1: High-Level KPIs -->
    <div class="row g-3 mb-4">
        <!-- 1. Population -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card card-kpi-1 p-3">
                <div class="kpi-label">ผู้ประสบภัย</div>
                <div class="kpi-value"><?php echo number_format($evac_stats['total']); ?></div>
                <div class="small text-muted mt-2">
                    <i class="fas fa-male me-1"></i> ชาย <?php echo number_format($evac_stats['male']); ?> | 
                    <i class="fas fa-female me-1"></i> หญิง <?php echo number_format($evac_stats['female']); ?>
                </div>
                <i class="fas fa-users stat-icon-bg text-primary"></i>
            </div>
        </div>
        <!-- 2. Capacity -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card card-kpi-2 p-3">
                <div class="kpi-label">การใช้พื้นที่ (Capacity)</div>
                <div class="kpi-value"><?php echo number_format($global_cap_percent, 1); ?>%</div>
                <div class="progress progress-thin mt-2 bg-light">
                    <div class="progress-bar bg-success" style="width: <?php echo $global_cap_percent; ?>%"></div>
                </div>
                <div class="small text-muted mt-1 text-end"><?php echo number_format($shelter_stats['total_occupied']); ?>/<?php echo number_format($shelter_stats['total_capacity']); ?></div>
                <i class="fas fa-campground stat-icon-bg text-success"></i>
            </div>
        </div>
        <!-- 3. Requests -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card card-kpi-3 p-3">
                <div class="kpi-label">คำร้องขอความช่วยเหลือ</div>
                <div class="d-flex justify-content-between align-items-end">
                    <div class="kpi-value"><?php echo number_format($req_stats['pending']); ?></div>
                    <div class="text-end">
                        <div class="h6 mb-0 text-success"><?php echo number_format($req_stats['rate'], 0); ?>%</div>
                        <div class="small text-muted">อัตราสำเร็จ</div>
                    </div>
                </div>
                <i class="fas fa-bullhorn stat-icon-bg text-warning"></i>
            </div>
        </div>
        <!-- 4. Incidents -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card card-kpi-4 p-3">
                <div class="kpi-label">ภัยพิบัติ (Active)</div>
                <div class="kpi-value"><?php echo number_format($incident_active); ?></div>
                <div class="small text-muted mt-2">เหตุการณ์เฝ้าระวัง</div>
                <i class="fas fa-exclamation-triangle stat-icon-bg text-danger"></i>
            </div>
        </div>
    </div>

    <!-- Row 2: Analysis & Dimensions -->
    <div class="row g-4 mb-4">
        
        <!-- Left: Trend & Inventory -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-chart-line me-2"></i>วิเคราะห์แนวโน้มและทรัพยากร</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Chart: Trend -->
                        <div class="col-md-8 border-end">
                            <h6 class="text-center small text-muted mb-3">แนวโน้มผู้เข้าพักพิง (7 วัน)</h6>
                            <div style="height: 250px;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                        <!-- Chart: Inventory Categories -->
                        <div class="col-md-4">
                            <h6 class="text-center small text-muted mb-3">สัดส่วนสินค้าคงคลัง</h6>
                            <div style="height: 200px; position: relative;">
                                <canvas id="invChart"></canvas>
                            </div>
                            <div class="mt-3 text-center small">
                                <a href="inventory_list.php" class="text-decoration-none">จัดการคลังสินค้า &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Shelter Status (Spatial Dimension) -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-map-marked-alt me-2"></i>สถานะศูนย์ (Top 5)</h6>
                    <small class="text-muted">เรียงตามความหนาแน่น</small>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if(empty($shelter_status_list)): ?>
                            <div class="text-center py-4 text-muted">ไม่มีข้อมูลศูนย์</div>
                        <?php else: ?>
                            <?php foreach($shelter_status_list as $s): 
                                $cap = $s['capacity'] > 0 ? $s['capacity'] : 1;
                                $pct = ($s['current_population'] / $cap) * 100;
                                $statusColor = $pct > 90 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="text-truncate fw-bold text-dark" style="max-width: 180px;">
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </div>
                                    <span class="badge <?php echo $statusColor; ?> rounded-pill"><?php echo number_format($pct, 0); ?>%</span>
                                </div>
                                <div class="progress progress-thin bg-light">
                                    <div class="progress-bar <?php echo $statusColor; ?>" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted" style="font-size: 0.7rem;">Occupied</small>
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo number_format($s['current_population']); ?> / <?php echo number_format($s['capacity']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Health & Operations -->
    <div class="row g-4">
        
        <!-- Health Dimension (Vulnerable Groups) -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-heartbeat me-2"></i>กลุ่มเปราะบาง (Vulnerable Groups)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Bedridden -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 border rounded bg-white shadow-sm h-100">
                                <div class="health-icon-box bg-danger me-3"><i class="fas fa-procedures"></i></div>
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo number_format($health_stats['bedridden']); ?></h5>
                                    <small class="text-muted">ผู้ป่วยติดเตียง</small>
                                </div>
                            </div>
                        </div>
                        <!-- Disabled -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 border rounded bg-white shadow-sm h-100">
                                <div class="health-icon-box bg-primary me-3"><i class="fas fa-wheelchair"></i></div>
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo number_format($health_stats['disabled']); ?></h5>
                                    <small class="text-muted">ผู้พิการ</small>
                                </div>
                            </div>
                        </div>
                        <!-- Pregnant -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 border rounded bg-white shadow-sm h-100">
                                <div class="health-icon-box bg-warning text-dark me-3"><i class="fas fa-baby-carriage"></i></div>
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo number_format($health_stats['pregnant']); ?></h5>
                                    <small class="text-muted">หญิงตั้งครรภ์</small>
                                </div>
                            </div>
                        </div>
                        <!-- Chronic -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-3 border rounded bg-white shadow-sm h-100">
                                <div class="health-icon-box bg-info me-3"><i class="fas fa-pills"></i></div>
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo number_format($health_stats['chronic']); ?></h5>
                                    <small class="text-muted">โรคเรื้อรัง</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Logs -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-history me-2"></i>ความเคลื่อนไหวล่าสุด</h6>
                    <a href="system_log_list.php" class="btn btn-sm btn-light text-primary fw-bold">ดูทั้งหมด</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead class="table-light">
                            <tr>
                                th>เวลา</th>
                                <th>ผู้ใช้งาน</th>
                                <th>กิจกรรม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_logs)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">ไม่มีข้อมูล</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_logs as $log): ?>
                                <tr>
                                    <td class="text-muted" style="width: 100px;"><?php echo date('H:i', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
                                    </td>
                                    <td>
                                        <span class="text-primary d-block" style="font-size: 0.85rem;"><?php echo htmlspecialchars($log['action']); ?></span>
                                        <small class="text-muted d-block text-truncate" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($log['details'] ?? ($log['description'] ?? '-')); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart Scripts -->
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
                borderWidth: 2, fill: true, tension: 0.4
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: { legend: { display: false } }
        }
    });

    // 2. Inventory Category Chart (Doughnut)
    <?php 
        $inv_labels = []; $inv_data = [];
        foreach($inv_categories as $ic) {
            $inv_labels[] = $ic['category'];
            $inv_data[] = $ic['total_qty'];
        }
    ?>
    const ctxInv = document.getElementById('invChart').getContext('2d');
    new Chart(ctxInv, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($inv_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($inv_data); ?>,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } }
        }
    });
</script>

</body>
</html>