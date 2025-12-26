<?php
/**
 * includes/header.php
 * ส่วนหัวของหน้าเว็บ (Navbar & Sidebar Structure)
 * ปรับปรุงเมนูให้ครอบคลุมระบบงานศูนย์พักพิง, คลังสินค้า, และส่วนบัญชาการ
 */

// Start Session
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Config & DB Connection
require_once __DIR__ . '/../config/db.php'; 

$current_page = basename($_SERVER['PHP_SELF']);
$menu_context = isset($_GET['menu']) ? $_GET['menu'] : '';

// ตรวจสอบสิทธิ์และกำหนดตัวแปร Role
$role = $_SESSION['role'] ?? 'guest';
$is_admin = ($role === 'admin');
$is_staff = ($role === 'staff');
$is_donation = ($role === 'donation_officer');

// สำหรับ Staff ให้ดึง Shelter ID ของตัวเองมาใช้ใน Link
$my_shelter_id = $_SESSION['shelter_id'] ?? '';

// ดึงชื่อศูนย์ที่รับผิดชอบมาแสดงใน Sidebar (MySQLi Version)
$user_shelter_name = '';
if ($my_shelter_id && isset($conn)) {
    $sql_sh = "SELECT name FROM shelters WHERE id = ?";
    $stmt_sh = $conn->prepare($sql_sh);
    if ($stmt_sh) {
        $stmt_sh->bind_param("i", $my_shelter_id);
        if ($stmt_sh->execute()) {
            $res_sh = $stmt_sh->get_result();
            if ($row_sh = $res_sh->fetch_assoc()) {
                $user_shelter_name = $row_sh['name'];
            }
        }
        $stmt_sh->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการศูนย์พักพิงอัจฉริยะ</title>
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sidebar-width: 270px;
            --header-height: 60px;
            --nav-bg: #0f172a;       /* Dark Navy */
            --nav-hover: #1e293b;    /* Lighter Navy */
            --active-gold: #fbbf24;  /* Amber 400 */
            --text-main: #f1f5f9;    /* Slate 100 */
            --text-muted: #94a3b8;   /* Slate 400 */
            --body-bg: #f8fafc;
            --border-color: rgba(255,255,255,0.08);
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--body-bg);
            color: #334155;
            overflow-x: hidden;
            font-size: 0.95rem;
        }

        .wrapper { display: flex; width: 100%; align-items: stretch; }

        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background-color: var(--nav-bg);
            color: var(--text-main);
            transition: margin 0.3s ease-in-out;
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 1050;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            overflow-y: auto;
        }

        #sidebar.collapsed { margin-left: calc(var(--sidebar-width) * -1); }

        #content {
            width: 100%;
            min-height: 100vh;
            transition: margin 0.3s ease-in-out;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        #content.expanded { margin-left: 0; }

        @media (max-width: 991.98px) {
            #sidebar { margin-left: calc(var(--sidebar-width) * -1); }
            #sidebar.show-mobile { margin-left: 0; }
            #content { margin-left: 0; }
            .mobile-overlay {
                display: none;
                position: fixed;
                width: 100vw; height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                backdrop-filter: blur(2px);
            }
            .mobile-overlay.show { display: block; }
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.2);
            text-align: center;
            border-bottom: 3px solid var(--active-gold);
        }

        .user-panel {
            padding: 20px 20px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex; align-items: center; gap: 12px;
        }

        .user-img {
            width: 40px; height: 40px;
            background: var(--active-gold);
            color: var(--nav-bg);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 1.1rem;
        }

        ul.components { padding: 10px 0; list-style: none; }
        .menu-label {
            font-size: 0.7rem; text-transform: uppercase;
            color: var(--text-muted); padding: 15px 25px 5px;
            font-weight: 600; letter-spacing: 0.5px;
        }

        ul.components li a {
            padding: 12px 25px; font-size: 0.9rem;
            display: flex; align-items: center;
            color: var(--text-muted); text-decoration: none;
            transition: all 0.2s; border-left: 3px solid transparent;
        }

        ul.components li a:hover, ul.components li a.active {
            color: #fff; background-color: var(--nav-hover);
            border-left-color: var(--active-gold);
        }

        ul.components li a i { width: 24px; text-align: center; margin-right: 12px; opacity: 0.8; }
        ul.components li a.active i { color: var(--active-gold); opacity: 1; }

        .dropdown-toggle::after { display: none !important; }
        .dropdown-icon { margin-left: auto; font-size: 0.75rem; transition: transform 0.3s; }
        a[aria-expanded="true"] .dropdown-icon { transform: rotate(180deg); }

        ul.collapse { background-color: rgba(0,0,0,0.15); }
        ul.collapse li a { padding-left: 58px; font-size: 0.85rem; border-bottom: none; }

        .top-navbar {
            height: var(--header-height);
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 0 25px; display: flex; align-items: center;
            justify-content: space-between; position: sticky; top: 0; z-index: 900;
        }

        .btn-toggle-menu { color: #475569; cursor: pointer; font-size: 1.2rem; }
    </style>
</head>
<body>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="wrapper">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex justify-content-center mb-2">
                <i class="fas fa-shield-alt fa-2x text-warning"></i>
            </div>
            <div class="fw-bold text-white">ระบบบริหารศูนย์พักพิงฯ</div>
            <div class="text-muted" style="font-size: 0.65rem;">DISASTER MANAGEMENT SYSTEM</div>
        </div>

        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="user-panel">
                <div class="user-img"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                <div class="user-info-text overflow-hidden">
                    <div class="user-name text-white small fw-bold"><?php echo $_SESSION['username'] ?? 'User'; ?></div>
                    <div class="user-role text-muted" style="font-size: 0.7rem;">
                        <i class="fas fa-circle text-success me-1" style="font-size: 6px;"></i>
                        <?php echo $is_admin ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่ปฏิบัติงาน'; ?>
                    </div>
                    <?php if($user_shelter_name): ?>
                        <div class="text-warning mt-1 text-truncate" style="font-size: 0.7rem;">
                            <i class="fas fa-home me-1"></i><?php echo $user_shelter_name; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> หน้าหลักภาพรวม
                    </a>
                </li>

                <!-- 1. ส่วนบัญชาการ (War Room) -->
                <li class="menu-label">War Room & สถานการณ์</li>
                <li>
                    <a href="monitor_dashboard.php" target="_blank">
                        <i class="fas fa-desktop text-danger"></i> ศูนย์ปฏิบัติการ (LIVE)
                    </a>
                </li>
                <li>
                    <a href="gis_dashboard.php" class="<?php echo $current_page == 'gis_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-map-marked-alt text-info"></i> แผนที่สถานการณ์ (GIS)
                    </a>
                </li>

                <!-- 2. ระบบจัดการผู้อพยพ -->
                <li class="menu-label">งานทะเบียน & ผู้อพยพ</li>
                <?php if ($is_admin): ?>
                <li>
                    <a href="shelter_list.php" class="<?php echo ($current_page == 'shelter_list.php') ? 'active' : ''; ?>">
                        <i class="fas fa-landmark"></i> จัดการข้อมูลศูนย์พักพิง
                    </a>
                </li>
                <?php endif; ?>
                
                <li>
                    <a href="#evacueeSubmenu" data-bs-toggle="collapse" class="dropdown-toggle <?php echo (strpos($current_page, 'evacuee') !== false || $current_page == 'qr_scanner.php') ? 'text-white' : ''; ?>">
                        <i class="fas fa-users-viewfinder"></i> ทะเบียนผู้ประสบภัย
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($current_page, 'evacuee') !== false || $current_page == 'qr_scanner.php') ? 'show' : ''; ?>" id="evacueeSubmenu">
                        <li><a href="evacuee_list.php?shelter_id=all" class="<?php echo $current_page == 'evacuee_list.php' ? 'active' : ''; ?>">บัญชีรายชื่อ (Roster)</a></li>
                        <li><a href="qr_scanner.php" class="<?php echo $current_page == 'qr_scanner.php' ? 'active' : ''; ?>">สแกนบัตร (QR Scan)</a></li>
                        <li><a href="search_evacuee.php" class="<?php echo $current_page == 'search_evacuee.php' ? 'active' : ''; ?>">สืบค้นข้อมูลบุคคล</a></li>
                    </ul>
                </li>

                <!-- 3. ทรัพยากรและพัสดุ -->
                <li class="menu-label">ทรัพยากร & การสนับสนุน</li>
                <li>
                    <a href="#logisticsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle <?php echo (strpos($current_page, 'inventory') !== false || strpos($current_page, 'distribution') !== false) ? 'text-white' : ''; ?>">
                        <i class="fas fa-box-open"></i> บริหารสิ่งของ & คลัง
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="collapse list-unstyled <?php echo (strpos($current_page, 'inventory') !== false || strpos($current_page, 'distribution') !== false) ? 'show' : ''; ?>" id="logisticsSubmenu">
                        <li><a href="inventory_dashboard.php" class="<?php echo $current_page == 'inventory_dashboard.php' ? 'active' : ''; ?>">แดชบอร์ดพัสดุ</a></li>
                        <li><a href="inventory_list.php" class="<?php echo $current_page == 'inventory_list.php' ? 'active' : ''; ?>">จัดการสต็อกสินค้า</a></li>
                        <li><a href="distribution_manager.php" class="<?php echo $current_page == 'distribution_manager.php' ? 'active' : ''; ?>">บันทึกการแจกจ่าย</a></li>
                    </ul>
                </li>

                <!-- ส่วนคำร้องขอ: แยก Admin และ Staff -->
                <?php if($is_admin): ?>
                <li>
                    <a href="request_admin.php" class="<?php echo $current_page == 'request_admin.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check text-warning"></i> อนุมัติคำร้องขอพัสดุ
                    </a>
                </li>
                <?php else: ?>
                <li>
                    <a href="request_manager.php" class="<?php echo $current_page == 'request_manager.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hand-holding-heart"></i> ส่งคำร้องขอความช่วยเหลือ
                    </a>
                </li>
                <?php endif; ?>

                <!-- 4. รายงานและตั้งค่าระบบ -->
                <li class="menu-label">ข้อมูลสรุป & ตั้งค่า</li>
                <li>
                    <a href="announcement_manager.php" class="<?php echo $current_page == 'announcement_manager.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i> ข่าวสารและประกาศ
                    </a>
                </li>
                <?php if ($is_admin): ?>
                <li>
                    <a href="line_notify_setup.php" class="<?php echo $current_page == 'line_notify_setup.php' ? 'active' : ''; ?>">
                        <i class="fab fa-line text-success"></i> ตั้งค่าแจ้งเตือน LINE
                    </a>
                </li>
                <li>
                    <a href="user_manager.php" class="<?php echo $current_page == 'user_manager.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield"></i> จัดการผู้ใช้งานระบบ
                    </a>
                </li>
                <?php endif; ?>

                <li class="mt-4 px-3 mb-5">
                    <a href="#" onclick="confirmLogout(event)" class="btn btn-outline-danger btn-sm w-100 border-0 shadow-sm">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>

    <!-- Page Content -->
    <div id="content">
        <nav class="top-navbar shadow-sm">
            <div class="d-flex align-items-center">
                <div id="sidebarCollapse" class="btn-toggle-menu me-3">
                    <i class="fas fa-bars-staggered"></i>
                </div>
                <div class="page-title d-none d-sm-block fw-bold text-dark">
                    <?php 
                        if($current_page == 'index.php') echo 'ภาพรวมสถานการณ์';
                        elseif($current_page == 'gis_dashboard.php') echo 'แผนที่สถานการณ์ (GIS)';
                        elseif($current_page == 'monitor_dashboard.php') echo 'ศูนย์ปฏิบัติการ (Monitor)';
                        elseif($current_page == 'evacuee_list.php') echo 'ทะเบียนรายชื่อผู้ประสบภัย';
                        elseif($current_page == 'inventory_dashboard.php') echo 'แดชบอร์ดคลังทรัพยากร';
                        elseif(strpos($current_page, 'request') !== false) echo 'ระบบจัดการคำร้องขอ';
                        else echo 'ระบบบริหารจัดการศูนย์พักพิง';
                    ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="family_finder.php" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    <i class="fas fa-search me-1"></i> สำหรับประชาชน
                </a>
                <div class="text-secondary small d-none d-md-block">
                    <i class="far fa-calendar-check me-1"></i> <?php echo date('d M Y'); ?>
                </div>
            </div>
        </nav>
        
        <div class="p-4">
            <!-- Content Starts Here -->

<script>
    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'ยืนยันการออกจากระบบ?',
            text: "คุณต้องการออกจากระบบบริหารจัดการใช่หรือไม่",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ใช่, ออกจากระบบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = 'logout.php'; }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const sidebarCollapse = document.getElementById('sidebarCollapse');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function toggleSidebar() {
            if (window.innerWidth <= 991.98) {
                sidebar.classList.toggle('show-mobile');
                mobileOverlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            }
        }

        if (sidebarCollapse) sidebarCollapse.addEventListener('click', toggleSidebar);
        if (mobileOverlay) mobileOverlay.addEventListener('click', toggleSidebar);
    });
</script>