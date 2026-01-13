<?php
// asset-card-print.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
date_default_timezone_set('Asia/Colombo');

// Shared-host safe session
if (session_status() === PHP_SESSION_NONE) {
  $cookie = session_get_cookie_params();
  session_set_cookie_params([
    'lifetime' => $cookie['lifetime'],
    'path'     => '/',
    'domain'   => $cookie['domain'],
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
  session_start();
}

$uid = (int)($_SESSION['id'] ?? 0);
$logged = !empty($_SESSION['loggedin']);
if (!$logged || $uid <= 0) { die('Session expired. Please login again.'); }

// Print options
// ?id=123 prints one asset
// ?status=APPROVED&limit=60 prints many
$id     = (int)($_GET['id'] ?? 0);
$status = strtoupper(trim($_GET['status'] ?? 'APPROVED'));
$limit  = (int)($_GET['limit'] ?? 60);
if ($limit <= 0 || $limit > 200) $limit = 60;

$rows = [];

if ($id > 0) {
  $stmt = $conn->prepare("
    SELECT a.id, a.item_code, a.item_name, t.type_name,
           c.category_name, b.budget_name
    FROM tbl_admin_assets a
    JOIN tbl_admin_asset_types t ON t.id=a.asset_type_id
    JOIN tbl_admin_categories c ON c.id=a.category_id
    JOIN tbl_admin_budgets b ON b.id=a.budget_id
    WHERE a.id=? LIMIT 1
  ");
  $stmt->bind_param("i",$id);
  $stmt->execute();
  $r = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($r) $rows[] = $r;
} else {
  $stmt = $conn->prepare("
    SELECT a.id, a.item_code, a.item_name, t.type_name,
           c.category_name, b.budget_name
    FROM tbl_admin_assets a
    JOIN tbl_admin_asset_types t ON t.id=a.asset_type_id
    JOIN tbl_admin_categories c ON c.id=a.category_id
    JOIN tbl_admin_budgets b ON b.id=a.budget_id
    WHERE a.status=?
    ORDER BY a.id DESC
    LIMIT ?
  ");
  $stmt->bind_param("si",$status,$limit);
  $stmt->execute();
  $res = $stmt->get_result();
  while($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();
}

$title = ($id > 0) ? "Sticker Print — Asset #{$id}" : "Sticker Print — {$status} (Top {$limit})";
?>
<style>
/* PRINT SETTINGS */
@page { size: A4; margin: 8mm; }

/* STICKER GRID */
.sheet{
  display:grid;
  grid-template-columns: repeat(3, 1fr); /* 3 across */
  gap: 6mm;
}

/* STICKER SIZE (tune later for your label paper) */
.sticker{
  border: 1px dashed #bbb;
  border-radius: 6px;
  padding: 4mm;
  height: 30mm;
  overflow: hidden;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  background:#fff;
}

.sticker .top{
  font-size: 10pt;
  font-weight: 700;
  line-height: 1.1;
  max-height: 12mm;
  overflow:hidden;
}

.sticker .meta{
  font-size: 8pt;
  color:#333;
  margin-top: 1mm;
  line-height:1.1;
  max-height: 8mm;
  overflow:hidden;
}

.barcode-wrap{
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap: 4mm;
  margin-top: 2mm;
}

svg.barcode{
  width: 70%;
  height: 14mm;
}

.code{
  font-size: 9pt;
  font-weight: 700;
  white-space: nowrap;
}

/* MAKE PRINT CLEAN */
@media print{
  .no-print{ display:none !important; }
  .sticker{ border:none; }
  .card{ box-shadow:none !important; border:none !important; }
  .container-fluid{ padding:0 !important; }
  .content{ padding:0 !important; }
}
</style>

<div class="content font-size">
  <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0 text-primary"><?= htmlspecialchars($title) ?></h5>

        <div class="no-print d-flex gap-2">
          <a href="main.php" class="btn btn-outline-secondary btn-sm">Back</a>
          <button class="btn btn-primary btn-sm" onclick="window.print()">Print</button>
        </div>
      </div>

      <div class="text-muted small mb-3">
        Layout: A4 • 3 columns • Adjust sticker size later for label paper.
      </div>

      <?php if (empty($rows)): ?>
        <div class="alert alert-warning">No items found to print.</div>
      <?php else: ?>
        <div class="sheet">
          <?php foreach($rows as $r): ?>
            <div class="sticker">
              <div>
                <div class="top"><?= htmlspecialchars($r['item_name']) ?></div>
                <div class="meta">
                  <?= htmlspecialchars($r['type_name']) ?> •
                  <?= htmlspecialchars($r['category_name']) ?> •
                  <?= htmlspecialchars($r['budget_name']) ?>
                </div>
              </div>

              <div class="barcode-wrap">
                <svg class="barcode" id="bc<?= (int)$r['id'] ?>"></svg>
                <div class="code"><?= htmlspecialchars($r['item_code']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

  </div>
</div>

<script src="assets/js/JsBarcode.all.min.js"></script>
<script>
(function(){
  const items = <?php
    $safe = [];
    foreach($rows as $r){
      $safe[] = ['id'=>(int)$r['id'], 'code'=>$r['item_code']];
    }
    echo json_encode($safe, JSON_UNESCAPED_SLASHES);
  ?>;

  items.forEach(it=>{
    try{
      JsBarcode("#bc"+it.id, it.code, {
        format:"CODE128",
        displayValue:false,
        margin:0
      });
    }catch(e){}
  });

  // Optional auto print:
  // window.onload = ()=> window.print();
})();
</script>
