<?php
// health_dashboard.php
// Dashboard สถานการณ์สุขภาพและกลุ่มเปราะบาง
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// 1. Shelter Filter Logic
if ($role === 'admin' && isset($_GET['shelter_id'])) {
    $shelter_id = (int)$_GET['shelter_id'];
} else {
    $shelter_id = $my_shelter_id;
}

// 2. Fetch Shelter Info
$shelter_info = [];
if ($shelter_id) {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$shelter_id]);
    $shelter_info = $stmt->fetch();
}

// 3. Health Statistics Calculation
$stats = [
    'chronic' => 0,    // โรคประจำตัว
    'pregnant' => 0,   // ตั้งครรภ์
    'disabled' => 0,   // พิการ
    'bedridden' => 0,  // ติดเตียง
    'elderly' => 0,    // ผู้สูงอายุ
    'children' => 0,   // เด็กเล็ก
    'total_evac' => 0
];

$recent_cases = [];

if ($shelter_id) {
    // 3.1 Demographics & Vulnerable Groups
    // หมายเหตุ: การนับนี้ขึ้นอยู่กับโครงสร้างข้อมูลใน evacuee_needs หรือ tags ใน evacuees
    // สมมติว่ามีการเก็บข้อมูลใน evacuee_needs หรือใช้ Logic อายุ
    
    // นับพื้นฐานจากอายุ
    $sql_demo = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
                    SUM(CASE WHEN age < 5 THEN 1 ELSE 0 END) as small_child
                 FROM evacuees WHERE shelter_id = ?";
    $stmt_demo = $pdo->prepare($sql_demo);
    $stmt_demo->execute([$shelter_id]);
    $res_demo = $stmt_demo->fetch(PDO::FETCH_ASSOC);
    
    if($res_demo) {
        $stats['total_evac'] = $res_demo['total'];
        $stats['elderly'] = $res_demo['elderly'];
        $stats['children'] = $res_demo['small_child'];
    }

    // นับจากตาราง evacuee_needs (ถ้ามี) หรือประยุกต์ใช้ query นี้
    // ค้นหา Keyword ใน needs_description หรือ category
    $sql_health = "SELECT 
                    SUM(CASE WHEN description LIKE '%เบาหวาน%' OR description LIKE '%ความดัน%' OR description LIKE '%หัวใจ%' THEN 1 ELSE 0 END) as chronic,
                    SUM(CASE WHEN description LIKE '%ครรภ์%' OR description LIKE '%ท้อง%' THEN 1 ELSE 0 END) as pregnant,
                    SUM(CASE WHEN description LIKE '%พิการ%' THEN 1 ELSE 0 END) as disabled,
                    SUM(CASE WHEN description LIKE '%ติดเตียง%' THEN 1 ELSE 0 END) as bedridden
                   FROM evacuee_needs n
                   JOIN evacuees e ON n.evacuee_id = e.id
                   WHERE e.shelter_id = ? AND n.status = 'pending'"; // นับเฉพาะที่ยังต้องการความช่วยเหลือ
    
    // *หมายเหตุ: หากไม่มีตาราง evacuee_needs ให้ comment ส่วนนี้ไว้*
    try {
        $stmt_health = $pdo->prepare($sql_health);
        $stmt_health->execute([$shelter_id]);
        $res_health = $stmt_health->fetch(PDO::FETCH_ASSOC);
        if ($res_health) {
            $stats['chronic'] = $res_health['chronic'];
            $stats['pregnant'] = $res_health['pregnant'];
            $stats['disabled'] = $res_health['disabled'];
            $stats['bedridden'] = $res_health['bedridden'];
        }
    } catch (Exception $e) {
        // Fallback or ignore if table missing
    }

    // 3.2 Medical Records / Logs (จำลองข้อมูลการรักษาล่าสุด)
    // ถ้ามีตาราง medical_records ใช้ query นี้
    /*
    $sql_med = "SELECT m.*, e.first_name, e.last_name 
                FROM medical_records m 
                JOIN evacuees e ON m.evacuee_id = e.id
                WHERE e.shelter_id = ? 
                ORDER BY m.created_at DESC LIMIT 5";
    */
    // เนื่องจากอาจยังไม่มีข้อมูลจริง ใช้ System logs ที่เกี่ยวกับ 'medical' หรือ 'evacuee' แทนชั่วคราว
    $sql_logs = "SELECT l.*, u.username 
                 FROM system_logs l
                 JOIN users u ON l.user_id = u.id
                 WHERE l.action LIKE '%medical%' OR l.action LIKE '%health%'
                 ORDER BY l.created_at DESC LIMIT 5";
    try {
        $recent_cases = $pdo->query($sql_logs)->fetchAll();
    } catch (Exception $e) { $recent_cases = []; }
}

