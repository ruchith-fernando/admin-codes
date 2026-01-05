<?php
// actual-postage.php
include 'connections/connection.php';
include 'postal-serial-modal.php';
$departments = [];
$result = $conn->query("SELECT DISTINCT department FROM tbl_admin_department_list ORDER BY department ASC");
while ($row = $result->fetch_assoc()) {
    $departments[] = $row['department'];
}

$lastPostageQuery = $conn->query("SELECT id, end_balance, added_date FROM tbl_admin_actual_postage_stamps ORDER BY id DESC LIMIT 1");

$open_balance = 0;
$last_added_date = null;

if ($row = $lastPostageQuery->fetch_assoc()) {
    $open_balance = floatval($row['end_balance']);
    $last_added_date = $row['added_date'];
}

$cheque_total = 0;
if ($last_added_date) {
    $stmt = $conn->prepare("SELECT SUM(cheque_amount) FROM tbl_admin_postage_cheques WHERE created_at > ?");
    $stmt->bind_param("s", $last_added_date);
    $stmt->execute();
    $stmt->bind_result($cheque_total);
    $stmt->fetch();
    $stmt->close();
} else {
    $result = $conn->query("SELECT SUM(cheque_amount) AS total FROM tbl_admin_postage_cheques");
    $cheque_total = $result->fetch_assoc()['total'] ?? 0;
}

$default_open_balance = $open_balance + floatval($cheque_total);
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card p-4 shadow-sm">
      <h5 class="mb-4 text-primary">Postage & Stamps Entry</h5>

      <div id="responseMessage"></div>

      <form id="postageForm">
        <div class="row mb-3">
          <div class="col-md-4">
            <label>Date</label>
            <input type="text" name="entry_date" id="entry_date" class="form-control" autocomplete="off" required>
          </div>
          <div class="col-md-4">
            <label>Department</label>
            <select name="department" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label>Where to - Colombo</label>
            <input type="text" name="where_to_colombo" class="form-control number" value="0">
          </div>
          <div class="col-md-3">
            <label>Where to - Outstation</label>
            <input type="text" name="where_to_outstation" class="form-control number" value="0">
          </div>
          <div class="col-md-3">
            <label>Total</label>
            <input type="text" id="total_display" class="form-control" readonly>
          </div>
        </div>

        <div class="mb-3">
          <label>Stamp Breakdown</label>
          <table class="table table-bordered" id="stampTable">
            <thead>
              <tr>
                <th>Stamp Value</th>
                <th>Quantity</th>
                <th>Subtotal</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="text" name="stamp_value[]" class="form-control number stamp-val" value="0"></td>
                <td><input type="number" name="stamp_quantity[]" class="form-control stamp-qty" value="0"></td>
                <td><input type="text" class="form-control subtotal" readonly></td>
                <td><button type="button" class="btn btn-danger btn-remove">×</button></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="text-end"><strong>Total Stamp Amount</strong></td>
                <td><input type="text" id="total_stamp_amount" class="form-control" readonly></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <button type="button" id="addRow" class="btn btn-secondary">+ Add Stamp</button>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label>Open Balance</label>
            <input type="text" name="open_balance" id="open_balance" class="form-control number" value="<?= number_format($default_open_balance, 2) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label>End Balance</label>
            <input type="text" name="end_balance" id="end_balance" class="form-control number bg-light" value="0" readonly>
          </div>
        </div>

        <div class="mb-4">
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>

      <div id="pendingTableArea"></div>
    </div>
  </div>
</div>
<script src="postal-serial.js"></script>
<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Success</h5>
      </div>
      <div class="modal-body">Postage record saved successfully.</div>
      <div class="modal-footer">
        <button class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="actual-postage-logic.js"></script>
<script>
$(document).ready(function () {
  $('#entry_date').datepicker({
    format: 'yyyy-mm-dd',
    endDate: new Date(),
    autoclose: true,
    todayHighlight: true
  }).datepicker('setDate', new Date());

  loadPendingTable();

  $('#postageForm').submit(function (e) {
    e.preventDefault();
    $.ajax({
      url: 'ajax-save-postage.php',
      method: 'POST',
      data: $(this).serialize(),
      success: function (response) {
        $('#responseMessage').html(response);
        new bootstrap.Modal(document.getElementById('successModal')).show();

        $('#postageForm')[0].reset();
        $('#stampTable tbody').html(`
          <tr>
            <td><input type="text" name="stamp_value[]" class="form-control number stamp-val" value="0"></td>
            <td><input type="number" name="stamp_quantity[]" class="form-control stamp-qty" value="0"></td>
            <td><input type="text" class="form-control subtotal" readonly tabindex="-1"></td>
            <td><button type="button" class="btn btn-danger btn-remove">×</button></td>
          </tr>
        `);

        $.get('ajax-get-open-balance.php', function(data) {
          $("#open_balance").val(data);
        });

        updateTotal();
        updateSubtotals();
        updateEndBalance();
        loadPendingTable();
      },
      error: function () {
        $('#responseMessage').html('<div class="alert alert-danger">Error submitting data.</div>');
      }
    });
  });
});

function loadPendingTable() {
  $.get('ajax-pending-postage-table.php', function (data) {
    $('#pendingTableArea').html(data);
  });
}
</script>
