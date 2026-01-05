<?php
 // water-monthly-fetch.php
 require_once 'connections/connection.php';
 require_once 'includes/userlog.php';
 if (session_status() === PHP_SESSION_NONE) session_start();
 
 header('Content-Type: application/json');
 
 $month = trim($_POST['month'] ?? '');
 if ($month === '') {
     echo json_encode(['error' => 'No month selected']);
     exit;
 }
 
 $monthEsc = mysqli_real_escape_string($conn, $month);
 // ✅ Financial Year budget_year = FY start year (Apr→Mar)
 $ts = strtotime("1 " . $month);
 $y  = (int)date("Y", $ts);
 $mn = (int)date("n", $ts);
 $budget_year = ($mn < 4) ? ($y - 1) : $y;
 
 
 /* -------------------------------------------------------
    CSS
    - highlight over-budget rows (must target TDs because of table-striped)
    - wrap Water Connection(s) and show each connection on new line
 ------------------------------------------------------- */
 $highlight_css = "
 <style>
   /* ✅ Bootstrap table-striped colors TDs, so highlight must color the cells */
   .water-report-table tbody tr.over-budget-row > * {
       background-color: #ffecec !important; /* very light red */
   }
 
   .water-report-table th.conn-col,
   .water-report-table td.conn-col {
       white-space: normal !important;
       word-break: break-word;
       overflow-wrap: anywhere;
       vertical-align: top;
   }
 
   /* optional: keep it readable */
   .water-report-table td.conn-col { max-width: 520px; }
 </style>
 ";
 
 /* -------------------------------------------------------
    1) MASTER BRANCH + REQUIRED CONNECTIONS (ACTIVE TYPES ONLY)
 ------------------------------------------------------- */
 $master = [];
 
 $map_sql = "
     SELECT
         bw.branch_code,
         bw.branch_name,
         bw.water_type_id,
         bw.connection_no,
         wt.water_type_name
     FROM tbl_admin_branch_water bw
     INNER JOIN tbl_admin_water_types wt
         ON wt.water_type_id = bw.water_type_id
     WHERE wt.is_active = 1
     ORDER BY bw.branch_code, wt.water_type_name, bw.connection_no
 ";
 $map_res = mysqli_query($conn, $map_sql);
 
 if ($map_res) {
     while ($r = mysqli_fetch_assoc($map_res)) {
         $code = $r['branch_code'];
 
         if (!isset($master[$code])) {
             $master[$code] = [
                 'branch_name'    => $r['branch_name'] ?? '',
                 'conn_labels'    => [],
                 'required_keys'  => [],
                 'key_to_label'   => [],    
                 'required_count' => 0,
             ];
 
         }
 
         $typeId = (int)$r['water_type_id'];
         $connNo = (int)($r['connection_no'] ?? 1);
         if ($connNo <= 0) $connNo = 1;
 
             $key    = $typeId . '|' . $connNo;
             $label = ($r['water_type_name'] ?? 'Type') . " (Conn " . $connNo . ")";
              $master[$code]['conn_labels'][]    = $label;
              $master[$code]['required_keys'][] = $key;
             $master[$code]['key_to_label'][$key] = $label;
         }
 
     foreach ($master as $code => $m) {
          $master[$code]['required_keys']  = array_values(array_unique($master[$code]['required_keys']));
          $master[$code]['conn_labels']    = array_values(array_unique($master[$code]['conn_labels']));
         $master[$code]['required_count'] = count($master[$code]['required_keys']);
     }
 }
 
 /* -------------------------------------------------------
    2) BUDGET DATA (per branch)
 ------------------------------------------------------- */
 $budget = [];
 $budget_sql = "
     SELECT branch_code, amount AS monthly_amount
     FROM tbl_admin_budget_water
     WHERE budget_year = '" . mysqli_real_escape_string($conn, $budget_year) . "'
 ";
 $budget_res = mysqli_query($conn, $budget_sql);
 if ($budget_res) {
     while ($b = mysqli_fetch_assoc($budget_res)) {
         $budget[$b['branch_code']] = (float)($b['monthly_amount'] ?? 0);
     }
 }
 
 /* -------------------------------------------------------
    3) ACTUAL DATA FOR THE MONTH (ALL STATUSES)
 ------------------------------------------------------- */
 $actualMap = []; // actualMap[branch_code][type|conn] = ['status'=>..., 'amount'=>float|null, 'raw'=>string, 'is_provision'=>'yes/no']
 
 $actual_sql = "
     SELECT branch_code, water_type_id, connection_no, approval_status, total_amount, is_provision
     FROM tbl_admin_actual_water
     WHERE month_applicable = '{$monthEsc}'
 ";
 $actual_res = mysqli_query($conn, $actual_sql);
 
 if ($actual_res) {
     while ($a = mysqli_fetch_assoc($actual_res)) {
         $code = $a['branch_code'];
         $tid  = (int)($a['water_type_id'] ?? 0);
         $cno  = (int)($a['connection_no'] ?? 1);
         if ($cno <= 0) $cno = 1;
 
         $key = $tid . '|' . $cno;
 
         $status = strtolower(trim($a['approval_status'] ?? ''));
         $rawAmt = (string)($a['total_amount'] ?? '');
         $rawAmtTrim = trim($rawAmt);
 
         $amtNum = null;
         if ($rawAmtTrim !== '') {
             $clean = str_replace(',', '', $rawAmtTrim);
             if (is_numeric($clean)) $amtNum = (float)$clean;
         }
 
         if (!isset($actualMap[$code])) $actualMap[$code] = [];
         $actualMap[$code][$key] = [
             'status'       => $status,
             'amount'       => $amtNum,
             'raw'          => $rawAmtTrim,
             'is_provision' => strtolower(trim($a['is_provision'] ?? 'no')),
         ];
     }
 }
 
 /* -------------------------------------------------------
    4) BUILD REPORT TABLE (EXCEL-LIKE)
    Columns:
    Branch Code | Branch Name | Budget (Monthly) | Branch Breakdown | Actual | Variance |
    Water Connection(s) | Breakdown | Remarks
 
    - Branch summary cells use rowspan (like merged cells in Excel)
    - Breakdown is 1 row per required connection (even if missing)
    - Remarks show: Z connections, Y pending, X entered, Provision list
 ------------------------------------------------------- */
 
 $report_css = "
 <style>
   .water-report-table { width:100%; }
   .water-report-table th { white-space: nowrap; }
   .water-report-table td { vertical-align: top; }
 
   .water-report-table td.num,
   .water-report-table th.num { text-align: right; }
 
   .water-report-table td.wrap { white-space: normal; word-break: break-word; }
   .water-report-table .over-text { color: #dc3545; } /* bootstrap text-danger */
   .water-report-table .var-over { color: #dc3545; font-weight: 600; }
   .water-report-table .muted { color:#6c757d; }
 
   /* optional soft row highlight (you can remove if you only want red text) */
   .water-report-table tr.over-budget-row > * { background:#ffecec !important; }
 </style>
 ";
 
 function money_fmt($n) {
     return number_format((float)$n, 2);
 }
 
 /**
  * Variance formatting to resemble screenshot:
  * - show parentheses + red when over budget (Actual > Budget)
  * - otherwise show positive number (Budget - Actual) without parentheses
  */
 function variance_fmt($budget, $actual) {
     $budget = (float)$budget;
     $actual = (float)$actual;
 
     if ($actual > $budget) {
         $d = $actual - $budget;
         return "<span class='var-over'>(" . money_fmt($d) . ")</span>";
     }
     $d = $budget - $actual;
     return money_fmt($d);
 }
 
 $table_html = $report_css . "
 <table class='table table-bordered water-report-table'>
   <thead class='table-light'>
     <tr>
       <th>Branch Code</th>
       <th>Branch Name</th>
       <th class='num'>Budget (Monthly)</th>
       <th>Branch Breakdown</th>
       <th class='num'>Actual</th>
       <th class='num'>Variance</th>
       <th class='wrap'>Water Connection(s)</th>
       <th class='num'>Breakdown</th>
       <th class='wrap'>Remarks</th>
     </tr>
   </thead>
   <tbody>
 ";
 
 $total_budget_all = 0.0;
 $total_actual_all = 0.0;
 
 uksort($master, 'strnatcmp');
 
 foreach ($master as $code => $mdata) {
 
     $branch_name = $mdata['branch_name'] ?? '';
     $required    = $mdata['required_keys'] ?? [];
     $keyToLabel  = $mdata['key_to_label']  ?? [];
 
     $reqCount = count($required);
     if ($reqCount <= 0) continue;
 
     $b_amt = (float)($budget[$code] ?? 0);
 
     // ✅ budget=0 => do not show record
     if ($b_amt <= 0) continue;
 
     $total_budget_all += $b_amt;
 
     // per-branch counters + totals (entered = approved/pending with a record)
     $enteredCount = 0;
     $pendingCount = 0;
     $provLabels   = [];
     $actualTotal  = 0.0;
 
     // build breakdown rows in the required order
     $breakRows = []; // each row: ['label'=>, 'status'=>, 'amount'=>float|null]
         foreach ($required as $k) {
         $label = $keyToLabel[$k] ?? $k;
 
         $row = $actualMap[$code][$k] ?? null;
         $status = $row ? strtolower(trim($row['status'] ?? '')) : '';
 
         // treat rejected/deleted/no-row as missing breakdown
         $isMissing = (!$row || in_array($status, ['rejected','deleted'], true));
 
         // ✅ ONLY APPROVED amounts should show + count in totals
         $amt = null;
 
         if (!$isMissing) {
             // counts (entered/pending) can still reflect submitted records
             $enteredCount++;
 
             if ($status === 'pending') $pendingCount++;
 
             if (($row['is_provision'] ?? 'no') === 'yes') {
                 $provLabels[] = $label;
             }
 
             // ✅ sum + show ONLY when approved
             if ($status === 'approved' && $row['amount'] !== null && $row['amount'] > 0) {
                 $amt = (float)$row['amount'];
                 $actualTotal += $amt;
             }
         }
 
         $breakRows[] = [
             'label'  => $label,
             'status' => $isMissing ? 'missing' : $status,
             'amount' => $amt
         ];
     }
 
 
     $missingCount = max(0, $reqCount - $enteredCount);
 
     $total_actual_all += $actualTotal;
 
     $overBudget = ($actualTotal > $b_amt);
     $overClass  = $overBudget ? "over-budget-row" : "";
     $overText   = $overBudget ? "over-text" : "";
 
     $safeCode       = htmlspecialchars($code, ENT_QUOTES);
     $safeBranchName = htmlspecialchars($branch_name, ENT_QUOTES);
 
     // remarks (Excel style)
     $remarks = [];
     $remarks[] = "No. of Connections: {$reqCount}";
     if ($pendingCount > 0) $remarks[] = "Pending for Approval - {$pendingCount}";
     $remarks[] = "Entered Connections - {$enteredCount}";
     if ($missingCount > 0) $remarks[] = "Missing Connections - {$missingCount}";
 
     $provLabels = array_values(array_unique($provLabels));
     if (!empty($provLabels)) {
         $remarks[] = "Provision - " . implode(", ", $provLabels);
     }
 
     $remarksHtml = "";
     if (!empty($remarks)) {
         $remarksHtml = implode("<br>", array_map(function($line){
             return htmlspecialchars($line, ENT_QUOTES);
         }, $remarks));
     }
 
     $rowspan = count($breakRows);
 
     // FIRST ROW (with rowspans)
     $first = $breakRows[0];
     $firstLabel = htmlspecialchars($first['label'], ENT_QUOTES);
 
     $firstAmtCell = "";
     if ($first['amount'] !== null) {
         $firstAmtCell = money_fmt($first['amount']);
     } else {
         $firstAmtCell = "<span class='muted'>-</span>";
     }
 
     $table_html .= "
     <tr class='{$overClass}'>
         <td rowspan='{$rowspan}' class='{$overText}'>{$safeCode}</td>
         <td rowspan='{$rowspan}' class='{$overText}'>{$safeBranchName}</td>
         <td rowspan='{$rowspan}' class='num {$overText}'>" . money_fmt($b_amt) . "</td>
 
         <td class='{$overText}'>" . $safeBranchName . "</td>
 
         <td rowspan='{$rowspan}' class='num {$overText}'>" . money_fmt($actualTotal) . "</td>
         <td rowspan='{$rowspan}' class='num'>" . variance_fmt($b_amt, $actualTotal) . "</td>
 
         <td class='wrap {$overText}'>{$firstLabel}</td>
         <td class='num {$overText}'>{$firstAmtCell}</td>
 
         <td rowspan='{$rowspan}' class='wrap {$overText}'>{$remarksHtml}</td>
     </tr>
     ";
 
     // REST ROWS (breakdown only)
     for ($i = 1; $i < count($breakRows); $i++) {
         $br = $breakRows[$i];
         $lbl = htmlspecialchars($br['label'], ENT_QUOTES);
 
         $amtCell = "";
         if ($br['amount'] !== null) $amtCell = money_fmt($br['amount']);
         else $amtCell = "<span class='muted'>-</span>";
 
         $table_html .= "
         <tr class='{$overClass}'>
             <td class='{$overText}'>" . $safeBranchName . "</td>
             <td class='wrap {$overText}'>{$lbl}</td>
             <td class='num {$overText}'>{$amtCell}</td>
         </tr>
         ";
     }
 }
 
 // totals
 $total_variance_html = variance_fmt($total_budget_all, $total_actual_all);
 
 $table_html .= "
 <tr class='table-secondary fw-bold'>
      <td colspan='2'>Total</td>
     <td class='num'>" . money_fmt($total_budget_all) . "</td>
     <td></td>
     <td class='num'>" . money_fmt($total_actual_all) . "</td>
     <td class='num'>{$total_variance_html}</td>
     <td colspan='3'></td>
 </tr>
 </tbody></table>
 ";
 
 /* -------------------------------------------------------
    5) ALERT LISTS (UNCHANGED)
 ------------------------------------------------------- */
 $alert_missing    = [];
 $alert_pending    = [];
 $alert_provisions = [];
 $missing_groups   = [];
 $pending_branches = [];
 
 $alert_sql = "
     SELECT 
         bw.branch_code,
         bw.branch_name,
         bw.water_type_id,
         bw.connection_no,
         wt.water_type_name,
         aw.id,
         aw.approval_status,
         aw.is_provision
     FROM tbl_admin_branch_water bw
     INNER JOIN tbl_admin_water_types wt 
         ON wt.water_type_id = bw.water_type_id
     LEFT JOIN tbl_admin_actual_water aw
         ON aw.branch_code      = bw.branch_code
        AND aw.water_type_id    = bw.water_type_id
        AND aw.connection_no    = bw.connection_no
        AND aw.month_applicable = '{$monthEsc}'
     WHERE wt.is_active = 1
 ";
 $alert_res = mysqli_query($conn, $alert_sql);
 
 if ($alert_res) {
     while ($r = mysqli_fetch_assoc($alert_res)) {
 
         $branch_label = ($r['branch_name'] ?? '') . " (" . ($r['branch_code'] ?? '') . ")";
         $type_name    = $r['water_type_name'] ?? '';
         $connNo       = (int)($r['connection_no'] ?? 1);
 
         $status  = strtolower(trim($r['approval_status'] ?? ''));
         $is_prov = strtolower(trim($r['is_provision'] ?? 'no'));
         $has_row = !empty($r['id']);
 
         $labelWithConn = $branch_label . " - " . $type_name . " (Conn " . $connNo . ")";
 
         // if ($status === 'pending') {
         //     $alert_pending[] = $labelWithConn;
         //     $pending_branches[$r['branch_code']] = $branch_label;
         // }
 
         if ($status === 'pending' && $is_prov !== 'yes') {
             $alert_pending[] = $labelWithConn;
              $pending_branches[$r['branch_code']] = $branch_label;
         }
 
         if ($is_prov === 'yes') {
             $alert_provisions[] = $labelWithConn;
         }
 
         $missing_this = (!$has_row || in_array($status, ['rejected', 'deleted'], true));
         if ($missing_this) {
             $alert_missing[] = $labelWithConn;
 
             $group_key = $type_name;
             if (stripos($type_name, 'tap') !== false) $group_key = 'Tap Line';
             elseif (stripos($type_name, 'bottle') !== false) $group_key = 'Bottle Water';
             elseif (stripos($type_name, 'machine') !== false) $group_key = 'Machine';
 
             if (!isset($missing_groups[$group_key])) $missing_groups[$group_key] = [];
             $grpLabel = $branch_label . " (Conn " . $connNo . ")";
             if (!in_array($grpLabel, $missing_groups[$group_key], true)) {
                  $missing_groups[$group_key][] = $grpLabel;
             }
         }
     }
 }
 
 $pending_count = count($pending_branches);
 
 userlog("📊 Water Report View | Month: $month | User: " . ($_SESSION['name'] ?? 'Unknown'));
 
 echo json_encode([
     'table'          => $table_html,
     'missing'        => $alert_missing,
     'missing_groups' => $missing_groups,
     'provisions'     => $alert_provisions,
     'pending'        => $alert_pending,
     'pending_count'  => $pending_count
 ]);
  