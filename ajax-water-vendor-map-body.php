<?php
// ajax-water-vendor-map-body.php
include 'connections/connection.php';

// Load active water types
$waterTypes = [];
$res = mysqli_query(
    $conn,
    "SELECT water_type_id, water_type_code, water_type_name
     FROM tbl_admin_water_types
     WHERE is_active = 1
     ORDER BY water_type_name"
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $waterTypes[] = $row;
    }
}

// Load active WATER vendors from master
$vendors = [];
$res2 = mysqli_query(
    $conn,
    "SELECT vendor_id, vendor_name
     FROM tbl_admin_vendors
     WHERE vendor_type = 'WATER' AND is_active = 1
     ORDER BY vendor_name"
);
if ($res2) {
    while ($row = mysqli_fetch_assoc($res2)) {
        $vendors[] = $row;
    }
}

// Existing mappings (for display)
$mappings = [];
$mapSql = "
    SELECT wv.*,
           wt.water_type_name,
           wt.water_type_code,
           v.vendor_name AS master_vendor_name
    FROM tbl_admin_water_vendors wv
    LEFT JOIN tbl_admin_water_types wt
        ON wv.water_type_id = wt.water_type_id
    LEFT JOIN tbl_admin_vendors v
        ON wv.vendor_master_id = v.vendor_id
    ORDER BY wt.water_type_name, wv.vendor_name
";
$mapRes = mysqli_query($conn, $mapSql);
if ($mapRes) {
    while ($row = mysqli_fetch_assoc($mapRes)) {
        $mappings[] = $row;
    }
}
?>

<!-- MAP FORM -->
<form id="waterVendorMapForm" method="POST" autocomplete="off">
    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label">Water Type <span class="text-danger">*</span></label>
            <select name="water_type_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($waterTypes as $wt): ?>
                    <option value="<?= (int)$wt['water_type_id'] ?>">
                        <?= htmlspecialchars($wt['water_type_name']) ?>
                        (<?= htmlspecialchars($wt['water_type_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Vendor (Master) <span class="text-danger">*</span></label>
            <select name="vendor_master_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($vendors as $v): ?>
                    <option value="<?= (int)$v['vendor_id'] ?>">
                        <?= htmlspecialchars($v['vendor_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Display Name (optional)</label>
            <input type="text" name="vendor_name" class="form-control"
                   placeholder="If blank, master vendor name is used">
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                <label class="form-check-label">Active</label>
            </div>
        </div>
    </div>

    <div class="mt-2 mb-4">
        <button type="submit" class="btn btn-primary">Save Mapping</button>
    </div>
</form>

<hr class="my-4">

<!-- EXISTING MAPPINGS TABLE -->
<h6 class="mb-3 text-secondary">Existing Water Vendor Mappings</h6>

<div class="table-responsive">
    <table class="table table-bordered table-striped table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Water Type</th>
                <th>Display Vendor Name</th>
                <th>Master Vendor</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($mappings): ?>
            <?php $i = 1; foreach ($mappings as $m): ?>
                <tr>
                    <td><?= $i++; ?></td>
                    <td>
                        <?= htmlspecialchars($m['water_type_name'] ?? '') ?>
                        <?php if (!empty($m['water_type_code'])): ?>
                            (<?= htmlspecialchars($m['water_type_code']) ?>)
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($m['vendor_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($m['master_vendor_name'] ?? '') ?></td>
                    <td class="text-center">
                        <?php if (!empty($m['is_active'])): ?>
                            <span class="badge bg-success">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">No mappings found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function ($) {
    'use strict';

    // 🔔 Inline alert in parent card
    function mapShowAlert(type, message) {
        var html =
            '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        $('#waterVendorMapAlertPlaceholder').html(html);
    }

    // Submit mapping form via AJAX
    $('#waterVendorMapForm').off('submit').on('submit', function (e) {
        e.preventDefault();

        $.post('submit-water-vendor-map.php', $(this).serialize(), function (res) {
            if (res.status === 'success') {
                mapShowAlert('success', res.message || 'Mapping saved successfully.');
                // reset form
                $('#waterVendorMapForm')[0].reset();
                // reload body to refresh mappings list
                $('#waterVendorMapContent').load('ajax-water-vendor-map-body.php');
            } else {
                var level = 'danger';
                if (res.status === 'warning') level = 'warning';
                mapShowAlert(level, res.message || 'Error saving mapping.');
            }
        }, 'json').fail(function (xhr) {
            mapShowAlert('danger', 'AJAX error: ' + xhr.status + ' ' + xhr.statusText);
        });
    });

})(jQuery);
</script>
