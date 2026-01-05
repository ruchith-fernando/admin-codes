<?php
// water-rejected.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php'; // ✅ Include the userlog function
if (session_status() === PHP_SESSION_NONE) session_start();

$current_hris = $_SESSION['hris'] ?? '';
$current_name = $_SESSION['name'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

// ✅ Log when this page is viewed
userlog("👀 Viewed Water Rejected Entries | HRIS: $current_hris | User: $current_name | IP: $ip");

function esc($v){return htmlspecialchars($v ?? '', ENT_QUOTES,'UTF-8');}
function format_amount($v){return is_numeric($v)?'Rs. '.number_format((float)$v,2,'.',','):'-';}

// Fetch rejected entries for the logged-in user
$q = $conn->prepare("
  SELECT id, branch_code, branch, total_amount, month_applicable,
         entered_name, entered_hris, entered_at,
         rejected_name, rejected_hris, rejected_at, rejection_reason
  FROM tbl_admin_actual_water
  WHERE approval_status='rejected' 
  AND entered_hris = ?
  ORDER BY rejected_at DESC
");
$q->bind_param('s', $current_hris);
$q->execute();
$res = $q->get_result();
?>

<div class="content font-size">
  <div class="container-fluid mt-4">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-danger mb-0">Water — Rejected Entries</h5>
      </div>

      <div class="alert alert-info py-2 mb-3">
        <strong>Logged in as:</strong> <?= esc($current_name) ?> |
        <strong>HRIS:</strong> <?= esc($current_hris) ?>
      </div>

      <?php if ($res->num_rows === 0): ?>
        <div class="alert alert-success mb-0">🎉 No rejected entries found for you.</div>
      <?php else: ?>
        <div id="alertContainer"></div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Month</th>
                <th>Branch Code</th>
                <th>Branch</th>
                <th class="text-end">Amount</th>
                <th>Rejected By</th>
                <th>Rejected At</th>
                <th>Reason</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while($r = $res->fetch_assoc()): ?>
                <tr 
                  data-id="<?= esc($r['id']) ?>"
                  data-branch="<?= esc($r['branch']) ?>"
                  data-branch-code="<?= esc($r['branch_code']) ?>"
                  data-month="<?= esc($r['month_applicable']) ?>"
                >
                  <td><?= esc($r['id']) ?></td>
                  <td><?= esc($r['month_applicable']) ?></td>
                  <td><?= esc($r['branch_code']) ?></td>
                  <td><?= esc($r['branch']) ?></td>
                  <td class="text-end"><?= format_amount($r['total_amount']) ?></td>
                  <td><?= esc($r['rejected_name']) ?> (<?= esc($r['rejected_hris']) ?>)</td>
                  <td><?= esc($r['rejected_at']) ?></td>
                  <td><?= esc($r['rejection_reason']) ?></td>
                  <td>
                    <button class="btn btn-danger btn-sm delete-btn">🗑 Delete</button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Delete Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this rejected record?</p>
        <p class="small text-muted mb-0">This will not permanently delete it, but will mark it as deleted.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Confirm Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const deleteModal = new bootstrap.Modal('#deleteModal');
  let deleteTarget = null;

  // Delete button click
  document.body.addEventListener('click', e=>{
    const delBtn = e.target.closest('.delete-btn');
    if(!delBtn) return;
    const row = delBtn.closest('tr');
    deleteTarget = {
      id: row.dataset.id,
      branch: row.dataset.branch,
      code: row.dataset.branchCode,
      month: row.dataset.month
    };
    deleteModal.show();
  });

  // Confirm delete
  document.getElementById('confirmDeleteBtn').addEventListener('click', ()=>{
    if(!deleteTarget) return;
    fetch('water-delete.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        id: deleteTarget.id,
        branch: deleteTarget.branch,
        branch_code: deleteTarget.code,
        month: deleteTarget.month
      })
    })
    .then(r=>r.json())
    .then(data=>{
      deleteModal.hide();
      showAlert(data.status==='success'?'success':'danger', data.message);
      if(data.status==='success'){
        document.querySelector(`tr[data-id="${deleteTarget.id}"]`)?.remove();
      }
    })
    .catch(()=>showAlert('danger','Network error'));
  });

  function showAlert(type,msg){
    const alert=document.createElement('div');
    alert.className=`alert alert-${type} alert-dismissible fade show mt-3`;
    alert.innerHTML=msg+`<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.getElementById('alertContainer').prepend(alert);
    setTimeout(()=>bootstrap.Alert.getOrCreateInstance(alert).close(),6000);
  }
})();
</script>
