<?php
// request_manager.php
// ระบบศูนย์ประสานงานและร้องขอความช่วยเหลือ (Request & Command Center)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;

// --- Handle Form Submission (Add Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_request') {
    $category = cleanInput($_POST['category']);
    $urgency = cleanInput($_POST['urgency']);
    $detail = cleanInput($_POST['detail']);
    $quantity = cleanInput($_POST['quantity']);
    
    // ถ้าเป็น Admin ต้องเลือกศูนย์ได้ ถ้าเป็น Staff ใช้ศูนย์ตัวเอง
    $target_shelter = ($role === 'admin') ? $_POST['shelter_id'] : $my_shelter_id;
    
    if ($target_shelter) {
        $stmt = $pdo->prepare("INSERT INTO shelter_requests (shelter_id, category, urgency, detail, quantity, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$target_shelter, $category, $urgency, $detail, $quantity, $_SESSION['user_id']]);
        $_SESSION['swal_success'] = "ส่งคำร้องเรียบร้อยแล้ว";
    }
    header("Location: request_manager.php");
    exit();
}

// --- Handle Status Update (Admin Only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if ($role === 'admin') {
        $req_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        $note = cleanInput($_POST['response_note']);
        
        $stmt = $pdo->prepare("UPDATE shelter_requests SET status = ?, response_note = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $note, $req_id]);
        $_SESSION['swal_success'] = "อัปเดตสถานะเรียบร้อยแล้ว";
    }
    header("Location: request_manager.php");
    exit();
}

// --- Fetch Requests ---
$sql = "SELECT r.*, s.name as shelter_name, u.username 
        FROM shelter_requests r
        JOIN shelters s ON r.shelter_id = s.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE 1=1 ";

// ถ้าไม่ใช่ Admin เห็นแค่ของศูนย์ตัวเอง
if ($role !== 'admin') {
    $sql .= " AND r.shelter_id = $my_shelter_id";
}

// Filter Status
$filter_status = $_GET['status'] ?? '';
if ($filter_status) {
    $sql .= " AND r.status = " . $pdo->quote($filter_status);
}

$sql .= " ORDER BY FIELD(r.urgency, 'critical', 'high', 'normal'), r.created_at DESC";
$requests = $pdo->query($sql)->fetchAll();

