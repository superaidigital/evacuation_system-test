<?php
// report_print.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // เรียกใช้ thaiDate()
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// รับค่า incident_id
$incident_id = isset($_GET['incident_id']) ? $_GET['incident_id'] : '';

if (!$incident_id) {
    // ถ้าไม่ส่งมา ให้เอา Active อันล่าสุด
    $stmt = $pdo->query("SELECT id FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $active = $stmt->fetch();
    $incident_id = $active ? $active['id'] : 0;
}

if (!$incident_id) die("ไม่พบข้อมูลเหตุการณ์");

// ดึงข้อมูลเหตุการณ์
$stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
$stmt->execute([$incident_id]);
$incident = $stmt->fetch();

// --- QUERY STATS ---

// 1. สรุปยอดรวม
$sql_summary = "SELECT 
    COUNT(*) as total_evacuees,
    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN age < 15 THEN 1 ELSE 0 END) as child,
    SUM(CASE WHEN age >= 60 THEN 1 ELSE 0 END) as elderly,
    COUNT(DISTINCT shelter_id) as total_shelters
    FROM evacuees 
    WHERE incident_id = ? AND check_out_date IS NULL";
$stmt = $pdo->prepare($sql_summary);
$stmt->execute([$incident_id]);
$summary = $stmt->fetch();

// 2. ข้อมูลรายศูนย์พักพิง (เรียงตามจำนวนคน)
$sql_shelters = "SELECT s.name, s.location, s.contact_phone, s.capacity,
    (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_people,
    (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.gender='male') as male,
    (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL AND e.gender='female') as female
    FROM shelters s 
    WHERE s.incident_id = ? AND s.status != 'closed'
    ORDER BY current_people DESC";
$stmt = $pdo->prepare($sql_shelters);
$stmt->execute([$incident_id]);
$shelters = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปสถานการณ์ - <?php echo htmlspecialchars($incident['name']); ?></title>
    <!-- Google Fonts: Sarabun (ฟอนต์สารบัญ สำหรับเอกสารราชการ) -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            color: #000;
            background: #fff; 
            padding: 20px;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.2;
        }
        
        .report-title {
            font-size: 24px;
            font-weight: bold;
        }
        
        .report-subtitle {
            font-size: 18px;
            font-weight: normal;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        /* ตารางแบบขาว-ดำ สำหรับพิมพ์ */
        .table-print {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table-print th, .table-print td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
            vertical-align: middle;
        }
        .table-print th {
            background-color: #f0f0f0;
            text-align: center;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }

        .signature-section {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            float: right;
            width: 300px;
        }
        
        /* ซ่อนปุ่มเมื่อสั่งพิมพ์ */
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .card { border: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

    <!-- ปุ่มควบคุม (ไม่แสดงตอนพิมพ์) -->
    <div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 rounded border">
        <div>
            <a href="report.php" class="btn btn-secondary">ย้อนกลับ</a>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary fw-bold">สั่งพิมพ์รายงาน (Print)</button>
        </div>
    </div>

    <!-- ส่วนหัวรายงาน -->
    <div class="report-header">
        <!-- ใส่ตราครุฑ หรือโลโก้หน่วยงานตรงนี้ได้ -->
        <!-- <img src="assets/img/garuda.png" width="60" class="mb-3"> -->
        <div class="report-title">รายงานสรุปสถานการณ์ภัยพิบัติ</div>
        <div class="report-subtitle">ศูนย์บัญชาการเหตุการณ์: <?php echo htmlspecialchars($incident['name']); ?></div>
    </div>

    <div class="meta-info">
        <div>
            <strong>ประเภทภัย:</strong> <?php echo ucfirst($incident['type']); ?><br>
            <strong>สถานะ:</strong> <?php echo ($incident['status'] == 'active') ? 'กำลังดำเนินการ' : 'จบภารกิจ'; ?>
        </div>
        <div class="text-end">
            <strong>วันที่พิมพ์รายงาน:</strong> <?php echo thaiDate(date('Y-m-d')); ?> เวลา <?php echo date('H:i'); ?> น.<br>
            <strong>ผู้พิมพ์:</strong> <?php echo htmlspecialchars($_SESSION['fullname']); ?>
        </div>
    </div>

    <!-- ส่วนที่ 1: สถิติรวม -->
    <h5 class="fw-bold mb-3">1. ข้อมูลสรุปภาพรวม</h5>
    <table class="table-print">
        <tr>
            <th width="25%">จำนวนผู้ประสบภัยรวม</th>
            <th width="25%">ชาย</th>
            <th width="25%">หญิง</th>
            <th width="25%">ศูนย์พักพิงที่เปิด</th>
        </tr>
        <tr style="font-size: 1.2rem;">
            <td class="text-center fw-bold"><?php echo number_format($summary['total_evacuees']); ?> คน</td>
            <td class="text-center"><?php echo number_format($summary['male']); ?></td>
            <td class="text-center"><?php echo number_format($summary['female']); ?></td>
            <td class="text-center"><?php echo number_format($summary['total_shelters']); ?> แห่ง</td>
        </tr>
    </table>

    <table class="table-print" style="width: 50%;">
        <tr>
            <th colspan="2">กลุ่มเปราะบาง</th>
        </tr>
        <tr>
            <td>เด็ก (ต่ำกว่า 15 ปี)</td>
            <td class="text-end"><?php echo number_format($summary['child']); ?> คน</td>
        </tr>
        <tr>
            <td>ผู้สูงอายุ (60 ปีขึ้นไป)</td>
            <td class="text-end"><?php echo number_format($summary['elderly']); ?> คน</td>
        </tr>
    </table>

    <!-- ส่วนที่ 2: รายละเอียดรายศูนย์ -->
    <h5 class="fw-bold mb-3 mt-4">2. รายละเอียดศูนย์พักพิง</h5>
    <table class="table-print">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th>ชื่อศูนย์พักพิง</th>
                <th width="25%">ที่ตั้ง</th>
                <th width="15%">เบอร์ติดต่อ</th>
                <th width="10%">ความจุ</th>
                <th width="10%">ยอดปัจจุบัน</th>
                <th width="10%">คงเหลือ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            foreach ($shelters as $row): 
                $balance = $row['capacity'] - $row['current_people'];
            ?>
            <tr>
                <td class="text-center"><?php echo $i++; ?></td>
                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['location']); ?></td>
                <td class="text-center"><?php echo $row['contact_phone'] ?: '-'; ?></td>
                <td class="text-center"><?php echo number_format($row['capacity']); ?></td>
                <td class="text-center fw-bold"><?php echo number_format($row['current_people']); ?></td>
                <td class="text-center"><?php echo number_format($balance); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(count($shelters) == 0): ?>
            <tr>
                <td colspan="7" class="text-center py-3">ไม่มีข้อมูลศูนย์พักพิง</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ส่วนท้าย: ลงนาม -->
    <div class="signature-section">
        <div class="signature-box">
            <p>ขอรับรองว่าข้อมูลข้างต้นเป็นความจริง</p>
            <br><br><br>
            <p>ลงชื่อ ...........................................................</p>
            <p>(...........................................................)</p>
            <p>ตำแหน่ง ...........................................................</p>
            <p>วันที่ ............/............/............</p>
        </div>
    </div>

</body>
</html>