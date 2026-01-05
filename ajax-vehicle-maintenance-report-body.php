<?php
// ajax-vehicle-maintenance-report-body.php

// We don’t want browsers (or proxies) caching this fragment, because the
// dashboard “selected months” + totals can change per user.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

require_once 'connections/connection.php';

$category = 'Vehicle Maintenance';

// This report supports user-specific month selections, so we read the logged-in HRIS id.
// If no session/user, it falls back to “global” selection (no user filter).
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$user_id = $_SESSION['hris'] ?? '';

$user_clause = '';
if ($user_id !== '') {
  $user_id_esc = mysqli_real_escape_string($conn, $user_id);
  $user_clause = " AND user_id='{$user_id_esc}'";
}

// Hard-coded FY window used by this report page (Apr 2025 -> Mar 2026)
$fyStart = new DateTime('2025-04-01');
$fyEnd   = new DateTime('2026-03-31');

/*
  How the numbers are calculated (matching your current report logic):
  - Month grouping is based on report_date (not created_at / applicable_month etc.)
  - Tire amount comes from tire-items table if available; otherwise falls back to vm.price
  - Wheel alignment is counted separately (wheel_alignment_amount)
  - “Running Repairs” supports both legacy label 'Other' and the newer 'Running Repairs'
  - Actual totals are the sum of: tire + alignment + battery + ac + running_repairs + service + licensing
*/

$sql = "
SELECT
  m.month_name AS Month,
  COALESCE(b.budget_amount, 0) AS budget_amount,
  COALESCE(a.tire, 0) AS tire,
  COALESCE(a.alignment, 0) AS alignment,
  COALESCE(a.battery, 0) AS battery,
  COALESCE(a.ac, 0) AS ac,
  COALESCE(a.running_repairs, 0) AS running_repairs,
  COALESCE(a.service, 0) AS service,
  COALESCE(a.licensing, 0) AS licensing,
  (
    COALESCE(a.tire, 0) + COALESCE(a.alignment, 0) + COALESCE(a.battery, 0) + COALESCE(a.ac, 0) +
    COALESCE(a.running_repairs, 0) + COALESCE(a.service, 0) + COALESCE(a.licensing, 0)
  ) AS total_actual
FROM
(
  /* Build the month list from anything that has data (budget or approved actuals) */
  SELECT budget_month AS month_name
  FROM tbl_admin_budget_vehicle_maintenance
  WHERE budget_month IS NOT NULL AND TRIM(budget_month) <> ''

  UNION

  SELECT DATE_FORMAT(report_date, '%M %Y')
  FROM tbl_admin_vehicle_maintenance
  WHERE status='Approved' AND report_date IS NOT NULL

  UNION

  SELECT DATE_FORMAT(report_date, '%M %Y')
  FROM tbl_admin_vehicle_service
  WHERE status='Approved' AND report_date IS NOT NULL

  UNION

  SELECT DATE_FORMAT(report_date, '%M %Y')
  FROM tbl_admin_vehicle_licensing_insurance
  WHERE status='Approved' AND report_date IS NOT NULL
) m

/* Budget per month */
LEFT JOIN (
  SELECT budget_month,
         SUM(CAST(REPLACE(amount, ',', '') AS DECIMAL(15,2))) AS budget_amount
  FROM tbl_admin_budget_vehicle_maintenance
  WHERE budget_month IS NOT NULL AND TRIM(budget_month) <> ''
  GROUP BY budget_month
) b ON b.budget_month = m.month_name

