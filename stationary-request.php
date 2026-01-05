<!-- stationary-request.php -->
<?php
include 'connections/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$hris = $_SESSION['hris'] ?? '';
$user_level = $_SESSION['user_level'] ?? '';

$allowed_roles = ['stationary_request', 'head_of_admin','boic'];

$roles = array_map('trim', explode(',', $user_level));

if (count(array_intersect($roles, $allowed_roles)) === 0) {
    echo '<div class="text-danger p-3">Access denied.</div>';
    exit;
}



date_default_timezone_set('Asia/Colombo'); 
$branch_code = $_SESSION['branch_code'] ?? '';
$branch_name = $_SESSION['branch_name'] ?? '';
$hris = $_SESSION['hris'] ?? 'UNKNOWN';

// Build item options ONCE including a placeholder
$itemOptions = "<option value=\"\" selected disabled>-- Select Item --</option>";
$res = mysqli_query($conn, "SELECT DISTINCT s.item_code, m.item_description
                            FROM tbl_admin_stationary_stock_in s
                            JOIN tbl_admin_print_stationary_master m ON s.item_code = m.item_code
                            ORDER BY s.item_code ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $item_code = htmlspecialchars($row['item_code']);
    $item_name = htmlspecialchars($row['item_description']);
    $itemOptions .= "<option value='$item_code'>$item_code - $item_name</option>";
}
?>

<style>
.select2-container--default .select2-selection--single {
  height: 38px !important;
  font-size: 1rem;
  line-height: 38px !important;
  border: 1px solid #ced4da !important;
  border-radius: 0.375rem !important;
  background-color: #fff !important;
  display: flex;
  align-items: center;
  padding: 0 12px !important;
}
.select2-container {
  width: 100% !important;
}
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div id="requestAlert" class="alert mt-4 d-none"></div>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="text-primary mb-0">Request Stock</h5>
      </div>

      <form id="requestStockForm">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Request Type</label>
            <select name="request_type" class="form-control" required>
              <option value="">-- Select Type --</option>
              <option value="daily_courier">Daily Courier</option>
              <option value="stationery_pack">Stationary Pack</option>
            </select>
          </div>
        </div>

        <table class="table table-bordered" id="requestItemTable">
          <thead class="table-light">
            <tr>
              <th style="width: 30%">Item</th>
              <th>Branch Stock</th>
              <th>Quantity</th>
              <th style="width: 40px;"></th>
            </tr>
          </thead>
          <tbody id="itemTableBody">
            <tr>
              <td>
                <select name="item_code[]" class="form-control item-select" required>
                  <?= $itemOptions ?>
                </select>
              </td>
              <td><input type="number" name="branch_stock[]" class="form-control" required placeholder="Current stock"></td>
              <td><input type="number" name="quantity[]" class="form-control" required min="1"></td>
              <td><button type="button" class="btn btn-danger btn-sm remove-row">&times;</button></td>
            </tr>
          </tbody>
        </table>

        <div class="mb-3">
          <button type="button" id="addItemRow" class="btn btn-secondary btn-sm">+ Add Another</button>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Request Date</label>
            <input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>" readonly required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Branch Code</label>
            <input type="text" name="branch_code" class="form-control" value="<?= $branch_code ?>" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label">Branch Name</label>
            <input type="text" name="branch_name" class="form-control" value="<?= $branch_name ?>" readonly>
          </div>
        </div>

        <input type="hidden" name="created_by" value="<?= $hris ?>">

        <div class="text-end mt-3">
          <button type="submit" class="btn btn-primary px-4">Submit Request</button>
        </div>
      </form>

      <!-- Submitted Requests Section -->
      <div class="mt-1">
        <h6 class="mb-3 text-secondary">Your Submitted Requests</h6>
        <div id="submittedRequestsTable">
          <p class="text-muted">Loading your submitted requests...</p>
        </div>

        <!-- All Other Requests (This Month, Read-only)
        <div class="mt-5">
          <h6 class="mb-3 text-secondary">All Past Requests (Read-Only)</h6>
          <div id="readonlyRequestsTable">
            <p class="text-muted">Loading past requests...</p>
          </div>
        </div> -->
      </div>
    </div>
  </div>
</div>

