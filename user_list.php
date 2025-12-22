<?php
// user_list.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // renderPagination
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 1. Security Check: เฉพาะ Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ------------------------------------------------------------------
// 2. Logic: Handle Actions (Add / Edit / Delete)
// ------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($id == $_SESSION['user_id']) {
        $_SESSION['swal_error'] = "ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($pdo, $_SESSION['user_id'], 'Delete User', "ลบผู้ใช้งาน ID: $id");
            $_SESSION['swal_success'] = "ลบผู้ใช้งานเรียบร้อยแล้ว";
        } catch (PDOException $e) {
            $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
    header("Location: user_list.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    $mode = $_POST['action_type'];
    $id = $_POST['user_id'] ?? '';
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    
    // รับค่า shelter_id (ถ้าไม่ใช่ staff/volunteer จะเป็น null หรือค่าว่าง)
    $shelter_id = !empty($_POST['shelter_id']) ? $_POST['shelter_id'] : null;

    // ถ้า Role เป็น admin ให้เคลียร์ shelter_id เป็น NULL เสมอ
    if ($role === 'admin') {
        $shelter_id = null;
    }

    try {
        if ($mode == 'add') {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->rowCount() > 0) {
                $_SESSION['swal_error'] = "ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                // เพิ่ม shelter_id ลงใน INSERT
                $sql = "INSERT INTO users (username, password, full_name, role, shelter_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $password_hash, $full_name, $role, $shelter_id]);
                
                logActivity($pdo, $_SESSION['user_id'], 'Add User', "เพิ่มผู้ใช้งาน: $username ($role)");
                $_SESSION['swal_success'] = "เพิ่มผู้ใช้งานเรียบร้อยแล้ว";
            }
        } else if ($mode == 'edit') {
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                // เพิ่ม shelter_id ลงใน UPDATE
                $sql = "UPDATE users SET full_name = ?, role = ?, shelter_id = ?, password = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $role, $shelter_id, $password_hash, $id]);
            } else {
                // เพิ่ม shelter_id ลงใน UPDATE (กรณีไม่เปลี่ยนรหัส)
                $sql = "UPDATE users SET full_name = ?, role = ?, shelter_id = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $role, $shelter_id, $id]);
            }
            logActivity($pdo, $_SESSION['user_id'], 'Edit User', "แก้ไขผู้ใช้งาน: $username");
            $_SESSION['swal_success'] = "แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว";
        }
    } catch (PDOException $e) {
        $_SESSION['swal_error'] = "Error: " . $e->getMessage();
    }
    header("Location: user_list.php");
    exit();
}

