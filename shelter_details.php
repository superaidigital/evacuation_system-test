<?php
/**
 * shelter_details.php
 * หน้าแสดงรายละเอียดเชิงลึกของศูนย์พักพิงและรายชื่อผู้อพยพภายในศูนย์
 * ปรับปรุง: รองรับ MySQLi และจัดการปัญหาคอลัมน์อายุ (Age/Birth Date)
 */

require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// 1. รับค่า ID และตรวจสอบความปลอดภัย
$shelter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($shelter_id <= 0) {
    echo "<div class='alert alert-danger shadow-sm rounded-3'><i class='fas fa-exclamation-circle me-2'></i>ไม่ระบุรหัสศูนย์พักพิง</div>";
    include 'includes/footer.php';
    exit;
}

try {
    // 2. ดึงข้อมูลรายละเอียดศูนย์พักพิง
    $sql_shelter = "SELECT s.*, i.name as incident_name 
                    FROM shelters s 
                    LEFT JOIN incidents i ON s.incident_id = i.id 
                    WHERE s.id = ?";
    $stmt = $conn->prepare($sql_shelter);
    $stmt->bind_param("i", $shelter_id);
    $stmt->execute();
    $shelter = $stmt->get_result()->fetch_assoc();

    if (!$shelter) {
        echo "<div class='alert alert-warning shadow-sm rounded-3'>ไม่พบข้อมูลศูนย์พักพิงในระบบ</div>";
        include 'includes/footer.php';
        exit;
    }

    // 3. ดึงรายชื่อผู้อพยพที่ยังไม่แจ้งออก (Active)
    $sql_evacuees = "SELECT * FROM evacuees WHERE shelter_id = ? AND check_out_date IS NULL ORDER BY created_at DESC";
    $stmt_ev = $conn->prepare($sql_evacuees);
    $stmt_ev->bind_param("i", $shelter_id);
    $stmt_ev->execute();
    $evacuees = $stmt_ev->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4. คำนวณสถิติความหนาแน่น
    $current_count = count($evacuees);
    $capacity = (int)$shelter['capacity'];
    $occupancy_rate = ($capacity > 0) ? ($current_count / $capacity) * 100 : 0;

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . h($e->getMessage()) . "</div>";
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="fas fa-hospital-alt text-primary me-2"></i><?php echo h($shelter['name']); ?></h2>
            <p class="text-muted mb-0"><i class="fas fa-map-marker-alt me-1"></i> <?php echo h($shelter['location']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="shelter_list.php" class="btn btn-outline-secondary">กลับหน้ารายชื่อศูนย์</a>
            <a href="evacuee_form.php?shelter_id=<?php echo $shelter_id; ?>&mode=add" class="btn btn-primary fw-bold">
                <i class="fas fa-user-plus me-1"></i>ลงทะเบียนใหม่
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- ข้อมูลสถิติและสถานะศูนย์ -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">สถานะความหนาแน่น</h6>
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <h2 class="fw-bold mb-0"><?php echo round($occupancy_rate, 1); ?>%</h2>
                        <span class="small text-muted"><?php echo $current_count; ?> / <?php echo $capacity; ?> คน</span>
                    </div>
                    <div class="progress mb-3" style="height: 12px; border-radius: 10px;">
                        <?php 
                            $bg_class = ($occupancy_rate >= 90) ? 'bg-danger' : (($occupancy_rate >= 70) ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="progress-bar <?php echo $bg_class; ?>" style="width: <?php echo min(100, $occupancy_rate); ?>%"></div>
                    </div>
                    <hr>
                    <div class="small">
                        <div class="mb-2"><i class="fas fa-user-tie me-2 text-primary"></i>ผู้ประสานงาน: <strong><?php echo h($shelter['contact_person']); ?></strong></div>
                        <div class="mb-0"><i class="fas fa-phone-alt me-2 text-success"></i>เบอร์ติดต่อ: <strong class="text-primary fs-6"><?php echo h($shelter['contact_phone']); ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="fas fa-bullhorn me-2"></i>เหตุการณ์</h6>
                    <p class="mb-0 small opacity-75"><?php echo h($shelter['incident_name'] ?: 'การอพยพทั่วไป'); ?></p>
                </div>
            </div>
        </div>

        <!-- รายชื่อผู้อพยพ -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center px-4">
                    <h6 class="mb-0 fw-bold">ผู้อพยพที่พักอยู่ปัจจุบัน</h6>
                    <span class="badge bg-light text-primary border border-primary-subtle px-3 py-2">รวม <?php echo $current_count; ?> คน</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-muted text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3">ชื่อ-นามสกุล</th>
                                    <th>เพศ / อายุ</th>
                                    <th>เบอร์โทรศัพท์</th>
                                    <th>สถานะสุขภาพ</th>
                                    <th class="text-end pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($current_count > 0): ?>
                                    <?php foreach ($evacuees as $row): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo h($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                            <small class="text-muted">ID: <?php echo $row['id_card'] ?: '-'; ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                                $gender_icon = ($row['gender'] == 'male') ? '<i class="fas fa-mars text-primary"></i>' : '<i class="fas fa-venus text-danger"></i>';
                                                
                                                // ตรวจสอบคอลัมน์อายุ (Age) หรือ วันเกิด (Birth Date)
                                                $age_display = '-';
                                                if (isset($row['age']) && $row['age'] > 0) {
                                                    $age_display = $row['age'];
                                                } elseif (isset($row['birth_date'])) {
                                                    $age_display = calculateAge($row['birth_date']);
                                                }
                                                echo "$gender_icon $age_display ปี";
                                            ?>
                                        </td>
                                        <td><?php echo h($row['phone']); ?></td>
                                        <td>
                                            <?php if($row['health_condition']): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle small"><?php echo h($row['health_condition']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle small">ปกติ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group shadow-sm">
                                                <a href="evacuee_card.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-light border" title="พิมพ์บัตร">
                                                    <i class="fas fa-id-card text-primary"></i>
                                                </a>
                                                <a href="evacuee_form.php?id=<?php echo $row['id']; ?>&mode=edit" class="btn btn-sm btn-light border ms-1" title="แก้ไข">
                                                    <i class="fas fa-edit text-secondary"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i><br>
                                            ยังไม่มีรายชื่อผู้อพยพในศูนย์นี้
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>