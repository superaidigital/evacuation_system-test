<?php
// incident_manager.php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check Login & Permission
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle Form Submission (Add/Edit Incident)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // CSRF Protection Check
    $csrf_token = $_POST['csrf_token'] ?? '';
    validateCSRFToken($csrf_token);

    $name = cleanInput($_POST['name']);
    $type = cleanInput($_POST['type']);
    $description = cleanInput($_POST['description']);
    $start_date = $_POST['start_date'];

    // --- CASE 1: ADD ---
    if ($_POST['action'] == 'add') {
        if (empty($name) || empty($type) || empty($start_date)) {
            $_SESSION['swal_error'] = "กรุณากรอกข้อมูลสำคัญให้ครบถ้วน";
        } else {
            try {
                $sql = "INSERT INTO incidents (name, type, description, status, start_date, created_at) VALUES (?, ?, ?, 'active', ?, NOW())";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$name, $type, $description, $start_date])) {
                    logActivity($pdo, $_SESSION['user_id'], 'Add Incident', "เปิดภารกิจใหม่: $name ($type)");
                    $_SESSION['swal_success'] = "เปิดภารกิจใหม่เรียบร้อยแล้ว";
                } else {
                    $_SESSION['swal_error'] = "ไม่สามารถบันทึกข้อมูลได้";
                }
            } catch (PDOException $e) {
                $_SESSION['swal_error'] = "Database Error: " . $e->getMessage();
            }
        }
    }
    // --- CASE 2: EDIT ---
    elseif ($_POST['action'] == 'edit') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if (!$id || empty($name) || empty($type) || empty($start_date)) {
            $_SESSION['swal_error'] = "กรุณากรอกข้อมูลสำคัญให้ครบถ้วน";
        } else {
            try {
                $sql = "UPDATE incidents SET name = ?, type = ?, description = ?, start_date = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$name, $type, $description, $start_date, $id])) {
                    logActivity($pdo, $_SESSION['user_id'], 'Edit Incident', "แก้ไขภารกิจ ID: $id");
                    $_SESSION['swal_success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";
                } else {
                    $_SESSION['swal_error'] = "ไม่สามารถบันทึกการแก้ไขได้";
                }
            } catch (PDOException $e) {
                $_SESSION['swal_error'] = "Database Error: " . $e->getMessage();
            }
        }
    }
    
    // Redirect (PRG Pattern)
    header("Location: incident_manager.php");
    exit();
}

