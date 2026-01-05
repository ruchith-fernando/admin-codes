<?php
require_once 'connections/connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$month_year = trim($_POST['month_year'] ?? '');
if($month_year === ''){
  echo json_encode(['success'=>true,'exists'=>false]);
  exit;
}

// Budget (optional)
$bud = $conn->prepare("SELECT budget_amount FROM tbl_admin_budget_tea_service WHERE month_year=? LIMIT 1");
$bud->bind_param("s", $month_year);
$bud->execute();
$budRow = $bud->get_result()->fetch_assoc();
$budget_amount = $budRow ? (float)$budRow['budget_amount'] : 0.0;

/**
 * Item master list (Tea, Coffee, etc)
 * We use ACTIVE items so the columns are stable (and “Tea/Coffee counts” always show).
 */
$itemList = [];
$itemIds  = [];

$itemsRes = mysqli_query($conn, "SELECT id, item_name FROM tbl_admin_tea_items WHERE is_active=1 ORDER BY sort_order, item_name");
if($itemsRes){
  while($it = mysqli_fetch_assoc($itemsRes)){
    $iid = (int)$it['id'];
    $itemIds[] = $iid;
    $itemList[$iid] = $it['item_name'];
  }
}

/**
 * Preload per-invoice per-item units for the selected month
 * unitMap[hdr_id][item_id] = units
 */
