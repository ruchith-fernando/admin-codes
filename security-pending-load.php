<?php
// security-pending-load.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$month = $_POST['month'] ?? '';
if(!$month){
    exit("<div class='alert alert-warning'>No month selected.</div>");
}

$current_hris = trim((string)($_SESSION['hris'] ?? ''));
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
$month_esc = mysqli_real_escape_string($conn, $month);

/* -------------------- 1) FIRMWISE PENDING -------------------- */
$sql_fw = "
    SELECT 
        t1.*,
        f.firm_name,
        r.reason AS reason_text,
        b.no_of_shifts AS budget_shifts
    FROM tbl_admin_actual_security_firmwise AS t1
    LEFT JOIN tbl_admin_security_firms AS f
        ON f.id = t1.firm_id
    LEFT JOIN tbl_admin_reason AS r
        ON r.id = t1.reason_id
    LEFT JOIN tbl_admin_budget_security AS b
        ON b.branch_code = t1.branch_code
       AND b.month_applicable = t1.month_applicable
    WHERE t1.month_applicable = '{$month_esc}'
      AND (t1.approval_status = 'pending' OR t1.approval_status IS NULL)
    ORDER BY t1.entered_at DESC, t1.branch_code ASC
";
$q_fw = mysqli_query($conn, $sql_fw);
if(!$q_fw){
    $err = mysqli_error($conn);
    echo "<div class='alert alert-danger'>DB error loading firmwise pending:<br>" . esc($err) . "</div>";
    exit;
}

/* -------------------- 2) 2000-INVOICE PENDING -------------------- */
$sql_inv = "
    SELECT
        i.*,
        f.firm_name,
        rr.reason AS reason_text
    FROM tbl_admin_actual_security_2000_invoices i
    LEFT JOIN tbl_admin_security_firms f ON f.id = i.firm_id
    LEFT JOIN tbl_admin_reason rr ON rr.id = i.reason_id
    WHERE i.month_applicable = '{$month_esc}'
      AND (i.approval_status = 'pending' OR i.approval_status IS NULL)
    ORDER BY i.entered_at DESC, CAST(i.branch_code AS UNSIGNED), i.branch_code, i.id DESC
";
$q_inv = mysqli_query($conn, $sql_inv);
if(!$q_inv){
    $err = mysqli_error($conn);
    echo "<div class='alert alert-danger'>DB error loading 2000 invoice pending:<br>" . esc($err) . "</div>";
    exit;
}

$fwCount  = mysqli_num_rows($q_fw);
$invCount = mysqli_num_rows($q_inv);

if($fwCount == 0 && $invCount == 0){
    echo "<div class='alert alert-info'>No pending security approvals for <b>".esc($month)."</b>.</div>";
    exit;
}

/* ==================== FIRMWISE TABLE ==================== */
if ($fwCount > 0) {

  $fwRowsHtml = '';
  $fwActionable = 0;

  while($r = mysqli_fetch_assoc($q_fw)){
      $entered_hris = trim((string)($r['entered_hris'] ?? ''));
      $is_own = ($entered_hris !== '' && $entered_hris === $current_hris);

      $firm_name  = trim((string)($r['firm_name'] ?? ''));
      $firm_name  = $firm_name !== '' ? $firm_name : '-';

      $reasonText = trim((string)($r['reason_text'] ?? ''));
      $budget     = isset($r['budget_shifts']) ? (int)$r['budget_shifts'] : null;
      $actual     = (int)($r['actual_shifts'] ?? 0);
      $amount     = (float)($r['total_amount'] ?? 0);
      $provText   = (($r['provision'] ?? 'no')==='yes'?'Yes':'No');

      $actions = '';
      if($is_own){
          $actions = "<span class='text-muted small fst-italic'>Own entry</span>";
      } else {
          $fwActionable++;
          $actions = "
            <button class='btn btn-success btn-sm approve-btn me-1'
                data-type='firmwise'
                data-id='".esc($r['id'])."'
                data-branch='".esc($r['branch'])."'
                data-branch-code='".esc($r['branch_code'])."'
                data-month='".esc($r['month_applicable'])."'>Approve</button>
            <button class='btn btn-danger btn-sm reject-btn'
                data-type='firmwise'
                data-id='".esc($r['id'])."'
                data-branch='".esc($r['branch'])."'
                data-branch-code='".esc($r['branch_code'])."'
                data-month='".esc($r['month_applicable'])."'>Reject</button>
          ";
      }

      $fwRowsHtml .= "
        <tr>
          <td>".esc($r['branch_code'])."</td>
          <td>".esc($r['branch'])."</td>
          <td>".esc($firm_name)."</td>
          <td>".esc($r['month_applicable'])."</td>
          <td class='text-end'>".($budget !== null ? number_format($budget,0) : "-")."</td>
          <td class='text-end'>".number_format($actual,0)."</td>
          <td>".($reasonText !== '' ? esc($reasonText) : '-')."</td>
          <td>{$provText}</td>
          <td class='text-end'>Rs. ".number_format($amount, 2)."</td>
          <td>".esc($r['entered_name'])."</td>
          <td>".esc($r['entered_hris'])."</td>
          <td>".esc($r['entered_at'])."</td>
          <td>{$actions}</td>
        </tr>
      ";
  }

  $fwBtnDisabled = ($fwActionable === 0) ? "disabled" : "";
  $fwBtnNote = ($fwActionable === 0) ? "<span class='text-muted small ms-2'>(no approvable rows)</span>" : "";

  echo "
    <div class='d-flex align-items-center justify-content-between mt-2 mb-2'>
      <h6 class='fw-bold mb-0'>Pending — Normal Security (Firmwise)</h6>
      <div>
        <button class='btn btn-success btn-sm approve-all-btn' data-type='firmwise' {$fwBtnDisabled}>
          ✅ Approve All — Firmwise
        </button>
        {$fwBtnNote}
      </div>
    </div>

    <div class='table-responsive'>
      <table class='table table-bordered table-hover align-middle'>
        <thead class='table-light'>
          <tr>
            <th>Branch Code</th>
            <th>Branch</th>
            <th>Security Firm</th>
            <th>Month</th>
            <th class='text-end'>Budgeted Shifts</th>
            <th class='text-end'>Actual Shifts</th>
            <th>Reason (if &gt; Budget)</th>
            <th>Provision</th>
            <th class='text-end'>Total Amount</th>
            <th>Entered By</th>
            <th>Entered HRIS</th>
            <th>Entered At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>{$fwRowsHtml}</tbody>
      </table>
    </div>
  ";
}

