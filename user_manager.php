<?php
// user_manager.php
// หน้าจัดการผู้ใช้งาน (เพิ่ม Role: Donation Management Officer)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// ตรวจสอบสิทธิ์ (เฉพาะ Admin)
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// --- Query Users ---
$search = $_GET['search'] ?? '';
$params = [];
$sql = "SELECT u.*, s.name as shelter_name 
        FROM users u 
        LEFT JOIN shelters s ON u.shelter_id = s.id 
        WHERE 1=1";

if ($search) {
    $sql .= " AND (u.username LIKE ? ";
    $params[] = "%$search%";
    $sql .= ")";
}

$sql .= " ORDER BY u.role, u.username";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch Shelters for Dropdown
$shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-header-users { background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: white; }
        
        /* FIX: Z-Index Problem */
        .modal-backdrop { z-index: 2000 !important; }
        .modal { z-index: 2050 !important; }
        body.modal-open { overflow: hidden !important; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">

    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-users-cog text-primary me-2"></i>จัดการผู้ใช้งาน</h3>
            <p class="text-muted small mb-0">เพิ่ม ลบ แก้ไขสิทธิ์การเข้าใช้งานระบบ</p>
        </div>
        <button class="btn btn-success shadow-sm" onclick="openAddModal()">
            <i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้งาน
        </button>
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

    <!-- Search & Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="ค้นหา Username..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Username</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ระดับสิทธิ์ (Role)</th>
                            <th>ศูนย์ที่ดูแล</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">ไม่พบข้อมูลผู้ใช้งาน</td></tr>
                        <?php else: ?>
                            <?php foreach($users as $u): 
                                // กำหนดสีและชื่อแสดงผลตาม Role
                                $roleBadge = 'bg-secondary';
                                $roleText = $u['role'];

                                if ($u['role'] === 'admin') {
                                    $roleBadge = 'bg-danger';
                                    $roleText = 'ผู้ดูแลระบบ (Admin)';
                                } elseif ($u['role'] === 'staff') {
                                    $roleBadge = 'bg-primary';
                                    $roleText = 'เจ้าหน้าที่ (Staff)';
                                } elseif ($u['role'] === 'donation_officer') {
                                    $roleBadge = 'bg-success';
                                    $roleText = 'จนท.ทรัพยากร (Donation Officer)';
                                }
                                
                                // ป้องกัน Error หากไม่มีคอลัมน์ชื่อสกุล
                                $firstName = $u['first_name'] ?? '';
                                $lastName = $u['last_name'] ?? '';
                                $fullName = trim($firstName . ' ' . $lastName);
                                if (empty($fullName)) $fullName = '-';

                                // Prepare JSON for JS
                                $userJson = htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($fullName); ?></td>
                                <td><span class="badge <?php echo $roleBadge; ?>"><?php echo $roleText; ?></span></td>
                                <td>
                                    <?php if($u['shelter_id']): ?>
                                        <span class="text-muted"><i class="fas fa-campground me-1"></i> <?php echo htmlspecialchars($u['shelter_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning me-1" onclick='openEditModal(<?php echo $userJson; ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
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

<!-- ================= MODALS ================= -->

<div id="modals-container">
    <!-- Modal: Add/Edit User -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="user_save.php" method="POST">
                    <input type="hidden" name="action" id="form_action" value="add">
                    <input type="hidden" name="id" id="user_id" value="">
                    
                    <div class="modal-header bg-primary text-white" id="modal_header">
                        <h5 class="modal-title" id="modal_title"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="ระบุรหัสผ่าน">
                            <small class="text-muted" id="password_hint">กรณีเพิ่มใหม่: จำเป็นต้องกรอก</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ชื่อจริง</label>
                                <input type="text" name="first_name" id="first_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">นามสกุล</label>
                                <input type="text" name="last_name" id="last_name" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Role Selection Updated -->
                        <div class="mb-3">
                            <label class="form-label">สิทธิ์การใช้งาน (Role) <span class="text-danger">*</span></label>
                            <select name="role" id="role" class="form-select" onchange="toggleShelter()" required>
                                <option value="staff">เจ้าหน้าที่ (Staff)</option>
                                <option value="donation_officer">เจ้าหน้าที่บริหารจัดการทรัพยากร (Donation Officer)</option>
                                <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="shelter_div">
                            <label class="form-label">ประจำศูนย์พักพิง</label>
                            <select name="shelter_id" id="shelter_id" class="form-select">
                                <option value="">-- ไม่ระบุ / ดูแลทุกศูนย์ --</option>
                                <?php foreach($shelters as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">สำหรับ Staff ควรระบุศูนย์ที่ดูแล</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" id="save_btn">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="user_save.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">ยืนยันการลบ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="mb-0">คุณต้องการลบผู้ใช้ <strong id="delete_name"></strong> ใช่หรือไม่?</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger">ลบผู้ใช้</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Script Logic -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const modalsContainer = document.getElementById('modals-container');
        if (modalsContainer) document.body.appendChild(modalsContainer);
        
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
    });

    function openModalSafe(modalId) {
        var el = document.getElementById(modalId);
        var myModal = bootstrap.Modal.getOrCreateInstance(el);
        myModal.show();
    }

    function openAddModal() {
        // Reset Form
        document.getElementById('form_action').value = 'add';
        document.getElementById('user_id').value = '';
        document.getElementById('username').value = '';
        document.getElementById('username').readOnly = false;
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('password_hint').innerText = "กรณีเพิ่มใหม่: จำเป็นต้องกรอก";
        document.getElementById('first_name').value = '';
        document.getElementById('last_name').value = '';
        document.getElementById('role').value = 'staff'; // Default
        document.getElementById('shelter_id').value = '';
        
        // Update UI
        document.getElementById('modal_title').innerHTML = '<i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่';
        document.getElementById('modal_header').className = 'modal-header bg-success text-white';
        document.getElementById('save_btn').className = 'btn btn-success';
        document.getElementById('save_btn').innerText = 'เพิ่มผู้ใช้งาน';
        
        toggleShelter();
        openModalSafe('userModal');
    }

    function openEditModal(user) {
        // Populate Form
        document.getElementById('form_action').value = 'edit';
        document.getElementById('user_id').value = user.id;
        document.getElementById('username').value = user.username;
        document.getElementById('username').readOnly = true;
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        document.getElementById('password_hint').innerText = "เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน";
        
        document.getElementById('first_name').value = user.first_name || '';
        document.getElementById('last_name').value = user.last_name || '';
        
        // Set Role
        const roleSelect = document.getElementById('role');
        // Check if role exists in options, if not default to staff (or handle as needed)
        let roleExists = false;
        for (let i = 0; i < roleSelect.options.length; i++) {
            if (roleSelect.options[i].value === user.role) {
                roleExists = true;
                break;
            }
        }
        if (roleExists) {
            roleSelect.value = user.role;
        } else {
            // Fallback for custom roles or old data
            roleSelect.value = 'staff'; 
        }

        document.getElementById('shelter_id').value = user.shelter_id || '';

        // Update UI
        document.getElementById('modal_title').innerHTML = '<i class="fas fa-edit me-2"></i>แก้ไขผู้ใช้งาน';
        document.getElementById('modal_header').className = 'modal-header bg-warning text-dark';
        document.getElementById('save_btn').className = 'btn btn-warning';
        document.getElementById('save_btn').innerText = 'บันทึกการแก้ไข';

        toggleShelter();
        openModalSafe('userModal');
    }

    function confirmDelete(id, name) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').innerText = name;
        openModalSafe('deleteModal');
    }

    function toggleShelter() {
        // Logic เพิ่มเติมถ้าต้องการให้บาง Role ซ่อนช่องเลือกศูนย์
    }
</script>

</body>
</html>