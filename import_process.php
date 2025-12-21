<?php
// incident_manage.php

// --- DEBUG MODE: เปิดแสดง Error (ลบออกเมื่อใช้งานจริง) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -----------------------------------------------------

require_once 'config/db.php';
// เรียกใช้ header เพื่อเริ่ม Session
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: เฉพาะ Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 2. Logic การบันทึกข้อมูล (Handle POST Request)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ตรวจสอบว่ามีการส่ง Action มาหรือไม่
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $start_date = $_POST['start_date'];

        if (empty($name) || empty($type) || empty($start_date)) {
            $_SESSION['swal_error'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
        } else {
            try {
                // สร้างเหตุการณ์ใหม่
                $sql = "INSERT INTO incidents (name, type, status, start_date, created_at) VALUES (?, ?, 'active', ?, NOW())";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$name, $type, $start_date])) {
                    $_SESSION['swal_success'] = "เปิดภารกิจใหม่เรียบร้อยแล้ว";
                } else {
                    $_SESSION['swal_error'] = "ไม่สามารถบันทึกข้อมูลได้";
                }
            } catch (PDOException $e) {
                $_SESSION['swal_error'] = "Database Error: " . $e->getMessage();
            }
        }
    } 
    
    // Redirect กลับมาที่หน้าเดิม (PRG Pattern)
    header("Location: incident_manage.php");
    exit();
}

// 3. Logic การปิดงาน (Handle GET Request)
if (isset($_GET['action']) && $_GET['action'] == 'close' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE incidents SET status = 'closed', end_date = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['swal_success'] = "ปิดภารกิจเรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
    header("Location: incident_manage.php");
    exit();
}

// 4. ดึงข้อมูลมาแสดงผล
$incidents = [];
try {
    $sql = "SELECT * FROM incidents ORDER BY FIELD(status, 'active', 'closed'), created_at DESC";
    $stmt = $pdo->query($sql);
    if($stmt) {
        $incidents = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // กรณีตารางยังไม่ถูกสร้าง
    $db_error = "Table 'incidents' not found or DB Error: " . $e->getMessage();
}

// Count Stats
$active_count = 0;
$closed_count = 0;
foreach($incidents as $inc) {
    if($inc['status'] == 'active') $active_count++;
    else $closed_count++;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .table-official thead th {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border: none;
            font-weight: 500;
            padding: 12px 15px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-closed {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .summary-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            background: white;
            transition: transform 0.2s;
        }
        .summary-card:hover { transform: translateY(-3px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .summary-icon {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }

        /* Fix Modal Z-Index */
        .modal { z-index: 1060; }
        .modal-backdrop { z-index: 1050; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    
    <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-exclamation-circle text-danger me-2"></i>การจัดการเหตุการณ์ภัยพิบัติ
            </h4>
            <span class="text-muted small">Incident Command Management</span>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i>เปิดภารกิจใหม่
        </button>
    </div>

    <!-- DB Error Alert -->
    <?php if(isset($db_error)): ?>
        <div class="alert alert-danger shadow-sm">
            <strong>System Error:</strong> <?php echo $db_error; ?>
            <br>กรุณาตรวจสอบว่ามีตาราง <code>incidents</code> ในฐานข้อมูลแล้วหรือไม่
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="summary-card" style="border-left: 4px solid #10b981;">
                <div class="summary-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-bolt"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold">กำลังดำเนินการ</div>
                    <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($active_count); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card" style="border-left: 4px solid #64748b;">
                <div class="summary-icon bg-secondary bg-opacity-10 text-secondary">
                    <i class="fas fa-archive"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold">ปิดภารกิจแล้ว</div>
                    <h3 class="mb-0 fw-bold text-dark"><?php echo number_format($closed_count); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card" style="border-left: 4px solid #3b82f6;">
                <div class="summary-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold">เหตุการณ์ทั้งหมด</div>
                    <h3 class="mb-0 fw-bold text-dark"><?php echo number_format(count($incidents)); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (isset($_SESSION['swal_success'])): ?>
        <script>
            Swal.fire({icon: 'success', title: 'สำเร็จ', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 2000, showConfirmButton: false});
        </script>
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['swal_error'])): ?>
        <script>
            Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: '<?php echo $_SESSION['swal_error']; ?>'});
        </script>
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>

    <!-- Incident Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-official">
                    <thead>
                        <tr>
                            <th class="ps-4">ชื่อเหตุการณ์</th>
                            <th>ประเภทภัย</th>
                            <th>ระยะเวลาปฏิบัติงาน</th>
                            <th>สถานะ</th>
                            <th class="text-end pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($incidents) > 0): ?>
                            <?php foreach ($incidents as $row): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark" style="font-size: 1rem;"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <small class="text-muted">ID: <?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></small>
                                </td>
                                <td><?php echo ucfirst($row['type']); ?></td>
                                <td>
                                    <div class="small">
                                        <i class="far fa-calendar-check text-success me-1"></i> 
                                        เริ่ม: <?php echo date('d/m/Y', strtotime($row['start_date'])); ?>
                                    </div>
                                    <?php if($row['end_date']): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="far fa-calendar-times text-danger me-1"></i> 
                                        จบ: <?php echo date('d/m/Y', strtotime($row['end_date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['status'] == 'active'): ?>
                                        <span class="status-badge status-active">กำลังดำเนินการ</span>
                                    <?php else: ?>
                                        <span class="status-badge status-closed">ปิดภารกิจแล้ว</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="shelter_list.php?filter_incident=<?php echo $row['id']; ?>" class="btn btn-outline-secondary btn-sm" title="ดูศูนย์พักพิง">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if($row['status'] == 'active'): ?>
                                            <button onclick="confirmClose(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')" 
                                                    class="btn btn-outline-danger btn-sm" title="ปิดภารกิจ">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-light btn-sm text-muted border" disabled><i class="fas fa-check"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-light"></i><br>
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

<?php include 'includes/footer.php'; ?>

<!-- Modal: เพิ่มเหตุการณ์ใหม่ (ย้ายมาอยู่นอกสุด) -->
<div class="modal fade" id="newIncidentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <!-- Form Action ชี้หาตัวเอง -->
        <form action="incident_manage.php" method="POST" class="w-100">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i>เปิดภารกิจใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ชื่อเหตุการณ์</label>
                        <input type="text" name="name" class="form-control form-control-lg" placeholder="เช่น น้ำท่วมใหญ่ เชียงราย 2568" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">ประเภทภัย</label>
                            <select name="type" class="form-select" required>
                                <option value="flood">อุทกภัย (น้ำท่วม)</option>
                                <option value="fire">อัคคีภัย (ไฟไหม้)</option>
                                <option value="storm">วาตภัย (พายุ)</option>
                                <option value="landslide">ดินโคลนถล่ม</option>
                                <option value="other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">วันที่เริ่มเหตุการณ์</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // ฟังก์ชันเปิด Modal อย่างปลอดภัย
    function openAddModal() {
        const modalEl = document.getElementById('newIncidentModal');
        if (modalEl) {
            document.body.appendChild(modalEl); // Fix: ย้าย Modal ไปนอก Container ที่มี z-index
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
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
                window.location.href = `incident_manage.php?action=close&id=${id}`;
            }
        });
    }
</script>

</body>
</html>