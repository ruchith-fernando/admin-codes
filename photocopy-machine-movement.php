<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// branches for dropdown
$branches = [];
$q = mysqli_query($conn, "SELECT branch_code, branch_name FROM tbl_admin_branches WHERE is_active=1 ORDER BY branch_name");
while ($q && ($r=mysqli_fetch_assoc($q))) $branches[] = $r;
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Photocopy — Machine Movement</h5>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">Serial</label>
          <input type="text" id="mv_serial" class="form-control" placeholder="Enter Serial">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Move Date</label>
          <input type="date" id="mv_date" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">To Branch</label>
          <select id="mv_to_branch" class="form-select">
            <option value="">-- Select --</option>
            <?php foreach($branches as $b): ?>
              <option value="<?= htmlspecialchars($b['branch_code']) ?>">
                <?= htmlspecialchars($b['branch_name']." (".$b['branch_code'].")") ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold">Reason</label>
          <input type="text" id="mv_reason" class="form-control" placeholder="Reason">
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-success" id="mv_btn">Move Machine</button>
      </div>

      <div id="mv_msg" class="mt-3"></div>

      <hr>
      <h6 class="text-primary">Recent Moves</h6>
      <div id="mv_table" class="table-responsive"></div>
    </div>
  </div>
</div>

<script>
function loadMoves(){
  $.post("photocopy-machine-moves-fetch.php", {}, function(res){
    $("#mv_table").html(res.table || "");
  }, "json");
}

$("#mv_btn").on("click", function(){
  const serial = ($("#mv_serial").val()||"").trim();
  const move_date = $("#mv_date").val();
  const to_branch = $("#mv_to_branch").val();
  const reason = ($("#mv_reason").val()||"").trim();

  if (!serial || !move_date || !to_branch) {
    $("#mv_msg").html(`<div class="alert alert-danger">Serial, Move Date, and To Branch are required.</div>`);
    return;
  }

  $.post("ajax-move-photocopy-machine.php", { serial, move_date, to_branch, reason }, function(res){
    $("#mv_msg").html(`<div class="alert ${res.success?'alert-success':'alert-danger'}">${res.message||''}</div>`);
    if (res.success) { $("#mv_serial").val(""); $("#mv_reason").val(""); }
    loadMoves();
  }, "json");
});

loadMoves();
</script>
