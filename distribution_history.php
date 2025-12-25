<?php
// distribution_history.php
// ระบบดูประวัติการรับบริจาคและแจกจ่ายสิ่งของ (พร้อมระบบแบ่งหน้า Pagination 10 แถว)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// --- 1. Filter Setup ---
$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// Shelter Filter
$shelter_id = ($role === 'admin') ? ($_GET['shelter_id'] ?? '') : $my_shelter_id;

// Date Filter (Default 30 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')); 
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$type       = $_GET['type'] ?? ''; // in, out
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Pagination Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10; // [แก้ไข] กำหนดให้แสดง 10 รายการต่อหน้า
$offset = ($page - 1) * $limit;

// --- 2. Build Queries (UNION Approach) ---

// เงื่อนไขร่วม (Date)
$date_cond_t = " AND t.created_at BETWEEN ? AND ? ";
$date_cond_d = " AND d.given_at BETWEEN ? AND ? ";

// 2.1 Query Part 1: จาก Inventory Transactions
$sql_trans = "SELECT 
                t.created_at as log_date,
                COALESCE(s.name, 'ไม่ระบุศูนย์') as shelter_name,
                COALESCE(i.item_name, 'Unknown Item') as item_name,
                t.transaction_type as type,
                t.quantity,
                COALESCE(i.unit, '-') as unit,
                t.note as detail,
                u.username,
                'inventory_log' as source
              FROM inventory_transactions t
              LEFT JOIN inventory i ON t.inventory_id = i.id
              LEFT JOIN shelters s ON i.shelter_id = s.id
              LEFT JOIN users u ON t.user_id = u.id
              WHERE 1=1 $date_cond_t";

// 2.2 Query Part 2: จาก Distribution Logs
$sql_dist = "SELECT 
                d.given_at as log_date,
                COALESCE(s.name, 'ไม่ระบุศูนย์/ภายนอก') as shelter_name,
                d.item_name,
                'out' as type,
                d.quantity,
                'ชิ้น/อัน' as unit,
                CONCAT('ผู้รับ: ', COALESCE(e.first_name, '-'), ' ', COALESCE(e.last_name, '-')) as detail,
                u.username,
                'distribution_log' as source
             FROM distribution_logs d
             LEFT JOIN evacuees e ON d.evacuee_id = e.id
             LEFT JOIN shelters s ON e.shelter_id = s.id
             LEFT JOIN users u ON d.given_by = u.id
             WHERE 1=1 $date_cond_d";

// --- Apply Parameters & Filters ---

$params_all = [];
// Params for Trans
$params_all[] = $start_date . " 00:00:00";
$params_all[] = $end_date . " 23:59:59";

// Filter Logic for Trans
if ($shelter_id) {
    $sql_trans .= " AND i.shelter_id = ? ";
    $params_all[] = $shelter_id;
}
if ($type) {
    $sql_trans .= " AND t.transaction_type = ? ";
    $params_all[] = $type;
}
if ($search) {
    $sql_trans .= " AND (i.item_name LIKE ? OR u.username LIKE ?) ";
    $params_all[] = "%$search%";
    $params_all[] = "%$search%";
}

// Params for Dist
$params_all[] = $start_date . " 00:00:00";
$params_all[] = $end_date . " 23:59:59";

// Filter Logic for Dist
if ($shelter_id) {
    $sql_dist .= " AND e.shelter_id = ? ";
    $params_all[] = $shelter_id;
}
if ($type == 'in') {
    $sql_dist .= " AND 1=0 "; // Distribution is always OUT
}
if ($search) {
    $sql_dist .= " AND (d.item_name LIKE ? OR u.username LIKE ?) ";
    $params_all[] = "%$search%";
    $params_all[] = "%$search%";
}

