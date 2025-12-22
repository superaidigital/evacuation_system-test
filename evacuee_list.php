<?php
// evacuee_list.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // เรียกใช้ thaiDate() และ renderPagination()
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$shelter_id = isset($_GET['shelter_id']) ? $_GET['shelter_id'] : '';

if (!$shelter_id) {
    die("ไม่ระบุรหัสศูนย์พักพิง");
}

// --- Pagination Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // จำนวนรายการต่อหน้า
$offset = ($page - 1) * $limit;

// 1. ดึงข้อมูลศูนย์พักพิง
$sql_shelter = "SELECT s.*, i.name as incident_name, i.status as incident_status 
                FROM shelters s 
                JOIN incidents i ON s.incident_id = i.id 
                WHERE s.id = ?";
$stmt = $pdo->prepare($sql_shelter);
$stmt->execute([$shelter_id]);
$shelter = $stmt->fetch();

if (!$shelter) {
    die("ไม่พบข้อมูลศูนย์พักพิง");
}

// 2. ดึงจำนวนผู้ประสบภัยทั้งหมด (สำหรับ Pagination)
$sql_count = "SELECT COUNT(*) FROM evacuees WHERE shelter_id = ?";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute([$shelter_id]);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 3. ดึงรายชื่อผู้ประสบภัย (พร้อม LIMIT)
// FIX: เปลี่ยนจากการใช้ ? ผสมกับ Named Parameter เป็น Named Parameter ทั้งหมด
$sql_list = "SELECT * FROM evacuees WHERE shelter_id = :shelter_id ORDER BY check_in_date DESC, id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql_list);
$stmt->bindValue(':shelter_id', $shelter_id);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$evacuees = $stmt->fetchAll();

// 4. คำนวณสถิติเบื้องต้น (ต้องนับจากทั้งหมด ไม่ใช่แค่หน้าปัจจุบัน จึงต้อง Query แยก หรือใช้ View)
// เพื่อประสิทธิภาพ เราจะ Query Count แยกตามเงื่อนไข
$stmt_stats = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN check_out_date IS NULL THEN 1 ELSE 0 END) as staying,
    SUM(CASE WHEN check_out_date IS NULL AND gender = 'male' THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN check_out_date IS NULL AND gender = 'female' THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN check_out_date IS NULL AND (age < 15 OR age >= 60 OR health_condition != '' AND health_condition IS NOT NULL) THEN 1 ELSE 0 END) as vulnerable
    FROM evacuees WHERE shelter_id = ?");
