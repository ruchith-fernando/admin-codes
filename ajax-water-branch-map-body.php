<?php
// ajax-water-branch-map-body.php (multi-connection support)
include 'connections/connection.php';

$branch_code = trim($_POST['branch_code'] ?? '');

// ---------- Branch list ----------
$branches = [];
$brSql = "
    SELECT branch_code, branch_name
    FROM tbl_admin_branches
    WHERE is_active = 1
    ORDER BY CAST(branch_code AS UNSIGNED), branch_code
";
$brRes = mysqli_query($conn, $brSql);
if ($brRes) {
    while ($row = mysqli_fetch_assoc($brRes)) {
        $branches[] = $row;
    }
}

// Resolve branch name
$branch_name = '';
if ($branch_code !== '') {
    foreach ($branches as $b) {
        if (($b['branch_code'] ?? '') === $branch_code) {
            $branch_name = $b['branch_name'] ?? '';
            break;
        }
    }
}

// ---------- Water types ----------
$waterTypes = [];
$wtRes = mysqli_query(
    $conn,
    "SELECT water_type_id, water_type_code, water_type_name
     FROM tbl_admin_water_types
     WHERE is_active = 1"
);
if ($wtRes) {
    while ($row = mysqli_fetch_assoc($wtRes)) {
        $waterTypes[] = $row;
    }
}

// Order: Tap Line (NWSDB) -> Machine -> Bottle -> rest
$orderMap = [
    'NWSDB'   => 1,
    'MACHINE' => 2,
    'BOTTLE'  => 3,
];
usort($waterTypes, function ($a, $b) use ($orderMap) {
    $ca = strtoupper($a['water_type_code'] ?? '');
    $cb = strtoupper($b['water_type_code'] ?? '');
    $wa = $orderMap[$ca] ?? 100;
    $wb = $orderMap[$cb] ?? 100;
    if ($wa === $wb) {
        return strcmp((string)$a['water_type_name'], (string)$b['water_type_name']);
    }
    return $wa <=> $wb;
});

// ---------- Vendors per type ----------
$vendorsByType = [];
$venRes = mysqli_query(
    $conn,
    "SELECT vendor_id, water_type_id, vendor_name
     FROM tbl_admin_water_vendors
     WHERE is_active = 1
     ORDER BY vendor_name"
);
if ($venRes) {
    while ($row = mysqli_fetch_assoc($venRes)) {
        $wtid = (int)$row['water_type_id'];
        $vendorsByType[$wtid] ??= [];
        $vendorsByType[$wtid][] = $row;
    }
}

// ---------- Existing mappings (NOW multi-connection) ----------
$existing = []; // $existing[water_type_id][connection_no] = row
if ($branch_code !== '') {
    $bcEsc = mysqli_real_escape_string($conn, $branch_code);
    $mapRes = mysqli_query(
        $conn,
        "SELECT *
         FROM tbl_admin_branch_water
         WHERE branch_code = '{$bcEsc}'
         ORDER BY water_type_id, connection_no"
    );
    if ($mapRes) {
        while ($row = mysqli_fetch_assoc($mapRes)) {
            $wtid = (int)($row['water_type_id'] ?? 0);
            $cno  = (int)($row['connection_no'] ?? 1);
            if ($wtid > 0) {
                $existing[$wtid] ??= [];
                $existing[$wtid][$cno] = $row;
            }
        }
    }
}
?>