/* ==================== 2000 INVOICES TABLE ==================== */
if ($invCount > 0) {

  $invRowsHtml = '';
  $invActionable = 0;

  while($i = mysqli_fetch_assoc($q_inv)){

      $entered_hris = trim((string)($i['entered_hris'] ?? ''));
      $is_own = ($entered_hris !== '' && $entered_hris === $current_hris);

      $firm_name = trim((string)($i['firm_name'] ?? ''));
      $firm_name = $firm_name !== '' ? $firm_name : '-';

      $reasonText = trim((string)($i['reason_text'] ?? ''));
      $provText   = (($i['provision'] ?? 'no') === 'yes') ? 'Yes' : 'No';
      $amt        = (float)($i['amount'] ?? 0);

      $actions = '';
      if($is_own){
          $actions = "<span class='text-muted small fst-italic'>Own entry</span>";
      } else {
          $invActionable++;
          $actions = "
            <button class='btn btn-success btn-sm approve-btn me-1'
                data-type='inv2000'
                data-id='".esc($i['id'])."'
                data-branch='".esc($i['branch'])."'
                data-branch-code='".esc($i['branch_code'])."'
                data-month='".esc($i['month_applicable'])."'>Approve</button>
            <button class='btn btn-danger btn-sm reject-btn'
                data-type='inv2000'
                data-id='".esc($i['id'])."'
                data-branch='".esc($i['branch'])."'
                data-branch-code='".esc($i['branch_code'])."'
                data-month='".esc($i['month_applicable'])."'>Reject</button>
          ";
      }

      $invRowsHtml .= "
        <tr>
          <td>".esc($i['branch_code'])."</td>
          <td>".esc($i['branch'])."</td>
          <td>".esc($i['month_applicable'])."</td>
          <td>".esc($i['invoice_no'])."</td>
          <td>{$provText}</td>
          <td class='text-end'>Rs. ".number_format($amt, 2)."</td>
          <td>".esc($i['entered_name'])."</td>
          <td>".esc($i['entered_hris'])."</td>
          <td>".esc($i['entered_at'])."</td>
          <td>{$actions}</td>
        </tr>
      ";
  }

  $invBtnDisabled = ($invActionable === 0) ? "disabled" : "";
  $invBtnNote = ($invActionable === 0) ? "<span class='text-muted small ms-2'>(no approvable rows)</span>" : "";

  echo "
    <div class='d-flex align-items-center justify-content-between mt-4 mb-2'>
      <h6 class='fw-bold mb-0'>Pending — Police, Additional Security & Radio Transmission</h6>
      <div>
        <button class='btn btn-success btn-sm approve-all-btn' data-type='inv2000' {$invBtnDisabled}>
          ✅ Approve All — 2000 Invoices
        </button>
        {$invBtnNote}
      </div>
    </div>

    <div class='table-responsive'>
      <table class='table table-bordered table-hover align-middle'>
        <thead class='table-light'>
          <tr>
            <th>Branch Code</th>
            <th>Branch</th>
            <th>Month</th>
            <th>Invoice / Ref No</th>
            <th>Provision</th>
            <th class='text-end'>Amount</th>
            <th>Entered By</th>
            <th>Entered HRIS</th>
            <th>Entered At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>{$invRowsHtml}</tbody>
      </table>
    </div>
  ";
}
