<?php
/**
 * line_notify_setup.php
 * หน้าสำหรับตั้งค่า LINE Notify Token และจัดการเงื่อนไขการแจ้งเตือน
 * ใช้สำหรับเชื่อมต่อระบบเข้ากับกลุ่ม LINE ของทีมบริหาร
 */
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบสิทธิ์ (เฉพาะ Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$status = "";

// บันทึกการตั้งค่า
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['line_token'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        // บันทึกลงตาราง settings (สมมติว่ามีตารางชื่อ settings สำหรับเก็บค่าคอนฟิก)
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                             VALUES ('line_notify_token', :token), ('line_notify_active', :active)
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        $stmt->execute(['token' => $token, 'active' => $is_active]);
        
        $message = "บันทึกการตั้งค่าเรียบร้อยแล้ว";
        $status = "success";
    } catch (PDOException $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $status = "danger";
    }
}

// ดึงค่าปัจจุบัน
$settings = [];
$res = $pdo->query("SELECT * FROM system_settings WHERE setting_key LIKE 'line_notify_%'")->fetchAll();
foreach ($res as $row) { $settings[$row['setting_key']] = $row['setting_value']; }

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-success text-white py-3 border-0 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fab fa-line me-2"></i>ตั้งค่า LINE Notify</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold">LINE Notify Access Token</label>
                            <input type="text" name="line_token" class="form-control form-control-lg" 
                                   value="<?php echo htmlspecialchars($settings['line_notify_token'] ?? ''); ?>" 
                                   placeholder="ใส่ Token จาก notify-bot.line.me">
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i> 
                                วิธีรับ Token: ล็อกอินที่ <a href="https://notify-bot.line.me/" target="_blank">LINE Notify</a> > ออก Access Token > เลือกกลุ่มที่ต้องการแจ้งเตือน
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">เงื่อนไขการแจ้งเตือน</label>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" 
                                       <?php echo ($settings['line_notify_active'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isActive">เปิดใช้งานระบบแจ้งเตือนทั้งหมด</label>
                            </div>
                            <div class="ps-4 text-muted small">
                                <div>- แจ้งเตือนเมื่อสินค้าเหลือต่ำกว่า 50 หน่วย</div>
                                <div>- แจ้งเตือนเมื่อมีการลงทะเบียนกลุ่มเปราะบางใหม่</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg fw-bold">
                                <i class="fas fa-save me-2"></i>บันทึกการตั้งค่า
                            </button>
                            <button type="button" onclick="testNotification()" class="btn btn-outline-secondary">
                                <i class="fas fa-paper-plane me-2"></i>ส่งข้อความทดสอบ
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="inventory_dashboard.php" class="text-decoration-none text-muted">
                    <i class="fas fa-arrow-left me-1"></i> กลับหน้าแดชบอร์ด
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function testNotification() {
    Swal.fire({
        title: 'กำลังทดสอบ...',
        text: 'ระบบจะส่งข้อความทดสอบเข้ากลุ่ม LINE ของคุณ',
        didOpen: () => { Swal.showLoading(); }
    });
    
    // เรียกใช้งาน API ทดสอบ (สร้างแยกเป็นไฟล์เล็กๆ หรือใช้ AJAX)
    fetch('api_test_line.php')
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire('สำเร็จ!', 'ส่งข้อความทดสอบเรียบร้อยแล้ว', 'success');
            } else {
                Swal.fire('ล้มเหลว', data.message, 'error');
            }
        });
}
</script>

<?php include 'includes/footer.php'; ?>