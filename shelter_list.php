<?php
// shelter_list.php
// แสดงรายการศูนย์พักพิง (Table & Grid View) - แก้ไขลิงก์ Dashboard ให้ถูกต้อง
require_once 'config/db.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$role = $_SESSION['role'];
$menu_context = isset($_GET['menu']) ? $_GET['menu'] : ''; // 'evacuee' for Roster mode

// --- Filter Logic ---
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$params = [];
$where = " WHERE 1=1 ";

if ($search) {
    $where .= " AND (name LIKE ? OR location LIKE ?) "; 
    $term = "%$search%";
    $params[] = $term; $params[] = $term;
}
if ($status) {
    $where .= " AND status = ? ";
    $params[] = $status;
}

// Fetch Shelters with dynamic population count
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM evacuees e WHERE e.shelter_id = s.id) as current_population
        FROM shelters s 
        $where 
        ORDER BY s.status DESC, s.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shelters = $stmt->fetchAll();

// Page Title
$page_title = ($menu_context == 'evacuee') ? 'รายชื่อผู้พักพิง (เลือกศูนย์)' : 'จัดการข้อมูลศูนย์พักพิง';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-header-custom { background: linear-gradient(to right, #4b6cb7, #182848); color: white; }
        .shelter-card { transition: transform 0.2s; border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .shelter-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .status-open { color: #198754; font-weight: bold; }
        .status-closed { color: #dc3545; font-weight: bold; }
        .status-full { color: #fd7e14; font-weight: bold; }
        
        .shelter-img-placeholder {
            height: 150px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 3rem;
            border-radius: 10px 10px 0 0;
        }
        
        .view-toggle-btn.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light">

<?php include 'includes/header.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-landmark text-primary me-2"></i><?php echo $page_title; ?></h3>
            <p class="text-muted small mb-0">บริหารจัดการและดูสถานะศูนย์พักพิงทั้งหมด</p>
        </div>
        
        <div class="d-flex gap-2">
            <?php if ($role === 'admin' && $menu_context != 'evacuee'): ?>
            <a href="shelter_form.php" class="btn btn-success shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มศูนย์พักพิง
            </a>
            <a href="shelter_import.php" class="btn btn-outline-success shadow-sm">
                <i class="fas fa-file-import me-1"></i> นำเข้า (CSV)
            </a>
            <?php endif; ?>
            
            <!-- View Toggle Buttons -->
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

    <!-- Alert Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-white rounded">
            <form method="GET" class="row g-3">
                <?php if($menu_context): ?><input type="hidden" name="menu" value="<?php echo $menu_context; ?>"><?php endif; ?>
                
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- สถานะทั้งหมด --</option>
                        <option value="open" <?php echo $status == 'open' ? 'selected' : ''; ?>>เปิดใช้งาน (Open)</option>
                        <option value="full" <?php echo $status == 'full' ? 'selected' : ''; ?>>เต็ม (Full)</option>
                        <option value="closed" <?php echo $status == 'closed' ? 'selected' : ''; ?>>ปิด (Closed)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อศูนย์..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW 1: TABLE VIEW -->
    <div id="view-table" class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ชื่อศูนย์พักพิง</th>
                            <th>ที่ตั้ง</th>
                            <th class="text-center">ความจุ (คน)</th>
                            <th class="text-center">คงเหลือ</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shelters)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิง</td></tr>
                        <?php else: ?>
                            <?php foreach ($shelters as $s): 
                                $capacity = $s['capacity'] ?? 0;
                                $occupied = $s['current_population'] ?? 0; 
                                $available = $capacity - $occupied;
                                $percent = ($capacity > 0) ? ($occupied / $capacity) * 100 : 0;
                                
                                $statusClass = 'bg-secondary';
                                $statusText = 'ปิด';
                                if ($s['status'] == 'open') { $statusClass = 'bg-success'; $statusText = 'เปิดรับ'; }
                                elseif ($s['status'] == 'full') { $statusClass = 'bg-warning text-dark'; $statusText = 'เต็ม'; }
                                
                                // FIX: Handle missing keys safely
                                $district = $s['district'] ?? '';
                                $province = $s['province'] ?? '';
                                $location_text = htmlspecialchars($s['location']);
                                if ($district) $location_text .= " อ." . htmlspecialchars($district);
                                if ($province) $location_text .= " จ." . htmlspecialchars($province);
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <i class="fas fa-campground me-2"></i> <?php echo htmlspecialchars($s['name']); ?>
                                </td>
                                <td>
                                    <small class="text-muted d-block"><?php echo $location_text; ?></small>
                                </td>
                                <td class="text-center">
                                    <?php echo number_format($capacity); ?>
                                    <div class="progress mt-1" style="height: 4px; width: 80px; margin: 0 auto;">
                                        <div class="progress-bar <?php echo $statusClass; ?>" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center fw-bold <?php echo $available <= 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format($available); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $statusClass; ?> rounded-pill"><?php echo $statusText; ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if ($menu_context == 'evacuee'): ?>
                                            <a href="evacuee_list.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary" title="ดูรายชื่อ">
                                                <i class="fas fa-users"></i> รายชื่อ
                                            </a>
                                        <?php else: ?>
                                            <a href="shelter_form.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-warning" title="แก้ไข">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- [FIXED] Link to NEW shelter_dashboard.php with correct param -->
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

    <!-- VIEW 2: GRID/CARD VIEW -->
    <div id="view-grid" class="d-none">
        <div class="row g-4">
            <?php if (empty($shelters)): ?>
                <div class="col-12 text-center py-5 text-muted">ไม่พบข้อมูลศูนย์พักพิง</div>
            <?php else: ?>
                <?php foreach ($shelters as $s): 
                    $capacity = $s['capacity'] ?? 0;
                    $occupied = $s['current_population'] ?? 0;
                    $percent = ($capacity > 0) ? ($occupied / $capacity) * 100 : 0;
                    
                    $statusColor = 'secondary';
                    $statusText = 'ปิด';
                    if ($s['status'] == 'open') { $statusColor = 'success'; $statusText = 'เปิดรับ'; }
                    elseif ($s['status'] == 'full') { $statusColor = 'warning'; $statusText = 'เต็ม'; }

                    $district = $s['district'] ?? '';
                    $province = $s['province'] ?? '';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shelter-card h-100">
                        <div class="shelter-img-placeholder">
                            <i class="fas fa-campground"></i>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold text-primary mb-0 text-truncate" style="max-width: 70%;">
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </h5>
                                <span class="badge bg-<?php echo $statusColor; ?>"><?php echo $statusText; ?></span>
                            </div>
                            <p class="card-text text-muted small mb-3">
                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($s['location']); ?><br>
                                <?php if($district || $province): ?>
                                    อ.<?php echo htmlspecialchars($district); ?> จ.<?php echo htmlspecialchars($province); ?>
                                <?php endif; ?>
                            </p>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>ความหนาแน่น</span>
                                    <span class="fw-bold"><?php echo number_format($occupied); ?> / <?php echo number_format($capacity); ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $statusColor; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?php if ($menu_context == 'evacuee'): ?>
                                    <a href="evacuee_list.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-users me-1"></i> ดูรายชื่อผู้พักพิง
                                    </a>
                                <?php else: ?>
                                    <div class="btn-group w-100">
                                        <a href="shelter_form.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-warning btn-sm">แก้ไข</a>
                                        <!-- [FIXED] Link to NEW shelter_dashboard.php with correct param -->
                                        <a href="shelter_dashboard.php?shelter_id=<?php echo $s['id']; ?>" class="btn btn-outline-info btn-sm">Dashboard</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

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