// Handle Close Incident (GET Request)
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if($id){
        try {
            $stmt = $pdo->prepare("UPDATE incidents SET status = 'closed', end_date = CURDATE() WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, $_SESSION['user_id'], 'Close Incident', "ปิดภารกิจ ID: $id");
            $_SESSION['swal_success'] = "ปิดภารกิจเรียบร้อยแล้ว";
        } catch (PDOException $e) {
            $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
    header("Location: incident_manager.php");
    exit();
}

// Fetch Incidents & Stats
$incidents = [];
$active_count = 0;
$closed_count = 0;

try {
    $sql = "SELECT * FROM incidents ORDER BY FIELD(status, 'active', 'closed'), created_at DESC";
    $stmt = $pdo->query($sql);
    $incidents = $stmt->fetchAll();

    foreach($incidents as $inc) {
        if($inc['status'] == 'active') $active_count++;
        else $closed_count++;
    }
} catch (PDOException $e) {
    $db_error = "เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage();
}

// Helper Functions
function getIncidentTypeName($type) {
    $types = [
        'flood' => 'อุทกภัย (น้ำท่วม)',
        'fire' => 'อัคคีภัย (ไฟไหม้)',
        'storm' => 'วาตภัย (พายุ)',
        'landslide' => 'ดินโคลนถล่ม',
        'drought' => 'ภัยแล้ง',
        'earthquake' => 'แผ่นดินไหว',
        'other' => 'อื่นๆ'
    ];
    return $types[$type] ?? 'ไม่ระบุ';
}

function getIncidentIcon($type) {
    $icons = [
        'flood' => 'fa-water',
        'fire' => 'fa-fire',
        'storm' => 'fa-wind',
        'landslide' => 'fa-mountain',
        'drought' => 'fa-sun',
        'earthquake' => 'fa-house-crack',
        'other' => 'fa-exclamation-circle'
    ];
    return $icons[$type] ?? 'fa-exclamation-circle';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการภัยพิบัติ/เหตุการณ์</title>
    <!-- CSS เฉพาะหน้านี้ (Bootstrap หลักโหลดจาก Header แล้ว) -->
    <style>
        .summary-card {
            border: none;
            border-radius: 12px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .summary-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .icon-box {
            width: 50px; height: 50px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-right: 15px;
        }
        .table-custom thead th {
            background-color: #334155;
            color: #fff;
            font-weight: 500;
            border: none;
        }
        .status-badge {
            padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600;
        }
        .badge-active { background-color: #dcfce7; color: #166534; }
        .badge-closed { background-color: #f1f5f9; color: #64748b; }
        
        /* แก้ไข Z-Index ของ Modal Backdrop */
        .modal-backdrop { z-index: 1050 !important; }
        .modal { z-index: 1060 !important; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid px-4 mt-4">

    <!-- Header & Action -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="fas fa-house-damage text-danger me-2"></i>ศูนย์บัญชาการเหตุการณ์</h3>
            <p class="text-muted small mb-0">Incident Command System (ICS)</p>
        </div>
        <button class="btn btn-primary shadow-sm px-4 py-2 rounded-pill" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i>เปิดภารกิจใหม่
        </button>
    </div>

    <!-- Error Alert -->
    <?php if(isset($db_error)): ?>
        <div class="alert alert-danger shadow-sm border-0">
            <i class="fas fa-exclamation-triangle me-2"></i> <strong>System Error:</strong> <?php echo $db_error; ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="summary-card d-flex align-items-center">
                <div class="icon-box bg-success bg-opacity-10 text-success">
                    <i class="fas fa-bolt"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">กำลังดำเนินการ</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($active_count); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card d-flex align-items-center">
                <div class="icon-box bg-secondary bg-opacity-10 text-secondary">
                    <i class="fas fa-archive"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">ปิดภารกิจแล้ว</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format($closed_count); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card d-flex align-items-center">
                <div class="icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">เหตุการณ์ทั้งหมด</h6>
                    <h2 class="mb-0 fw-bold"><?php echo number_format(count($incidents)); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Incident Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom">
                    <thead>
                        <tr>
                            <th class="ps-4 py-3">ชื่อเหตุการณ์</th>
                            <th>ประเภทภัย</th>
                            <th>ระยะเวลา</th>
                            <th>สถานะ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($incidents) > 0): ?>
                            <?php foreach ($incidents as $row): ?>
                                <?php $type = $row['type'] ?? 'other'; ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></div>
                                        <div class="text-muted small text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($row['description'] ?? '-'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fas <?php echo getIncidentIcon($type); ?> me-1 text-secondary"></i>
                                            <?php echo getIncidentTypeName($type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small"><i class="far fa-clock text-success me-1"></i> <?php echo thaiDate($row['start_date']); ?></div>
                                        <?php if($row['end_date']): ?>
                                            <div class="small text-muted"><i class="far fa-check-circle text-danger me-1"></i> จบ: <?php echo thaiDate($row['end_date']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($row['status'] == 'active'): ?>
                                            <span class="status-badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-closed">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <!-- ปุ่มแก้ไข -->
                                        <button class="btn btn-sm btn-outline-warning rounded-pill me-1" 
                                            onclick='openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <a href="shelter_list.php?filter_incident=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill me-1">
                                            <i class="fas fa-campground"></i>
                                        </a>
                                        
                                        <?php if($row['status'] == 'active'): ?>
                                            <button onclick="confirmClose(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')" 
                                                class="btn btn-sm btn-outline-danger rounded-pill">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-25"></i><br>
                                    ยังไม่มีข้อมูลเหตุการณ์ในระบบ
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ปิด div ของ content หลักจาก header -->
<?php include 'includes/footer.php'; ?>

<!-- ======================================================= -->
<!-- ย้าย Modal มาไว้นอกสุด (หลัง Footer) เพื่อแก้ปัญหา Backdrop -->
<!-- ======================================================= -->

<!-- Modal: เพิ่ม/แก้ไข เหตุการณ์ -->
<div class="modal fade" id="incidentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form action="incident_manager.php" method="POST" class="w-100" id="incidentForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="incidentId" value="">
            
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="modal-title fw-bold" id="modalTitle">
                        <i class="fas fa-plus-circle me-2"></i>เปิดภารกิจใหม่
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ชื่อเหตุการณ์ <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="inputName" class="form-control form-control-lg" placeholder="เช่น น้ำท่วมใหญ่ 2568" required>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">ประเภทภัย <span class="text-danger">*</span></label>
                            <select name="type" id="inputType" class="form-select" required>
                                <option value="" disabled selected>-- เลือก --</option>
                                <option value="flood">อุทกภัย (น้ำท่วม)</option>
                                <option value="fire">อัคคีภัย (ไฟไหม้)</option>
                                <option value="storm">วาตภัย (พายุ)</option>
                                <option value="landslide">ดินโคลนถล่ม</option>
                                <option value="earthquake">แผ่นดินไหว</option>
                                <option value="drought">ภัยแล้ง</option>
                                <option value="other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">วันที่เริ่ม <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="inputStartDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label fw-bold text-secondary">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" id="inputDescription" class="form-control" rows="2" placeholder="พื้นที่ประสบภัย, ระดับความรุนแรง..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert Logic -->
<?php if (isset($_SESSION['swal_success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: '<?php echo $_SESSION['swal_success']; ?>',
            timer: 2000,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['swal_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['swal_error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: '<?php echo $_SESSION['swal_error']; ?>'
        });
    </script>
    <?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>

<!-- 
    NOTE: ลบ <script src="bootstrap.bundle.min.js"> ออก 
    เพราะใน includes/footer.php มีการเรียกใช้แล้ว การใส่ซ้ำจะทำให้ Modal พัง 
-->

<script>
    // ฟังก์ชันเปิด Modal สำหรับเพิ่มข้อมูล
    function openAddModal() {
        // Reset Form
        document.getElementById('incidentForm').reset();
        document.getElementById('formAction').value = 'add';
        document.getElementById('incidentId').value = '';
        document.getElementById('inputStartDate').value = '<?php echo date("Y-m-d"); ?>';
        
        // Update Title
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>เปิดภารกิจใหม่';
        
        // Show Modal
        // ตรวจสอบว่า Bootstrap โหลดแล้วหรือยัง
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        } else {
            console.error('Bootstrap JS not loaded');
            alert('ระบบกำลังโหลด กรุณาลองใหม่อีกครั้ง');
        }
    }

    // ฟังก์ชันเปิด Modal สำหรับแก้ไขข้อมูล
    function openEditModal(data) {
        // Populate Data
        document.getElementById('formAction').value = 'edit';
        document.getElementById('incidentId').value = data.id;
        document.getElementById('inputName').value = data.name;
        document.getElementById('inputType').value = data.type || 'other';
        document.getElementById('inputStartDate').value = data.start_date;
        document.getElementById('inputDescription').value = data.description || '';
        
        // Update Title
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>แก้ไขภารกิจ';
        
        // Show Modal
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        }
    }

    function confirmClose(id, name) {
        Swal.fire({
            title: 'ยืนยันการปิดภารกิจ?',
            html: `คุณต้องการปิดสถานะเหตุการณ์ <b>"${name}"</b> ใช่หรือไม่?<br><small class="text-danger">เมื่อปิดแล้วจะไม่สามารถแก้ไขข้อมูลได้</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ปิดงานทันที',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `incident_manager.php?action=close&id=${id}`;
            }
        });
    }
</script>

</body>
</html>