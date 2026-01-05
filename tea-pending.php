<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$month_q = mysqli_query($conn,"
  SELECT DISTINCT month_year
  FROM tbl_admin_tea_service_hdr
  WHERE approval_status='pending'
  ORDER BY STR_TO_DATE(CONCAT('01 ', month_year), '%d %M %Y') DESC
");
$months = [];
while($m = mysqli_fetch_assoc($month_q)) $months[] = $m['month_year'];
?>

<div class="content font-size">
  <div class="container-fluid mt-4">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Tea Service — Pending Approvals</h5>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">Select Month</label>
        <select id="tea_pending_month" class="form-select">
          <option value="">-- Choose Month --</option>
          <?php foreach($months as $m): ?>
            <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button id="teaApproveAllBtn" class="btn btn-success btn-sm d-none">✅ Approve All Remaining</button>

      <div id="teaPendingAlert"></div>
      <div id="tea_pending_table" class="table-responsive mt-3"></div>

    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="teaRejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title">Reject Tea Record</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <form id="teaRejectForm">
      <div class="modal-body">
        <input type="hidden" name="id" id="tea_reject_id">

        <div class="mb-3">
          <label class="form-label">Reason</label>
          <select name="reason" id="tea_reject_reason" class="form-select" required>
            <option value="">Select...</option>
            <option>Incorrect Amount</option>
            <option>Duplicate Entry</option>
            <option>Wrong Floor</option>
            <option>Wrong Month</option>
            <option>Missing Supporting Documents</option>
            <option>Other (specify below)</option>
          </select>
        </div>

        <div id="tea_other_reason_div" class="mb-3" style="display:none;">
          <label class="form-label">Other Reason</label>
          <textarea name="other_reason" id="tea_other_reason" class="form-control" rows="2"></textarea>
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
<div class="modal fade" id="teaApproveAllModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header bg-success text-white">
      <h5 class="modal-title">Approve All Remaining</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p>This will approve all pending records except your own entries.</p>
      <p>Continue?</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="button" id="teaConfirmApproveAllBtn" class="btn btn-success">Confirm</button>
    </div>
  </div></div>
</div>

<script>
(function(){
  const rejectModal = new bootstrap.Modal('#teaRejectModal');
  const approveAllModal = new bootstrap.Modal('#teaApproveAllModal');

  function showAlert(type,msg){
    const cls = (type==="success") ? "alert-success" : "alert-danger";
    const el = `<div class="alert ${cls} alert-dismissible fade show mt-3">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    document.getElementById("teaPendingAlert").innerHTML = el;
  }

  function reloadTable(){
    const month = document.getElementById("tea_pending_month").value;
    document.getElementById("tea_pending_table").innerHTML = "";
    document.getElementById("teaApproveAllBtn").classList.add("d-none");
    if(!month) return;

    fetch("tea-pending-load.php", {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body: new URLSearchParams({ month_year: month })
    })
    .then(r=>r.text())
    .then(html=>{
      document.getElementById("tea_pending_table").innerHTML = html;
      if(html.includes("tea-approve-btn")){
        document.getElementById("teaApproveAllBtn").classList.remove("d-none");
      }
    });
  }

  document.getElementById("tea_pending_month").addEventListener("change", reloadTable);

  /* Single approve */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".tea-approve-btn");
    if(!btn) return;

    fetch("tea-approve-single.php", {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body: new URLSearchParams({ id: btn.dataset.id })
    })
    .then(r=>r.json())
    .then(data=>{
      showAlert(data.status, data.message);
      if(data.status === "success") reloadTable();
    });
  });

  /* Open reject modal */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".tea-reject-btn");
    if(!btn) return;

    document.getElementById("tea_reject_id").value = btn.dataset.id;
    rejectModal.show();
  });

  document.getElementById("tea_reject_reason").addEventListener("change", function(){
    const show = this.value.includes("Other");
    document.getElementById("tea_other_reason_div").style.display = show ? "block" : "none";
    document.getElementById("tea_other_reason").required = show;
  });

  /* Reject submit */
  document.getElementById("teaRejectForm").addEventListener("submit", e=>{
    e.preventDefault();
    const fd = new FormData(e.target);

    fetch("tea-reject.php", { method:"POST", body:fd })
      .then(r=>r.json())
      .then(data=>{
        rejectModal.hide();
        showAlert(data.status, data.message);
        if(data.status === "success") reloadTable();
      });
  });

  /* Approve all */
  document.getElementById("teaApproveAllBtn").addEventListener("click", ()=>approveAllModal.show());
  document.getElementById("teaConfirmApproveAllBtn").addEventListener("click", ()=>{
    approveAllModal.hide();

    const ids = Array.from(document.querySelectorAll(".tea-reject-btn"))
      .map(b=>b.dataset.id);

    if(ids.length === 0) return showAlert("error","No records.");

    fetch("tea-bulk-approve.php", {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body: "ids=" + encodeURIComponent(ids.join(","))
    })
    .then(r=>r.json())
    .then(data=>{
      showAlert(data.success ? "success" : "error", data.message);
      if(data.success) reloadTable();
    });
  });

})();
</script>
