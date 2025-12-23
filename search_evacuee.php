<?php
// search_evacuee.php
// Refactored: เพิ่ม Security (XSS Prevention), Privacy Masking และปรับปรุง Query
require_once 'config/db.php';
require_once 'includes/functions.php'; 

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$results = [];

if ($keyword) {
    // ใช้ Parameter Binding ป้องกัน SQL Injection
    // ค้นหาครอบคลุม: เลขบัตร, ชื่อ, นามสกุล, เบอร์โทร
    $sql = "SELECT e.*, s.name as shelter_name, s.location as shelter_location, i.name as incident_name 
            FROM evacuees e
            LEFT JOIN shelters s ON e.shelter_id = s.id
            LEFT JOIN incidents i ON e.incident_id = i.id
            WHERE (e.id_card LIKE ? 
               OR e.first_name LIKE ? 
               OR e.last_name LIKE ? 
               OR e.phone LIKE ?)
            ORDER BY e.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $search_term = "%$keyword%";
    $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    $results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .search-box {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            padding: 40px;
            border-radius: 12px;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            text-align: center;
            margin-bottom: 30px;
        }
        
        .search-input-group {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .search-input {
            border-radius: 30px;
            padding: 15px 25px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-size: 1.1rem;
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 5px;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: bold;
        }

        .result-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            transition: all 0.2s;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            border-color: #cbd5e1;
        }

        .result-header {
            background-color: #f8fafc;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-body { padding: 15px; }

        .info-label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-size: 1rem;
            color: #0f172a;
            font-weight: 500;
        }
        
        /* Privacy Blur Effect (Optional: เอาเมาส์ชี้ถึงเห็นเลขบัตรเต็ม) */
        .id-card-mask:hover {
            cursor: pointer;
            color: #2563eb;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    
    <!-- Search Section -->
    <div class="search-box">
        <h2 class="fw-bold mb-2"><i class="fas fa-search me-2 text-warning"></i>ระบบค้นหาผู้ประสบภัย</h2>
        <p class="text-white-50 mb-4">ค้นหาด้วย ชื่อ-นามสกุล, เลขบัตรประชาชน หรือเบอร์โทรศัพท์</p>
        
        <form action="" method="GET" class="search-input-group">
            <input type="text" name="keyword" class="form-control search-input" 
                   placeholder="ระบุคำค้นหา..." value="<?php echo h($keyword); ?>" autofocus required>
            <button type="submit" class="btn btn-warning search-btn shadow-sm">
                <i class="fas fa-search"></i> ค้นหา
            </button>
        </form>
    </div>

    <!-- Results Section -->
    <?php if ($keyword): ?>
        <h5 class="mb-3 text-secondary fw-bold">
            ผลการค้นหา: <span class="text-dark">"<?php echo h($keyword); ?>"</span>
            <small class="text-muted fw-normal ms-2">(พบ <?php echo count($results); ?> รายการ)</small>
        </h5>

        <?php if (count($results) > 0): ?>
            <?php foreach ($results as $row): ?>
                <div class="result-card shadow-sm">
                    <div class="result-header">
                        <div>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-2 rounded-pill">
                                <i class="fas fa-layer-group me-1"></i> <?php echo h($row['incident_name']); ?>
                            </span>
                        </div>
                        <div class="text-muted small">
                            <i class="far fa-clock me-1"></i> ลงทะเบียนเมื่อ: <?php echo thaiDate(date('Y-m-d', strtotime($row['created_at']))); ?>
                        </div>
                    </div>
                    <div class="result-body">
                        <div class="row g-3">
                            <div class="col-md-3 border-end">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-light rounded-circle p-2 me-3 text-secondary">
                                        <i class="fas fa-user fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="info-label">ชื่อ-นามสกุล</div>
                                        <div class="info-value text-primary fw-bold">
                                            <!-- ใช้ h() ป้องกัน XSS -->
                                            <?php echo h($row['prefix']) . h($row['first_name']) . ' ' . h($row['last_name']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-muted small ms-5">
                                    <i class="far fa-id-card me-1"></i> 
                                    <!-- ใช้ Masking ปิดเลขบัตร -->
                                    <span class="id-card-mask" title="เลขบัตรเต็ม: <?php echo h($row['id_card']); ?>">
                                        <?php echo $row['id_card'] ? maskIDCard($row['id_card']) : '-'; ?>
                                    </span>
                                    <br>
                                    <i class="fas fa-phone me-1"></i> <?php echo h($row['phone'] ?: '-'); ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4 border-end">
                                <div class="info-label"><i class="fas fa-campground me-1"></i> สถานที่พักพิง</div>
                                <div class="info-value mb-1">
                                    <?php 
                                        if($row['stay_type'] == 'shelter') {
                                            echo h($row['shelter_name']);
                                        } else {
                                            echo '<span class="text-success"><i class="fas fa-tent"></i> พักนอกศูนย์/บ้านญาติ</span>';
                                        }
                                    ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i> 
                                    <?php 
                                        if($row['stay_type'] == 'shelter') echo h($row['shelter_location']);
                                        else echo h($row['stay_detail']);
                                    ?>
                                </small>
                            </div>

                            <div class="col-md-3 border-end">
                                <div class="info-label">สถานะปัจจุบัน</div>
                                <div class="mt-2">
                                    <?php if($row['check_out_date']): ?>
                                        <span class="badge bg-secondary px-3 py-2">
                                            <i class="fas fa-sign-out-alt me-1"></i> จำหน่ายออกแล้ว
                                        </span>
                                        <div class="small text-muted mt-1">เมื่อ: <?php echo thaiDate(date('Y-m-d', strtotime($row['check_out_date']))); ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="fas fa-bed me-1"></i> กำลังพักอาศัย
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-2 text-end d-flex align-items-center justify-content-end">
                                <!-- ส่ง mode=edit และ id ไป -->
                                <a href="evacuee_form.php?id=<?php echo $row['id']; ?>&mode=edit" class="btn btn-outline-secondary btn-sm me-2">
                                    <i class="fas fa-edit"></i> แก้ไข
                                </a>
                                <?php if($row['shelter_id']): ?>
                                    <a href="evacuee_list.php?shelter_id=<?php echo $row['shelter_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye"></i> ดูศูนย์
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning text-center py-5">
                <i class="fas fa-search-minus fa-3x mb-3 text-warning"></i><br>
                <h4>ไม่พบข้อมูลที่ค้นหา</h4>
                <p class="mb-0">กรุณาตรวจสอบคำสะกด หรือลองค้นหาด้วยคำสำคัญอื่น (ชื่อ, นามสกุล, เลขบัตร)</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- State: ยังไม่ได้ค้นหา -->
        <div class="text-center py-5 text-muted opacity-50">
            <i class="fas fa-search fa-4x mb-3"></i>
            <h5>กรอกคำค้นหาเพื่อเริ่มใช้งาน</h5>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>