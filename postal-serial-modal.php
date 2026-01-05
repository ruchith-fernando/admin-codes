<!-- postal-serial-modal.php -->
<div class="modal fade" id="serialModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="postalSerialForm">
        <div class="modal-header">
          <h5 class="modal-title">Enter Postal Serial Number & Date Posted</h5>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="postal_serial_number">Postal Serial Number</label>
            <input type="text" class="form-control" id="postal_serial_number" name="postal_serial_number" required>
          </div>
          <div class="mb-3">
            <label for="date_posted">Date Posted</label>
            <input type="text" class="form-control" id="date_posted" name="date_posted" autocomplete="off" required>
          </div>
          <input type="hidden" id="postage_id" name="postage_id">
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Update</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
