<?php
// distribution_manager.php
// หน้าแจกจ่ายสิ่งของ (ตัดสต็อก) - แก้ไขปัญหาเลือกศูนย์แล้ว Error
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$my_shelter_id = $_SESSION['shelter_id'] ?? 0;
// รับค่า shelter_id จาก GET (URL)
$shelter_id = ($role === 'admin') ? ($_GET['shelter_id'] ?? '') : $my_shelter_id;

// Fetch Shelters (Admin Only)
$shelters = [];
if ($role === 'admin') {
    $shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
}

// Fetch Items available in this shelter
$items = [];
if ($shelter_id || $my_shelter_id) {
    $sid = $shelter_id ? $shelter_id : $my_shelter_id;
    // ดึงเฉพาะสินค้าที่มีของ (quantity > 0)
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE shelter_id = ? AND quantity > 0 ORDER BY item_name");
    $stmt->execute([$sid]);
    $items = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>บันทึกการแจกจ่ายสิ่งของ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-header-dist { background: linear-gradient(135deg, #d32f2f 0%, #ef5350 100%); color: white; }
        .form-section-title { border-left: 5px solid #d32f2f; padding-left: 10px; font-weight: bold; margin-bottom: 15px; color: #444; }
        .stock-badge { font-size: 0.9rem; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="inventory_list.php">คลังสินค้า</a></li>
            <li class="breadcrumb-item active" aria-current="page">แจกจ่าย/ตัดสต็อก</li>
        </ol>
    </nav>

    <!-- Header Area -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold text-dark"><i class="fas fa-hand-holding-heart text-danger me-2"></i>บันทึกการแจกจ่าย</h3>
        <a href="distribution_history.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-history me-1"></i> ดูประวัติการแจก
        </a>
    </div>

    <!-- Alerts -->
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

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header card-header-dist py-3">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>ฟอร์มตัดเบิกสินค้าออกจากคลัง</h5>
                </div>
                <div class="card-body p-4">
                    
                    <form action="distribution_save.php" method="POST" id="distForm">
                        
                        <!-- 1. เลือกศูนย์ (Admin Only) -->
                        <?php if ($role === 'admin'): ?>
                        <div class="mb-4 bg-light p-3 rounded border">
                            <label class="form-label fw-bold">ศูนย์พักพิงต้นทาง <span class="text-danger">*</span></label>
                            <!-- [FIX] เปลี่ยน onchange เป็นการ Redirect หน้าเว็บ แทนการ Submit Form -->
                            <select name="shelter_id" id="shelter_select" class="form-select" onchange="window.location.href='?shelter_id='+this.value">
                                <option value="">-- กรุณาเลือกศูนย์ --</option>
                                <?php foreach ($shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $shelter_id == $s['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if(!$shelter_id): ?>
                                <div class="text-danger mt-2 small"><i class="fas fa-exclamation-triangle"></i> ต้องเลือกศูนย์ก่อนเพื่อโหลดรายการสินค้า</div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="shelter_id" value="<?php echo $my_shelter_id; ?>">
                        <?php endif; ?>

                        <?php if ($shelter_id || $my_shelter_id): ?>
                        
                        <!-- Section 1: ข้อมูลสินค้า -->
                        <div class="mb-5">
                            <div class="form-section-title">1. รายละเอียดสินค้าที่เบิก</div>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold">เลือกสินค้า <span class="text-danger">*</span></label>
                                    <select name="inventory_id" id="inventory_select" class="form-select select2" required>
                                        <option value="">-- ค้นหาชื่อสินค้า --</option>
                                        <?php foreach ($items as $itm): ?>
                                            <option value="<?php echo $itm['id']; ?>" data-unit="<?php echo $itm['unit']; ?>" data-qty="<?php echo $itm['quantity']; ?>">
                                                <?php echo htmlspecialchars($itm['item_name']); ?> (คงเหลือ: <?php echo number_format($itm['quantity']); ?> <?php echo $itm['unit']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">จำนวนที่เบิก <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="quantity" id="dist_qty" class="form-control fw-bold text-primary" min="1" required placeholder="0">
                                        <span class="input-group-text bg-white text-secondary" id="unit_label">หน่วย</span>
                                    </div>
                                    <div class="text-end mt-1">
                                        <small class="text-muted">คงเหลือในคลัง: <span id="current_stock" class="fw-bold text-success">-</span></small>
                                    </div>
                                </div>
                            </div>
                            <!-- Ref No -->
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary small">เลขที่เอกสาร/ใบเบิก (Ref No.)</label>
                                    <input type="text" name="ref_no" class="form-control form-control-sm" placeholder="เช่น DOC-2023/001 (ถ้ามี)">
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: ข้อมูลผู้รับ -->
                        <div class="mb-4">
                            <div class="form-section-title">2. ข้อมูลผู้รับของ/ปลายทาง</div>
                            
                            <!-- Toggle Type -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">ประเภทผู้รับ</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="recipient_type" id="type_evacuee" value="evacuee" checked onchange="toggleRecipient('evacuee')">
                                    <label class="btn btn-outline-danger" for="type_evacuee"><i class="fas fa-user me-1"></i> ผู้ประสบภัย (รายบุคคล)</label>

                                    <input type="radio" class="btn-check" name="recipient_type" id="type_general" value="general" onchange="toggleRecipient('general')">
                                    <label class="btn btn-outline-secondary" for="type_general"><i class="fas fa-users me-1"></i> หน่วยงาน / ส่วนกลาง</label>
                                </div>
                            </div>

                            <!-- Case A: Evacuee -->
                            <div id="evacuee_section" class="card bg-light border-0 p-3">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">ค้นหาชื่อผู้ประสบภัย <span class="text-danger">*</span></label>
                                        <select name="evacuee_id" id="evacuee_id_select" class="form-select select2-ajax" style="width: 100%;">
                                            <option value="">-- พิมพ์ชื่อเพื่อค้นหา --</option>
                                        </select>
                                        <div class="form-text small">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหาในฐานข้อมูลทะเบียน</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label text-secondary small">ชื่อผู้มารับแทน (กรณีเจ้าตัวไม่มารับเอง)</label>
                                        <input type="text" name="receiver_name_ev" class="form-control" placeholder="ระบุชื่อ-สกุล ผู้รับแทน">
                                    </div>
                                </div>
                            </div>

                            <!-- Case B: General/Group -->
                            <div id="general_section" class="card bg-light border-0 p-3 d-none">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">ชื่อหน่วยงาน / กลุ่มเป้าหมาย <span class="text-danger">*</span></label>
                                        <input type="text" name="recipient_group" id="recipient_group_input" class="form-control" placeholder="เช่น ครัวกลาง, ทีมแพทย์อาสา, จุดสกัดที่ 1">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label text-secondary small">ชื่อผู้เบิก/เจ้าหน้าที่รับผิดชอบ</label>
                                        <input type="text" name="receiver_name_gen" class="form-control" placeholder="ระบุชื่อเจ้าหน้าที่ผู้ดำเนินการเบิก">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Note -->
                        <div class="mb-4">
                            <label class="form-label">หมายเหตุเพิ่มเติม</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="รายละเอียดอื่นๆ (ถ้ามี)..."></textarea>
                        </div>

                        <hr>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="inventory_list.php" class="btn btn-light border me-md-2">ยกเลิก</a>
                            <button type="submit" class="btn btn-danger btn-lg px-5 shadow-sm" onclick="return confirm('ยืนยันการตัดสต็อก?');">
                                <i class="fas fa-save me-2"></i> บันทึกการแจกจ่าย
                            </button>
                        </div>

                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Init Select2 for Items
        $('#inventory_select').select2({
            theme: 'bootstrap-5',
            placeholder: '-- ค้นหา/เลือกสินค้า --',
            allowClear: true
        });

        // Stock Update Logic
        $('#inventory_select').on('change', function() {
            var selected = $(this).find(':selected');
            var qty = selected.data('qty');
            var unit = selected.data('unit');
            
            if (qty !== undefined) {
                $('#current_stock').text(new Intl.NumberFormat().format(qty) + ' ' + unit);
                $('#unit_label').text(unit);
                $('#dist_qty').attr('max', qty);
                $('#dist_qty').prop('disabled', false);
            } else {
                $('#current_stock').text('-');
                $('#unit_label').text('หน่วย');
                $('#dist_qty').prop('disabled', true);
            }
        });

        // Quantity Validation
        $('#dist_qty').on('input', function() {
            var max = parseInt($(this).attr('max')) || 0;
            var val = parseInt($(this).val()) || 0;
            
            if(val > max) {
                $(this).addClass('is-invalid');
                // Optional: Auto cap or just warn
                // $(this).val(max); 
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        // Init Select2 for Evacuee Search (AJAX)
        $('.select2-ajax').select2({
            theme: 'bootstrap-5',
            ajax: {
                url: 'search_evacuee.php', // ต้องมีไฟล์นี้สำหรับค้นหา JSON
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function(item) {
                            return {
                                id: item.id,
                                text: item.full_name + ' (ID: ' + item.id_card.substr(-4) + ')'
                            }
                        })
                    };
                },
                cache: true
            },
            placeholder: 'พิมพ์ชื่อเพื่อค้นหา...',
            minimumInputLength: 2,
            language: {
                inputTooShort: function() { return "โปรดพิมพ์อย่างน้อย 2 ตัวอักษร"; },
                noResults: function() { return "ไม่พบข้อมูล"; },
                searching: function() { return "กำลังค้นหา..."; }
            }
        });

        // Initialize Recipient Toggle
        toggleRecipient('evacuee');
    });

    function toggleRecipient(type) {
        const evacueeSelect = $('#evacuee_id_select');
        const generalInput = $('#recipient_group_input');

        if(type === 'evacuee') {
            $('#evacuee_section').removeClass('d-none');
            $('#general_section').addClass('d-none');
            
            // Set Required
            evacueeSelect.prop('required', true);
            generalInput.prop('required', false);
        } else {
            $('#evacuee_section').addClass('d-none');
            $('#general_section').removeClass('d-none');

            // Set Required
            evacueeSelect.prop('required', false);
            generalInput.prop('required', true);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>