// Fetch Shelters for dropdown (Admin use)
$shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>ศูนย์ประสานงานและร้องขอ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .border-critical { border-left: 5px solid #dc3545 !important; }
        .border-high { border-left: 5px solid #fd7e14 !important; }
        .border-normal { border-left: 5px solid #0d6efd !important; }
        
        /* Fix Modal Backdrop Issue */
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-bullhorn text-primary me-2"></i>ศูนย์ประสานงานและร้องขอ</h3>
        <button class="btn btn-primary fw-bold" id="btnNewRequest">
            <i class="fas fa-plus-circle me-1"></i> สร้างคำร้องใหม่
        </button>
    </div>

    <!-- Filter Buttons -->
    <div class="mb-3">
        <a href="request_manager.php" class="btn btn-sm btn-outline-secondary <?php echo !$filter_status ? 'active' : ''; ?>">ทั้งหมด</a>
        <a href="request_manager.php?status=pending" class="btn btn-sm btn-outline-warning <?php echo $filter_status=='pending' ? 'active' : ''; ?>">รอรับเรื่อง</a>
        <a href="request_manager.php?status=in_progress" class="btn btn-sm btn-outline-info <?php echo $filter_status=='in_progress' ? 'active' : ''; ?>">กำลังดำเนินการ</a>
        <a href="request_manager.php?status=completed" class="btn btn-sm btn-outline-success <?php echo $filter_status=='completed' ? 'active' : ''; ?>">เสร็จสิ้น</a>
    </div>

    <div class="row">
        <?php if(empty($requests)): ?>
            <div class="col-12 text-center py-5 text-muted">ไม่มีรายการคำร้อง</div>
        <?php else: ?>
            <?php foreach($requests as $req): 
                $borderClass = 'border-' . $req['urgency'];
                $bgClass = $req['status'] == 'completed' ? 'bg-light opacity-75' : 'bg-white';
                
                $statusBadges = [
                    'pending' => '<span class="badge bg-warning text-dark"><i class="far fa-clock"></i> รอรับเรื่อง</span>',
                    'approved' => '<span class="badge bg-info"><i class="fas fa-check"></i> รับเรื่องแล้ว</span>',
                    'in_progress' => '<span class="badge bg-primary"><i class="fas fa-shipping-fast"></i> กำลังดำเนินการ</span>',
                    'completed' => '<span class="badge bg-success"><i class="fas fa-check-circle"></i> เสร็จสิ้น</span>',
                    'rejected' => '<span class="badge bg-secondary">ยกเลิก/ปฏิเสธ</span>'
                ];
            ?>
            <div class="col-lg-6 mb-3">
                <div class="card shadow-sm h-100 <?php echo $bgClass; ?> <?php echo $borderClass; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <h5 class="card-title fw-bold text-dark">
                                <?php if($role === 'admin'): ?>
                                    <small class="text-muted d-block fs-6 mb-1"><i class="fas fa-campground"></i> <?php echo htmlspecialchars($req['shelter_name']); ?></small>
                                <?php endif; ?>
                                <?php 
                                    $iconMap = ['supplies'=>'box', 'medical'=>'briefcase-medical', 'manpower'=>'users', 'transport'=>'truck', 'other'=>'question-circle'];
                                    $icon = $iconMap[$req['category']] ?? 'circle';
                                ?>
                                <i class="fas fa-<?php echo $icon; ?> me-1"></i> <?php echo ucfirst($req['category']); ?>
                            </h5>
                            <div><?php echo $statusBadges[$req['status']] ?? $req['status']; ?></div>
                        </div>

                        <p class="card-text text-secondary mb-1">
                            <?php echo nl2br(htmlspecialchars($req['detail'])); ?>
                            <?php if($req['quantity']): ?>
                                <br><strong class="text-dark">จำนวน:</strong> <?php echo htmlspecialchars($req['quantity']); ?>
                            <?php endif; ?>
                        </p>

                        <div class="d-flex justify-content-between align-items-end mt-3">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i> <?php echo $req['username']; ?> • 
                                <?php echo thaiDate(date('Y-m-d', strtotime($req['created_at']))); ?> <?php echo date('H:i', strtotime($req['created_at'])); ?>
                                <?php if($req['urgency'] == 'critical'): ?>
                                    <br><span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> ด่วนที่สุด!</span>
                                <?php endif; ?>
                            </small>
                            
                            <?php if($role === 'admin'): ?>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick='updateStatus(<?php echo json_encode($req); ?>)'>
                                    <i class="fas fa-edit"></i> อัปเดตงาน
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($req['response_note']): ?>
                            <div class="alert alert-secondary mt-2 mb-0 py-2 small">
                                <strong>Admin Note:</strong> <?php echo htmlspecialchars($req['response_note']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: New Request -->
<div class="modal fade" id="newRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="request_manager.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">ส่งคำร้องขอความช่วยเหลือ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_request">
                    
                    <?php if($role === 'admin'): ?>
                    <div class="mb-3">
                        <label class="form-label">เลือกศูนย์พักพิง (แทน Staff)</label>
                        <select name="shelter_id" class="form-select" required>
                            <?php foreach($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category" class="form-select">
                                <option value="supplies">เสบียง/สิ่งของ (Supplies)</option>
                                <option value="medical">ยา/เวชภัณฑ์ (Medical)</option>
                                <option value="manpower">กำลังพล (Manpower)</option>
                                <option value="transport">ยานพาหนะ (Transport)</option>
                                <option value="other">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">ความเร่งด่วน</label>
                            <select name="urgency" class="form-select">
                                <option value="normal">ปกติ (Normal)</option>
                                <option value="high" class="text-warning fw-bold">ด่วน (High)</option>
                                <option value="critical" class="text-danger fw-bold">ด่วนที่สุด (Critical)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">รายละเอียดสิ่งที่ต้องการ <span class="text-danger">*</span></label>
                        <textarea name="detail" class="form-control" rows="3" required placeholder="ระบุสิ่งที่ต้องการ และเหตุผลความจำเป็น..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">จำนวน (ระบุหน่วย)</label>
                        <input type="text" name="quantity" class="form-control" placeholder="เช่น 50 แพ็ค, 2 คัน">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold">ส่งคำร้อง</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Update Status (Admin) -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="request_manager.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">อัปเดตสถานะงาน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="request_id" id="update_req_id">
                    
                    <div class="mb-3">
                        <label class="form-label">สถานะใหม่</label>
                        <select name="status" id="update_status" class="form-select">
                            <option value="pending">รอรับเรื่อง</option>
                            <option value="approved">รับเรื่องแล้ว (Approved)</option>
                            <option value="in_progress">กำลังดำเนินการ (In Progress)</option>
                            <option value="completed">เสร็จสิ้น (Completed)</option>
                            <option value="rejected">ปฏิเสธ/ยกเลิก</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">บันทึกเพิ่มเติม (Response Note)</label>
                        <textarea name="response_note" id="update_note" class="form-control" rows="3" placeholder="เช่น จัดส่งแล้วโดยรถทะเบียน..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-info fw-bold text-white">บันทึกสถานะ</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Fix for "Dim Screen / Can't Click": Move Modals to Body
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            document.body.appendChild(modal);
        });

        // Initialize Modals Logic
        const btnNew = document.getElementById('btnNewRequest');
        if(btnNew) {
            btnNew.addEventListener('click', function() {
                var myModal = new bootstrap.Modal(document.getElementById('newRequestModal'));
                myModal.show();
            });
        }
    });

    function updateStatus(req) {
        document.getElementById('update_req_id').value = req.id;
        document.getElementById('update_status').value = req.status;
        document.getElementById('update_note').value = req.response_note || '';
        
        var statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
        statusModal.show();
    }
</script>
</body>
</html>