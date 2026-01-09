<?php
// process-issues.php  (STEP 2: allocations maintenance added)
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload-issues.log');
error_reporting(E_ALL);

include 'connections/connection.php';

/**
 * -----------------------------
 * Allocation helper functions
 * -----------------------------
 */

function normalizeContributionAmount($val) {
    // Allow numbers like "1,500.00" or "1500"
    $v = trim((string)$val);
    if ($v === '') return null;
    $v = str_replace([',', ' '], '', $v);
    return is_numeric($v) ? (float)$v : null;
}

function upsertContributionVersioned($conn, $hris, $mobile, $amountRaw, $effectiveFrom, &$stats) {

    $hris   = trim((string)$hris);
    $mobile = normalizeMobile($mobile);
    $amount = normalizeContributionAmount($amountRaw);

    if ($hris === '' || empty($mobile) || $amount === null) {
        $stats['skipped']++;
        return true; // not an error, just nothing to do
    }

    if (empty($effectiveFrom)) $effectiveFrom = date('Y-m-d');

    // Find current active contribution
    $stmt = $conn->prepare("SELECT id, contribution_amount, effective_from
        FROM tbl_admin_hris_contributions
        WHERE hris_no = ?
          AND mobile_no = ?
          AND effective_to IS NULL
        ORDER BY effective_from DESC, id DESC
        LIMIT 1");
    if (!$stmt) {
        $stats['failed']++;
        return false;
    }

    $stmt->bind_param("ss", $hris, $mobile);
    $stmt->execute();
    $res = $stmt->get_result();
    $cur = $res->fetch_assoc();
    $stmt->close();

    // If there is an active record and amount is the same -> no change
    if ($cur) {
        $curAmount = (float)$cur['contribution_amount'];

        // Use a small tolerance for decimals
        if (abs($curAmount - $amount) < 0.0001) {
            $stats['unchanged']++;
            return true;
        }

        // Close the existing record yesterday
        $prevTo = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));

        // Safety: don't close to a date earlier than its effective_from
        $curFrom = $cur['effective_from'];
        if (!empty($curFrom) && $prevTo < $curFrom) {
            // If someone tries to backdate in future, just close on same day
            $prevTo = $effectiveFrom;
        }

        $upd = $conn->prepare("
            UPDATE tbl_admin_hris_contributions
            SET effective_to = ?
            WHERE id = ?
        ");
        if (!$upd) {
            $stats['failed']++;
            return false;
        }
        $id = (int)$cur['id'];
        $upd->bind_param("si", $prevTo, $id);
        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            $stats['failed']++;
            return false;
        }

        $stats['closed_old']++;
    }

    // Insert new active record
    $ins = $conn->prepare("
        INSERT INTO tbl_admin_hris_contributions
          (hris_no, mobile_no, contribution_amount, effective_from, effective_to)
        VALUES (?, ?, ?, ?, NULL)
    ");
    if (!$ins) {
        $stats['failed']++;
        return false;
    }

    $ins->bind_param("ssds", $hris, $mobile, $amount, $effectiveFrom);
    $ok2 = $ins->execute();
    $ins->close();

    if ($ok2) {
        $stats['inserted']++;
        return true;
    }

    $stats['failed']++;
    return false;
}

function normalizeMobile($m) {
    $m = strtoupper(trim((string)$m));
    $m = preg_replace('/\s+/', '', $m);
    return $m === '' ? null : $m;
}

function normalizeStatus($s) {
    $s = strtolower(trim((string)$s));
    if ($s === '') return 'connected';

    // common variants
    if (in_array($s, ['disconnect', 'disconnected', 'inactive', 'terminated', 'barred'], true)) return 'disconnected';
    if (in_array($s, ['connect', 'connected', 'active'], true)) return 'connected';

    return $s; // fallback
}