$unitMap = [];
$uStmt = $conn->prepare("
  SELECT d.hdr_id, d.item_id, SUM(d.units) AS units
  FROM tbl_admin_tea_service_dtl d
  INNER JOIN tbl_admin_tea_service_hdr h ON h.id = d.hdr_id
  WHERE h.month_year=?
  GROUP BY d.hdr_id, d.item_id
");
$uStmt->bind_param("s", $month_year);
$uStmt->execute();
$uRes = $uStmt->get_result();
while($u = $uRes->fetch_assoc()){
  $hid = (int)$u['hdr_id'];
  $iid = (int)$u['item_id'];
  $unitMap[$hid][$iid] = (int)$u['units'];
}

$stmt = $conn->prepare("
  SELECT h.id, h.month_year, h.floor_id, h.sr_number, h.ot_amount,
         h.total_price, h.sscl_amount, h.vat_amount, h.grand_total,
         h.approval_status, h.entered_name, h.entered_hris, h.entered_at,
         f.floor_name, f.floor_no
  FROM tbl_admin_tea_service_hdr h
  INNER JOIN tbl_admin_floors f ON f.id = h.floor_id
  WHERE h.month_year=?
  ORDER BY f.floor_no, f.floor_name
");
$stmt->bind_param("s", $month_year);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows === 0){
  echo json_encode(['success'=>true,'exists'=>false]);
  exit;
}

$rows = [];

$sumGrand = 0.0;
$sumItems = 0.0;
$sumSscl = 0.0;
$sumVat = 0.0;
$sumOt = 0.0;
$sumUnits = 0;

$sumApproved = 0.0;
$counts = ['pending'=>0,'approved'=>0,'rejected'=>0,'other'=>0];

$sumItemUnits = []; // totals per item_id
foreach($itemIds as $iid){ $sumItemUnits[$iid] = 0; }

while($r = $res->fetch_assoc()){
  $hid = (int)$r['id'];

  // build per-item units for this invoice
  $perItem = [];
  $unitsTotal = 0;
  foreach($itemIds as $iid){
    $u = isset($unitMap[$hid][$iid]) ? (int)$unitMap[$hid][$iid] : 0;
    $perItem[$iid] = $u;
    $unitsTotal += $u;
    $sumItemUnits[$iid] += $u;
  }

  $status = strtolower(trim($r['approval_status'] ?? 'pending'));
  if(!isset($counts[$status])) $counts['other']++; else $counts[$status]++;

  $g = (float)$r['grand_total'];
  $sumGrand += $g;
  $sumItems += (float)$r['total_price'];
  $sumSscl  += (float)$r['sscl_amount'];
  $sumVat   += (float)$r['vat_amount'];
  $sumOt    += (float)$r['ot_amount'];

  $sumUnits += $unitsTotal;
  if($status === 'approved') $sumApproved += $g;

  $r['_item_units'] = $perItem;
  $r['units_total'] = $unitsTotal;
  $rows[] = $r;
}

function badgeClass($status){
  $status = strtolower($status);
  if($status === 'approved') return 'success';
  if($status === 'rejected') return 'danger';
  if($status === 'pending') return 'warning';
  return 'secondary';
}

$html = "";
$html .= "<div class='card border shadow-sm'>";
$html .= "  <div class='card-header bg-light'>";
$html .= "    <div class='d-flex flex-wrap justify-content-between align-items-center gap-2'>";
$html .= "      <div><b>Invoice Summary — ".esc($month_year)."</b></div>";
$html .= "      <div class='small text-muted'>Total Invoices: <b>".count($rows)."</b> | Approved: <b>".$counts['approved']."</b> | Pending: <b>".$counts['pending']."</b> | Rejected: <b>".$counts['rejected']."</b></div>";
$html .= "    </div>";
$html .= "  </div>";
$html .= "  <div class='table-responsive'>";
$html .= "    <table class='table table-sm table-bordered mb-0 align-middle'>";
$html .= "      <thead class='table-light'>";
$html .= "        <tr>";
$html .= "          <th>Floor</th>";
$html .= "          <th>Status</th>";

/* dynamic item columns (Tea, Coffee, etc) */
foreach($itemIds as $iid){
  $html .= "          <th class='text-end'>".esc($itemList[$iid])."</th>";
}

$html .= "          <th class='text-end'>Units</th>";
$html .= "          <th class='text-end'>Items Total</th>";
$html .= "          <th class='text-end'>SSCL</th>";
$html .= "          <th class='text-end'>VAT</th>";
$html .= "          <th class='text-end'>OT</th>";
$html .= "          <th class='text-end'>Grand</th>";
$html .= "          <th>Entered By</th>";
$html .= "        </tr>";
$html .= "      </thead>";
$html .= "      <tbody>";

foreach($rows as $r){
  $status = strtolower(trim($r['approval_status'] ?? 'pending'));
  $badge = badgeClass($status);
  $html .= "<tr>";
  $html .= "  <td>".esc($r['floor_name'])."</td>";
  $html .= "  <td><span class='badge bg-{$badge}'>".strtoupper(esc($status))."</span></td>";

  // per item counts
  foreach($itemIds as $iid){
    $u = (int)($r['_item_units'][$iid] ?? 0);
    $html .= "  <td class='text-end'>".number_format($u)."</td>";
  }

  $html .= "  <td class='text-end'><b>".number_format((int)$r['units_total'])."</b></td>";
  $html .= "  <td class='text-end'>".number_format((float)$r['total_price'],2)."</td>";
  $html .= "  <td class='text-end'>".number_format((float)$r['sscl_amount'],2)."</td>";
  $html .= "  <td class='text-end'>".number_format((float)$r['vat_amount'],2)."</td>";
  $html .= "  <td class='text-end'>".number_format((float)$r['ot_amount'],2)."</td>";
  $html .= "  <td class='text-end'><b>".number_format((float)$r['grand_total'],2)."</b></td>";
  $html .= "  <td>".esc($r['entered_hris'])."</td>";
  $html .= "</tr>";
}

$html .= "      </tbody>";
$html .= "      <tfoot class='table-light'>";
$html .= "        <tr>";
$html .= "          <th colspan='2' class='text-end'>TOTAL</th>";

foreach($itemIds as $iid){
  $html .= "          <th class='text-end'>".number_format((int)$sumItemUnits[$iid])."</th>";
}

$html .= "          <th class='text-end'><b>".number_format($sumUnits)."</b></th>";
$html .= "          <th class='text-end'>".number_format($sumItems,2)."</th>";
$html .= "          <th class='text-end'>".number_format($sumSscl,2)."</th>";
$html .= "          <th class='text-end'>".number_format($sumVat,2)."</th>";
$html .= "          <th class='text-end'>".number_format($sumOt,2)."</th>";
$html .= "          <th class='text-end'><b>".number_format($sumGrand,2)."</b></th>";
$html .= "          <th></th>";
$html .= "        </tr>";
$html .= "      </tfoot>";

$html .= "    </table>";
$html .= "  </div>";
$html .= "</div>";

echo json_encode([
  'success' => true,
  'exists' => true,
  'html' => $html
]);
