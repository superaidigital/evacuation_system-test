<?php
// announcement_manager.php
// หน้าจัดการข่าวสารและประกาศ (Admin/Staff)

session_start();
require_once 'config/db.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน (ต้องล็อกอินก่อน)
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

// --- ส่วนจัดการการบันทึกข้อมูล (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. บันทึกหรือแก้ไขประกาศ
    if (isset($_POST['save_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $type = $_POST['type'];     // General, Urgent, Alert
        $status = $_POST['status']; // Active, Inactive
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($title) || empty($content)) {
            $_SESSION['error'] = "กรุณากรอกหัวข้อและรายละเอียดให้ครบถ้วน";
        } else {
            if ($id > 0) {
                // กรณีแก้ไข (Update)
                $sql = "UPDATE announcements SET title=?, content=?, type=?, status=?, updated_at=NOW() WHERE id=?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ssssi", $title, $content, $type, $status, $id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "แก้ไขประกาศเรียบร้อยแล้ว";
                    } else {
                        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                // กรณีเพิ่มใหม่ (Insert)
                $user_id = $_SESSION['user_id'];
                $sql = "INSERT INTO announcements (title, content, type, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ssssi", $title, $content, $type, $status, $user_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "เพิ่มประกาศใหม่เรียบร้อยแล้ว";
                    } else {
                        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
        // Redirect เพื่อล้างค่า POST
        header("Location: announcement_manager.php");
        exit();
    }
    
    // 2. ลบประกาศ
    if (isset($_POST['delete_id'])) {
        $del_id = intval($_POST['delete_id']);
        $sql = "DELETE FROM announcements WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $del_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "ลบประกาศเรียบร้อยแล้ว";
            } else {
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ: " . $stmt->error;
            }
            $stmt->close();
        }
        header("Location: announcement_manager.php");
        exit();
    }
}

// --- ส่วนดึงข้อมูล (GET) ---

// 1. ดึงข้อมูลประกาศทั้งหมด
$announcements = [];
// ตรวจสอบก่อนว่าตารางมีอยู่จริงหรือไม่ (กัน Error กรณีเพิ่งสร้างระบบ)
$check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
if ($check_table && $check_table->num_rows > 0) {
    $sql = "SELECT * FROM announcements ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        $announcements = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// 2. ดึงข้อมูลสำหรับแก้ไข (ถ้ามีพารามิเตอร์ ?edit=ID)
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_data = $res->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข่าวสาร/ประกาศ</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f6f9; }
        
        /* ปรับ Layout ให้เข้ากับ Sidebar */
        .content-wrapper { 
            /* ค่า margin-left จะถูกจัดการโดย CSS ใน header.php หรือ main layout */
            /* แต่ถ้าระบบใช้ structure แบบ AdminLTE หรือ Custom Sidebar ให้ใช้ class container */
        }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .table th { font-weight: 500; color: #555; }
        
        .badge-urgent { background-color: #ffc107; color: #000; }
        .badge-alert { background-color: #dc3545; color: #fff; }
        .badge-general { background-color: #0dcaf0; color: #000; }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <div class="container-fluid p-4" style="margin-top: 20px;">
        <div class="row justify-content-center">
            
            <!-- ส่วนหัวข้อหน้า -->
            <div class="col-12 mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold text-dark m-0"><i class="fas fa-bullhorn text-primary me-2"></i>จัดการข่าวสาร/ประกาศ</h3>
                    <p class="text-muted small m-0">สร้างและจัดการประกาศแจ้งเตือนสถานการณ์</p>
                </div>
                <?php if($edit_data): ?>
                    <a href="announcement_manager.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-plus me-1"></i> เพิ่มประกาศใหม่
                    </a>
                <?php endif; ?>
            </div>

            <!-- แจ้งเตือน Alert -->
            <div class="col-12">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ส่วนฟอร์ม (Form) -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas <?php echo $edit_data ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                            <?php echo $edit_data ? 'แก้ไขประกาศ' : 'เพิ่มประกาศใหม่'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="announcement_manager.php">
                            <?php if ($edit_data): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label fw-bold">หัวข้อประกาศ <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required placeholder="เช่น แจ้งเตือนระดับน้ำ..." value="<?php echo htmlspecialchars($edit_data['title'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">รายละเอียด <span class="text-danger">*</span></label>
                                <textarea name="content" class="form-control" rows="5" required placeholder="ใส่รายละเอียดข่าวสาร..."><?php echo htmlspecialchars($edit_data['content'] ?? ''); ?></textarea>
                            </div>

                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <label class="form-label fw-bold">ประเภท</label>
                                    <select name="type" class="form-select">
                                        <option value="General" <?php echo ($edit_data['type'] ?? '') == 'General' ? 'selected' : ''; ?>>ทั่วไป (General)</option>
                                        <option value="Urgent" <?php echo ($edit_data['type'] ?? '') == 'Urgent' ? 'selected' : ''; ?>>เร่งด่วน (Urgent)</option>
                                        <option value="Alert" <?php echo ($edit_data['type'] ?? '') == 'Alert' ? 'selected' : ''; ?>>แจ้งเตือนภัย (Alert)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">สถานะ</label>
                                    <select name="status" class="form-select">
                                        <option value="Active" <?php echo ($edit_data['status'] ?? '') == 'Active' ? 'selected' : ''; ?>>🟢 ใช้งาน</option>
                                        <option value="Inactive" <?php echo ($edit_data['status'] ?? '') == 'Inactive' ? 'selected' : ''; ?>>⚪ ปิดการแสดง</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="save_announcement" class="btn btn-primary py-2 fw-bold shadow-sm">
                                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                                </button>
                                <?php if($edit_data): ?>
                                    <a href="announcement_manager.php" class="btn btn-secondary">ยกเลิก</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ส่วนตารางรายการ (List) -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-dark">รายการประกาศทั้งหมด</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4" style="width: 45%;">หัวข้อ / รายละเอียด</th>
                                        <th class="text-center">ประเภท</th>
                                        <th class="text-center">สถานะ</th>
                                        <th class="text-center">วันที่</th>
                                        <th class="text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($announcements)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block text-light"></i>
                                                ยังไม่มีรายการประกาศ
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $row): 
                                            // กำหนดสี Badge ตาม Type
                                            $badgeClass = 'bg-secondary';
                                            $icon = 'fa-info-circle';
                                            if ($row['type'] == 'Urgent') { $badgeClass = 'badge-urgent'; $icon = 'fa-exclamation-circle'; }
                                            elseif ($row['type'] == 'Alert') { $badgeClass = 'badge-alert'; $icon = 'fa-bell'; }
                                            elseif ($row['type'] == 'General') { $badgeClass = 'badge-general'; $icon = 'fa-bullhorn'; }
                                        ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['title']); ?></div>
                                                <small class="text-muted text-truncate d-block" style="max-width: 300px;">
                                                    <?php echo htmlspecialchars($row['content']); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3 py-2 fw-normal">
                                                    <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo $row['type']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($row['status'] == 'Active'): ?>
                                                    <span class="text-success small fw-bold"><i class="fas fa-circle me-1"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="text-secondary small"><i class="fas fa-circle me-1"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-muted small">
                                                <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="announcement_manager.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-warning" title="แก้ไข">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบประกาศนี้?');">
                                                        <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
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

        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>