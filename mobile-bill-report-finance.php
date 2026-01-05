<?php
// mobile-bill-report-finance.php (AJAX-based)
include 'connections/connection.php';

$update_date_sql = "SELECT DISTINCT Update_date FROM tbl_admin_mobile_bill_data ORDER BY STR_TO_DATE(Update_date, '%M-%Y') ASC";
$update_date_result = $conn->query($update_date_sql);
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Mobile Bill Report - Finance</h5>

      <div class="mb-3 d-flex gap-2 align-items-center">
        <select name="update_date" id="update_date" class="form-control" style="max-width: 600px;">
            <option value="">Select Update Date</option>
            <?php while ($row = $update_date_result->fetch_assoc()) { 
            // Normalize to "Month-Year" format (e.g., "April-2025")
            $formattedDate = date('F-Y', strtotime('01-' . $row['Update_date']));
            ?>
            <option value="<?php echo htmlspecialchars($formattedDate); ?>">
                <?php echo htmlspecialchars($formattedDate); ?>
            </option>
            <?php } ?>
        </select>

        <button type="button" onclick="exportData('excel')" class="btn btn-primary" style="height: 38px;">Download Excel</button>
</div>


      <div id="tableContainer"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="selectMonthModal" tabindex="-1" aria-labelledby="selectMonthModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select a Month</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Please select a month from the dropdown before downloading the report.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
function exportData(type) {
  const update_date = document.getElementById('update_date').value;
  if (!update_date) {
    $('#selectMonthModal').modal('show');
    return;
  }
  window.location.href = 'export-mobile-bill-finance-excel.php?update_date=' + encodeURIComponent(update_date);
}

function loadTable(page = 1) {
  const update_date = document.getElementById('update_date').value;
  $.ajax({
    url: 'ajax-mobile-bill-table-finance.php',
    method: 'GET',
    data: { page: page, update_date: update_date },
    success: function (response) {
      $('#tableContainer').html(response);
    },
    error: function () {
      $('#tableContainer').html('<div class="alert alert-danger">Failed to load data.</div>');
    }
  });
}

$(document).ready(function () {
  $('#update_date').on('change', function () {
    loadTable();
  });

  // Handle pagination click
  $(document).on('click', '.pagination a.page-link', function (e) {
    e.preventDefault();
    const page = $(this).data('page');
    if (page) loadTable(page);
  });

  // Initial load
  loadTable();
});

</script>