<form id="branchWaterForm" method="post" autocomplete="off">

  <div class="row mb-3">
    <div class="col-md-3">
      <label class="form-label">Branch Code <span class="text-danger">*</span></label>
      <select name="branch_code" id="branch_code"
              class="form-select select2-branch-code" required>
        <option value="">-- Select Branch --</option>
        <?php foreach ($branches as $b): ?>
          <?php
            $bc    = (string)($b['branch_code'] ?? '');
            $bname = (string)($b['branch_name'] ?? '');
          ?>
          <option value="<?= htmlspecialchars($bc) ?>"
                  <?= $branch_code === $bc ? 'selected' : '' ?>>
            <?= htmlspecialchars($bc) ?> - <?= htmlspecialchars($bname) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">This drives which mapping rows are saved.</small>
    </div>

    <div class="col-md-5">
      <label class="form-label">Branch Name</label>
      <input type="text" class="form-control" id="branch_name_display"
             value="<?= htmlspecialchars((string)$branch_name) ?>" readonly>
      <small class="text-muted">Auto-filled from branch master.</small>
    </div>
  </div>

  <?php if (!$waterTypes): ?>
    <div class="alert alert-warning">No active water types defined.</div>
  <?php else: ?>

  <div class="table-responsive mb-3">
    <table class="table table-sm align-middle water-map-table">
      <thead class="table-light">
        <tr>
          <th style="width:6%;">Has?</th>
          <th style="width:7%;">Conn #</th>
          <th style="width:18%;">Water Type</th>
          <th style="width:26%;">Supplier (Water Vendor)</th>
          <th style="width:23%;">Account Number (Tap Line)</th>
          <th style="width:14%;">No. of Machines (Machine)</th>
          <th style="width:6%;">Action</th>
        </tr>
      </thead>
      <tbody>

      <?php foreach ($waterTypes as $wt): ?>
        <?php
          $wtid = (int)$wt['water_type_id'];
          $code = strtoupper((string)($wt['water_type_code'] ?? ''));
          $name = (string)($wt['water_type_name'] ?? '');

          $connections = $existing[$wtid] ?? [];
          if (!$connections) {
              // Always render at least connection #1 as blank row
              $connections = [1 => null];
          } else {
              ksort($connections);
          }

          $firstConnNo = (int)array_key_first($connections);
        ?>

        <?php foreach ($connections as $connNo => $row): ?>
          <?php
            $connNo = (int)$connNo;
            $has = ($row !== null);

            $vendor_id      = $row['vendor_id']      ?? '';
            $account_number = $row['account_number'] ?? '';
            $no_of_machines = $row['no_of_machines'] ?? '';

            // Default: all disabled until Has is checked
            $vendor_disabled = 'disabled';
            $acct_disabled   = 'disabled';
            $mach_disabled   = 'disabled';

            if ($has) {
                if ($code === 'BOTTLE') {
                    $vendor_disabled = '';
                } elseif ($code === 'NWSDB') {
                    $vendor_disabled = '';
                    $acct_disabled   = '';
                } elseif ($code === 'MACHINE') {
                    $vendor_disabled = '';
                    $mach_disabled   = '';
                } else {
                    $vendor_disabled = $acct_disabled = $mach_disabled = '';
                }
            }
          ?>

          <tr class="branch-water-row"
              data-water-type-id="<?= $wtid ?>"
              data-water-type-code="<?= htmlspecialchars($code) ?>"
              data-connection-no="<?= $connNo ?>">
            <td class="text-center">
              <input type="checkbox"
                     class="form-check-input wt-has"
                     name="has[<?= $wtid ?>][<?= $connNo ?>]"
                     value="1"
                     <?= $has ? 'checked' : '' ?>>
            </td>

            <td class="text-center">
              <span class="conn-no"><?= (int)$connNo ?></span>
            </td>

            <td>
              <strong><?= htmlspecialchars($name) ?></strong><br>
              <span class="text-muted small">Code: <?= htmlspecialchars($code) ?></span>
            </td>

            <td>
              <select name="vendor_id[<?= $wtid ?>][<?= $connNo ?>]"
                      class="form-select form-select-sm wt-vendor"
                      <?= $vendor_disabled ? 'disabled="disabled"' : '' ?>>
                <option value="">-- Select Supplier --</option>
                <?php if (!empty($vendorsByType[$wtid])): ?>
                  <?php foreach ($vendorsByType[$wtid] as $v): ?>
                    <?php
                      $vid   = (int)($v['vendor_id'] ?? 0);
                      $vname = (string)($v['vendor_name'] ?? '');
                    ?>
                    <option value="<?= $vid ?>"
                            <?= ((string)$vendor_id !== '' && (int)$vendor_id === $vid) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($vname) ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </td>

            <td>
              <input type="text"
                     name="account_number[<?= $wtid ?>][<?= $connNo ?>]"
                     class="form-control form-control-sm wt-account"
                     value="<?= htmlspecialchars((string)$account_number) ?>"
                     <?= $acct_disabled ? 'disabled="disabled"' : '' ?>>
            </td>

            <td>
              <input type="number"
                     name="no_of_machines[<?= $wtid ?>][<?= $connNo ?>]"
                     class="form-control form-control-sm wt-machines"
                     value="<?= htmlspecialchars((string)$no_of_machines) ?>"
                     min="0"
                     <?= $mach_disabled ? 'disabled="disabled"' : '' ?>>
            </td>

            <td class="text-center">
              <?php if ($connNo === $firstConnNo): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-primary wt-add-conn"
                        data-wtid="<?= $wtid ?>"
                        title="Add another connection">
                  +
                </button>
              <?php endif; ?>

              <?php if ($connNo > 1): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-danger wt-remove-conn"
                        title="Remove this connection">
                  ×
                </button>
              <?php endif; ?>
            </td>
          </tr>

        <?php endforeach; ?>
      <?php endforeach; ?>

      </tbody>
    </table>
  </div>

  <div class="mb-4">
    <button type="submit" class="btn btn-primary">Save Branch Mapping</button>
  </div>

  <?php endif; ?>

