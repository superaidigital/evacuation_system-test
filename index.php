<?php
// index.php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. ดึงเหตุการณ์ปัจจุบัน (Active Incident)
$stmt_inc = $pdo->query("SELECT * FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$current_incident = $stmt_inc->fetch();

$incident_id = $current_incident ? $current_incident['id'] : 0;
$incident_name = $current_incident ? $current_incident['name'] : 'ไม่มีสถานการณ์ภัยพิบัติ (Normal State)';
$is_active = $current_incident ? true : false;

// 2. ดึงสถิติเบื้องต้น
$stats = [
    'evacuees' => 0,
    'shelters' => 0,
    'capacity' => 0,
    'staff' => 0
];

if ($incident_id) {
    // ผู้ประสบภัยที่ยังพักอยู่
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE incident_id = ? AND check_out_date IS NULL");
    $stmt->execute([$incident_id]);
    $stats['evacuees'] = $stmt->fetchColumn();

    // ศูนย์ที่เปิดใช้งาน
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ?");
    $stmt->execute([$incident_id]);
    $stats['shelters'] = $stmt->fetchColumn();

    // ความจุรวมทั้งหมดของศูนย์ในเหตุการณ์นี้
    $stmt = $pdo->prepare("SELECT SUM(capacity) FROM shelters WHERE incident_id = ?");
    $stmt->execute([$incident_id]);
    $stats['capacity'] = $stmt->fetchColumn();

    // เจ้าหน้าที่ปฏิบัติงาน (ผู้ดูแล)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM caretakers c JOIN shelters s ON c.shelter_id = s.id WHERE s.incident_id = ?");
    $stmt->execute([$incident_id]);
    $stats['staff'] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05));
            transform: skewX(-20deg);
        }

        .status-badge {
            background-color: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.4);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background-color: #fbbf24;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7);
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(251, 191, 36, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border-color: #cbd5e1;
        }

        .stat-card.primary { border-left: 4px solid #0f172a; }
        .stat-card.warning { border-left: 4px solid #fbbf24; }
        .stat-card.success { border-left: 4px solid #10b981; }
        .stat-card.info { border-left: 4px solid #3b82f6; }

        .stat-icon {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 2.5rem;
            opacity: 0.1;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #334155;
            transition: all 0.2s;
        }

        .quick-action-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .icon-box {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 15px;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    
    <!-- Header Section -->
    <div class="dashboard-header mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="text-white-50 text-uppercase small fw-bold mb-1">Overview Dashboard</div>
                <h2 class="fw-bold mb-2">ภาพรวมสถานการณ์ภัยพิบัติ</h2>
                <div class="d-flex align-items-center gap-3">
                    <span class="status-badge">
                        <?php if($is_active): ?>
                            <span class="pulse-dot"></span> สถานการณ์ปัจจุบัน: <?php echo htmlspecialchars($incident_name); ?>
                        <?php else: ?>
                            <i class="fas fa-check-circle"></i> ปกติ (ไม่มีเหตุการณ์)
                        <?php endif; ?>
                    </span>
                    <small class="text-white-50"><i class="far fa-clock"></i> ข้อมูลล่าสุด: <?php echo date('H:i น.'); ?></small>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="incident_manager.php" class="btn btn-outline-warning btn-sm">
                    <i class="fas fa-cog"></i> จัดการเหตุการณ์
                </a>
                <?php endif; ?>
                <a href="monitor_dashboard.php" target="_blank" class="btn btn-light text-primary btn-sm ms-2">
                    <i class="fas fa-desktop"></i> เปิด War Room
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row g-4 mb-4">
        <!-- Card 1: ผู้ประสบภัย -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card primary">
                <i class="fas fa-users stat-icon text-primary"></i>
                <h6 class="text-muted text-uppercase small fw-bold">ผู้ประสบภัย (ปัจจุบัน)</h6>
                <h2 class="fw-bold text-dark mt-2 mb-0"><?php echo number_format($stats['evacuees']); ?></h2>
                <small class="text-muted">คน</small>
            </div>
        </div>

        <!-- Card 2: ศูนย์ที่เปิด -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card success">
                <i class="fas fa-campground stat-icon text-success"></i>
                <h6 class="text-muted text-uppercase small fw-bold">ศูนย์พักพิงที่เปิด</h6>
                <h2 class="fw-bold text-dark mt-2 mb-0"><?php echo number_format($stats['shelters']); ?></h2>
                <small class="text-success">
                    <i class="fas fa-check"></i> พร้อมให้บริการ
                </small>
            </div>
        </div>

        <!-- Card 3: อัตราการครองเตียง -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card warning">
                <i class="fas fa-bed stat-icon text-warning"></i>
                <h6 class="text-muted text-uppercase small fw-bold">ความจุรองรับได้</h6>
                <?php 
                    $percent = ($stats['capacity'] > 0) ? ($stats['evacuees'] / $stats['capacity']) * 100 : 0;
                ?>
                <h2 class="fw-bold text-dark mt-2 mb-0"><?php echo number_format($percent, 1); ?>%</h2>
                <div class="progress mt-2" style="height: 4px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Card 4: เจ้าหน้าที่ -->
        <div class="col-md-3 col-sm-6">
            <div class="stat-card info">
                <i class="fas fa-user-shield stat-icon text-info"></i>
                <h6 class="text-muted text-uppercase small fw-bold">เจ้าหน้าที่ปฏิบัติงาน</h6>
                <h2 class="fw-bold text-dark mt-2 mb-0"><?php echo number_format($stats['staff']); ?></h2>
                <small class="text-muted">นาย</small>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-bolt text-warning"></i> เมนูด่วน (Quick Actions)</h5>
    <div class="row g-3">
        <div class="col-md-4">
            <a href="evacuee_form.php?mode=add" class="quick-action-btn">
                <div class="icon-box bg-primary text-white">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <div class="fw-bold">ลงทะเบียนรายใหม่</div>
                    <small class="text-muted">บันทึกข้อมูลผู้ประสบภัยเข้าสู่ระบบ</small>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="search_evacuee.php" class="quick-action-btn">
                <div class="icon-box bg-success text-white">
                    <i class="fas fa-search"></i>
                </div>
                <div>
                    <div class="fw-bold">ค้นหาข้อมูลบุคคล</div>
                    <small class="text-muted">ตรวจสอบประวัติการเข้าพักพิง</small>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="report.php" class="quick-action-btn">
                <div class="icon-box bg-secondary text-white">
                    <i class="fas fa-print"></i>
                </div>
                <div>
                    <div class="fw-bold">ออกรายงานสรุป</div>
                    <small class="text-muted">พิมพ์รายงานประจำวัน/ส่งออก Excel</small>
                </div>
            </a>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>