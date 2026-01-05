<?php
session_start();
require_once 'connections/connection.php';
header('Content-Type: application/json; charset=utf-8');

function out($a){ while(ob_get_level()) ob_end_clean(); echo json_encode($a, JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function to_float($v){
  $s = (string)($v ?? '');
  if ($s === '') return 0.0;
  return floatval(str_replace(',','',$s));
}
function n2($v){
  $s = (string)($v ?? '');
  if ($s === '') return '';
  $n = floatval(str_replace(',','',$s));
  return is_finite($n) ? number_format($n, 2) : e($s);
}

/* secure attachment row (does NOT show folder path) */
function attachment_row_secure($label, $type, $id, $field, $index=null, $savedPath=''){
  $savedPath = str_replace('\\','/', trim((string)$savedPath));
  if ($savedPath === '') return '';

  $fileName = basename($savedPath);
  $fileName = $fileName ?: 'attachment';

  $q = 'vehicle-approvals-pro-file.php?type='.urlencode($type)
     .'&id='.(int)$id
     .'&field='.urlencode($field);

  if ($index !== null) $q .= '&i='.(int)$index;

  return '
    <div class="list-group-item d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">'.e($label).'</div>
        <div class="small text-muted">'.e($fileName).'</div>
      </div>
      <div>
        <a class="btn btn-sm btn-outline-primary" href="'.e($q).'" target="_blank" rel="noopener noreferrer">View</a>
      </div>
    </div>';
}

/* dedupe key */
function norm_key($p){
  $p = str_replace('\\','/', trim((string)$p));
  return strtolower($p);
}

$id   = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

$map = [
  'maintenance' => 'tbl_admin_vehicle_maintenance',
  'service'     => 'tbl_admin_vehicle_service',
  'license'     => 'tbl_admin_vehicle_licensing_insurance',
];
$table = $map[$type] ?? '';
if(!$id || !$table) out(['html'=>'<div class="alert alert-danger">Invalid request.</div>']);

$conn->set_charset('utf8mb4');

/* record + entered-by */
$sql = "SELECT t.*, u.name AS entered_name, u.hris AS entered_hris
        FROM {$table} t
        LEFT JOIN tbl_admin_users u ON t.entered_by = u.hris
        WHERE t.id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if(!$stmt) out(['html'=>'<div class="alert alert-danger">SQL error.</div>']);
$stmt->bind_param("i", $id);
$stmt->execute();
$rs = $stmt->get_result();
if(!$rs || !$rs->num_rows) out(['html'=>'<div class="alert alert-danger">Record not found.</div>']);
$row = $rs->fetch_assoc();
$stmt->close();

$entered_hris   = $row['entered_hris'] ?? $row['entered_by'] ?? '';
$entered_name   = $row['entered_name'] ?? '';
$entered_by_txt = trim($entered_name) !== '' ? trim($entered_name).' ('.$entered_hris.')' : (string)$entered_hris;

/* tire items */
$tireItems = [];
$tireSum = 0.0;

if ($type === 'maintenance') {
  $mt = strtolower(trim((string)($row['maintenance_type'] ?? '')));
  if ($mt === 'tire') {
    $stmt2 = $conn->prepare("
      SELECT tire_no, tire_brand, tire_price
      FROM tbl_admin_vehicle_maintenance_tire_items
      WHERE maintenance_id = ?
      ORDER BY tire_no ASC
    ");
    if ($stmt2) {
      $stmt2->bind_param("i", $id);
      $stmt2->execute();
      $r2 = $stmt2->get_result();
      while ($r2 && ($ti = $r2->fetch_assoc())) {
        $tireItems[] = $ti;
        $tireSum += to_float($ti['tire_price'] ?? 0);
      }
      $stmt2->close();
    }
  }
}

$wheelAlign = to_float($row['wheel_alignment_amount'] ?? 0);
$grandTotalDisplay = $tireSum + $wheelAlign;

/* attachments build (DEDUPED) */
$attHtml = '';
$seen = []; // normalized path => 1

if ($type === 'maintenance') {
  // Bill
  $p = (string)($row['bill_upload'] ?? '');
  if ($p !== '') {
    $k = norm_key($p);
    if (!isset($seen[$k])) {
      $seen[$k] = 1;
      $attHtml .= attachment_row_secure('Bill', $type, $id, 'bill_upload', null, $p);
    }
  }

  // Warranty Card
  $p = (string)($row['warranty_card_upload'] ?? '');
  if ($p !== '') {
    $k = norm_key($p);
    if (!isset($seen[$k])) {
      $seen[$k] = 1;
      $attHtml .= attachment_row_secure('Warranty Card', $type, $id, 'warranty_card_upload', null, $p);
    }
  }

  // image_path (JSON list)
  $img = (string)($row['image_path'] ?? '');
  if ($img !== '') {
    $decoded = json_decode($img, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      $attNo = 1;
      foreach ($decoded as $idx => $pp) {
        $pp = (string)$pp;
        if ($pp === '') continue;

        $k = norm_key($pp);
        if (isset($seen[$k])) continue; // ✅ skip duplicates (bill/warranty or repeated)

        $seen[$k] = 1;
        $attHtml .= attachment_row_secure('Attachment '.$attNo++, $type, $id, 'image_path', (int)$idx, $pp);
      }
    } else {
      // older non-json
      $k = norm_key($img);
      if (!isset($seen[$k])) {
        $seen[$k] = 1;
        $attHtml .= attachment_row_secure('Attachment 1', $type, $id, 'image_path', 0, $img);
      }
    }
  }
}

if ($type === 'service') {
  $p = (string)($row['bill_upload'] ?? '');
  if ($p !== '') {
    $k = norm_key($p);
    if (!isset($seen[$k])) {
      $seen[$k] = 1;
      $attHtml .= attachment_row_secure('Service Bill', $type, $id, 'bill_upload', null, $p);
    }
  }
}

ob_start(); ?>
<div class="row g-3">

  <div class="col-md-6">
    <label class="form-label">SR Number</label>
    <input type="text" class="form-control readonly" value="<?= e($row['sr_number']) ?>" readonly>
  </div>

  <div class="col-md-6">
    <label class="form-label">Vehicle Number</label>
    <input type="text" class="form-control readonly" value="<?= e($row['vehicle_number']) ?>" readonly>
  </div>

  <?php if($type === 'maintenance'): ?>
    <?php
      $mtRaw = (string)($row['maintenance_type'] ?? '');
      $dateVal = in_array($mtRaw, ['Battery','Tire'], true) ? ($row['purchase_date'] ?? '') : ($row['repair_date'] ?? '');
    ?>

    <div class="col-md-6">
      <label class="form-label">Maintenance Type</label>
      <input type="text" class="form-control readonly" value="<?= e($mtRaw) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Date</label>
      <input type="text" class="form-control readonly" value="<?= e($dateVal) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Shop</label>
      <input type="text" class="form-control readonly" value="<?= e($row['shop_name']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Driver</label>
      <input type="text" class="form-control readonly" value="<?= e($row['driver_name']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Mileage</label>
      <input type="text" class="form-control readonly" value="<?= e($row['mileage']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Saved Total Amount</label>
      <input type="text" class="form-control readonly" value="<?= n2($row['price']) ?>" readonly>
      <div class="small text-muted mt-1">(This is what was saved in the main maintenance record)</div>
    </div>

    <?php if($mtRaw === 'Tire'): ?>
      <div class="col-md-6">
        <label class="form-label">Tire Size</label>
        <input type="text" class="form-control readonly" value="<?= e($row['tire_size']) ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Tire Quantity</label>
        <input type="text" class="form-control readonly" value="<?= e($row['tire_quantity']) ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Wheel Alignment Amount</label>
        <input type="text" class="form-control readonly" value="<?= n2($row['wheel_alignment_amount']) ?>" readonly>
      </div>

      <div class="col-md-6">
        <label class="form-label">Grand Total (Tires + Alignment)</label>
        <input type="text" class="form-control readonly" value="<?= number_format($grandTotalDisplay, 2) ?>" readonly>
      </div>

      <div class="col-12">
        <label class="form-label">Tire Items (Brand / Price)</label>

        <?php if(!count($tireItems)): ?>
          <div class="alert alert-secondary mb-0">
            No per-tire details found.
            <div class="small mt-1">If this is an old entry, it won’t have tire item rows.</div>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">Tire #</th>
                  <th>Brand</th>
                  <th style="width:180px;">Price</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($tireItems as $ti): ?>
                  <tr>
                    <td><?= e($ti['tire_no']) ?></td>
                    <td><?= e($ti['tire_brand']) ?></td>
                    <td><?= n2($ti['tire_price']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="table-light">
                  <td colspan="2" class="text-end fw-semibold">Tires Sum</td>
                  <td class="fw-semibold"><?= number_format($tireSum, 2) ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if(in_array($mtRaw, ['AC','Running Repairs'], true)): ?>
      <div class="col-12">
        <label class="form-label">Problem Description</label>
        <textarea class="form-control readonly" rows="2" readonly><?= e($row['problem_description']) ?></textarea>
      </div>
    <?php endif; ?>

  <?php elseif($type === 'service'): ?>

    <div class="col-md-6">
      <label class="form-label">Service Date</label>
      <input type="text" class="form-control readonly" value="<?= e($row['service_date']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Shop / Garage</label>
      <input type="text" class="form-control readonly" value="<?= e($row['shop_name']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Amount</label>
      <input type="text" class="form-control readonly" value="<?= n2($row['amount']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Driver</label>
      <input type="text" class="form-control readonly" value="<?= e($row['driver_name']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Previous Meter</label>
      <input type="text" class="form-control readonly" value="<?= e($row['meter_reading']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Next Service Meter</label>
      <input type="text" class="form-control readonly" value="<?= e($row['next_service_meter']) ?>" readonly>
    </div>

  <?php else: /* license */ ?>

    <div class="col-md-6">
      <label class="form-label">Emission Test Date</label>
      <input type="text" class="form-control readonly" value="<?= e($row['emission_test_date']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Emission Amount</label>
      <input type="text" class="form-control readonly" value="<?= n2($row['emission_test_amount']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Revenue License Date</label>
      <input type="text" class="form-control readonly" value="<?= e($row['revenue_license_date']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Revenue License Amount</label>
      <input type="text" class="form-control readonly" value="<?= n2($row['revenue_license_amount']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Insurance Amount</label>
      <input type="text" class="form-control readonly" value="<?= n2($row['insurance_amount']) ?>" readonly>
    </div>

    <div class="col-md-6">
      <label class="form-label">Handled By</label>
      <input type="text" class="form-control readonly" value="<?= e($row['person_handled']) ?>" readonly>
    </div>

  <?php endif; ?>

  <div class="col-md-6">
    <label class="form-label">Status</label>
    <input type="text" class="form-control readonly" value="<?= e($row['status']) ?>" readonly>
  </div>

  <div class="col-md-6">
    <label class="form-label">Created At</label>
    <input type="text" class="form-control readonly" value="<?= e($row['created_at']) ?>" readonly>
  </div>

  <div class="col-md-6">
    <label class="form-label">Entered By</label>
    <input type="text" class="form-control readonly" value="<?= e($entered_by_txt) ?>" readonly>
  </div>

  <!-- Attachments -->
  <div class="col-12">
    <label class="form-label">Attachments</label>
    <?php if (trim($attHtml) === ''): ?>
      <div class="alert alert-secondary mb-0">No attachments found.</div>
    <?php else: ?>
      <div class="list-group"><?= $attHtml ?></div>
    <?php endif; ?>
  </div>

</div>
<?php
out(['html' => ob_get_clean()]);
