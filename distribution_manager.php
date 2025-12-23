<?php
/**
 * Distribution Manager (หน้าจอเบิกจ่ายสิ่งของช่วยเหลือ)
 * Version: Compatible (รองรับทั้ง $pdo และ $db)
 */

// 1. Start Session
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 2. Include Config & Functions
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

// ----------------------------------------------------------------------------------
// [SMART CONNECTION] ระบบเชื่อมต่อฐานข้อมูลอัจฉริยะ
// แก้ปัญหา Error: Undefined variable $db หรือ $pdo
// ----------------------------------------------------------------------------------
if (!isset($pdo)) {
    // กรณีใช้ Config แบบ Class ($db)
    if (isset($db) && property_exists($db, 'pdo')) {
        $pdo = $db->pdo;
    } 
    // กรณีไม่มีตัวแปรใดๆ เลย (Error)
    else {
        die("<div class='alert alert-danger'>
                <h3>Database Error</h3>
                ไม่พบตัวแปรเชื่อมต่อฐานข้อมูล (\$pdo หรือ \$db) 
                กรุณาตรวจสอบไฟล์ <code>config/db.php</code>
             </div>");
    }
}
// ----------------------------------------------------------------------------------

// 3. Determine Shelter ID (เลือกศูนย์พักพิง)
// ลำดับ: GET -> SESSION -> 0
$shelter_id = isset($_GET['shelter_id']) ? (int)$_GET['shelter_id'] : ($_SESSION['shelter_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'staff';
$can_switch_shelter = ($user_role === 'admin');

// 4. Fetch Shelters (สำหรับ Admin เลือกศูนย์)
$shelters = [];
if ($can_switch_shelter) {
    try {
        $stmt_s = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed' ORDER BY name");
        if ($stmt_s) {
            $shelters = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
            // ถ้ายังไม่เลือกศูนย์ ให้เลือกอันแรกอัตโนมัติ
            if (!$shelter_id && count($shelters) > 0) {
                $shelter_id = $shelters[0]['id'];
            }
        }
    } catch (PDOException $e) { /* Table shelters might not exist yet */ }
} else {
    // User ทั่วไป บังคับใช้ศูนย์ของตนเอง
    if (isset($_SESSION['shelter_id'])) {
        $shelter_id = $_SESSION['shelter_id'];
    }
}

// 5. Fetch Inventory Data (ดึงของในคลัง)
$inventory = [];
if ($shelter_id) {
    try {
        // ใช้ Prepared Statement ป้องกัน SQL Injection
        // ดึงเฉพาะของที่มีในศูนย์นี้
        $sql = "SELECT * FROM inventory WHERE shelter_id = ? ORDER BY quantity DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$shelter_id]);
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Inventory Error: " . $e->getMessage());
    }
}

// 6. Handle Search Evacuee (ค้นหาผู้ประสบภัยที่จะรับของ)
$evacuee = null;
$search_key = isset($_GET['search_evacuee']) ? trim($_GET['search_evacuee']) : '';

if ($search_key && $shelter_id) {
    try {
        // ค้นหาจาก ID Card หรือ ชื่อ-นามสกุล
        $sql_ev = "SELECT * FROM evacuees 
                   WHERE shelter_id = ? 
                   AND (status != 'check_out' OR status IS NULL) 
                   AND (id_card LIKE ? OR first_name LIKE ? OR last_name LIKE ?) 
                   LIMIT 1";
        $stmt_ev = $pdo->prepare($sql_ev);
        $term = "%$search_key%";
        $stmt_ev->execute([$shelter_id, $term, $term, $term]);
        $evacuee = $stmt_ev->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Search Error: " . $e->getMessage());
    }
}

$page_title = "บริหารจัดการสิ่งของ";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .card-stock { border-left: 4px solid #3b82f6; transition: transform 0.2s; }
        .card-stock:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bg-low-stock { background-color: #fff1f2 !important; }
        .text-low-stock { color: #e11d48 !important; font-weight: bold; }
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }
        .item-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border-radius: 50%; color: #64748b; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<!-- Alert Handler -->
<?php if (isset($_SESSION['success']) || isset($_SESSION['swal_success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: '<?php echo $_SESSION['success'] ?? $_SESSION['swal_success']; ?>',
            timer: 2000,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['success'], $_SESSION['swal_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error']) || isset($_SESSION['swal_error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: '<?php echo $_SESSION['error'] ?? $_SESSION['swal_error']; ?>'
        });
    </script>
    <?php unset($_SESSION['error'], $_SESSION['swal_error']); ?>
<?php endif; ?>

<div class="container mt-4 mb-5">

    <!-- Header & Shelter Selector -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded shadow-sm border-bottom border-primary border-3">
        <div>
            <h4 class="fw-bold text-primary mb-1"><i class="fas fa-boxes-stacked me-2"></i>บริหารจัดการสิ่งของ</h4>
            <p class="text-muted small mb-0">จัดการคลังสินค้า รับบริจาค และแจกจ่ายผู้ประสบภัย</p>
        </div>
        
        <?php if ($can_switch_shelter): ?>
        <form action="" method="GET" class="d-flex gap-2 align-items-center">
            <label class="fw-bold text-muted small text-nowrap">เลือกศูนย์:</label>
            <select name="shelter_id" class="form-select form-select-sm w-auto shadow-sm" onchange="this.form.submit()">
                <option value="">-- กรุณาเลือก --</option>
                <?php foreach ($shelters as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php else: ?>
            <div class="badge bg-primary text-white fs-6 px-3 py-2 shadow-sm">
                <i class="fas fa-campground"></i> ศูนย์: <?php echo htmlspecialchars($_SESSION['shelter_name'] ?? $shelter_id); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$shelter_id): ?>
        <div class="alert alert-warning text-center shadow-sm p-5 mt-5">
            <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
            <h4>กรุณาเลือกศูนย์พักพิง</h4>
            <p class="text-muted">โปรดเลือกศูนย์พักพิงที่ต้องการจัดการข้อมูลจากเมนูด้านบน</p>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- LEFT: Inventory List -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-clipboard-list text-primary me-2"></i>คลังสินค้า (Inventory)</h5>
                    <button class="btn btn-sm btn-success fw-bold shadow-sm px-3" id="btnDonation">
                        <i class="fas fa-plus-circle me-1"></i> รับบริจาค / เพิ่มของ
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-uppercase small text-muted">
                                    <th class="ps-3 py-3">ชื่อรายการ</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-center">คงเหลือ</th>
                                    <th class="text-end pe-3">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($inventory)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-box-open fa-2x mb-2"></i><br>ยังไม่มีรายการสิ่งของในคลัง</td></tr>
                                <?php else: ?>
                                    <?php foreach ($inventory as $item): 
                                        $qty = (int)$item['quantity'];
                                        $low_stock = $qty < 10;
                                        $category = isset($item['category']) ? $item['category'] : 'ทั่วไป';
                                        
                                        // Icon logic
                                        $icon = 'fa-box';
                                        if(strpos($category, 'food') !== false) $icon = 'fa-utensils';
                                        elseif(strpos($category, 'medicine') !== false) $icon = 'fa-briefcase-medical';
                                        elseif(strpos($category, 'clothes') !== false) $icon = 'fa-tshirt';
                                    ?>
                                    <tr class="<?php echo $low_stock ? 'bg-low-stock' : ''; ?>">
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center">
                                                <div class="item-icon me-3 <?php echo $low_stock ? 'bg-danger bg-opacity-10 text-danger' : ''; ?>">
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($item['unit']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary border"><?php echo htmlspecialchars($category); ?></span></td>
                                        <td class="text-center">
                                            <span class="fs-5 <?php echo $low_stock ? 'text-low-stock' : 'text-primary fw-bold'; ?>">
                                                <?php echo number_format($qty); ?>
                                            </span>
                                            <?php if($low_stock): ?>
                                                <br><small class="text-danger" style="font-size: 0.7em;">ใกล้หมด</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="distribution_history.php?inventory_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary" title="ดูประวัติ">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Distribute Panel -->
        <div class="col-lg-5">
            
            <!-- 1. Search Evacuee -->
            <div class="card shadow-sm border-0 mb-4 bg-primary bg-opacity-10">
                <div class="card-body">
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-hand-holding-heart me-2"></i>แจกจ่ายสิ่งของ</h5>
                    
                    <form action="" method="GET" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                        <input type="text" name="search_evacuee" class="form-control" placeholder="ค้นหา: ชื่อ หรือ เลขบัตร..." value="<?php echo htmlspecialchars($search_key); ?>" autofocus>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </form>

                    <?php if ($search_key && !$evacuee): ?>
                        <div class="alert alert-danger py-2 d-flex align-items-center shadow-sm">
                            <i class="fas fa-times-circle me-2"></i> <small>ไม่พบข้อมูลผู้ประสบภัย (หรือ Check-out แล้ว)</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($evacuee): ?>
                        <div class="bg-white p-3 rounded shadow-sm border border-primary position-relative">
                            <span class="position-absolute top-0 end-0 badge bg-success m-2">พบข้อมูล</span>
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded-circle p-3 me-3" style="width: 50px; height: 50px; display:flex; justify-content:center; align-items:center;">
                                    <i class="fas fa-user fa-lg"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($evacuee['first_name'] . ' ' . $evacuee['last_name']); ?></div>
                                    <div class="small text-muted">
                                        <i class="fas fa-id-card me-1"></i> 
                                        <?php echo function_exists('maskIDCard') ? maskIDCard($evacuee['id_card']) : htmlspecialchars($evacuee['id_card']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-3 border-secondary opacity-25">
                            
                            <!-- Distribute Form -->
                            <form action="distribution_save.php" method="POST">
                                <input type="hidden" name="action" value="distribute">
                                <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                                <input type="hidden" name="evacuee_id" value="<?php echo $evacuee['id']; ?>">
                                <?php if(function_exists('generateCSRFToken')): ?>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold text-uppercase">เลือกสิ่งของที่จะแจก</label>
                                    <select name="inventory_id" id="dist_inv_id" class="form-select" required>
                                        <option value="">-- เลือกรายการ --</option>
                                        <?php foreach ($inventory as $item): ?>
                                            <?php if($item['quantity'] > 0): ?>
                                            <option value="<?php echo $item['id']; ?>" data-max="<?php echo $item['quantity']; ?>" data-unit="<?php echo $item['unit']; ?>">
                                                <?php echo htmlspecialchars($item['item_name']); ?> (เหลือ <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>)
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small text-muted fw-bold text-uppercase">จำนวน</label>
                                    <div class="input-group">
                                        <button class="btn btn-outline-secondary" type="button" onclick="stepDown('qty')"><i class="fas fa-minus"></i></button>
                                        <input type="number" name="quantity" id="qty" class="form-control text-center fw-bold text-primary" value="1" min="1" required>
                                        <span class="input-group-text bg-light" id="qty_unit_label">หน่วย</span>
                                        <button class="btn btn-outline-secondary" type="button" onclick="stepUp('qty')"><i class="fas fa-plus"></i></button>
                                    </div>
                                    <div id="max_qty_hint" class="form-text text-end text-danger d-none small mt-1"></div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                                    <i class="fas fa-check-circle me-1"></i> ยืนยันการแจก
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-search fa-2x mb-2 opacity-50"></i>
                            <p class="small mb-0">ค้นหาผู้ประสบภัยเพื่อเริ่มแจกจ่าย</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Recent History (Log Preview) -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-2 border-bottom">
                    <small class="fw-bold text-muted text-uppercase"><i class="fas fa-history me-1"></i> ประวัติความเคลื่อนไหวล่าสุด</small>
                </div>
                <div class="list-group list-group-flush small">
                    <?php
                    // Display Recent Logs
                    try {
                        $sql_log = "SELECT d.*, i.item_name, i.unit 
                                    FROM distributions d 
                                    JOIN inventory i ON d.inventory_id = i.id 
                                    WHERE i.shelter_id = ? 
                                    ORDER BY d.created_at DESC LIMIT 5";
                        $stmt_log = $pdo->prepare($sql_log);
                        $stmt_log->execute([$shelter_id]);
                        
                        $count = 0;
                        while($log = $stmt_log->fetch(PDO::FETCH_ASSOC)):
                            $count++;
                    ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-danger me-2"><i class="fas fa-arrow-up"></i></span>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($log['item_name']); ?></span>
                                <div class="text-muted" style="font-size: 0.8em;">
                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                            <span class="badge bg-light text-dark border rounded-pill px-3">
                                - <?php echo number_format($log['quantity']); ?> <?php echo htmlspecialchars($log['unit']); ?>
                            </span>
                        </div>
                    <?php 
                        endwhile;
                        if ($count == 0) {
                            echo '<div class="p-4 text-center text-muted"><i class="fas fa-clipboard-check mb-2"></i><br>ยังไม่มีประวัติการแจกจ่าย</div>';
                        }
                    } catch (PDOException $e) {
                         // Fallback logic if table doesn't exist yet
                         echo '<div class="p-3 text-center text-muted small">ยังไม่มีข้อมูล</div>';
                    }
                    ?>
                </div>
                <div class="card-footer bg-white text-center py-2">
                    <a href="distribution_history.php?shelter_id=<?php echo $shelter_id; ?>" class="btn btn-sm btn-link text-decoration-none">ดูประวัติทั้งหมด</a>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Add Donation (รับบริจาค) -->
<div class="modal fade" id="donationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="distribution_save.php" method="POST">
            <div class="modal-content shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-box-open me-2"></i>รับบริจาค / เพิ่มสต็อก</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_stock">
                    <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                    <?php if(function_exists('generateCSRFToken')): ?>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อสิ่งของ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            <input type="text" name="item_name" class="form-control" list="item_suggestions" required placeholder="ระบุชื่อสิ่งของ..." autocomplete="off">
                        </div>
                        <datalist id="item_suggestions">
                            <option value="น้ำดื่ม (แพ็ค)">
                            <option value="ข้าวสาร (ถุง 5kg)">
                            <option value="บะหมี่กึ่งสำเร็จรูป (กล่อง)">
                            <option value="ปลากระป๋อง (กระป๋อง)">
                            <option value="ยาสามัญประจำบ้าน (ชุด)">
                            <option value="ผ้าห่ม (ผืน)">
                            <option value="มุ้ง (หลัง)">
                            <option value="ยากันยุง (กล่อง)">
                        </datalist>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">หมวดหมู่</label>
                            <select name="category" class="form-select">
                                <option value="general" selected>ของใช้ทั่วไป</option>
                                <option value="food">อาหาร/เครื่องดื่ม</option>
                                <option value="medicine">ยา/เวชภัณฑ์</option>
                                <option value="clothes">เครื่องนุ่งห่ม</option>
                                <option value="baby">สำหรับเด็ก/ทารก</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">หน่วยนับ <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" placeholder="เช่น ชิ้น, แพ็ค" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">จำนวนที่รับเข้า <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control form-control-lg text-center text-success fw-bold" required min="1" placeholder="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">แหล่งที่มา / ผู้บริจาค</label>
                        <input type="text" name="source" class="form-control" placeholder="เช่น กาชาด, คุณใจดี, งบอบจ.">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fas fa-save me-1"></i> บันทึกรับของ</button>
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

        // Initialize Donation Modal
        const btnDonation = document.getElementById('btnDonation');
        if(btnDonation) {
            btnDonation.addEventListener('click', function() {
                var myModal = new bootstrap.Modal(document.getElementById('donationModal'));
                myModal.show();
            });
        }
        
        // Dynamic Unit & Max Limit Logic
        const distInvSelect = document.getElementById('dist_inv_id');
        const qtyInput = document.getElementById('qty');
        const hint = document.getElementById('max_qty_hint');
        const unitLabel = document.getElementById('qty_unit_label');
        
        if(distInvSelect) {
            distInvSelect.addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const max = selected.getAttribute('data-max');
                const unit = selected.getAttribute('data-unit');
                
                // Update Unit Label
                if(unit) unitLabel.textContent = unit;
                else unitLabel.textContent = 'หน่วย';

                // Update Max Limit
                if(max) {
                    qtyInput.setAttribute('max', max);
                    hint.textContent = 'เบิกได้สูงสุด ' + max + ' ' + (unit || 'หน่วย');
                    hint.classList.remove('d-none');
                } else {
                    qtyInput.removeAttribute('max');
                    hint.classList.add('d-none');
                }
            });
        }
    });

    function stepUp(id) {
        var el = document.getElementById(id);
        if(el) el.stepUp();
    }
    function stepDown(id) {
        var el = document.getElementById(id);
        if(el) el.stepDown();
    }
</script>
</body>
</html>