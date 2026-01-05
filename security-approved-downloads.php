<?php
// security-approved-downloads.php
define('SKIP_SESSION_CHECK', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connections/connection.php';
require_once 'includes/userlog.php';

/* -------------- PAGE VIEW USERLOG ------------------- */
$hris = $_SESSION['hris'] ?? 'N/A';
$name = $_SESSION['name'] ?? 'Unknown';
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

try {
    userlog(sprintf(
        "📄 Viewed Security Approved PDF Log Page | User: %s (%s) | IP: %s",
        $name,
        $hris,
        $ip
    ));
} catch (Throwable $e) {
    // Silent fail – same style as other pages
}
/* ----------------------------------------------------- */

function esc($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// Load logs from common PDF log (security only)
$q = mysqli_query(
    $conn,
    "SELECT *
     FROM tbl_admin_pdf_log
     WHERE module = 'security'
     ORDER BY generated_at DESC"
);

$records = [];
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $records[] = $r;
    }
}
?>
<style>
/* Remove horizontal scroll: force wrapping */
.security-pdf-table{
  table-layout: fixed;     /* key: prevents wide columns forcing scroll */
  width: 100%;
}

.security-pdf-table th,
.security-pdf-table td{
  white-space: normal !important;     /* allow wrap */
  overflow-wrap: anywhere;            /* break long strings */
  word-break: break-word;             /* fallback */
  vertical-align: top;
}

/* Optional: make key columns wider, others smaller */
.security-pdf-table th:nth-child(1),
.security-pdf-table td:nth-child(1){ width: 22%; } /* PDF Name */
.security-pdf-table th:nth-child(4),
.security-pdf-table td:nth-child(4){ width: 22%; } /* Firm/Branch Group */

.security-pdf-table th:nth-child(2),
.security-pdf-table td:nth-child(2){ width: 8%; }  /* Type */
.security-pdf-table th:nth-child(3),
.security-pdf-table td:nth-child(3){ width: 10%; } /* Month */
.security-pdf-table th:nth-child(5),
.security-pdf-table td:nth-child(5){ width: 16%; } /* Approved By */
.security-pdf-table th:nth-child(6),
.security-pdf-table td:nth-child(6){ width: 12%; } /* Generated */
.security-pdf-table th:nth-child(7),
.security-pdf-table td:nth-child(7){ width: 10%; } /* Download button */

</style>
<div class="content font-size">
  <div class="container-fluid mt-4">

    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Security — Approved PDF Downloads</h5>

      <input type="text" id="searchSecurityPDF" class="form-control mb-3" 
        placeholder="Search date, firm / branch, month, Bulk, HRIS...">

      <div class="table-responsive" style="overflow-x:hidden;">
        <table class="table table-bordered table-hover w-100 security-pdf-table">
          <thead class="table-light">
            <tr>
              <th>PDF Name</th>
              <th>Type</th>
              <th>Month</th>
              <th>Firm / Branch Group</th>
              <th>Approved By</th>
              <th>Generated</th>
              <th>Download</th>
            </tr>
          </thead>
          <tbody id="securityPdfTable">
            <?php foreach ($records as $r): 
                // File is stored in the same exports folder as water
                $file   = "exports/" . $r['pdf_name'];
                $exists = file_exists(__DIR__ . "/exports/" . $r['pdf_name']);

                // Label taken from entity_label in tbl_admin_pdf_log
                $label = trim((string)($r['entity_label'] ?? ''));
            ?>
            <tr>
              <td><?= esc($r['pdf_name']) ?></td>
              <td><?= esc(ucfirst($r['pdf_type'])) ?></td>
              <td><?= esc($r['month_applicable'] ?? '-') ?></td>
              <td><?= esc($label !== '' ? $label : '-') ?></td>
              <td><?= esc($r['approved_by_name'] ?? '-') ?> (<?= esc($r['approved_by_hris'] ?? '-') ?>)</td>
              <td><?= esc($r['generated_at'] ?? '-') ?></td>

              <td>
                <?php if ($exists): ?>
                  <a href="download-security-approved-pdf.php?file=<?= esc($r['pdf_name']) ?>" 
                     class="btn btn-primary btn-sm">
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
document.getElementById("searchSecurityPDF").addEventListener("keyup", function(){
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#securityPdfTable tr");

    rows.forEach(r => {
        r.style.display = r.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});
</script>
