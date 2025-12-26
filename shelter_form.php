<?php
// shelter_form.php
// ฟอร์มสำหรับเพิ่มและแก้ไขข้อมูลศูนย์พักพิง (รองรับ MySQLi + Leaflet Map)
session_start();
include('config/db.php');
include_once('includes/functions.php'); // เรียกใช้ functions สำหรับ CSRF (ถ้ามี)

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header('location: login.php');
    exit();
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// รองรับพารามิเตอร์ edit แบบเดิม (shelter_form.php?edit=1)
if (isset($_GET['edit'])) {
    $mode = 'edit';
    $id = intval($_GET['edit']);
}

// ค่าเริ่มต้น
$data = [
    'name' => '', 
    'location' => '', 
    'capacity' => '', 
    'contact_phone' => '', 
    'status' => 'Open', // ใช้ตัวพิมพ์ใหญ่ตาม Enum ใน DB (Open, Full, Closed)
    'incident_id' => '', 
    'latitude' => '', 
    'longitude' => '',
    'district' => '',
    'province' => '',
    'contact_person' => ''
];

// ดึงรายการภารกิจ (Incidents)
$incidents = [];
$incident_sql = "SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC";
// ตรวจสอบว่ามีตาราง incidents หรือไม่ (ป้องกัน Error กรณีเพิ่งเริ่มโปรเจกต์)
$check_table = $conn->query("SHOW TABLES LIKE 'incidents'");
if ($check_table && $check_table->num_rows > 0) {
    $inc_result = $conn->query($incident_sql);
    if ($inc_result) {
        $incidents = $inc_result->fetch_all(MYSQLI_ASSOC);
    }
}

// กรณีแก้ไข: ดึงข้อมูลเดิม
if ($mode == 'edit' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $fetched = $result->fetch_assoc();
        // Merge ข้อมูลเดิมเข้ากับค่าเริ่มต้น
        $data = array_merge($data, $fetched);
    } else {
        $_SESSION['error'] = "ไม่พบข้อมูลศูนย์พักพิง";
        header('location: shelter_list.php');
        exit();
    }
    $stmt->close();
}

// ฟังก์ชันสร้าง CSRF Token อย่างง่าย (ถ้าใน includes/functions.php ไม่มี)
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?php echo $mode == 'add' ? 'เพิ่มศูนย์พักพิง' : 'แก้ไขศูนย์พักพิง'; ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; background-color: #f4f6f9; }
        #map-picker {
            height: 350px;
            width: 100%;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            z-index: 1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .form-section-title {
            border-left: 5px solid #0d6efd;
            padding-left: 15px;
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 20px;
            font-size: 1.1rem;
            background-color: #f8f9fa;
            padding-top: 5px;
            padding-bottom: 5px;
            border-radius: 0 5px 5px 0;
        }
    </style>
</head>
<body>

