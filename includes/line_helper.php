<?php
/**
 * includes/line_helper.php
 * ฟังก์ชันกลางสำหรับส่งการแจ้งเตือนผ่าน LINE Notify
 */

function sendLineNotification($message) {
    global $pdo;
    
    // 1. ดึงค่า Token จากฐานข้อมูล
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_notify_token'");
    $token = $stmt->fetchColumn();
    
    $stmt_active = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_notify_active'");
    $is_active = $stmt_active->fetchColumn();

    if (!$token || $is_active != '1') return false;

    // 2. ส่งข้อมูลผ่าน cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://notify-api.line.me/api/notify");
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "message=" . urlencode($message));
    
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Bearer ' . $token
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $result = curl_exec($ch);
    $res = json_decode($result, true);
    curl_close($ch);

    return (isset($res['status']) && $res['status'] == 200);
}

/**
 * ฟังก์ชันเฉพาะสำหรับแจ้งเตือนสินค้าเหลือน้อย
 */
function alertLowStock($itemName, $currentQty, $unit) {
    $msg = "\n⚠️ [แจ้งเตือนพัสดุวิกฤต]\n";
    $msg .= "รายการ: " . $itemName . "\n";
    $msg .= "ยอดคงเหลือ: " . number_format($currentQty) . " " . $unit . "\n";
    $msg .= "กรุณาพิจารณาเติมทรัพยากรโดยด่วน\n";
    $msg .= "เช็คข้อมูล: " . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/inventory_dashboard.php";
    
    return sendLineNotification($msg);
}