try {
    // 1. Count Total Records First
    $count_sql = "SELECT COUNT(*) FROM (
                    $sql_trans
                    UNION ALL
                    $sql_dist
                  ) as combined_count";
    
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params_all);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // 2. Fetch Data with Limit & Offset
    $final_sql = "SELECT * FROM (
                    $sql_trans
                    UNION ALL
                    $sql_dist
                  ) as combined_logs 
                  ORDER BY log_date DESC 
                  LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params_all);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    $logs = [];
    $total_records = 0;
    $total_pages = 0;
}

// Fetch Shelters for Filter (Admin)
$shelters_list = [];
if ($role === 'admin') {
    $shelters_list = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
}

// Helper function to build pagination links
function getQueryString($page) {
    $query = $_GET;
    $query['page'] = $page;
    return http_build_query($query);
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

<div class="container mt-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-history text-primary me-2"></i>ประวัติการรับ-จ่ายสิ่งของ</h3>
            <p class="text-muted small mb-0">รายการทั้งหมด: <?php echo number_format($total_records); ?> รายการ</p>
        </div>
        <a href="distribution_manager.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-box-open me-2"></i> ไปหน้าจัดการสต็อก
        </a>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
    <?php endif; ?>

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
                    <label class="form-label small fw-bold">ค้นหา</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="ชื่อสินค้า/ผู้เกี่ยวข้อง..." value="<?php echo htmlspecialchars($search); ?>">
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
                            <th class="ps-4">รายละเอียด</th>
                            <th>ผู้บันทึก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i><br>
                                ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): 
                                $isIn = $row['type'] == 'in';
                                $badgeColor = $isIn ? 'bg-success' : 'bg-danger';
                                $badgeIcon  = $isIn ? 'fa-arrow-down' : 'fa-arrow-up';
                                $badgeText  = $isIn ? 'รับเข้า' : 'จ่ายออก';
                                $qtyColor   = $isIn ? 'text-success' : 'text-danger';
                                $qtySign    = $isIn ? '+' : '-';
                                
                                $sourceBadge = ($row['source'] == 'inventory_log') 
                                    ? '<span class="badge bg-secondary text-white ms-1" style="font-size:0.6rem;">Stock</span>'
                                    : '<span class="badge bg-info text-dark ms-1" style="font-size:0.6rem;">Distrib</span>';
                            ?>
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <div class="fw-bold"><?php echo date('d/m/Y', strtotime($row['log_date'])); ?></div>
                                    <div class="small text-muted"><?php echo date('H:i', strtotime($row['log_date'])); ?> น.</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border text-truncate" style="max-width: 150px;">
                                        <?php echo htmlspecialchars($row['shelter_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($row['item_name']); ?>
                                        <?php echo $sourceBadge; ?>
                                    </div>
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
                                        if ($row['detail']) {
                                            echo '<div class="text-secondary small text-wrap" style="max-width: 300px;"><i class="fas fa-info-circle me-1"></i> ' . htmlspecialchars($row['detail']) . '</div>';
                                        } else {
                                            echo '<span class="text-muted small">-</span>';
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
            
            <!-- Pagination UI -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white">
                <div class="text-muted small">
                    แสดงหน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?> (ทั้งหมด <?php echo number_format($total_records); ?> รายการ)
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        
                        <!-- First Page -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString(1); ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>

                        <!-- Previous -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString($page - 1); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <!-- Page Numbers (Show +/- 2 pages) -->
                        <?php
                        $range = 2;
                        $initial_num = $page - $range;
                        $condition_limit_num = ($page + $range)  + 1;

                        for ($i = $initial_num; $i < $condition_limit_num; $i++) {
                            if (($i > 0) && ($i <= $total_pages)) {
                                $active = ($page == $i) ? 'active' : '';
                                echo '<li class="page-item '.$active.'"><a class="page-link" href="?'.getQueryString($i).'">'.$i.'</a></li>';
                            }
                        }
                        ?>

                        <!-- Next -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString($page + 1); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>

                        <!-- Last Page -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString($total_pages); ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
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