/* Actuals per month (merged from maintenance + service + licensing) */
LEFT JOIN (
  SELECT month_name,
         SUM(tire) AS tire,
         SUM(alignment) AS alignment,
         SUM(battery) AS battery,
         SUM(ac) AS ac,
         SUM(running_repairs) AS running_repairs,
         SUM(service) AS service,
         SUM(licensing) AS licensing
  FROM (
    /* Maintenance:
       - tire uses tire-items sum if it exists, otherwise vm.price
       - alignment comes from wheel_alignment_amount only for Tire entries
    */
    SELECT
      DATE_FORMAT(vm.report_date, '%M %Y') AS month_name,

      SUM(
        CASE
          WHEN vm.maintenance_type='Tire'
          THEN COALESCE(ti.tire_sum, CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2)))
          ELSE 0
        END
      ) AS tire,

      SUM(
        CASE
          WHEN vm.maintenance_type='Tire'
          THEN CAST(REPLACE(COALESCE(vm.wheel_alignment_amount,'0'), ',', '') AS DECIMAL(15,2))
          ELSE 0
        END
      ) AS alignment,

      SUM(CASE WHEN vm.maintenance_type='Battery' THEN CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2)) ELSE 0 END) AS battery,
      SUM(CASE WHEN vm.maintenance_type='AC'      THEN CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2)) ELSE 0 END) AS ac,

      SUM(
        CASE
          WHEN vm.maintenance_type IN ('Other','Running Repairs')
          THEN CAST(REPLACE(COALESCE(vm.price,'0'), ',', '') AS DECIMAL(15,2))
          ELSE 0
        END
      ) AS running_repairs,

      0 AS service,
      0 AS licensing
    FROM tbl_admin_vehicle_maintenance vm
    LEFT JOIN (
      SELECT maintenance_id,
             SUM(CAST(REPLACE(COALESCE(tire_price,'0'), ',', '') AS DECIMAL(15,2))) AS tire_sum
      FROM tbl_admin_vehicle_maintenance_tire_items
      GROUP BY maintenance_id
    ) ti ON ti.maintenance_id = vm.id
    WHERE vm.status='Approved' AND vm.report_date IS NOT NULL
    GROUP BY month_name

    UNION ALL

    /* Service */
    SELECT
      DATE_FORMAT(report_date, '%M %Y') AS month_name,
      0 AS tire,
      0 AS alignment,
      0 AS battery,
      0 AS ac,
      0 AS running_repairs,
      SUM(CAST(REPLACE(COALESCE(amount,'0'), ',', '') AS DECIMAL(15,2))) AS service,
      0 AS licensing
    FROM tbl_admin_vehicle_service
    WHERE status='Approved' AND report_date IS NOT NULL
    GROUP BY month_name

    UNION ALL

    /* Licensing */
    SELECT
      DATE_FORMAT(report_date, '%M %Y') AS month_name,
      0 AS tire,
      0 AS alignment,
      0 AS battery,
      0 AS ac,
      0 AS running_repairs,
      0 AS service,
      SUM(
        CAST(REPLACE(COALESCE(emission_test_amount, '0'), ',', '') AS DECIMAL(15,2)) +
        CAST(REPLACE(COALESCE(revenue_license_amount, '0'), ',', '') AS DECIMAL(15,2)) +
        CAST(REPLACE(COALESCE(insurance_amount, '0'), ',', '') AS DECIMAL(15,2))
      ) AS licensing
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE status='Approved' AND report_date IS NOT NULL
    GROUP BY month_name
  ) allx
  GROUP BY month_name
) a ON a.month_name = m.month_name

/* Keep only valid, non-empty months inside the FY window */
WHERE m.month_name IS NOT NULL AND m.month_name <> ''
  AND STR_TO_DATE(CONCAT('01 ', m.month_name), '%d %M %Y')
      BETWEEN '2025-04-01' AND '2026-03-31'

ORDER BY STR_TO_DATE(m.month_name, '%M %Y');
";

$res = $conn->query($sql);
if(!$res){
  echo '<div class="alert alert-danger">Query failed: '.htmlspecialchars($conn->error).'</div>';
  exit;
}

/* Pull previously saved dashboard selections for this report/category */
$category_esc = mysqli_real_escape_string($conn, $category);
$selMap = [];

