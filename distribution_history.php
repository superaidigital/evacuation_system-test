<?php
// distribution_history.php
// ระบบดูประวัติการรับบริจาคและแจกจ่ายสิ่งของ (Inventory Logs)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- 1. Filter Setup ---
$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// Shelter Filter: Admin เลือกได้, Staff บังคับศูนย์ตัวเอง
$shelter_id = ($role === 'admin') ? ($_GET['shelter_id'] ?? '') : $my_shelter_id;

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')); // Default 7 วันย้อนหลัง
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$type       = $_GET['type'] ?? ''; // in, out
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- 2. Build Query ---
$params = [];
$where = " WHERE 1=1 ";

// Filter by Shelter
if ($shelter_id) {
    $where .= " AND i.shelter_id = ? ";
    $params[] = $shelter_id;
}

// Filter by Date
if ($start_date && $end_date) {
    $where .= " AND DATE(t.created_at) BETWEEN ? AND ? ";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Filter by Type
if ($type) {
    $where .= " AND t.transaction_type = ? ";
    $params[] = $type;
}

// Search (Item Name or User)
if ($search) {
    $where .= " AND (i.item_name LIKE ? OR u.username LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Pagination Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Count Total
$count_sql = "SELECT COUNT(*) 
              FROM inventory_transactions t 
              JOIN inventory i ON t.inventory_id = i.id 
              LEFT JOIN users u ON t.user_id = u.id 
              $where";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Main Query
// FIX: Removed the subquery that fetches evacuee_name via 'd.inventory_id' 
// to prevent Fatal Error: Unknown column 'd.inventory_id' if DB is not updated.
// To enable names, ensure DB has 'inventory_id' in 'distribution_logs' and uncomment the subquery.
/*
        (SELECT CONCAT(e.first_name, ' ', e.last_name) 
         FROM distribution_logs d 
         JOIN evacuees e ON d.evacuee_id = e.id 
         WHERE d.inventory_id = t.inventory_id 
           AND ABS(TIMESTAMPDIFF(SECOND, d.created_at, t.created_at)) <= 5 
         LIMIT 1) as evacuee_name
*/
$sql = "SELECT t.*, i.item_name, i.unit, u.username, s.name as shelter_name
        FROM inventory_transactions t
        JOIN inventory i ON t.inventory_id = i.id
        JOIN shelters s ON i.shelter_id = s.id
        LEFT JOIN users u ON t.user_id = u.id
        $where
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch Shelters for Filter (Admin)
$shelters_list = [];
if ($role === 'admin') {
    $shelters_list = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>ประวัติการรับ-จ่ายของ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bg-gradient-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-history text-primary me-2"></i>ประวัติการรับ-จ่ายสิ่งของ</h3>
            <p class="text-muted small mb-0">ตรวจสอบรายการย้อนหลัง (Transaction Logs)</p>
        </div>
        <a href="distribution_manager.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-box-open me-2"></i> ไปหน้าจัดการสต็อก
        </a>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-gradient-header rounded">
            <form action="" method="GET" class="row g-3">
                <?php if ($role === 'admin'): ?>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">ศูนย์พักพิง</label>
                    <select name="shelter_id" class="form-select form-select-sm">
                        <option value="">-- ทั้งหมด --</option>
                        <?php foreach ($shelters_list as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small fw-bold">ประเภทรายการ</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="in" <?php echo $type == 'in' ? 'selected' : ''; ?>>รับเข้า (In)</option>
                        <option value="out" <?php echo $type == 'out' ? 'selected' : ''; ?>>จ่ายออก (Out)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">ค้นหา (ชื่อของ/ผู้ทำรายการ)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">วัน-เวลา</th>
                            <th>ศูนย์พักพิง</th>
                            <th>รายการสิ่งของ</th>
                            <th class="text-center">ประเภท</th>
                            <th class="text-end">จำนวน</th>
                            <th class="ps-4">รายละเอียด / ผู้รับ / แหล่งที่มา</th>
                            <th>ผู้ทำรายการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): 
                                $isIn = $row['transaction_type'] == 'in';
                                $badgeColor = $isIn ? 'bg-success' : 'bg-danger';
                                $badgeIcon  = $isIn ? 'fa-arrow-down' : 'fa-arrow-up';
                                $badgeText  = $isIn ? 'รับเข้า' : 'จ่ายออก';
                                $qtyColor   = $isIn ? 'text-success' : 'text-danger';
                                $qtySign    = $isIn ? '+' : '-';
                            ?>
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <div class="fw-bold"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                    <div class="small text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['shelter_name']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['item_name']); ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badgeColor; ?> bg-opacity-75" style="width: 80px;">
                                        <i class="fas <?php echo $badgeIcon; ?> me-1"></i> <?php echo $badgeText; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold <?php echo $qtyColor; ?> fs-6">
                                    <?php echo $qtySign . number_format($row['quantity']); ?>
                                    <small class="text-muted fw-normal ms-1"><?php echo $row['unit']; ?></small>
                                </td>
                                <td class="ps-4">
                                    <?php 
                                    if ($row['transaction_type'] == 'out') {
                                        // Added check for existence of 'evacuee_name' key
                                        if (isset($row['evacuee_name']) && $row['evacuee_name']) {
                                            echo '<div class="text-primary"><i class="fas fa-user me-1"></i> <strong>' . htmlspecialchars($row['evacuee_name']) . '</strong></div>';
                                            echo '<small class="text-muted">ผู้ประสบภัย</small>';
                                        } else {
                                            echo '<span class="text-muted">แจกจ่ายทั่วไป</span>';
                                        }
                                    } else {
                                        // Type IN
                                        $source = $row['source'] ? htmlspecialchars($row['source']) : 'ไม่ระบุ';
                                        echo '<div><i class="fas fa-hand-holding-heart me-1 text-success"></i> ' . $source . '</div>';
                                        echo '<small class="text-muted">ผู้บริจาค/แหล่งที่มา</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="small text-secondary">
                                        <i class="fas fa-user-shield me-1"></i> <?php echo htmlspecialchars($row['username']); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="p-3 border-top bg-light">
                <?php 
                    // สร้าง Pagination Link (Re-use function renderPagination หรือเขียนใหม่แบบง่าย)
                    $queryParams = $_GET; 
                    unset($queryParams['page']);
                    $queryString = http_build_query($queryParams);
                ?>
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo $queryString; ?>">ก่อนหน้า</a>
                        </li>
                        <?php for($i=1; $i<=$total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $queryString; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo $queryString; ?>">ถัดไป</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>