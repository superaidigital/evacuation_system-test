<?php
// inventory_list.php
// หน้าจัดการบัญชีคลังสินค้า (Inventory) - แก้ไขปัญหา Modal จอทึบ
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;
$shelter_id = ($role === 'admin') ? ($_GET['shelter_id'] ?? '') : $my_shelter_id;
$search = $_GET['search'] ?? '';

// --- Queries ---
$params = [];
$where = " WHERE 1=1 ";

if ($shelter_id) {
    $where .= " AND i.shelter_id = ? ";
    $params[] = $shelter_id;
}

if ($search) {
    $where .= " AND (i.item_name LIKE ? OR i.category LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Fetch Inventory
$sql = "SELECT i.*, s.name as shelter_name 
        FROM inventory i
        JOIN shelters s ON i.shelter_id = s.id
        $where
        ORDER BY i.shelter_id, i.category, i.item_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Fetch Shelters (for Admin & Modal)
$shelters = $pdo->query("SELECT * FROM shelters WHERE status != 'closed'")->fetchAll();

// Get Categories for Dropdown
$categories = ['อาหาร', 'น้ำดื่ม', 'ยา/เวชภัณฑ์', 'เครื่องนุ่มห่ม', 'ของใช้ทั่วไป', 'อุปกรณ์นอน', 'อื่นๆ'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>จัดการคลังสินค้า</title>
    <!-- CSS Link -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-header-custom { background: linear-gradient(to right, #2c3e50, #4ca1af); color: white; }
        .bg-low-stock { background-color: #fff3cd; }

        /* --- HARD FIX: Z-Index Problem --- */
        /* บังคับให้ Backdrop และ Modal อยู่ชั้นบนสุดเสมอ */
        .modal-backdrop {
            z-index: 2000 !important;
        }
        .modal {
            z-index: 2050 !important;
        }
        /* ป้องกัน Body ขยับ */
        body.modal-open {
            overflow: hidden !important;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">

    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-boxes text-primary me-2"></i>จัดการคลังสินค้า</h3>
            <p class="text-muted small mb-0">บริหารจัดการสิ่งของบริจาคและทรัพยากรคงคลัง</p>
        </div>
        <div class="d-flex gap-2">
            <a href="distribution_manager.php" class="btn btn-outline-primary">
                <i class="fas fa-dolly me-1"></i> ตัดจ่าย/แจกของ
            </a>
            <!-- เรียกใช้ JS Function แทน data-bs-toggle เพื่อควบคุมการเปิด -->
            <button class="btn btn-success shadow-sm" onclick="openAddModal()">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มสินค้าใหม่
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-white rounded">
            <form method="GET" class="row g-3">
                <?php if ($role === 'admin'): ?>
                <div class="col-md-4">
                    <select name="shelter_id" class="form-select">
                        <option value="">-- ทุกศูนย์พักพิง --</option>
                        <?php foreach ($shelters as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อสินค้า หรือ หมวดหมู่..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fas fa-search"></i> ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header card-header-custom">
            <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>รายการสินค้าคงคลัง</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อสินค้า</th>
                            <th>หมวดหมู่</th>
                            <?php if(!$shelter_id): ?><th>ศูนย์พักพิง</th><?php endif; ?>
                            <th class="text-end">คงเหลือ</th>
                            <th>หน่วย</th>
                            <th>อัปเดตล่าสุด</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">ไม่พบข้อมูลสินค้า</td></tr>
                        <?php else: ?>
                            <?php foreach ($inventory as $item): 
                                $isLowStock = $item['quantity'] < 10;
                                $rowClass = $isLowStock ? 'bg-low-stock' : '';
                                // Encode JSON for JS safely
                                $itemJson = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td class="ps-4 fw-bold text-primary">
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                    <?php if($isLowStock): ?><span class="badge bg-warning text-dark ms-2" style="font-size:0.6rem;">ใกล้หมด</span><?php endif; ?>
                                </td>
                                <td><span class="badge bg-info text-dark bg-opacity-25 border border-info"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <?php if(!$shelter_id): ?>
                                    <td><small class="text-secondary"><?php echo htmlspecialchars($item['shelter_name']); ?></small></td>
                                <?php endif; ?>
                                <td class="text-end fs-5 fw-bold text-dark"><?php echo number_format($item['quantity']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($item['last_updated'])); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-success me-1" 
                                            onclick='openRestockModal(<?php echo $itemJson; ?>)' 
                                            title="รับเพิ่ม">
                                        <i class="fas fa-plus"></i> รับเพิ่ม
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            onclick='openEditModal(<?php echo $itemJson; ?>)' 
                                            title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
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

<!-- ================= MODALS (ซ่อนไว้ก่อน แล้ว JS จะย้ายไปที่ Body) ================= -->

<div id="modals-container">
    <!-- Modal 1: Add New Item -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="inventory_save.php" method="POST">
                    <input type="hidden" name="action" value="add_item">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>เพิ่มสินค้าใหม่</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($role === 'admin' && !$my_shelter_id): ?>
                            <div class="mb-3">
                                <label class="form-label">เลือกศูนย์พักพิง <span class="text-danger">*</span></label>
                                <select name="shelter_id" class="form-select" required>
                                    <option value="">-- กรุณาเลือก --</option>
                                    <?php foreach ($shelters as $s): ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="shelter_id" value="<?php echo $my_shelter_id; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" class="form-control" required placeholder="เช่น บะหมี่กึ่งสำเร็จรูป, น้ำดื่ม">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">หมวดหมู่</label>
                                <select name="category" class="form-select">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">หน่วยนับ <span class="text-danger">*</span></label>
                                <input type="text" name="unit" class="form-control" required placeholder="เช่น แพ็ค, ขวด, ชุด">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">จำนวนตั้งต้น</label>
                            <input type="number" name="quantity" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">หมายเหตุ/แหล่งที่มา</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="เช่น รับบริจาคจาก อบต."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 2: Restock (Add Quantity) -->
    <div class="modal fade" id="restockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="inventory_save.php" method="POST">
                    <input type="hidden" name="action" value="restock">
                    <input type="hidden" name="id" id="restock_id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-box-open me-2"></i>รับของเข้าเพิ่ม (Restock)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border">
                            <strong id="restock_item_name" class="text-primary fs-5"></strong><br>
                            <small class="text-muted">คงเหลือปัจจุบัน: <span id="restock_current_qty"></span> <span id="restock_unit"></span></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">จำนวนที่รับเพิ่ม <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control form-control-lg fw-bold text-success" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">แหล่งที่มา/ผู้บริจาค</label>
                            <input type="text" name="source" class="form-control" placeholder="ระบุชื่อผู้บริจาค หรือหน่วยงาน (ถ้ามี)">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">ยืนยันรับของ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 3: Edit Item -->
    <div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="inventory_save.php" method="POST">
                    <input type="hidden" name="action" value="edit_item">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลสินค้า</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">หมวดหมู่</label>
                                <select name="category" id="edit_category" class="form-select">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">หน่วยนับ <span class="text-danger">*</span></label>
                                <input type="text" name="unit" id="edit_unit" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">แก้ไขจำนวนคงเหลือ (กรณีข้อมูลผิดพลาด)</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control bg-light" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Script อยู่หลัง Footer -->
<script>
    // --- ULTIMATE FIX: ย้าย Modal ทั้งหมดไปที่ Body ทันทีที่โหลดหน้า ---
    document.addEventListener("DOMContentLoaded", function() {
        // 1. ตรวจสอบ Bootstrap
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap JS not loaded!');
            return;
        }

        // 2. ย้าย Modals ไปที่ Body เพื่อหลุดจาก Container ที่ซ้อนทับ
        const modalsContainer = document.getElementById('modals-container');
        if (modalsContainer) {
            document.body.appendChild(modalsContainer);
        }

        // 3. ล้าง Backdrop ที่อาจค้างอยู่ (Stuck Backdrop Cleanup)
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });

    // --- Helper Function: Open Modal Safely ---
    function openModalSafe(modalId) {
        // ล้าง Backdrop ก่อนเปิดเสมอ เผื่อมีอะไรค้าง
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());

        var el = document.getElementById(modalId);
        // ใช้ getOrCreateInstance เพื่อป้องกันการสร้าง Instance ซ้ำ
        var myModal = bootstrap.Modal.getOrCreateInstance(el);
        myModal.show();
    }

    // --- Action Functions ---

    function openAddModal() {
        openModalSafe('addItemModal');
    }

    function openRestockModal(item) {
        // Set Values
        document.getElementById('restock_id').value = item.id;
        document.getElementById('restock_item_name').innerText = item.item_name;
        document.getElementById('restock_current_qty').innerText = new Intl.NumberFormat().format(item.quantity);
        document.getElementById('restock_unit').innerText = item.unit;
        
        openModalSafe('restockModal');
    }

    function openEditModal(item) {
        // Set Values
        document.getElementById('edit_id').value = item.id;
        document.getElementById('edit_item_name').value = item.item_name;
        document.getElementById('edit_category').value = item.category;
        document.getElementById('edit_unit').value = item.unit;
        document.getElementById('edit_quantity').value = item.quantity;
        
        openModalSafe('editItemModal');
    }
</script>

</body>
</html>