$stmt_stats->execute([$shelter_id]);
$stats = $stmt_stats->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .shelter-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #fbbf24; /* Gold accent */
        }

        .mini-stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
        }
        .mini-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .mini-stat-value { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        .mini-stat-label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; }

        .table-custom thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-staying { background-color: #dcfce7; color: #166534; }
        .status-left { background-color: #f1f5f9; color: #64748b; }
        
        .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid px-4">
    
    <!-- Shelter Header Info -->
    <div class="shelter-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-warning text-dark mb-2">
                    <i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($shelter['incident_name']); ?>
                </span>
                <h3 class="mb-1 fw-bold"><?php echo htmlspecialchars($shelter['name']); ?></h3>
                <div class="opacity-75">
                    <i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($shelter['location']); ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($shelter['contact_phone']); ?>
                </div>
            </div>
            <div class="text-end">
                <a href="evacuee_form.php?shelter_id=<?php echo $shelter_id; ?>&mode=add" class="btn btn-primary btn-lg shadow-sm fw-bold">
                    <i class="fas fa-user-plus me-2"></i> ลงทะเบียนรายใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- Mini Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="mini-stat-card border-bottom-primary" style="border-bottom: 4px solid #3b82f6;">
                <div class="mini-stat-value text-primary"><?php echo number_format($stats['staying']); ?></div>
                <div class="mini-stat-label">กำลังพักอาศัย</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="mini-stat-card" style="border-bottom: 4px solid #10b981;">
                <div class="mini-stat-value text-success"><?php echo number_format($shelter['capacity'] - $stats['staying']); ?></div>
                <div class="mini-stat-label">ที่ว่างคงเหลือ</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="mini-stat-card" style="border-bottom: 4px solid #f59e0b;">
                <div class="mini-stat-value text-warning"><?php echo number_format($stats['vulnerable']); ?></div>
                <div class="mini-stat-label">กลุ่มเปราะบาง</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="mini-stat-card" style="border-bottom: 4px solid #64748b;">
                <div class="mini-stat-value text-secondary"><?php echo number_format($stats['total']); ?></div>
                <div class="mini-stat-label">ยอดสะสมทั้งหมด</div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['swal_success'])): ?>
        <script>Swal.fire('สำเร็จ', '<?php echo $_SESSION['swal_success']; ?>', 'success');</script>
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>

    <!-- Evacuee List Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-list me-2"></i> รายชื่อผู้พักพิง (Roster)</h6>
            
            <div class="d-flex align-items-center">
                <small class="text-muted me-2">แสดง <?php echo $limit; ?> รายการ/หน้า</small>
                <input type="text" id="tableSearch" class="form-control form-control-sm w-auto" placeholder="ค้นหาในหน้านี้..." onkeyup="filterTable()">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-custom" id="evacueeTable">
                    <thead>
                        <tr>
                            <th class="ps-4">ชื่อ-นามสกุล</th>
                            <th>เพศ/อายุ</th>
                            <th>สุขภาพ/เพิ่มเติม</th>
                            <th>วันที่เข้าพัก</th>
                            <th>สถานะ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($evacuees) > 0): ?>
                            <?php foreach ($evacuees as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                    <small class="text-muted"><i class="far fa-id-card me-1"></i> <?php echo $row['id_card'] ? $row['id_card'] : '-'; ?></small>
                                </td>
                                <td>
                                    <?php 
                                        $gender_icon = ($row['gender'] == 'male') ? '<i class="fas fa-mars text-primary"></i>' : (($row['gender'] == 'female') ? '<i class="fas fa-venus text-danger"></i>' : '');
                                        echo "$gender_icon " . ($row['age'] > 0 ? $row['age'] . " ปี" : "-");
                                    ?>
                                </td>
                                <td>
                                    <?php if($row['health_condition']): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">
                                            <i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars($row['health_condition']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">- ปกติ -</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small"><?php echo thaiDate(date('Y-m-d', strtotime($row['check_in_date']))); ?></div>
                                    <div class="text-muted small" style="font-size: 0.75rem;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                                </td>
                                <td>
                                    <?php if ($row['check_out_date']): ?>
                                        <span class="status-badge status-left">
                                            <i class="fas fa-sign-out-alt me-1"></i> ออกแล้ว
                                        </span>
                                        <div class="small text-muted mt-1" style="font-size: 0.7rem;">
                                            <?php echo thaiDate(date('Y-m-d', strtotime($row['check_out_date']))); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-badge status-staying">
                                            <i class="fas fa-bed me-1"></i> พักอยู่
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="evacuee_form.php?id=<?php echo $row['id']; ?>&mode=edit" class="btn btn-outline-secondary btn-action" title="แก้ไขข้อมูล">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if (!$row['check_out_date']): ?>
                                            <button onclick="confirmCheckout(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name']); ?>')" 
                                                    class="btn btn-outline-warning btn-action ms-1" title="จำหน่ายออก (Check-out)">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if($_SESSION['role'] == 'admin'): ?>
                                            <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-outline-danger btn-action ms-1" title="ลบข้อมูล">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-user-slash fa-3x mb-3 text-light"></i><br>
                                    ยังไม่มีผู้เข้าพักในศูนย์นี้
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-3 bg-light border-top">
                <?php echo renderPagination($page, $total_pages, ['shelter_id' => $shelter_id]); ?>
                <div class="text-center text-muted small mt-2">
                    แสดง <?php echo count($evacuees); ?> จากทั้งหมด <?php echo number_format($total_records); ?> รายการ
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // Client-side Table Filter (ค้นหาเฉพาะหน้าปัจจุบัน)
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("tableSearch");
        filter = input.value.toUpperCase();
        table = document.getElementById("evacueeTable");
        tr = table.getElementsByTagName("tr");
        for (i = 0; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td")[0]; // Search in Name column
            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }       
        }
    }

    function confirmCheckout(id, name) {
        Swal.fire({
            title: 'ยืนยันจำหน่ายออก?',
            text: `ผู้ประสบภัย "${name}" ได้ออกจากศูนย์พักพิงแล้วใช่หรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ใช่, จำหน่ายออก',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `evacuee_status.php?action=checkout&id=${id}&shelter_id=<?php echo $shelter_id; ?>`;
            }
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "ข้อมูลจะถูกลบถาวรและไม่สามารถกู้คืนได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ลบข้อมูลทันที',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `evacuee_status.php?action=delete&id=${id}&shelter_id=<?php echo $shelter_id; ?>`;
            }
        });
    }
</script>

</body>
</html>