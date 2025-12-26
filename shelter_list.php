<?php
// shelter_list.php
// แสดงรายการศูนย์พักพิง (Table & Grid View) - เพิ่ม Pagination (แบ่งหน้า)
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบ Session
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) { 
    header("Location: login.php"); 
    exit(); 
}

// กำหนด Role
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$menu_context = isset($_GET['menu']) ? $_GET['menu'] : ''; // 'evacuee' for Roster mode

// --- Fetch Filter Options (Incidents) ---
$incidents_list = [];
$check_inc_table = $conn->query("SHOW TABLES LIKE 'incidents'");
if ($check_inc_table && $check_inc_table->num_rows > 0) {
    $inc_sql = "SELECT id, name FROM incidents ORDER BY id DESC";
    $inc_result = $conn->query($inc_sql);
    if ($inc_result) {
        $incidents_list = $inc_result->fetch_all(MYSQLI_ASSOC);
    }
}

// --- Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$incident_id = isset($_GET['incident_id']) ? trim($_GET['incident_id']) : '';

// --- Pagination Configuration ---
$limit = 10; // จำนวนแถวต่อหน้า
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$params = [];
$types = ""; 
$where = " WHERE 1=1 ";

if ($search) {
    $where .= " AND (s.name LIKE ? OR s.location LIKE ?) "; 
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}
if ($status) {
    $where .= " AND s.status LIKE ? "; 
    $params[] = $status;
    $types .= "s";
}
if ($incident_id) {
    $where .= " AND s.incident_id = ? ";
    $params[] = $incident_id;
    $types .= "i";
}

// --- 1. Count Total Rows (สำหรับการแบ่งหน้า) ---
// ต้องทำ Query แยกเพื่อหาจำนวนทั้งหมดก่อนใส่ LIMIT
$count_sql = "SELECT COUNT(*) as total FROM shelters s LEFT JOIN incidents i ON s.incident_id = i.id " . $where;
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// ป้องกัน page เกินจำนวนหน้าที่มีจริง
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// --- 2. Main Query with LIMIT & OFFSET ---
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id) as current_population,
        i.name as incident_name
        FROM shelters s 
        LEFT JOIN incidents i ON s.incident_id = i.id
        $where 
        ORDER BY 
        CASE 
            WHEN s.status LIKE 'Open%' OR s.status LIKE 'open%' THEN 1 
            WHEN s.status LIKE 'Full%' OR s.status LIKE 'full%' THEN 2 
            ELSE 3 
        END, 
        s.name ASC
        LIMIT ? OFFSET ?";

// เพิ่ม limit และ offset เข้าไปใน params
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$shelters = $result->fetch_all(MYSQLI_ASSOC);

$page_title = ($menu_context == 'evacuee') ? 'รายชื่อผู้พักพิง (เลือกศูนย์)' : 'จัดการข้อมูลศูนย์พักพิง';

