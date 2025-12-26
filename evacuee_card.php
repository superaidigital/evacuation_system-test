<?php
/**
 * evacuee_card.php
 * หน้าสำหรับสร้างบัตรประจำตัวผู้อพยพพร้อม QR Code (Print-friendly)
 * ปรับปรุง: รองรับ MySQLi, ระบบตรวจสอบอายุอัจฉริยะ และดีไซน์ Modern Navy
 */

require_once 'config/db.php';
require_once 'includes/functions.php';

// 1. รับค่า ID และตรวจสอบความถูกต้อง (Security Check)
$evacuee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($evacuee_id <= 0) {
    die("<div class='container mt-5 text-center'><h3>ไม่พบรหัสผู้ประสบภัย</h3><a href='index.php' class='btn btn-primary'>กลับหน้าหลัก</a></div>");
}

try {
    // 2. ดึงข้อมูลผู้อพยพและศูนย์พักพิง (MySQLi Prepared Statement)
    $sql = "SELECT e.*, s.name as shelter_name, s.location as shelter_loc 
            FROM evacuees e 
            LEFT JOIN shelters s ON e.shelter_id = s.id 
            WHERE e.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $evacuee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $person = $result->fetch_assoc();

    if (!$person) {
        die("<div class='container mt-5 text-center'><h3>ไม่พบข้อมูลในระบบ</h3><a href='index.php'>กลับหน้าหลัก</a></div>");
    }

    // 3. เตรียมข้อมูล QR Code (รูปแบบมาตรฐาน EVAC-ID สำหรับสแกนเนอร์)
    $qr_data = "EVAC-" . $person['id'];
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

    // 4. ระบบจัดการข้อมูลอายุ (Smart Age Fallback)
    $display_age = 'ไม่ระบุ';
    if (!empty($person['age']) && $person['age'] > 0) {
        $display_age = $person['age'] . " ปี";
    } elseif (!empty($person['birth_date']) && $person['birth_date'] != '0000-00-00') {
        $display_age = calculateAge($person['birth_date']) . " ปี";
    }

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card - <?php echo h($person['first_name']); ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Font: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { background-color: #f1f5f9; font-family: 'Prompt', sans-serif; padding-top: 50px; }

        /* Media Print: ซ่อนส่วนที่ไม่จำเป็นเมื่อพิมพ์ */
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding-top: 0; }
            .id-card { box-shadow: none !important; border: 1px solid #ddd !important; margin: 20px auto !important; }
        }

        /* ตกแต่งตัวบัตร (ID Card Container) */
        .id-card {
            width: 450px;
            height: 280px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin: 0 auto;
            display: flex;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* ส่วนสีน้ำเงินด้านข้าง (Branding Section) */
        .card-brand {
            width: 150px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 15px;
        }

        .qr-frame {
            background: white;
            padding: 8px;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .qr-frame img { width: 110px; height: 110px; }

        .id-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #fbbf24;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ส่วนข้อมูลหลัก (Content Section) */
        .card-info {
            flex: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .sys-title {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            color: #cbd5e1;
            text-transform: uppercase;
            text-align: right;
        }

        .field-label {
            font-size: 0.65rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .field-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .shelter-badge {
            font-size: 0.85rem;
            color: #2563eb;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .vulnerable-tag {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: #fffbeb;
            color: #92400e;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            border: 1px solid #fde68a;
        }

        .btn-group-print { margin-bottom: 30px; }
    </style>
</head>
<body>

    <!-- ปุ่มควบคุม (ซ่อนเมื่อพิมพ์) -->
    <div class="container text-center no-print btn-group-print">
        <div class="btn-group shadow-sm">
            <button onclick="window.print()" class="btn btn-primary px-4 fw-bold">
                <i class="fas fa-print me-2"></i>พิมพ์บัตรประจำตัว
            </button>
            <a href="evacuee_list.php?shelter_id=<?php echo $person['shelter_id']; ?>" class="btn btn-light px-4 border">
                กลับหน้ารายชื่อ
            </a>
        </div>
        <p class="mt-3 text-muted small"><i class="fas fa-info-circle me-1"></i> แนะนำการพิมพ์: ปรับ Layout เป็นแนวตั้ง และเปิด 'Background Graphics'</p>
    </div>

    <!-- โครงสร้างบัตร ID Card -->
    <div class="id-card">
        <!-- ฝั่งซ้าย: QR และ Branding -->
        <div class="card-brand">
            <div class="qr-frame">
                <img src="<?php echo $qr_url; ?>" alt="QR Code">
            </div>
            <div class="id-label"><?php echo $qr_data; ?></div>
            <div style="font-size: 0.5rem; opacity: 0.5; margin-top: 5px;">OFFICIAL IDENTIFICATION</div>
        </div>

        <!-- ฝั่งขวา: ข้อมูลบุคคล -->
        <div class="card-info">
            <div class="sys-title">
                <i class="fas fa-shield-alt text-warning"></i> DMS EVACUATION
            </div>

            <div class="mt-2">
                <p class="field-label">ชื่อ-นามสกุล (Full Name)</p>
                <div class="field-value"><?php echo h($person['first_name'] . ' ' . $person['last_name']); ?></div>
                
                <p class="field-label">ศูนย์พักพิง (Shelter Site)</p>
                <div class="shelter-badge">
                    <i class="fas fa-hospital-user me-1"></i> <?php echo h($person['shelter_name'] ?: 'ไม่ระบุศูนย์'); ?>
                </div>

                <div class="row mt-auto">
                    <div class="col-6">
                        <p class="field-label">เพศ/อายุ (Gender/Age)</p>
                        <div class="fw-bold small">
                            <?php echo ($person['gender'] == 'male' ? 'ชาย' : 'หญิง'); ?> / <?php echo $display_age; ?>
                        </div>
                    </div>
                    <div class="col-6 border-start ps-3">
                        <p class="field-label">วันที่เข้าพัก (Check-in)</p>
                        <div class="fw-bold small">
                            <?php echo date('d/m/Y', strtotime($person['check_in_date'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- แสดงสถานะกลุ่มเปราะบาง (ถ้ามี) -->
            <?php if (!empty($person['vulnerable_group'])): ?>
                <div class="vulnerable-tag shadow-sm">
                    <i class="fas fa-heart text-danger me-1"></i> <?php echo h($person['vulnerable_group']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ส่วนแนะนำเพิ่มเติม -->
    <div class="container mt-5 no-print">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="alert alert-light border-0 shadow-sm rounded-4 p-4">
                    <h6 class="fw-bold text-dark"><i class="fas fa-qrcode text-primary me-2"></i>การใช้งานบัตร</h6>
                    <p class="small text-muted mb-0">
                        1. บัตรนี้ใช้เพื่อยืนยันตัวตนภายในศูนย์พักพิง<br>
                        2. เจ้าหน้าที่สามารถสแกน QR Code เพื่อบันทึกการรับถุงยังชีพ<br>
                        3. แนะนำให้ติดภาพถ่ายจริง (หากมี) เพื่อการระบุตัวตนที่ชัดเจน
                    </p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>