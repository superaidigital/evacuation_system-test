<?php
// evacuee_form.php
require_once 'config/db.php';
require_once 'includes/functions.php';

// เริ่ม Session ถ้ายังไม่มี
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) : '';
$mode = isset($_GET['mode']) ? cleanInput($_GET['mode']) : 'add';
$selected_shelter_id = isset($_GET['shelter_id']) ? filter_input(INPUT_GET, 'shelter_id', FILTER_VALIDATE_INT) : '';

$current_incident_id = 0;
$current_incident_name = '';
$data = [];
$existing_needs = [];

// 2. Logic การดึงข้อมูล
if ($mode == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM evacuees WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();

    if ($data) {
        $current_incident_id = $data['incident_id'];
        $selected_shelter_id = $data['shelter_id'];
        
        $stmt_inc = $pdo->prepare("SELECT name FROM incidents WHERE id = ?");
        $stmt_inc->execute([$current_incident_id]);
        $inc_data = $stmt_inc->fetch();
        $current_incident_name = $inc_data ? $inc_data['name'] : 'ไม่ระบุเหตุการณ์';

        try {
            $stmt_needs = $pdo->prepare("SELECT need_type FROM evacuee_needs WHERE evacuee_id = ?");
            $stmt_needs->execute([$id]);
            $existing_needs = $stmt_needs->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) { $existing_needs = []; }
    } else {
        die("ไม่พบข้อมูลผู้ประสบภัย ID: $id");
    }
} else {
    $stmt = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $active_incident = $stmt->fetch();
    if ($active_incident) {
        $current_incident_id = $active_incident['id'];
        $current_incident_name = $active_incident['name'];
    }
}

