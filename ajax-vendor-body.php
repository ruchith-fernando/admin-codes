<?php
// ajax-vendor-body.php
include 'connections/connection.php';

$search_vendor = trim($_POST['search_vendor'] ?? '');

// Build vendor list query
$where = '1';
if ($search_vendor !== '') {
    $safe = mysqli_real_escape_string($conn, $search_vendor);
    $where = "
        (vendor_code LIKE '%{$safe}%'
         OR vendor_name LIKE '%{$safe}%'
         OR vendor_type LIKE '%{$safe}%'
         OR phone LIKE '%{$safe}%'
         OR email LIKE '%{$safe}%'
         OR address LIKE '%{$safe}%')
    ";
}

$vendor_sql = "
    SELECT vendor_id, vendor_code, vendor_name, vendor_type,
           phone, email, address, is_active
    FROM tbl_admin_vendors
    WHERE $where
    ORDER BY vendor_type, vendor_name
";
$vendor_rs = mysqli_query($conn, $vendor_sql);
?>

<!-- Add / Update Vendor Form (Create Only) -->
<form id="vendorForm" method="POST">
    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Vendor Code</label>
            <input type="text" name="vendor_code" class="form-control" placeholder="Optional">
        </div>
        <div class="col-md-5">
            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
            <input type="text" name="vendor_name" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Vendor Type</label>
            <select name="vendor_type" class="form-select">
                <option value="WATER">WATER</option>
                <option value="ELECTRICITY">ELECTRICITY</option>
                <option value="PHOTOCOPY">PHOTOCOPY</option>
                <option value="OTHER">OTHER</option>
            </select>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control">
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                <label class="form-check-label">Active</label>
            </div>
        </div>
    </div>

    <div class="row mt-2 mb-4">
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">Save Vendor</button>
        </div>
    </div>
</form>

<hr class="my-4">

<!-- Search + List -->
<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label">Search Vendors</label>
        <input type="text" id="searchVendor" class="form-control"
               placeholder="Code, Name, Type, Phone, Email, Address"
               value="<?= htmlspecialchars($search_vendor) ?>">
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        if ($vendor_rs && mysqli_num_rows($vendor_rs) > 0):
            while ($row = mysqli_fetch_assoc($vendor_rs)):
        ?>
            <tr class="vendor-row"
                style="cursor:pointer;"
                data-vendor-id="<?= (int)$row['vendor_id'] ?>"
                data-vendor-code="<?= htmlspecialchars($row['vendor_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-vendor-name="<?= htmlspecialchars($row['vendor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-vendor-type="<?= htmlspecialchars($row['vendor_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-phone="<?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                data-is-active="<?= !empty($row['is_active']) ? 1 : 0 ?>"
            >
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($row['vendor_code'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['vendor_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['vendor_type'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['address'] ?? '') ?></td>
                <td class="text-center">
                    <?php if (!empty($row['is_active'])): ?>
                        <span class="badge bg-success">Yes</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">No</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php
            endwhile;
        else:
        ?>
            <tr>
                <td colspan="8" class="text-center">No vendors found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="vendorEditModal" tabindex="-1" aria-labelledby="vendorEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="vendorEditForm" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="vendorEditModalLabel">Edit Vendor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

          <input type="hidden" name="vendor_id" id="editVendorId">

          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Vendor Code</label>
              <input type="text" name="vendor_code" id="editVendorCode" class="form-control">
            </div>
            <div class="col-md-5">
              <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
              <input type="text" name="vendor_name" id="editVendorName" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Vendor Type</label>
              <select name="vendor_type" id="editVendorType" class="form-select">
                  <option value="WATER">WATER</option>
                  <option value="ELECTRICITY">ELECTRICITY</option>
                  <option value="OTHER">OTHER</option>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" id="editPhone" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="editEmail" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="editAddress" class="form-control">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1">
                <label class="form-check-label" for="editIsActive">Active</label>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Vendor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function ($) {
    'use strict';

    // 🔔 Helper to show Bootstrap alert in the parent page
    function vendorShowAlert(type, message) {
        var html =
            '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        $('#vendorAlertPlaceholder').html(html);
    }

    // CREATE: Submit vendor form via AJAX (submit-vendor.php – DO NOT TOUCH)
    $('#vendorForm').off('submit').on('submit', function (e) {
        e.preventDefault();

        $.post('submit-vendor.php', $(this).serialize(), function (res) {
            if (res.status === 'success') {
                vendorShowAlert('success', res.message || 'Vendor saved successfully.');
                $('#vendorForm')[0].reset();
                $('#waterVendorContent').load('ajax-vendor-body.php');
            } else {
                var level = 'danger';
                if (res.status === 'warning') level = 'warning';
                vendorShowAlert(level, res.message || 'Error saving vendor.');
            }
        }, 'json').fail(function (xhr) {
            vendorShowAlert('danger', 'AJAX error: ' + xhr.status + ' ' + xhr.statusText);
        });
    });

    // Search box: reload body with filter
    $('#searchVendor').off('keyup').on('keyup', function () {
        var term = $(this).val();
        $.post('ajax-vendor-body.php', { search_vendor: term }, function (res) {
            $('#waterVendorContent').html(res);
        });
    });

    // EDIT: row click => open modal, pre-fill form
    $('#waterVendorContent').off('click', '.vendor-row').on('click', '.vendor-row', function () {
        var $tr = $(this);

        $('#editVendorId').val($tr.data('vendor-id'));
        $('#editVendorCode').val($tr.data('vendor-code') || '');
        $('#editVendorName').val($tr.data('vendor-name') || '');
        $('#editVendorType').val($tr.data('vendor-type') || 'WATER');
        $('#editPhone').val($tr.data('phone') || '');
        $('#editEmail').val($tr.data('email') || '');
        $('#editAddress').val($tr.data('address') || '');

        if (parseInt($tr.data('is-active'), 10) === 1) {
            $('#editIsActive').prop('checked', true);
        } else {
            $('#editIsActive').prop('checked', false);
        }

        var modalEl = document.getElementById('vendorEditModal');
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    });

    // EDIT: submit update via AJAX to new update-vendor.php
    $('#vendorEditForm').off('submit').on('submit', function (e) {
        e.preventDefault();

        $.post('update-vendor.php', $(this).serialize(), function (res) {
            if (res.status === 'success') {
                vendorShowAlert('success', res.message || 'Vendor updated successfully.');

                // hide modal
                var modalEl = document.getElementById('vendorEditModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                // reload listing (search cleared; you can pass current search if needed)
                $('#waterVendorContent').load('ajax-vendor-body.php');
            } else {
                var level = (res.status === 'warning') ? 'warning' : 'danger';
                vendorShowAlert(level, res.message || 'Error updating vendor.');
            }
        }, 'json').fail(function (xhr) {
            vendorShowAlert('danger', 'AJAX error: ' + xhr.status + ' ' + xhr.statusText);
        });
    });

})(jQuery);
</script>
