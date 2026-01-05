<?php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Photocopy — Pending Approvals</h5>

      <div class="mb-3">
        <button class="btn btn-outline-primary" id="pc_refresh_pending">Refresh</button>
      </div>

      <div id="pc_approval_msg"></div>
      <div id="pc_pending_table" class="table-responsive"></div>
    </div>
  </div>
</div>

<script>
function loadPending(){
  $("#pc_approval_msg").html("");
  $.post("photocopy-approval-fetch.php", {}, function(res){
    $("#pc_pending_table").html(res.table || "");
  }, "json");
}

$(document).on("click","#pc_refresh_pending", loadPending);

$(document).on("click",".pc-approve-btn", function(){
  const id = $(this).data("id");
  $.post("photocopy-approval-action.php", { id, action:"approve" }, function(res){
    $("#pc_approval_msg").html(`<div class="alert ${res.success?'alert-success':'alert-danger'}">${res.message||''}</div>`);
    loadPending();
  }, "json");
});

$(document).on("click",".pc-reject-btn", function(){
  const id = $(this).data("id");
  const reason = prompt("Rejection reason:");
  if (reason === null) return;
  $.post("photocopy-approval-action.php", { id, action:"reject", reason }, function(res){
    $("#pc_approval_msg").html(`<div class="alert ${res.success?'alert-success':'alert-danger'}">${res.message||''}</div>`);
    loadPending();
  }, "json");
});

loadPending();
</script>
