<?php
// dashboard_vulnerable.php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Authentication Check
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ------------------------------------------------------------------
// 1. Data Aggregation (สรุปยอดแยกตามประเภท)
// ------------------------------------------------------------------
$stats = [
    'elderly' => 0,
    'disabled' => 0,
    'pregnant' => 0,
    'infant' => 0,
    'chronic' => 0,
    'halal' => 0,
    'vegetarian' => 0
];

try {
    // นับจำนวนตามประเภทความต้องการ (เฉพาะคนที่ยังไม่ออก check_out_date IS NULL)
    $sql_stats = "SELECT n.need_type, COUNT(DISTINCT n.evacuee_id) as total
                  FROM evacuee_needs n
                  JOIN evacuees e ON n.evacuee_id = e.id
                  WHERE e.check_out_date IS NULL
                  GROUP BY n.need_type";
    $stmt = $pdo->query($sql_stats);
    while ($row = $stmt->fetch()) {
        if (isset($stats[$row['need_type']])) {
            $stats[$row['need_type']] = $row['total'];
        }
    }
} catch (PDOException $e) {
    $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูลสถิติ: " . $e->getMessage();
}

// ------------------------------------------------------------------
// 2. Data Listing (ดึงรายชื่อคนที่มีความต้องการพิเศษ)
// ------------------------------------------------------------------
$filter_type = isset($_GET['type']) ? cleanInput($_GET['type']) : '';
$vulnerable_people = [];

try {
    // Query รายชื่อ พร้อมข้อมูลศูนย์พักพิง และสิ่งที่ต้องการ
    $sql_list = "SELECT e.id, e.prefix, e.first_name, e.last_name, e.age, e.phone, e.health_condition,
                        s.name as shelter_name, s.contact_phone as shelter_phone,
                        GROUP_CONCAT(n.need_type) as needs_list
                 FROM evacuees e
                 JOIN evacuee_needs n ON e.id = n.evacuee_id
                 JOIN shelters s ON e.shelter_id = s.id
                 WHERE e.check_out_date IS NULL ";
    
    $params = [];
    if ($filter_type) {
        $sql_list .= " AND n.need_type = ? ";
        $params[] = $filter_type;
    }
    
    $sql_list .= " GROUP BY e.id ORDER BY s.name, e.first_name";
    
    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute($params);
    $vulnerable_people = $stmt_list->fetchAll();

} catch (PDOException $e) {
    // Silent fail or log error
}