$selRes = $conn->query("
  SELECT month_name, is_selected
  FROM tbl_admin_dashboard_month_selection
  WHERE category='{$category_esc}'{$user_clause}
");

if($selRes){
  while($r = $selRes->fetch_assoc()){
    $selMap[$r['month_name']] = (($r['is_selected'] ?? '') === 'yes');
  }
}

/* Used to generate stable row ids for scrolling/highlighting */
function slugify_row($s){
  return preg_replace('/[^a-z0-9]+/i', '-', strtolower($s ?? ''));
}

$rows = [];
while($r = $res->fetch_assoc()){
  $m = $r['Month'];

  // Sanity check: only keep months that parse correctly and fall within the FY window
  $d = DateTime::createFromFormat('F Y', $m);
  if(!$d || $d < $fyStart || $d > $fyEnd) continue;

  // Cast everything to float so totals/variance behave consistently
  $budget = (float)$r['budget_amount'];
  $tire   = (float)$r['tire'];
  $align  = (float)$r['alignment'];
  $bat    = (float)$r['battery'];
  $ac     = (float)$r['ac'];
  $rr     = (float)$r['running_repairs'];
  $svc    = (float)$r['service'];
  $lic    = (float)$r['licensing'];

  $total = $tire + $align + $bat + $ac + $rr + $svc + $lic;

  // Match your other reports: don’t show months with no actual spend
  if($total <= 0) continue;

  // Variance = (budget - actual) / budget
  $variance = $budget > 0 ? round((($budget - $total) / $budget) * 100) : null;

  $rows[] = [
    'month'    => $m,
    'budget'   => $budget,
    'tire'     => $tire,
    'align'    => $align,
    'battery'  => $bat,
    'ac'       => $ac,
    'rr'       => $rr,
    'service'  => $svc,
    'lic'      => $lic,
    'total'    => $total,
    'variance' => $variance,
    'checked'  => !empty($selMap[$m]) ? 'checked' : ''
  ];
}
?>

<style>
/* Keep the toggle compact so the “Select/Remark” column stays tidy */
.table .form-switch{ padding-left:0; min-height:0; }
.form-switch .form-check-input{ width:2.6em; height:1.3em; cursor:pointer; }

.toggle-cell .toggle-wrap{
  display:inline-flex;
  align-items:center;
  gap:.5rem;
}

/* Used to highlight negative variances and budget overruns */
.text-danger{ color:#d9534f!important; font-weight:bold; }

/* Row highlight used when the page wants to focus a specific record */
.row-focus{ background:#fdf6e3!important; }
</style>

<div class="table-responsive">
<table class="table table-bordered table-sm text-center wide-table">
<thead class="table-light">
<tr>
  <th>#</th>
  <th>Month</th>
  <th>Budget (Rs)</th>
  <th>Tire</th>
  <th>Wheel Alignment</th>
  <th>Battery</th>
  <th>AC</th>
  <th>Running Repairs</th>
  <th>Service</th>
  <th>Licensing</th>
  <th>Total</th>
  <th>Variance (%)</th>
  <th>Select / Remark</th>
</tr>
</thead>
<tbody>

<?php
$i = 1;

// These totals are based only on checked months (same behavior as the UI footer recalc)
$tb = $tt = $tal = $tbty = $ta = $trr = $ts = $tl = $tact = 0;

foreach($rows as $r):
  if(!empty($r['checked'])){
    $tb   += $r['budget'];
    $tt   += $r['tire'];
    $tal  += $r['align'];
    $tbty += $r['battery'];
    $ta   += $r['ac'];
    $trr  += $r['rr'];
    $ts   += $r['service'];
    $tl   += $r['lic'];
    $tact += $r['total'];
  }
?>
<tr class="report-row"
    data-category="<?=htmlspecialchars($category)?>"
    data-record="<?=htmlspecialchars($r['month'])?>"
    data-budget="<?=$r['budget']?>"
    data-actual="<?=$r['total']?>"
    data-tire="<?=$r['tire']?>"
    data-alignment="<?=$r['align']?>"
    data-battery="<?=$r['battery']?>"
    data-ac="<?=$r['ac']?>"
    data-other="<?=$r['rr']?>"
    data-service="<?=$r['service']?>"
    data-licensing="<?=$r['lic']?>"
    id="row-<?=slugify_row($category.'-'.$r['month'])?>">

  <td><?=$i++?></td>
  <td><?=htmlspecialchars($r['month'])?></td>
  <td><?=number_format($r['budget'],2)?></td>
  <td><?=number_format($r['tire'],2)?></td>
  <td><?=number_format($r['align'],2)?></td>
  <td><?=number_format($r['battery'],2)?></td>
  <td><?=number_format($r['ac'],2)?></td>
  <td><?=number_format($r['rr'],2)?></td>
  <td><?=number_format($r['service'],2)?></td>
  <td><?=number_format($r['lic'],2)?></td>

  <!-- Turn red when we spent more than the budget -->
  <td class="<?=($r['total']>$r['budget'])?'text-danger':''?>">
    <?=number_format($r['total'],2)?>
  </td>

  <!-- Negative variance means actual > budget -->
  <td class="<?=($r['variance']<0)?'text-danger':''?>">
    <?=$r['variance']!==null?$r['variance'].'%':'N/A'?>
  </td>

  <td class="toggle-cell">
    <div class="toggle-wrap">
      <!-- Checkbox drives dashboard selection + footer totals -->
      <div class="form-check form-switch m-0">
        <input type="checkbox"
               class="form-check-input month-checkbox"
               role="switch"
               data-category="<?=htmlspecialchars($category)?>"
               data-month="<?=htmlspecialchars($r['month'])?>"
               <?=$r['checked']?>>
      </div>

      <!-- Remarks button opens the remarks modal for this month -->
      <button class="btn btn-sm btn-outline-secondary open-remarks"
              data-category="<?=htmlspecialchars($category)?>"
              data-record="<?=htmlspecialchars($r['month'])?>">💬</button>
    </div>
  </td>
</tr>
<?php endforeach;

$tv = $tb > 0 ? round((($tb - $tact) / $tb) * 100) : null;
?>

<tr class="fw-bold table-light">
  <td colspan="2" class="text-end">Total</td>
  <td id="footer-total-budget"><?=number_format($tb,2)?></td>
  <td id="footer-total-tire"><?=number_format($tt,2)?></td>
  <td id="footer-total-alignment"><?=number_format($tal,2)?></td>
  <td id="footer-total-battery"><?=number_format($tbty,2)?></td>
  <td id="footer-total-ac"><?=number_format($ta,2)?></td>
  <td id="footer-total-other"><?=number_format($trr,2)?></td>
  <td id="footer-total-service"><?=number_format($ts,2)?></td>
  <td id="footer-total-licensing"><?=number_format($tl,2)?></td>
  <td id="footer-total-actual"><?=number_format($tact,2)?></td>

  <td id="footer-total-variance" class="<?=($tv<0)?'text-danger':''?>">
    <?=$tv!==null?$tv.'%':'N/A'?>
  </td>

  <td></td>
</tr>

</tbody>
</table>
</div>

<!-- Saves the currently checked months back into tbl_admin_dashboard_month_selection -->
<button id="update-selection" class="btn btn-primary mt-3">Update Dashboard Selection</button>
