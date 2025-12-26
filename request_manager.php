<?php
/**
 * request_manager.php
 * ระบบบริหารจัดการคำร้องขอพัสดุและของบรรเทาทุกข์
 * ฟีเจอร์: สลับมุมมอง, แบ่งหน้า (10 แถว), ค้นหา (Search), และกรอง (Priority Filter)
 * รองรับ MySQLi ($conn)
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'staff';
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;
$is_admin = ($user_role === 'admin');

// 2. จัดการตัวแปรควบคุม (Search, Filters, View Mode, Pagination)
$search = isset($_GET['q']) ? cleanInput($_GET['q']) : '';
$status_filter = isset($_GET['status']) ? cleanInput($_GET['status']) : 'pending';
$priority_filter = isset($_GET['priority']) ? cleanInput($_GET['priority']) : 'all';
$view_mode = isset($_GET['view']) ? cleanInput($_GET['view']) : 'card'; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

try {
    // 3. สร้างเงื่อนไข SQL (Conditions)
    $cond = [];
    $params = [];
    $types = "";

    // กรองตามสิทธิ์
    if (!$is_admin) {
        $cond[] = "r.shelter_id = ?";
        $params[] = $my_shelter_id;
        $types .= "i";
    }

    // กรองตามสถานะ (Tab)
    if ($status_filter !== 'all') {
        $cond[] = "r.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    // กรองตามระดับความสำคัญ (Dropdown)
    if ($priority_filter !== 'all') {
        $cond[] = "r.priority = ?";
        $params[] = $priority_filter;
        $types .= "s";
    }

    // ค้นหา (Keyword) - ค้นหาจากชื่อศูนย์ หรือ ชื่อสินค้า
    if ($search !== '') {
        $cond[] = "(s.name LIKE ? OR i.item_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    $where_clause = !empty($cond) ? " WHERE " . implode(" AND ", $cond) : "";

    // 4. นับจำนวนรายการทั้งหมดเพื่อทำ Pagination
    $count_sql = "SELECT COUNT(*) as total FROM requests r 
                  INNER JOIN shelters s ON r.shelter_id = s.id
                  INNER JOIN inventory i ON r.item_id = i.id 
                  $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);

    // 5. ดึงข้อมูลรายการ
    $sql = "SELECT r.*, s.name as shelter_name, i.item_name, i.unit, i.quantity as stock_qty
            FROM requests r
            INNER JOIN shelters s ON r.shelter_id = s.id
            INNER JOIN inventory i ON r.item_id = i.id
            $where_clause
            ORDER BY CASE 
                WHEN r.priority = 'high' THEN 1 
                WHEN r.priority = 'medium' THEN 2 
                ELSE 3 END, r.created_at DESC
            LIMIT ?, ?";
    
    $types_with_limit = $types . "ii";
    $params_with_limit = array_merge($params, [$offset, $limit]);

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types_with_limit, ...$params_with_limit);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Database Error: " . h($e->getMessage()) . "</div>";
    $requests = [];
}
?>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row align-items-center mb-4">
        <div class="col-md-5">
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-bullhorn text-primary me-2"></i>ศูนย์ประสานงานและคำร้อง</h2>
            <p class="text-muted mb-0 small">จัดการและตรวจสอบรายการพัสดุที่ขอสนับสนุน (แสดงหน้าละ <?php echo $limit; ?> รายการ)</p>
        </div>
        <div class="col-md-7 text-md-end mt-3 mt-md-0">
            <div class="d-flex flex-wrap justify-content-md-end gap-2">
                <!-- ค้นหาแบบ Keyword -->
                <form action="" method="GET" class="d-flex shadow-sm rounded-pill overflow-hidden bg-white border">
                    <input type="hidden" name="status" value="<?php echo h($status_filter); ?>">
                    <input type="hidden" name="priority" value="<?php echo h($priority_filter); ?>">
                    <input type="hidden" name="view" value="<?php echo h($view_mode); ?>">
                    <input type="text" name="q" class="form-control border-0 px-3" placeholder="ค้นหาศูนย์/พัสดุ..." value="<?php echo h($search); ?>" style="width: 180px; font-size: 0.85rem;">
                    <button type="submit" class="btn btn-white text-primary border-0"><i class="fas fa-search"></i></button>
                </form>

                <div class="btn-group shadow-sm">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'card', 'page' => 1])); ?>" 
                       class="btn btn-outline-primary <?php echo $view_mode == 'card' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'table', 'page' => 1])); ?>" 
                       class="btn btn-outline-primary <?php echo $view_mode == 'table' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                    </a>
                </div>
                <a href="shelter_request.php" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4">
                    <i class="fas fa-plus-circle me-1"></i>ส่งคำร้องใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar (Tabs & Priority Dropdown) -->
    <div class="row g-3 mb-4">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-2">
                    <ul class="nav nav-pills nav-fill">
                        <?php 
                        $statuses = [
                            'pending' => ['label' => 'รอการดำเนินการ', 'icon' => 'fa-clock', 'color' => 'warning'],
                            'approved' => ['label' => 'อนุมัติแล้ว', 'icon' => 'fa-check-circle', 'color' => 'success'],
                            'rejected' => ['label' => 'ปฏิเสธ', 'icon' => 'fa-times-circle', 'color' => 'danger'],
                            'all' => ['label' => 'ทั้งหมด', 'icon' => 'fa-list', 'color' => 'dark']
                        ];
                        foreach ($statuses as $key => $val): 
                            $active_class = ($status_filter == $key) ? "active bg-{$val['color']} " . ($key == 'warning' ? 'text-dark' : '') : "text-muted";
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_class; ?> fw-bold" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['status' => $key, 'page' => 1])); ?>">
                                <i class="fas <?php echo $val['icon']; ?> me-1"></i> <?php echo $val['label']; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <select class="form-select border-0 shadow-sm rounded-4 py-2 h-100 fw-bold text-muted" 
                    onchange="location.href='?'+'<?php echo http_build_query(array_merge($_GET, ['priority' => '', 'page' => 1])); ?>'.replace('priority=', 'priority='+this.value)">
                <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>ความเร่งด่วน: ทั้งหมด</option>
                <option value="high" class="text-danger" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>ด่วนที่สุด (High)</option>
                <option value="medium" class="text-warning" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>ปานกลาง (Medium)</option>
                <option value="low" class="text-info" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>ปกติ (Low)</option>
            </select>
        </div>
    </div>

    <?php if (empty($requests)): ?>
        <div class="text-center py-5">
            <div class="text-muted opacity-25 mb-3"><i class="fas fa-inbox fa-4x"></i></div>
            <h5 class="text-muted">ไม่พบรายการคำร้องที่ตรงกับเงื่อนไข</h5>
            <a href="request_manager.php" class="btn btn-link">ล้างตัวกรองทั้งหมด</a>
        </div>
    <?php else: ?>

        <?php if ($view_mode == 'table'): ?>
            <!-- Table View -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-muted text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">ความเร่งด่วน</th>
                                <th>ศูนย์พักพิง</th>
                                <th>รายการพัสดุ</th>
                                <th>จำนวน</th>
                                <th>วันที่ขอ</th>
                                <th>สถานะ</th>
                                <th class="text-end pe-4">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge <?php echo $req['priority'] == 'high' ? 'bg-danger' : ($req['priority'] == 'medium' ? 'bg-warning text-dark' : 'bg-info'); ?> px-2 py-1">
                                        <?php echo strtoupper($req['priority']); ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark"><?php echo h($req['shelter_name']); ?></td>
                                <td><?php echo h($req['item_name']); ?></td>
                                <td><?php echo number_format($req['quantity']); ?> <small class="text-muted"><?php echo h($req['unit']); ?></small></td>
                                <td class="small"><?php echo date('d/m/Y H:i', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <?php if($req['status'] == 'pending'): ?>
                                        <span class="text-warning fw-bold small"><i class="fas fa-spinner fa-spin me-1"></i>รอพิจารณา</span>
                                    <?php elseif($req['status'] == 'approved'): ?>
                                        <span class="text-success fw-bold small"><i class="fas fa-check-circle me-1"></i>อนุมัติแล้ว</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold small"><i class="fas fa-times-circle me-1"></i>ปฏิเสธ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if($is_admin && $req['status'] == 'pending'): ?>
                                        <button onclick="handleRequest(<?php echo $req['id']; ?>, 'approved')" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                        <button onclick="handleRequest(<?php echo $req['id']; ?>, 'rejected')" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($req)); ?>)"><i class="fas fa-eye text-primary"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Card View -->
            <div class="row g-4 mb-4">
                <?php foreach ($requests as $req): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100 border-top border-5 <?php 
                            echo $req['priority'] == 'high' ? 'border-danger' : ($req['priority'] == 'medium' ? 'border-warning' : 'border-info'); 
                        ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="fw-bold mb-0 text-dark"><?php echo h($req['shelter_name']); ?></h6>
                                    <span class="text-muted small"><?php echo date('d/m H:i', strtotime($req['created_at'])); ?></span>
                                </div>
                                <div class="bg-light p-3 rounded-3 mb-3 border border-light-subtle">
                                    <div class="h5 mb-0 fw-bold">
                                        <?php echo h($req['item_name']); ?> 
                                        <span class="text-primary mx-1"><?php echo number_format($req['quantity']); ?></span> 
                                        <small class="text-muted"><?php echo h($req['unit']); ?></small>
                                    </div>
                                    <?php if($is_admin && $req['status'] == 'pending'): ?>
                                        <div class="mt-2 small <?php echo $req['stock_qty'] < $req['quantity'] ? 'text-danger fw-bold' : 'text-success'; ?>" style="font-size: 0.7rem;">
                                            <i class="fas fa-warehouse me-1 text-secondary"></i> สต็อกคงเหลือ: <?php echo number_format($req['stock_qty']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <p class="small text-muted mb-4" style="min-height: 40px;">
                                    <strong>เหตุผล:</strong> <?php echo h($req['reason'] ?: '-'); ?>
                                </p>
                                <div class="d-flex gap-2">
                                    <?php if($is_admin && $req['status'] == 'pending'): ?>
                                        <button onclick="handleRequest(<?php echo $req['id']; ?>, 'approved')" class="btn btn-success flex-grow-1 fw-bold btn-sm shadow-sm">อนุมัติ</button>
                                        <button onclick="handleRequest(<?php echo $req['id']; ?>, 'rejected')" class="btn btn-outline-danger btn-sm px-3 shadow-sm"><i class="fas fa-times"></i></button>
                                    <?php else: ?>
                                        <div class="w-100 py-2 text-center border rounded-3 bg-light small fw-bold text-uppercase">
                                            <?php echo h($req['status']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <div class="mt-2">
            <?php 
                echo renderPagination($page, $total_pages, [
                    'status' => $status_filter, 
                    'view' => $view_mode, 
                    'priority' => $priority_filter,
                    'q' => $search
                ]); 
            ?>
            <div class="text-center text-muted small mt-2">
                พบทั้งหมด <?php echo number_format($total_records); ?> รายการ
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function handleRequest(id, status) {
    const action = status === 'approved' ? 'อนุมัติ' : 'ปฏิเสธ';
    const color = status === 'approved' ? '#198754' : '#dc3545';
    Swal.fire({
        title: `ยืนยันการ${action}?`,
        text: "ตรวจสอบความถูกต้องของรายการและจำนวนเรียบร้อยแล้ว",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `request_process.php?id=${id}&status=${status}`;
        }
    });
}

function viewDetails(data) {
    Swal.fire({
        title: 'รายละเอียดคำร้อง',
        html: `
            <div class="text-start small">
                <p><strong>ศูนย์:</strong> ${data.shelter_name}</p>
                <p><strong>รายการ:</strong> ${data.item_name} ${data.quantity} ${data.unit}</p>
                <p><strong>เหตุผล:</strong> ${data.reason || '-'}</p>
                <p><strong>ความเร่งด่วน:</strong> ${data.priority.toUpperCase()}</p>
                <p><strong>สถานะ:</strong> ${data.status}</p>
                <p><strong>วันที่ขอ:</strong> ${data.created_at}</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'รับทราบ'
    });
}
</script>

<style>
    .nav-pills .nav-link { border-radius: 12px; transition: all 0.3s; font-size: 0.85rem; }
    .card { transition: all 0.2s; border-radius: 15px; }
    .card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; }
    .form-control:focus, .form-select:focus { box-shadow: none; border-color: var(--active-gold); }
    .btn-group .btn { border-radius: 10px !important; margin: 0 2px; }
</style>

<?php include 'includes/footer.php'; ?>