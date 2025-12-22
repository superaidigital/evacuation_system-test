<?php
// system_log_list.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // renderPagination
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: เฉพาะ Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 2. Filter Logic
$user_search = isset($_GET['user']) ? trim($_GET['user']) : '';
$action_search = isset($_GET['action']) ? trim($_GET['action']) : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// 3. Pagination Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // แสดง 10 รายการต่อหน้า
$offset = ($page - 1) * $limit;

// 4. Query Construction
// Base Condition
$where_sql = " WHERE 1=1 ";
$params = [];

if ($user_search) {
    $where_sql .= " AND (u.username LIKE ? OR u.full_name LIKE ?) ";
    $params[] = "%$user_search%";
    $params[] = "%$user_search%";
}

if ($action_search) {
    $where_sql .= " AND l.action LIKE ? ";
    $params[] = "%$action_search%";
}

if ($date_start) {
    $where_sql .= " AND DATE(l.created_at) >= ? ";
    $params[] = $date_start;
}

if ($date_end) {
    $where_sql .= " AND DATE(l.created_at) <= ? ";
    $params[] = $date_end;
}

// 4.1 Count Total
$sql_count = "SELECT COUNT(*) FROM system_logs l LEFT JOIN users u ON l.user_id = u.id " . $where_sql;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 4.2 Fetch Data with Limit
$sql = "SELECT l.*, u.username, u.full_name 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $where_sql
        ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .filter-card {
            background-color: #fff;
            border-bottom: 3px solid #1e293b;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .log-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .badge-action {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Action Types Colors */
        .bg-login { background-color: #dbeafe; color: #1e40af; } /* Blue */
        .bg-add { background-color: #dcfce7; color: #166534; }   /* Green */
        .bg-edit { background-color: #fef9c3; color: #854d0e; }  /* Yellow */
        .bg-delete { background-color: #fee2e2; color: #991b1b; } /* Red */
        .bg-other { background-color: #f3f4f6; color: #374151; }  /* Gray */
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    
    <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-history text-secondary me-2"></i>ประวัติการใช้งานระบบ
            </h4>
            <span class="text-muted small">System Audit Logs</span>
        </div>
        <div>
            <span class="badge bg-white text-dark border shadow-sm">
                <i class="fas fa-list me-1"></i> รายการทั้งหมด <?php echo number_format($total_records); ?>
            </span>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card filter-card border-0 mb-4 rounded-3">
        <div class="card-body p-3">
            <form action="" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">ผู้ใช้งาน (User)</label>
                    <input type="text" name="user" class="form-control form-control-sm" placeholder="ชื่อ หรือ Username" value="<?php echo htmlspecialchars($user_search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">การกระทำ (Action)</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">ทั้งหมด</option>
                        <option value="Login" <?php echo $action_search=='Login'?'selected':''; ?>>เข้าสู่ระบบ (Login)</option>
                        <option value="Add" <?php echo strpos($action_search,'Add')!==false?'selected':''; ?>>เพิ่มข้อมูล (Add)</option>
                        <option value="Edit" <?php echo strpos($action_search,'Edit')!==false?'selected':''; ?>>แก้ไขข้อมูล (Edit)</option>
                        <option value="Delete" <?php echo strpos($action_search,'Delete')!==false?'selected':''; ?>>ลบข้อมูล (Delete)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">ช่วงเวลา</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="date_start" class="form-control" value="<?php echo $date_start; ?>">
                        <span class="input-group-text">ถึง</span>
                        <input type="date" name="date_end" class="form-control" value="<?php echo $date_end; ?>">
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <button type="submit" class="btn btn-primary btn-sm px-3 shadow-sm"><i class="fas fa-search me-1"></i> ค้นหา</button>
                    <a href="system_log_list.php" class="btn btn-light btn-sm border px-3">ล้างค่า</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-official">
                    <thead>
                        <tr>
                            <th class="ps-4">วัน-เวลา</th>
                            <th>ผู้ทำรายการ</th>
                            <th>ประเภท (Action)</th>
                            <th>รายละเอียด</th>
                            <th class="text-end pe-4">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($logs) > 0): ?>
                            <?php foreach ($logs as $row): ?>
                                <?php
                                    // กำหนดสีตามประเภท Action
                                    $act = strtolower($row['action']);
                                    $bg_class = 'bg-other';
                                    $icon = 'fa-info';
                                    
                                    if(strpos($act, 'login') !== false) { $bg_class = 'bg-login'; $icon = 'fa-sign-in-alt'; }
                                    elseif(strpos($act, 'add') !== false) { $bg_class = 'bg-add'; $icon = 'fa-plus'; }
                                    elseif(strpos($act, 'edit') !== false) { $bg_class = 'bg-edit'; $icon = 'fa-pen'; }
                                    elseif(strpos($act, 'delete') !== false) { $bg_class = 'bg-delete'; $icon = 'fa-trash'; }
                                ?>
                                <tr>
                                    <td class="ps-4 text-nowrap">
                                        <div class="fw-bold text-dark"><?php echo thaiDate(date('Y-m-d', strtotime($row['created_at']))); ?></div>
                                        <div class="small text-muted"><?php echo date('H:i:s', strtotime($row['created_at'])); ?> น.</div>
                                    </td>
                                    <td>
                                        <?php if($row['username']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="log-icon bg-light text-secondary me-2">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['username']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Unknown User (ID: <?php echo $row['user_id']; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-action <?php echo $bg_class; ?>">
                                            <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo htmlspecialchars($row['action']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-dark"><?php echo htmlspecialchars($row['description']); ?></span>
                                    </td>
                                    <td class="text-end pe-4 text-muted font-monospace small">
                                        <?php echo htmlspecialchars($row['ip_address']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูลประวัติการใช้งาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-3 bg-light border-top">
                <?php 
                    echo renderPagination($page, $total_pages, [
                        'user' => $user_search,
                        'action' => $action_search,
                        'date_start' => $date_start,
                        'date_end' => $date_end
                    ]); 
                ?>
                <div class="text-center text-muted small mt-2">
                    แสดง <?php echo count($logs); ?> จากทั้งหมด <?php echo number_format($total_records); ?> รายการ
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>