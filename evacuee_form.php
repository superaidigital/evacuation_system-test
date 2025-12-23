<?php
// evacuee_form.php
// Refactored: Added Medical Triage Section
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$mode = $_GET['mode'] ?? 'add';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$shelter_id = $_GET['shelter_id'] ?? ($_SESSION['shelter_id'] ?? '');

// Init Data
$data = [
    'incident_id' => '', 'shelter_id' => $shelter_id, 
    'id_card' => '', 'prefix' => '', 'first_name' => '', 'last_name' => '', 
    'phone' => '', 'gender' => '', 'age' => '', 
    'address_card' => '', 'stay_type' => 'shelter', 'stay_detail' => '',
    // New Medical Fields
    'triage_level' => 'green', 'medical_condition' => '', 'drug_allergy' => ''
];
$needs = []; // For evacuee_needs

// Fetch Data for Edit
if ($mode == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM evacuees WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $data = $fetched;
        // Fetch Needs
        $stmtNeeds = $pdo->prepare("SELECT need_type FROM evacuee_needs WHERE evacuee_id = ?");
        $stmtNeeds->execute([$id]);
        $needs = $stmtNeeds->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Fetch Incidents & Shelters
$incidents = $pdo->query("SELECT id, name FROM incidents WHERE status='active'")->fetchAll();
$shelters = $pdo->query("SELECT id, name FROM shelters WHERE status!='closed'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title><?php echo $mode == 'add' ? 'ลงทะเบียนผู้ประสบภัย' : 'แก้ไขข้อมูล'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .triage-radio { display: none; }
        .triage-label { 
            cursor: pointer; opacity: 0.5; transition: all 0.2s; border: 3px solid transparent;
            padding: 15px; border-radius: 10px; text-align: center;
        }
        .triage-radio:checked + .triage-label { opacity: 1; transform: scale(1.05); }
        
        .triage-green:checked + .triage-label { border-color: #10b981; background-color: #ecfdf5; color: #065f46; }
        .triage-yellow:checked + .triage-label { border-color: #f59e0b; background-color: #fffbeb; color: #92400e; }
        .triage-red:checked + .triage-label { border-color: #ef4444; background-color: #fef2f2; color: #991b1b; }

        .form-section-title {
            border-left: 4px solid #0d6efd; padding-left: 10px; font-weight: bold; color: #0d6efd; margin: 20px 0 15px 0;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i><?php echo $mode == 'add' ? 'ลงทะเบียนผู้ประสบภัย' : 'แก้ไขข้อมูลผู้ประสบภัย'; ?></h5>
                </div>
                <div class="card-body p-4">

                    <?php if (isset($_SESSION['swal_error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['swal_error']; unset($_SESSION['swal_error']); ?></div>
                    <?php endif; ?>

                    <form action="evacuee_save.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                        <?php if ($mode == 'edit'): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>

                        <!-- 1. ข้อมูลทั่วไป -->
                        <div class="form-section-title">ข้อมูลส่วนตัว</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ภารกิจ <span class="text-danger">*</span></label>
                                <select name="incident_id" class="form-select" required>
                                    <option value="">-- เลือกภารกิจ --</option>
                                    <?php foreach ($incidents as $inc): ?>
                                        <option value="<?php echo $inc['id']; ?>" <?php echo $data['incident_id'] == $inc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($inc['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">เลขบัตรประชาชน</label>
                                <input type="text" name="id_card" class="form-control" maxlength="13" value="<?php echo htmlspecialchars($data['id_card']); ?>" placeholder="13 หลัก (ถ้ามี)">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">คำนำหน้า</label>
                                <select name="prefix_select" class="form-select">
                                    <option value="นาย" <?php echo $data['prefix'] == 'นาย' ? 'selected' : ''; ?>>นาย</option>
                                    <option value="นาง" <?php echo $data['prefix'] == 'นาง' ? 'selected' : ''; ?>>นาง</option>
                                    <option value="นางสาว" <?php echo $data['prefix'] == 'นางสาว' ? 'selected' : ''; ?>>นางสาว</option>
                                    <option value="ด.ช." <?php echo $data['prefix'] == 'ด.ช.' ? 'selected' : ''; ?>>ด.ช.</option>
                                    <option value="ด.ญ." <?php echo $data['prefix'] == 'ด.ญ.' ? 'selected' : ''; ?>>ด.ญ.</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($data['first_name']); ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($data['last_name']); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($data['phone']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">อายุ</label>
                                <input type="number" name="age" class="form-control" value="<?php echo $data['age']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เพศ</label>
                                <select name="gender" class="form-select">
                                    <option value="male" <?php echo $data['gender'] == 'male' ? 'selected' : ''; ?>>ชาย</option>
                                    <option value="female" <?php echo $data['gender'] == 'female' ? 'selected' : ''; ?>>หญิง</option>
                                    <option value="other" <?php echo $data['gender'] == 'other' ? 'selected' : ''; ?>>อื่นๆ</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. ข้อมูลที่พัก -->
                        <div class="form-section-title">สถานที่พักพิง</div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">ประเภทการพัก</label>
                                <select name="stay_type" class="form-select" onchange="toggleStayDetail(this.value)">
                                    <option value="shelter" <?php echo $data['stay_type'] == 'shelter' ? 'selected' : ''; ?>>พักในศูนย์พักพิง</option>
                                    <option value="outside" <?php echo $data['stay_type'] == 'outside' ? 'selected' : ''; ?>>พักนอกศูนย์/บ้านญาติ</option>
                                </select>
                            </div>
                            <div class="col-md-8" id="shelter_select_div">
                                <label class="form-label">เลือกศูนย์พักพิง <span class="text-danger">*</span></label>
                                <select name="shelter_id" class="form-select">
                                    <option value="">-- เลือกศูนย์ --</option>
                                    <?php foreach ($shelters as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $data['shelter_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8 d-none" id="stay_detail_div">
                                <label class="form-label">รายละเอียดที่อยู่ (กรณีพักนอกศูนย์)</label>
                                <input type="text" name="stay_detail" class="form-control" value="<?php echo htmlspecialchars($data['stay_detail']); ?>" placeholder="เช่น บ้านญาติ ม.3 ต.เมือง">
                            </div>
                        </div>

                        <!-- 3. คัดกรองสุขภาพ (Medical Triage) -->
                        <div class="form-section-title text-danger"><i class="fas fa-heartbeat me-2"></i>คัดกรองสุขภาพ (Medical Triage)</div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <input type="radio" name="triage_level" value="green" id="t_green" class="triage-radio triage-green" <?php echo $data['triage_level'] == 'green' ? 'checked' : ''; ?>>
                                <label for="t_green" class="triage-label w-100 h-100 bg-white shadow-sm">
                                    <i class="fas fa-smile fa-3x mb-2 text-success"></i><br>
                                    <span class="fw-bold fs-5">เขียว (Green)</span><br>
                                    <small>เจ็บป่วยเล็กน้อย / สบายดี</small>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <input type="radio" name="triage_level" value="yellow" id="t_yellow" class="triage-radio triage-yellow" <?php echo $data['triage_level'] == 'yellow' ? 'checked' : ''; ?>>
                                <label for="t_yellow" class="triage-label w-100 h-100 bg-white shadow-sm">
                                    <i class="fas fa-frown-open fa-3x mb-2 text-warning"></i><br>
                                    <span class="fw-bold fs-5">เหลือง (Yellow)</span><br>
                                    <small>เจ็บป่วยปานกลาง / รอได้</small>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <input type="radio" name="triage_level" value="red" id="t_red" class="triage-radio triage-red" <?php echo $data['triage_level'] == 'red' ? 'checked' : ''; ?>>
                                <label for="t_red" class="triage-label w-100 h-100 bg-white shadow-sm">
                                    <i class="fas fa-dizzy fa-3x mb-2 text-danger"></i><br>
                                    <span class="fw-bold fs-5">แดง (Red)</span><br>
                                    <small>วิกฤต / ต้องการหมอด่วน!</small>
                                </label>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">อาการ / โรคประจำตัว</label>
                                <textarea name="medical_condition" class="form-control" rows="2" placeholder="เช่น เบาหวาน, ความดัน, มีไข้สูง..."><?php echo htmlspecialchars($data['medical_condition']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ประวัติการแพ้ยา / อาหาร</label>
                                <textarea name="drug_allergy" class="form-control" rows="2" placeholder="ระบุชื่อยาที่แพ้ (ถ้ามี)"><?php echo htmlspecialchars($data['drug_allergy']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">กลุ่มเปราะบาง (เลือกได้มากกว่า 1)</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php 
                                $vul_groups = [
                                    'elderly' => 'ผู้สูงอายุ', 'disabled' => 'ผู้พิการ', 
                                    'pregnant' => 'หญิงตั้งครรภ์', 'infant' => 'เด็กเล็ก', 'chronic' => 'ผู้ป่วยเรื้อรัง'
                                ];
                                foreach($vul_groups as $key => $label): 
                                    $checked = in_array($key, $needs) ? 'checked' : '';
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="needs[]" value="<?php echo $key; ?>" id="n_<?php echo $key; ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label" for="n_<?php echo $key; ?>"><?php echo $label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="evacuee_list.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-save me-2"></i> บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
    function toggleStayDetail(val) {
        if(val === 'outside') {
            document.getElementById('shelter_select_div').classList.add('d-none');
            document.getElementById('stay_detail_div').classList.remove('d-none');
        } else {
            document.getElementById('shelter_select_div').classList.remove('d-none');
            document.getElementById('stay_detail_div').classList.add('d-none');
        }
    }
    // Init state
    toggleStayDetail('<?php echo $data['stay_type']; ?>');
</script>
</body>
</html>