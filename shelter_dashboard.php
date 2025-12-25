<?php
// shelter_dashboard.php
// หน้า Dashboard สรุปข้อมูลเฉพาะศูนย์พักพิง (Population, Demographics, Inventory)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// กำหนด Shelter ID ที่จะดู
// Admin เลือกได้ผ่าน URL, Staff บังคับดูศูนย์ตัวเอง
if ($role === 'admin' && isset($_GET['shelter_id'])) {
    $shelter_id = (int)$_GET['shelter_id'];
} else {
    $shelter_id = $my_shelter_id;
}

// ถ้าไม่มี Shelter ID (เช่น Admin เข้ามาแต่ยังไม่เลือก)
if (!$shelter_id && $role === 'admin') {
    // Redirect ไปหน้า List หรือเลือกศูนย์ก่อน (ในที่นี้ให้แสดง Dropdown เลือก)
    $show_selector = true;
} else {
    $show_selector = false;
}

// --- 1. Fetch Shelter Info ---
$shelter_info = [];
if ($shelter_id) {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$shelter_id]);
    $shelter_info = $stmt->fetch();
}

// --- 2. Fetch Statistics (ถ้ามี Shelter ID) ---
$stats = [
    'total' => 0, 'male' => 0, 'female' => 0, 
    'child' => 0, 'adult' => 0, 'elderly' => 0,
    'vulnerable' => 0
];
$inventory_alert = [];

if ($shelter_id) {
    // 2.1 Demographics
    $sql_demo = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female,
                    SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as child,
                    SUM(CASE WHEN age BETWEEN 15 AND 59 THEN 1 ELSE 0 END) as adult,
                    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly
                 FROM evacuees WHERE shelter_id = ?";
    $stmt_demo = $pdo->prepare($sql_demo);
    $stmt_demo->execute([$shelter_id]);
    $res_demo = $stmt_demo->fetch(PDO::FETCH_ASSOC);
    if ($res_demo) $stats = array_merge($stats, $res_demo);

    // 2.2 Vulnerable Count (นับจาก Table needs หรือ flag ใน evacuees แล้วแต่โครงสร้าง)
    // สมมติโครงสร้าง: ตาราง evacuees มี column 'category' หรือนับจาก evacuee_needs
    // เพื่อความง่าย ลองนับจาก Keywords ใน evacuee_needs ถ้ามี หรือนับ Elderly เป็นกลุ่มเปราะบางพื้นฐาน
    $stats['vulnerable'] = $stats['elderly']; // เบื้องต้นนับผู้สูงอายุ

    // 2.3 Inventory Low Stock
    $sql_inv = "SELECT item_name, quantity, unit FROM inventory WHERE shelter_id = ? AND quantity < 20 ORDER BY quantity ASC LIMIT 5";
    $stmt_inv = $pdo->prepare($sql_inv);
    $stmt_inv->execute([$shelter_id]);
    $inventory_alert = $stmt_inv->fetchAll();
}

