<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$current_page = basename($_SERVER['PHP_SELF']);
$menu_context = isset($_GET['menu']) ? $_GET['menu'] : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการศูนย์พักพิง</title>
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sidebar-width: 270px;
            --header-height: 60px;
            
            /* Official Color Palette */
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

        /* --- LAYOUT STRUCTURE --- */
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background-color: var(--nav-bg);
            color: var(--text-main);
            transition: margin 0.3s ease-in-out;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1050;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            overflow-y: auto;
        }

        #sidebar.collapsed {
            margin-left: calc(var(--sidebar-width) * -1);
        }

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

        #content.expanded {
            margin-left: 0;
        }

        @media (max-width: 991.98px) {
            #sidebar { margin-left: calc(var(--sidebar-width) * -1); }
            #sidebar.show-mobile { margin-left: 0; }
            #content { margin-left: 0; }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                backdrop-filter: blur(2px);
                transition: opacity 0.3s;
            }
            .mobile-overlay.show { display: block; }
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.2);
            text-align: center;
            border-bottom: 3px solid var(--active-gold);
        }
        
        .sidebar-title {
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            color: #fff;
        }
        
        .sidebar-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 400;
            text-transform: uppercase;
        }

        .user-panel {
            padding: 20px 20px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-img {
            width: 40px;
            height: 40px;
            background: var(--active-gold);
            color: var(--nav-bg);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .user-info-text {
            line-height: 1.3;
            overflow: hidden;
        }
        
        .user-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: #fff;
            white-space: nowrap;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* --- MENU LIST --- */
        ul.components {
            padding: 10px 0;
            margin: 0;
            list-style: none;
        }

        .menu-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 15px 25px 5px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        ul.components li a {
            padding: 12px 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            /* Thin separator lines for formal list look */
            border-bottom: 1px solid rgba(255,255,255,0.02); 
            position: relative; 
            cursor: pointer;
        }

        ul.components li a:hover {
            color: #fff;
            background-color: var(--nav-hover);
        }

        /* Active Menu State */
        ul.components li a.active {
            color: #fff;
            background-color: rgba(251, 191, 36, 0.1); 
            border-left-color: var(--active-gold);
            font-weight: 500;
        }

        ul.components li a i {
            width: 24px;
            font-size: 1rem;
            text-align: center;
            margin-right: 12px;
            opacity: 0.8;
        }
        
        ul.components li a.active i {
            opacity: 1;
            color: var(--active-gold);
        }

        /* Parent Active State (When child is active) */
        a.active-parent {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        a.active-parent i {
            opacity: 1;
            color: #fff; 
        }

        /* FIX: Hide Bootstrap's Default Arrow completely */
        .dropdown-toggle::after {
            display: none !important;
            content: none !important;
            border: none !important; 
        }

        /* Dropdown Arrow Custom */
        .dropdown-icon {
            margin-left: auto;
            font-size: 0.75rem;
            transition: transform 0.3s;
        }
        
        a[aria-expanded="true"] .dropdown-icon {
            transform: rotate(180deg);
        }

        ul.collapse {
            background-color: rgba(0,0,0,0.15);
        }
        
        ul.collapse li a {
            padding-left: 58px;
            font-size: 0.85rem;
            border-bottom: none;
        }

        .top-navbar {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .btn-toggle-menu {
            color: #475569;
            cursor: pointer;
            padding: 5px;
            font-size: 1.2rem;
        }
        .btn-toggle-menu:hover { color: var(--nav-bg); }

        .page-title {
            font-weight: 600;
            color: var(--nav-bg);
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

<!-- Overlay for Mobile -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="wrapper">
    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex justify-content-center mb-2">
                <i class="fas fa-shield-alt fa-2x text-warning"></i>
            </div>
            <div class="sidebar-title">ระบบบริหารศูนย์พักพิงฯ</div>
            <div class="sidebar-subtitle">Disaster Management System</div>
        </div>

        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="user-panel">
                <div class="user-img">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-info-text">
                    <div class="user-name"><?php echo $_SESSION['fullname'] ?? 'ผู้ใช้งานทั่วไป'; ?></div>
                    <div class="user-role">
                        <i class="fas fa-circle fa-xs text-success me-1" style="font-size: 6px; vertical-align: middle;"></i>
                        <?php 
                            $role = $_SESSION['role'] ?? 'staff';
                            echo ($role == 'admin') ? 'ผู้ดูแลระบบ (Admin)' : 'เจ้าหน้าที่ (Staff)';
                        ?>
                    </div>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas fa-tachometer-alt"></i> หน้าหลักภาพรวม
                        </div>
                    </a>
                </li>
                
                <li class="menu-label">ระบบฐานข้อมูล</li>

                <!-- หมวด: ทะเบียนศูนย์พักพิง -->
                <?php
                    // Logic Active:
                    // 1. หน้า shelter_form.php หรือ caretaker_list.php -> Active
                    // 2. หน้า shelter_list.php แต่ต้องไม่ใช่หมวดผู้ประสบภัย (menu!=evacuee) -> Active
                    $is_shelter_active = in_array($current_page, ['shelter_form.php', 'caretaker_list.php']) || ($current_page == 'shelter_list.php' && $menu_context != 'evacuee');
                ?>
                <li>
                    <a href="#shelterSubmenu" data-bs-toggle="collapse" class="dropdown-toggle <?php echo $is_shelter_active ? 'active-parent' : ''; ?>">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas fa-landmark"></i> ทะเบียนศูนย์พักพิง
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </div>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $is_shelter_active ? 'show' : ''; ?>" id="shelterSubmenu">
                        <li>
                            <a href="shelter_list.php" class="<?php echo ($current_page == 'shelter_list.php' && $menu_context != 'evacuee') ? 'active' : ''; ?>">
                                จัดการศูนย์พักพิง
                            </a>
                        </li>
                        <li>
                            <a href="caretaker_list.php" class="<?php echo $current_page == 'caretaker_list.php' ? 'active' : ''; ?>">
                                ทำเนียบผู้ดูแลศูนย์
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- หมวด: ทะเบียนผู้ประสบภัย -->
                <?php
                    // Logic Active:
                    // 1. หน้า evacuee_list.php, search_evacuee.php, import_csv.php -> Active
                    // 2. หน้า shelter_list.php แต่ต้องเป็นหมวดผู้ประสบภัย (menu=evacuee) -> Active
                    $is_evacuee_active = in_array($current_page, ['evacuee_list.php', 'evacuee_form.php', 'search_evacuee.php', 'import_csv.php']) || ($current_page == 'shelter_list.php' && $menu_context == 'evacuee');
                ?>
                <li>
                    <a href="#evacueeSubmenu" data-bs-toggle="collapse" class="dropdown-toggle <?php echo $is_evacuee_active ? 'active-parent' : ''; ?>">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas fa-address-book"></i> ทะเบียนผู้ประสบภัย
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </div>
                    </a>
                    <ul class="collapse list-unstyled <?php echo $is_evacuee_active ? 'show' : ''; ?>" id="evacueeSubmenu">
                        <li>
                            <a href="shelter_list.php?menu=evacuee" class="<?php echo ($current_page == 'shelter_list.php' && $menu_context == 'evacuee') ? 'active' : ''; ?>">
                                รายชื่อผู้พักพิง (รายศูนย์)
                            </a>
                        </li>
                        <li>
                            <a href="search_evacuee.php" class="<?php echo $current_page == 'search_evacuee.php' ? 'active' : ''; ?>">
                                ค้นหาข้อมูลบุคคล
                            </a>
                        </li>
                        <li>
                            <a href="import_csv.php" class="<?php echo $current_page == 'import_csv.php' ? 'active' : ''; ?>">
                                นำเข้าข้อมูล (CSV)
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="menu-label">การรายงานและระบบ</li>

                <li>
                    <a href="report.php" class="<?php echo $current_page == 'report.php' ? 'active' : ''; ?>">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas fa-clipboard-list"></i> รายงานสรุปผล
                        </div>
                    </a>
                </li>

                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                <li>
                    <a href="#adminSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas fa-tools"></i> ส่วนผู้ดูแลระบบ
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </div>
                    </a>
                    <ul class="collapse list-unstyled <?php echo in_array($current_page, ['incident_manager.php', 'user_list.php', 'user_form.php', 'system_log_list.php']) ? 'show' : ''; ?>" id="adminSubmenu">
                        <li><a href="incident_manager.php" class="<?php echo $current_page == 'incident_manager.php' ? 'active' : ''; ?>">จัดการเหตุการณ์ภัยพิบัติ</a></li>
                        <li><a href="user_list.php" class="<?php echo in_array($current_page, ['user_list.php', 'user_form.php']) ? 'active' : ''; ?>">จัดการบัญชีผู้ใช้งาน</a></li>
                        <li><a href="system_log_list.php" class="<?php echo $current_page == 'system_log_list.php' ? 'active' : ''; ?>">ประวัติการใช้งาน (Logs)</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <li>
                    <a href="monitor_dashboard.php" target="_blank">
                        <div class="d-flex align-items-center w-100">
                            <i class="fas fa-desktop"></i> ศูนย์ปฏิบัติการ (War Room)
                        </div>
                    </a>
                </li>
            </ul>

            <div class="px-3 mt-4 mb-4">
                <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm d-flex justify-content-center align-items-center gap-2">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            </div>
        <?php else: ?>
             <div class="p-4 text-center">
                <a href="login.php" class="btn btn-primary w-100 fw-bold">เข้าสู่ระบบ</a>
             </div>
        <?php endif; ?>
    </nav>

    <!-- Page Content Holder -->
    <div id="content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="d-flex align-items-center">
                <div id="sidebarCollapse" class="btn-toggle-menu me-3">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="page-title d-none d-sm-block">
                    <?php 
                        // เปลี่ยนชื่อหัวข้อให้เป็นทางการตามหน้า
                        if($current_page == 'index.php') echo 'ภาพรวมสถานการณ์ (Dashboard)';
                        elseif(strpos($current_page, 'report') !== false) echo 'รายงานสรุปสถานการณ์';
                        elseif(strpos($current_page, 'shelter') !== false) echo 'ระบบทะเบียนศูนย์พักพิง';
                        elseif(strpos($current_page, 'evacuee') !== false) echo 'ระบบทะเบียนผู้ประสบภัย';
                        elseif(strpos($current_page, 'search') !== false) echo 'ระบบสืบค้นข้อมูล';
                        elseif(strpos($current_page, 'user') !== false) echo 'การจัดการบัญชีผู้ใช้งาน';
                        elseif(strpos($current_page, 'incident') !== false) echo 'การจัดการเหตุการณ์ภัยพิบัติ';
                        elseif(strpos($current_page, 'log') !== false) echo 'ประวัติการใช้งานระบบ';
                        else echo 'ระบบบริหารจัดการ';
                    ?>
                </div>
            </div>
            <div class="text-secondary small">
                <i class="far fa-calendar-alt me-1"></i> <?php echo date('d F Y'); ?>
            </div>
        </nav>

        <!-- Main Content Padding -->
        <div class="p-4">