// Fetch All Shelters for Admin
$all_shelters = [];
if ($role === 'admin') {
    $all_shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>สถานการณ์สุขภาพ (Health Dashboard)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-health {
            border: none;
            border-radius: 15px;
            color: white;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s;
        }
        .card-health:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        .bg-gradient-red { background: linear-gradient(135deg, #FF5370 0%, #ff869a 100%); }
        .bg-gradient-blue { background: linear-gradient(135deg, #4099ff 0%, #73b4ff 100%); }
        .bg-gradient-green { background: linear-gradient(135deg, #2ed8b6 0%, #59e0c5 100%); }
        .bg-gradient-orange { background: linear-gradient(135deg, #FFB64D 0%, #ffcb80 100%); }
        
        .health-icon {
            position: absolute;
            right: -15px;
            bottom: -15px;
            font-size: 6rem;
            opacity: 0.2;
            transform: rotate(-20deg);
        }
        
        .triage-badge { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .triage-red { background-color: #dc3545; box-shadow: 0 0 5px #dc3545; }
        .triage-yellow { background-color: #ffc107; box-shadow: 0 0 5px #ffc107; }
        .triage-green { background-color: #198754; box-shadow: 0 0 5px #198754; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-user-md text-danger me-2"></i>สถานการณ์สุขภาพ</h3>
            <?php if($shelter_id && $shelter_info): ?>
                <span class="badge bg-danger fs-6"><i class="fas fa-hospital-alt me-1"></i> <?php echo htmlspecialchars($shelter_info['name']); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if($role === 'admin'): ?>
        <form class="d-flex" action="" method="GET">
            <select name="shelter_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- เลือกศูนย์พักพิง --</option>
                <?php foreach($all_shelters as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <?php if(!$shelter_id): ?>
        <div class="alert alert-warning text-center py-5">
            <i class="fas fa-stethoscope fa-3x mb-3"></i><br>
            กรุณาเลือกศูนย์พักพิงเพื่อดูข้อมูลสุขภาพ
        </div>
    <?php else: ?>

    <!-- 1. Health Status Cards -->
    <div class="row g-4 mb-4">
        <!-- Chronic Diseases -->
        <div class="col-md-3">
            <div class="card card-health bg-gradient-orange h-100 p-3">
                <h5 class="mb-1">โรคเรื้อรัง</h5>
                <small class="text-white-50">เบาหวาน/ความดัน/หัวใจ</small>
                <div class="d-flex align-items-end mt-3">
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['chronic']); ?></h2>
                    <span class="ms-2 mb-1">ราย</span>
                </div>
                <i class="fas fa-pills health-icon"></i>
            </div>
        </div>
        <!-- Pregnant -->
        <div class="col-md-3">
            <div class="card card-health bg-gradient-red h-100 p-3">
                <h5 class="mb-1">หญิงตั้งครรภ์</h5>
                <small class="text-white-50">ต้องการการดูแลพิเศษ</small>
                <div class="d-flex align-items-end mt-3">
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['pregnant']); ?></h2>
                    <span class="ms-2 mb-1">ราย</span>
                </div>
                <i class="fas fa-baby-carriage health-icon"></i>
            </div>
        </div>
        <!-- Disabled/Bedridden -->
        <div class="col-md-3">
            <div class="card card-health bg-gradient-blue h-100 p-3">
                <h5 class="mb-1">ผู้พิการ/ติดเตียง</h5>
                <small class="text-white-50">ช่วยเหลือตัวเองไม่ได้</small>
                <div class="d-flex align-items-end mt-3">
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['disabled'] + $stats['bedridden']); ?></h2>
                    <span class="ms-2 mb-1">ราย</span>
                </div>
                <i class="fas fa-wheelchair health-icon"></i>
            </div>
        </div>
        <!-- Elderly -->
        <div class="col-md-3">
            <div class="card card-health bg-gradient-green h-100 p-3">
                <h5 class="mb-1">ผู้สูงอายุ (60+)</h5>
                <small class="text-white-50">กลุ่มเสี่ยงสูง</small>
                <div class="d-flex align-items-end mt-3">
                    <h2 class="mb-0 fw-bold"><?php echo number_format($stats['elderly']); ?></h2>
                    <span class="ms-2 mb-1">คน</span>
                </div>
                <i class="fas fa-blind health-icon"></i>
            </div>
        </div>
    </div>

    <!-- 2. Charts & Details -->
    <div class="row g-4">
        
        <!-- Left: Health Summary -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-chart-bar me-2 text-primary"></i>สรุปกลุ่มเปราะบาง (Vulnerable Groups)</h6>
                </div>
                <div class="card-body">
                    <?php if($stats['total_evac'] > 0): ?>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fas fa-user-clock text-success me-2"></i>ผู้สูงอายุ</span>
                                <span><?php echo $stats['elderly']; ?> คน (<?php echo number_format(($stats['elderly']/$stats['total_evac'])*100, 1); ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($stats['elderly']/$stats['total_evac'])*100; ?>%"></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fas fa-child text-info me-2"></i>เด็กเล็ก (0-4 ปี)</span>
                                <span><?php echo $stats['children']; ?> คน (<?php echo number_format(($stats['children']/$stats['total_evac'])*100, 1); ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-info" style="width: <?php echo ($stats['children']/$stats['total_evac'])*100; ?>%"></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span><i class="fas fa-procedures text-danger me-2"></i>ผู้ป่วยติดเตียง/พิการ</span>
                                <span><?php echo $stats['bedridden'] + $stats['disabled']; ?> ราย</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-danger" style="width: <?php echo (($stats['bedridden']+$stats['disabled'])/$stats['total_evac'])*100; ?>%"></div>
                            </div>
                        </div>

                        <div class="alert alert-light border mt-4">
                            <i class="fas fa-info-circle text-primary me-1"></i> 
                            ข้อมูลนี้คำนวณจากฐานข้อมูลผู้ประสบภัยและการระบุความต้องการพิเศษ (Needs)
                        </div>

                    <?php else: ?>
                        <div class="text-center py-5 text-muted">ยังไม่มีข้อมูลผู้พักพิงในระบบ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Recent Activity / Needs -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-clipboard-list me-2"></i>บันทึกล่าสุด</h6>
                    <span class="badge bg-light text-dark border">Medical Logs</span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if(empty($recent_cases)): ?>
                            <li class="list-group-item text-center py-4 text-muted">ไม่มีประวัติการรักษาล่าสุด</li>
                        <?php else: ?>
                            <?php foreach($recent_cases as $case): ?>
                            <li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($case['action']); ?></h6>
                                    <small class="text-muted"><?php echo date('d/m H:i', strtotime($case['created_at'])); ?></small>
                                </div>
                                <p class="mb-1 small text-dark"><?php echo htmlspecialchars($case['details'] ?? '-'); ?></p>
                                <small class="text-muted"><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($case['username']); ?></small>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white text-center border-top-0 py-3">
                    <a href="dashboard_vulnerable.php" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-search me-1"></i> ค้นหารายชื่อกลุ่มเปราะบาง
                    </a>
                </div>
            </div>
        </div>

    </div>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>