<!-- View Items Modal -->
<div class="modal fade" id="viewItemsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalItemTitle">Request Items</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Centered Alert -->
      <div class="d-flex justify-content-center mt-3">
        <div id="modalAlert" class="alert d-none text-center px-4 py-2" role="alert"
             style="max-width: 800px; border-radius: 0.5rem;">
          <!-- Dynamic alert message -->
        </div>
      </div>

      <div class="modal-body" id="modalItemBody">
        <!-- Dynamic content loaded here -->
      </div>
    </div>
  </div>
</div>



<script src="stationary-request.js"></script>

<script>
$(document).ready(function () {
  function initSelect2() {
    $('.item-select').select2({
      placeholder: '-- Select Item --',
      minimumResultsForSearch: 5,
      width: '100%'
    });
  }

  initSelect2();

  // Show Bootstrap alert message
  function showAlert(message, type = 'danger') {
    $('#requestAlert')
      .removeClass('d-none alert-success alert-danger')
      .addClass(`alert alert-${type}`)
      .text(message);
  }

  // Check if last row inputs are filled
  function isLastRowFilled() {
    const $lastRow = $('#itemTableBody tr:last');
    const itemVal = $lastRow.find('select[name="item_code[]"]').val();
    const stockVal = $lastRow.find('input[name="branch_stock[]"]').val();
    const qtyVal = $lastRow.find('input[name="quantity[]"]').val();
    return itemVal && stockVal && qtyVal;
  }

  // Add row only if last row is filled
  function addNewRowIfValid() {
    if (!isLastRowFilled()) {
      showAlert('Please fill all fields in the last row before adding a new one.', 'danger');
      return false;
    }

    const newRow = `
      <tr>
        <td>
          <select name="item_code[]" class="form-control item-select" required>
            <?= $itemOptions ?>
          </select>
        </td>
        <td><input type="number" name="branch_stock[]" class="form-control" required placeholder="Current stock"></td>
        <td><input type="number" name="quantity[]" class="form-control" required min="1"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-row">&times;</button></td>
      </tr>`;
    $('#itemTableBody').append(newRow);
    initSelect2();
    return true;
  }

  $('#addItemRow').click(function () {
    const added = addNewRowIfValid();
    if (added) {
      $('#requestAlert').addClass('d-none'); // Hide alert if successful
    }
  });

  $('#requestItemTable').on('click', '.remove-row', function () {
    $(this).closest('tr').remove();
  });

  // Handle Tab on last quantity input to add row
  $('#requestItemTable').on('keydown', 'input[name="quantity[]"]', function (e) {
    const $inputs = $('#requestItemTable').find('input[name="quantity[]"]');
    const isLastInput = $inputs.last()[0] === this;

    if (e.key === 'Tab' && !e.shiftKey && isLastInput) {
      if (!isLastRowFilled()) {
        showAlert('Please fill all fields in the last row before adding a new one.', 'danger');
        return;
      }

      e.preventDefault(); // prevent normal tab
      const added = addNewRowIfValid();
      if (added) {
        $('#requestAlert').addClass('d-none'); // Hide alert if successful
        setTimeout(() => {
          $('#itemTableBody tr:last select[name="item_code[]"]').focus().select2('open');
        }, 100);
      }
    }
  });

  $('#requestStockForm').submit(function(e) {
    e.preventDefault();

    const formData = $(this).serialize();
    console.log("Submitting form data:", formData); // DEBUG

    $.ajax({
      type: 'POST',
      url: 'submit-request.php',
      data: formData,
      dataType: 'json',
      success: function(response) {
        console.log("Response received:", response); // DEBUG
        if (response.status === 'success') {
          showAlert(response.message, 'success');
          $('#requestStockForm')[0].reset();
          $('#itemTableBody').html(`<!-- reload first row -->
            <tr>
              <td>
                <select name="item_code[]" class="form-control item-select" required>
                  <?= $itemOptions ?>
                </select>
              </td>
              <td><input type="number" name="branch_stock[]" class="form-control" required placeholder="Current stock"></td>
              <td><input type="number" name="quantity[]" class="form-control" required min="1"></td>
              <td><button type="button" class="btn btn-danger btn-sm remove-row">&times;</button></td>
            </tr>`);
          initSelect2();
        } else {
          showAlert(response.message, 'danger');
        }
      },
      error: function(xhr, status, error) {
        console.error("AJAX Error:", status, error); // DEBUG
        showAlert("Submission failed. Check console for error.", 'danger');
      }
    });
  });
});
</script>



