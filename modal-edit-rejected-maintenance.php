<!-- modal-edit-rejected-maintenance.php -->

<!-- modal-edit-rejected-maintenance.php -->
<div class="modal fade" id="maintenanceEditModal" tabindex="-1" aria-labelledby="maintenanceEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-white">
        <h5 class="modal-title">Edit & Resubmit Maintenance Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="resubmitMaintenanceForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">

          <div class="row mb-3">
            <div class="col-md-4">
              <label>Vehicle Number</label>
              <input type="text" class="form-control" name="vehicle_number" id="edit_vehicle_number" required>
            </div>
            <div class="col-md-4">
              <label>Maintenance Type</label>
              <select class="form-control" name="maintenance_type" id="edit_maintenance_type" required>
                <option value="Battery">Battery</option>
                <option value="Tire">Tire</option>
                <option value="AC">AC</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label>Purchase Date</label>
              <input type="date" class="form-control" name="purchase_date" id="edit_purchase_date">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label>Shop Name</label>
              <input type="text" class="form-control" name="shop_name" id="edit_shop_name">
            </div>
            <div class="col-md-4">
              <label>Price</label>
              <input type="text" class="form-control" name="price" id="edit_price">
            </div>
            <div class="col-md-4">
              <label>Make</label>
              <input type="text" class="form-control" name="make" id="edit_make">
            </div>
          </div>

          <!-- Add more fields as needed -->
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Resubmit for Approval</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editRejectedMaintenance(id) {
  $.ajax({
    url: 'ajax-get-maintenance-record.php',
    type: 'POST',
    data: { id: id },
    success: function(response) {
      const data = JSON.parse(response);
      $('#edit_id').val(data.id);
      $('#edit_vehicle_number').val(data.vehicle_number);
      $('#edit_maintenance_type').val(data.maintenance_type);
      $('#edit_purchase_date').val(data.purchase_date);
      $('#edit_shop_name').val(data.shop_name);
      $('#edit_price').val(data.price);
      $('#edit_make').val(data.make);
      // You can continue populating other fields similarly
      $('#maintenanceEditModal').modal('show');
    }
  });
}

$('#resubmitMaintenanceForm').submit(function(e) {
  e.preventDefault();
  $.ajax({
    url: 'ajax-get-rejected-maintenance.php',
    type: 'POST',
    data: $(this).serialize(),
    success: function(resp) {
      const res = JSON.parse(resp);
      if (res.status === 'success') {
        $('#maintenanceEditModal').modal('hide');
        loadRejectedMaintenance();
        alert('Resubmission successful!');
      } else {
        alert('Error: ' + res.message);
      }
    }
  });
});
</script>
