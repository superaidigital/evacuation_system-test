<?php
// report_shelter.php
// Refactored: Optimized Dashboard Reporting & Print Friendly
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$incident_id = filter_input(INPUT_GET, 'incident_id', FILTER_VALIDATE_INT);

// Default to latest active incident if not selected
if (!$incident_id) {
    $stmt = $pdo->query("SELECT id FROM incidents WHERE status='active' ORDER BY id DESC LIMIT 1");
    $incident_id = $stmt->fetchColumn();
}

// Fetch Incident Info
$incident_name = "ไม่พบข้อมูล";
if ($incident_id) {
    $stmt = $pdo->prepare("SELECT name FROM incidents WHERE id = ?");
    $stmt->execute([$incident_id]);
    $incident_name = $stmt->fetchColumn();
}

// Optimized Query: ดึงข้อมูลศูนย์ + จำนวนคน + กลุ่มเปราะบาง ใน Query เดียว (หรือ 2 เพื่อความง่าย)
$shelters = [];
if ($incident_id) {
    $sql = "SELECT s.id, s.name, s.location, s.capacity, s.contact_phone, s.status,
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as total_occupied,
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.gender='male') as male,
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.gender='female') as female,
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.age >= 60) as elderly,
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.age < 15) as children
            FROM shelters s
            WHERE s.incident_id = ?
            ORDER BY s.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$incident_id]);
    $shelters = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>รายงานสรุปสถานการณ์ศูนย์พักพิง</title>
    <!-- CSS and Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .page-header { background: white; padding: 20px; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .card-report { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table-custom thead th { background-color: #2c3e50; color: white; vertical-align: middle; }
        
        /* Print Styles */
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .card-report { box-shadow: none; border: 1px solid #ddd; }
            .table-custom thead th { background-color: #ddd !important; color: black !important; }
            a { text-decoration: none; color: black; }
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container-fluid mt-4">
    
    <!-- Controls (No Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3 class="fw-bold"><i class="fas fa-chart-pie me-2"></i>รายงานสรุปศูนย์พักพิง</h3>
        <div>
            <a href="export_process.php?type=shelters&incident_id=<?php echo $incident_id; ?>" class="btn btn-success me-2">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> พิมพ์รายงาน
            </button>
        </div>
    </div>

    <!-- Report Header -->
    <div class="card card-report p-4 text-center">
        <h4 class="fw-bold mb-2">รายงานสรุปข้อมูลผู้ประสบภัยและสถานะศูนย์พักพิง</h4>
        <h5 class="text-primary"><?php echo h($incident_name); ?></h5>
        <p class="text-muted mb-0">ข้อมูล ณ วันที่: <?php echo thaiDate(date('Y-m-d')); ?> เวลา <?php echo date('H:i'); ?> น.</p>
    </div>

    <!-- Summary Stats -->
    <?php 
        // Calculate Totals for Summary
        $sum_total = 0; $sum_cap = 0; $sum_male = 0; $sum_female = 0; $sum_elderly = 0; $sum_child = 0;
        foreach ($shelters as $s) {
            $sum_total += $s['total_occupied'];
            $sum_cap += $s['capacity'];
            $sum_male += $s['male'];
            $sum_female += $s['female'];
            $sum_elderly += $s['elderly'];
            $sum_child += $s['children'];
        }
    ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card card-report p-3 text-center border-start border-4 border-primary">
                <div class="text-muted small">ผู้พักพิงทั้งหมด</div>
                <div class="h2 fw-bold text-primary mb-0"><?php echo number_format($sum_total); ?></div>
                <small>คน</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-report p-3 text-center border-start border-4 border-info">
                <div class="text-muted small">ความจุรวมทั้งหมด</div>
                <div class="h2 fw-bold text-info mb-0"><?php echo number_format($sum_cap); ?></div>
                <small>ที่รองรับได้</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-report p-3 text-center border-start border-4 border-warning">
                <div class="text-muted small">ผู้สูงอายุ (60+)</div>
                <div class="h2 fw-bold text-warning mb-0"><?php echo number_format($sum_elderly); ?></div>
                <small>คน</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-report p-3 text-center border-start border-4 border-success">
                <div class="text-muted small">เด็ก (<15)</div>
                <div class="h2 fw-bold text-success mb-0"><?php echo number_format($sum_child); ?></div>
                <small>คน</small>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="card card-report">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-striped table-hover mb-0">
                    <thead class="text-center">
                        <tr>
                            <th rowspan="2">ชื่อศูนย์พักพิง</th>
                            <th rowspan="2">สถานะ</th>
                            <th colspan="3">จำนวนผู้เข้าพัก</th>
                            <th colspan="2">กลุ่มเปราะบาง</th>
                            <th rowspan="2">ความจุคงเหลือ</th>
                        </tr>
                        <tr>
                            <th>ชาย</th>
                            <th>หญิง</th>
                            <th>รวม</th>
                            <th>ผู้สูงอายุ</th>
                            <th>เด็ก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shelters)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลศูนย์พักพิงในภารกิจนี้</td></tr>
                        <?php else: ?>
                            <?php foreach ($shelters as $row): 
                                $occupancy = ($row['capacity'] > 0) ? ($row['total_occupied'] / $row['capacity']) * 100 : 0;
                                $status_badge = match($row['status']) {
                                    'open' => '<span class="badge bg-success">เปิด</span>',
                                    'full' => '<span class="badge bg-danger">เต็ม</span>',
                                    'closed' => '<span class="badge bg-secondary">ปิด</span>',
                                    default => '<span class="badge bg-secondary">'.$row['status'].'</span>'
                                };
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo h($row['name']); ?></div>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo h($row['location']); ?></small>
                                </td>
                                <td class="text-center"><?php echo $status_badge; ?></td>
                                <td class="text-center"><?php echo number_format($row['male']); ?></td>
                                <td class="text-center"><?php echo number_format($row['female']); ?></td>
                                <td class="text-center fw-bold bg-light"><?php echo number_format($row['total_occupied']); ?></td>
                                <td class="text-center text-warning fw-bold"><?php echo number_format($row['elderly']); ?></td>
                                <td class="text-center text-success fw-bold"><?php echo number_format($row['children']); ?></td>
                                <td class="text-center">
                                    <?php 
                                        $available = $row['capacity'] - $row['total_occupied'];
                                        echo ($available > 0) ? number_format($available) : '<span class="text-danger">0</span>';
                                    ?>
                                    <div class="progress mt-1" style="height: 3px;">
                                        <div class="progress-bar <?php echo ($occupancy > 90) ? 'bg-danger' : 'bg-success'; ?>" style="width: <?php echo $occupancy; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold text-center">
                        <tr>
                            <td colspan="2" class="text-end">รวมทั้งสิ้น</td>
                            <td><?php echo number_format($sum_male); ?></td>
                            <td><?php echo number_format($sum_female); ?></td>
                            <td><?php echo number_format($sum_total); ?></td>
                            <td><?php echo number_format($sum_elderly); ?></td>
                            <td><?php echo number_format($sum_child); ?></td>
                            <td><?php echo number_format($sum_cap - $sum_total); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>