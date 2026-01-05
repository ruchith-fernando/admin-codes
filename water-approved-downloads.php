<?php
// water-approved-downloads.php
define('SKIP_SESSION_CHECK', true);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'connections/connection.php';
require_once 'includes/userlog.php';

/* -------------- PAGE VIEW USERLOG ------------------- */
$hris = $_SESSION['hris'] ?? 'N/A';
$name = $_SESSION['name'] ?? 'Unknown';
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

try {
    userlog(sprintf(
        "📄 Viewed Water Approved PDF Log Page | User: %s (%s) | IP: %s",
        $name,
        $hris,
        $ip
    ));
} catch (Throwable $e) {
    // Silent fail
}
/* ----------------------------------------------------- */

function esc($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// Load logs from table (module = water)
$q = mysqli_query($conn, "
    SELECT *
    FROM tbl_admin_pdf_log
    WHERE module = 'water'
    ORDER BY generated_at DESC
");

$records = [];
if ($q) {
    while($r = mysqli_fetch_assoc($q)){
        $records[] = $r;
    }
}
?>

<div class="content font-size">
  <div class="container-fluid mt-4">

    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Water — Approved PDF Downloads</h5>

      <input type="text" id="searchPDF" class="form-control mb-3" 
        placeholder="Search date, branch, month, Bulk, HRIS...">

      <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="table-light">
            <tr>
              <th>PDF Name</th>
              <th>Type</th>
              <th>Month</th>
              <th>Branch / Group</th>
              <th>Approved By</th>
              <th>Generated</th>
              <th>Download</th>
            </tr>
          </thead>
          <tbody id="pdfTable">
            <?php foreach ($records as $r): 
                $file   = "exports/" . $r['pdf_name'];
                $exists = file_exists(__DIR__ . "/exports/" . $r['pdf_name']);

                // entity_label is the generic display field (branch name or bulk description)
                $label = isset($r['entity_label']) && trim((string)$r['entity_label']) !== ''
                    ? $r['entity_label']
                    : '-';
            ?>
            <tr>
              <td><?= esc($r['pdf_name']) ?></td>
              <td><?= esc(ucfirst($r['pdf_type'])) ?></td>
              <td><?= esc($r['month_applicable'] ?: '-') ?></td>
              <td><?= esc($label) ?></td>
              <td><?= esc($r['approved_by_name'] ?? '-') ?> (<?= esc($r['approved_by_hris'] ?? '-') ?>)</td>
              <td><?= esc($r['generated_at'] ?? '-') ?></td>

              <td>
                <?php if($exists): ?>
                  <a href="download-water-approved-pdf.php?file=<?= esc($r['pdf_name']) ?>" class="btn btn-primary btn-sm">
                      ⬇ Download
                  </a>
                <?php else: ?>
                  <span class="text-danger fw-bold">Missing File</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<script>
document.getElementById("searchPDF").addEventListener("keyup", function(){
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#pdfTable tr");

    rows.forEach(r => {
        r.style.display = r.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});
</script>
