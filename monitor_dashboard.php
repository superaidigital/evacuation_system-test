<?php
// monitor_dashboard.php
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
$incident_name = $current_incident ? $current_incident['name'] : 'Standby Mode (No Active Incident)';

// Init Stats
$total_evacuees = 0;
$total_shelters = 0;
$male_count = 0;
$female_count = 0;
$elderly_count = 0;
$child_count = 0;
$shelter_data = [];

if ($incident_id) {
    // ยอดรวมผู้พักพิง (ยังไม่ออก)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM evacuees WHERE incident_id = ? AND check_out_date IS NULL");
    $stmt->execute([$incident_id]);
    $total_evacuees = $stmt->fetchColumn();

    // ยอดรวมศูนย์
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ?");
    $stmt->execute([$incident_id]);
    $total_shelters = $stmt->fetchColumn();

    // แยกชาย/หญิง
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN prefix IN ('นาย','ด.ช.') THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN prefix IN ('นาง','นางสาว','ด.ญ.') THEN 1 ELSE 0 END) as female
        FROM evacuees WHERE incident_id = ? AND check_out_date IS NULL");
    $stmt->execute([$incident_id]);
    $gender = $stmt->fetch();
    $male_count = $gender['male'] ?? 0;
    $female_count = $gender['female'] ?? 0;

    // กลุ่มเปราะบาง (สูงอายุ 60+, เด็ก <15)
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
        SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as children
        FROM evacuees WHERE incident_id = ? AND check_out_date IS NULL");
    $stmt->execute([$incident_id]);
    $vul = $stmt->fetch();
    $elderly_count = $vul['elderly'] ?? 0;
    $child_count = $vul['children'] ?? 0;

    // ข้อมูลรายศูนย์ (เรียงตามจำนวนคนพักพิง มาก->น้อย)
    $sql = "SELECT s.name, s.capacity, 
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current 
            FROM shelters s WHERE s.incident_id = ? 
            ORDER BY current DESC LIMIT 6"; // โชว์ Top 6 ศูนย์ที่มีคนเยอะสุด
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$incident_id]);
    $shelter_data = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>War Room - ศูนย์บัญชาการเหตุการณ์</title>
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts (Prompt) -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-color: #1a1a2e;
            color: #e0e0e0;
            font-family: 'Prompt', sans-serif;
            overflow-x: hidden;
        }
        .card-dark {
            background-color: #16213e;
            border: 1px solid #2a3b55;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .text-neon-green { color: #00ff9d; text-shadow: 0 0 10px rgba(0,255,157,0.3); }
        .text-neon-blue { color: #00d2ff; text-shadow: 0 0 10px rgba(0,210,255,0.3); }
        .text-neon-red { color: #ff4b5c; text-shadow: 0 0 10px rgba(255,75,92,0.3); }
        .text-neon-yellow { color: #ffdd59; text-shadow: 0 0 10px rgba(255,221,89,0.3); }
        
        .stat-number { font-size: 3.5rem; font-weight: 600; line-height: 1.2; }
        .stat-label { font-size: 1rem; opacity: 0.7; letter-spacing: 1px; }
        
        .progress-dark { background-color: #0f3460; height: 10px; border-radius: 5px; }
        
        .top-bar {
            background: linear-gradient(90deg, #16213e 0%, #0f3460 100%);
            padding: 15px 30px;
            border-bottom: 2px solid #e94560;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .live-indicator {
            display: inline-block;
            width: 12px; height: 12px;
            background-color: #ff0000;
            border-radius: 50%;
            margin-right: 8px;
            animation: blink 1s infinite;
        }
        @keyframes blink { 50% { opacity: 0.3; } }
        
        .table-dark-custom {
            --bs-table-bg: #16213e;
            --bs-table-color: #e0e0e0;
            border-color: #2a3b55;
        }
    </style>
    
    <!-- Auto Refresh ทุก 30 วินาที -->
    <meta http-equiv="refresh" content="30">
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="d-flex align-items-center">
            <h3 class="mb-0 fw-bold"><i class="fas fa-desktop"></i> DISASTER WAR ROOM</h3>
            <span class="ms-4 badge bg-danger text-uppercase px-3 py-2">
                <span class="live-indicator"></span> <?php echo htmlspecialchars($incident_name); ?>
            </span>
        </div>
        <div class="text-end">
            <div class="h5 mb-0" id="clock">00:00:00</div>
            <small class="text-muted"><?php echo date('d F Y'); ?></small>
            <a href="index.php" class="btn btn-sm btn-outline-light ms-3">Exit</a>
        </div>
    </div>

    <div class="container-fluid p-4">
        
        <!-- Main Stats Row -->
        <div class="row g-4 mb-4">
            <!-- Total Evacuees -->
            <div class="col-md-3">
                <div class="card-dark p-4 h-100 text-center position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-25">
                        <i class="fas fa-users fa-5x"></i>
                    </div>
                    <h5 class="stat-label text-neon-blue">TOTAL EVACUEES</h5>
                    <div class="stat-number text-white my-2"><?php echo number_format($total_evacuees); ?></div>
                    <div class="text-muted">ผู้พักพิงในระบบ</div>
                </div>
            </div>
            
            <!-- Open Shelters -->
            <div class="col-md-3">
                <div class="card-dark p-4 h-100 text-center position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3 opacity-25">
                        <i class="fas fa-campground fa-5x"></i>
                    </div>
                    <h5 class="stat-label text-neon-green">ACTIVE SHELTERS</h5>
                    <div class="stat-number text-white my-2"><?php echo number_format($total_shelters); ?></div>
                    <div class="text-muted">ศูนย์ที่เปิดใช้งาน</div>
                </div>
            </div>

            <!-- Vulnerable Groups -->
            <div class="col-md-6">
                <div class="card-dark p-4 h-100">
                    <h5 class="stat-label text-neon-yellow mb-4"><i class="fas fa-exclamation-triangle"></i> VULNERABLE GROUPS (กลุ่มเปราะบาง)</h5>
                    <div class="row text-center">
                        <div class="col-6 border-end border-secondary">
                            <i class="fas fa-blind fa-2x text-warning mb-2"></i>
                            <div class="h2 fw-bold text-white"><?php echo number_format($elderly_count); ?></div>
                            <small class="text-muted">ผู้สูงอายุ (60+)</small>
                        </div>
                        <div class="col-6">
                            <i class="fas fa-child fa-2x text-info mb-2"></i>
                            <div class="h2 fw-bold text-white"><?php echo number_format($child_count); ?></div>
                            <small class="text-muted">เด็ก (<15)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts & Details Row -->
        <div class="row g-4">
            <!-- Demographics (Gender) -->
            <div class="col-md-4">
                <div class="card-dark p-4 h-100">
                    <h5 class="stat-label text-white mb-4">DEMOGRAPHICS (เพศ)</h5>
                    
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <span class="text-info"><i class="fas fa-mars"></i> ชาย</span>
                        <span class="h4 mb-0 text-white"><?php echo number_format($male_count); ?></span>
                    </div>
                    <?php 
                        $total_gen = $male_count + $female_count;
                        $male_pct = ($total_gen > 0) ? ($male_count / $total_gen) * 100 : 0;
                    ?>
                    <div class="progress progress-dark mb-4">
                        <div class="progress-bar bg-info" style="width: <?php echo $male_pct; ?>%"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <span class="text-danger"><i class="fas fa-venus"></i> หญิง</span>
                        <span class="h4 mb-0 text-white"><?php echo number_format($female_count); ?></span>
                    </div>
                    <?php $female_pct = ($total_gen > 0) ? ($female_count / $total_gen) * 100 : 0; ?>
                    <div class="progress progress-dark">
                        <div class="progress-bar bg-danger" style="width: <?php echo $female_pct; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Top Shelters Capacity -->
            <div class="col-md-8">
                <div class="card-dark p-4 h-100">
                    <h5 class="stat-label text-white mb-4">SHELTER CAPACITY STATUS (TOP 6)</h5>
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle">
                            <thead>
                                <tr class="text-muted text-uppercase small">
                                    <th>ศูนย์พักพิง</th>
                                    <th class="text-end">จำนวนคน</th>
                                    <th class="text-end">ความจุ</th>
                                    <th style="width: 30%;">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($shelter_data) > 0): ?>
                                    <?php foreach ($shelter_data as $s): ?>
                                        <?php 
                                            $cap = $s['capacity'] > 0 ? $s['capacity'] : 1;
                                            $pct = ($s['current'] / $cap) * 100;
                                            $bar_color = ($pct >= 90) ? 'bg-danger' : (($pct >= 70) ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($s['name']); ?></td>
                                            <td class="text-end text-neon-blue fw-bold"><?php echo number_format($s['current']); ?></td>
                                            <td class="text-end text-muted"><?php echo number_format($s['capacity']); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress progress-dark flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo $pct; ?>%"></div>
                                                    </div>
                                                    <small class="<?php echo ($pct>=100)?'text-danger':'text-muted'; ?>">
                                                        <?php echo number_format($pct, 0); ?>%
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No Data Available</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // นาฬิกาดิจิตอล
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('th-TH', { hour12: false });
            document.getElementById('clock').innerText = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>