function closeActiveAllocationForMobile($conn, $mobile, $effectiveTo) {
    $stmt = $conn->prepare("
        UPDATE tbl_admin_mobile_allocations
        SET effective_to = ?, status = 'Inactive'
        WHERE mobile_number = ?
          AND status = 'Active'
          AND effective_to IS NULL
    ");
    if (!$stmt) return false;

    $stmt->bind_param("ss", $effectiveTo, $mobile);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function getCurrentActiveAllocation($conn, $mobile) {
    $stmt = $conn->prepare("
        SELECT id, hris_no, effective_from
        FROM tbl_admin_mobile_allocations
        WHERE mobile_number = ?
          AND status = 'Active'
          AND effective_to IS NULL
        ORDER BY effective_from DESC, id DESC
        LIMIT 1
    ");
    if (!$stmt) return null;

    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function insertNewAllocation($conn, $mobile, $hris, $owner, $effectiveFrom) {
    // owner_name is NOT NULL in your schema
    $owner = trim((string)$owner);
    if ($owner === '') $owner = 'N/A';

    $stmt = $conn->prepare("
        INSERT INTO tbl_admin_mobile_allocations
          (mobile_number, hris_no, owner_name, effective_from, effective_to, status)
        VALUES (?, ?, ?, ?, NULL, 'Active')
    ");
    if (!$stmt) return false;

    $stmt->bind_param("ssss", $mobile, $hris, $owner, $effectiveFrom);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Applies allocation rules per CSV row:
 * - Disconnected: close active allocation (effective_to = effectiveDate)
 * - Connected:
 *    - if no active allocation -> insert
 *    - if same HRIS -> do nothing
 *    - if different HRIS -> close old yesterday, insert new today (prevents overlap trigger errors)
 */
function applyAllocationFromCsv($conn, $mobileRaw, $hrisRaw, $ownerNameRaw, $connStatusRaw, $effectiveDate) {

    $mobile = normalizeMobile($mobileRaw);
    $hris   = trim((string)$hrisRaw);
    $owner  = trim((string)$ownerNameRaw);
    $status = normalizeStatus($connStatusRaw);

    if (empty($mobile)) return true; // nothing to do

    if (empty($effectiveDate)) $effectiveDate = date('Y-m-d');

    // DISCONNECTED -> close current allocation if any
    if ($status === 'disconnected') {
        closeActiveAllocationForMobile($conn, $mobile, $effectiveDate);
        return true;
    }

    // CONNECTED (default)
    if (empty($hris)) {
        // cannot allocate without HRIS; skip quietly
        return true;
    }

    $cur = getCurrentActiveAllocation($conn, $mobile);

    if (!$cur) {
        // No active allocation -> create one
        return insertNewAllocation($conn, $mobile, $hris, $owner, $effectiveDate);
    }

    if (trim((string)$cur['hris_no']) === $hris) {
        // Already allocated to same HRIS -> nothing to do
        return true;
    }

    // Different HRIS currently active -> close old yesterday then insert new today
    $prevTo = date('Y-m-d', strtotime($effectiveDate . ' -1 day'));
    closeActiveAllocationForMobile($conn, $mobile, $prevTo);
    return insertNewAllocation($conn, $mobile, $hris, $owner, $effectiveDate);
}

/**
 * -----------------------------
 * File upload validation
 * -----------------------------
 */

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== 0) {
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Please upload a valid CSV file.</div>";
    exit;
}

$uploadedFilePath = $_FILES['csv_file']['tmp_name'];
$uploadedFileName = $_FILES['csv_file']['name'] ?? 'N/A';

$handle = fopen($uploadedFilePath, "r");
if ($handle === false) {
    echo "<div class='alert alert-danger fw-bold'>❌ Error: Could not read the CSV file.</div>";
    exit;
}

// Skip header row
fgetcsv($handle);

$inserted = 0;
$skipped  = 0;
$contribs = 0;
$alloc_ok = 0;
$alloc_failed = 0;

$today = date('Y-m-d');

while (($data = fgetcsv($handle, 1000, ",")) !== false) {

    list(
        $mobile_no,
        $remarks,
        $voice_data,
        $branch_operational_remarks,
        $name_of_employee,
        $hris_no,
        $company_hierarchy,
        $connection_status,
        $nic_no,
        $company_contribution
    ) = array_pad($data, 10, null);

    // --- Normalize values ---
    $mobile_no                 = normalizeMobile($mobile_no);
    $remarks                   = trim((string)$remarks) ?: null;
    $voice_data                = trim((string)$voice_data) ?: null;
    $branch_operational_remarks = trim((string)$branch_operational_remarks) ?: null;
    $name_of_employee          = trim((string)$name_of_employee) ?: null;
    $hris_no                   = trim((string)$hris_no) ?: null;
    $company_hierarchy         = trim((string)$company_hierarchy) ?: null;
    $connection_status         = trim((string)$connection_status) ?: 'Connected';
    $nic_no                    = trim((string)$nic_no) ?: null;
    $company_contribution      = trim((string)$company_contribution) ?: null;

    // Other table fields (default null)
    $epf_no = $title = $designation = $display_name = $location = null;
    $category = $employment_categories = $date_joined = $date_resigned = null;
    $category_ops_sales = $status = $disconnection_date = null;

    // --- Enrich from employee details if HRIS is present ---
    if (!empty($hris_no)) {
        $emp_stmt = $conn->prepare("
            SELECT epf_no, company_hierarchy, title, name_of_employee, designation,
                   display_name, location, nic_no, category, employment_categories,
                   date_joined, date_resigned, category_ops_sales, status
            FROM tbl_admin_employee_details
            WHERE TRIM(hris) = ?
            LIMIT 1
        ");
        if ($emp_stmt) {
            $emp_stmt->bind_param("s", $hris_no);
            $emp_stmt->execute();
            $emp_result = $emp_stmt->get_result();
            if ($emp_row = $emp_result->fetch_assoc()) {
                $epf_no                = $emp_row['epf_no'] ?? null;
                $company_hierarchy     = $company_hierarchy ?? $emp_row['company_hierarchy'];
                $title                 = $emp_row['title'] ?? null;
                $name_of_employee      = $name_of_employee ?? $emp_row['name_of_employee'];
                $designation           = $emp_row['designation'] ?? null;
                $display_name          = $emp_row['display_name'] ?? null;
                $location              = $location ?? $emp_row['location'];
                $nic_no                = $nic_no ?? $emp_row['nic_no'];
                $category              = $emp_row['category'] ?? null;
                $employment_categories = $emp_row['employment_categories'] ?? null;
                $date_joined           = $emp_row['date_joined'] ?? null;
                $date_resigned         = $emp_row['date_resigned'] ?? null;
                $category_ops_sales    = $emp_row['category_ops_sales'] ?? null;
                $status                = $status ?? $emp_row['status'];
            }
            $emp_stmt->close();
        }
    }

    /**
     * ✅ NEW (STEP 2):
     * Maintain tbl_admin_mobile_allocations for invoice linking.
     */
    try {
        $ok = applyAllocationFromCsv(
            $conn,
            $mobile_no,
            $hris_no,
            $name_of_employee,
            $connection_status,
            $today
        );
        if ($ok) $alloc_ok++; else $alloc_failed++;
    } catch (Throwable $e) {
        $alloc_failed++;
        error_log("ALLOCATION FAIL mobile={$mobile_no} hris={$hris_no} : " . $e->getMessage());
        // don't exit; continue
    }

    // --- Insert into mobile issues (raw import log) ---
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

    if ($stmt) {
        $stmt->bind_param(
            "ssssssdsssssssssssssss",
            $mobile_no,
            $remarks,
            $voice_data,
            $branch_operational_remarks,
            $name_of_employee,
            $hris_no,
            $company_contribution,
            $epf_no,
            $company_hierarchy,
            $title,
            $designation,
            $display_name,
            $location,
            $nic_no,
            $category,
            $employment_categories,
            $date_joined,
            $date_resigned,
            $category_ops_sales,
            $status,
            $connection_status,
            $disconnection_date
        );

        if ($stmt->execute()) {
            $inserted++;

            // --- Contribution insert (unchanged for now; Step 3 will version this) ---
            if (!empty($company_contribution) && !empty($hris_no)) {
                $contrib_stmt = $conn->prepare("
                    INSERT INTO tbl_admin_hris_contributions (
                        hris_no, mobile_no, contribution_amount, effective_from
                    ) VALUES (?, ?, ?, ?)
                ");
                if ($contrib_stmt) {
                    $contrib_stmt->bind_param("ssds", $hris_no, $mobile_no, $company_contribution, $today);
                    if ($contrib_stmt->execute()) {
                        $contribs++;
                    }
                    $contrib_stmt->close();
                }
            }

        } else {
            $skipped++;
            error_log("MOBILE_ISSUES INSERT FAIL mobile={$mobile_no} : " . $stmt->error);
        }
        $stmt->close();
    } else {
        $skipped++;
        error_log("PREPARE FAIL (mobile_issues) mobile={$mobile_no}");
    }
}

fclose($handle);
$conn->close();

echo "
<div class='alert alert-success fw-bold'>✅ CSV Upload Complete</div>
<div class='result-block'>
  <div><b>File Name:</b> " . htmlspecialchars($uploadedFileName) . "</div>
  <div><b>Total Records Inserted:</b> $inserted</div>
  <div><b>Skipped Rows:</b> $skipped</div>
  <div><b>Allocations Updated:</b> $alloc_ok</div>
  <div><b>Allocation Failures:</b> $alloc_failed</div>
  <div><b>HRIS Contributions Added:</b> $contribs</div>
</div>";
?>
