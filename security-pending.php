<?php
// security-pending.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$secPendingMainLog = __DIR__ . '/security-pending-main.log';
function sp_log($msg) {
    global $secPendingMainLog;
    @file_put_contents($secPendingMainLog, date('Y-m-d H:i:s') . " | " . $msg . "\n", FILE_APPEND);
}

sp_log("=== security-pending.php loaded ===");

if (isset($_POST['log_month_click']) && isset($_POST['month'])) {
    $month = $_POST['month'];
    $hris  = $_SESSION['hris'] ?? 'N/A';
    $user  = $_SESSION['name'] ?? 'Unknown';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

    $msg = "🔐 Security pending | Month selected | Month: $month | HRIS: $hris | User: $user | IP: $ip";
    userlog($msg);
    sp_log("Month click AJAX | {$msg}");

    header('Content-Type: application/json');
    echo json_encode(["status" => "success"]);
    exit;
}

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$monthSql = "
    SELECT DISTINCT month_applicable 
    FROM tbl_admin_actual_security_firmwise
    ORDER BY STR_TO_DATE(month_applicable,'%M %Y') ASC
";

$month_q = mysqli_query($conn, $monthSql);
if (!$month_q) {
    $err = mysqli_error($conn);
    echo "<div class='alert alert-danger m-3'>Database error loading months:<br>" . esc($err) . "</div>";
    return;
}

$months = [];
while($m = mysqli_fetch_assoc($month_q)){
    $months[] = $m['month_applicable'];
}
?>

<div class="content font-size">
  <div class="container-fluid mt-4">

    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Security — Pending Approvals</h5>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Pending Records</label>
        <select id="pending_month_select" class="form-select">
          <option value="">-- Choose Month --</option>
          <?php foreach($months as $m): ?>
            <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="alertContainer"></div>

      <div id="pending_table_container" class="table-responsive mt-3"></div>

    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title">Reject Record</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <form id="rejectForm">
      <div class="modal-body">
        <input type="hidden" name="id" id="reject_id">
        <input type="hidden" name="branch" id="reject_branch_hidden">
        <input type="hidden" name="branch_code" id="reject_branch_code_hidden">
        <input type="hidden" name="month" id="reject_month_hidden">

        <p>Rejecting record for <strong id="reject_branch"></strong> — <span id="reject_month"></span></p>

        <div class="mb-3">
          <label class="form-label">Reason</label>
          <select name="rejection_reason" id="rejection_reason" class="form-select" required>
            <option value="">Select...</option>
            <option>Incorrect Amount</option>
            <option>Incorrect Shifts</option>
            <option>Wrong Firm</option>
            <option>Wrong Branch</option>
            <option>Invalid Month</option>
            <option>Missing Supporting Documents</option>
            <option>Other (specify below)</option>
          </select>
        </div>

        <div id="other_reason_div" class="mb-3" style="display:none;">
          <label class="form-label">Other Reason</label>
          <textarea name="other_reason" id="other_reason" class="form-control" rows="2"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm Reject</button>
      </div>
    </form>

  </div></div>
</div>

<!-- Approve All Modal -->
<div class="modal fade" id="approveConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">

    <div class="modal-header bg-success text-white">
      <h5 class="modal-title">Approve All</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
      <p id="approveAllModalText">This will approve all pending records except your own entries.</p>
      <p>Are you sure you want to continue?</p>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="button" id="confirmApproveBtn" class="btn btn-success">Confirm Approve</button>
    </div>

  </div></div>
</div>

