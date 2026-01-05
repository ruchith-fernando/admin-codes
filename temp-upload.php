<?php
// mobile-issues-upload.php
session_start();
include 'connections/connection.php'; // expects $conn = new mysqli(...)

$inserted = 0;
$skipped  = 0;
$errors   = 0;
$msgs     = [];
$hasResult = false;

function toDateOrNull($v) {
    $v = trim((string)$v);
    if ($v === '' || strcasecmp($v, 'NULL') === 0) return null;
    // Accept common formats: 2025-06-15, 15/06/2025, 06/15/2025, 15-06-2025, etc.
    $v = str_replace(['.', '/'], ['-', '-'], $v);
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}
function toDecOrNull($v) {
    $v = trim((string)$v);
    if ($v === '' || strcasecmp($v, 'NULL') === 0) return null;
    // keep only digits, dot, minus
    $v = preg_replace('/[^0-9\.\-]/', '', $v);
    return $v === '' ? null : (float)$v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasResult = true;

    if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        $msgs[] = "Upload error code: " . ($_FILES['csvFile']['error'] ?? 'unknown');
    } else {
        $hasHeader = isset($_POST['has_header']);
        $file = $_FILES['csvFile']['tmp_name'];
        if (($h = fopen($file, 'r')) === false) {
            $msgs[] = "Could not open the uploaded file.";
        } else {
            // Increase script limits for large imports
            @set_time_limit(0);
            @ini_set('auto_detect_line_endings', '1');

            // Prepare INSERT
            $sql = "INSERT INTO tbl_admin_mobile_issues_backup (
                        mobile_no, remarks, voice_data, branch_operational_remarks, name_of_employee,
                        hris_no, company_contribution, epf_no, company_hierarchy, title, designation,
                        display_name, location, nic_no, category, employment_categories,
                        date_joined, date_resigned, category_ops_sales, status, connection_status, disconnection_date
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $msgs[] = "Prepare failed: " . $conn->error;
            } else {
                $conn->begin_transaction();
                $rowNum = 0;

                if ($hasHeader) {
                    // Skip header row
                    fgetcsv($h);
                }

                // Expected CSV columns (22 cols):
                // 0 mobile_no
                // 1 remarks
                // 2 voice_data
                // 3 branch_operational_remarks
                // 4 name_of_employee
                // 5 hris_no
                // 6 company_contribution
                // 7 epf_no
                // 8 company_hierarchy
                // 9 title
                // 10 designation
                // 11 display_name
                // 12 location
                // 13 nic_no
                // 14 category
                // 15 employment_categories
                // 16 date_joined
                // 17 date_resigned
                // 18 category_ops_sales
                // 19 status
                // 20 connection_status
                // 21 disconnection_date

                while (($row = fgetcsv($h)) !== false) {
                    $rowNum++;
                    // Pad/trim to 22 columns
                    $row = array_map('trim', array_pad($row, 22, null));

                    // Skip completely empty rows
                    if (count(array_filter($row, function($v){ return $v !== null && $v !== ''; })) === 0) {
                        $skipped++;
                        continue;
                    }

                    $mobile_no                 = $row[0] ?? null;
                    $remarks                   = $row[1] ?? null;
                    $voice_data                = $row[2] ?? null;
                    $branch_operational_remark = $row[3] ?? null;
                    $name_of_employee          = $row[4] ?? null;
                    $hris_no                   = $row[5] ?? null;
                    $company_contribution      = toDecOrNull($row[6] ?? null);
                    $epf_no                    = $row[7] ?? null;
                    $company_hierarchy         = $row[8] ?? null;
                    $title                     = $row[9] ?? null;
                    $designation               = $row[10] ?? null;
                    $display_name              = $row[11] ?? null;
                    $location                  = $row[12] ?? null;
                    $nic_no                    = $row[13] ?? null;
                    $category                  = $row[14] ?? null;
                    $employment_categories     = $row[15] ?? null;
                    $date_joined               = toDateOrNull($row[16] ?? null);
                    $date_resigned             = toDateOrNull($row[17] ?? null);
                    $category_ops_sales        = $row[18] ?? null;
                    $status                    = $row[19] ?? null;
                    $connection_status         = ($row[20] ?? null) ?: 'Connected';
                    $disconnection_date        = $row[21] ?? null; // keep as text per schema

                    // Bind as strings; MySQL will convert dates/decimals
                    $stmt->bind_param(
                        'ssssssssssssssssssssss',
                        $mobile_no,
                        $remarks,
                        $voice_data,
                        $branch_operational_remark,
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

                    if (!$stmt->execute()) {
                        $errors++;
                        // collect first few errors to show
                        if ($errors <= 5) {
                            $msgs[] = "Row $rowNum error: " . $stmt->error;
                        }
                    } else {
                        $inserted++;
                    }

                    // Optional: commit every N rows to keep transactions small
                    if (($inserted + $skipped + $errors) % 2000 === 0) {
                        $conn->commit();
                        $conn->begin_transaction();
                    }
                }

                fclose($h);
                $conn->commit();
                $stmt->close();

                $msgs[] = "Import finished. Inserted: $inserted, Skipped: $skipped, Errors: $errors.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Upload Mobile Issues CSV</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 24px; }
    .sample { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  </style>
</head>
<body>
<div class="container">
  <h3 class="mb-3 text-primary">Upload Mobile Issues — CSV Import</h3>

  <?php if ($hasResult): ?>
    <?php foreach ($msgs as $m): ?>
      <div class="alert <?= $errors ? 'alert-warning' : 'alert-success' ?>"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-12">
          <label for="csvFile" class="form-label fw-semibold">CSV file</label>
          <input class="form-control" type="file" id="csvFile" name="csvFile" accept=".csv" required>
        </div>
        <div class="col-12 form-check">
          <input class="form-check-input" type="checkbox" id="has_header" name="has_header" checked>
          <label class="form-check-label" for="has_header">First row is a header</label>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Upload & Import</button>
        </div>
      </form>

      <hr class="my-4">

      <p class="mb-2">Expected CSV column order (22 columns):</p>
      <div class="sample p-3 bg-light rounded small">
        mobile_no, remarks, voice_data, branch_operational_remarks, name_of_employee,
        hris_no, company_contribution, epf_no, company_hierarchy, title, designation,
        display_name, location, nic_no, category, employment_categories,
        date_joined (YYYY-MM-DD), date_resigned (YYYY-MM-DD),
        category_ops_sales, status, connection_status, disconnection_date
      </div>

      <p class="mt-3 text-muted small">
        Notes: Empty cells become <code>NULL</code>. Dates are normalized to <code>YYYY-MM-DD</code> when possible.
        <code>connection_status</code> defaults to <strong>Connected</strong> if blank.
      </p>
    </div>
  </div>
</div>
</body>
</html>
