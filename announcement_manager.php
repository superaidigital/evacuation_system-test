<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบสิทธิ์การใช้งาน (Clean Code: Authentication Guard)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูลศูนย์พักพิงเพื่อใช้ใน Dropdown
$shelters = [];
$shelter_sql = "SELECT id, name FROM shelters ORDER BY name ASC";
$shelter_result = mysqli_query($conn, $shelter_sql);
if ($shelter_result) {
    while ($row = mysqli_fetch_assoc($shelter_result)) {
        $shelters[] = $row;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">จัดการข่าวสารและประกาศ (Announcements)</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <!-- ส่วนแสดงข้อความแจ้งเตือน (Flash Messages) -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- แบบฟอร์มสร้างประกาศใหม่ -->
                <div class="col-md-4">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-bullhorn"></i> สร้างประกาศใหม่</h3>
                        </div>
                        <form action="announcement_save.php" method="POST">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="title">หัวข้อประกาศ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" required placeholder="เช่น แจ้งเตือนระดับน้ำ...">
                                </div>
                                <div class="form-group">
                                    <label for="type">ประเภท/ความเร่งด่วน</label>
                                    <select class="form-control" id="type" name="type">
                                        <option value="info">🔵 ข่าวสารทั่วไป (Info)</option>
                                        <option value="success">🟢 เรื่องดี/ความช่วยเหลือ (Success)</option>
                                        <option value="warning">🟡 แจ้งเตือน (Warning)</option>
                                        <option value="danger">🔴 วิกฤต/ฉุกเฉิน (Danger)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="target_shelter_id">เป้าหมาย (ศูนย์พักพิง)</label>
                                    <select class="form-control" id="target_shelter_id" name="target_shelter_id">
                                        <option value="">🌐 ประกาศถึงทุกศูนย์ (Global)</option>
                                        <?php foreach ($shelters as $shelter): ?>
                                            <option value="<?php echo $shelter['id']; ?>"><?php echo htmlspecialchars($shelter['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="content">รายละเอียด <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="content" name="content" rows="4" required placeholder="รายละเอียดของประกาศ..."></textarea>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="save_announcement" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> บันทึกประกาศ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ตรายการประกาศล่าสุด -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header border-0">
                            <h3 class="card-title">ประวัติการประกาศ</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-striped table-valign-middle">
                                <thead>
                                    <tr>
                                        <th>หัวข้อ</th>
                                        <th>ประเภท</th>
                                        <th>เป้าหมาย</th>
                                        <th>วันที่ประกาศ</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Clean Code: Use prepared statements or simple query logic
                                    // Query ดึงข้อมูลประกาศ join กับตาราง shelters (Left join เพราะบางอันเป็น Global)
                                    $query = "SELECT a.*, s.name as shelter_name 
                                              FROM announcements a 
                                              LEFT JOIN shelters s ON a.target_shelter_id = s.id 
                                              ORDER BY a.created_at DESC LIMIT 20";
                                    $result = mysqli_query($conn, $query);

                                    if (mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            // จัดการสีของ Badge ตาม Type
                                            $badgeClass = 'badge-info';
                                            $typeText = 'ทั่วไป';
                                            switch($row['type']) {
                                                case 'warning': $badgeClass = 'badge-warning'; $typeText = 'แจ้งเตือน'; break;
                                                case 'danger': $badgeClass = 'badge-danger'; $typeText = 'ฉุกเฉิน'; break;
                                                case 'success': $badgeClass = 'badge-success'; $typeText = 'สำเร็จ'; break;
                                            }
                                            
                                            // จัดการข้อความเป้าหมาย
                                            $target = $row['target_shelter_id'] ? htmlspecialchars($row['shelter_name']) : '<span class="text-muted font-italic">ทั้งหมด</span>';
                                            
                                            // จัดการสถานะ Active
                                            $statusBadge = $row['is_active'] ? '<span class="badge badge-success">แสดงผล</span>' : '<span class="badge badge-secondary">ซ่อน</span>';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted text-truncate" style="max-width: 200px; display: inline-block;">
                                                        <?php echo mb_strimwidth(htmlspecialchars($row['content']), 0, 50, '...'); ?>
                                                    </small>
                                                </td>
                                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $typeText; ?></span></td>
                                                <td><?php echo $target; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo $statusBadge; ?></td>
                                                <td>
                                                    <a href="announcement_save.php?delete=<?php echo $row['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('ยืนยันที่จะลบประกาศนี้?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <!-- Toggle Status Button -->
                                                    <a href="announcement_save.php?toggle=<?php echo $row['id']; ?>&status=<?php echo $row['is_active']; ?>" 
                                                       class="btn btn-sm btn-default" title="เปลี่ยนสถานะ">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center'>ไม่พบข้อมูลประกาศ</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>