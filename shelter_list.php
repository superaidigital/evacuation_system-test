<?php
/**
 * shelter_list.php
 * หน้าจัดการข้อมูลศูนย์พักพิง (เวอร์ชันปรับปรุง: เพิ่มปุ่มล้างตัวกรองและระบบแบ่งหน้า)
 * รองรับการค้นหา, กรองตามเหตุการณ์, และแสดงสถิติความหนาแน่น
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// 1. ตรวจสอบสิทธิ์ (เฉพาะ Admin ที่เข้าถึงหน้านี้ได้)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger m-4'>คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการข้อมูลนี้</div>";
    include 'includes/footer.php';
    exit();
}

// 2. รับค่าการค้นหาและกรองข้อมูล
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$incident_id = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;

// 3. ตั้งค่าระบบแบ่งหน้า (Pagination)
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    // 4. สร้างเงื่อนไขการค้นหา (Where Clause)
    $where = [];
    $params = [];
    $types = "";

    if ($search !== '') {
        $where[] = "(s.name LIKE ? OR s.location LIKE ?)";
        $search_val = "%$search%";
        $params[] = $search_val;
        $params[] = $search_val;
        $types .= "ss";
    }

    if ($incident_id > 0) {
        $where[] = "s.incident_id = ?";
        $params[] = $incident_id;
        $types .= "i";
    }

    $where_sql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

    // 5. นับจำนวนรายการทั้งหมดตามเงื่อนไข
    $count_sql = "SELECT COUNT(*) as total FROM shelters s $where_sql";
    $stmt_count = $conn->prepare($count_sql);
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_records = $stmt_count->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $limit);

    // 6. ดึงข้อมูลศูนย์พักพิงพร้อมระบบ Limit/Offset
    $sql = "SELECT s.*, i.name as incident_name, 
            (SELECT COUNT(*) FROM evacuees WHERE shelter_id = s.id AND check_out_date IS NULL) as current_occupancy 
            FROM shelters s 
            LEFT JOIN incidents i ON s.incident_id = i.id
            $where_sql
            ORDER BY s.id DESC
            LIMIT ?, ?";
    
    $types_limit = $types . "ii";
    $params_limit = array_merge($params, [$offset, $limit]);

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types_limit, ...$params_limit);
    $stmt->execute();
    $shelters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ดึงรายชื่อเหตุการณ์
    $res_incidents = $conn->query("SELECT id, name FROM incidents ORDER BY id DESC");
    $incidents = $res_incidents->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    $shelters = [];
    $total_records = 0;
    $total_pages = 0;
}
?>

<div class="container-fluid py-4">
    <!-- ส่วนหัวหน้าจอ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fas fa-landmark text-primary me-2"></i>ทะเบียนศูนย์พักพิง</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">หน้าหลัก</a></li>
                    <li class="breadcrumb-item active">จัดการข้อมูลศูนย์</li>
                </ol>
            </nav>
        </div>
        <a href="shelter_form.php?mode=add" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4">
            <i class="fas fa-plus-circle me-1"></i> เพิ่มศูนย์พักพิงใหม่
        </a>
    </div>

    <!-- ส่วนกรองข้อมูล (Filters) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">ค้นหา</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ชื่อศูนย์ หรือ สถานที่..." value="<?php echo h($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">เหตุการณ์ภัยพิบัติ</label>
                    <select name="incident_id" class="form-select">
                        <option value="0">--- แสดงทุกเหตุการณ์ ---</option>
                        <?php foreach($incidents as $inc): ?>
                            <option value="<?php echo $inc['id']; ?>" <?php echo $incident_id == $inc['id'] ? 'selected' : ''; ?>>
                                <?php echo h($inc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark fw-bold flex-grow-1">
                            <i class="fas fa-filter me-1"></i> กรองข้อมูล
                        </button>
                        <!-- ปุ่มล้างตัวกรอง -->
                        <?php if($search != '' || $incident_id > 0): ?>
                            <a href="shelter_list.php" class="btn btn-outline-secondary fw-bold" title="ล้างค่าการค้นหา">
                                <i class="fas fa-sync-alt"></i> ล้างค่า
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ตารางข้อมูลศูนย์พักพิง -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-muted small text-uppercase">
                        <th class="ps-4 py-3" width="5%">ID</th>
                        <th width="20%">ชื่อศูนย์พักพิง</th>
                        <th width="15%">เหตุการณ์</th>
                        <th width="20%">สถานที่ตั้ง</th>
                        <th width="15%">ความหนาแน่น</th>
                        <th width="10%">ผู้ติดต่อ</th>
                        <th class="text-end pe-4" width="15%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($shelters)): ?>
                        <?php foreach ($shelters as $s): 
                            $occupancy_perc = ($s['capacity'] > 0) ? ($s['current_occupancy'] / $s['capacity']) * 100 : 0;
                            $bar_color = ($occupancy_perc >= 90) ? 'bg-danger' : (($occupancy_perc >= 70) ? 'bg-warning' : 'bg-success');
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small">#<?php echo $s['id']; ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo h($s['name']); ?></div>
                                <div class="text-muted small">ความจุ: <?php echo number_format($s['capacity']); ?> คน</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo h($s['incident_name'] ?: 'ทั่วไป'); ?></span>
                            </td>
                            <td>
                                <div class="small text-truncate" style="max-width: 200px;" title="<?php echo h($s['location']); ?>">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo h($s['location']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="fw-bold"><?php echo round($occupancy_perc, 1); ?>%</span>
                                    <span class="text-muted"><?php echo $s['current_occupancy']; ?> / <?php echo $s['capacity']; ?></span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 10px;">
                                    <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo min(100, $occupancy_perc); ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-bold"><?php echo h($s['contact_person']); ?></div>
                                <div class="small text-primary"><?php echo h($s['contact_phone']); ?></div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm">
                                    <a href="shelter_details.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-light border" title="ดูรายละเอียด">
                                        <i class="fas fa-eye text-primary"></i>
                                    </a>
                                    <a href="shelter_form.php?id=<?php echo $s['id']; ?>&mode=edit" class="btn btn-sm btn-light border" title="แก้ไข">
                                        <i class="fas fa-edit text-secondary"></i>
                                    </a>
                                    <button onclick="confirmDelete(<?php echo $s['id']; ?>, '<?php echo addslashes($s['name']); ?>')" class="btn btn-sm btn-light border" title="ลบ">
                                        <i class="fas fa-trash-alt text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted opacity-25 mb-3"><i class="fas fa-search-minus fa-4x"></i></div>
                                <h5 class="text-muted">ไม่พบข้อมูลที่ตรงกับเงื่อนไขการค้นหา</h5>
                                <a href="shelter_list.php" class="btn btn-link">ล้างการค้นหาเพื่อแสดงทั้งหมด</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ส่วนแสดงเลขหน้า -->
    <?php if ($total_pages > 1): ?>
        <div class="d-flex flex-column align-items-center mb-5">
            <div class="text-muted small mb-3">แสดง <?php echo count($shelters); ?> จากทั้งหมด <?php echo number_format($total_records); ?> รายการ</div>
            <?php 
                echo renderPagination($page, $total_pages, [
                    'search' => $search,
                    'incident_id' => $incident_id
                ]); 
            ?>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: `คุณกำลังจะลบศูนย์พักพิง "${name}" ข้อมูลผู้อพยพในศูนย์จะได้รับผลกระทบ`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ใช่, ลบข้อมูล',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `shelter_delete.php?id=${id}`;
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>