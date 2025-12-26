<?php
/**
 * request_admin.php
 * หน้าจัดการคำร้องขอพัสดุสำหรับ Admin (รองรับ MySQLi)
 * แก้ไขปัญหา: Unknown column 'i.name' เป็น 'i.item_name'
 * ฟีเจอร์: ค้นหา, กรองสถานะ/ความสำคัญ, สลับ Table/Card, แบ่งหน้า 10 แถว
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger m-4'>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ เฉพาะผู้ดูแลระบบเท่านั้น</div>";
    include 'includes/footer.php';
    exit();
}

// 2. รับค่าพารามิเตอร์ควบคุม (Search, Filter, View, Pagination)
$search = isset($_GET['q']) ? cleanInput($_GET['q']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : 'pending';
$priority_filter = isset($_GET['priority']) ? cleanInput($_GET['priority']) : 'all';
$view_mode = isset($_GET['view']) ? cleanInput($_GET['view']) : 'table'; // Admin มักชอบดูแบบตาราง
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

try {
    // 3. สร้างเงื่อนไข SQL
    $cond = [];
    $params = [];
    $types = "";

    // กรองสถานะ
    if ($status_filter !== 'all') {
        $cond[] = "r.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    // กรองความสำคัญ
    if ($priority_filter !== 'all') {
        $cond[] = "r.priority = ?";
        $params[] = $priority_filter;
        $types .= "s";
    }

    // ค้นหา (ชื่อศูนย์ หรือ ชื่อสินค้า)
    if ($search !== '') {
        $cond[] = "(s.name LIKE ? OR i.item_name LIKE ?)"; // ใช้ item_name แทน name
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    $where_clause = !empty($cond) ? " WHERE " . implode(" AND ", $cond) : "";

    // 4. นับจำนวนรายการทั้งหมด
    $count_sql = "SELECT COUNT(*) as total FROM requests r 
                  JOIN shelters s ON r.shelter_id = s.id 
                  JOIN inventory i ON r.item_id = i.id $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);

    // 5. ดึงข้อมูล (FIX: ใช้ i.item_name)
    $sql = "SELECT r.*, s.name as shelter_name, i.item_name, i.unit, i.quantity as stock_qty
            FROM requests r
            JOIN shelters s ON r.shelter_id = s.id
            JOIN inventory i ON r.item_id = i.id
            $where_clause
            ORDER BY CASE WHEN r.priority = 'high' THEN 1 WHEN r.priority = 'medium' THEN 2 ELSE 3 END, r.created_at DESC
            LIMIT ?, ?";
    
    $types_with_limit = $types . "ii";
    $params_with_limit = array_merge($params, [$offset, $limit]);

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types_with_limit, ...$params_with_limit);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    echo "<div class='alert alert-danger m-4'>Error: " . h($e->getMessage()) . "</div>";
    $requests = [];
}
?>

<div class="container-fluid py-4">
    <!-- Top Header -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-user-shield text-danger me-2"></i>จัดการคำร้องขอ (โหมดผู้ดูแล)</h2>
            <p class="text-muted mb-0 small">พิจารณาอนุมัติหรือปฏิเสธการขอรับสนับสนุนพัสดุจากทุกศูนย์</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="d-flex flex-wrap justify-content-md-end gap-2">
                <!-- Search Form -->
                <form action="" method="GET" class="d-flex bg-white shadow-sm rounded-pill border overflow-hidden">
                    <input type="hidden" name="status" value="<?php echo h($status_filter); ?>">
                    <input type="hidden" name="priority" value="<?php echo h($priority_filter); ?>">
                    <input type="hidden" name="view" value="<?php echo h($view_mode); ?>">
                    <input type="text" name="q" class="form-control border-0 px-3" placeholder="ค้นหาศูนย์/พัสดุ..." value="<?php echo h($search); ?>" style="width: 200px; font-size: 0.85rem;">
                    <button type="submit" class="btn btn-link text-primary border-0"><i class="fas fa-search"></i></button>
                </form>
                
                <div class="btn-group shadow-sm">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'table', 'page' => 1])); ?>" class="btn btn-outline-primary <?php echo $view_mode == 'table' ? 'active' : ''; ?>"><i class="fas fa-table"></i></a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'card', 'page' => 1])); ?>" class="btn btn-outline-primary <?php echo $view_mode == 'card' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Filter Bar -->
    <div class="row g-3 mb-4">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-2">
                    <ul class="nav nav-pills nav-fill">
                        <?php 
                        $tabs = [
                            'pending' => ['label' => 'รออนุมัติ', 'icon' => 'fa-clock', 'color' => 'warning'],
                            'approved' => ['label' => 'อนุมัติแล้ว', 'icon' => 'fa-check-circle', 'color' => 'success'],
                            'rejected' => ['label' => 'ปฏิเสธแล้ว', 'icon' => 'fa-times-circle', 'color' => 'danger'],
                            'all' => ['label' => 'ทั้งหมด', 'icon' => 'fa-list', 'color' => 'dark']
                        ];
                        foreach($tabs as $k => $v):
                            $active = ($status_filter == $k) ? "active bg-{$v['color']} " . ($k=='warning'?'text-dark':'') : "text-muted";
                        ?>
                        <li class="nav-item">
                            <a class="nav-link fw-bold" href="?<?php echo http_build_query(array_merge($_GET, ['status' => $k, 'page' => 1])); ?>">
                                <i class="fas <?php echo $v['icon']; ?> me-1"></i> <?php echo $v['label']; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <select class="form-select border-0 shadow-sm rounded-4 py-2 h-100 fw-bold" onchange="location.href='?'+'<?php echo http_build_query(array_merge($_GET, ['priority' => '', 'page' => 1])); ?>'.replace('priority=', 'priority='+this.value)">
                <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>ระดับความสำคัญ: ทั้งหมด</option>
                <option value="high" class="text-danger" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>ด่วนที่สุด (High)</option>
                <option value="medium" class="text-warning" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>ปานกลาง (Medium)</option>
                <option value="low" class="text-info" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>ปกติ (Low)</option>
            </select>
        </div>
    </div>

    <!-- Data Display -->
    <?php if (empty($requests)): ?>
        <div class="text-center py-5 bg-white rounded-4 shadow-sm">
            <i class="fas fa-folder-open fa-4x text-light mb-3"></i>
            <h5 class="text-muted">ไม่พบข้อมูลคำร้องที่ต้องการ</h5>
            <a href="request_admin.php" class="btn btn-link">ล้างตัวกรอง</a>
        </div>
    <?php else: ?>
        <?php if ($view_mode == 'table'): ?>
            <!-- TABLE VIEW -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-muted">
                            <tr>
                                <th class="ps-4">ระดับ</th>
                                <th>ศูนย์พักพิง</th>
                                <th>พัสดุ</th>
                                <th>จำนวน</th>
                                <th>สต็อกกลาง</th>
                                <th>สถานะ</th>
                                <th class="text-end pe-4">พิจารณา</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge <?php echo $req['priority'] == 'high' ? 'bg-danger' : ($req['priority'] == 'medium' ? 'bg-warning text-dark' : 'bg-info'); ?>">
                                        <?php echo strtoupper($req['priority']); ?>
                                    </span>
                                </td>
                                <td class="fw-bold"><?php echo h($req['shelter_name']); ?></td>
                                <td><?php echo h($req['item_name']); ?></td>
                                <td class="fw-bold text-primary"><?php echo number_format($req['quantity']); ?> <small class="text-muted"><?php echo h($req['unit']); ?></small></td>
                                <td>
                                    <span class="<?php echo $req['stock_qty'] < $req['quantity'] ? 'text-danger fw-bold' : 'text-success'; ?>">
                                        <i class="fas fa-warehouse me-1"></i> <?php echo number_format($req['stock_qty']); ?>
                                    </span>
                                </td>
                                <td><?php echo $req['status']; ?></td>
                                <td class="text-end pe-4">
                                    <?php if ($req['status'] == 'pending'): ?>
                                        <div class="btn-group shadow-sm">
                                            <button onclick="processRequest(<?php echo $req['id']; ?>, 'approved')" class="btn btn-sm btn-success" title="อนุมัติ"><i class="fas fa-check"></i></button>
                                            <button onclick="processRequest(<?php echo $req['id']; ?>, 'rejected')" class="btn btn-sm btn-danger" title="ปฏิเสธ"><i class="fas fa-times"></i></button>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border" onclick="showReason('<?php echo addslashes($req['reason']); ?>')"><i class="fas fa-info-circle"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- CARD VIEW -->
            <div class="row g-4 mb-4">
                <?php foreach ($requests as $req): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 border-top border-5 <?php echo $req['priority']=='high'?'border-danger':($req['priority']=='medium'?'border-warning':'border-info'); ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <h6 class="fw-bold"><?php echo h($req['shelter_name']); ?></h6>
                                <small class="text-muted"><?php echo date('d/m H:i', strtotime($req['created_at'])); ?></small>
                            </div>
                            <div class="bg-light p-3 rounded-3 mb-3 border border-light-subtle">
                                <div class="h5 mb-1 fw-bold"><?php echo h($req['item_name']); ?> <span class="text-primary"><?php echo number_format($req['quantity']); ?></span></div>
                                <div class="small <?php echo $req['stock_qty'] < $req['quantity'] ? 'text-danger fw-bold' : 'text-success'; ?>">
                                    สต็อกคงเหลือ: <?php echo number_format($req['stock_qty']); ?> <?php echo h($req['unit']); ?>
                                </div>
                            </div>
                            <?php if($req['status'] == 'pending'): ?>
                                <div class="d-flex gap-2">
                                    <button onclick="processRequest(<?php echo $req['id']; ?>, 'approved')" class="btn btn-success flex-grow-1 fw-bold btn-sm">อนุมัติ</button>
                                    <button onclick="processRequest(<?php echo $req['id']; ?>, 'rejected')" class="btn btn-outline-danger btn-sm px-3">ปฏิเสธ</button>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-2 border rounded-3 bg-light small fw-bold text-uppercase"><?php echo h($req['status']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            <?php echo renderPagination($page, $total_pages, array_diff_key($_GET, ['page'=>''])); ?>
        </div>
        <div class="text-center text-muted small mt-2">พบทั้งหมด <?php echo $total_records; ?> รายการ</div>
    <?php endif; ?>
</div>

<script>
function processRequest(id, status) {
    const action = status === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ';
    const color = status === 'approved' ? '#198754' : '#dc3545';
    
    Swal.fire({
        title: `ยืนยันการ${action}?`,
        text: status === 'approved' ? "ระบบจะทำการหักสต็อกสินค้าโดยอัตโนมัติ" : "คำร้องนี้จะถูกยกเลิก",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: 'ยืนยันดำเนินการ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `request_process.php?id=${id}&status=${status}`;
        }
    });
}

function showReason(text) {
    Swal.fire({ title: 'เหตุผล/หมายเหตุ', text: text || 'ไม่มีระบุ', icon: 'info' });
}
</script>

<?php include 'includes/footer.php'; ?>