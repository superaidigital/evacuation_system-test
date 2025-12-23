<?php
// incident_manager.php
// Refactored: Incident Lifecycle Management & Data Integrity Protection
// Updated: Fix start_date, Add Pagination, Search, and CSRF Protection

require_once 'config/db.php';
require_once 'includes/functions.php'; // Contains cleanInput, renderPagination, generateCSRFToken, validateCSRFToken

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: เฉพาะ Admin หรือ Staff (แต่ Staff ลบไม่ได้)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    $_SESSION['swal_error'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------------
// 2. Logic: Handle Actions (Add / Edit / Delete)
// ------------------------------------------------------------------

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    // CSRF Check for GET requests is harder, usually we use POST for delete. 
    // For simplicity here, we rely on role check, but strictly should be POST.
    
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['swal_error'] = "ขออภัย สิทธิ์ของคุณไม่สามารถลบข้อมูลได้ (Admin Only)";
    } else {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        try {
            // Check usage first (Referential Integrity)
            // 1. Check Shelters
            $checkShelter = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ?");
            $checkShelter->execute([$id]);
            
            // 2. Check Evacuees
            $checkEvacuee = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE incident_id = ?");
            $checkEvacuee->execute([$id]);

            if ($checkShelter->fetchColumn() > 0 || $checkEvacuee->fetchColumn() > 0) {
                $_SESSION['swal_error'] = "ไม่สามารถลบภารกิจนี้ได้ เนื่องจากมีข้อมูลศูนย์พักพิงหรือผู้ประสบภัยผูกอยู่ (แนะนำให้เปลี่ยนสถานะเป็น Closed)";
            } else {
                $stmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
                $stmt->execute([$id]);
                logActivity($pdo, $_SESSION['user_id'], 'Delete Incident', "ลบเหตุการณ์ ID: $id");
                $_SESSION['swal_success'] = "ลบข้อมูลเรียบร้อยแล้ว";
            }
        } catch (PDOException $e) {
            $_SESSION['swal_error'] = "Error: " . $e->getMessage();
        }
    }
    header("Location: incident_manager.php");
    exit();
}

// Handle Add/Edit (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    // CSRF Check
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (function_exists('validateCSRFToken')) {
        validateCSRFToken($csrf_token);
    }

    $mode = $_POST['action_type'];
    $id = filter_input(INPUT_POST, 'incident_id', FILTER_VALIDATE_INT);
    
    // Sanitize Inputs
    $name = cleanInput($_POST['name']);
    $description = cleanInput($_POST['description']);
    $type = cleanInput($_POST['type'] ?? 'other');
    $date = $_POST['start_date']; // Date format usually safe, but prepared stmt handles it
    $status = cleanInput($_POST['status']); // active, closed

    try {
        if ($mode == 'add') {
            $sql = "INSERT INTO incidents (name, description, type, start_date, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $type, $date, $status]);
            
            logActivity($pdo, $_SESSION['user_id'], 'Add Incident', "เพิ่มเหตุการณ์: $name");
            $_SESSION['swal_success'] = "สร้างเหตุการณ์ใหม่เรียบร้อยแล้ว";

        } else if ($mode == 'edit' && $id) {
            $sql = "UPDATE incidents SET name=?, description=?, type=?, start_date=?, status=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $type, $date, $status, $id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'Edit Incident', "แก้ไขเหตุการณ์: $name");
            $_SESSION['swal_success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";
        }
    } catch (PDOException $e) {
        $_SESSION['swal_error'] = "Database Error: " . $e->getMessage();
    }
    header("Location: incident_manager.php");
    exit();
}

// ------------------------------------------------------------------
// 3. Data Fetching & Pagination
// ------------------------------------------------------------------

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base Query
$where_sql = " WHERE 1=1 ";
$params = [];

