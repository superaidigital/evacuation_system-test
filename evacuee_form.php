<?php
// evacuee_form.php
require_once 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$selected_shelter_id = isset($_GET['shelter_id']) ? $_GET['shelter_id'] : '';

// ตัวแปรสำหรับเก็บข้อมูล Incident ที่เรากำลังทำงานด้วย
$current_incident_id = 0;
$current_incident_name = '';
$data = []; // เก็บข้อมูลผู้ประสบภัย (กรณี Edit)

// --- LOGIC การดึงข้อมูล ---

if ($mode == 'edit' && $id) {
    // 1. กรณีแก้ไข: ดึงข้อมูลเดิมออกมา
    $stmt = $pdo->prepare("SELECT * FROM evacuees WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();

    if ($data) {
        $current_incident_id = $data['incident_id']; // ใช้ Incident เดิมของข้อมูลชุดนี้
        $selected_shelter_id = $data['shelter_id'];
        
        // หาชื่อเหตุการณ์
        $stmt_inc = $pdo->prepare("SELECT name FROM incidents WHERE id = ?");
        $stmt_inc->execute([$current_incident_id]);
        $inc_data = $stmt_inc->fetch();
        $current_incident_name = $inc_data ? $inc_data['name'] : 'ไม่ระบุเหตุการณ์';
    } else {
        die("ไม่พบข้อมูลผู้ประสบภัย ID: $id");
    }

} else {
    // 2. กรณีเพิ่มใหม่: ต้องหา Incident ที่ Active อยู่
    $stmt = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $active_incident = $stmt->fetch();

    if ($active_incident) {
        $current_incident_id = $active_incident['id'];
        $current_incident_name = $active_incident['name'];
    }
}

// --- LOGIC ดึงรายชื่อศูนย์พักพิง (ตาม Incident ID ที่ได้) ---
$shelters = [];
if ($current_incident_id) {
    $sql_shelter = "SELECT id, name, location, capacity, 
                    (SELECT COUNT(*) FROM evacuees WHERE shelter_id = shelters.id AND check_out_date IS NULL) as used
                    FROM shelters 
                    WHERE incident_id = ? AND status != 'closed'
                    ORDER BY name ASC";
    $stmt_s = $pdo->prepare($sql_shelter);
    $stmt_s->execute([$current_incident_id]);
    $shelters = $stmt_s->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        /* Custom Styles for Form */
        .form-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); /* Navy Gradient */
            color: white;
            padding: 25px;
            border-radius: 12px 12px 0 0;
            border-bottom: 4px solid #fbbf24; /* Gold Border */
        }
        
        .form-section-title {
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 10px;
        }
        
        .form-section-title i {
            width: 35px;
            height: 35px;
            background-color: #f1f5f9;
            color: #0f172a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #fbbf24;
            box-shadow: 0 0 0 0.25rem rgba(251, 191, 36, 0.25);
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">
    
    <!-- กรณีไม่มี Incident (เฉพาะ Add Mode) -->
    <?php if ($mode == 'add' && !$current_incident_id): ?>
        <div class="alert alert-danger text-center shadow-sm p-5">
            <div class="mb-3"><i class="fas fa-exclamation-triangle fa-3x"></i></div>
            <h4 class="fw-bold">ไม่พบภารกิจที่กำลังดำเนินการ (No Active Incident)</h4>
            <p class="text-muted">กรุณาติดต่อผู้ดูแลระบบเพื่อเปิดภารกิจใหม่ก่อนเริ่มลงทะเบียนผู้ประสบภัย</p>
            <div class="mt-4">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i> กลับหน้าหลัก</a>
                <?php if($_SESSION['role'] == 'admin'): ?>
                    <a href="incident_manager.php" class="btn btn-danger ms-2"><i class="fas fa-cog me-2"></i> ไปเปิดภารกิจ</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                
                <div class="card border-0 shadow-lg rounded-3">
                    <div class="form-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1 fw-bold"><i class="fas fa-user-edit me-2"></i> ทะเบียนผู้ประสบภัย</h4>
                            <small class="opacity-75">แบบฟอร์มบันทึกข้อมูล (Evacuee Registration Form)</small>
                        </div>
                        <div class="text-end d-none d-sm-block">
                            <div class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">
                                <i class="fas fa-layer-group me-1"></i> ภารกิจ: <?php echo htmlspecialchars($current_incident_name); ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5 bg-white">
                        <form action="evacuee_save.php" method="POST" class="needs-validation">
                            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="incident_id" value="<?php echo $current_incident_id; ?>">

                            <!-- Section 1: ข้อมูลการเข้าพัก -->
                            <div class="mb-5">
                                <div class="form-section-title">
                                    <i class="fas fa-campground"></i> 1. ข้อมูลการเข้าพัก (Shelter Info)
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">เลือกศูนย์พักพิง <span class="text-danger">*</span></label>
                                        <select name="shelter_id" class="form-select form-select-lg bg-light" required>
                                            <option value="" selected disabled>-- กรุณาเลือกศูนย์พักพิง --</option>
                                            <?php foreach ($shelters as $s): ?>
                                                <?php 
                                                    $is_full = ($s['capacity'] > 0 && $s['used'] >= $s['capacity']);
                                                    $full_label = $is_full ? '(เต็ม)' : '';
                                                    $vacancy = ($s['capacity'] > 0) ? ($s['capacity'] - $s['used']) : '-';
                                                    $style = $is_full ? 'color: #ef4444;' : '';
                                                ?>
                                                <option value="<?php echo $s['id']; ?>" style="<?php echo $style; ?>"
                                                    <?php echo ($selected_shelter_id == $s['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($s['name']); ?> 
                                                    (ว่าง <?php echo $vacancy; ?> คน) <?php echo $full_label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if(empty($shelters)): ?>
                                            <div class="text-danger small mt-2">
                                                <i class="fas fa-info-circle"></i> ยังไม่มีศูนย์พักพิงในภารกิจนี้ <a href="shelter_form.php?mode=add" class="fw-bold text-danger">คลิกเพื่อเพิ่มศูนย์ใหม่</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: ข้อมูลส่วนตัว -->
                            <div class="mb-5">
                                <div class="form-section-title">
                                    <i class="fas fa-id-card"></i> 2. ข้อมูลส่วนตัว (Personal Info)
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">เลขบัตรประชาชน (13 หลัก)</label>
                                        <input type="text" name="id_card" class="form-control" maxlength="13" 
                                               value="<?php echo htmlspecialchars($data['id_card'] ?? ''); ?>" placeholder="ระบุเลขบัตร (ถ้ามี)">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">คำนำหน้า</label>
                                        <select name="prefix" class="form-select">
                                            <option value="นาย" <?php echo ($data['prefix']??'')=='นาย'?'selected':''; ?>>นาย</option>
                                            <option value="นาง" <?php echo ($data['prefix']??'')=='นาง'?'selected':''; ?>>นาง</option>
                                            <option value="นางสาว" <?php echo ($data['prefix']??'')=='นางสาว'?'selected':''; ?>>นางสาว</option>
                                            <option value="ด.ช." <?php echo ($data['prefix']??'')=='ด.ช.'?'selected':''; ?>>ด.ช.</option>
                                            <option value="ด.ญ." <?php echo ($data['prefix']??'')=='ด.ญ.'?'selected':''; ?>>ด.ญ.</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">ชื่อจริง <span class="text-danger">*</span></label>
                                        <input type="text" name="first_name" class="form-control" required
                                               value="<?php echo htmlspecialchars($data['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                        <input type="text" name="last_name" class="form-control" required
                                               value="<?php echo htmlspecialchars($data['last_name'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">เพศ</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="gender" id="gender_m" value="male" <?php echo ($data['gender']??'')=='male'?'checked':''; ?>>
                                            <label class="btn btn-outline-secondary" for="gender_m">ชาย</label>

                                            <input type="radio" class="btn-check" name="gender" id="gender_f" value="female" <?php echo ($data['gender']??'')=='female'?'checked':''; ?>>
                                            <label class="btn btn-outline-secondary" for="gender_f">หญิง</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">อายุ (ปี)</label>
                                        <input type="number" name="age" class="form-control" min="0" max="120"
                                               value="<?php echo $data['age'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรศัพท์ติดต่อ</label>
                                        <input type="text" name="phone" class="form-control" 
                                               placeholder="0xx-xxxxxxx"
                                               value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: สุขภาพ -->
                            <div class="mb-4">
                                <div class="form-section-title">
                                    <i class="fas fa-notes-medical text-danger"></i> 3. ข้อมูลสุขภาพ (Health Condition)
                                </div>
                                <div class="alert alert-light border border-warning">
                                    <label class="form-label fw-bold">โรคประจำตัว / ภาวะเสี่ยง / ความพิการ / ตั้งครรภ์</label>
                                    <textarea name="health_condition" class="form-control" rows="3" 
                                              placeholder="ระบุรายละเอียด เช่น เบาหวาน, ความดัน, ผู้ป่วยติดเตียง, ต้องการอาหารเฉพาะ (หากไม่มีให้เว้นว่าง)"><?php echo htmlspecialchars($data['health_condition'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <hr class="my-4 text-muted">

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="javascript:history.back()" class="btn btn-light btn-lg px-4 border text-muted">
                                    <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                                </a>
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow fw-bold">
                                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>