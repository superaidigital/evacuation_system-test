<?php
/**
 * evacuee_list.php
 * หน้าแสดงรายชื่อผู้ประสบภัย/ผู้อพยพ
 * ปรับปรุง: รองรับค่า shelter_id = 'all' และใช้ระบบ MySQLi
 */
require_once 'config/db.php';
require_once 'includes/functions.php'; 
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. รับค่ารหัสศูนย์พักพิง (รองรับทั้งตัวเลข และ 'all')
$shelter_id = isset($_GET['shelter_id']) ? $_GET['shelter_id'] : '';

if (!$shelter_id) {
    die("<div class='alert alert-danger'>ไม่ระบุรหัสศูนย์พักพิง</div>");
}

// --- Pagination Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; 
$offset = ($page - 1) * $limit;

try {
    if ($shelter_id === 'all') {
        // --- กรณีดูทุกศูนย์พักพิง ---
        $shelter = [
            'id' => 'all',
            'name' => 'ทุกศูนย์พักพิง (ภาพรวม)',
            'incident_name' => 'สรุปสถานการณ์รวมทุกพื้นที่',
            'location' => 'ครอบคลุมทุกพื้นที่อพยพ',
            'contact_phone' => 'สายด่วน 1784',
            'capacity' => 0
        ];

        // ดึงความจุรวมทั้งหมด
        $res_cap = $conn->query("SELECT SUM(capacity) as total_cap FROM shelters");
        $row_cap = $res_cap->fetch_assoc();
        $shelter['capacity'] = $row_cap['total_cap'] ?: 0;

        // นับจำนวนผู้ประสบภัยทั้งหมด
        $sql_count = "SELECT COUNT(*) as total FROM evacuees WHERE check_out_date IS NULL";
        $total_records = $conn->query($sql_count)->fetch_assoc()['total'];

        // ดึงรายชื่อผู้ประสบภัยทั้งหมด (แสดงชื่อศูนย์ด้วย)
        $sql_list = "SELECT e.*, s.name as shelter_name 
                     FROM evacuees e 
                     LEFT JOIN shelters s ON e.shelter_id = s.id 
                     WHERE e.check_out_date IS NULL 
                     ORDER BY e.created_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql_list);
        $stmt->bind_param("ii", $offset, $limit);
        
    } else {
        // --- กรณีดูเฉพาะศูนย์ใดศูนย์หนึ่ง ---
        $sql_sh = "SELECT s.*, i.name as incident_name 
                   FROM shelters s 
                   LEFT JOIN incidents i ON s.incident_id = i.id 
                   WHERE s.id = ?";
        $stmt_sh = $conn->prepare($sql_sh);
        $id_int = (int)$shelter_id;
        $stmt_sh->bind_param("i", $id_int);
        $stmt_sh->execute();
        $shelter = $stmt_sh->get_result()->fetch_assoc();

        if (!$shelter) {
            die("<div class='container mt-5 alert alert-warning'>ไม่พบข้อมูลศูนย์พักพิงที่ระบุ</div>");
        }

        // นับจำนวนในศูนย์นี้
        $sql_count = "SELECT COUNT(*) as total FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL";
        $stmt_c = $conn->prepare($sql_count);
        $stmt_c->bind_param("i", $id_int);
        $stmt_c->execute();
        $total_records = $stmt_c->get_result()->fetch_assoc()['total'];

        // ดึงรายชื่อในศูนย์นี้
        $sql_list = "SELECT e.*, ? as shelter_name 
                     FROM evacuees e 
                     WHERE e.shelter_id = ? AND e.check_out_date IS NULL 
                     ORDER BY e.created_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql_list);
        $stmt->bind_param("siii", $shelter['name'], $id_int, $offset, $limit);
    }

    $stmt->execute();
    $evacuees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total_pages = ceil($total_records / $limit);

    // 4. สรุปสถิติสำหรับกลุ่มเปราะบาง (Vulnerable)
    $where_clause = ($shelter_id === 'all') ? "WHERE check_out_date IS NULL" : "WHERE shelter_id = " . (int)$shelter_id . " AND check_out_date IS NULL";
    $sql_vun = "SELECT COUNT(*) as v_count FROM evacuees $where_clause AND (age < 15 OR age >= 60 OR (health_condition != '' AND health_condition IS NOT NULL))";
    $vulnerable_count = $conn->query($sql_vun)->fetch_assoc()['v_count'];

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<style>
    .shelter-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 25px; border-left: 5px solid #fbbf24; }
    .stat-mini-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; text-align: center; }
