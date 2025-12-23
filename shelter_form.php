<?php
// shelter_form.php
// Refactored: Added GPS Map Picker (Leaflet.js)
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$mode = $_GET['mode'] ?? 'add';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Init Data
$data = [
    'name' => '', 'location' => '', 'capacity' => '', 
    'contact_phone' => '', 'status' => 'open', 
    'incident_id' => '', 'latitude' => '', 'longitude' => ''
];

// Load Incident List
$incidents = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC")->fetchAll();

if ($mode == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) $data = $fetched;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title><?php echo $mode == 'add' ? 'เพิ่มศูนย์พักพิง' : 'แก้ไขศูนย์พักพิง'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map-picker {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            z-index: 1;
        }
        .form-section-title {
            border-left: 4px solid #0d6efd;
            padding-left: 10px;
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fas <?php echo $mode == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?> me-2"></i>
                        <?php echo $mode == 'add' ? 'เพิ่มศูนย์พักพิงใหม่' : 'แก้ไขข้อมูลศูนย์พักพิง'; ?>
                    </h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (isset($_SESSION['swal_error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['swal_error']; unset($_SESSION['swal_error']); ?></div>
                    <?php endif; ?>

                    <form action="shelter_save.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                        <?php if ($mode == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <?php endif; ?>

                        <!-- ส่วนที่ 1: ข้อมูลทั่วไป -->
                        <div class="form-section-title">ข้อมูลทั่วไป</div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">ภารกิจภัยพิบัติ <span class="text-danger">*</span></label>
                            <select name="incident_id" class="form-select" required>
                                <option value="">-- เลือกภารกิจ --</option>
                                <?php foreach ($incidents as $inc): ?>
                                    <option value="<?php echo $inc['id']; ?>" <?php echo $data['incident_id'] == $inc['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($inc['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ชื่อศูนย์พักพิง <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($data['name']); ?>" placeholder="เช่น โรงเรียนบ้านดอน, วัดป่า...">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">เบอร์ติดต่อประสานงาน</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($data['contact_phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">สถานะศูนย์</label>
                                <select name="status" class="form-select">
                                    <option value="open" <?php echo $data['status'] == 'open' ? 'selected' : ''; ?>>🟢 เปิดรับผู้พักพิง (Open)</option>
                                    <option value="full" <?php echo $data['status'] == 'full' ? 'selected' : ''; ?>>🔴 เต็มแล้ว (Full)</option>
                                    <option value="closed" <?php echo $data['status'] == 'closed' ? 'selected' : ''; ?>>⚪ ปิดศูนย์ (Closed)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">ความจุสูงสุด (คน) <span class="text-danger">*</span></label>
                            <input type="number" name="capacity" class="form-control" required min="1" value="<?php echo $data['capacity']; ?>">
                        </div>

                        <!-- ส่วนที่ 2: ที่ตั้งและพิกัด -->
                        <div class="form-section-title mt-4">ที่ตั้งและพิกัดแผนที่ (GIS)</div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">รายละเอียดที่ตั้ง</label>
                            <textarea name="location" class="form-control" rows="2" required placeholder="ระบุ หมู่ที่, ตำบล, อำเภอ หรือจุดสังเกตสำคัญ..."><?php echo htmlspecialchars($data['location']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-primary"><i class="fas fa-map-marker-alt me-1"></i> ระบุพิกัดบนแผนที่</label>
                            <div class="text-muted small mb-2">ลากหมุดสีฟ้า 🔵 ไปยังตำแหน่งของศูนย์พักพิง</div>
                            
                            <!-- Map Container -->
                            <div id="map-picker" class="shadow-sm mb-2"></div>
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" name="latitude" id="lat" class="form-control form-control-sm bg-light" readonly placeholder="Latitude" value="<?php echo $data['latitude']; ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="longitude" id="lng" class="form-control form-control-sm bg-light" readonly placeholder="Longitude" value="<?php echo $data['longitude']; ?>">
                                </div>
                            </div>
                            <div class="form-text">พิกัดจะถูกใช้แสดงผลใน War Room Dashboard</div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="shelter_list.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // 1. Setup Default Coordinates (Thailand Center or Previous Value)
    // Default: Bangkok Region
    var defaultLat = 13.7563; 
    var defaultLng = 100.5018; 
    
    var curLat = document.getElementById('lat').value;
    var curLng = document.getElementById('lng').value;

    if (curLat && curLng) {
        defaultLat = parseFloat(curLat);
        defaultLng = parseFloat(curLng);
    }

    // 2. Init Map
    var map = L.map('map-picker').setView([defaultLat, defaultLng], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // 3. Add Draggable Marker
    var marker = L.marker([defaultLat, defaultLng], {
        draggable: true
    }).addTo(map);

    // 4. Update Inputs on Drag
    function updateInputs(lat, lng) {
        document.getElementById('lat').value = lat.toFixed(6);
        document.getElementById('lng').value = lng.toFixed(6);
    }

    marker.on('dragend', function(e) {
        var coord = e.target.getLatLng();
        updateInputs(coord.lat, coord.lng);
        map.panTo(coord);
    });

    // 5. Click to Move Marker
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateInputs(e.latlng.lat, e.latlng.lng);
    });
    
    // Initial update if empty
    if (!curLat) {
        updateInputs(defaultLat, defaultLng);
    }
</script>

</body>
</html>