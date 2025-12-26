<?php
/**
 * gis_dashboard.php
 * แผนที่แสดงตำแหน่งศูนย์พักพิงแบบ Interactive
 * อัปเดต: เพิ่มระบบค้นหาและกรองสถานะความหนาแน่น (Density Filter)
 * รองรับ MySQLi ($conn)
 */
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

try {
    // ดึงข้อมูลศูนย์พักพิงพร้อมพิกัดและการคำนวณจำนวนคนปัจจุบัน
    // หมายเหตุ: ตรวจสอบว่าในตาราง shelters มีคอลัมน์ lat, lng หรือไม่ (ถ้าไม่มีระบบจะใช้ค่าจำลองเพื่อแสดงผล)
    $query = "SELECT s.*, 
              (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_count
              FROM shelters s";
    $result = $conn->query($query);
    $shelters = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $shelters[] = $row;
        }
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
}
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container-fluid px-4 py-4">
    <!-- Header Section -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="fw-bold mb-0 text-dark"><i class="fas fa-map-marked-alt text-primary me-2"></i>แผนที่สถานการณ์ศูนย์พักพิง (GIS)</h2>
            <p class="text-muted small mb-0">แสดงข้อมูลพิกัดและความหนาแน่นของผู้อพยพรายศูนย์</p>
        </div>
        <div class="col-md-6">
            <!-- Search & Filter Bar -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-md-7">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="mapSearch" class="form-control border-start-0" placeholder="ค้นหาชื่อศูนย์พักพิง..." onkeyup="applyFilters()">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <select id="statusFilter" class="form-select" onchange="applyFilters()">
                                <option value="all">ทุกสถานะความหนาแน่น</option>
                                <option value="critical" class="text-danger fw-bold">วิกฤต/เต็ม (>90%)</option>
                                <option value="moderate" class="text-warning fw-bold">หนาแน่น (70-90%)</option>
                                <option value="available" class="text-success fw-bold">ว่าง (<70%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- แผนที่ (Left Side) -->
        <div class="col-lg-9 mb-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden position-relative">
                <div id="map" style="height: 650px; width: 100%; z-index: 1;"></div>
                
                <!-- Legend Overlay -->
                <div class="position-absolute bottom-0 start-0 m-3 p-2 bg-white rounded shadow-sm border" style="z-index: 1000; font-size: 0.75rem;">
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-danger me-2" style="width: 15px; height: 15px; border-radius: 50%;"> </span> วิกฤต (>90%)</div>
                    <div class="d-flex align-items-center mb-1"><span class="badge bg-warning me-2" style="width: 15px; height: 15px; border-radius: 50%;"> </span> เริ่มหนาแน่น (70-90%)</div>
                    <div class="d-flex align-items-center"><span class="badge bg-success me-2" style="width: 15px; height: 15px; border-radius: 50%;"> </span> ว่าง/ปกติ (<70%)</div>
                </div>
            </div>
        </div>

        <!-- รายการศูนย์ (Right Side) -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="mb-0 fw-bold">รายการศูนย์พักพิง</h6>
                    <span id="resultCount" class="badge bg-primary rounded-pill"><?php echo count($shelters); ?></span>
                </div>
                <div class="card-body p-0">
                    <div id="shelterList" class="list-group list-group-flush overflow-auto" style="max-height: 580px;">
                        <?php foreach($shelters as $s): 
                            $occupancy = ($s['capacity'] > 0) ? ($s['current_count'] / $s['capacity']) * 100 : 0;
                            $status_key = ($occupancy > 90) ? 'critical' : (($occupancy > 70) ? 'moderate' : 'available');
                        ?>
                        <div class="list-group-item p-3 shelter-item" 
                             data-name="<?php echo htmlspecialchars($s['name']); ?>" 
                             data-status="<?php echo $status_key; ?>"
                             data-id="<?php echo $s['id']; ?>"
                             style="cursor: pointer;"
                             onclick="focusMarker(<?php echo $s['id']; ?>)">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="fw-bold small text-dark"><?php echo htmlspecialchars($s['name']); ?></div>
                                <span class="badge <?php echo $occupancy > 90 ? 'bg-danger' : ($occupancy > 70 ? 'bg-warning' : 'bg-success'); ?> rounded-pill" style="font-size: 0.65rem;">
                                    <?php echo round($occupancy); ?>%
                                </span>
                            </div>
                            <div class="progress mb-1" style="height: 5px;">
                                <div class="progress-bar <?php echo $occupancy > 90 ? 'bg-danger' : ($occupancy > 70 ? 'bg-warning' : 'bg-success'); ?>" 
                                     style="width: <?php echo min(100, $occupancy); ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted" style="font-size: 0.7rem;"><i class="fas fa-users me-1"></i> <?php echo $s['current_count']; ?> / <?php echo $s['capacity']; ?> คน</small>
                                <small class="text-primary" style="font-size: 0.7rem;">คลิกเพื่อดูแผนที่ <i class="fas fa-chevron-right ms-1"></i></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div id="noResults" class="p-5 text-center text-muted" style="display: none;">
                            <i class="fas fa-search fa-2x mb-2 opacity-25"></i><br>
                            ไม่พบศูนย์พักพิงที่ค้นหา
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // 1. ตั้งค่าแผนที่พื้นฐาน
    var map = L.map('map').setView([13.7367, 100.5231], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // 2. เตรียมข้อมูลและเก็บ Layer ของ Marker ไว้เพื่อกรอง
    var shelterData = <?php echo json_encode($shelters); ?>;
    var markersLayer = L.layerGroup().addTo(map);
    var markerMap = {}; // เก็บ Object Marker ตาม ID เพื่อใช้สั่ง Focus

    function initMarkers() {
        markersLayer.clearLayers();
        markerMap = {};
        var bounds = [];

        shelterData.forEach(function(s) {
            var occupancy = (s.capacity > 0) ? (s.current_count / s.capacity) * 100 : 0;
            var markerColor = occupancy > 90 ? '#dc3545' : (occupancy > 70 ? '#ffc107' : '#198754');
            var statusKey = occupancy > 90 ? 'critical' : (occupancy > 70 ? 'moderate' : 'available');
            
            // ใช้พิกัดจริง หรือจำลองพิกัดหากไม่มีข้อมูล
            var lat = parseFloat(s.latitude) || (13.7 + (Math.random() * 2));
            var lng = parseFloat(s.longitude) || (100.5 + (Math.random() * 2));

            var marker = L.circleMarker([lat, lng], {
                radius: 12,
                fillColor: markerColor,
                color: "#fff",
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9,
                id: s.id,
                name: s.name,
                status: statusKey
            });

            var popupContent = `
                <div style="font-family: 'Prompt', sans-serif; min-width: 180px;">
                    <h6 class="fw-bold mb-1">${s.name}</h6>
                    <div class="mb-2">
                        <span class="badge ${occupancy > 90 ? 'bg-danger' : (occupancy > 70 ? 'bg-warning text-dark' : 'bg-success')} mb-2">หนาแน่น ${Math.round(occupancy)}%</span><br>
                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> ${s.location || 'ไม่ระบุพิกัดชัดเจน'}</small>
                    </div>
                    <div class="d-grid">
                        <a href="shelter_details.php?id=${s.id}" class="btn btn-primary btn-sm text-white">จัดการข้อมูล</a>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);
            marker.addTo(markersLayer);
            
            markerMap[s.id] = marker;
            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) map.fitBounds(bounds);
    }

    // 3. ฟังก์ชันกรองข้อมูล (Apply Filters)
    function applyFilters() {
        var searchText = document.getElementById('mapSearch').value.toLowerCase();
        var statusFilter = document.getElementById('statusFilter').value;
        var items = document.querySelectorAll('.shelter-item');
        var count = 0;

        items.forEach(function(item) {
            var name = item.getAttribute('data-name').toLowerCase();
            var status = item.getAttribute('data-status');
            var id = item.getAttribute('data-id');
            var marker = markerMap[id];

            var matchSearch = name.includes(searchText);
            var matchStatus = (statusFilter === 'all' || status === statusFilter);

            if (matchSearch && matchStatus) {
                item.style.display = 'block';
                if (marker && !map.hasLayer(marker)) marker.addTo(markersLayer);
                count++;
            } else {
                item.style.display = 'none';
                if (marker && map.hasLayer(marker)) markersLayer.removeLayer(marker);
            }
        });

        document.getElementById('resultCount').innerText = count;
        document.getElementById('noResults').style.display = (count === 0) ? 'block' : 'none';
    }

    // 4. ฟังก์ชันซูมไปที่ศูนย์เมื่อคลิกรายการด้านข้าง
    function focusMarker(id) {
        var marker = markerMap[id];
        if (marker) {
            map.setView(marker.getLatLng(), 14);
            marker.openPopup();
        }
    }

    // เริ่มต้นแสดงผล
    document.addEventListener('DOMContentLoaded', initMarkers);
</script>

<style>
    .leaflet-popup-content-wrapper { border-radius: 12px; padding: 5px; }
    .shelter-item:hover { background-color: #f8fafc; }
    .shelter-item.active { background-color: #e2e8f0; border-left: 4px solid #fbbf24; }
    #shelterList::-webkit-scrollbar { width: 5px; }
    #shelterList::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<?php include 'includes/footer.php'; ?>