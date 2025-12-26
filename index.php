<?php
/**
 * index.php
 * หน้าหลักของระบบ (Main Landing Dashboard)
 * ปรับปรุงให้แสดงผลตามระดับสิทธิ์ (Admin / Staff / Resource Officer)
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// เตรียมตัวแปรสถิติ
$stats = [
    'total_evacuees' => 0,
    'vulnerable' => 0,
    'capacity_percent' => 0,
    'pending_requests' => 0,
    'low_stock' => 0
];

try {
    if ($role === 'admin') {
        // --- มุมมอง Admin: ภาพรวมทั้งจังหวัด ---
        $res = $conn->query("SELECT 
            (SELECT COUNT(*) FROM evacuees WHERE check_out_date IS NULL) as current_stay,
            (SELECT COUNT(*) FROM evacuees WHERE check_out_date IS NULL AND (age < 15 OR age >= 60)) as vulnerable,
            (SELECT SUM(capacity) FROM shelters) as total_cap,
            (SELECT COUNT(*) FROM requests WHERE status = 'pending') as pending_req,
            (SELECT COUNT(*) FROM inventory WHERE quantity < 50) as low_inv");
        $data = $res->fetch_assoc();
        
        $stats['total_evacuees'] = $data['current_stay'];
        $stats['vulnerable'] = $data['vulnerable'];
        $stats['pending_requests'] = $data['pending_req'];
        $stats['low_stock'] = $data['low_inv'];
        $stats['capacity_percent'] = ($data['total_cap'] > 0) ? ($data['current_stay'] / $data['total_cap']) * 100 : 0;

    } else {
        // --- มุมมอง Staff: เฉพาะศูนย์ที่รับผิดชอบ ---
        $stmt = $conn->prepare("SELECT 
            (SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL) as current_stay,
            (SELECT COUNT(*) FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL AND (age < 15 OR age >= 60)) as vulnerable,
            (SELECT capacity FROM shelters WHERE id = ?) as total_cap,
            (SELECT COUNT(*) FROM requests WHERE shelter_id = ? AND status = 'pending') as pending_req");
        $stmt->bind_param("iiii", $my_shelter_id, $my_shelter_id, $my_shelter_id, $my_shelter_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        $stats['total_evacuees'] = $data['current_stay'];
        $stats['vulnerable'] = $data['vulnerable'];
        $stats['pending_requests'] = $data['pending_req'];
        $stats['capacity_percent'] = ($data['total_cap'] > 0) ? ($data['current_stay'] / $data['total_cap']) * 100 : 0;
    }

    // ดึงข่าวประกาศล่าสุด 3 รายการ
    $announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
}
?>

<div class="container-fluid py-4">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-md-7">
            <h2 class="fw-bold text-dark mb-1">สวัสดีคุณ <?php echo $_SESSION['username']; ?></h2>
            <p class="text-muted">ยินดีต้อนรับสู่ระบบบริหารจัดการศูนย์พักพิง (<?php echo $role === 'admin' ? 'โหมดส่วนกลาง' : 'โหมดปฏิบัติการ'; ?>)</p>
        </div>
        <div class="col-md-5 text-md-end">
            <div class="btn-group shadow-sm">
                <a href="qr_scanner.php" class="btn btn-primary fw-bold px-4 py-2">
                    <i class="fas fa-qrcode me-2"></i> สแกนบัตร (Check-in/จ่ายของ)
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Card Row -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-primary">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold mb-1">ผู้อพยพปัจจุบัน</div>
                            <h2 class="fw-bold mb-0 text-primary"><?php echo number_format($stats['total_evacuees']); ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3"><i class="fas fa-users text-primary fa-lg"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-warning">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold mb-1">กลุ่มเปราะบาง</div>
                            <h2 class="fw-bold mb-0 text-warning"><?php echo number_format($stats['vulnerable']); ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3"><i class="fas fa-heart text-warning fa-lg"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-danger">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold mb-1">คำร้องค้างจ่าย</div>
                            <h2 class="fw-bold mb-0 text-danger"><?php echo number_format($stats['pending_requests']); ?></h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded-3"><i class="fas fa-hand-holding-heart text-danger fa-lg"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-info">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold mb-1">ความหนาแน่น</div>
                            <h2 class="fw-bold mb-0 text-info"><?php echo round($stats['capacity_percent']); ?>%</h2>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded-3"><i class="fas fa-hotel text-info fa-lg"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ทางลัดระบบงาน (Quick Links) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-rocket me-2 text-primary"></i>ทางลัดเมนูปฏิบัติงาน</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="evacuee_list.php?shelter_id=<?php echo $my_shelter_id ?: 'all'; ?>" class="text-decoration-none">
                                <div class="p-3 border rounded-3 text-center hover-shadow transition-all bg-light">
                                    <i class="fas fa-address-book fa-2x mb-2 text-primary"></i>
                                    <div class="fw-bold text-dark small">บัญชีรายชื่อ (Roster)</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="distribution_manager.php" class="text-decoration-none">
                                <div class="p-3 border rounded-3 text-center hover-shadow transition-all bg-light">
                                    <i class="fas fa-box-open fa-2x mb-2 text-success"></i>
                                    <div class="fw-bold text-dark small">บันทึกแจกของ</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="request_manager.php" class="text-decoration-none">
                                <div class="p-3 border rounded-3 text-center hover-shadow transition-all bg-light">
                                    <i class="fas fa-paper-plane fa-2x mb-2 text-warning"></i>
                                    <div class="fw-bold text-dark small">ส่งคำร้องขอพัสดุ</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="gis_dashboard.php" class="text-decoration-none">
                                <div class="p-3 border rounded-3 text-center hover-shadow transition-all bg-light">
                                    <i class="fas fa-map-marked-alt fa-2x mb-2 text-info"></i>
                                    <div class="fw-bold text-dark small">แผนที่สถานการณ์</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="inventory_dashboard.php" class="text-decoration-none">
                                <div class="p-3 border rounded-3 text-center hover-shadow transition-all bg-light">
                                    <i class="fas fa-warehouse fa-2x mb-2 text-dark"></i>
                                    <div class="fw-bold text-dark small">คลังทรัพยากร</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="monitor_dashboard.php" target="_blank" class="text-decoration-none">
                                <div class="p-3 border rounded-3 text-center hover-shadow transition-all bg-light">
                                    <i class="fas fa-desktop fa-2x mb-2 text-danger"></i>
                                    <div class="fw-bold text-dark small">เปิดจอ War Room</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ข่าวสารและประกาศล่าสุด -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-bullhorn me-2 text-warning"></i>ประกาศล่าสุด</h5>
                    <a href="announcement_manager.php" class="small text-decoration-none">ดูทั้งหมด</a>
                </div>
                <div class="card-body">
                    <?php if(!empty($announcements)): ?>
                        <?php foreach($announcements as $news): ?>
                            <div class="mb-3 pb-3 border-bottom border-light">
                                <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($news['title']); ?></h6>
                                <p class="small text-muted mb-1 text-truncate"><?php echo strip_tags($news['content']); ?></p>
                                <small class="text-muted" style="font-size: 0.7rem;">
                                    <i class="far fa-clock me-1"></i><?php echo thai_date($news['created_at']); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted small">ยังไม่มีประกาศใหม่ในขณะนี้</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-shadow:hover { 
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; 
        background-color: #fff !important; 
        border-color: var(--active-gold) !important;
    }
    .transition-all { transition: all 0.3s ease; }
</style>

<?php include 'includes/footer.php'; ?>