// ------------------------------------------------------------------
// 3. Data Fetching & Pagination
// ------------------------------------------------------------------

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch Shelters for Dropdown
try {
    $shelters = $pdo->query("SELECT id, name FROM shelters ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $shelters = []; // กรณี table shelters มีปัญหา หรือยังไม่มีข้อมูล
}

// Count Users
$total_records = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Users with Shelter Name
try {
    // ใช้ LEFT JOIN เพื่อดึงชื่อศูนย์พักพิง ถ้า shelter_id ตรงกัน
    $sql = "SELECT u.*, s.name as shelter_name 
            FROM users u
            LEFT JOIN shelters s ON u.shelter_id = s.id
            ORDER BY u.role ASC, u.username ASC 
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback กรณีไม่มีคอลัมน์ shelter_id ใน users table
    $sql = "SELECT *, NULL as shelter_name FROM users ORDER BY role ASC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .role-badge {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-admin { background-color: #fee2e2; color: #b91c1c; }
        .role-staff { background-color: #dbeafe; color: #1e40af; }
        .role-volunteer { background-color: #d1fae5; color: #047857; }
        
        .avatar-circle {
            width: 38px;
            height: 38px;
            background-color: #e2e8f0;
            color: #475569;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* Modal Styles */
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
                <i class="fas fa-users-cog text-primary me-2"></i>จัดการผู้ใช้งานระบบ
            </h4>
            <span class="text-muted small">System User Management</span>
        </div>
        <!-- ปุ่มเปิด Modal -->
        <button class="btn btn-primary shadow-sm" onclick="openModal('add')">
            <i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่
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

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-official">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">#</th>
                            <th>ผู้ใช้งาน (User)</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>สิทธิ์ (Role)</th>
                            <th>สังกัด (Shelter)</th>
                            <th>เข้าใช้งานล่าสุด</th>
                            <th class="text-end pe-4">เครื่องมือ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $row): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="avatar-circle">
                                    <?php echo strtoupper(substr($row['username'], 0, 1)); ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['username']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td>
                                <?php 
                                    $role_class = 'role-staff';
                                    if($row['role'] == 'admin') $role_class = 'role-admin';
                                    if($row['role'] == 'volunteer') $role_class = 'role-volunteer';
                                ?>
                                <span class="role-badge <?php echo $role_class; ?>">
                                    <?php echo ucfirst($row['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['shelter_name'])): ?>
                                    <span class="text-primary fw-bold small"><i class="fas fa-home me-1"></i> <?php echo htmlspecialchars($row['shelter_name']); ?></span>
                                <?php elseif ($row['role'] == 'admin'): ?>
                                    <span class="text-muted small">- ส่วนกลาง -</span>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic text-danger">ยังไม่ระบุ</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?php echo $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : '-'; ?>
                            </td>
                            <td class="text-end pe-4">
                                <!-- ปุ่มแก้ไข: ส่ง JSON ข้อมูล -->
                                <button class="btn btn-sm btn-outline-secondary me-1" 
                                        onclick='openModal("edit", <?php echo json_encode($row); ?>)' 
                                        title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if($row['id'] !== $_SESSION['user_id']): ?>
                                    <button onclick="confirmDeleteUser(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['username']); ?>')" class="btn btn-sm btn-outline-danger" title="ลบ">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light text-muted border" disabled><i class="fas fa-user-check"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="p-3 bg-light border-top">
                <?php echo renderPagination($page, $total_pages); ?>
            </div>
            
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- ========================================== -->
<!-- MODAL POPUP: เพิ่ม/แก้ไข ผู้ใช้งาน -->
<!-- ========================================== -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form action="user_list.php" method="POST" class="w-100" id="userForm">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Hidden Fields -->
                    <input type="hidden" name="action_type" id="action_type" value="add">
                    <input type="hidden" name="user_id" id="user_id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="username" class="form-control" required placeholder="ภาษาอังกฤษตัวเล็ก">
                        <div class="form-text text-muted" id="usernameHint">ใช้สำหรับเข้าสู่ระบบ (แก้ไขไม่ได้)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">สิทธิ์การใช้งาน (Role) <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-select" required onchange="toggleShelterSelect()">
                            <option value="staff">เจ้าหน้าที่ (Staff)</option>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                            <option value="volunteer">อาสาสมัคร (Volunteer)</option>
                        </select>
                    </div>

                    <!-- Shelter Selection (Hidden for Admin) -->
                    <div class="mb-3" id="shelterSelectGroup">
                        <label class="form-label fw-bold">ประจำศูนย์พักพิง <span class="text-danger" id="shelterReq">*</span></label>
                        <select name="shelter_id" id="shelter_id" class="form-select">
                            <option value="">-- เลือกศูนย์พักพิง --</option>
                            <?php foreach ($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">จำเป็นสำหรับ Staff และ Volunteer เพื่อระบุสถานที่ปฏิบัติงาน</div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <label class="form-label fw-bold" id="passwordLabel">รหัสผ่าน <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="กำหนดรหัสผ่าน">
                        <div class="form-text text-muted" id="passwordHint"></div>
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
    // Toggle Shelter Dropdown Visibility
    function toggleShelterSelect() {
        const role = document.getElementById('role').value;
        const shelterGroup = document.getElementById('shelterSelectGroup');
        const shelterSelect = document.getElementById('shelter_id');
        
        if (role === 'admin') {
            shelterGroup.classList.add('d-none'); // ซ่อนถ้าเป็น Admin
            shelterSelect.value = ""; // เคลียร์ค่า
            shelterSelect.required = false;
        } else {
            shelterGroup.classList.remove('d-none'); // แสดงถ้าเป็น Staff/Volunteer
            // shelterSelect.required = true; // บังคับเลือก (Optional: ถ้าต้องการบังคับให้ uncomment)
        }
    }

    // เปิด Modal (Add/Edit)
    function openModal(mode, data = null) {
        setTimeout(() => {
            const modalEl = document.getElementById('userModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                
                // Reset ค่า Default
                document.getElementById('userForm').reset();
                document.getElementById('action_type').value = mode;
                document.getElementById('user_id').value = '';
                
                const usernameInput = document.getElementById('username');
                const passwordInput = document.getElementById('password');
                const roleSelect = document.getElementById('role');
                const shelterSelect = document.getElementById('shelter_id');
                
                if (mode === 'add') {
                    // โหมดเพิ่ม
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่';
                    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกข้อมูล';
                    
                    usernameInput.readOnly = false;
                    usernameInput.style.backgroundColor = '';
                    document.getElementById('usernameHint').style.display = 'none';
                    
                    document.getElementById('passwordLabel').innerHTML = 'รหัสผ่าน <span class="text-danger">*</span>';
                    passwordInput.required = true;
                    passwordInput.placeholder = 'กำหนดรหัสผ่าน';
                    document.getElementById('passwordHint').innerText = '';
                    
                    // Default Role & Shelter
                    roleSelect.value = 'staff';
                    toggleShelterSelect(); // เรียกเพื่อแสดง Dropdown
                    
                } else if (mode === 'edit' && data) {
                    // โหมดแก้ไข
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i> แก้ไขข้อมูลผู้ใช้งาน';
                    document.getElementById('btnSave').innerHTML = '<i class="fas fa-save me-1"></i> บันทึกการแก้ไข';
                    
                    // Fill Data
                    document.getElementById('user_id').value = data.id;
                    usernameInput.value = data.username;
                    document.getElementById('full_name').value = data.full_name;
                    roleSelect.value = data.role;
                    
                    // Fill Shelter Data (ถ้ามี)
                    if (data.shelter_id) {
                        shelterSelect.value = data.shelter_id;
                    } else {
                        shelterSelect.value = "";
                    }
                    
                    // Lock Username
                    usernameInput.readOnly = true;
                    usernameInput.style.backgroundColor = '#f1f5f9';
                    document.getElementById('usernameHint').style.display = 'block';
                    
                    // Password Optional
                    document.getElementById('passwordLabel').innerHTML = 'เปลี่ยนรหัสผ่านใหม่ (ถ้าต้องการ)';
                    passwordInput.required = false;
                    passwordInput.placeholder = 'เว้นว่างไว้หากไม่ต้องการเปลี่ยน';
                    document.getElementById('passwordHint').innerText = 'หากกรอกช่องนี้ รหัสผ่านเดิมจะถูกเปลี่ยนทันที';

                    toggleShelterSelect(); // อัปเดตการแสดงผล Dropdown ตาม Role
                }
                
                modal.show();
            }
        }, 100);
    }

    // ฟังก์ชันลบ
    function confirmDeleteUser(id, username) {
        Swal.fire({
            title: 'ยืนยันการลบผู้ใช้งาน?',
            text: `คุณต้องการลบบัญชี "${username}" ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `user_list.php?action=delete&id=${id}`;
            }
        });
    }
</script>

</body>
</html>