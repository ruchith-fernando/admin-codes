<?php
// water-pending.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_POST['log_month_click']) && isset($_POST['month'])) {

    $month = $_POST['month'];
    $hris  = $_SESSION['hris'] ?? 'N/A';
    $user  = $_SESSION['name'] ?? 'Unknown';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

    $msg = "📅 Month selected | Month: $month | HRIS: $hris | User: $user | IP: $ip";

    userlog($msg); 

    echo json_encode(["status" => "success"]);
    exit;
}
/* ---------------------------------------------------------- */


$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Load USED months for dropdown
$month_q = mysqli_query($conn,"
    SELECT DISTINCT month_applicable 
    FROM tbl_admin_actual_water
    ORDER BY STR_TO_DATE(month_applicable,'%M %Y') ASC
");
$months = [];
while($m = mysqli_fetch_assoc($month_q)){
    $months[] = $m['month_applicable'];
}
?>

<div class="content font-size">
  <div class="container-fluid mt-4">

    <div class="card shadow bg-white rounded p-4">

      <h5 class="text-primary mb-3">Water — Pending Approvals</h5>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <!-- MONTH SELECT -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Pending Records</label>
        <select id="pending_month_select" class="form-select">
          <option value="">-- Choose Month --</option>
          <?php foreach($months as $m): ?>
            <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Approve All Button -->
      <button id="approveAllBtn" class="btn btn-success btn-sm d-none">✅ Approve All Remaining</button>

      <div id="alertContainer"></div>

      <!-- Table container -->
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
        <p>Rejecting record for <strong id="reject_branch"></strong> — <span id="reject_month"></span></p>

        <div class="mb-3">
          <label class="form-label">Reason</label>
          <select name="rejection_reason" id="rejection_reason" class="form-select" required>
            <option value="">Select...</option>
            <option>Incorrect Amount</option>
            <option>Duplicate Entry</option>
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
      <h5 class="modal-title">Approve All Remaining</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>

    <div class="modal-body">
      <p>This will approve all pending records except your own entries.</p>
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

  const rejectModal = new bootstrap.Modal('#rejectModal');
  const approveModal = new bootstrap.Modal('#approveConfirmModal');

  /* MONTH SELECT */
  document.getElementById("pending_month_select").addEventListener("change", function(){
      const month = this.value;
      document.getElementById("pending_table_container").innerHTML = "";
      document.getElementById("approveAllBtn").classList.add("d-none");

      if(month){
          fetch("water-pending.php", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: new URLSearchParams({ log_month_click: 1, month })
          });
      }

      if(!month) return;

      fetch("water-pending-load.php", {
          method:"POST",
          headers:{ "Content-Type":"application/x-www-form-urlencoded" },
          body: new URLSearchParams({ month })
      })
      .then(r=>r.text())
      .then(html=>{
          document.getElementById("pending_table_container").innerHTML = html;

          if(html.includes("approve-btn")){
              document.getElementById("approveAllBtn").classList.remove("d-none");
          }
      });
  });

  /* SINGLE APPROVAL */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".approve-btn");
    if(!btn) return;

    fetch("water-approve-single.php", {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        id: btn.dataset.id,
        branch: btn.dataset.branch,
        month: btn.dataset.month
      })
    })
    .then(r=>r.json())
    .then(data=>{
      showAlert(data.status, data.message);

      if(data.status === "success"){
        const row = btn.closest("tr");
        if (row) row.remove();

        updateApproveAllButton();
        if(data.pdf_url) showPDFDownloadForm(data.pdf_url);
      }
    });
  });

  /* OPEN REJECT MODAL */
  document.body.addEventListener("click", e=>{
    const btn = e.target.closest(".reject-btn");
    if(!btn) return;

    document.getElementById("reject_id").value = btn.dataset.id;
    document.getElementById("reject_branch").textContent = btn.dataset.branch;
    document.getElementById("reject_month").textContent = btn.dataset.month;

    rejectModal.show();
  });

  /* SHOW/HIDE OTHER REASON */
  document.getElementById("rejection_reason").addEventListener("change", function(){
    const show = this.value.includes("Other");
    document.getElementById("other_reason_div").style.display = show? "block" : "none";
    document.getElementById("other_reason").required = show;
  });

  /* SUBMIT REJECT */
  document.getElementById("rejectForm").addEventListener("submit", e=>{
    e.preventDefault();
    const fd = new FormData(e.target);

    fetch("water-reject.php", { method:"POST", body:fd })
      .then(r=>r.json())
      .then(data=>{
        rejectModal.hide();
        showAlert(data.status, data.message);

        if(data.status === "success"){
          const btn = document.querySelector(`button[data-id="${fd.get("id")}"]`);
          if(btn){
            const row = btn.closest("tr");
            if (row) row.remove();
          }

          updateApproveAllButton();
        }
      });
  });

  /* APPROVE ALL */
  document.getElementById("approveAllBtn").addEventListener("click", ()=>approveModal.show());

  document.getElementById("confirmApproveBtn").addEventListener("click", ()=>{
    approveModal.hide();

    const ids = Array.from(document.querySelectorAll(".reject-btn")).map(b=>b.dataset.id);

    if(ids.length === 0){
      return showAlert("info","No records to approve.");
    }

    fetch("water-bulk-approve.php", {
      method:"POST",
      headers:{ "Content-Type":"application/x-www-form-urlencoded" },
      body:"ids="+encodeURIComponent(ids.join(","))
    })
    .then(r=>r.json())
    .then(data=>{
      showAlert(data.success ? "success" : "danger", data.message);

      if(data.success){
        document.getElementById("pending_table_container").innerHTML = "";
        document.getElementById("approveAllBtn").classList.add("d-none");

        if(data.pdf_url) showPDFDownloadForm(data.pdf_url);
      }
    });
  });

  /* UPDATE APPROVE ALL VISIBILITY */
  function updateApproveAllButton(){
      const remaining = document.querySelectorAll(".approve-btn").length;
      if(remaining === 0){
         document.getElementById("approveAllBtn").classList.add("d-none");
      }
  }

  /* ALERT */
  function showAlert(type,msg){
    const alert=document.createElement('div');
    alert.className=`alert alert-${type==='success'?'success':'danger'} alert-dismissible fade show mt-3`;
    alert.innerHTML=msg+`<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.getElementById('alertContainer').prepend(alert);
    setTimeout(()=>bootstrap.Alert.getOrCreateInstance(alert).close(),6000);
  }

  /* PDF DOWNLOAD BOX */
  function showPDFDownloadForm(url){
    const box = document.createElement("div");
    box.className = "alert alert-secondary mt-3 pdf-box";

    box.innerHTML = `
      <h6 class="fw-bold">Download Approval PDF</h6>
      <p>Your approved record summary is ready.</p>
      <a href="${url}" target="_blank" class="btn btn-primary btn-sm">⬇ Download PDF</a>
    `;

    document.getElementById("alertContainer").prepend(box);

    // Auto-remove after 15 seconds
    setTimeout(() => {
        box.style.transition = "opacity 0.8s";
        box.style.opacity = "0";
        setTimeout(() => box.remove(), 800);
    }, 15000);
  }


})();
</script>

