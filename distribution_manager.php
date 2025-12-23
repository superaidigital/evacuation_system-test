<?php
// distribution_manager.php
// ระบบบริหารจัดการสิ่งของ: คลังสินค้า, รับบริจาค, และแจกจ่ายผู้ประสบภัย
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// 1. Determine Shelter ID
$shelter_id = $_GET['shelter_id'] ?? ($_SESSION['shelter_id'] ?? 0);
$can_switch_shelter = ($_SESSION['role'] === 'admin');

// 2. Fetch Shelters (For Admin selector)
$shelters = [];
if ($can_switch_shelter) {
    $shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed' ORDER BY name")->fetchAll();
    if (!$shelter_id && count($shelters) > 0) {
        $shelter_id = $shelters[0]['id'];
    }
}

// 3. Fetch Inventory Data
$inventory = [];
if ($shelter_id) {
    // FIX: ลบ 'category' ออกจาก ORDER BY เพื่อแก้ปัญหา Fatal Error กรณี Database ยังไม่ได้อัปเดต
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE shelter_id = ? ORDER BY item_name ASC");
    $stmt->execute([$shelter_id]);
    $inventory = $stmt->fetchAll();
}

// 4. Handle Search Evacuee
$evacuee = null;
$search_key = $_GET['search_evacuee'] ?? '';
if ($search_key && $shelter_id) {
    $sql_ev = "SELECT * FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL AND (id_card LIKE ? OR first_name LIKE ? OR last_name LIKE ?) LIMIT 1";
    $stmt_ev = $pdo->prepare($sql_ev);
    $term = "%$search_key%";
    $stmt_ev->execute([$shelter_id, $term, $term, $term]);
    $evacuee = $stmt_ev->fetch();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>บริหารจัดการสิ่งของ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card-stock { border-left: 4px solid #3b82f6; transition: transform 0.2s; }
        .card-stock:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .bg-low-stock { background-color: #fef2f2; border-left-color: #ef4444; }
        
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<!-- Alert Handler -->
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

<div class="container mt-4">

    <!-- Header & Shelter Selector -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-primary"><i class="fas fa-box-open me-2"></i>บริหารจัดการสิ่งของ</h3>
            <p class="text-muted small mb-0">รับบริจาค และ แจกจ่ายสิ่งของให้ผู้ประสบภัย</p>
        </div>
        
        <?php if ($can_switch_shelter): ?>
        <form action="" method="GET" class="d-flex gap-2">
            <select name="shelter_id" class="form-select w-auto" onchange="this.form.submit()">
                <option value="">-- เลือกศูนย์พักพิง --</option>
                <?php foreach ($shelters as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php else: ?>
            <div class="badge bg-info text-dark fs-6 px-3 py-2">
                <i class="fas fa-campground"></i> ศูนย์: <?php echo $_SESSION['shelter_id']; ?> (Staff)
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$shelter_id): ?>
        <div class="alert alert-warning text-center">กรุณาเลือกศูนย์พักพิงเพื่อจัดการข้อมูล</div>
    <?php else: ?>

    <div class="row g-4">
        <!-- LEFT: Inventory List -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-clipboard-list text-primary me-2"></i>คลังสินค้าปัจจุบัน</h5>
                    <button class="btn btn-sm btn-success fw-bold" id="btnDonation">
                        <i class="fas fa-plus-circle me-1"></i> รับบริจาค / เพิ่มของ
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">รายการ</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-center">คงเหลือ</th>
                                    <th>หน่วย</th>
                                    <th class="text-end pe-3">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($inventory)): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่มีรายการสิ่งของในคลัง</td></tr>
                                <?php else: ?>
                                    <?php foreach ($inventory as $item): 
                                        $low_stock = $item['quantity'] < 10;
                                        // FIX: ป้องกัน Error Undefined index ถ้าไม่มีคอลัมน์ category
                                        $category = isset($item['category']) ? $item['category'] : 'ทั่วไป';
                                    ?>
                                    <tr class="<?php echo $low_stock ? 'bg-low-stock' : ''; ?>">
                                        <td class="ps-3 fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo htmlspecialchars($category); ?></span></td>
                                        <td class="text-center fs-5 <?php echo $low_stock ? 'text-danger fw-bold' : 'text-primary'; ?>">
                                            <?php echo number_format($item['quantity']); ?>
                                        </td>
                                        <td><?php echo $item['unit']; ?></td>
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

        <!-- RIGHT: Distribute & Quick Actions -->
        <div class="col-lg-5">
            
            <!-- Search Evacuee to Distribute -->
            <div class="card shadow-sm border-0 bg-primary bg-opacity-10 mb-4">
                <div class="card-body">
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-hand-holding-heart me-2"></i>แจกจ่ายสิ่งของ</h5>
                    <form action="" method="GET" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                        <input type="text" name="search_evacuee" class="form-control" placeholder="ค้นหาชื่อ หรือ เลขบัตร..." value="<?php echo htmlspecialchars($search_key); ?>" autofocus>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </form>

                    <?php if ($search_key && !$evacuee): ?>
                        <div class="alert alert-danger py-2"><small>ไม่พบข้อมูลผู้ประสบภัยในศูนย์นี้</small></div>
                    <?php endif; ?>

                    <?php if ($evacuee): ?>
                        <div class="bg-white p-3 rounded shadow-sm border">
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-light rounded-circle p-2 me-2"><i class="fas fa-user text-primary"></i></div>
                                <div>
                                    <div class="fw-bold"><?php echo $evacuee['first_name'] . ' ' . $evacuee['last_name']; ?></div>
                                    <div class="small text-muted">บัตร: <?php echo function_exists('maskIDCard') ? maskIDCard($evacuee['id_card']) : $evacuee['id_card']; ?></div>
                                </div>
                            </div>
                            
                            <hr class="my-2">
                            
                            <form action="distribution_save.php" method="POST">
                                <input type="hidden" name="action" value="distribute">
                                <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                                <input type="hidden" name="evacuee_id" value="<?php echo $evacuee['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">

                                <div class="mb-2">
                                    <label class="small text-muted">เลือกสิ่งของ</label>
                                    <select name="inventory_id" class="form-select form-select-sm" required>
                                        <option value="">-- เลือกรายการ --</option>
                                        <?php foreach ($inventory as $item): ?>
                                            <?php if($item['quantity'] > 0): ?>
                                            <option value="<?php echo $item['id']; ?>">
                                                <?php echo $item['item_name']; ?> (เหลือ <?php echo $item['quantity']; ?>)
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-muted">จำนวน</label>
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('qty').stepDown()">-</button>
                                        <input type="number" name="quantity" id="qty" class="form-control text-center" value="1" min="1" max="100" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('qty').stepUp()">+</button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold">
                                    <i class="fas fa-check-circle me-1"></i> ยืนยันการแจก
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Logs (Preview) -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2">
                    <small class="fw-bold text-muted">ประวัติล่าสุด</small>
                </div>
                <div class="list-group list-group-flush small">
                    <?php
                    try {
                        // ปรับ Query ให้เรียบง่าย เพื่อป้องกัน Error ถ้าตาราง Transaction ยังไม่สมบูรณ์
                        $logs = $pdo->prepare("SELECT t.*, i.item_name FROM inventory_transactions t JOIN inventory i ON t.inventory_id = i.id WHERE i.shelter_id = ? ORDER BY t.created_at DESC LIMIT 5");
                        $logs->execute([$shelter_id]);
                        while($log = $logs->fetch()):
                            $is_in = $log['transaction_type'] == 'in';
                            $color = $is_in ? 'text-success' : 'text-danger';
                            $icon = $is_in ? 'fa-arrow-down' : 'fa-arrow-up';
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="<?php echo $color; ?> me-1"><i class="fas <?php echo $icon; ?>"></i></span>
                                <?php echo htmlspecialchars($log['item_name']); ?>
                                <span class="text-muted">(<?php echo $log['source'] ?: '-'; ?>)</span>
                            </div>
                            <span class="fw-bold"><?php echo number_format($log['quantity']); ?></span>
                        </div>
                        <?php endwhile; 
                    } catch (PDOException $e) {
                        echo '<div class="p-3 text-muted text-center">ยังไม่มีประวัติ หรือตารางยังไม่สมบูรณ์</div>';
                    }
                    ?>
                </div>
                <div class="card-footer bg-white text-center py-1">
                    <a href="distribution_history.php?shelter_id=<?php echo $shelter_id; ?>" class="small text-decoration-none">ดูทั้งหมด</a>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Add Donation -->
<div class="modal fade" id="donationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="distribution_save.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-box me-2"></i>รับบริจาค / เพิ่มสต็อก</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_stock">
                    <input type="hidden" name="shelter_id" value="<?php echo $shelter_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo function_exists('generateCSRFToken') ? generateCSRFToken() : ''; ?>">

                    <div class="mb-3">
                        <label class="form-label">ชื่อสิ่งของ <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" list="item_suggestions" required placeholder="เช่น ข้าวสาร, น้ำดื่ม, ยาพารา...">
                        <datalist id="item_suggestions">
                            <option value="น้ำดื่ม (แพ็ค)">
                            <option value="ข้าวสาร (ถุง 5kg)">
                            <option value="บะหมี่กึ่งสำเร็จรูป (กล่อง)">
                            <option value="ปลากระป๋อง (กระป๋อง)">
                            <option value="ยาสามัญประจำบ้าน (ชุด)">
                            <option value="ผ้าห่ม (ผืน)">
                        </datalist>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">หมวดหมู่</label>
                            <select name="category" class="form-select">
                                <option value="general" selected>ของใช้ทั่วไป</option>
                                <option value="food">อาหาร/เครื่องดื่ม</option>
                                <option value="medicine">ยา/เวชภัณฑ์</option>
                                <option value="clothes">เครื่องนุ่งห่ม</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">หน่วยนับ</label>
                            <input type="text" name="unit" class="form-control" placeholder="เช่น ชิ้น, แพ็ค, ขวด">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">จำนวนที่รับเข้า <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">แหล่งที่มา / ผู้บริจาค</label>
                        <input type="text" name="source" class="form-control" placeholder="เช่น กาชาด, คุณใจดี, งบอบจ.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success fw-bold">บันทึกรับของ</button>
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

        // Initialize Button Logic
        const btnDonation = document.getElementById('btnDonation');
        if(btnDonation) {
            btnDonation.addEventListener('click', function() {
                var myModal = new bootstrap.Modal(document.getElementById('donationModal'));
                myModal.show();
            });
        }
    });
</script>
</body>
</html>