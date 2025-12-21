<?php
// shelter_form.php
require_once 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';

// 1. ค้นหาเหตุการณ์ที่กำลัง Active (เพื่อนำมาผูกกับศูนย์ที่จะสร้างใหม่)
$stmt = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_incident = $stmt->fetch();

// กรณีเพิ่มศูนย์ใหม่ แต่ไม่มีเหตุการณ์เปิดอยู่ -> ต้องแจ้งเตือนและห้ามทำต่อ
if (!$active_incident && $mode == 'add') {
    $error_message = "ไม่พบภารกิจ/เหตุการณ์ที่กำลังเปิดใช้งาน (Active Incident)<br>กรุณาติดต่อผู้ดูแลระบบเพื่อเปิดภารกิจใหม่ก่อนเพิ่มศูนย์พักพิง";
}

// 2. ถ้าเป็นโหมดแก้ไข ให้ดึงข้อมูลเก่ามาแสดง
$data = [];
if ($mode == 'edit' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        
        // ถ้าแก้ไข เราอาจจะยอมให้แก้ได้แม้ incident ปิดไปแล้ว แต่ต้องระวัง logic
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <!-- Header จะถูก include ใน body แต่เราใส่ style เฉพาะหน้านี้ไว้ก่อนได้ -->
    <style>
        .form-card {
            border-top: 4px solid #1a237e; /* Navy Blue */
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .section-title {
            font-weight: 600;
            color: #283593;
            border-bottom: 2px solid #e8eaf6;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger text-center shadow-sm">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
                    <?php echo $error_message; ?>
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-outline-danger btn-sm">กลับหน้าหลัก</a>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                            <a href="incident_manage.php" class="btn btn-danger btn-sm ms-2">ไปจัดการเหตุการณ์</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>

            <div class="card form-card bg-white border-0">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="fas fa-home text-warning me-2"></i> 
                            <?php echo $mode == 'add' ? 'เพิ่มข้อมูลศูนย์พักพิง' : 'แก้ไขข้อมูลศูนย์พักพิง'; ?>
                        </h5>
                        <?php if($active_incident && $mode == 'add'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                ภายใต้ภารกิจ: <?php echo htmlspecialchars($active_incident['name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <form action="shelter_save.php" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        
                        <!-- สำคัญ: ส่ง incident_id ไปด้วย (ถ้า edit ใช้ของเดิม, ถ้า add ใช้ active) -->
                        <input type="hidden" name="incident_id" value="<?php echo $mode == 'edit' ? $data['incident_id'] : $active_incident['id']; ?>">

                        <div class="mb-4">
                            <h6 class="section-title">1. ข้อมูลทั่วไปของศูนย์</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อศูนย์พักพิง <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-lg" required 
                                       placeholder="ระบุชื่อสถานที่ เช่น วัด..., โรงเรียน..."
                                       value="<?php echo htmlspecialchars($data['name'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">ที่ตั้ง / รายละเอียดสถานที่</label>
                                <textarea name="location" class="form-control" rows="3" required placeholder="บ้านเลขที่, หมู่บ้าน, จุดสังเกต..."><?php echo htmlspecialchars($data['location'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">ความจุรองรับ (คน) <span class="text-danger">*</span></label>
                                    <input type="number" name="capacity" class="form-control" required min="1"
                                           value="<?php echo $data['capacity'] ?? ''; ?>">
                                    <div class="form-text">จำนวนผู้ประสบภัยสูงสุดที่รองรับได้</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">เบอร์โทรศัพท์ติดต่อศูนย์</label>
                                    <input type="text" name="contact_phone" class="form-control" 
                                           placeholder="0xx-xxxxxxx"
                                           value="<?php echo htmlspecialchars($data['contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="section-title">2. สถานะการเปิดให้บริการ</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">สถานะปัจจุบัน</label>
                                    <select name="status" class="form-select">
                                        <option value="open" <?php echo ($data['status'] ?? '') == 'open' ? 'selected' : ''; ?>>🟢 เปิดรับผู้ประสบภัย (Open)</option>
                                        <option value="full" <?php echo ($data['status'] ?? '') == 'full' ? 'selected' : ''; ?>>🟡 เต็มศักยภาพ (Full)</option>
                                        <option value="closed" <?php echo ($data['status'] ?? '') == 'closed' ? 'selected' : ''; ?>>🔴 ปิดให้บริการชั่วคราว (Closed)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                            <a href="shelter_list.php" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-arrow-left me-1"></i> ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                                <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>