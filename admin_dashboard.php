<?php
/**
 * admin_dashboard.php
 * หน้าจอหลักสำหรับผู้ดูแลระบบ (Command Center)
 * รวบรวมสถิติสำคัญจากทุกโมดูลเพื่อให้เห็นภาพรวมสถานการณ์อพยพ
 */
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    // 1. สถิติผู้อพยพรวม
    $evacuee_stats = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN check_out_date IS NULL THEN 1 ELSE 0 END) as current_staying,
        SUM(CASE WHEN check_out_date IS NULL AND (age < 15 OR age >= 60) THEN 1 ELSE 0 END) as vulnerable
        FROM evacuees")->fetch();

    // 2. สถิติศูนย์พักพิง
    $shelter_stats = $pdo->query("SELECT COUNT(*) as total_shelters, SUM(capacity) as total_capacity FROM shelters")->fetch();

    // 3. จำนวนคำร้องค้างดำเนินการ
    $pending_requests = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn();

    // 4. สินค้าที่วิกฤต (เหลือน้อยกว่า 50)
    $low_stock_count = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity < 50")->fetchColumn();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h1 class="h2 fw-bold text-dark">ศูนย์บัญชาการระบบอพยพ</h1>
            <p class="text-muted">Master Command Center - ภาพรวมข้อมูลระดับจังหวัด/เขต</p>
        </div>
        <div class="col-auto">
            <div class="text-end">
                <div class="fw-bold"><?php echo thaiDate(date('Y-m-d')); ?></div>
                <div class="small text-muted">อัปเดตแบบ Real-time</div>
            </div>
        </div>
    </div>

    <!-- แถบสถิติหลัก (Key Metrics) -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small text-uppercase opacity-75">ผู้อพยพทั้งหมด</div>
                            <div class="display-5 fw-bold"><?php echo number_format($evacuee_stats['current_staying']); ?></div>
                        </div>
                        <i class="fas fa-users fa-3x opacity-25"></i>
                    </div>
                    <div class="mt-3 small">
                        <i class="fas fa-chart-line me-1"></i> สะสมทั้งหมด: <?php echo number_format($evacuee_stats['total']); ?> คน
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small text-uppercase opacity-75">กลุ่มเปราะบาง</div>
                            <div class="display-5 fw-bold"><?php echo number_format($evacuee_stats['vulnerable']); ?></div>
                        </div>
                        <i class="fas fa-blind fa-3x opacity-25"></i>
                    </div>
                    <div class="mt-3 small fw-bold">
                        <i class="fas fa-exclamation-circle me-1"></i> ต้องการการดูแลพิเศษ
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small text-uppercase opacity-75">คำร้องค้างจ่าย</div>
                            <div class="display-5 fw-bold"><?php echo number_format($pending_requests); ?></div>
                        </div>
                        <i class="fas fa-hand-holding-heart fa-3x opacity-25"></i>
                    </div>
                    <div class="mt-3 small">
                        <a href="request_admin.php" class="text-white text-decoration-none fw-bold">จัดการคำร้องเดี๋ยวนี้ <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-dark text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small text-uppercase opacity-75">พัสดุวิกฤต</div>
                            <div class="display-5 fw-bold text-warning"><?php echo number_format($low_stock_count); ?></div>
                        </div>
                        <i class="fas fa-box-open fa-3x opacity-25"></i>
                    </div>
                    <div class="mt-3 small">
                        <a href="inventory_dashboard.php" class="text-warning text-decoration-none fw-bold">ตรวจสอบคลังสินค้า <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ส่วนลิงก์ลัด (Quick Navigation) -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark">ระบบบริหารจัดการ</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="shelter_list.php" class="list-group-item list-group-item-action py-3 px-4 border-0">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="fas fa-hospital me-2 text-primary"></i>รายชื่อศูนย์พักพิง</h6>
                                    <small class="text-muted">จัดการข้อมูลศูนย์ ความหนาแน่น และรายชื่อผู้อพยพ</small>
                                </div>
                                <span class="badge bg-light text-dark rounded-pill"><?php echo $shelter_stats['total_shelters']; ?> แห่ง</span>
                            </div>
                        </a>
                        <a href="qr_scanner.php" class="list-group-item list-group-item-action py-3 px-4 border-0">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="fas fa-qrcode me-2 text-dark"></i>โหมดสแกนเนอร์หน้างาน</h6>
                                    <small class="text-muted">ใช้สมาร์ทโฟนสแกนบัตรเพื่อดูข้อมูลหรือบันทึกการรับของ</small>
                                </div>
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                        </a>
                        <a href="line_notify_setup.php" class="list-group-item list-group-item-action py-3 px-4 border-0">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="fab fa-line me-2 text-success"></i>ตั้งค่าการแจ้งเตือน LINE</h6>
                                    <small class="text-muted">จัดการ Token และเงื่อนไขการส่ง Alert เข้ากลุ่มผู้บริหาร</small>
                                </div>
                                <i class="fas fa-cog text-muted"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 text-center">
                    <h5 class="mb-0 fw-bold text-dark">ความหนาแน่นรวม</h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <?php 
                        $occupancy = ($shelter_stats['total_capacity'] > 0) ? ($evacuee_stats['current_staying'] / $shelter_stats['total_capacity']) * 100 : 0;
                    ?>
                    <div class="position-relative mb-4">
                        <h2 class="fw-bold mb-0"><?php echo round($occupancy, 1); ?>%</h2>
                        <small class="text-muted d-block text-center">ของการรองรับ</small>
                    </div>
                    <div class="progress w-100" style="height: 15px;">
                        <div class="progress-bar <?php echo $occupancy > 80 ? 'bg-danger' : 'bg-success'; ?>" 
                             role="progressbar" style="width: <?php echo $occupancy; ?>%"></div>
                    </div>
                    <p class="mt-3 small text-muted text-center">
                        พักอยู่ <?php echo number_format($evacuee_stats['current_staying']); ?> จากที่ว่างทั้งหมด <?php echo number_format($shelter_stats['total_capacity']); ?> ที่
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body { background-color: #f1f5f9; font-family: 'Sarabun', sans-serif; }
    .card { transition: transform 0.2s; }
    .card:hover { transform: translateY(-5px); }
</style>

<?php include 'includes/footer.php'; ?>