// Helper function สร้าง Query String สำหรับลิงก์เปลี่ยนหน้า
function get_pagination_link($target_page) {
    $query_params = $_GET;
    $query_params['page'] = $target_page;
    return '?' . http_build_query($query_params);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; background-color: #f4f6f9; }
        
        /* Custom Card Styles */
        .shelter-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        .shelter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        /* Header Gradient Block */
        .shelter-header-bg {
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.85);
            font-size: 3rem;
            position: relative;
        }
        .shelter-header-bg i {
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* Floating Status Badge */
        .status-badge-corner {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-body-custom {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* Stats Box */
        .stats-box {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            border: 1px solid #eee;
        }
        .stats-label { font-size: 0.75rem; color: #6c757d; font-weight: 500; }
        .stats-value { font-weight: 700; color: #212529; font-size: 1.2rem; }

        .action-footer {
            padding: 1rem 1.5rem;
            background-color: #fff;
            border-top: 1px solid #f0f2f5;
        }
        
        .view-toggle-btn.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-landmark text-primary me-2"></i><?php echo $page_title; ?></h3>
            <p class="text-muted small mb-0">บริหารจัดการและดูสถานะศูนย์พักพิงทั้งหมด</p>
        </div>
        
        <div class="d-flex gap-2 align-items-center">
            <?php if (($role === 'admin' || $role === 'staff') && $menu_context != 'evacuee'): ?>
            <a href="shelter_form.php" class="btn btn-success shadow-sm rounded-pill px-3">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มศูนย์พักพิง
            </a>
            <?php endif; ?>
            
            <div class="btn-group shadow-sm" role="group">
                <button type="button" class="btn btn-outline-secondary view-toggle-btn active" onclick="setView('table')" id="btn-table">
                    <i class="fas fa-list"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary view-toggle-btn" onclick="setView('grid')" id="btn-grid">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
        <div class="card-body bg-white rounded-3">
            <form method="GET" class="row g-2">
                <?php if($menu_context): ?><input type="hidden" name="menu" value="<?php echo htmlspecialchars($menu_context); ?>"><?php endif; ?>
                
                <div class="col-md-3">
                    <select name="incident_id" class="form-select border-light bg-light" onchange="this.form.submit()">
                        <option value="">-- เหตุการณ์ทั้งหมด --</option>
                        <?php foreach($incidents_list as $inc): ?>
                            <option value="<?php echo $inc['id']; ?>" <?php echo $incident_id == $inc['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($inc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select border-light bg-light" onchange="this.form.submit()">
                        <option value="">-- สถานะทั้งหมด --</option>
                        <option value="Open" <?php echo ($status == 'Open' || $status == 'open') ? 'selected' : ''; ?>>เปิดใช้งาน (Open)</option>
                        <option value="Full" <?php echo ($status == 'Full' || $status == 'full') ? 'selected' : ''; ?>>เต็ม (Full)</option>
                        <option value="Closed" <?php echo ($status == 'Closed' || $status == 'closed') ? 'selected' : ''; ?>>ปิด (Closed)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-light bg-light" placeholder="ค้นหาชื่อศูนย์, สถานที่..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW 1: TABLE VIEW -->
    <div id="view-table" class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">ชื่อศูนย์พักพิง</th>
                            <th class="py-3">ที่ตั้ง</th>
                            <th class="text-center py-3">ความจุ (คน)</th>
                            <th class="text-center py-3">คงเหลือ</th>
                            <th class="text-center py-3">สถานะ</th>
                            <th class="text-center py-3">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shelters)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิง</td></tr>
                        <?php else: ?>
                            <?php foreach ($shelters as $s): 
                                $capacity = $s['capacity'] > 0 ? $s['capacity'] : 1;
                                $occupied = $s['current_population'] ?? 0; 
                                $available = $s['capacity'] - $occupied;
                                $percent = ($occupied / $capacity) * 100;
                                
                                // === Check Status (Case Insensitive) ===
                                $st = strtolower(trim($s['status'] ?? ''));
                                
                                $statusClass = 'bg-secondary';
                                $statusText = 'ปิด'; // Default fallback
                                
                                if ($st == 'open') { 
                                    $statusClass = 'bg-success'; 
                                    $statusText = 'เปิดรับ'; 
                                } elseif ($st == 'full') { 
                                    $statusClass = 'bg-warning text-dark'; 
                                    $statusText = 'เต็ม'; 
                                } elseif ($st == 'closed') { 
                                    $statusClass = 'bg-danger'; 
                                    $statusText = 'ปิด'; 
                                }

                                $district = $s['district'] ?? '';
                                $province = $s['province'] ?? '';
                                $location_text = htmlspecialchars($s['location']);
                                if ($district) $location_text .= " อ." . htmlspecialchars($district);
                                if ($province) $location_text .= " จ." . htmlspecialchars($province);
                                
                                $incidentName = $s['incident_name'] ?? '';
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-primary">
                                        <i class="fas fa-campground me-2"></i> <?php echo htmlspecialchars($s['name']); ?>
                                    </div>
                                    <?php if($incidentName): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info rounded-pill fw-normal mt-1">
                                            <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($incidentName); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted d-block text-truncate" style="max-width: 200px;"><?php echo $location_text; ?></small>
                                </td>
                                <td class="text-center">
                                    <?php echo number_format($s['capacity']); ?>
                                    <div class="progress mt-1 rounded-pill" style="height: 4px; width: 80px; margin: 0 auto; background-color: #e9ecef;">
                                        <div class="progress-bar <?php echo $statusClass; ?>" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center fw-bold <?php echo $available <= 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format($available); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $statusClass; ?> rounded-pill fw-normal px-3"><?php echo $statusText; ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if ($menu_context == 'evacuee'): ?>
                                            <a href="evacuee_list.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="fas fa-users"></i> รายชื่อ
                                            </a>
                                        <?php else: ?>
                                            <a href="shelter_form.php?edit=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-warning" title="แก้ไข">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="shelter_dashboard.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info" title="Dashboard">
                                                <i class="fas fa-chart-pie"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- VIEW 2: GRID/CARD VIEW (IMPROVED) -->
    <div id="view-grid" class="d-none">
        <div class="row g-4">
            <?php if (empty($shelters)): ?>
                <div class="col-12 text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิง</div>
            <?php else: ?>
                <?php foreach ($shelters as $s): 
                    $capacity = $s['capacity'] > 0 ? $s['capacity'] : 1;
                    $occupied = $s['current_population'] ?? 0;
                    $available = $s['capacity'] - $occupied;
                    $percent = ($occupied / $capacity) * 100;
                    
                    // === Check Status (Case Insensitive) ===
                    $st = strtolower(trim($s['status'] ?? ''));

                    // Default Setup (Closed/Gray)
                    $bgGradient = 'linear-gradient(135deg, #6c757d 0%, #495057 100%)'; 
                    $statusColor = 'secondary';
                    $statusText = 'ปิด';
                    $statusClass = 'bg-secondary';
                    
                    if ($st == 'open') { 
                        $bgGradient = 'linear-gradient(135deg, #20c997 0%, #0ca678 100%)';
                        $statusColor = 'success'; 
                        $statusText = 'เปิดรับ'; 
                        $statusClass = 'bg-success';
                    } elseif ($st == 'full') { 
                        $bgGradient = 'linear-gradient(135deg, #fcc419 0%, #fab005 100%)';
                        $statusColor = 'warning'; 
                        $statusText = 'เต็ม';
                        $statusClass = 'bg-warning';
                    } elseif ($st == 'closed') {
                        $bgGradient = 'linear-gradient(135deg, #ff6b6b 0%, #e03131 100%)';
                        $statusColor = 'danger';
                        $statusText = 'ปิด';
                        $statusClass = 'bg-danger';
                    }

                    $district = $s['district'] ?? '';
                    $province = $s['province'] ?? '';
                    $location_text = htmlspecialchars($s['location']);
                    if ($district) $location_text .= " อ." . htmlspecialchars($district);
                    
                    $incidentName = $s['incident_name'] ?? '';
                ?>
                <div class="col-md-6 col-lg-4 d-flex align-items-stretch">
                    <div class="card shelter-card w-100">
                        <!-- Header Color Block -->
                        <div class="shelter-header-bg" style="background: <?php echo $bgGradient; ?>;">
                            <i class="fas fa-campground"></i>
                            <span class="status-badge-corner text-<?php echo $statusColor; ?>">
                                <i class="fas fa-circle fa-xs me-1"></i> <?php echo $statusText; ?>
                            </span>
                        </div>
                        
                        <div class="card-body card-body-custom">
                            <!-- Incident Badge -->
                            <?php if($incidentName): ?>
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark border shadow-sm">
                                        <i class="fas fa-tag text-secondary me-1"></i> <?php echo htmlspecialchars($incidentName); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <!-- Title -->
                            <h5 class="fw-bold text-dark mb-1 text-truncate"><?php echo htmlspecialchars($s['name']); ?></h5>
                            <p class="text-muted small mb-3 text-truncate">
                                <i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo $location_text; ?>
                            </p>

                            <!-- Stats Grid -->
                            <div class="row g-2 mb-3 mt-auto">
                                <div class="col-6">
                                    <div class="stats-box">
                                        <div class="stats-label">ความจุ (คน)</div>
                                        <div class="stats-value text-muted"><?php echo number_format($capacity); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stats-box">
                                        <div class="stats-label">ว่าง (ที่)</div>
                                        <div class="stats-value <?php echo $available <= 0 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo number_format($available); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div class="mt-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">ความหนาแน่น</span>
                                    <span class="fw-bold text-<?php echo $statusColor; ?>"><?php echo number_format($percent, 0); ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px; border-radius: 4px; background-color: #f1f3f5;">
                                    <div class="progress-bar <?php echo $statusClass; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="action-footer d-flex gap-2">
                            <?php if ($menu_context == 'evacuee'): ?>
                                <a href="evacuee_list.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
                                    <i class="fas fa-users me-1"></i> ดูรายชื่อ
                                </a>
                            <?php else: ?>
                                <a href="shelter_form.php?edit=<?php echo $s['id']; ?>" class="btn btn-outline-warning btn-sm flex-grow-1 rounded-pill">
                                    <i class="fas fa-edit me-1"></i> แก้ไข
                                </a>
                                <a href="shelter_dashboard.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-outline-info btn-sm flex-grow-1 rounded-pill">
                                    <i class="fas fa-chart-pie me-1"></i> Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            
            <!-- Previous -->
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link rounded-pill me-1" href="<?php echo get_pagination_link($page - 1); ?>" tabindex="-1" aria-disabled="true">ก่อนหน้า</a>
            </li>

            <!-- Page Numbers -->
            <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if($start > 1) {
                    echo '<li class="page-item"><a class="page-link rounded-pill me-1" href="'.get_pagination_link(1).'">1</a></li>';
                    if($start > 2) echo '<li class="page-item disabled"><span class="page-link rounded-pill me-1">...</span></li>';
                }

                for ($i = $start; $i <= $end; $i++): 
            ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link rounded-pill me-1" href="<?php echo get_pagination_link($i); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>

            <?php 
                if($end < $total_pages) {
                    if($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link rounded-pill me-1">...</span></li>';
                    echo '<li class="page-item"><a class="page-link rounded-pill me-1" href="'.get_pagination_link($total_pages).'">'.$total_pages.'</a></li>';
                }
            ?>

            <!-- Next -->
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link rounded-pill" href="<?php echo get_pagination_link($page + 1); ?>">ถัดไป</a>
            </li>
        </ul>
        <div class="text-center text-muted small mt-2">
            แสดง <?php echo $offset + 1; ?> ถึง <?php echo min($offset + $limit, $total_rows); ?> จากทั้งหมด <?php echo number_format($total_rows); ?> รายการ
        </div>
    </nav>
    <?php endif; ?>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- View Toggle Script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const savedView = localStorage.getItem('shelter_view_mode') || 'table';
        setView(savedView);
    });

    function setView(view) {
        const tableView = document.getElementById('view-table');
        const gridView = document.getElementById('view-grid');
        const btnTable = document.getElementById('btn-table');
        const btnGrid = document.getElementById('btn-grid');

        if (view === 'grid') {
            tableView.classList.add('d-none');
            gridView.classList.remove('d-none');
            btnTable.classList.remove('active');
            btnGrid.classList.add('active');
        } else {
            tableView.classList.remove('d-none');
            gridView.classList.add('d-none');
            btnTable.classList.add('active');
            btnGrid.classList.remove('active');
        }
        
        localStorage.setItem('shelter_view_mode', view);
    }
</script>

</body>
</html>