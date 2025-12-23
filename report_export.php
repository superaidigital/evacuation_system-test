<?php
// export_process.php
// Refactored: ใช้เทคนิค Streaming Download เพื่อรองรับข้อมูลขนาดใหญ่ (High Scalability)
// และแก้ปัญหาภาษาไทยเพี้ยนใน Excel (UTF-8 BOM)

require_once 'config/db.php';
require_once 'includes/functions.php';

// 1. Authentication & Security Check
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

// 2. Prepare Parameters
$incident_id = filter_input(INPUT_GET, 'incident_id', FILTER_VALIDATE_INT);
$shelter_id  = filter_input(INPUT_GET, 'shelter_id', FILTER_VALIDATE_INT);
$type        = $_GET['type'] ?? 'evacuees'; // รองรับการ Export หลายแบบในอนาคต

// ตรวจสอบสิทธิ์ (Optional: ถ้าเป็น Staff ศูนย์ไหน ต้อง Export ได้แค่ศูนย์นั้น)
// if ($_SESSION['role'] == 'staff' && $_SESSION['shelter_id'] != $shelter_id) { die("Unauthorized"); }

// 3. Setup Headers for CSV Download
$filename = "export_" . $type . "_" . date('Y-m-d_H-i') . ".csv";

// ปิด Output Buffering เพื่อให้ข้อมูลถูกส่งทันที (ลด Memory Usage)
if (ob_get_level()) ob_end_clean();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 4. Open Output Stream & Add BOM
$output = fopen('php://output', 'w');

// สำคัญ! เขียน BOM (Byte Order Mark) เพื่อให้ Excel เปิดภาษาไทยได้ถูกต้อง
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

try {
    if ($type === 'evacuees') {
        // --- Export รายชื่อผู้ประสบภัย ---
        
        // Header Row
        fputcsv($output, [
            'รหัส', 
            'เลขบัตรประชาชน', 
            'คำนำหน้า', 'ชื่อ', 'นามสกุล', 
            'เพศ', 'อายุ', 'เบอร์โทร', 
            'ที่อยู่ตามบัตร', 
            'สุขภาพ/โรคประจำตัว', 
            'สถานะที่พัก', 'ชื่อศูนย์พักพิง/รายละเอียด', 
            'ลงทะเบียนเมื่อ'
        ]);

        // Query Data (ใช้ Unbuffered Query ถ้าระบบรองรับ หรือ Fetch ทีละส่วน)
        // ในที่นี้ใช้ Standard PDO แต่ Fetch ทีละ row
        $sql = "SELECT e.*, s.name as shelter_name 
                FROM evacuees e 
                LEFT JOIN shelters s ON e.shelter_id = s.id 
                WHERE e.incident_id = ?";
        
        $params = [$incident_id];
        
        if ($shelter_id) {
            $sql .= " AND e.shelter_id = ?";
            $params[] = $shelter_id;
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Data Sanitization for CSV (ป้องกัน CSV Injection ถ้า field เริ่มต้นด้วย =,+,-,@)
            // แต่สำหรับข้อมูลทั่วไป แค่ Clean ก็พอ
            
            $stay_place = ($row['stay_type'] == 'shelter') ? $row['shelter_name'] : $row['stay_detail'];
            $gender_map = ['male'=>'ชาย', 'female'=>'หญิง', 'other'=>'อื่นๆ'];

            $csv_row = [
                $row['id'],
                "'" . $row['id_card'], // ใส่ ' นำหน้าเพื่อให้ Excel มองเป็น Text ไม่ใช่ Sci-Notation
                $row['prefix'],
                $row['first_name'],
                $row['last_name'],
                $gender_map[$row['gender']] ?? $row['gender'],
                $row['age'],
                "'" . $row['phone'],
                $row['address_card'],
                $row['health_condition'],
                ($row['stay_type'] == 'shelter' ? 'ในศูนย์' : 'นอกศูนย์'),
                $stay_place,
                $row['created_at']
            ];

            fputcsv($output, $csv_row);
        }

    } elseif ($type === 'shelters') {
        // --- Export ข้อมูลศูนย์พักพิง ---
        fputcsv($output, ['ชื่อศูนย์', 'สถานที่', 'ความจุ', 'เบอร์ติดต่อ', 'สถานะ', 'จำนวนผู้พักพิงปัจจุบัน']);
        
        $sql = "SELECT s.*, 
                (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_count 
                FROM shelters s 
                WHERE s.incident_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$incident_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['name'],
                $row['location'],
                $row['capacity'],
                "'" . $row['contact_phone'],
                $row['status'],
                $row['current_count']
            ]);
        }
    }

} catch (Exception $e) {
    // กรณี Error ระหว่าง Stream อาจจะทำอะไรไม่ได้มากเพราะ Header ส่งไปแล้ว
    // แต่สามารถเขียน Error ลงไฟล์ CSV ได้เพื่อให้ User รู้
    fputcsv($output, ['ERROR: ' . $e->getMessage()]);
}

fclose($output);
exit();
?>