</form>

<hr class="mt-4 mb-3">

<h5 class="mb-3 text-secondary">Existing Branch Water Mappings</h5>

<div class="mb-2 d-flex gap-2 flex-wrap">
  <input type="text" id="branchMapSearch" class="form-control"
         placeholder="Search by branch code, name, water type, vendor, account, conn #"
         style="max-width: 450px;">
</div>

<div id="branchMapTableWrapper" class="mt-2"><!-- AJAX branch map table --></div>

<style>
/* Make Select2 look like a normal Bootstrap 5 control for branch select */
.select2-container--bootstrap-5 .select2-selection--single {
    height: 2.5rem;
    padding: .375rem .75rem;
    border: 1px solid #ced4da;
    border-radius: .375rem;
}
.select2-container--bootstrap-5 .select2-selection__arrow {
    height: 2.5rem;
}

/* Vendor select2 inside the mapping table: slightly smaller but same style */
.water-map-table .select2-container--bootstrap-5 .select2-selection--single {
    height: 2.1rem;
    padding: .25rem .5rem;
    font-size: 0.85rem;
}
.water-map-table .select2-container--bootstrap-5 .select2-selection__arrow {
    height: 2.1rem;
}

/* Fallback */
.select2-container .select2-selection--single { min-height: 2.1rem; }
.select2-container .select2-selection__rendered { line-height: 1.4; }

/* Compact table */
.water-map-table th,
.water-map-table td {
    padding: .45rem .55rem;
    font-size: 0.9rem;
}

/* pagination */
.branch-map-pagination .page-link {
    padding: .35rem .75rem;
    font-size: 0.9rem;
}
</style>