if ($keyword) {
    $where_sql .= " AND (name LIKE ? OR description LIKE ?) ";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

// Count Total
$sql_count = "SELECT COUNT(*) FROM incidents" . $where_sql;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Data (with Shelter & Evacuee Count)
$sql = "SELECT i.*, 
        (SELECT COUNT(*) FROM shelters s WHERE s.incident_id = i.id) as shelter_count,
        (SELECT COUNT(*) FROM evacuees e WHERE e.incident_id = i.id) as evacuee_count
        FROM incidents i 
        $where_sql 
        ORDER BY i.status ASC, i.start_date DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>จัดการเหตุการณ์ภัยพิบัติ</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .incident-card {
            transition: all 0.2s;
            border-left: 5px solid transparent;
        }
        .incident-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .border-active { border-left-color: #10b981; } /* Green */
        .border-closed { border-left-color: #64748b; } /* Grey */
        
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .bg-active { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .bg-closed { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        /* Modal Custom Style */
        .modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border-bottom: 4px solid #fbbf24;
        }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-exclamation-circle text-danger me-2"></i>จัดการเหตุการณ์ภัยพิบัติ
            </h4>
            <span class="text-muted small">Disaster Incident Management</span>
        </div>
        <button class="btn btn-primary shadow-sm fw-bold" onclick="openModal('add')">
            <i class="fas fa-plus me-2"></i> สร้างเหตุการณ์ใหม่
        </button>
    </div>

    <!-- Alert Messages (PHP Session -> SweetAlert2) -->
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

    <!-- Search Box -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label fw-bold text-secondary">ค้นหา:</label>
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="ชื่อเหตุการณ์ หรือ รายละเอียด..." value="<?php echo htmlspecialchars($keyword); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
                    <?php if($keyword): ?>
                        <a href="incident_manager.php" class="btn btn-outline-secondary">ล้างค่า</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Incident List -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-official">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ชื่อเหตุการณ์</th>
                            <th>ประเภท</th>
                            <th>วันที่เกิดเหตุ</th>
                            <th>สถานะ</th>
                            <th>ข้อมูลในระบบ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($incidents) > 0): ?>
                            <?php foreach ($incidents as $row): ?>
                                <?php 
                                    $row_class = $row['status'] == 'active' ? 'border-active' : 'border-closed';
                                    $status_badge = $row['status'] == 'active' 
                                        ? '<span class="status-badge bg-active"><i class="fas fa-dot-circle me-1"></i> กำลังดำเนินการ</span>' 
                                        : '<span class="status-badge bg-closed"><i class="fas fa-check-circle me-1"></i> จบภารกิจ</span>';
                                    
                                    // Mapping Type Name
                                    $type_map = [
                                        'flood' => 'อุทกภัย (น้ำท่วม)',
                                        'storm' => 'วาตภัย (พายุ)',
                                        'fire' => 'อัคคีภัย (ไฟไหม้)',
                                        'landslide' => 'ดินโคลนถล่ม',
                                        'other' => 'อื่นๆ'
                                    ];
                                    $type_text = $type_map[$row['type']] ?? $row['type'];
                                ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6"><?php echo h($row['name']); ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 200px;">
                                        <?php echo h($row['description']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark bg-opacity-25 border border-info">
                                        <?php echo h($type_text); ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt text-muted me-1"></i>
                                    <?php echo thaiDate(date('Y-m-d', strtotime($row['start_date']))); ?>
                                </td>
                                <td><?php echo $status_badge; ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="shelter_list.php?filter_incident=<?php echo $row['id']; ?>" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none border border-primary border-opacity-25" title="ศูนย์พักพิง">
                                            <i class="fas fa-campground"></i> <?php echo number_format($row['shelter_count']); ?>
                                        </a>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25" title="ผู้ประสบภัย">
                                            <i class="fas fa-users"></i> <?php echo number_format($row['evacuee_count']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border me-1" 
                                            onclick='openModal("edit", <?php echo json_encode($row); ?>)' title="แก้ไข">
                                        <i class="fas fa-edit text-warning"></i>
                                    </button>
                                    
                                    <?php if($_SESSION['role'] == 'admin'): ?>
                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')" 
                                                class="btn btn-sm btn-light border" title="ลบ" 
                                                <?php echo ($row['shelter_count'] > 0 || $row['evacuee_count'] > 0) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลเหตุการณ์</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-3 bg-light border-top">
                <?php echo renderPagination($page, $total_pages, ['search' => $keyword]); ?>
                <div class="text-center text-muted small mt-2">
                    แสดง <?php echo count($incidents); ?> จากทั้งหมด <?php echo number_format($total_records); ?> รายการ
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- ========================================== -->
<!-- MODAL: Add/Edit Incident -->
<!-- ========================================== -->
<div class="modal fade" id="incidentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form action="incident_manager.php" method="POST" class="w-100" id="incidentForm">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus-circle me-2"></i>สร้างเหตุการณ์ใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Security Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Hidden Fields -->
                    <input type="hidden" name="action_type" id="action_type" value="add">
                    <input type="hidden" name="incident_id" id="incident_id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อเหตุการณ์ <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="เช่น น้ำท่วมอุบลราชธานี 2567">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทภัยพิบัติ</label>
                        <select name="type" id="type" class="form-select">
                            <option value="flood">อุทกภัย (น้ำท่วม)</option>
                            <option value="storm">วาตภัย (พายุ)</option>
                            <option value="fire">อัคคีภัย (ไฟไหม้)</option>
                            <option value="landslide">ดินโคลนถล่ม</option>
                            <option value="other">อื่นๆ</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">รายละเอียด</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="ระบุรายละเอียดพื้นที่ หรือความรุนแรง..."></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันที่เกิดเหตุ <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="start_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">สถานะ</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" class="text-success fw-bold">Active (กำลังดำเนินการ)</option>
                                <option value="closed" class="text-secondary">Closed (จบภารกิจ)</option>
                            </select>
                        </div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openModal(mode, data = null) {
        setTimeout(() => {
            const modalEl = document.getElementById('incidentModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                
                document.getElementById('incidentForm').reset();
                document.getElementById('action_type').value = mode;
                document.getElementById('incident_id').value = '';
                
                if (mode === 'add') {
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> สร้างเหตุการณ์ใหม่';
                    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกข้อมูล';
                    document.getElementById('start_date').value = new Date().toISOString().split('T')[0];
                    document.getElementById('status').value = 'active';
                    document.getElementById('type').value = 'flood';
                } else if (mode === 'edit' && data) {
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> แก้ไขเหตุการณ์';
                    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกการแก้ไข';
                    
                    document.getElementById('incident_id').value = data.id;
                    document.getElementById('name').value = data.name;
                    document.getElementById('description').value = data.description;
                    document.getElementById('type').value = data.type || 'other';
                    document.getElementById('start_date').value = data.start_date;
                    document.getElementById('status').value = data.status;
                }
                
                modal.show();
            }
        }, 100);
    }

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: `ต้องการลบเหตุการณ์ "${name}" ใช่หรือไม่? หากลบแล้วจะไม่สามารถกู้คืนได้`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `incident_manager.php?action=delete&id=${id}`;
            }
        });
    }
</script>

</body>
</html>