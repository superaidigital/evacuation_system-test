<?php
// donation_dashboard.php
// หน้า Dashboard สรุปข้อมูลการรับบริจาค (Donation In-flow Statistics)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// 1. กำหนด Shelter ID
if ($role === 'admin' && isset($_GET['shelter_id'])) {
    $shelter_id = (int)$_GET['shelter_id'];
} else {
    $shelter_id = $my_shelter_id;
}

// 2. ดึงข้อมูลศูนย์
$shelter_info = [];
if ($shelter_id) {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$shelter_id]);
    $shelter_info = $stmt->fetch();
}

// 3. คำนวณสถิติการรับบริจาค (Transaction Type = 'in')
$stats = [
    'total_items' => 0,    // จำนวนชิ้นรวมทั้งหมด
    'today_items' => 0,    // จำนวนชิ้นวันนี้
    'total_trans' => 0,    // จำนวนครั้งที่รับ
    'donors_count' => 0    // จำนวนผู้บริจาค (นับคร่าวๆ จาก Note หรือ Source ถ้ามี)
];
$recent_donations = [];
$top_items = [];

if ($shelter_id) {
    // 3.1 สถิติรวม
    $sql_stat = "SELECT 
                    SUM(t.quantity) as total_qty,
                    COUNT(t.id) as total_times,
                    SUM(CASE WHEN DATE(t.created_at) = CURDATE() THEN t.quantity ELSE 0 END) as today_qty
                 FROM inventory_transactions t
                 JOIN inventory i ON t.inventory_id = i.id
                 WHERE i.shelter_id = ? AND t.transaction_type = 'in'";
    $stmt_stat = $pdo->prepare($sql_stat);
    $stmt_stat->execute([$shelter_id]);
    $res_stat = $stmt_stat->fetch(PDO::FETCH_ASSOC);
    
    if ($res_stat) {
        $stats['total_items'] = (int)$res_stat['total_qty'];
        $stats['total_trans'] = (int)$res_stat['total_times'];
        $stats['today_items'] = (int)$res_stat['today_qty'];
    }

    // 3.2 รายการรับบริจาคล่าสุด 10 รายการ
    $sql_recent = "SELECT t.*, i.item_name, i.unit, u.username
                   FROM inventory_transactions t
                   JOIN inventory i ON t.inventory_id = i.id
                   LEFT JOIN users u ON t.user_id = u.id
                   WHERE i.shelter_id = ? AND t.transaction_type = 'in'
                   ORDER BY t.created_at DESC LIMIT 10";
    $stmt_recent = $pdo->prepare($sql_recent);
    $stmt_recent->execute([$shelter_id]);
    $recent_donations = $stmt_recent->fetchAll();

    // 3.3 สินค้าที่ได้รับบริจาคสูงสุด 5 อันดับ
    $sql_top = "SELECT i.item_name, i.unit, SUM(t.quantity) as total_qty
                FROM inventory_transactions t
                JOIN inventory i ON t.inventory_id = i.id
                WHERE i.shelter_id = ? AND t.transaction_type = 'in'
                GROUP BY i.item_name, i.unit
                ORDER BY total_qty DESC LIMIT 5";
    $stmt_top = $pdo->prepare($sql_top);
    $stmt_top->execute([$shelter_id]);
    $top_items = $stmt_top->fetchAll();
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
    <title>Dashboard การรับบริจาค</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-stat { border: none; border-radius: 12px; color: white; transition: all 0.3s; }
        .card-stat:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        .bg-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-orange { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
        .bg-teal { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); color: #0f172a !important; }
        
        .icon-box { font-size: 2rem; opacity: 0.8; }
        .table-custom th { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-hand-holding-heart me-2 text-success"></i>Dashboard การรับบริจาค</h3>
            <?php if($shelter_id && $shelter_info): ?>
                <span class="badge bg-success fs-6"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($shelter_info['name']); ?></span>
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
        <div class="text-center py-5">
            <div class="text-muted"><i class="fas fa-box-open fa-3x mb-3"></i><br>กรุณาเลือกศูนย์พักพิงเพื่อดูข้อมูลการบริจาค</div>
        </div>
    <?php else: ?>

    <!-- 1. Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-stat bg-purple h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2" style="opacity: 0.8;">สิ่งของที่ได้รับทั้งหมด</h6>
                        <h2 class="mb-0 fw-bold"><?php echo number_format($stats['total_items']); ?> <span class="fs-6 fw-normal">รายการ</span></h2>
                    </div>
                    <div class="icon-box"><i class="fas fa-boxes"></i></div>
                </div>
                <div class="mt-3 small" style="opacity: 0.7;">
                    จาก <?php echo number_format($stats['total_trans']); ?> ครั้งการรับมอบ
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat bg-teal h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 text-dark" style="opacity: 0.7;">ได้รับวันนี้</h6>
                        <h2 class="mb-0 fw-bold text-dark">+<?php echo number_format($stats['today_items']); ?></h2>
                    </div>
                    <div class="icon-box text-dark"><i class="fas fa-calendar-day"></i></div>
                </div>
                <div class="mt-3 small text-dark" style="opacity: 0.7;">
                    อัปเดตล่าสุด: <?php echo date('H:i'); ?> น.
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat bg-orange h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2" style="opacity: 0.8;">จัดการคลังสินค้า</h6>
                        <a href="inventory_list.php" class="btn btn-sm btn-light text-dark fw-bold rounded-pill px-3">
                            <i class="fas fa-arrow-right me-1"></i> ไปหน้าคลัง
                        </a>
                    </div>
                    <div class="icon-box"><i class="fas fa-dolly"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- 2. Recent Donations Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 fw-bold border-bottom-0">
                    <i class="fas fa-history me-2 text-primary"></i> รายการรับบริจาคล่าสุด
                </div>
                <div class="table-responsive">
                    <table class="table table-custom table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">วัน-เวลา</th>
                                <th>รายการ</th>
                                <th class="text-end">จำนวน</th>
                                <th>แหล่งที่มา / หมายเหตุ</th>
                                <th>ผู้บันทึก</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_donations)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">ยังไม่มีรายการรับบริจาค</td></tr>
                            <?php else: ?>
                                <?php foreach($recent_donations as $row): ?>
                                <tr>
                                    <td class="ps-4 text-nowrap">
                                        <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($row['item_name']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                            +<?php echo number_format($row['quantity']); ?> <?php echo $row['unit']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            // ดึงข้อมูลแหล่งที่มา (ถ้ามีใน Note)
                                            $note = $row['note'] ?? '-';
                                            echo htmlspecialchars($note);
                                        ?>
                                    </td>
                                    <td>
                                        <div class="small text-secondary"><i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($row['username']); ?></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-center border-top-0 py-3">
                    <a href="distribution_history.php?type=in" class="text-decoration-none small fw-bold">ดูประวัติการรับทั้งหมด <i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        </div>

        <!-- 3. Top Donated Items -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 fw-bold border-bottom-0">
                    <i class="fas fa-trophy me-2 text-warning"></i> สิ่งของที่ได้รับสูงสุด 5 อันดับ
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if(empty($top_items)): ?>
                            <li class="list-group-item text-center py-4 text-muted">ไม่มีข้อมูล</li>
                        <?php else: ?>
                            <?php foreach($top_items as $index => $item): 
                                $rank_color = ($index == 0) ? 'text-warning' : (($index == 1) ? 'text-secondary' : (($index == 2) ? 'text-danger' : 'text-muted'));
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div class="d-flex align-items-center">
                                    <div class="fw-bold fs-5 me-3 <?php echo $rank_color; ?>" style="width: 20px; text-align: center;">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    </div>
                                </div>
                                <span class="badge bg-light text-dark border">
                                    <?php echo number_format($item['total_qty']) . ' ' . $item['unit']; ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>