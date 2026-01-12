<?php
// ajax-mobile-allocation-save.php
require_once 'connections/connection.php';
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('Asia/Colombo');

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function normalizeContributionAmount($val) {
  $v = trim((string)$val);
  if ($v === '') return null;
  $v = str_replace([',', ' '], '', $v);
  return is_numeric($v) ? (float)$v : null;
}

function upsertContributionVersioned($conn, $hris, $mobile, $amountRaw, $effectiveFrom) {
  $amount = normalizeContributionAmount($amountRaw);
  if ($hris === '' || $mobile === '' || $amount === null) return;

  // current active record?
  $stmt = $conn->prepare("
    SELECT id, contribution_amount, effective_from
    FROM tbl_admin_hris_contributions
    WHERE hris_no=? AND mobile_no=? AND effective_to IS NULL
    ORDER BY effective_from DESC, id DESC
    LIMIT 1
  ");
  $stmt->bind_param("ss", $hris, $mobile);
  $stmt->execute();
  $cur = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($cur) {
    $curAmount = (float)$cur['contribution_amount'];
    if (abs($curAmount - $amount) < 0.0001) return;

    $prevTo = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
    $curFrom = $cur['effective_from'];
    if (!empty($curFrom) && $prevTo < $curFrom) $prevTo = $effectiveFrom;

    $upd = $conn->prepare("UPDATE tbl_admin_hris_contributions SET effective_to=? WHERE id=?");
    $id = (int)$cur['id'];
    $upd->bind_param("si", $prevTo, $id);
    $upd->execute();
    $upd->close();
  }

  $ins = $conn->prepare("
    INSERT INTO tbl_admin_hris_contributions
      (hris_no, mobile_no, contribution_amount, effective_from, effective_to)
    VALUES (?, ?, ?, ?, NULL)
  ");
  $ins->bind_param("ssds", $hris, $mobile, $amount, $effectiveFrom);
  $ins->execute();
  $ins->close();
}

// inputs
$mobile = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
$hris   = trim($_POST['hris'] ?? '');
$owner  = trim($_POST['owner'] ?? '');
$eff    = trim($_POST['effective_from'] ?? '');

$voice_data = trim($_POST['voice_data'] ?? '');
$conn_status = trim($_POST['connection_status'] ?? 'Connected');
$remarks = trim($_POST['remarks'] ?? '');
$branch_remarks = trim($_POST['branch_operational_remarks'] ?? '');
$hierarchy = trim($_POST['company_hierarchy'] ?? '');
$nic = trim($_POST['nic_no'] ?? '');
$contribution_raw = trim($_POST['company_contribution'] ?? '');

// validate
if (!preg_match('/^\d{9}$/', $mobile)) { echo "<div class='alert alert-danger'>Mobile must be 9 digits.</div>"; exit; }
if ($hris === '') { echo "<div class='alert alert-danger'>HRIS is required.</div>"; exit; }
if ($eff === '') { echo "<div class='alert alert-danger'>Effective From is required.</div>"; exit; }
if ($voice_data === '') { echo "<div class='alert alert-danger'>Voice/Data is required.</div>"; exit; }

// HRIS numeric must be 6 digits
if (ctype_digit($hris) && !preg_match('/^\d{6}$/', $hris)) {
  echo "<div class='alert alert-danger'>Numeric HRIS must be exactly 6 digits (example: 006428).</div>";
  exit;
}

// enforce Active employee for numeric HRIS
if (preg_match('/^\d{6}$/', $hris)) {
  $st = $conn->prepare("SELECT 1 FROM tbl_admin_employee_details WHERE TRIM(hris)=? AND status='Active' LIMIT 1");
  $st->bind_param("s", $hris);
  $st->execute();
  $okActive = $st->get_result()->num_rows > 0;
  $st->close();
  if (!$okActive) { echo "<div class='alert alert-danger'>⛔ Cannot save. HRIS must be Active.</div>"; exit; }
}

// normalize connection status
if (!in_array($conn_status, ['Connected','Disconnected'], true)) $conn_status = 'Connected';

// enrich from employee details when numeric HRIS
$epf_no = $title = $designation = $display_name = $location = $category = $employment_categories = $date_joined = $date_resigned = $category_ops_sales = $emp_status = $disconnection_date = null;

if (preg_match('/^\d{6}$/', $hris)) {
  $emp_stmt = $conn->prepare("
    SELECT epf_no, company_hierarchy, title, name_of_employee, designation,
           display_name, location, nic_no, category, employment_categories,
           date_joined, date_resigned, category_ops_sales, status
    FROM tbl_admin_employee_details
    WHERE TRIM(hris)=?
    LIMIT 1
  ");
  $emp_stmt->bind_param("s", $hris);
  $emp_stmt->execute();
  $er = $emp_stmt->get_result()->fetch_assoc();
  $emp_stmt->close();

  if ($er) {
    if ($owner === '') $owner = $er['name_of_employee'] ?? '';
    if ($hierarchy === '') $hierarchy = $er['company_hierarchy'] ?? '';
    if ($nic === '') $nic = $er['nic_no'] ?? '';

    $epf_no = $er['epf_no'] ?? null;
    $title = $er['title'] ?? null;
    $designation = $er['designation'] ?? null;
    $display_name = $er['display_name'] ?? null;
    $location = $er['location'] ?? null;
    $category = $er['category'] ?? null;
    $employment_categories = $er['employment_categories'] ?? null;
    $date_joined = $er['date_joined'] ?? null;
    $date_resigned = $er['date_resigned'] ?? null;
    $category_ops_sales = $er['category_ops_sales'] ?? null;
    $emp_status = $er['status'] ?? null;
  }
}

if ($owner === '') $owner = 'N/A';

// if disconnected, store disconnection_date as effective date
if ($conn_status === 'Disconnected') {
  $disconnection_date = $eff;
}

$conn->begin_transaction();

try {
  $action = 'ISSUES_ONLY';

  // Allocation maintenance (only if Connected)
  if ($conn_status === 'Connected') {

    $cur = $conn->prepare("
      SELECT id, effective_from
      FROM tbl_admin_mobile_allocations
      WHERE mobile_number=?
        AND status='Active'
        AND effective_to IS NULL
      ORDER BY effective_from DESC, id DESC
      LIMIT 1
    ");
    $cur->bind_param("s", $mobile);
    $cur->execute();
    $active = $cur->get_result()->fetch_assoc();
    $cur->close();

    $action = 'NEW';

    if ($active) {
      $oldId = (int)$active['id'];
      $oldFrom = $active['effective_from'];

      if ($eff <= $oldFrom) {
        throw new Exception("Effective From must be after existing effective_from ($oldFrom).");
      }

      $closeTo = date('Y-m-d', strtotime($eff . ' -1 day'));

      $upd = $conn->prepare("
        UPDATE tbl_admin_mobile_allocations
        SET effective_to=?, status='Inactive'
        WHERE id=? AND effective_to IS NULL
      ");
      $upd->bind_param("si", $closeTo, $oldId);
      $upd->execute();
      $upd->close();

      $action = 'TRANSFER';
    }

    $ins = $conn->prepare("
      INSERT INTO tbl_admin_mobile_allocations
        (mobile_number, hris_no, owner_name, effective_from, effective_to, status, created_at, updated_at)
      VALUES (?, ?, ?, ?, NULL, 'Active', NOW(), NOW())
    ");
    $ins->bind_param("ssss", $mobile, $hris, $owner, $eff);
    $ins->execute();
    $allocId = $ins->insert_id;
    $ins->close();
  } else {
    // Disconnected: close active allocation if any (same day)
    $upd = $conn->prepare("
      UPDATE tbl_admin_mobile_allocations
      SET effective_to=?, status='Inactive'
      WHERE mobile_number=? AND status='Active' AND effective_to IS NULL
    ");
    $upd->bind_param("ss", $eff, $mobile);
    $upd->execute();
    $upd->close();
    $action = 'CLOSED';
  }

  // Insert issues row (this replaces Excel)
  $company_contribution = normalizeContributionAmount($contribution_raw);

  $stmt = $conn->prepare("
    INSERT INTO tbl_admin_mobile_issues (
      mobile_no, remarks, voice_data, branch_operational_remarks,
      name_of_employee, hris_no, company_contribution, epf_no,
      company_hierarchy, title, designation, display_name,
      location, nic_no, category, employment_categories,
      date_joined, date_resigned, category_ops_sales, status,
      connection_status, disconnection_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stmt->bind_param(
    "ssssssdsssssssssssssss",
    $mobile,
    $remarks,
    $voice_data,
    $branch_remarks,
    $owner,
    $hris,                       // ✅ save real HRIS here (already padded to 6 digits by UI)
    $company_contribution,
    $epf_no,
    $hierarchy,
    $title,
    $designation,
    $display_name,
    $location,
    $nic,
    $category,
    $employment_categories,
    $date_joined,
    $date_resigned,
    $category_ops_sales,
    $emp_status,
    $conn_status,
    $disconnection_date
  );
  $stmt->execute();
  $issueId = $stmt->insert_id;
  $stmt->close();

  // Versioned contributions table (if amount is given)
  if ($company_contribution !== null && $hris !== '') {
    upsertContributionVersioned($conn, $hris, $mobile, $company_contribution, $eff);
  }

  $conn->commit();

  echo "<div class='alert alert-success'>
    ✅ Saved successfully (<b>".esc($action)."</b>)<br>
    Issues ID: <b>".esc($issueId)."</b><br>
    Mobile: <b>".esc($mobile)."</b> | HRIS: <b>".esc($hris)."</b> | From: <b>".esc($eff)."</b>
  </div>";

} catch (Throwable $e) {
  $conn->rollback();
  echo "<div class='alert alert-danger'>Save failed: ".esc($e->getMessage())."</div>";
}
