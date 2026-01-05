<?php
// upload-branch-electricity.php
require_once 'connections/connection.php';

// Optional: keep large uploads smooth
@set_time_limit(0);
@ini_set('auto_detect_line_endings', '1');

function elog($msg) {
  if (!is_dir('logs')) @mkdir('logs', 0777, true);
  @file_put_contents('branch_electricity_upload.log', "[".date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}

$done = false;
$report = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
  if (!is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
    $report = "No file uploaded.";
  } else {
    $tmp = $_FILES['csv_file']['tmp_name'];
    $fh = fopen($tmp, 'r');
    if (!$fh) {
      $report = "Unable to open uploaded file.";
    } else {
      $inserted = 0; $updated = 0; $skipped = 0; $line = 0;

      // Try to detect header
      $header = fgetcsv($fh);
      $line++;

      if ($header === false) {
        $report = "Empty file.";
      } else {
        // Strip UTF-8 BOM if present
        if (isset($header[0])) {
          $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        // Build a column index map if header row looks like names
        $map = ['branch_code'=>0,'branch_name'=>1,'account_no'=>2,'bank_paid_to'=>3];
        $isHeader = false;

        $lower = array_map(function($x){ return strtolower(trim($x)); }, $header);
        if (in_array('branch_code', $lower) || in_array('branch name', $lower) || in_array('branch_name', $lower)) {
          // Treat first row as header; build index map
          $isHeader = true;
          $find = function($name, $alts, $arr) {
            $names = array_merge([$name], $alts);
            foreach ($names as $n) {
              $idx = array_search($n, $arr);
              if ($idx !== false) return $idx;
            }
            return null;
          };
          $map['branch_code'] = $find('branch_code', ['code', 'branch code'], $lower);
          $map['branch_name'] = $find('branch_name', ['branch', 'branch name'], $lower);
          $map['account_no']  = $find('account_no', ['account', 'account no', 'account number'], $lower);
          $map['bank_paid_to']= $find('bank_paid_to', ['bank', 'bank paid to'], $lower);

          // If some optional columns are missing, they stay null later.
        } else {
          // First row is data -> process it below by reusing as $row
          $isHeader = false;
          // move pointer back to treat $header as first data row
          rewind($fh);
        }

        // Transaction (optional, speeds up massive imports)
        mysqli_begin_transaction($conn);

        while (($row = fgetcsv($fh)) !== false) {
          $line++;

          // If we rewound (no header), ensure $row is valid (skip blank lines)
          if (count($row) === 1 && trim($row[0]) === '') { $skipped++; continue; }

          // Extract fields by map or by order
          if ($isHeader) {
            $branch_code = isset($map['branch_code']) && $map['branch_code'] !== null ? trim((string)($row[$map['branch_code']] ?? '')) : '';
            $branch_name = isset($map['branch_name']) && $map['branch_name'] !== null ? trim((string)($row[$map['branch_name']] ?? '')) : '';
            $account_no  = isset($map['account_no'])  && $map['account_no']  !== null ? trim((string)($row[$map['account_no']]  ?? '')) : '';
            $bank_paid_to= isset($map['bank_paid_to'])&& $map['bank_paid_to']!== null ? trim((string)($row[$map['bank_paid_to']]?? '')) : '';
          } else {
            // Assume CSV order: branch_code, branch_name, account_no, bank_paid_to
            $branch_code = trim((string)($row[0] ?? ''));
            $branch_name = trim((string)($row[1] ?? ''));
            $account_no  = trim((string)($row[2] ?? ''));
            $bank_paid_to= trim((string)($row[3] ?? ''));
          }

          if ($branch_code === '' || $branch_name === '') {
            $skipped++;
            elog("Line $line skipped: missing branch_code/branch_name");
            continue;
          }

          // Escape
          $bc = mysqli_real_escape_string($conn, $branch_code);
          $bn = mysqli_real_escape_string($conn, $branch_name);
          $an = mysqli_real_escape_string($conn, $account_no);
          $bp = mysqli_real_escape_string($conn, $bank_paid_to);

          // Upsert (update name/account/bank on duplicate branch_code)
          $sql = "
            INSERT INTO tbl_admin_branch_electricity
              (branch_code, branch_name, account_no, bank_paid_to)
            VALUES ('{$bc}', '{$bn}', ".($an!==''?"'{$an}'":"NULL").", ".($bp!==''?"'{$bp}'":"NULL").")
            ON DUPLICATE KEY UPDATE
              branch_name = VALUES(branch_name),
              account_no  = VALUES(account_no),
              bank_paid_to= VALUES(bank_paid_to)
          ";

          $ok = mysqli_query($conn, $sql);
          if ($ok) {
            if (mysqli_affected_rows($conn) === 1) {
              $inserted++;
            } else {
              // ON DUPLICATE that results in an update usually returns 2 affected rows on MySQL, but may be 1 if values identical
              $updated++;
            }
          } else {
            $skipped++;
            elog("Line $line error: ".mysqli_error($conn)." | SQL: $sql");
          }
        }

        mysqli_commit($conn);
        fclose($fh);

        $done = true;
        $report = "Upload finished. Inserted: $inserted, Updated: $updated, Skipped: $skipped.";
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Upload Branch Electricity CSV</title>
</head>
<body>
  <h3>Upload Branch Electricity CSV</h3>

  <?php if ($report !== ""): ?>
    <p><strong><?php echo htmlspecialchars($report); ?></strong></p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit">Upload</button>
  </form>

  <p style="margin-top:1rem;">
    Expected columns (header optional): <code>branch_code, branch_name, account_no, bank_paid_to</code>
  </p>
</body>
</html>