// 3. ดึงรายชื่อศูนย์พักพิง
$shelters = [];
if ($current_incident_id) {
    $sql_shelter = "SELECT id, name, capacity, 
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
        /* CSS ป้องกัน Layout ทับซ้อน */
        body { overflow-x: hidden; }
        .form-header { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: white; 
            padding: 25px; 
            border-radius: 12px 12px 0 0; 
            border-bottom: 4px solid #fbbf24; 
        }
        .form-section-title { 
            color: #1e293b; font-weight: 600; font-size: 1.1rem; margin-bottom: 20px; 
            display: flex; align-items: center; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px; 
        }
        .form-section-title i { 
            width: 35px; height: 35px; background-color: #f1f5f9; color: #0f172a; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            margin-right: 10px; font-size: 1rem; 
        }
        .card-form-container { position: relative; z-index: 10; }
        
        .needs-checkbox-card {
            cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; position: relative; z-index: 1;
        }
        .needs-checkbox-card:hover {
            background-color: #f8fafc; border-color: #fbbf24; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .form-check-input:checked + .form-check-label { font-weight: bold; color: #0f172a; }
        
        /* Custom Input for Prefix */
        .prefix-wrapper { position: relative; }
        #custom_prefix_input { display: none; margin-top: 5px; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid px-4 mt-4 mb-5 card-form-container">
    
    <?php if (isset($_SESSION['swal_error'])): ?>
        <div class="alert alert-danger shadow-sm alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['swal_error']; unset($_SESSION['swal_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($mode == 'add' && !$current_incident_id): ?>
        <div class="alert alert-warning text-center shadow-sm p-5 border-0 rounded-3">
            <h4>ไม่พบภารกิจที่กำลังดำเนินการ</h4>
            <a href="index.php" class="btn btn-outline-dark mt-2">กลับหน้าหลัก</a>
        </div>
    <?php else: ?>

        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="card border-0 shadow-lg rounded-3">
                    <div class="form-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1 fw-bold"><i class="fas fa-user-edit me-2"></i> ทะเบียนผู้ประสบภัย</h4>
                            <small class="text-white-50">แบบฟอร์มลงทะเบียนเข้าพักศูนย์พักพิง</small>
                        </div>
                        <div class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm">
                            <i class="fas fa-flag me-1"></i> ภารกิจ: <?php echo htmlspecialchars($current_incident_name); ?>
                        </div>
                    </div>

                    <div class="card-body p-4 bg-white">
                        <form action="evacuee_save.php" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="incident_id" value="<?php echo $current_incident_id; ?>">

                            <!-- Section 1: ข้อมูลการเข้าพัก -->
                            <div class="mb-5">
                                <div class="form-section-title"><i class="fas fa-campground"></i> 1. ข้อมูลการเข้าพัก</div>
                                
                                <!-- เลือกประเภทการพัก -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">รูปแบบการพักอาศัย</label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="stay_type" id="stay_shelter" value="shelter" 
                                                <?php echo ($data['stay_type'] ?? 'shelter') == 'shelter' ? 'checked' : ''; ?> 
                                                onclick="toggleStayType()">
                                            <label class="form-check-label" for="stay_shelter">
                                                <i class="fas fa-home text-primary me-1"></i> พักในศูนย์พักพิง
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="stay_type" id="stay_outside" value="outside" 
                                                <?php echo ($data['stay_type'] ?? '') == 'outside' ? 'checked' : ''; ?> 
                                                onclick="toggleStayType()">
                                            <label class="form-check-label" for="stay_outside">
                                                <i class="fas fa-tent text-success me-1"></i> พักนอกศูนย์/บ้านญาติ
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- กรณีพักในศูนย์ -->
                                <div id="shelter_select_group">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">เลือกศูนย์พักพิง <span class="text-danger">*</span></label>
                                        <select name="shelter_id" id="shelter_id" class="form-select form-select-lg">
                                            <option value="" disabled selected>-- กรุณาเลือกศูนย์ --</option>
                                            <?php foreach ($shelters as $s): ?>
                                                <?php 
                                                    $vacancy = $s['capacity'] - $s['used'];
                                                    $is_full = $vacancy <= 0;
                                                    $force_enable = ($mode == 'edit' && $selected_shelter_id == $s['id']);
                                                    $label = htmlspecialchars($s['name']) . " (ว่าง $vacancy ที่)";
                                                ?>
                                                <option value="<?php echo $s['id']; ?>" 
                                                    <?php echo ($is_full && !$force_enable) ? 'disabled' : ''; ?> 
                                                    <?php echo ($selected_shelter_id == $s['id']) ? 'selected' : ''; ?>
                                                    class="<?php echo $is_full ? 'text-danger' : ''; ?>"
                                                >
                                                    <?php echo $label . ($is_full ? ' [เต็ม]' : ''); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- กรณีพักนอกศูนย์ -->
                                <div id="outside_stay_detail" style="display: none;">
                                    <div class="alert alert-success border-0 shadow-sm">
                                        <div class="mb-2 fw-bold"><i class="fas fa-map-marker-alt"></i> รายละเอียดที่พักอาศัย (นอกศูนย์)</div>
                                        <textarea name="stay_detail" class="form-control" rows="2" placeholder="ระบุบ้านเลขที่, ชื่อญาติ, หรือสถานที่พักพิงชั่วคราวอื่นๆ..."><?php echo htmlspecialchars($data['stay_detail'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: ข้อมูลส่วนตัว -->
                            <div class="mb-5">
                                <div class="form-section-title"><i class="fas fa-id-card"></i> 2. ข้อมูลส่วนตัว</div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">เลขบัตรประชาชน</label>
                                        <input type="text" name="id_card" class="form-control" maxlength="13" placeholder="13 หลัก (ถ้ามี)" value="<?php echo htmlspecialchars($data['id_card'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold">คำนำหน้า</label>
                                        <div class="prefix-wrapper">
                                            <select name="prefix_select" id="prefix_select" class="form-select" onchange="checkPrefix()">
                                                <option value="">-- เลือก --</option>
                                                <option value="นาย" <?php echo ($data['prefix']??'')=='นาย'?'selected':''; ?>>นาย</option>
                                                <option value="นาง" <?php echo ($data['prefix']??'')=='นาง'?'selected':''; ?>>นาง</option>
                                                <option value="นางสาว" <?php echo ($data['prefix']??'')=='นางสาว'?'selected':''; ?>>นางสาว</option>
                                                <option value="ด.ช." <?php echo ($data['prefix']??'')=='ด.ช.'?'selected':''; ?>>ด.ช.</option>
                                                <option value="ด.ญ." <?php echo ($data['prefix']??'')=='ด.ญ.'?'selected':''; ?>>ด.ญ.</option>
                                                <option value="other" <?php echo !in_array(($data['prefix']??''), ['นาย','นาง','นางสาว','ด.ช.','ด.ญ.','']) ? 'selected' : ''; ?>>ระบุเอง...</option>
                                            </select>
                                            <input type="text" name="prefix_custom" id="prefix_custom" class="form-control mt-1" placeholder="ระบุคำนำหน้า..." value="<?php echo htmlspecialchars($data['prefix'] ?? ''); ?>" style="display: none;">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">ชื่อจริง <span class="text-danger">*</span></label>
                                        <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($data['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">นามสกุล <span class="text-danger">*</span></label>
                                        <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($data['last_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">อายุ (ปี)</label>
                                        <input type="number" name="age" class="form-control" min="0" max="120" value="<?php echo htmlspecialchars($data['age'] ?? ''); ?>">
                                    </div>
                                     <div class="col-md-4">
                                        <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                                        <input type="text" name="phone" class="form-control" placeholder="ถ้ามี" value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold">เพศสภาพ</label>
                                        <div class="mt-2">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="gender" id="genderM" value="male" <?php echo ($data['gender']??'')=='male'?'checked':''; ?>>
                                                <label class="form-check-label" for="genderM">ชาย</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="gender" id="genderF" value="female" <?php echo ($data['gender']??'')=='female'?'checked':''; ?>>
                                                <label class="form-check-label" for="genderF">หญิง</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- ที่อยู่ตามบัตรประชาชน -->
                                    <div class="col-12 mt-3">
                                        <label class="form-label fw-bold">ที่อยู่ตามบัตรประชาชน</label>
                                        <input type="text" name="address_card" class="form-control" placeholder="บ้านเลขที่ หมู่ ตำบล อำเภอ จังหวัด..." value="<?php echo htmlspecialchars($data['address_card'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 3: กลุ่มเปราะบาง -->
                            <div class="mb-5">
                                <div class="form-section-title"><i class="fas fa-heartbeat"></i> 3. กลุ่มเปราะบางและความต้องการพิเศษ</div>
                                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x me-3"></i>
                                    <div><strong>ข้อมูลสำคัญ:</strong> เพื่อการจัดเตรียมอาหาร ยา และพื้นที่นอนที่เหมาะสม</div>
                                </div>

                                <div class="mb-3">
                                    <div class="row g-3">
                                        <!-- Checkbox Items (เหมือนเดิม) -->
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="elderly" id="need_elderly" <?php echo in_array('elderly', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_elderly">🧓 ผู้สูงอายุ</label></div></div>
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="disabled" id="need_disabled" <?php echo in_array('disabled', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_disabled">♿ ผู้พิการ</label></div></div>
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="pregnant" id="need_pregnant" <?php echo in_array('pregnant', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_pregnant">🤰 หญิงตั้งครรภ์</label></div></div>
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="infant" id="need_infant" <?php echo in_array('infant', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_infant">👶 เด็กเล็ก</label></div></div>
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="chronic" id="need_chronic" <?php echo in_array('chronic', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_chronic">💊 ป่วยเรื้อรัง</label></div></div>
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="halal" id="need_halal" <?php echo in_array('halal', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_halal">☪️ อาหารฮาลาล</label></div></div>
                                        <div class="col-md-3 col-6"><div class="form-check p-3 rounded bg-white needs-checkbox-card h-100"><input class="form-check-input" type="checkbox" name="needs[]" value="vegetarian" id="need_veg" <?php echo in_array('vegetarian', $existing_needs)?'checked':''; ?>><label class="form-check-label stretched-link" for="need_veg">🥗 มังสวิรัติ</label></div></div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="form-label fw-bold">รายละเอียดสุขภาพเพิ่มเติม</label>
                                    <textarea name="health_condition" class="form-control" rows="2" placeholder="โรคประจำตัว, ยาที่แพ้..."><?php echo htmlspecialchars($data['health_condition'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <hr class="my-4">
                            <div class="d-flex justify-content-end gap-3">
                                <a href="index.php" class="btn btn-secondary btn-lg px-4 rounded-pill">ยกเลิก</a>
                                <button type="submit" class="btn btn-success btn-lg px-5 shadow rounded-pill"><i class="fas fa-save me-2"></i> บันทึกข้อมูล</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // 1. ฟังก์ชันจัดการคำนำหน้าและเพศอัตโนมัติ
    function checkPrefix() {
        const select = document.getElementById('prefix_select');
        const customInput = document.getElementById('prefix_custom');
        const val = select.value;

        // Show/Hide Custom Input
        if (val === 'other') {
            customInput.style.display = 'block';
            customInput.required = true;
            customInput.value = ''; // Clear input if newly selected
        } else {
            customInput.style.display = 'none';
            customInput.required = false;
            // ถ้าไม่ใช่ other ให้เก็บค่า select ลงใน input hidden หรือส่งค่าไปตรงๆ (จะ handle ใน PHP)
        }

        // Auto-select Gender
        if (val === 'นาย' || val === 'ด.ช.') {
            document.getElementById('genderM').checked = true;
        } else if (val === 'นาง' || val === 'นางสาว' || val === 'ด.ญ.') {
            document.getElementById('genderF').checked = true;
        }
    }

    // 2. ฟังก์ชันสลับโหมดที่พัก (ในศูนย์/นอกศูนย์)
    function toggleStayType() {
        const isOutside = document.getElementById('stay_outside').checked;
        const shelterGroup = document.getElementById('shelter_select_group');
        const outsideDetail = document.getElementById('outside_stay_detail');
        const shelterSelect = document.getElementById('shelter_id');

        if (isOutside) {
            shelterGroup.style.display = 'none';
            outsideDetail.style.display = 'block';
            shelterSelect.required = false; // ไม่บังคับเลือกศูนย์
        } else {
            shelterGroup.style.display = 'block';
            outsideDetail.style.display = 'none';
            shelterSelect.required = true; // บังคับเลือกศูนย์
        }
    }

    // Run on load to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        checkPrefix();
        toggleStayType();
        
        // ถ้าเป็นการ Edit และมี Custom Prefix ให้โชว์ Input
        <?php if (!in_array(($data['prefix']??''), ['นาย','นาง','นางสาว','ด.ช.','ด.ญ.','']) && ($data['prefix']??'') != ''): ?>
            document.getElementById('prefix_select').value = 'other';
            document.getElementById('prefix_custom').style.display = 'block';
            document.getElementById('prefix_custom').value = '<?php echo $data['prefix']; ?>';
        <?php endif; ?>
    });
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>