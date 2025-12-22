<?php
// inventory_list.php
require_once 'config/db.php';
require_once 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$shelter_id = isset($_GET['shelter_id']) ? $_GET['shelter_id'] : '';

// ดึงข้อมูลศูนย์พักพิง
$shelter = [];
if ($shelter_id) {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->execute([$shelter_id]);
    $shelter = $stmt->fetch();
}

// ดึงรายการสิ่งของ
$items = [];
if ($shelter_id) {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE shelter_id = ? ORDER BY category, item_name");
    $stmt->execute([$shelter_id]);
    $items = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>คลังสิ่งของ - <?php echo htmlspecialchars($shelter['name'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="fas fa-boxes text-primary"></i> คลังสิ่งของและทรัพยากร</h4>
            <span class="text-muted">ศูนย์: <?php echo htmlspecialchars($shelter['name'] ?? 'กรุณาเลือกศูนย์'); ?></span>
        </div>
        <button class="btn btn-success" onclick="openStockModal('in')"><i class="fas fa-plus"></i> รับของเข้า</button>
    </div>

    <?php if(!$shelter_id): ?>
        <div class="alert alert-warning">กรุณาเลือกศูนย์พักพิงก่อนจัดการคลังสินค้า</div>
    <?php else: ?>
        
        <div class="row">
            <?php foreach ($items as $item): ?>
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm h-100 <?php echo ($item['quantity'] <= $item['min_threshold']) ? 'border-danger' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                <span class="badge bg-secondary"><?php echo ucfirst($item['category']); ?></span>
                            </div>
                            
                            <h2 class="text-center my-3 <?php echo ($item['quantity'] <= $item['min_threshold']) ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($item['quantity']); ?> 
                                <small class="fs-6 text-muted"><?php echo $item['unit']; ?></small>
                            </h2>
                            
                            <?php if($item['quantity'] <= $item['min_threshold']): ?>
                                <div class="text-center text-danger small mb-2"><i class="fas fa-exclamation-triangle"></i> ของใกล้หมด</div>
                            <?php endif; ?>

                            <button class="btn btn-outline-danger w-100 btn-sm" onclick="openStockModal('out', <?php echo $item['id']; ?>, '<?php echo $item['item_name']; ?>')">
                                <i class="fas fa-hand-holding-heart"></i> เบิกจ่าย
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- การ์ดเพิ่มรายการใหม่ -->
            <div class="col-md-3 mb-3">
                <div class="card shadow-sm h-100 border-dashed" style="border: 2px dashed #dee2e6; cursor: pointer;" onclick="openNewItemModal()">
                    <div class="card-body d-flex align-items-center justify-content-center text-muted">
                        <div class="text-center">
                            <i class="fas fa-plus-circle fa-2x mb-2"></i>
                            <div>เพิ่มรายการสินค้าใหม่</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Modal จะต้องใส่เพิ่มที่นี่ (ผมขอละไว้ก่อนเพื่อให้โค้ดไม่ยาวเกินไป) -->
<!-- คุณต้องสร้างไฟล์ inventory_action.php เพื่อรับค่า POST จาก Modal ไปบันทึก -->

<?php include 'includes/footer.php'; ?>
</body>
</html>