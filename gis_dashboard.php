<?php
// gis_dashboard.php
// GIS War Room Map Visualization
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// 1. Fetch Active Incident
$stmt = $pdo->query("SELECT id, name FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$incident = $stmt->fetch();
$incident_id = $incident['id'] ?? 0;
$incident_name = $incident['name'] ?? 'No Active Incident';

// 2. Fetch Shelters Data for Map (GeoJSON format preparation)
$shelters = [];
if ($incident_id) {
    // ดึงข้อมูลศูนย์ + พิกัด + จำนวนคน + สถานะ
    // หมายเหตุ: ตาราง shelters ต้องมี latitude, longitude (ถ้าไม่มีต้องเพิ่ม)
    // สมมติว่าในระบบจริงมี field นี้ หรือเราจะใช้ location text ไป geocode (แต่เพื่อประสิทธิภาพควรเก็บ lat/lon)
    // ในที่นี้จะจำลอง Lat/Lon หากไม่มีใน DB หรือสุ่มแถวๆ ประเทศไทยเพื่อ Demo
    
    $sql = "SELECT s.*, 
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_people
            FROM shelters s 
            WHERE s.incident_id = ? AND s.status != 'closed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$incident_id]);
    $raw_shelters = $stmt->fetchAll();

    foreach ($raw_shelters as $s) {
        // Mockup Coordinates if null (For Demo purpose ONLY)
        // ในระบบจริงต้องมี Lat/Lon ใน DB
        $lat = $s['latitude'] ?? (13.7563 + (rand(-100, 100) / 1000)); 
        $lon = $s['longitude'] ?? (100.5018 + (rand(-100, 100) / 1000));
        
        $pct = ($s['capacity'] > 0) ? ($s['current_people'] / $s['capacity']) * 100 : 0;
        
        // Determine Color
        $color = '#10b981'; // Green
        if ($pct >= 100) $color = '#ef4444'; // Red (Full)
        elseif ($pct >= 80) $color = '#f59e0b'; // Orange (Warning)

        $shelters[] = [
            'id' => $s['id'],
            'name' => $s['name'],
            'lat' => $lat,
            'lon' => $lon,
            'people' => $s['current_people'],
            'capacity' => $s['capacity'],
            'percent' => round($pct, 1),
            'color' => $color,
            'contact' => $s['contact_phone']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title>GIS War Room - แผนที่สถานการณ์</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body, html { height: 100%; margin: 0; padding: 0; overflow: hidden; font-family: 'Prompt', sans-serif; }
        
        #map { height: 100%; width: 100%; z-index: 1; }
        
        .warroom-overlay {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            width: 320px;
            backdrop-filter: blur(5px);
        }

        .legend-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 0.9rem; }
        .dot { width: 12px; height: 12px; border-radius: 50%; margin-right: 10px; display: inline-block; }
        
        .custom-popup .leaflet-popup-content-wrapper {
            border-radius: 8px;
            padding: 0;
            overflow: hidden;
        }
        .custom-popup .leaflet-popup-content { margin: 0; width: 280px !important; }
        
        .popup-header { background: #1e293b; color: white; padding: 10px 15px; font-weight: bold; }
        .popup-body { padding: 15px; }
        
        .btn-back {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

    <!-- Control Panel Overlay -->
    <div class="warroom-overlay">
        <h5 class="fw-bold mb-1"><i class="fas fa-map-marked-alt text-primary"></i> แผนที่สถานการณ์</h5>
        <div class="text-danger fw-bold small mb-3"><?php echo htmlspecialchars($incident_name); ?></div>
        
        <div class="mb-3">
            <label class="small text-muted fw-bold">สัญลักษณ์สถานะ</label>
            <div class="legend-item"><span class="dot" style="background: #10b981;"></span> ว่าง (รับคนได้)</div>
            <div class="legend-item"><span class="dot" style="background: #f59e0b;"></span> หนาแน่น (>80%)</div>
            <div class="legend-item"><span class="dot" style="background: #ef4444;"></span> เต็ม / วิกฤต</div>
        </div>

        <hr>

        <div class="d-grid gap-2">
            <button class="btn btn-primary btn-sm" onclick="fitBounds()"><i class="fas fa-expand"></i> ดูภาพรวมทั้งหมด</button>
            <a href="monitor_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-line"></i> กลับสู่ Dashboard</a>
        </div>
    </div>

    <!-- Map Container -->
    <div id="map"></div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // 1. Init Map
        // Default Center (Thailand)
        var map = L.map('map').setView([13.7563, 100.5018], 6);

        // 2. Add Tile Layer (OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // 3. Load Data from PHP
        var shelters = <?php echo json_encode($shelters); ?>;
        var markers = [];

        // 4. Create Markers
        shelters.forEach(function(s) {
            // Create Custom Icon based on color
            var markerHtml = `<div style="
                background-color: ${s.color};
                width: 20px;
                height: 20px;
                border-radius: 50%;
                border: 2px solid white;
                box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            "></div>`;

            var icon = L.divIcon({
                className: 'custom-pin',
                html: markerHtml,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            var marker = L.marker([s.lat, s.lon], {icon: icon}).addTo(map);
            
            // Popup Content
            var popupContent = `
                <div class="custom-popup">
                    <div class="popup-header">${s.name}</div>
                    <div class="popup-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fas fa-users"></i> ผู้พักพิง:</span>
                            <span class="fw-bold">${s.people} / ${s.capacity}</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar" style="width: ${s.percent}%; background-color: ${s.color}"></div>
                        </div>
                        <div class="small text-muted mb-2"><i class="fas fa-phone"></i> ${s.contact}</div>
                        <div class="text-center mt-2">
                            <a href="shelter_list.php?search=${encodeURIComponent(s.name)}" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                                จัดการข้อมูล
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            markers.push(marker);
        });

        // 5. Auto Fit Bounds
        function fitBounds() {
            if (markers.length > 0) {
                var group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Init Fit
        fitBounds();

    </script>
</body>
</html>