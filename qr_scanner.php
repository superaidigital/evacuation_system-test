<?php
/**
 * หน้าสำหรับสแกน QR Code จากบัตรประจำตัวผู้อพยพ
 * ใช้ Library html5-qrcode เพื่อเข้าถึงกล้องมือถือ/คอมพิวเตอร์
 */

require_once 'config/db.php';
require_once 'includes/header.php';
?>

<div class="container py-4 text-center">
    <h2 class="mb-4"><i class="fas fa-qrcode"></i> สแกนบัตรประจำตัวผู้อพยพ</h2>
    
    <div class="row justify-content-center">
        <div class="col-md-6">
            <!-- บริเวณแสดงภาพจากกล้อง -->
            <div id="reader" style="width: 100%; border-radius: 15px; overflow: hidden; background: #000;"></div>
            
            <div id="result" class="mt-4" style="display: none;">
                <div class="card border-success shadow">
                    <div class="card-body">
                        <h5 class="text-success"><i class="fas fa-check-circle"></i> พบข้อมูลผู้อพยพ!</h5>
                        <p id="scanned-id" class="font-weight-bold mb-3"></p>
                        <div id="action-buttons">
                            <!-- ปุ่มที่จะถูกสร้างเมื่อสแกนสำเร็จ -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="index.php" class="btn btn-secondary">ยกเลิกและกลับหน้าหลัก</a>
            </div>
        </div>
    </div>
</div>

<!-- โหลด Library สำหรับสแกน QR Code -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    function onScanSuccess(decodedText, decodedResult) {
        // เมื่อสแกนสำเร็จ
        console.log(`Code matched = ${decodedText}`, decodedResult);
        
        // รูปแบบข้อมูลในบัตรคือ "EVAC-ID" เราต้องการแค่ ID
        const parts = decodedText.split('-');
        if (parts[0] === 'EVAC' && parts[1]) {
            const evacueeId = parts[1];
            
            // หยุดการสแกนชั่วคราว
            html5QrcodeScanner.clear();
            
            // แสดงผลและปุ่มดำเนินการ
            document.getElementById('result').style.display = 'block';
            document.getElementById('scanned-id').innerText = "รหัสอ้างอิง: " + decodedText;
            
            document.getElementById('action-buttons').innerHTML = `
                <a href="evacuee_form.php?id=${evacueeId}" class="btn btn-primary btn-block mb-2">
                    <i class="fas fa-user-edit"></i> ดู/แก้ไขข้อมูลส่วนตัว
                </a>
                <a href="distribution_manager.php?evacuee_id=${evacueeId}" class="btn btn-success btn-block mb-2">
                    <i class="fas fa-box-open"></i> บันทึกการรับถุงยังชีพ
                </a>
                <button onclick="location.reload()" class="btn btn-outline-secondary btn-block">
                    สแกนคนถัดไป
                </button>
            `;
        }
    }

    function onScanFailure(error) {
        // จัดการเมื่อสแกนไม่ติด (ปล่อยว่างไว้เพื่อให้ทำงานต่อเนื่อง)
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { fps: 10, qrbox: {width: 250, height: 250} },
        /* verbose= */ false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>

<style>
    #reader__scan_region video {
        object-fit: cover !important;
    }
    #reader {
        border: none !important;
    }
    #reader__dashboard_section_csr button {
        background: #4e73df !important;
        color: white !important;
        border: none !important;
        padding: 10px 20px !important;
        border-radius: 5px !important;
        margin: 10px 0;
    }
</style>

<?php include 'includes/footer.php'; ?>