<?php include('includes/header.php'); ?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas <?php echo $mode == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?> me-2"></i>
                        <?php echo $mode == 'add' ? 'เพิ่มศูนย์พักพิงใหม่' : 'แก้ไขข้อมูลศูนย์พักพิง'; ?>
                    </h5>
                    <a href="shelter_list.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>
                
                <div class="card-body p-4">
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="shelter_save.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- ส่ง id ไปทั้งแบบ name="id" และโหมด (เพื่อรองรับไฟล์ shelter_save.php เดิม) -->
                        <?php if ($mode == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <?php endif; ?>

                        <!-- ส่วนที่ 1: ข้อมูลทั่วไป -->
                        <div class="form-section-title">ข้อมูลทั่วไป</div>
                        
                        <!-- ตรวจสอบว่ามีตาราง incidents หรือไม่ ถ้ามีให้แสดง Dropdown -->
                        <?php if (!empty($incidents)): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ภารกิจภัยพิบัติ <span class="text-danger">*</span></label>
                            <select name="incident_id" class="form-select">
                                <option value="">-- เลือกภารกิจ --</option>
                                <?php foreach ($incidents as $inc): ?>
                                    <option value="<?php echo $inc['id']; ?>" <?php echo $data['incident_id'] == $inc['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($inc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">ชื่อศูนย์พักพิง <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($data['name']); ?>" placeholder="เช่น โรงเรียนบ้านหนองไผ่">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">สถานะศูนย์</label>
                                <select name="status" class="form-select">
                                    <option value="Open" <?php echo ($data['status'] == 'Open' || $data['status'] == 'open') ? 'selected' : ''; ?>>🟢 เปิดใช้งาน (Open)</option>
                                    <option value="Full" <?php echo ($data['status'] == 'Full' || $data['status'] == 'full') ? 'selected' : ''; ?>>🔴 เต็มแล้ว (Full)</option>
                                    <option value="Closed" <?php echo ($data['status'] == 'Closed' || $data['status'] == 'closed') ? 'selected' : ''; ?>>⚪ ปิด (Closed)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">ผู้ดูแล / ผู้ติดต่อ</label>
                                <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($data['contact_person']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">เบอร์ติดต่อ</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($data['contact_phone']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ความจุสูงสุด (คน) <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" class="form-control" required min="1" value="<?php echo $data['capacity']; ?>">
                        </div>

                        <!-- ส่วนที่ 2: ที่ตั้งและพิกัด -->
                        <div class="form-section-title mt-4">ที่ตั้งและพิกัดแผนที่ (GIS)</div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">รายละเอียดที่ตั้ง <span class="text-danger">*</span></label>
                            <textarea name="location" class="form-control" rows="2" required placeholder="เลขที่, หมู่บ้าน, ถนน..."><?php echo htmlspecialchars($data['location']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">อำเภอ</label>
                                <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($data['district']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">จังหวัด</label>
                                <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($data['province']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-primary"><i class="fas fa-map-marker-alt me-1"></i> ระบุพิกัดบนแผนที่</label>
                            <div class="text-muted small mb-2">ลากหมุดสีฟ้า 🔵 ไปยังตำแหน่งของศูนย์พักพิง หรือคลิกบนแผนที่</div>
                            
                            <!-- Map Container -->
                            <div id="map-picker" class="shadow-sm mb-2"></div>
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light">Lat</span>
                                        <input type="text" name="latitude" id="lat" class="form-control bg-white" readonly placeholder="Latitude" value="<?php echo $data['latitude']; ?>">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light">Lng</span>
                                        <input type="text" name="longitude" id="lng" class="form-control bg-white" readonly placeholder="Longitude" value="<?php echo $data['longitude']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="shelter_list.php" class="btn btn-secondary px-4">ยกเลิก</a>
                            <button type="submit" name="save_shelter" class="btn btn-primary px-4 fw-bold">
                                <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. ตั้งค่าพิกัดเริ่มต้น (Default Coordinates)
        // เริ่มต้นที่กึ่งกลางประเทศไทย หรือ กรุงเทพฯ ถ้ายังไม่มีข้อมูล
        var defaultLat = 13.7563; 
        var defaultLng = 100.5018; 
        var zoomLevel = 6;
        
        var curLat = document.getElementById('lat').value;
        var curLng = document.getElementById('lng').value;

        // ถ้ามีข้อมูลเดิม ให้ใช้ข้อมูลเดิม
        if (curLat && curLng && curLat != 0 && curLng != 0) {
            defaultLat = parseFloat(curLat);
            defaultLng = parseFloat(curLng);
            zoomLevel = 15; // ซูมเข้าไปใกล้ๆ
        } else {
             // พยายามดึงพิกัดปัจจุบันของผู้ใช้ (Browser Geolocation)
             if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    // ถ้ายังไม่ได้ปักหมุด ให้เลื่อนไปหา User
                    if(!curLat) {
                        var userLat = position.coords.latitude;
                        var userLng = position.coords.longitude;
                        map.setView([userLat, userLng], 13);
                        marker.setLatLng([userLat, userLng]);
                        updateInputs(userLat, userLng);
                    }
                });
            }
        }

        // 2. สร้างแผนที่
        var map = L.map('map-picker').setView([defaultLat, defaultLng], zoomLevel);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // 3. สร้างหมุด (Marker) ที่สามารถลากได้
        var marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);

        // 4. ฟังก์ชันอัปเดตค่าใน Input
        function updateInputs(lat, lng) {
            document.getElementById('lat').value = lat.toFixed(6);
            document.getElementById('lng').value = lng.toFixed(6);
        }

        // Event: เมื่อลากหมุดเสร็จ
        marker.on('dragend', function(e) {
            var coord = e.target.getLatLng();
            updateInputs(coord.lat, coord.lng);
            map.panTo(coord);
        });

        // Event: เมื่อคลิกบนแผนที่
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateInputs(e.latlng.lat, e.latlng.lng);
        });
        
        // อัปเดตครั้งแรกถ้ายังไม่มีค่า
        if (!curLat) {
            updateInputs(defaultLat, defaultLng);
        }
        
        // แก้ปัญหาแผนที่แสดงผลไม่เต็มกรอบเมื่อโหลดใน Modal หรือ Tab
        setTimeout(function(){ map.invalidateSize(); }, 400);
    });
</script>

</body>
</html>