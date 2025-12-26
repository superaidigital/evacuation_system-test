<?php
/**
 * หน้าบันทึกการรับของบรรเทาทุกข์แบบรวดเร็ว
 * ออกแบบมาเพื่อทำงานร่วมกับหน้า QR Scanner
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// รับ ID ผู้อพยพจาก QR Scanner
$evacuee_id = isset($_GET['evacuee_id']) ? (int)$_GET['evacuee_id'] : 0;

if ($evacuee_id <= 0) {
    die("ไม่ระบุข้อมูลผู้อพยพ");
}

try {
    // 1. ดึงข้อมูลบุคคลและศูนย์
    $stmt = $pdo->prepare("SELECT e.*, s.name as shelter_name FROM evacuees e JOIN shelters s ON e.shelter_id = s.id WHERE e.id = ?");
    $stmt->execute([$evacuee_id]);
    $evacuee = $stmt->fetch();

    if (!$evacuee) die("ไม่พบข้อมูลผู้อพยพ");

    // 2. ดึงรายการสินค้าที่มีในสต็อกของศูนย์นี้ (หรือสต็อกกลาง)
    $stmt_inv = $pdo->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY name ASC");
    $inventory = $stmt_inv->fetchAll();

    // 3. ตรวจสอบประวัติการรับของวันนี้ (ป้องกันการรับซ้ำในวันเดียวกัน)
    $stmt_history = $pdo->prepare("SELECT d.*, i.name as item_name 
                                  FROM distribution d 
                                  JOIN inventory i ON d.item_id = i.id 
                                  WHERE d.evacuee_id = ? AND DATE(d.distributed_at) = CURDATE()");
    $stmt_history->execute([$evacuee_id]);
    $today_received = $stmt_history->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- ข้อมูลผู้รับ -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-user fa-2x"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold"><?php echo htmlspecialchars($evacuee['first_name'] . ' ' . $evacuee['last_name']); ?></h4>
                        <p class="text-muted mb-0">ศูนย์: <?php echo htmlspecialchars($evacuee['shelter_name']); ?> | กลุ่ม: <?php echo $evacuee['vulnerable_group'] ?: 'ทั่วไป'; ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($today_received)): ?>
                <div class="alert alert-warning border-0 shadow-sm">
                    <h6 class="fw-bold"><i class="fas fa-exclamation-triangle"></i> คำเตือน: วันนี้ได้รับของแล้ว</h6>
                    <ul class="mb-0">
                        <?php foreach($today_received as $item): ?>
                            <li><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['quantity']; ?> หน่วย) เมื่อเวลา <?php echo date('H:i', strtotime($item['distributed_at'])); ?> น.</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- ฟอร์มแจกของ -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-box-open me-2"></i> บันทึกการแจกจ่ายของวันนี้</h5>
                </div>
                <div class="card-body">
                    <form action="distribution_save.php" method="POST">
                        <input type="hidden" name="evacuee_id" value="<?php echo $evacuee_id; ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">เลือกรายการสิ่งของ</label>
                            <select name="item_id" class="form-select form-select-lg" required>
                                <option value="">-- เลือกรายการสินค้า --</option>
                                <?php foreach($inventory as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?> (คงเหลือ: <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">จำนวนที่แจก</label>
                                <input type="number" name="quantity" class="form-control form-control-lg" value="1" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">หมายเหตุ</label>
                                <input type="text" name="note" class="form-control form-control-lg" placeholder="เช่น รับแทนครอบครัว">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg fw-bold">
                                <i class="fas fa-save me-2"></i> ยืนยันการแจกจ่าย
                            </button>
                            <a href="qr_scanner.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i> กลับไปสแกนคนถัดไป
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>