<script>
(function(){
  const rejectModalEl  = document.getElementById('rejectModal');
  const approveModalEl = document.getElementById('approveConfirmModal');

  const rejectModal  = rejectModalEl  ? new bootstrap.Modal(rejectModalEl)  : null;
  const approveModal = approveModalEl ? new bootstrap.Modal(approveModalEl) : null;

  let currentBulkType = null; // 'firmwise' or 'inv2000'

  /* MONTH SELECT */
  const monthSelect = document.getElementById("pending_month_select");
  if (monthSelect) {
    monthSelect.addEventListener("change", function(){
        const month = this.value;
        document.getElementById("pending_table_container").innerHTML = "";

        if(month){
            fetch("security-pending.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ log_month_click: 1, month })
            });
        }

        if(!month) return;

        fetch("security-pending-load.php", {
            method:"POST",
            headers:{ "Content-Type":"application/x-www-form-urlencoded" },
            body: new URLSearchParams({ month })
        })
        .then(r=>r.text())
        .then(html=>{
            document.getElementById("pending_table_container").innerHTML = html;
        });
    });
  }

  /* INDIVIDUAL APPROVE (routes by data-type) */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".approve-btn");
    if(!btn) return;

    const type = btn.dataset.type || "firmwise";
    const url  = (type === "inv2000") ? "security-2000-approve-single.php" : "security-approve-single.php";

    fetch(url, {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        id: btn.dataset.id,
        branch: btn.dataset.branch,
        branch_code: btn.dataset.branchCode || '',
        month: btn.dataset.month
      })
    })
    .then(r=>r.json())
    .then(data=>{
      const ok = (data.status === "success") || (data.success === true);
      showAlert(ok ? "success" : "danger", data.message || "Done");

      if(ok){
        const row = btn.closest("tr");
        if (row) row.remove();
        refreshBulkButtons();
      }
    })
    .catch(err => showAlert("danger","Error approving: "+err));
  });

  /* OPEN REJECT MODAL */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".reject-btn");
    if(!btn) return;

    document.getElementById("reject_id").value                 = btn.dataset.id;
    document.getElementById("reject_branch").textContent       = btn.dataset.branch;
    document.getElementById("reject_branch_hidden").value      = btn.dataset.branch;
    document.getElementById("reject_branch_code_hidden").value = btn.dataset.branchCode || '';
    document.getElementById("reject_month").textContent        = btn.dataset.month;
    document.getElementById("reject_month_hidden").value       = btn.dataset.month;

    document.getElementById("rejectForm").dataset.type = btn.dataset.type || 'firmwise';

    if (rejectModal) rejectModal.show();
  });

  /* SHOW/HIDE OTHER REASON */
  document.getElementById("rejection_reason").addEventListener("change", function(){
    const show = this.value.includes("Other");
    document.getElementById("other_reason_div").style.display = show? "block" : "none";
    document.getElementById("other_reason").required = show;
  });

  /* SUBMIT REJECT (routes by rejectForm.dataset.type) */
  document.getElementById("rejectForm").addEventListener("submit", e=>{
    e.preventDefault();
    const fd = new FormData(e.target);

    const type = e.target.dataset.type || 'firmwise';
    const url  = (type === "inv2000") ? "security-2000-reject.php" : "security-reject.php";

    fetch(url, { method:"POST", body:fd })
      .then(r=>r.json())
      .then(data=>{
        if (rejectModal) rejectModal.hide();

        const ok = (data.status === "success") || (data.success === true);
        showAlert(ok ? "success" : "danger", data.message || "Done");

        if(ok){
          const id = fd.get("id");
          const rowBtn = document.querySelector(`button.reject-btn[data-id="${id}"][data-type="${type}"]`);
          const row    = rowBtn ? rowBtn.closest("tr") : null;
          if (row) row.remove();
          refreshBulkButtons();
        }
      })
      .catch(err => showAlert("danger","Error rejecting: "+err));
  });

  /* CLICK BULK APPROVE BUTTON (per table) */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".approve-all-btn");
    if(!btn) return;
    if (btn.disabled) return;

    currentBulkType = btn.dataset.type || null;
    if (!currentBulkType) return;

    const label = (currentBulkType === "inv2000") ? "2000 Series Invoices" : "Normal Security (Firmwise)";
    document.getElementById("approveAllModalText").textContent =
      `This will approve all pending records in: ${label} (except your own entries).`;

    if (approveModal) approveModal.show();
  });

  /* CONFIRM BULK APPROVE (only selected table type) */
  document.getElementById("confirmApproveBtn").addEventListener("click", ()=>{
    if (approveModal) approveModal.hide();

    if (!currentBulkType) return;

    const ids = Array.from(document.querySelectorAll(`.approve-btn[data-type="${currentBulkType}"]`))
      .map(b => b.dataset.id)
      .filter(Boolean);

    if (ids.length === 0) {
      return showAlert("info","No records to approve in this table.");
    }

    const url = (currentBulkType === "inv2000")
      ? "security-2000-bulk-approve.php"
      : "security-bulk-approve.php";

    fetch(url, {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body:"ids="+encodeURIComponent(ids.join(","))
    })
    .then(r=>r.json())
    .then(data=>{
      const ok = (data.success === true) || (data.status === "success");
      showAlert(ok ? "success" : "danger", data.message || "Done");

      if (ok) {
        // remove rows for this type (only those that had approve buttons)
        ids.forEach(id => {
          const b = document.querySelector(`.approve-btn[data-type="${currentBulkType}"][data-id="${id}"]`);
          const row = b ? b.closest("tr") : null;
          if (row) row.remove();
        });
        refreshBulkButtons();
      }
    })
    .catch(err => showAlert("danger","Error calling bulk approve: " + err))
    .finally(()=>{ currentBulkType = null; });
  });

  /* After approvals/rejections, hide/disable bulk buttons if no actionable rows left */
  function refreshBulkButtons(){
    const fwRemaining  = document.querySelectorAll('.approve-btn[data-type="firmwise"]').length;
    const invRemaining = document.querySelectorAll('.approve-btn[data-type="inv2000"]').length;

    const fwBtn  = document.querySelector('.approve-all-btn[data-type="firmwise"]');
    const invBtn = document.querySelector('.approve-all-btn[data-type="inv2000"]');

    if (fwBtn) {
      fwBtn.disabled = (fwRemaining === 0);
      fwBtn.classList.toggle('disabled', fwRemaining === 0);
    }
    if (invBtn) {
      invBtn.disabled = (invRemaining === 0);
      invBtn.classList.toggle('disabled', invRemaining === 0);
    }
  }

  /* ALERT */
  function showAlert(type,msg){
    const alert=document.createElement('div');
    alert.className=`alert alert-${type==='success'?'success': (type==='info'?'info':'danger')} alert-dismissible fade show mt-3`;
    alert.innerHTML=msg+`<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.getElementById('alertContainer').prepend(alert);
    setTimeout(()=>bootstrap.Alert.getOrCreateInstance(alert).close(),6000);
  }

})();
</script>
