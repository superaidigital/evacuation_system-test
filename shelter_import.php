<?php
// shelter_import.php
require_once 'config/db.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Security Check: เฉพาะ Admin หรือ Staff
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ดึงเหตุการณ์ที่ Active อยู่
$incidents = $pdo->query("SELECT * FROM incidents WHERE status = 'active' ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .import-card {
            border: none;
            border-top: 5px solid #10b981; /* Green Top */
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .step-circle {
            width: 30px;
            height: 30px;
            background-color: #e2e8f0;
            color: #64748b;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .step-active {
            background-color: #10b981;
            color: white;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="d-flex align-items-center mb-4">
                <h3 class="fw-bold text-dark mb-0">
                    <i class="fas fa-file-import text-success me-2"></i>นำเข้าข้อมูลศูนย์พักพิง (Import)
                </h3>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['swal_success'])): ?>
                <div class="alert alert-success d-flex align-items-center shadow-sm">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <strong>สำเร็จ!</strong> <?php echo $_SESSION['swal_success']; ?>
                    </div>
                </div>
                <?php unset($_SESSION['swal_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['swal_error'])): ?>
                <div class="alert alert-danger d-flex align-items-center shadow-sm">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <strong>เกิดข้อผิดพลาด!</strong> <?php echo $_SESSION['swal_error']; ?>
                    </div>
                </div>
                <?php unset($_SESSION['swal_error']); ?>
            <?php endif; ?>

            <div class="card import-card bg-white">
                <div class="card-body p-5">
                    
                    <form action="shelter_import_process.php" method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold d-flex align-items-center">
                                <span class="step-circle step-active">1</span> เลือกภารกิจ/เหตุการณ์
                            </label>
                            <select name="incident_id" class="form-select form-select-lg ms-5" style="width: auto; min-width: 300px;" required>
                                <?php if(count($incidents) > 0): ?>
                                    <?php foreach ($incidents as $inc): ?>
                                        <option value="<?php echo $inc['id']; ?>"><?php echo htmlspecialchars($inc['name']); ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled selected>-- ไม่มีภารกิจที่เปิดอยู่ --</option>
                                <?php endif; ?>
                            </select>
                            <?php if(count($incidents) == 0): ?>
                                <div class="text-danger ms-5 mt-1 small">* กรุณาเปิดภารกิจใหม่ก่อนนำเข้าข้อมูล</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold d-flex align-items-center">
                                <span class="step-circle step-active">2</span> เตรียมไฟล์ข้อมูล (CSV)
                            </label>
                            <div class="ms-5">
                                <p class="text-muted small mb-2">กรุณาดาวน์โหลดไฟล์ต้นฉบับ (Template) เพื่อกรอกข้อมูลให้ถูกต้องตามรูปแบบระบบ</p>
                                <a href="download_shelter_template.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-download me-1"></i> ดาวน์โหลด Template (.csv)
                                </a>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold d-flex align-items-center">
                                <span class="step-circle step-active">3</span> อัปโหลดไฟล์
                            </label>
                            <div class="ms-5">
                                <input type="file" name="csv_file" class="form-control form-control-lg" accept=".csv" required>
                                <div class="form-text">รองรับไฟล์นามสกุล .csv เท่านั้น (ขนาดไม่เกิน 2MB)</div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <a href="shelter_list.php" class="btn btn-light border px-4 me-2">ยกเลิก</a>
                            <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm">
                                <i class="fas fa-cloud-upload-alt me-2"></i> เริ่มนำเข้าข้อมูล
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>