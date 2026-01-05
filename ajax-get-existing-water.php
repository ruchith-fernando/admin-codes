<?php
// ajax-get-existing-water.php (FULL CLEAN REWRITE)
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

/* ============================================================
   INPUTS
============================================================ */
$branch_code = trim($_POST['branch_code'] ?? '');
$month       = trim($_POST['month'] ?? '');

if ($branch_code === '' || $month === '') {
    echo json_encode(['exists' => false]);
    exit;
}

/* ============================================================
   FETCH EXISTING RECORD
============================================================ */
$sql = "
    SELECT 
        id,
        branch,
        branch_code,
        water_type,
        account_number,

        monthly_charge,
        from_date,
        to_date,
        number_of_days,

        usage_qty,
        total_amount,

        is_provision,
        provision_reason,
        approval_status
    FROM tbl_admin_actual_water
    WHERE branch_code = '" . mysqli_real_escape_string($conn, $branch_code) . "'
      AND month_applicable = '" . mysqli_real_escape_string($conn, $month) . "'
    LIMIT 1
";

$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['exists' => false]);
    exit;
}

$row = mysqli_fetch_assoc($res);

/* ============================================================
   LOCKING LOGIC
============================================================ */
$status  = strtolower(trim($row['approval_status'] ?? ''));
$is_prov = strtolower(trim($row['is_provision'] ?? ''));

/*
   Rules:
   ✔ approved → locked
   ✔ pending → locked
   ✔ provision=yes → editable once
   ✔ rejected/deleted → editable
*/
if ($is_prov === 'yes') {
    $locked = false; // allow editing once
} else {
    $locked = in_array($status, ['approved', 'pending']);
}

switch ($status) {
    case 'approved':
        $status_msg = "✅ Approved record — editing locked.";
        break;
    case 'pending':
        $status_msg = "⏳ Pending approval — editing locked.";
        break;
    case 'rejected':
        $status_msg = "❗ Rejected — please re-enter data.";
        break;
    case 'deleted':
        $status_msg = "❗ Deleted — please re-enter data.";
        break;
    default:
        $status_msg = ($is_prov === 'yes')
            ? "ℹ️ Provisional entry — you may edit this record once."
            : "";
}

/* ============================================================
   FORMAT AMOUNT
============================================================ */
$total_amount = $row['total_amount'];
if ($total_amount !== null && $total_amount !== '') {
    $total_amount = number_format((float)$total_amount, 2, '.', ',');
}

/* ============================================================
   LOG
============================================================ */
userlog(
    "🔎 Water existing record | Branch: {$branch_code} | Month: {$month} | Status: {$row['approval_status']} | Provision: {$row['is_provision']} | User: " .
    ($_SESSION['name'] ?? 'Unknown')
);

/* ============================================================
   RETURN JSON (FINAL STRUCTURE)
============================================================ */
echo json_encode([
    'exists'          => true,

    'branch'          => $row['branch'],
    'branch_code'     => $row['branch_code'],
    'water_type'      => $row['water_type'],
    'account_number'  => $row['account_number'],

    'monthly_charge'  => $row['monthly_charge'],

    'from_date'       => $row['from_date'],
    'to_date'         => $row['to_date'],
    'number_of_days'  => $row['number_of_days'],

    'usage_qty'       => $row['usage_qty'],
    'total_amount'    => $total_amount,

    'is_provision'    => $row['is_provision'],
    'provision_reason'=> $row['provision_reason'],

    'approval_status' => $row['approval_status'],
    'locked'          => $locked,
    'status_msg'      => $status_msg
]);
?>
