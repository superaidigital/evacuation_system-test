<?php
// system_log_list.php
// หน้าแสดงประวัติการใช้งานและกิจกรรมในระบบ (Activity Logs) - ปรับปรุงสี Badge ให้ชัดเจน
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// อนุญาตเฉพาะ Admin หรือผู้ที่มีสิทธิ์ดู Log
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// --- 1. Filter Setup ---
$shelter_id = $_GET['shelter_id'] ?? '';
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$action_type = $_GET['action_type'] ?? '';

// --- 2. Pagination Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 50; 
$offset = ($page - 1) * $limit;

// --- 3. Build Query ---
$params = [];
$where = " WHERE 1=1 ";

// Filter Date
if ($start_date && $end_date) {
    $where .= " AND l.created_at BETWEEN ? AND ? ";
    $params[] = $start_date . " 00:00:00";
    $params[] = $end_date . " 23:59:59";
}

// Filter Shelter
if ($shelter_id) {
    $where .= " AND u.shelter_id = ? ";
    $params[] = $shelter_id;
}

// Filter Search
if ($search) {
    $where .= " AND (u.username LIKE ? OR l.action LIKE ? OR l.details LIKE ? OR l.ip_address LIKE ?) ";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

// Filter Action Type
if ($action_type) {
    if ($action_type == 'login') {
        $where .= " AND (l.action LIKE '%login%' OR l.action LIKE '%logout%') ";
    } elseif ($action_type == 'add') {
        $where .= " AND (l.action LIKE '%add%' OR l.action LIKE '%insert%' OR l.action LIKE '%create%' OR l.action LIKE '%register%') ";
    } elseif ($action_type == 'edit') {
        $where .= " AND (l.action LIKE '%edit%' OR l.action LIKE '%update%') ";
    } elseif ($action_type == 'delete') {
        $where .= " AND (l.action LIKE '%delete%' OR l.action LIKE '%remove%') ";
    } elseif ($action_type == 'inventory') {
        $where .= " AND (l.action LIKE '%stock%' OR l.action LIKE '%inventory%' OR l.action LIKE '%distribute%') ";
    }
}

// Count Total
$count_sql = "SELECT COUNT(*) 
              FROM system_logs l
              LEFT JOIN users u ON l.user_id = u.id 
              $where";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Main Query
$sql = "SELECT l.*, u.username, u.role, s.name as shelter_name
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN shelters s ON u.shelter_id = s.id
        $where
        ORDER BY l.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch Shelters
$shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();

// Helper for Pagination Link
function getQueryString($page) {
    $query = $_GET;
    $query['page'] = $page;
    return http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>ประวัติกิจกรรมและความเคลื่อนไหว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bg-gradient-header { background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%); color: white; }
        .table-logs th { background-color: #f1f5f9; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; }
        .table-logs td { font-size: 0.9rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table-logs tr:hover td { background-color: #f8fafc; }
        
        /* --- Customized Action Badges (สีชัดเจนขึ้น) --- */
        .badge-action {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 110px;
        }

        /* Login: Blue/Indigo - Security */
        .badge-login { 
            background-color: #e0e7ff; 
            color: #3730a3; 
            border: 1px solid #c7d2fe; 
        }
        
        /* Add: Emerald Green - Positive Action */
        .badge-add { 
            background-color: #d1fae5; 
            color: #065f46; 
            border: 1px solid #a7f3d0; 
        }
        
        /* Edit: Amber/Orange - Modification */
        .badge-edit { 
            background-color: #fef3c7; 
            color: #92400e; 
            border: 1px solid #fde68a; 
        }
        
        /* Delete: Rose/Red - Critical */
        .badge-delete { 
            background-color: #ffe4e6; 
            color: #9f1239; 
            border: 1px solid #fecdd3; 
        }
        
        /* Inventory: Cyan/Teal - Logistics */
        .badge-inv { 
            background-color: #ccfbf1; 
            color: #115e59; 
            border: 1px solid #99f6e4; 
        }

        /* Other: Slate - Neutral */
        .badge-other { 
            background-color: #f1f5f9; 
            color: #475569; 
            border: 1px solid #e2e8f0; 
        }

        .user-role-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; opacity: 0.9; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid px-4 mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-history text-primary me-2"></i>ประวัติกิจกรรมในระบบ (System Logs)</h3>
            <p class="text-muted small mb-0">ตรวจสอบความเคลื่อนไหว กิจกรรม และการใช้งานของผู้เจ้าหน้าที่ทุกระดับ</p>
        </div>
        <div>
            <span class="badge bg-white text-dark border shadow-sm fs-6 px-3 py-2">
                <i class="fas fa-database text-secondary me-2"></i> รายการทั้งหมด: <strong><?php echo number_format($total_records); ?></strong>
            </span>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-bold text-secondary"><i class="fas fa-filter me-2"></i>กรองข้อมูล (Filters)</h6>
        </div>
        <div class="card-body bg-white rounded-bottom">
            <form action="" method="GET" class="row g-3 align-items-end">
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">ศูนย์พักพิง</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-home text-muted"></i></span>
                        <select name="shelter_id" class="form-select border-start-0 ps-0">
                            <option value="">-- แสดงทุกศูนย์ --</option>
                            <?php foreach($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">ช่วงเวลา</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        <span class="input-group-text bg-light border-start-0 border-end-0 text-muted">ถึง</span>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">ประเภทกิจกรรม</label>
                    <select name="action_type" class="form-select form-select-sm">
                        <option value="">-- ทั้งหมด --</option>
                        <option value="login" <?php echo $action_type == 'login' ? 'selected' : ''; ?>>เข้า/ออกระบบ (Login)</option>
                        <option value="add" <?php echo $action_type == 'add' ? 'selected' : ''; ?>>เพิ่มข้อมูล (Create)</option>
                        <option value="edit" <?php echo $action_type == 'edit' ? 'selected' : ''; ?>>แก้ไขข้อมูล (Update)</option>
                        <option value="delete" <?php echo $action_type == 'delete' ? 'selected' : ''; ?>>ลบข้อมูล (Delete)</option>
                        <option value="inventory" <?php echo $action_type == 'inventory' ? 'selected' : ''; ?>>คลังสินค้า (Stock)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">ค้นหา</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหา Keyword..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        <?php if($shelter_id || $search || $action_type): ?>
                            <a href="system_log_list.php" class="btn btn-light border" title="ล้างค่า"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-logs mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">วัน-เวลา</th>
                            <th style="width: 15%;">ศูนย์พักพิง</th>
                            <th style="width: 18%;">ผู้ใช้งาน</th>
                            <th style="width: 15%;">ประเภทกิจกรรม</th>
                            <th style="width: 27%;">รายละเอียด</th>
                            <th style="width: 10%;">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-3x mb-3 text-secondary opacity-25"></i><br>
                                ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $row): 
                                // Determine Badge Style & Icon
                                $act = strtolower($row['action']);
                                $badgeClass = 'badge-other';
                                $icon = 'fa-circle';
                                
                                if (strpos($act, 'login') !== false || strpos($act, 'logout') !== false) {
                                    $badgeClass = 'badge-login'; $icon = 'fa-key';
                                } elseif (strpos($act, 'add') !== false || strpos($act, 'insert') !== false || strpos($act, 'create') !== false || strpos($act, 'register') !== false) {
                                    $badgeClass = 'badge-add'; $icon = 'fa-plus-circle';
                                } elseif (strpos($act, 'edit') !== false || strpos($act, 'update') !== false) {
                                    $badgeClass = 'badge-edit'; $icon = 'fa-pen';
                                } elseif (strpos($act, 'delete') !== false || strpos($act, 'remove') !== false) {
                                    $badgeClass = 'badge-delete'; $icon = 'fa-trash-alt';
                                } elseif (strpos($act, 'stock') !== false || strpos($act, 'distribute') !== false || strpos($act, 'inventory') !== false) {
                                    $badgeClass = 'badge-inv'; $icon = 'fa-box-open';
                                }

                                // Role Display
                                $roleBadge = 'bg-secondary';
                                if ($row['role'] == 'admin') $roleBadge = 'bg-danger';
                                elseif ($row['role'] == 'staff') $roleBadge = 'bg-primary';
                                elseif ($row['role'] == 'donation_officer') $roleBadge = 'bg-success';
                                
                                // Shelter Display
                                $shelterDisplay = $row['shelter_name'] ? 
                                    '<div class="text-dark small"><i class="fas fa-map-marker-alt text-danger me-1"></i>'.htmlspecialchars($row['shelter_name']).'</div>' : 
                                    '<span class="text-muted small"><em>(ส่วนกลาง)</em></span>';
                            ?>
                            <tr>
                                <td class="ps-4 text-nowrap">
                                    <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                    <div class="small text-muted font-monospace"><?php echo date('H:i:s', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td>
                                    <?php echo $shelterDisplay; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="fw-bold text-dark me-2"><?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?></div>
                                    </div>
                                    <span class="badge user-role-badge <?php echo $roleBadge; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['role'] ?? 'unknown'))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-action <?php echo $badgeClass; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($row['action']); ?>
                                    </span>
                                </td>
                                <td class="text-secondary">
                                    <div class="text-break small" style="max-width: 350px; line-height: 1.5;">
                                        <?php 
                                            echo htmlspecialchars($row['details'] ?? ($row['description'] ?? '-')); 
                                        ?>
                                    </div>
                                </td>
                                <td class="small text-muted font-monospace">
                                    <?php echo htmlspecialchars($row['ip_address'] ?? '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white">
                <div class="small text-muted">
                    หน้า <strong><?php echo $page; ?></strong> จาก <?php echo $total_pages; ?>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString(1); ?>">&laquo; แรกสุด</a>
                        </li>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString($page - 1); ?>">ก่อนหน้า</a>
                        </li>
                        
                        <?php
                        $start_p = max(1, $page - 2);
                        $end_p = min($total_pages, $page + 2);
                        
                        if($start_p > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';

                        for ($i = $start_p; $i <= $end_p; $i++):
                        ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo getQueryString($i); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if($end_p < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>

                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString($page + 1); ?>">ถัดไป</a>
                        </li>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo getQueryString($total_pages); ?>">ท้ายสุด &raquo;</a>
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