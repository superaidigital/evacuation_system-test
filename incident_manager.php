<?php
// incident_manager.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // renderPagination
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: เฉพาะ Admin หรือ Staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------------
// 2. Logic: Handle Actions (Add / Edit / Delete / Toggle Status)
// ------------------------------------------------------------------

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['swal_error'] = "ขออภัย สิทธิ์ของคุณไม่สามารถลบข้อมูลได้";
    } else {
        $id = $_GET['id'];
        try {
            // Check usage first
            $check = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['swal_error'] = "ไม่สามารถลบเหตุการณ์นี้ได้ เนื่องจากมีการผูกกับศูนย์พักพิงแล้ว";
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

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    $mode = $_POST['action_type'];
    $id = $_POST['incident_id'] ?? '';
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // FIX: เปลี่ยนจาก incident_date เป็น start_date ตามฐานข้อมูล
    $date = $_POST['start_date']; // YYYY-MM-DD
    $status = $_POST['status']; // active, closed

    try {
        if ($mode == 'add') {
            // FIX: ใช้คอลัมน์ start_date
            $sql = "INSERT INTO incidents (name, description, start_date, status, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $date, $status]);
            
            logActivity($pdo, $_SESSION['user_id'], 'Add Incident', "เพิ่มเหตุการณ์: $name");
            $_SESSION['swal_success'] = "สร้างเหตุการณ์ใหม่เรียบร้อยแล้ว";

        } else if ($mode == 'edit') {
            // FIX: ใช้คอลัมน์ start_date
            $sql = "UPDATE incidents SET name=?, description=?, start_date=?, status=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $date, $status, $id]);
            
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

// Fetch Data (with Shelter Count)
// FIX: เปลี่ยน ORDER BY incident_date เป็น ORDER BY start_date
$sql = "SELECT i.*, 
        (SELECT COUNT(*) FROM shelters s WHERE s.incident_id = i.id) as shelter_count 
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

<div class="container-fluid">

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

    <!-- Alert Messages -->
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
                    <input type="text" name="search" class="form-control" placeholder="ชื่อเหตุการณ์..." value="<?php echo htmlspecialchars($keyword); ?>">
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
                            <th>รายละเอียด</th>
                            <th>วันที่เกิดเหตุ</th>
                            <th>สถานะ</th>
                            <th>ศูนย์พักพิง</th>
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
                                ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="small text-muted">ID: <?php echo $row['id']; ?></div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($row['description']); ?>">
                                        <?php echo htmlspecialchars($row['description']) ?: '-'; ?>
                                    </div>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt text-muted me-1"></i>
                                    <!-- FIX: ใช้ start_date -->
                                    <?php echo thaiDate(date('Y-m-d', strtotime($row['start_date']))); ?>
                                </td>
                                <td><?php echo $status_badge; ?></td>
                                <td>
                                    <a href="shelter_list.php?filter_incident=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary position-relative border-0 bg-primary bg-opacity-10">
                                        <i class="fas fa-campground me-1"></i> <?php echo number_format($row['shelter_count']); ?> แห่ง
                                    </a>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border me-1" 
                                            onclick='openModal("edit", <?php echo json_encode($row); ?>)' title="แก้ไข">
                                        <i class="fas fa-edit text-warning"></i>
                                    </button>
                                    
                                    <?php if($_SESSION['role'] == 'admin'): ?>
                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')" 
                                                class="btn btn-sm btn-light border" title="ลบ" 
                                                <?php echo $row['shelter_count'] > 0 ? 'disabled' : ''; ?>>
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
                    <!-- Hidden Fields -->
                    <input type="hidden" name="action_type" id="action_type" value="add">
                    <input type="hidden" name="incident_id" id="incident_id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อเหตุการณ์ <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="เช่น น้ำท่วมอุบลราชธานี 2567">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">รายละเอียด</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="ระบุรายละเอียดพื้นที่ หรือความรุนแรง..."></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">วันที่เกิดเหตุ <span class="text-danger">*</span></label>
                            <!-- FIX: เปลี่ยน name และ id เป็น start_date -->
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
                } else if (mode === 'edit' && data) {
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> แก้ไขเหตุการณ์';
                    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกการแก้ไข';
                    
                    document.getElementById('incident_id').value = data.id;
                    document.getElementById('name').value = data.name;
                    document.getElementById('description').value = data.description;
                    // FIX: ใช้ start_date
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