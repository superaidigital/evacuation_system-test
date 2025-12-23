<?php
// user_manager.php
// ระบบจัดการผู้ใช้งาน (Admin Only)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit();
}

// Fetch Users
$sql = "SELECT u.*, s.name as shelter_name 
        FROM users u 
        LEFT JOIN shelters s ON u.shelter_id = s.id 
        ORDER BY u.role, u.username";
$users = $pdo->query($sql)->fetchAll();

// Fetch Shelters for Assignment
$shelters = $pdo->query("SELECT id, name FROM shelters WHERE status != 'closed'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>จัดการผู้ใช้งานระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-users-cog text-primary me-2"></i>จัดการผู้ใช้งานระบบ</h3>
        <button class="btn btn-primary" onclick="openModal('add')">
            <i class="fas fa-user-plus me-2"></i> เพิ่มผู้ใช้งาน
        </button>
    </div>

    <?php if (isset($_SESSION['swal_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['swal_success']; unset($_SESSION['swal_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อผู้ใช้งาน (Username)</th>
                            <th>บทบาท (Role)</th>
                            <th>ศูนย์ที่รับผิดชอบ</th>
                            <th>ใช้งานล่าสุด</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td>
                                <?php if($u['role'] == 'admin'): ?>
                                    <span class="badge bg-danger">ผู้ดูแลระบบ (Admin)</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">เจ้าหน้าที่ (Staff)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $u['shelter_name'] ? htmlspecialchars($u['shelter_name']) : '<span class="text-muted">- ไม่ระบุ -</span>'; ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-'; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-warning me-1" onclick='openModal("edit", <?php echo json_encode($u); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $u['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="user_save.php" method="POST">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalTitle">เพิ่มผู้ใช้งาน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="user_id" id="user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่าน <span id="pass-hint" class="text-muted small">(กำหนดใหม่)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="ระบุหากต้องการเปลี่ยนรหัสผ่าน">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">บทบาท</label>
                        <select name="role" id="role" class="form-select" onchange="toggleShelterSelect()">
                            <option value="staff">เจ้าหน้าที่ (Staff)</option>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="shelter-div">
                        <label class="form-label">ประจำศูนย์พักพิง</label>
                        <select name="shelter_id" id="shelter_id" class="form-select">
                            <option value="">-- ไม่ระบุ / ส่วนกลาง --</option>
                            <?php foreach($shelters as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">สำหรับ Staff ควรระบุศูนย์ที่รับผิดชอบ เพื่อให้ระบบจัดการสิทธิ์ได้ถูกต้อง</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function toggleShelterSelect() {
        var role = document.getElementById('role').value;
        // Admin อาจจะไม่ต้องสังกัดศูนย์ หรือ Staff ต้องสังกัด
        // ในที่นี้ให้เลือกได้ทั้งคู่ แต่เน้น Staff
    }

    function openModal(mode, data = null) {
        var modal = new bootstrap.Modal(document.getElementById('userModal'));
        document.getElementById('action').value = mode;
        
        if (mode == 'add') {
            document.getElementById('modalTitle').innerText = 'เพิ่มผู้ใช้งาน';
            document.getElementById('user_id').value = '';
            document.getElementById('username').value = '';
            document.getElementById('password').required = true;
            document.getElementById('pass-hint').innerText = '(กำหนดใหม่)';
            document.getElementById('role').value = 'staff';
            document.getElementById('shelter_id').value = '';
        } else {
            document.getElementById('modalTitle').innerText = 'แก้ไขผู้ใช้งาน';
            document.getElementById('user_id').value = data.id;
            document.getElementById('username').value = data.username;
            document.getElementById('password').required = false;
            document.getElementById('pass-hint').innerText = '(เว้นว่างหากไม่เปลี่ยน)';
            document.getElementById('role').value = data.role;
            document.getElementById('shelter_id').value = data.shelter_id || '';
        }
        modal.show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "การกระทำนี้ไม่สามารถย้อนกลับได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบผู้ใช้',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'user_save.php?action=delete&id=' + id + '&csrf_token=<?php echo generateCSRFToken(); ?>';
            }
        })
    }
</script>
</body>
</html>