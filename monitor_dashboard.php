<?php
// monitor_dashboard.php
// Refactored: Optimized Queries for High Performance
require_once 'config/db.php';
require_once 'includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. Get Active Incident (Single Query)
$stmt = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$incident = $stmt->fetch();
$incident_id = $incident['id'] ?? 0;
$incident_name = $incident['name'] ?? 'Standby Mode (No Active Incident)';

// Init Data
$stats = [
    'total_evacuees' => 0,
    'total_shelters' => 0,
    'male' => 0, 'female' => 0,
    'elderly' => 0, 'children' => 0, 'disabled' => 0
];
$shelter_data = [];

if ($incident_id) {
    // 2. Aggregate Query (รวมทุกการนับไว้ในคำสั่งเดียว เพื่อลด Load Database)
    $sql_agg = "SELECT 
        COUNT(*) as total_evacuees,
        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female,
        SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
        SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as children,
        COUNT(DISTINCT shelter_id) as active_shelters_count
        FROM evacuees 
        WHERE incident_id = ? AND check_out_date IS NULL";
    
    $stmt_agg = $pdo->prepare($sql_agg);
    $stmt_agg->execute([$incident_id]);
    $result = $stmt_agg->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $stats['total_evacuees'] = $result['total_evacuees'];
        $stats['male'] = $result['male'];
        $stats['female'] = $result['female'];
        $stats['elderly'] = $result['elderly'];
        $stats['children'] = $result['children'];
    }

    // 3. Get Total Shelters Count (Opened)
    $stmt_sh = $pdo->prepare("SELECT COUNT(*) FROM shelters WHERE incident_id = ? AND status != 'closed'");
    $stmt_sh->execute([$incident_id]);
    $stats['total_shelters'] = $stmt_sh->fetchColumn();

    // 4. Get Top Shelters (Single Query with Join/Subquery optimized)
    $sql_top = "SELECT s.name, s.capacity, 
                (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current 
                FROM shelters s 
                WHERE s.incident_id = ? AND s.status != 'closed'
                ORDER BY current DESC LIMIT 6";
    $stmt_top = $pdo->prepare($sql_top);
    $stmt_top->execute([$incident_id]);
    $shelter_data = $stmt_top->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>War Room - ศูนย์บัญชาการเหตุการณ์</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&family=Chakra+Petch:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-color: #0b1120; /* Deep Navy */
            color: #e2e8f0;
            font-family: 'Prompt', sans-serif;
            overflow-x: hidden;
        }
        .font-tech { font-family: 'Chakra Petch', sans-serif; }
        
        /* Dashboard Cards */
        .card-monitor {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
        }
        
        .stat-value {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1;
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.5);
        }
        
        /* Neon Texts */
        .text-neon-blue { color: #38bdf8; }
        .text-neon-green { color: #4ade80; }
        .text-neon-red { color: #f87171; }
        .text-neon-gold { color: #facc15; }

        /* Progress Bars */
        .progress-dark { background-color: rgba(255,255,255,0.1); height: 8px; border-radius: 4px; }
        
        /* Top Bar */
        .warroom-header {
            background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
            border-bottom: 2px solid #ef4444; /* Alert Red Line */
            padding: 1rem 2rem;
        }
        
        .blink-dot {
            width: 10px; height: 10px; background: #ef4444; border-radius: 50%;
            display: inline-block; animation: blinker 1.5s linear infinite;
        }
        @keyframes blinker { 50% { opacity: 0; } }
    </style>
    
    <meta http-equiv="refresh" content="60"> <!-- Refresh every 60s -->
</head>
<body>

    <!-- Header -->
    <div class="warroom-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <h3 class="mb-0 fw-bold font-tech text-white"><i class="fas fa-satellite-dish me-2"></i> WAR ROOM</h3>
            <div class="d-flex align-items-center px-3 py-1 rounded bg-danger bg-opacity-20 border border-danger text-danger">
                <span class="blink-dot me-2"></span>
                <span class="fw-bold text-uppercase" style="letter-spacing: 1px;"><?php echo h($incident_name); ?></span>
            </div>
        </div>
        <div class="text-end">
            <div class="h4 mb-0 font-tech" id="clock">00:00:00</div>
            <div class="small text-secondary"><?php echo date('d F Y'); ?></div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid p-4">
        
        <!-- Summary Row -->
        <div class="row g-4 mb-4">
            <!-- Total People -->
            <div class="col-md-3">
                <div class="card-monitor p-4 h-100 text-center position-relative overflow-hidden">
                    <div class="text-secondary text-uppercase small fw-bold mb-2">Total Evacuees</div>
                    <div class="stat-value text-neon-blue font-tech"><?php echo number_format($stats['total_evacuees']); ?></div>
                    <div class="mt-2 text-muted small"><i class="fas fa-users me-1"></i> ผู้พักพิงในระบบปัจจุบัน</div>
                </div>
            </div>
            
            <!-- Shelters -->
            <div class="col-md-3">
                <div class="card-monitor p-4 h-100 text-center">
                    <div class="text-secondary text-uppercase small fw-bold mb-2">Active Shelters</div>
                    <div class="stat-value text-neon-green font-tech"><?php echo number_format($stats['total_shelters']); ?></div>
                    <div class="mt-2 text-muted small"><i class="fas fa-campground me-1"></i> ศูนย์เปิดให้บริการ</div>
                </div>
            </div>

            <!-- Vulnerable Stats -->
            <div class="col-md-6">
                <div class="card-monitor p-4 h-100">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="text-neon-gold fw-bold text-uppercase"><i class="fas fa-exclamation-triangle"></i> Vulnerable Groups</div>
                        <div class="small text-muted">กลุ่มเปราะบางที่ต้องดูแลพิเศษ</div>
                    </div>
                    <div class="row text-center mt-3">
                        <div class="col-6 border-end border-secondary">
                            <div class="h1 mb-0 font-tech text-white"><?php echo number_format($stats['elderly']); ?></div>
                            <small class="text-secondary">ผู้สูงอายุ (60+)</small>
                        </div>
                        <div class="col-6">
                            <div class="h1 mb-0 font-tech text-white"><?php echo number_format($stats['children']); ?></div>
                            <small class="text-secondary">เด็ก (<15)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Row -->
        <div class="row g-4">
            <!-- Demographics -->
            <div class="col-lg-4">
                <div class="card-monitor p-4 h-100">
                    <h5 class="text-white font-tech mb-4 border-bottom border-secondary pb-2">DEMOGRAPHICS</h5>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-info"><i class="fas fa-mars"></i> Male</span>
                            <span class="text-white fw-bold"><?php echo number_format($stats['male']); ?></span>
                        </div>
                        <?php 
                            $total_gen = $stats['male'] + $stats['female'];
                            $m_pct = ($total_gen > 0) ? ($stats['male'] / $total_gen) * 100 : 0;
                        ?>
                        <div class="progress progress-dark">
                            <div class="progress-bar bg-info" style="width: <?php echo $m_pct; ?>%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-danger"><i class="fas fa-venus"></i> Female</span>
                            <span class="text-white fw-bold"><?php echo number_format($stats['female']); ?></span>
                        </div>
                        <div class="progress progress-dark">
                            <div class="progress-bar bg-danger" style="width: <?php echo 100 - $m_pct; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Shelters Table -->
            <div class="col-lg-8">
                <div class="card-monitor p-4 h-100">
                    <h5 class="text-white font-tech mb-4 border-bottom border-secondary pb-2">TOP SHELTER OCCUPANCY</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-borderless table-sm mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-secondary text-uppercase small">
                                    <th>Shelter Name</th>
                                    <th class="text-end">Occupancy</th>
                                    <th class="text-center" style="width: 100px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($shelter_data)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No data available</td></tr>
                                <?php else: ?>
                                    <?php foreach($shelter_data as $s): 
                                        $cap = $s['capacity'] > 0 ? $s['capacity'] : 1;
                                        $pct = ($s['current'] / $cap) * 100;
                                        $color = $pct > 90 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <tr class="align-middle">
                                        <td class="py-2">
                                            <div class="fw-bold text-white"><?php echo h($s['name']); ?></div>
                                            <div class="progress progress-dark mt-1" style="height: 4px;">
                                                <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bold text-white fs-5"><?php echo number_format($s['current']); ?></span>
                                            <span class="text-muted small">/ <?php echo number_format($s['capacity']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $color; ?> bg-opacity-75"><?php echo number_format($pct, 0); ?>%</span>
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

    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString('th-TH');
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>