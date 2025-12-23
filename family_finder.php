<?php
// family_finder.php
// ระบบติดตามญาติ (Public Access) - ออกแบบตามหลัก PDPA
require_once 'config/db.php';
require_once 'includes/functions.php';

// ไม่ต้องเช็ค Login เพราะเป็นหน้า Public

$fname = isset($_GET['fname']) ? trim($_GET['fname']) : '';
$lname = isset($_GET['lname']) ? trim($_GET['lname']) : '';
$results = [];
$searched = false;

if ($fname && $lname) {
    $searched = true;
    // Query: ค้นหาเฉพาะคนที่ยังพักอยู่ (check_out_date IS NULL)
    // การค้นหาต้องตรงเป๊ะ (Exact Match) เพื่อป้องกันการสุ่มชื่อ
    $sql = "SELECT e.first_name, e.last_name, e.gender, e.age, e.created_at, 
            s.name as shelter_name, s.contact_phone as shelter_contact, i.name as incident_name
            FROM evacuees e
            JOIN shelters s ON e.shelter_id = s.id
            JOIN incidents i ON e.incident_id = i.id
            WHERE e.first_name = ? AND e.last_name = ? AND e.check_out_date IS NULL AND i.status = 'active'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fname, $lname]);
    $results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>ระบบติดตามญาติและผู้สูญหาย - Disaster Response</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f0f2f5; }
        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .search-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: -50px;
        }
        .status-card {
            border-left: 5px solid #10b981;
            transition: transform 0.2s;
        }
        .status-card:hover { transform: translateY(-3px); }
        .privacy-notice { font-size: 0.85rem; color: #64748b; }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-shield-alt me-2"></i>ระบบบริหารจัดการภัยพิบัติ</a>
            <div class="ms-auto">
                <a href="login.php" class="btn btn-outline-light btn-sm">สำหรับเจ้าหน้าที่</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section text-center">
        <div class="container">
            <h1 class="fw-bold mb-3">ตรวจสอบสถานะญาติ / ผู้ประสบภัย</h1>
            <p class="lead text-white-50">"เราช่วยคุณค้นหา เพื่อความอุ่นใจของครอบครัว"</p>
        </div>
    </div>

    <!-- Search Section -->
    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- Form Card -->
                <div class="search-card mb-5">
                    <form action="" method="GET">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label fw-bold">ชื่อจริง (ไม่ต้องมีคำนำหน้า)</label>
                                <input type="text" name="fname" class="form-control form-control-lg" placeholder="เช่น สมชาย" required value="<?php echo htmlspecialchars($fname); ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">นามสกุล</label>
                                <input type="text" name="lname" class="form-control form-control-lg" placeholder="เช่น ใจดี" required value="<?php echo htmlspecialchars($lname); ?>">
                            </div>
                            <div class="col-md-2 d-grid">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="fas fa-search"></i> ค้นหา</button>
                            </div>
                        </div>
                        <div class="mt-3 text-center privacy-notice">
                            <i class="fas fa-user-shield me-1"></i> ระบบจะแสดงเฉพาะข้อมูลสถานะความปลอดภัยและสถานที่พักพิง ตามนโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA)
                        </div>
                    </form>
                </div>

                <!-- Results -->
                <?php if ($searched): ?>
                    <?php if (count($results) > 0): ?>
                        <h4 class="mb-4 text-center fw-bold text-success"><i class="fas fa-check-circle me-2"></i>พบข้อมูลในระบบ</h4>
                        
                        <?php foreach ($results as $row): ?>
                        <div class="card status-card shadow-sm mb-3">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h4 class="fw-bold text-dark mb-1">
                                            คุณ<?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?>
                                        </h4>
                                        <div class="text-muted mb-3">
                                            <span class="badge bg-secondary me-2">อายุ <?php echo $row['age']; ?> ปี</span>
                                            <span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['incident_name']); ?></span>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="icon-box bg-success text-white rounded-circle p-2 me-3">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">สถานะปัจจุบัน</div>
                                                <div class="fw-bold text-success">ปลอดภัย (ลงทะเบียนแล้ว)</div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center">
                                            <div class="icon-box bg-primary text-white rounded-circle p-2 me-3">
                                                <i class="fas fa-campground"></i>
                                            </div>
                                            <div>
                                                <div class="small text-muted">สถานที่พักพิง</div>
                                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($row['shelter_name']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-center border-start mt-3 mt-md-0">
                                        <p class="small text-muted mb-2">ต้องการติดต่อศูนย์พักพิง?</p>
                                        <h5 class="fw-bold text-dark mb-2"><i class="fas fa-phone-alt me-2"></i><?php echo htmlspecialchars($row['shelter_contact']); ?></h5>
                                        <div class="small text-muted">ข้อมูล ณ: <?php echo thaiDate(date('Y-m-d', strtotime($row['created_at']))); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div class="alert alert-warning text-center py-5 shadow-sm rounded-3">
                            <i class="fas fa-search-minus fa-4x mb-3 text-warning opacity-50"></i>
                            <h4>ไม่พบข้อมูล "คุณ<?php echo htmlspecialchars($fname . ' ' . $lname); ?>"</h4>
                            <p class="text-muted">
                                บุคคลนี้อาจยังไม่ได้ลงทะเบียนเข้าศูนย์พักพิง หรือ ชื่อ-นามสกุล สะกดไม่ตรงกับบัตรประชาชน<br>
                                กรุณาตรวจสอบตัวสะกดอีกครั้ง หรือติดต่อสายด่วน <strong>1784</strong>
                            </p>
                            <a href="family_finder.php" class="btn btn-outline-warning mt-2">ค้นหาใหม่</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container small">
            &copy; <?php echo date('Y'); ?> ระบบบริหารจัดการศูนย์พักพิงชั่วคราว. All Rights Reserved.
        </div>
    </footer>

</body>
</html>