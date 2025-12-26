<?php
/**
 * inventory_dashboard.php
 * หน้าจอสรุปยอดสินค้าคงเหลือและภาพรวมการแจกจ่าย (ฉบับปรับปรุง MySQLi)
 * แก้ไข Error: Unknown column 'i.name' เป็น 'i.item_name'
 */
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/**
 * 1. ดึงข้อมูลสินค้าที่ "เหลือน้อย" (Critical Stock)
 * ดึงรายการที่จำนวนต่ำกว่า 50 หน่วย
 */
$low_stock_items = [];
$sql_low = "SELECT * FROM inventory WHERE quantity < 50 ORDER BY quantity ASC";
$res_low = $conn->query($sql_low);
if ($res_low) {
    while($row = $res_low->fetch_assoc()) {
        $low_stock_items[] = $row;
    }
}

/**
 * 2. ดึงประวัติการแจกจ่ายล่าสุด 10 รายการ
 * FIX: เปลี่ยนจาก i.name เป็น i.item_name ตามโครงสร้าง Table จริง
 */
$latest_logs = [];
$sql_logs = "SELECT d.*, e.first_name, e.last_name, i.item_name 
             FROM distribution d 
             JOIN evacuees e ON d.evacuee_id = e.id 
             JOIN inventory i ON d.item_id = i.id 
             ORDER BY d.distributed_at DESC LIMIT 10";
$res_logs = $conn->query($sql_logs);
if ($res_logs) {
    while($row = $res_logs->fetch_assoc()) {
        $latest_logs[] = $row;
    }
}

/**
 * 3. เตรียมข้อมูลสำหรับกราฟวงกลม (Doughnut Chart)
 */
$chart_labels = [];
$chart_data = [];
$sql_chart = "SELECT category, SUM(quantity) as total_qty FROM inventory GROUP BY category";
$res_chart = $conn->query($sql_chart);
if ($res_chart) {
    while($row = $res_chart->fetch_assoc()) {
        $chart_labels[] = $row['category'] ?: 'ไม่ระบุหมวดหมู่';
        $chart_data[] = (int)$row['total_qty'];
    }
}
?>

<!-- Load Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1 text-dark"><i class="fas fa-warehouse text-primary me-2"></i>ระบบบริหารทรัพยากร</h2>
            <p class="text-muted mb-0">ภาพรวมคลังสินค้าและสถิติการช่วยเหลือผู้ประสบภัย</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.location.reload()" class="btn btn-outline-secondary">
                <i class="fas fa-sync-alt"></i>
            </button>
            <a href="inventory_list.php" class="btn btn-primary shadow-sm fw-bold">
                <i class="fas fa-boxes me-1"></i> จัดการคลังสินค้า
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- กราฟสัดส่วนทรัพยากร -->
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-info"></i>สัดส่วนพัสดุรายหมวดหมู่</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 280px;">
                    <?php if(!empty($chart_data)): ?>
                        <canvas id="categoryChart"></canvas>
                    <?php else: ?>
                        <div class="text-muted small text-center">ไม่มีข้อมูลพัสดุในระบบ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- สินค้าวิกฤต (เหลือน้อย) -->
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>รายการสินค้าวิกฤต (Low Stock)</h6>
                    <span class="badge bg-danger rounded-pill"><?php echo count($low_stock_items); ?> รายการ</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small">
                                <tr>
                                    <th class="ps-4">ชื่อสินค้า</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-end">คงเหลือ</th>
                                    <th width="30%">ระดับความวิกฤต</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($low_stock_items as $item): 
                                    $perc = min(100, ($item['quantity'] / 50) * 100);
                                ?>
                                <tr class="border-bottom">
                                    <td class="ps-4">
                                        <!-- FIX: เปลี่ยนจาก $item['name'] เป็น $item['item_name'] -->
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    </td>
                                    <td><small class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['category']); ?></small></td>
                                    <td class="text-end">
                                        <span class="h6 mb-0 text-danger fw-bold"><?php echo number_format($item['quantity']); ?></span>
                                        <small class="text-muted"><?php echo $item['unit']; ?></small>
                                    </td>
                                    <td class="pe-4">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $perc; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($low_stock_items)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted small">สินค้าทุกรายการมีความพร้อมใช้งาน (เกิน 50 หน่วย)</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ประวัติการแจกจ่ายล่าสุด -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center px-4">
            <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-warning"></i>ประวัติการแจกจ่ายล่าสุด (Distribution Feed)</h6>
            <a href="distribution_history.php" class="btn btn-sm btn-link text-decoration-none">ดูประวัติทั้งหมด <i class="fas fa-chevron-right ms-1"></i></a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light small text-muted">
                        <tr>
                            <th class="ps-4">วันที่ - เวลา</th>
                            <th>ผู้รับมอบ</th>
                            <th>พัสดุที่แจก</th>
                            <th class="text-center">จำนวน</th>
                            <th>หมายเหตุ / ผู้บันทึก</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach($latest_logs as $log): ?>
                        <tr>
                            <td class="ps-4">
                                <div><?php echo date('d/m/Y', strtotime($log['distributed_at'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($log['distributed_at'])); ?> น.</small>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary-emphasis border border-primary-subtle">
                                    <!-- FIX: ใช้ item_name ตามที่ Select มาจาก SQL -->
                                    <?php echo htmlspecialchars($log['item_name']); ?>
                                </span>
                            </td>
                            <td class="text-center fw-bold text-success">+ <?php echo number_format($log['quantity']); ?></td>
                            <td>
                                <div class="text-muted"><?php echo htmlspecialchars($log['note'] ?: '-'); ?></div>
                                <small class="text-muted" style="font-size: 0.65rem;">Staff ID: #<?php echo $log['distributed_by']; ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($latest_logs)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่พบประวัติการแจกจ่ายในระบบ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('categoryChart');
        if(ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($chart_data); ?>,
                        backgroundColor: [
                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'
                        ],
                        hoverOffset: 15,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                boxWidth: 12,
                                font: { size: 11, family: "'Prompt', sans-serif" }
                            }
                        }
                    },
                    cutout: '75%'
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>