// Helper Functions for Display
function getNeedLabel($type) {
    $labels = [
        'elderly' => ['label' => 'ผู้สูงอายุ', 'icon' => 'fa-blind', 'color' => 'warning'],
        'disabled' => ['label' => 'ผู้พิการ', 'icon' => 'fa-wheelchair', 'color' => 'danger'],
        'pregnant' => ['label' => 'หญิงตั้งครรภ์', 'icon' => 'fa-person-pregnant', 'color' => 'info'],
        'infant' => ['label' => 'เด็กเล็ก', 'icon' => 'fa-baby', 'color' => 'primary'],
        'chronic' => ['label' => 'ป่วยเรื้อรัง', 'icon' => 'fa-pills', 'color' => 'secondary'],
        'halal' => ['label' => 'อาหารฮาลาล', 'icon' => 'fa-mosque', 'color' => 'success'],
        'vegetarian' => ['label' => 'มังสวิรัติ', 'icon' => 'fa-leaf', 'color' => 'success']
    ];
    return $labels[$type] ?? ['label' => $type, 'icon' => 'fa-circle', 'color' => 'secondary'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Dashboard กลุ่มเปราะบาง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stat-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.2s;
            cursor: pointer;
            overflow: hidden;
            position: relative;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .stat-icon-bg {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 5rem;
            opacity: 0.2;
            transform: rotate(-15deg);
        }
        .filter-active { border: 3px solid #0d6efd; transform: scale(1.02); }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid px-4 mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-heartbeat text-danger me-2"></i>ศูนย์ข้อมูลกลุ่มเปราะบาง</h3>
            <p class="text-muted mb-0">Vulnerable Groups Monitoring System</p>
        </div>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>พิมพ์รายงาน</button>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-5">
        <?php foreach ($stats as $type => $count): 
            $meta = getNeedLabel($type);
        ?>
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card bg-<?php echo $meta['color']; ?> text-white h-100 <?php echo ($filter_type == $type) ? 'filter-active' : ''; ?>" 
                 onclick="window.location.href='dashboard_vulnerable.php?type=<?php echo $type; ?>'">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1 opacity-75"><?php echo $meta['label']; ?></h6>
                            <h2 class="mb-0 fw-bold"><?php echo number_format($count); ?> <small class="fs-6">คน</small></h2>
                        </div>
                        <i class="fas <?php echo $meta['icon']; ?> fa-2x opacity-50"></i>
                    </div>
                    <i class="fas <?php echo $meta['icon']; ?> stat-icon-bg"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- ปุ่มดูทั้งหมด -->
        <div class="col-xl-3 col-md-6">
             <div class="card stat-card bg-white text-dark border h-100" onclick="window.location.href='dashboard_vulnerable.php'">
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="text-center">
                        <i class="fas fa-users fa-2x mb-2 text-secondary"></i>
                        <h6 class="mb-0">ดูรายชื่อทั้งหมด</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-list-ul me-2"></i>รายชื่อผู้ต้องการความช่วยเหลือ 
                <?php if($filter_type): ?>
                    <span class="badge bg-primary rounded-pill ms-2"><?php echo getNeedLabel($filter_type)['label']; ?></span>
                    <a href="dashboard_vulnerable.php" class="btn btn-sm btn-link text-decoration-none text-danger"><i class="fas fa-times"></i> ล้างตัวกรอง</a>
                <?php endif; ?>
            </h5>
            <span class="badge bg-light text-dark border"><?php echo count($vulnerable_people); ?> รายการ</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อ-นามสกุล</th>
                            <th>อายุ</th>
                            <th>กลุ่มเปราะบาง</th>
                            <th>สุขภาพ/ยา</th>
                            <th>ศูนย์พักพิง</th>
                            <th>ติดต่อ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($vulnerable_people) > 0): ?>
                            <?php foreach ($vulnerable_people as $person): 
                                $needs = explode(',', $person['needs_list']);
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?php echo htmlspecialchars($person['prefix'] . $person['first_name'] . ' ' . $person['last_name']); ?></div>
                                </td>
                                <td><?php echo $person['age'] ? $person['age'].' ปี' : '-'; ?></td>
                                <td>
                                    <?php foreach($needs as $n): $m = getNeedLabel($n); ?>
                                        <span class="badge bg-<?php echo $m['color']; ?> me-1 mb-1">
                                            <i class="fas <?php echo $m['icon']; ?>"></i> <?php echo $m['label']; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <small class="text-muted text-wrap" style="max-width: 250px;">
                                        <?php echo htmlspecialchars($person['health_condition'] ?: '-'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div><i class="fas fa-home text-secondary"></i> <?php echo htmlspecialchars($person['shelter_name']); ?></div>
                                    <small class="text-muted"><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($person['shelter_phone'] ?: '-'); ?></small>
                                </td>
                                <td>
                                    <?php if($person['phone']): ?>
                                        <a href="tel:<?php echo $person['phone']; ?>" class="btn btn-sm btn-outline-success rounded-pill">
                                            <i class="fas fa-phone"></i> โทร
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="evacuee_form.php?mode=edit&id=<?php echo $person['id']; ?>" class="btn btn-sm btn-light border" title="แก้ไข">
                                        <i class="fas fa-edit text-warning"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-user-check fa-3x mb-3 opacity-25"></i><br>
                                    ไม่พบข้อมูลกลุ่มเปราะบางในเงื่อนไขที่เลือก
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>