<?php
// includes/sidebar.php
// ไฟล์จัดการแสดงผลเมนูตามระดับสิทธิ์ (Role-Based Menu)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบสถานะ ถ้าไม่มีให้เป็น guest
$role = $_SESSION['role'] ?? 'guest';
$current_page = basename($_SERVER['PHP_SELF']);

// ฟังก์ชันช่วยตรวจสอบ Active Menu
function isActive($pageName, $currentPage) {
    return ($pageName === $currentPage) ? 'active' : '';
}
?>

<div class="d-flex flex-column flex-shrink-0 p-3 bg-white shadow-sm" style="width: 280px; min-height: 100vh;">
    <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-dark text-decoration-none">
        <span class="fs-4 fw-bold text-primary"><i class="fas fa-shield-alt me-2"></i>Evacuation Sys</span>
    </a>
    <hr>
    
    <!-- ส่วนแสดงข้อมูลผู้ใช้ -->
    <div class="mb-3 text-center">
        <div class="avatar bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
            <i class="fas fa-user fa-lg"></i>
        </div>
        <div>
            <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></strong><br>
            <span class="badge rounded-pill 
                <?php 
                    if($role=='admin') echo 'bg-danger'; 
                    elseif($role=='staff') echo 'bg-primary'; 
                    elseif($role=='donation_officer') echo 'bg-success'; 
                    else echo 'bg-secondary';
                ?>">
                <?php 
                    if($role=='admin') echo 'ผู้ดูแลระบบ'; 
                    elseif($role=='staff') echo 'เจ้าหน้าที่ศูนย์'; 
                    elseif($role=='donation_officer') echo 'จนท. ทรัพยากร'; 
                    else echo 'ผู้เยี่ยมชม';
                ?>
            </span>
        </div>
    </div>

    <ul class="nav nav-pills flex-column mb-auto">
        
        <!-- 1. DASHBOARD (ทุกคนเห็น แต่ Link อาจต่างกัน) -->
        <li class="nav-item">
            <a href="index.php" class="nav-link link-dark <?php echo isActive('index.php', $current_page); ?>">
                <i class="fas fa-home me-2"></i> หน้าหลัก
            </a>
        </li>
        <li class="nav-item">
            <?php 
                $dashLink = 'monitor_dashboard.php'; // Default Admin
                if ($role == 'staff') $dashLink = 'shelter_status.php';
                if ($role == 'donation_officer') $dashLink = 'inventory_list.php';
            ?>
            <a href="<?php echo $dashLink; ?>" class="nav-link link-dark <?php echo isActive($dashLink, $current_page); ?>">
                <i class="fas fa-chart-line me-2"></i> แดชบอร์ด
            </a>
        </li>

        <!-- 2. ทะเบียนผู้ประสบภัย (Admin & Staff) -->
        <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item mt-2">
            <small class="text-muted text-uppercase fw-bold ps-3" style="font-size: 0.75rem;">ผู้ประสบภัย & ศูนย์</small>
        </li>
        <li>
            <a href="evacuee_list.php" class="nav-link link-dark <?php echo isActive('evacuee_list.php', $current_page); ?>">
                <i class="fas fa-users me-2"></i> ทะเบียนผู้ประสบภัย
            </a>
        </li>
        <li>
            <a href="family_finder.php" class="nav-link link-dark <?php echo isActive('family_finder.php', $current_page); ?>">
                <i class="fas fa-search me-2"></i> ค้นหาครอบครัว
            </a>
        </li>
        <li>
            <a href="shelter_list.php" class="nav-link link-dark <?php echo isActive('shelter_list.php', $current_page); ?>">
                <i class="fas fa-campground me-2"></i> ข้อมูลศูนย์พักพิง
            </a>
        </li>
        <?php endif; ?>

        <!-- 3. จัดการทรัพยากร (Admin, Donation Officer, Staff) -->
        <?php if ($role === 'admin' || $role === 'donation_officer' || $role === 'staff'): ?>
        <li class="nav-item mt-2">
            <small class="text-muted text-uppercase fw-bold ps-3" style="font-size: 0.75rem;">คลังสินค้า & สิ่งของ</small>
        </li>
        <li>
            <a href="inventory_list.php" class="nav-link link-dark <?php echo isActive('inventory_list.php', $current_page); ?>">
                <i class="fas fa-boxes me-2"></i> คลังสินค้า/รับบริจาค
            </a>
        </li>
        <li>
            <a href="distribution_manager.php" class="nav-link link-dark <?php echo isActive('distribution_manager.php', $current_page); ?>">
                <i class="fas fa-hand-holding-heart me-2"></i> เบิกจ่ายสิ่งของ
            </a>
        </li>
        <li>
            <a href="distribution_history.php" class="nav-link link-dark <?php echo isActive('distribution_history.php', $current_page); ?>">
                <i class="fas fa-history me-2"></i> ประวัติการรับ-จ่าย
            </a>
        </li>
        <?php endif; ?>

        <!-- 4. รายงาน (Admin & Donation Officer) -->
        <?php if ($role === 'admin' || $role === 'donation_officer'): ?>
        <li class="nav-item mt-2">
            <small class="text-muted text-uppercase fw-bold ps-3" style="font-size: 0.75rem;">รายงาน & สถิติ</small>
        </li>
        <li>
            <a href="report.php" class="nav-link link-dark <?php echo isActive('report.php', $current_page); ?>">
                <i class="fas fa-file-alt me-2"></i> รายงานสรุปภาพรวม
            </a>
        </li>
        <?php endif; ?>

        <!-- 5. ผู้ดูแลระบบ (Admin Only) -->
        <?php if ($role === 'admin'): ?>
        <li class="nav-item mt-2">
            <small class="text-muted text-uppercase fw-bold ps-3" style="font-size: 0.75rem;">ตั้งค่าระบบ</small>
        </li>
        <li>
            <a href="user_manager.php" class="nav-link link-dark <?php echo isActive('user_manager.php', $current_page); ?>">
                <i class="fas fa-users-cog me-2"></i> จัดการผู้ใช้งาน
            </a>
        </li>
        <li>
            <a href="system_log_list.php" class="nav-link link-dark <?php echo isActive('system_log_list.php', $current_page); ?>">
                <i class="fas fa-clipboard-list me-2"></i> System Logs
            </a>
        </li>
        <?php endif; ?>

    </ul>
    
    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle" id="dropdownUser2" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-cog me-2"></i>
            <strong>ตั้งค่าส่วนตัว</strong>
        </a>
        <ul class="dropdown-menu text-small shadow" aria-labelledby="dropdownUser2">
            <li><a class="dropdown-item" href="#">เปลี่ยนรหัสผ่าน</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
        </ul>
    </div>
</div>