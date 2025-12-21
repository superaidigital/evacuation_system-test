<?php
// import_csv.php
require_once 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// หา Active Incident เพื่อตั้งเป็นค่าเริ่มต้น
$stmt = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_incident = $stmt->fetch();

// ดึงรายชื่อศูนย์พักพิง
$shelters = [];
if ($active_incident) {
    $stmt_s = $pdo->prepare("SELECT id, name FROM shelters WHERE incident_id = ? ORDER BY name ASC");
    $stmt_s->execute([$active_incident['id']]);
    $shelters = $stmt_s->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .import-steps {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border-top: 5px solid #1a237e; /* Navy */
        }
        .step-item {
            display: flex;
            margin-bottom: 25px;
        }
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .step-active .step-icon {
            background-color: #fbbf24; /* Gold */
            color: #1e293b; /* Navy */
        }
        .step-content {
            padding-top: 5px;
            width: 100%;
        }
        .step-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-file-upload text-success me-2"></i>นำเข้าข้อมูลผู้ประสบภัย
            </h4>
            <span class="text-muted small">Import Evacuee Data (CSV)</span>
        </div>
        <a href="evacuee_list.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> กลับหน้ารายชื่อ
        </a>
    </div>

    <?php if (isset($_SESSION['swal_success'])): ?>
        <div class="alert alert-success shadow-sm border-success d-flex align-items-center mb-4">
            <i class="fas fa-check-circle fa-2x me-3"></i>
            <div>
                <h6 class="fw-bold mb-0">นำเข้าข้อมูลสำเร็จ!</h6>
                <small><?php echo $_SESSION['swal_success']; ?></small>
            </div>
        </div>
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['swal_error'])): ?>
        <div class="alert alert-danger shadow-sm border-danger d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
            <div>
                <h6 class="fw-bold mb-0">เกิดข้อผิดพลาด</h6>
                <small><?php echo $_SESSION['swal_error']; ?></small>
            </div>
        </div>
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="import-steps">
                <form action="import_process.php" method="POST" enctype="multipart/form-data">
                    
                    <!-- Step 1 -->
                    <div class="step-item step-active">
                        <div class="step-icon">1</div>
                        <div class="step-content">
                            <div class="step-title">เลือกภารกิจและศูนย์พักพิง</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">ภารกิจ (Incident)</label>
                                    <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($active_incident['name'] ?? 'ไม่มีภารกิจ Active'); ?>" readonly>
                                    <input type="hidden" name="incident_id" value="<?php echo $active_incident['id'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">นำเข้าสู่ศูนย์พักพิง (Destination)</label>
                                    <select name="shelter_id" class="form-select" required>
                                        <option value="" selected disabled>-- เลือกศูนย์พักพิง --</option>
                                        <?php foreach ($shelters as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-item step-active">
                        <div class="step-icon">2</div>
                        <div class="step-content">
                            <div class="step-title">เตรียมไฟล์ข้อมูล</div>
                            <p class="text-muted small mb-2">ดาวน์โหลดไฟล์ต้นฉบับ (Template) และกรอกข้อมูลตามรูปแบบที่กำหนด ห้ามเปลี่ยนหัวคอลัมน์</p>
                            <a href="download_template.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-1"></i> ดาวน์โหลด Template (.csv)
                            </a>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-item step-active">
                        <div class="step-icon">3</div>
                        <div class="step-content">
                            <div class="step-title">อัปโหลดไฟล์ CSV</div>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <div class="form-text">รองรับไฟล์ .csv (UTF-8) ขนาดไม่เกิน 5MB</div>
                        </div>
                    </div>

                    <hr class="mt-4 mb-4">

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm">
                            <i class="fas fa-file-import me-2"></i> เริ่มนำเข้าข้อมูล
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>