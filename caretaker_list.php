<?php
// caretaker_list.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // เรียกใช้ logActivity()
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ------------------------------------------------------------------
// 2. Logic: Handle Actions (Add / Edit / Delete)
// ------------------------------------------------------------------

// 2.1 Handle Delete (GET)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM caretakers WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'Delete Caretaker', "ลบผู้ดูแล ID: $id");
        $_SESSION['swal_success'] = "ลบข้อมูลเรียบร้อยแล้ว";
    } catch (\PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['swal_error'] = "เกิดข้อผิดพลาดในการลบข้อมูล";
    }
    header("Location: caretaker_list.php");
    exit();
}

// 2.2 Handle Save (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    
    $mode = $_POST['action_type'];
    $id = $_POST['caretaker_id'] ?? '';
    
    $prefix = trim($_POST['prefix']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $position = trim($_POST['position']);
    $phone = trim($_POST['phone']);
    $shelter_id = $_POST['shelter_id'];

    if (empty($first_name) || empty($last_name) || empty($shelter_id)) {
        $_SESSION['swal_error'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        try {
            if ($mode == 'add') {
                $sql = "INSERT INTO caretakers (prefix, first_name, last_name, position, phone, shelter_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$prefix, $first_name, $last_name, $position, $phone, $shelter_id]);
                
                logActivity($pdo, $_SESSION['user_id'], 'Add Caretaker', "เพิ่มผู้ดูแล: $first_name $last_name");
                $_SESSION['swal_success'] = "เพิ่มผู้ดูแลเรียบร้อยแล้ว";

            } else if ($mode == 'edit') {
                $sql = "UPDATE caretakers SET prefix=?, first_name=?, last_name=?, position=?, phone=?, shelter_id=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$prefix, $first_name, $last_name, $position, $phone, $shelter_id, $id]);
                
                logActivity($pdo, $_SESSION['user_id'], 'Edit Caretaker', "แก้ไขผู้ดูแล: $first_name $last_name");
                $_SESSION['swal_success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";
            }
        } catch (\PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['swal_error'] = "Database Error: " . $e->getMessage();
        }
    }
    header("Location: caretaker_list.php");
    exit();
}

// ------------------------------------------------------------------
// 3. Data Fetching
// ------------------------------------------------------------------

// ดึงรายชื่อศูนย์พักพิง (สำหรับ Dropdown ใน Modal)
try {
    $sql_shelters = "SELECT s.id, s.name, i.name as incident_name, i.status 
                     FROM shelters s 
                     LEFT JOIN incidents i ON s.incident_id = i.id 
                     ORDER BY i.status ASC, s.name ASC";
    $shelters = $pdo->query($sql_shelters)->fetchAll();
} catch (\PDOException $e) {
    $shelters = []; // กัน Error
}

// รับค่าค้นหา
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Query รายชื่อผู้ดูแล
$caretakers = [];
try {
    $sql = "SELECT c.*, s.name as shelter_name, i.name as incident_name, i.status as incident_status
            FROM caretakers c
            LEFT JOIN shelters s ON c.shelter_id = s.id
            LEFT JOIN incidents i ON s.incident_id = i.id
            WHERE (c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ? OR s.name LIKE ?)
            ORDER BY c.first_name ASC";

    $stmt = $pdo->prepare($sql);
    $params = ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"];
    $stmt->execute($params);
    $caretakers = $stmt->fetchAll();
} catch (\PDOException $e) {
    // ใช้ \PDOException เพื่อให้มั่นใจว่าจับ Error ได้จริง
    error_log("Caretaker Query Error: " . $e->getMessage());
    $db_error = "เกิดข้อผิดพลาดฐานข้อมูล (Table Structure Mismatch). <a href='fix_db_caretakers.php' class='btn btn-warning btn-sm'>คลิกที่นี่เพื่อซ่อมแซมฐานข้อมูล</a>";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ทำเนียบผู้ดูแลศูนย์</title>
    <style>
        .avatar-initial {
            width: 40px;
            height: 40px;
            background-color: #e2e8f0;
            color: #475569;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .table-official thead th {
            background-color: #1e293b; /* Navy */
            color: white;
            font-weight: 500;
            border: none;
            padding: 12px;
        }

        /* Modal Custom Style */
        .modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border-bottom: 4px solid #fbbf24; /* Gold */
        }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        
        /* Fix modal z-index issues */
        .modal-backdrop { z-index: 1050; }
        .modal { z-index: 1060; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    
    <!-- Title & Action -->
    <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-user-nurse text-warning me-2"></i>ทำเนียบผู้ดูแลศูนย์
            </h4>
            <span class="text-muted small">Caretaker Directory Management</span>
        </div>
        <!-- ปุ่มเปิด Modal สำหรับเพิ่มใหม่ -->
        <button class="btn btn-primary shadow-sm" type="button" onclick="openAddModal()">
            <i class="fas fa-user-plus me-2"></i>เพิ่มผู้ดูแล
        </button>
    </div>

    <!-- DB Error Notification -->
    <?php if(isset($db_error)): ?>
        <div class="alert alert-danger shadow-sm border-danger">
            <h4><i class="fas fa-tools"></i> ระบบตรวจพบปัญหาฐานข้อมูล</h4>
            <p class="mb-2"><?php echo $db_error; ?></p>
        </div>
    <?php endif; ?>

    <!-- Search Box -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label fw-bold text-secondary">ค้นหา:</label>
                </div>
                <div class="col-md-4">
                    <input type="text" name="keyword" class="form-control" placeholder="ชื่อ, เบอร์โทร หรือชื่อศูนย์..." value="<?php echo htmlspecialchars($keyword); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
                    <?php if($keyword): ?>
                        <a href="caretaker_list.php" class="btn btn-outline-secondary">ล้างค่า</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Alert Notification -->
    <?php if (isset($_SESSION['swal_success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo $_SESSION['swal_success']; ?>',
                timer: 1500,
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

    <!-- Data Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-official">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 60px;">#</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ตำแหน่ง</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th>ประจำศูนย์พักพิง</th>
                            <th>ภารกิจ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($caretakers) > 0): ?>
                            <?php foreach ($caretakers as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="avatar-initial">
                                        <?php echo mb_substr($row['first_name'], 0, 1); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['prefix'] . $row['first_name'] . ' ' . $row['last_name']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['position']); ?></td>
                                <td>
                                    <?php if($row['phone']): ?>
                                        <a href="tel:<?php echo $row['phone']; ?>" class="text-decoration-none text-dark">
                                            <i class="fas fa-phone-alt text-success me-1"></i> <?php echo $row['phone']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['shelter_name']): ?>
                                        <i class="fas fa-home text-muted me-1"></i> <?php echo htmlspecialchars($row['shelter_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">ไม่ระบุ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['incident_name']): ?>
                                        <span class="badge <?php echo ($row['incident_status']=='active'?'bg-success':'bg-secondary'); ?> bg-opacity-10 text-dark border">
                                            <?php echo htmlspecialchars($row['incident_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <!-- ปุ่มแก้ไข: ส่งข้อมูล JSON ไปให้ JS -->
                                    <button class="btn btn-sm btn-light border me-1" 
                                            onclick='openEditModal(<?php echo json_encode($row); ?>)' 
                                            title="แก้ไข">
                                        <i class="fas fa-edit text-warning"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-sm btn-light border" title="ลบ">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-light"></i><br>
                                    ไม่พบข้อมูลผู้ดูแล
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- ========================================== -->
<!-- MODAL POPUP: เพิ่ม/แก้ไข ข้อมูล -->
<!-- ========================================== -->
<div class="modal fade" id="caretakerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="caretaker_list.php" method="POST" class="w-100" id="caretakerForm">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ดูแลใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Hidden Fields -->
                    <input type="hidden" name="action_type" id="action_type" value="add">
                    <input type="hidden" name="caretaker_id" id="caretaker_id" value="">

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">คำนำหน้า</label>
                            <select name="prefix" id="prefix" class="form-select">
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ตำแหน่ง/หน้าที่</label>
                            <input type="text" name="position" id="position" class="form-control" placeholder="เช่น พยาบาล, อาสาสมัคร">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ประจำศูนย์พักพิง <span class="text-danger">*</span></label>
                        <select name="shelter_id" id="shelter_id" class="form-select" required>
                            <option value="" selected disabled>-- เลือกศูนย์พักพิง --</option>
                            <?php foreach ($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['name']); ?> 
                                    (<?php echo ($s['status'] ?? 'open') == 'active' ? '🟢 Active' : '⚪ '.$s['incident_name']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnSave">
                        <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script>
    // เปิด Modal โหมดเพิ่ม
    function openAddModal() {
        setTimeout(() => {
            const modalEl = document.getElementById('caretakerModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                
                document.getElementById('caretakerForm').reset();
                document.getElementById('action_type').value = 'add';
                document.getElementById('caretaker_id').value = '';
                
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> เพิ่มผู้ดูแลใหม่';
                document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกข้อมูล';
                
                modal.show();
            }
        }, 100);
    }

    // เปิด Modal โหมดแก้ไข
    function openEditModal(data) {
        setTimeout(() => {
            const modalEl = document.getElementById('caretakerModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                
                document.getElementById('action_type').value = 'edit';
                document.getElementById('caretaker_id').value = data.id;
                document.getElementById('prefix').value = data.prefix;
                document.getElementById('first_name').value = data.first_name;
                document.getElementById('last_name').value = data.last_name;
                document.getElementById('position').value = data.position;
                document.getElementById('phone').value = data.phone;
                document.getElementById('shelter_id').value = data.shelter_id;
                
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i> แก้ไขข้อมูลผู้ดูแล';
                document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกการแก้ไข';
                
                modal.show();
            }
        }, 100);
    }

    // ฟังก์ชันยืนยันการลบ
    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "ข้อมูลผู้ดูแลจะถูกลบถาวร",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `caretaker_list.php?action=delete&id=${id}`;
            }
        });
    }
</script>

</body>
</html>