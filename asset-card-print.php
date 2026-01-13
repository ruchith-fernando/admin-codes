<?php
// asset-card-print.php
require_once 'connections/connection.php';
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
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

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Print Stickers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* A4 page */
    @page { size: A4; margin: 8mm; }
    body { font-family: Arial, sans-serif; margin:0; padding:0; }

    /* Sticker grid */
    .sheet{
      display:grid;
      grid-template-columns: repeat(3, 1fr); /* 3 across */
      gap: 6mm;
    }

    /* Sticker size (adjust later to match your label paper) */
    .sticker{
      border: 1px dashed #bbb;
      border-radius: 6px;
      padding: 4mm;
      height: 30mm;           /* sticker height */
      overflow: hidden;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }

    .top{
      font-size: 10pt;
      font-weight: 700;
      line-height: 1.1;
      max-height: 12mm;
      overflow:hidden;
    }

    .meta{
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

    .controls{
      position: fixed;
      top: 8px;
      right: 8px;
      background:#fff;
      border:1px solid #ddd;
      padding:8px 10px;
      border-radius:8px;
      box-shadow: 0 2px 8px rgba(0,0,0,.1);
      z-index:9999;
    }
    .controls button{ padding:6px 10px; cursor:pointer; }
    @media print{
      .controls{ display:none; }
      .sticker{ border: none; } /* remove dashed borders when printing */
    }
  </style>
</head>
<body>

<div class="controls">
  <button onclick="window.print()">Print</button>
</div>

<?php if (empty($rows)): ?>
  <div style="padding:20px;">No items found to print.</div>
<?php else: ?>
  <div class="sheet">
    <?php foreach($rows as $i => $r): ?>
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
    }catch(e){
      // ignore
    }
  });

  // Optional auto print:
  // window.onload = ()=> window.print();
})();
</script>

</body>
</html>