// Fetch All Shelters for Admin Selector
$all_shelters = [];
if ($role === 'admin') {
    $all_shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>Dashboard ข้อมูลศูนย์พักพิง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-stat { transition: transform 0.2s; border: none; border-radius: 10px; color: white; }
        .card-stat:hover { transform: translateY(-5px); }
        .bg-gradient-blue { background: linear-gradient(45deg, #4099ff, #73b4ff); }
        .bg-gradient-green { background: linear-gradient(45deg, #2ed8b6, #59e0c5); }
        .bg-gradient-pink { background: linear-gradient(45deg, #FF5370, #ff869a); }
        .bg-gradient-amber { background: linear-gradient(45deg, #FFB64D, #ffcb80); }
        
        .stat-icon { font-size: 2.5rem; opacity: 0.5; position: absolute; right: 20px; top: 20px; }
        .stat-value { font-size: 2rem; font-weight: bold; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>สรุปข้อมูลศูนย์พักพิง</h3>
            <?php if($shelter_id && $shelter_info): ?>
                <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($shelter_info['name']); ?></span>
                <span class="text-muted small ms-2"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($shelter_info['location'] ?? '-'); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if($role === 'admin'): ?>
        <form class="d-flex" action="" method="GET">
            <select name="shelter_id" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                <option value="">-- เลือกศูนย์เพื่อดูข้อมูล --</option>
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
        <div class="alert alert-info text-center py-5">
            <i class="fas fa-search-location fa-3x mb-3"></i><br>
            กรุณาเลือกศูนย์พักพิงที่ต้องการดูข้อมูล
        </div>
    <?php else: ?>

    <!-- 1. Key Statistics Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Population -->
        <div class="col-md-3">
            <div class="card card-stat bg-gradient-blue h-100 p-3">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">ผู้พักพิงทั้งหมด (คน)</div>
                <div class="mt-2 small text-white-50">
                    ความจุ: <?php echo number_format($shelter_info['capacity'] ?? 0); ?>
                </div>
            </div>
        </div>
        <!-- Vulnerable -->
        <div class="col-md-3">
            <div class="card card-stat bg-gradient-pink h-100 p-3">
                <div class="stat-icon"><i class="fas fa-wheelchair"></i></div>
                <div class="stat-value"><?php echo number_format($stats['vulnerable']); ?></div>
                <div class="stat-label">กลุ่มเปราะบาง/สูงอายุ</div>
                <div class="mt-2 small text-white-50">ต้องการดูแลพิเศษ</div>
            </div>
        </div>
        <!-- Male -->
        <div class="col-md-3">
            <div class="card card-stat bg-success h-100 p-3 bg-opacity-75">
                <div class="stat-icon"><i class="fas fa-male"></i></div>
                <div class="stat-value"><?php echo number_format($stats['male']); ?></div>
                <div class="stat-label">ชาย</div>
            </div>
        </div>
        <!-- Female -->
        <div class="col-md-3">
            <div class="card card-stat bg-info h-100 p-3 bg-opacity-75">
                <div class="stat-icon"><i class="fas fa-female"></i></div>
                <div class="stat-value"><?php echo number_format($stats['female']); ?></div>
                <div class="stat-label">หญิง</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- 2. Capacity & Age Charts -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="fas fa-chart-bar me-2 text-warning"></i> โครงสร้างประชากร
                </div>
                <div class="card-body">
                    <h6 class="text-muted mb-3">ช่วงอายุ (Age Groups)</h6>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>เด็ก (0-14 ปี)</span>
                            <span class="fw-bold"><?php echo number_format($stats['child']); ?> คน</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <?php $pct_child = $stats['total'] > 0 ? ($stats['child'] / $stats['total']) * 100 : 0; ?>
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $pct_child; ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>วัยทำงาน (15-59 ปี)</span>
                            <span class="fw-bold"><?php echo number_format($stats['adult']); ?> คน</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <?php $pct_adult = $stats['total'] > 0 ? ($stats['adult'] / $stats['total']) * 100 : 0; ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct_adult; ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>ผู้สูงอายุ (60+ ปี)</span>
                            <span class="fw-bold"><?php echo number_format($stats['elderly']); ?> คน</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <?php $pct_eld = $stats['total'] > 0 ? ($stats['elderly'] / $stats['total']) * 100 : 0; ?>
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $pct_eld; ?>%"></div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-muted mb-3">อัตราการใช้พื้นที่ (Capacity)</h6>
                    <?php 
                        $cap = $shelter_info['capacity'] > 0 ? $shelter_info['capacity'] : 1;
                        $usage_pct = ($stats['total'] / $cap) * 100;
                        $bar_color = $usage_pct > 90 ? 'bg-danger' : ($usage_pct > 70 ? 'bg-warning' : 'bg-primary');
                    ?>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar <?php echo $bar_color; ?> progress-bar-striped" role="progressbar" style="width: <?php echo $usage_pct; ?>%">
                            <?php echo number_format($usage_pct, 1); ?>%
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block text-end">
                        ใช้ไป <?php echo number_format($stats['total']); ?> จาก <?php echo number_format($cap); ?> ที่รองรับ
                    </small>
                </div>
            </div>
        </div>

        <!-- 3. Inventory Alerts -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold py-3 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> สินค้าใกล้หมด (Low Stock)
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($inventory_alert)): ?>
                            <li class="list-group-item text-center py-4 text-muted">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i><br>
                                สต็อกสินค้าปกติ
                            </li>
                        <?php else: ?>
                            <?php foreach ($inventory_alert as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <small class="text-muted">ควรเติมสินค้า</small>
                                </div>
                                <span class="badge bg-danger rounded-pill">
                                    เหลือ <?php echo number_format($item['quantity']) . ' ' . $item['unit']; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white text-center border-top-0 pb-3">
                    <a href="inventory_list.php" class="btn btn-sm btn-outline-primary w-100">จัดการคลังสินค้า</a>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>