<script>
(function ($) {
  'use strict';

  var currentMapPage = 1;

  function branchWaterShowAlert(type, message) {
      var html =
        '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
        '</div>';
      $('#branchWaterAlertPlaceholder').html(html);
  }

  // ---------- Enable/disable row fields based on Has? + type ----------
  function updateRowState($row) {
      var typeCode = ($row.attr('data-water-type-code') || '').toUpperCase();
      var $chk      = $row.find('.wt-has');
      var $vendor   = $row.find('.wt-vendor');
      var $account  = $row.find('.wt-account');
      var $machines = $row.find('.wt-machines');

      if (!$chk.is(':checked')) {
          $vendor.prop('disabled', true);
          $account.prop('disabled', true);
          $machines.prop('disabled', true);
          return;
      }

      if (typeCode === 'BOTTLE') {
          $vendor.prop('disabled', false);
          $account.prop('disabled', true);
          $machines.prop('disabled', true);
      } else if (typeCode === 'NWSDB') {
          $vendor.prop('disabled', false);
          $account.prop('disabled', false);
          $machines.prop('disabled', true);
      } else if (typeCode === 'MACHINE') {
          $vendor.prop('disabled', false);
          $account.prop('disabled', true);
          $machines.prop('disabled', false);
      } else {
          $vendor.prop('disabled', false);
          $account.prop('disabled', false);
          $machines.prop('disabled', false);
      }
  }

  function refreshAllRows() {
      $('.branch-water-row').each(function () {
          updateRowState($(this));
      });
  }

  /* ---------- Select2 initialisation ---------- */
  function initBranchSelect2() {
      if (!$.fn.select2) return;
      var $bc = $('#branch_code');
      if (!$bc.length) return;

      if ($bc.hasClass('select2-hidden-accessible')) {
          $bc.select2('destroy');
      }
      $bc.select2({
          theme: 'bootstrap-5',
          width: '100%',
          placeholder: '-- Select Branch --',
          allowClear: false
      });
  }

  function initVendorSelect2($ctx) {
      if (!$.fn.select2) return;

      var $targets = $ctx ? $ctx.find('.wt-vendor') : $('.wt-vendor');

      $targets.each(function () {
          var $v = $(this);
          if ($v.hasClass('select2-hidden-accessible')) {
              $v.select2('destroy');
          }
          $v.select2({
              theme: 'bootstrap-5',
              width: '100%',
              placeholder: '-- Select Supplier --',
              allowClear: false
          });
      });
  }

  // ---------- Add connection row ----------
  function addConnectionRow(wtid) {
      var $rows = $('.branch-water-row[data-water-type-id="' + wtid + '"]');
      if (!$rows.length) return;

      // find max connection_no
      var maxConn = 0;
      $rows.each(function () {
          var c = parseInt($(this).attr('data-connection-no') || '0', 10);
          if (c > maxConn) maxConn = c;
      });
      var nextConn = maxConn + 1;

      // clone last row of same type
      var $last = $rows.last();
      var $new  = $last.clone(false, false);

      // update attrs
      $new.attr('data-connection-no', nextConn);
      $new.find('.conn-no').text(nextConn);

      // update names + clear values
      $new.find('.wt-has')
          .prop('checked', true)
          .attr('name', 'has[' + wtid + '][' + nextConn + ']');

      $new.find('.wt-vendor')
          .attr('name', 'vendor_id[' + wtid + '][' + nextConn + ']')
          .val('');

      $new.find('.wt-account')
          .attr('name', 'account_number[' + wtid + '][' + nextConn + ']')
          .val('');

      $new.find('.wt-machines')
          .attr('name', 'no_of_machines[' + wtid + '][' + nextConn + ']')
          .val('');

      // actions: remove allowed, add button only on first conn row
      $new.find('.wt-add-conn').remove();
      if (!$new.find('.wt-remove-conn').length) {
          $new.find('td:last').append(
              '<button type="button" class="btn btn-sm btn-outline-danger wt-remove-conn" title="Remove this connection">×</button>'
          );
      }

      // insert after last
      $last.after($new);

      // re-init select2 only for new row vendor
      initVendorSelect2($new);

      // apply correct enable/disable
      updateRowState($new);

      // ensure vendor select2 shows cleared
      $new.find('.wt-vendor').trigger('change');
  }

  // ---------- Table (search + pagination) ----------
  function debounce(fn, delay) {
      var timer = null;
      return function () {
          var ctx = this, args = arguments;
          clearTimeout(timer);
          timer = setTimeout(function () {
              fn.apply(ctx, args);
          }, delay);
      };
  }

  function loadBranchMapTable(page) {
      if (!page) page = 1;
      currentMapPage = page;

      var term = $('#branchMapSearch').val();
      $('#branchMapTableWrapper').html('<div class="text-muted">Loading mappings...</div>');

      $.ajax({
          url: 'water-branch-map-table.php',
          method: 'GET',
          dataType: 'html',
          data: { page: page, search: term }
      })
      .done(function (html) {
          $('#branchMapTableWrapper').html(html);
      })
      .fail(function (xhr) {
          console.error('BRANCH MAP TABLE ERROR:', xhr.status, xhr.statusText, xhr.responseText);
          $('#branchMapTableWrapper').html('<div class="alert alert-danger">Failed to load mappings.</div>');
      });
  }

  // checkbox change
  $(document).off('change.branchWater', '.wt-has')
      .on('change.branchWater', '.wt-has', function () {
          var $row = $(this).closest('.branch-water-row');
          updateRowState($row);
      });

  // add connection
  $(document).off('click.branchWaterAdd', '.wt-add-conn')
      .on('click.branchWaterAdd', '.wt-add-conn', function () {
          var wtid = $(this).data('wtid');
          if (wtid) addConnectionRow(wtid);
      });

  // remove connection (only conn > 1 exists in UI)
  $(document).off('click.branchWaterRemove', '.wt-remove-conn')
      .on('click.branchWaterRemove', '.wt-remove-conn', function () {
          var $row = $(this).closest('.branch-water-row');
          $row.remove();
      });

  // branch change -> reload fragment
  $(document).off('change.branchWaterBranch', '#branch_code')
      .on('change.branchWaterBranch', '#branch_code', function () {
          var bc = $(this).val();

          $.post('ajax-water-branch-map-body.php', { branch_code: bc }, function (res) {
              $('#branchWaterContent').html(res);

              // re-init on new HTML
              refreshAllRows();
              initBranchSelect2();
              initVendorSelect2();
          });
      });

  // form submit
  $(document).off('submit.branchWater', '#branchWaterForm')
      .on('submit.branchWater', '#branchWaterForm', function (e) {
          e.preventDefault();
          var bc = $('#branch_code').val();
          if (!bc) {
              branchWaterShowAlert('danger', 'Please select a branch first.');
              return;
          }

          $.post('submit-water-branch-map.php', $(this).serialize(), function (res) {
              if (res.status === 'success') {
                  branchWaterShowAlert('success', res.message || 'Mapping saved.');

                  // reload form for same branch
                  $.post('ajax-water-branch-map-body.php', { branch_code: bc }, function (html) {
                      $('#branchWaterContent').html(html);
                      refreshAllRows();
                      initBranchSelect2();
                      initVendorSelect2();
                  });

                  // reload mappings table
                  loadBranchMapTable(currentMapPage);
              } else {
                  var lvl = (res.status === 'warning') ? 'warning' : 'danger';
                  branchWaterShowAlert(lvl, res.message || 'Error saving mapping.');
              }
          }, 'json').fail(function (xhr) {
              branchWaterShowAlert('danger', 'AJAX error: ' + xhr.status + ' ' + xhr.statusText);
          });
      });

  // live search
  $(document).off('keyup.branchMapSearch', '#branchMapSearch')
      .on('keyup.branchMapSearch', '#branchMapSearch', debounce(function () {
          loadBranchMapTable(1);
      }, 300));

  // pagination
  $(document).off('click.branchMapPage', '.branch-map-page-btn')
      .on('click.branchMapPage', '.branch-map-page-btn', function (e) {
          e.preventDefault();
          e.stopPropagation();
          var pg = $(this).data('pg');
          if (pg) loadBranchMapTable(pg);
      });

  $(function () {
      refreshAllRows();
      initBranchSelect2();
      initVendorSelect2();
      loadBranchMapTable(1);
  });

})(jQuery);
</script>
