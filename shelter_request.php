<?php
/**
 * shelter_request.php
 * หน้าสำหรับเจ้าหน้าที่ศูนย์พักพิงส่งคำร้องขอพัสดุ/ของบรรเทาทุกข์
 * แก้ไข: เปลี่ยน 'name' เป็น 'item_name' และรองรับ MySQLi
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// 1. ตรวจสอบสิทธิ์ (ต้อง Login)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_shelter_id = $_SESSION['shelter_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// 2. ประมวลผลเมื่อมีการส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $priority = $_POST['priority'];
    $reason = cleanInput($_POST['reason']);
    $target_shelter_id = ($role === 'admin' && isset($_POST['shelter_id'])) ? (int)$_POST['shelter_id'] : $my_shelter_id;

    if ($item_id > 0 && $quantity > 0 && $target_shelter_id > 0) {
        $sql_ins = "INSERT INTO requests (shelter_id, item_id, quantity, priority, status, reason, created_at) 
                    VALUES (?, ?, ?, ?, 'pending', ?, NOW())";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bind_param("iiiss", $target_shelter_id, $item_id, $quantity, $priority, $reason);

        if ($stmt_ins->execute()) {
            $success_msg = "ส่งคำร้องขอเรียบร้อยแล้ว ติดตามสถานะได้ที่หน้าจัดการคำร้อง";
        } else {
            $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    } else {
        $error_msg = "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง";
    }
}

// 3. ดึงรายการสินค้าจาก Inventory (แก้ไข: ใช้ item_name แทน name)
$items = [];
$res_items = $conn->query("SELECT id, item_name, unit FROM inventory ORDER BY item_name ASC");
if ($res_items) {
    while ($row = $res_items->fetch_assoc()) {
        $items[] = $row;
    }
}

// 4. กรณีเป็น Admin ให้เลือกศูนย์ที่จะขอแทนได้
$shelters = [];
if ($is_admin) {
    $res_sh = $conn->query("SELECT id, name FROM shelters ORDER BY name ASC");
    while ($row = $res_sh->fetch_assoc()) { $shelters[] = $row; }
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="request_manager.php" class="text-decoration-none">ระบบคำร้อง</a></li>
                    <li class="breadcrumb-item active">ส่งคำร้องขอใหม่</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-primary">
                        <i class="fas fa-paper-plane me-2"></i>แบบฟอร์มขอรับการสนับสนุนพัสดุ
                    </h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($success_msg): ?>
                        <div class="alert alert-success border-0 shadow-sm mb-4">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                            <div class="mt-2"><a href="request_manager.php" class="btn btn-sm btn-success">ไปที่หน้าจัดการคำร้อง</a></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <!-- ส่วนสำหรับ Admin เลือกศูนย์ -->
                        <?php if($is_admin): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ศูนย์พักพิงที่ต้องการขอ</label>
                            <select name="shelter_id" class="form-select shadow-sm" required>
                                <option value="">--- เลือกศูนย์พักพิง ---</option>
                                <?php foreach($shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo h($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">เลือกรายการสิ่งของ</label>
                            <select name="item_id" class="form-select shadow-sm" required>
                                <option value="">--- เลือกพัสดุที่ต้องการ ---</option>
                                <?php foreach($items as $i): ?>
                                    <option value="<?php echo $i['id']; ?>">
                                        <?php echo h($i['item_name']); ?> (หน่วย: <?php echo h($i['unit']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">จำนวนที่ต้องการ</label>
                                <input type="number" name="quantity" class="form-control shadow-sm" min="1" required placeholder="ระบุตัวเลข">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">ระดับความเร่งด่วน</label>
                                <select name="priority" class="form-select shadow-sm">
                                    <option value="low">ปกติ (Low)</option>
                                    <option value="medium">ปานกลาง (Medium)</option>
                                    <option value="high" class="text-danger fw-bold">ด่วนที่สุด (High)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">เหตุผลความจำเป็น</label>
                            <textarea name="reason" class="form-control shadow-sm" rows="3" placeholder="ระบุเหตุผลหรือรายละเอียดเพิ่มเติม..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="submit_request" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                <i class="fas fa-check-circle me-2"></i>ยืนยันการส่งคำร้อง
                            </button>
                            <a href="request_manager.php" class="btn btn-light border">ยกเลิก</a>
                        </div>
                    </form>

                </div>
            </div>
            
            <div class="text-center mt-4">
                <div class="alert alert-warning border-0 small py-2 d-inline-block">
                    <i class="fas fa-info-circle me-1"></i> คำร้องจะถูกส่งไปยังส่วนกลางเพื่อพิจารณาอนุมัติพัสดุ
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>