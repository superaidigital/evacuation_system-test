<?php
// shelter_list.php
require_once 'config/db.php';
require_once 'includes/functions.php'; // สำหรับ renderPagination

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Filter Logic
$filter_incident_id = isset($_GET['filter_incident']) ? $_GET['filter_incident'] : '';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // แสดง 12 ใบ (Cards) ต่อหน้า
$offset = ($page - 1) * $limit;

// ถ้าไม่เลือก Incident ให้หา Active ล่าสุด
if (!$filter_incident_id) {
    $stmt = $pdo->query("SELECT id FROM incidents WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    $active_inc = $stmt->fetch();
    if ($active_inc) {
        $filter_incident_id = $active_inc['id'];
    }
}

// 3. Fetch Data
$shelters = [];
$total_records = 0;
$total_pages = 0;

if ($filter_incident_id) {
    
    // 3.1 Count Total
    $sql_count = "SELECT COUNT(*) FROM shelters s WHERE s.incident_id = ?";
    $params = [$filter_incident_id];

    if ($search_keyword) {
        $sql_count .= " AND (s.name LIKE ? OR s.location LIKE ? OR s.contact_phone LIKE ?) ";
        $params[] = "%$search_keyword%";
        $params[] = "%$search_keyword%";
        $params[] = "%$search_keyword%";
    }
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // 3.2 Fetch Data with Limit
    $sql = "SELECT s.*, 
            (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id AND e.check_out_date IS NULL) as current_occupancy
            FROM shelters s 
            WHERE s.incident_id = ? ";
    
    // Reset params for main query
    $params = [$filter_incident_id];

    if ($search_keyword) {
        $sql .= " AND (s.name LIKE ? OR s.location LIKE ? OR s.contact_phone LIKE ?) ";
        $params[] = "%$search_keyword%";
        $params[] = "%$search_keyword%";
        $params[] = "%$search_keyword%";
    }
    
    $sql .= " ORDER BY s.name ASC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $shelters = $stmt->fetchAll();
}

// ดึง Incident ทั้งหมดสำหรับ Dropdown
$all_incidents = $pdo->query("SELECT id, name, status FROM incidents ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <style>
        .filter-bar {
            background-color: #fff;
            padding: 15px 20px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .shelter-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .shelter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: #cbd5e1;
        }
        .status-strip {
            height: 4px;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }
        .status-open { background-color: #10b981; }
        .status-full { background-color: #fbbf24; }
        .status-closed { background-color: #64748b; }
        
        .progress-thin {
            height: 6px;
            border-radius: 3px;
            background-color: #f1f5f9;
            margin-top: 5px;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container-fluid px-0">
    
    <!-- Filter Bar -->
    <div class="filter-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center flex-grow-1">
            <h4 class="mb-0 fw-bold text-dark me-3 text-nowrap">
                <i class="fas fa-campground text-primary me-2"></i>ข้อมูลศูนย์พักพิง
            </h4>
            
            <form action="" method="GET" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 600px;">
                <select name="filter_incident" class="form-select form-select-sm border-secondary fw-bold" style="min-width: 200px;" onchange="this.form.submit()">
                    <option value="" disabled <?php echo empty($filter_incident_id) ? 'selected' : ''; ?>>-- เลือกเหตุการณ์ --</option>
                    <?php foreach ($all_incidents as $inc): ?>
                        <option value="<?php echo $inc['id']; ?>" <?php echo $filter_incident_id == $inc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($inc['name']); ?> 
                            (<?php echo $inc['status'] == 'active' ? 'Active' : 'Closed'; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อศูนย์, ที่ตั้ง..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                    <button class="btn btn-outline-secondary" type="submit">ค้นหา</button>
                </div>
            </form>
        </div>
        
        <div>
            <?php if($filter_incident_id): ?>
                <a href="shelter_form.php?incident_id=<?php echo $filter_incident_id; ?>&mode=add" class="btn btn-primary btn-sm shadow-sm fw-bold">
                    <i class="fas fa-plus me-1"></i> เพิ่มศูนย์ใหม่
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-fluid px-4">
        
        <!-- Alerts -->
        <?php if (isset($_SESSION['swal_success'])): ?>
            <script>Swal.fire({icon: 'success', title: 'สำเร็จ', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 1500, showConfirmButton: false});</script>
            <?php unset($_SESSION['swal_success']); ?>
        <?php endif; ?>

        <?php if (!$filter_incident_id): ?>
            <div class="alert alert-warning text-center mt-4 border-warning bg-warning bg-opacity-10">
                <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i><br>
                <strong>กรุณาเลือกเหตุการณ์ภัยพิบัติ</strong> เพื่อดูข้อมูลศูนย์พักพิงที่เกี่ยวข้อง
            </div>
        <?php elseif (empty($shelters)): ?>
            <div class="text-center py-5 text-muted">
                <div class="mb-3 opacity-50"><i class="fas fa-folder-open fa-4x"></i></div>
                <h5>ไม่พบข้อมูลศูนย์พักพิง</h5>
                <?php if($search_keyword): ?>
                    <p>ไม่พบผลลัพธ์สำหรับคำค้นหา "<?php echo htmlspecialchars($search_keyword); ?>"</p>
                    <a href="shelter_list.php?filter_incident=<?php echo $filter_incident_id; ?>" class="btn btn-sm btn-outline-primary">ล้างคำค้นหา</a>
                <?php else: ?>
                    <p>ยังไม่มีศูนย์พักพิงในเหตุการณ์นี้ คลิกปุ่ม "เพิ่มศูนย์ใหม่" เพื่อเริ่มต้น</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            
            <!-- Shelter Grid -->
            <div class="row g-4 pb-3">
                <?php foreach ($shelters as $row): ?>
                    <?php 
                        $status_class = 'status-open';
                        $status_label = '<span class="badge bg-success bg-opacity-10 text-success border border-success">เปิดรับ</span>';
                        
                        if($row['status'] == 'full') { 
                            $status_class = 'status-full'; 
                            $status_label = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning">เต็ม</span>';
                        }
                        if($row['status'] == 'closed') { 
                            $status_class = 'status-closed'; 
                            $status_label = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary">ปิดชั่วคราว</span>';
                        }

                        $capacity = $row['capacity'] > 0 ? $row['capacity'] : 1;
                        $current = $row['current_occupancy'];
                        $percent = ($current / $capacity) * 100;
                        $bar_color = ($percent >= 90) ? 'bg-danger' : (($percent >= 70) ? 'bg-warning' : 'bg-success');
                    ?>
                    
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="shelter-card shadow-sm">
                            <div class="status-strip <?php echo $status_class; ?>"></div>
                            
                            <div class="p-3 pt-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="fw-bold text-dark mb-0 text-truncate" title="<?php echo htmlspecialchars($row['name']); ?>" style="max-width: 85%;">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </h6>
                                    
                                    <div class="dropdown">
                                        <button class="btn btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                            <li><a class="dropdown-item" href="evacuee_list.php?shelter_id=<?php echo $row['id']; ?>"><i class="fas fa-list-ul me-2 text-primary"></i> รายชื่อผู้พักพิง</a></li>
                                            <li><a class="dropdown-item" href="shelter_form.php?id=<?php echo $row['id']; ?>&mode=edit"><i class="fas fa-edit me-2 text-warning"></i> แก้ไขข้อมูล</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="confirmDeleteShelter(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')"><i class="fas fa-trash-alt me-2"></i> ลบศูนย์</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="text-muted small mb-3 text-truncate">
                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i> <?php echo htmlspecialchars($row['location']); ?>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1 fw-bold">
                                        <span class="text-muted">ความหนาแน่น</span>
                                        <span class="<?php echo ($percent>=100)?'text-danger':'text-dark'; ?>">
                                            <?php echo number_format($current); ?> / <?php echo number_format($row['capacity']); ?>
                                        </span>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar <?php echo $bar_color; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
                                    <div class="small text-muted">
                                        <i class="fas fa-phone me-1"></i> <?php echo $row['contact_phone'] ?: '-'; ?>
                                    </div>
                                    <?php echo $status_label; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <div class="pb-5">
                <?php 
                    echo renderPagination($page, $total_pages, [
                        'filter_incident' => $filter_incident_id,
                        'search' => $search_keyword
                    ]); 
                ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function confirmDeleteShelter(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `คุณต้องการลบศูนย์พักพิง "${name}" ใช่หรือไม่? หากมีผู้พักอาศัยอยู่จะไม่สามารถลบได้`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `shelter_status.php?action=delete&id=${id}`;
        }
    });
}
</script>

</body>
</html>