</style>

<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="shelter-header shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="badge bg-warning text-dark mb-2"><?php echo h($shelter['incident_name']); ?></span>
                <h2 class="fw-bold mb-1"><?php echo h($shelter['name']); ?></h2>
                <div class="opacity-75 small">
                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo h($shelter['location']); ?> | 
                    <i class="fas fa-phone-alt me-1"></i> <?php echo h($shelter['contact_phone']); ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="qr_scanner.php" class="btn btn-outline-light me-1"><i class="fas fa-qrcode me-1"></i>สแกน</a>
                <?php if($shelter_id !== 'all'): ?>
                    <a href="evacuee_form.php?shelter_id=<?php echo $shelter_id; ?>&mode=add" class="btn btn-primary fw-bold">
                        <i class="fas fa-user-plus me-1"></i>ลงทะเบียนใหม่
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-mini-card border-bottom border-primary border-4">
                <div class="text-muted small text-uppercase">ผู้อพยพปัจจุบัน</div>
                <div class="h3 fw-bold mb-0"><?php echo number_format($total_records); ?> <small class="fs-6 text-muted">คน</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini-card border-bottom border-warning border-4">
                <div class="text-muted small text-uppercase">กลุ่มเปราะบาง</div>
                <div class="h3 fw-bold mb-0 text-warning"><?php echo number_format($vulnerable_count); ?> <small class="fs-6 text-muted">คน</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini-card border-bottom border-info border-4">
                <div class="text-muted small text-uppercase">อัตราการรองรับ</div>
                <?php $occ = ($shelter['capacity'] > 0) ? ($total_records / $shelter['capacity']) * 100 : 0; ?>
                <div class="h3 fw-bold mb-0 text-info"><?php echo round($occ, 1); ?>%</div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2 text-primary"></i>บัญชีรายชื่อผู้เข้าพักพิง</h6>
            <input type="text" id="tableSearch" class="form-control form-control-sm w-auto" placeholder="ค้นหาชื่อ..." onkeyup="filterTable()">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="evacueeTable">
                    <thead class="bg-light small text-muted text-uppercase">
                        <tr>
                            <th class="ps-4">ชื่อ-นามสกุล</th>
                            <th>ข้อมูลทั่วไป</th>
                            <?php if($shelter_id === 'all'): ?><th>ศูนย์พักพิง</th><?php endif; ?>
                            <th>สุขภาพ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($total_records > 0): ?>
                            <?php foreach($evacuees as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted">ID Card: <?php echo maskIDCard($row['id_card']); ?></small>
                                </td>
                                <td>
                                    <?php echo ($row['gender'] == 'male' ? 'ชาย' : 'หญิง'); ?> / 
                                    <?php echo (isset($row['age']) ? $row['age'] : calculateAge($row['birth_date'])); ?> ปี
                                </td>
                                <?php if($shelter_id === 'all'): ?>
                                    <td><span class="badge bg-light text-dark border"><?php echo h($row['shelter_name']); ?></span></td>
                                <?php endif; ?>
                                <td>
                                    <?php if($row['health_condition']): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle small"><?php echo h($row['health_condition']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">- ปกติ -</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm">
                                        <a href="evacuee_card.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-light border" title="บัตรประจำตัว">
                                            <i class="fas fa-id-card text-primary"></i>
                                        </a>
                                        <a href="evacuee_form.php?id=<?php echo $row['id']; ?>&mode=edit" class="btn btn-sm btn-light border ms-1" title="แก้ไข">
                                            <i class="fas fa-edit text-secondary"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูลผู้เข้าพักพิง</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top bg-light">
                <?php echo renderPagination($page, $total_pages, ['shelter_id' => $shelter_id]); ?>
            </div>
        </div>
    </div>
</div>

<script>
    function filterTable() {
        let input = document.getElementById("tableSearch").value.toUpperCase();
        let rows = document.getElementById("evacueeTable").getElementsByTagName("tr");
        for (let i = 1; i < rows.length; i++) {
            let nameCell = rows[i].getElementsByTagName("td")[0];
            if (nameCell) {
                let text = nameCell.textContent || nameCell.innerText;
                rows[i].style.display = text.toUpperCase().indexOf(input) > -1 ? "" : "none";
            }
        }
    }
</script>

<?php include 'includes/footer.php'; ?>