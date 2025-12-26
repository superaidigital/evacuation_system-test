<?php
/**
 * family_finder.php
 * หน้าสำหรับประชาชนทั่วไป (Public Access)
 * - สืบค้นรายชื่อญาติ (แสดงข้อมูลแบบจำกัด)
 * - ดูประกาศล่าสุดจากศูนย์บัญชาการ
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

// ใช้ MySQLi ตามความต้องการล่าสุด
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($search_query && strlen($search_query) >= 2) {
    // ค้นหาเฉพาะผู้ที่ยังพักอยู่ (check_out_date IS NULL) และดึงชื่อศูนย์พักพิง
    // ใช้ LIKE เพื่อให้ค้นหาได้ยืดหยุ่น
    $sql = "SELECT e.first_name, e.last_name, e.gender, e.age, s.name as shelter_name, s.location
            FROM evacuees e
            JOIN shelters s ON e.shelter_id = s.id
            WHERE (e.first_name LIKE ? OR e.last_name LIKE ? OR e.id_card LIKE ?)
            AND e.check_out_date IS NULL
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $search_term = "%$search_query%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ดึงประกาศล่าสุด 3 รายการ
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์ข้อมูลประชาชน - Disaster Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f1f5f9; }
        .hero-section { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 60px 0; border-bottom: 5px solid #fbbf24; }
        .search-card { margin-top: -40px; border-radius: 15px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .announcement-item { border-left: 4px solid #fbbf24; transition: transform 0.2s; }
        .announcement-item:hover { transform: translateX(5px); }
    </style>
</head>
<body>

<div class="hero-section text-center">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3"><i class="fas fa-search-location me-2"></i>ศูนย์สืบค้นข้อมูลประชาชน</h1>
        <p class="lead opacity-75">ค้นหาตำแหน่งที่พักของญาติพี่น้องและติดตามประกาศสำคัญจากภาครัฐ</p>
    </div>
</div>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Search Card -->
            <div class="card search-card mb-5">
                <div class="card-body p-4">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-9">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="q" class="form-control border-start-0" 
                                       placeholder="ระบุชื่อ, นามสกุล หรือเลขบัตรฯ..." value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">ค้นหา</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- News & Announcements -->
            <div class="row g-4">
                <!-- ข่าวสาร -->
                <div class="col-md-5">
                    <h5 class="fw-bold mb-3"><i class="fas fa-bullhorn text-danger me-2"></i>ประกาศล่าสุด</h5>
                    <?php if ($announcements): ?>
                        <?php foreach($announcements as $news): ?>
                            <div class="card announcement-item shadow-sm mb-3">
                                <div class="card-body p-3">
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($news['title']); ?></h6>
                                    <p class="small text-muted mb-2 text-truncate"><?php echo strip_tags($news['content']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark small" style="font-size: 0.65rem;">
                                            <?php echo date('d M Y', strtotime($news['created_at'])); ?>
                                        </span>
                                        <a href="#" class="btn btn-sm btn-link p-0 text-decoration-none">อ่านต่อ..</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small p-4 text-center border rounded bg-white">ไม่มีประกาศใหม่ในขณะนี้</div>
                    <?php endif; ?>
                </div>

                <!-- ผลการค้นหา -->
                <div class="col-md-7">
                    <h5 class="fw-bold mb-3"><i class="fas fa-users text-primary me-2"></i>ผลการค้นหาญาติ</h5>
                    <?php if ($search_query): ?>
                        <?php if ($results): ?>
                            <?php foreach($results as $person): ?>
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="fw-bold mb-1">
                                                คุณ <?php echo htmlspecialchars($person['first_name'] . ' ' . mb_substr($person['last_name'], 0, 2) . 'xxx'); ?>
                                            </h6>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle">พักพิงอยู่</span>
                                        </div>
                                        <div class="small text-muted mt-2">
                                            <i class="fas fa-hospital me-1"></i> <strong>ศูนย์:</strong> <?php echo htmlspecialchars($person['shelter_name']); ?><br>
                                            <i class="fas fa-map-marker-alt me-1"></i> <strong>พิกัด:</strong> <?php echo htmlspecialchars($person['location']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-light border text-center p-5 rounded-4">
                                <i class="fas fa-user-slash fa-3x text-light mb-3"></i>
                                <p class="mb-0 text-muted">ไม่พบข้อมูลผู้พักพิงตามที่ระบุ<br><small>กรุณาตรวจสอบชื่อ-นามสกุลอีกครั้ง</small></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info border-0 shadow-sm small rounded-4">
                            <i class="fas fa-info-circle me-2"></i>กรุณาระบุชื่อหรือนามสกุลอย่างน้อย 2 ตัวอักษรเพื่อเริ่มต้นการค้นหา
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center mt-5 mb-5">
                <p class="text-muted small">ต้องการความช่วยเหลือด่วน? ติดต่อสายด่วนนิรภัย 1784</p>
                <a href="login.php" class="btn btn-sm btn-outline-secondary">สำหรับเจ้าหน้าที่</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>