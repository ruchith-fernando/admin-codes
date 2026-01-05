<?php
include 'connections/connection.php';
session_start();

$floors = mysqli_query($conn, "SELECT id, floor_no, floor_name FROM tbl_admin_floors WHERE is_active=1 ORDER BY floor_no");
$items  = mysqli_query($conn, "SELECT id, item_name FROM tbl_admin_tea_items WHERE is_active=1 ORDER BY sort_order, item_name");
?>
<form id="teaForm">
  <div class="row mb-3">
    <div class="col-md-3">
      <label>Month</label>
      <input type="month" name="month" class="form-control" required>
    </div>
    <div class="col-md-3">
      <label>Floor</label>
      <select name="floor_id" class="form-select" required>
        <option value="">-- Select Floor --</option>
        <?php while($f = mysqli_fetch_assoc($floors)) { ?>
          <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['floor_name']) ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="col-md-3">
      <label>SR Number</label>
      <input type="text" name="sr_number" class="form-control">
    </div>
    <div class="col-md-3">
      <label>OT Amount (No Tax)</label>
      <input type="number" step="0.01" min="0" name="ot_amount" class="form-control" value="0">
    </div>
  </div>

  <div class="row">
    <?php while($it = mysqli_fetch_assoc($items)) { ?>
      <div class="col-md-2 mb-2">
        <label><?= htmlspecialchars($it['item_name']) ?> units</label>
        <input type="number" min="0" name="units[<?= (int)$it['id'] ?>]" class="form-control" value="0">
      </div>
    <?php } ?>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button type="button" id="btnPreview" class="btn btn-secondary">Preview</button>
    <button type="button" id="btnConfirm" class="btn btn-primary" disabled>Confirm & Save (Pending)</button>
  </div>
</form>

<hr class="my-4">
<div id="previewArea"></div>

<script>
let previewToken = null;

$('#btnPreview').on('click', function () {
  $('#previewArea').html('<div class="text-center"><div class="spinner-border"></div><div>Calculating...</div></div>');

  $.post('tea-service-calc.php', $('#teaForm').serialize(), function(res){
    if(res.status !== 'success'){
      $('#previewArea').html('<div class="alert alert-danger">'+res.message+'</div>');
      $('#btnConfirm').prop('disabled', true);
      previewToken = null;
      return;
    }

    previewToken = res.preview_token;
    $('#btnConfirm').prop('disabled', false);

    // build preview HTML
    let html = `
      <h6 class="text-primary">Preview</h6>
      <table class="table table-bordered">
        <thead class="table-light">
          <tr>
            <th>Item</th><th>Units</th><th>Rate</th><th>Total</th><th>SSCL</th><th>VAT</th><th>Grand</th>
          </tr>
        </thead>
        <tbody>
    `;
    res.lines.forEach(l=>{
      html += `<tr>
        <td>${l.item_name}</td>
        <td>${l.units}</td>
        <td>${Number(l.unit_price).toFixed(2)}</td>
        <td>${Number(l.total_price).toFixed(2)}</td>
        <td>${Number(l.sscl_amount).toFixed(2)}</td>
        <td>${Number(l.vat_amount).toFixed(2)}</td>
        <td>${Number(l.line_grand_total).toFixed(2)}</td>
      </tr>`;
    });

    html += `</tbody></table>
      <div class="row">
        <div class="col-md-3"><b>Items Total:</b> ${Number(res.summary.total_price).toFixed(2)}</div>
        <div class="col-md-3"><b>SSCL:</b> ${Number(res.summary.sscl_amount).toFixed(2)}</div>
        <div class="col-md-3"><b>VAT:</b> ${Number(res.summary.vat_amount).toFixed(2)}</div>
        <div class="col-md-3"><b>OT:</b> ${Number(res.summary.ot_amount).toFixed(2)}</div>
      </div>
      <div class="mt-2"><b>Grand Total:</b> ${Number(res.summary.grand_total).toFixed(2)}</div>
    `;
    $('#previewArea').html(html);

  }, 'json');
});

$('#btnConfirm').on('click', function(){
  if(!previewToken) return;

  $.post('tea-service-save.php', { preview_token: previewToken }, function(res){
    if(res.status === 'success'){
      $('#previewArea').html('<div class="alert alert-success">Saved as PENDING successfully.</div>');
      $('#teaForm')[0].reset();
      $('#btnConfirm').prop('disabled', true);
      previewToken = null;
    } else {
      $('#previewArea').html('<div class="alert alert-danger">'+res.message+'</div>');
    }
  }, 'json');
});
</script>
