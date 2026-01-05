<?php
// slt-branches-upload.php
// Layout uses your standard Bootstrap card style.

if (isset($_GET['action']) && $_GET['action'] === 'template') {
    // Output a simple CSV template
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="slt_branches_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Connection Number', 'Allocated To']);
    fputcsv($out, ['1234567890', 'Head Office']);
    fputcsv($out, ['0987654321', 'Branch - Kandy']);
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SLT Branches Import</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Import SLT Branch Allocations</h5>

      <div class="mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="?action=template">Download CSV Template</a>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Import completed successfully.</div>
      <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger">Import failed. Check the log for details.</div>
      <?php endif; ?>

      <form class="row g-3" action="slt-branches-upload-process.php" method="post" enctype="multipart/form-data">
        <div class="col-md-6">
          <label class="form-label">Upload CSV (export your Excel as CSV)</label>
          <input type="file" class="form-control" name="csv_file" accept=".csv" required>
          <div class="form-text">Columns required: <strong>Connection Number</strong>, <strong>Allocated To</strong></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Update existing rows?</label>
          <select class="form-select" name="allow_update" required>
            <option value="yes" selected>Yes — update if connection already exists</option>
            <option value="no">No — skip duplicates</option>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Import Now</button>
        </div>
      </form>

      <hr class="my-4">
      <p class="text-muted small mb-0">
        Notes: This tool logs to <code>logs/slt-branches-import.log</code>. Ensure the <code>logs</code> folder is writable.
      </p>
    </div>
  </div>
</div>
</body>
</html>
