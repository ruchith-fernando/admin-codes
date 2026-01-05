<?php
// water-branch-master.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

if (empty($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

// --- flash messages for INSERT form ---
$success  = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : '';
$errorMsg = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/* =======================================================
   INSERT NEW BRANCH ONLY (NO UPDATE HERE)
   UPDATE is handled by water-branch-update.php via modal
   =======================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_code = strtoupper(trim(isset($_POST['branch_code']) ? $_POST['branch_code'] : ''));
    $branch_name = trim(isset($_POST['branch_name']) ? $_POST['branch_name'] : '');
    $region      = trim(isset($_POST['region']) ? $_POST['region'] : '');
    $address     = trim(isset($_POST['address']) ? $_POST['address'] : '');
    $city        = trim(isset($_POST['city']) ? $_POST['city'] : '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    $errors = array();
    if ($branch_code === '') $errors[] = 'Branch Code is required.';
    if ($branch_name === '') $errors[] = 'Branch Name is required.';

    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode('<br>', $errors);
    } else {
        $sql = "
            INSERT INTO tbl_admin_branches
                (branch_code, branch_name, region, address, city, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                'sssssi',
                $branch_code,
                $branch_name,
                $region,
                $address,
                $city,
                $is_active
            );
            if ($stmt->execute()) {
                $_SESSION['flash_success'] =
                    'Branch <strong>' . htmlspecialchars($branch_code) . '</strong> created successfully.';
                userlog('Branch INSERT (water-branch-master)', array(
                    'branch_code' => $branch_code,
                    'branch_name' => $branch_name,
                    'region'      => $region,
                    'address'     => $address,
                    'city'        => $city,
                    'is_active'   => $is_active,
                ));
            } else {
                // 1062 = duplicate key
                if ($stmt->errno == 1062) {
                    $_SESSION['flash_error'] = 'Branch code already exists. Please use Edit to modify.';
                } else {
                    $_SESSION['flash_error'] = 'Database error while creating branch. [' . $stmt->errno . ']';
                }
                userlog('Branch INSERT failed (water-branch-master)', array(
                    'error' => $stmt->error,
                    'errno' => $stmt->errno
                ));
            }
            $stmt->close();
        } else {
            $_SESSION['flash_error'] = 'Failed to prepare INSERT statement.';
            userlog('Branch INSERT prepare failed (water-branch-master)', array('error' => $conn->error));
        }
    }

    header('Location: water-branch-master.php');
    exit;
}

/* --------------------------------------------------------
   PRELOAD BRANCH CODES + NAMES for Select2
   (from tbl_admin_branch_water)
---------------------------------------------------------*/
$branchOptions = array();
try {
    $q = "
        SELECT DISTINCT branch_code, branch_name
        FROM tbl_admin_branch_water
        WHERE branch_code IS NOT NULL AND branch_code <> ''
        ORDER BY CAST(branch_code AS UNSIGNED), branch_code
    ";
    if ($res = $conn->query($q)) {
        while ($row = $res->fetch_assoc()) {
            $branchOptions[] = $row;
        }
        $res->free();
    }
} catch (Throwable $e) {
    userlog('Error loading branch options (water-branch-master)', array('error' => $e->getMessage()));
}
?>
<!doctype html>
<html lang="en">
    <meta charset="utf-8">
    <title>Water - Branch Master</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <!-- Select2 CSS if not already loaded -->
    <style>
        .select2-branch + .select2-container .select2-selection--single {
            height: 40px;
        }
        .select2-branch + .select2-container
        .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
        }
        .select2-branch + .select2-container
        .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
    </style>


<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-4 text-primary">Branch Master</h5>

      <div id="alertPlaceholder">
        <?php if ($errorMsg): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $errorMsg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
      </div>

      <!-- =========================
           BRANCH FORM (INSERT NEW)
      ========================== -->
      <form method="post" autocomplete="off" id="branchForm">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Branch Code <span class="text-danger">*</span></label>

            <select name="branch_code" id="branch_code"
                    class="form-select select2-branch" required>
              <option value="">-- Select Branch --</option>
              <?php foreach ($branchOptions as $opt): ?>
                <?php
                  $code = isset($opt['branch_code']) ? $opt['branch_code'] : '';
                  $name = isset($opt['branch_name']) ? $opt['branch_name'] : '';
                ?>
                <option value="<?= htmlspecialchars($code) ?>"
                        data-bname="<?= htmlspecialchars($name) ?>">
                    <?= htmlspecialchars($code . ' - ' . $name) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <small class="text-muted">Type or select a branch code.</small>
          </div>

          <div class="col-md-8">
            <label class="form-label">Branch Name <span class="text-danger">*</span></label>
            <input type="text" name="branch_name" id="branch_name"
                   class="form-control" value="" readonly>
            <small class="text-muted">
                Auto-filled from water records when branch code is selected.
            </small>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Region</label>
            <input type="text" name="region" id="region" class="form-control" value="">
          </div>
          <div class="col-md-4">
            <label class="form-label">City</label>
            <input type="text" name="city" id="city" class="form-control" value="">
          </div>
          <div class="col-md-4">
            <label class="form-label">Address</label>
            <input type="text" name="address" id="address" class="form-control" value="">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="is_active"
                     id="is_active" value="1" checked>
              <label class="form-check-label" for="is_active">Active</label>
            </div>
          </div>
        </div>

        <div class="mt-3 mb-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save Branch</button>
          <button type="button" id="btnClearForm" class="btn btn-outline-secondary btn-sm">Clear</button>
        </div>
      </form>

      <!-- =========================
           SEARCH + TABLE WRAPPER
      ========================== -->
      <h5 class="mb-3 text-secondary">Existing Branches</h5>

      <div class="mb-2 d-flex gap-2 flex-wrap">
        <input type="text" id="searchBranch" class="form-control"
               placeholder="Search by Code, Name, Region, City"
               style="max-width: 400px;">
      </div>

      <div id="branchTableWrapper" class="mt-2"><!-- AJAX table loads here --></div>

    </div>
  </div>
</div>

<!-- =========================
     EDIT MODAL
========================== -->
<div class="modal fade" id="branchEditModal" tabindex="-1" aria-labelledby="branchEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="branchEditModalLabel">Edit Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="branchEditForm">
          <input type="hidden" name="id" id="edit_id">

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">Branch Code <span class="text-danger">*</span></label>
              <input type="text" name="branch_code" id="edit_branch_code" class="form-control" readonly>
            </div>
            <div class="col-md-8">
              <label class="form-label">Branch Name <span class="text-danger">*</span></label>
              <input type="text" name="branch_name" id="edit_branch_name" class="form-control" readonly>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label">Region</label>
              <input type="text" name="region" id="edit_region" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">City</label>
              <input type="text" name="city" id="edit_city" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="edit_address" class="form-control">
            </div>
          </div>

          <div class="row mb-2">
            <div class="col-md-3">
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                <label class="form-check-label" for="edit_is_active">Active</label>
              </div>
            </div>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" id="btnUpdateBranch" class="btn btn-primary btn-sm">Update Branch</button>
      </div>
    </div>
  </div>
</div>
<script>
$(document).ready(function () {

    var currentBranchPage = 1;

    function showAlert(type, message) {
        var html =
          '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
          message +
          '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
          '</div>';
        $('#alertPlaceholder').html(html);
    }

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

    function fetchBranchTable(page) {
        if (!page) page = 1;
        currentBranchPage = page;
        var term = $('#searchBranch').val();
        $('#branchTableWrapper').html('<div class="text-muted">Loading branches...</div>');

        $.ajax({
            url: 'water-branch-table.php',
            method: 'GET',
            data: { page: page, search: term },
            dataType: 'html'
        })
        .done(function (html) {
            $('#branchTableWrapper').html(html);
        })
        .fail(function (xhr) {
            console.error('BRANCH TABLE ERROR:', xhr.status, xhr.statusText, xhr.responseText);
            $('#branchTableWrapper').html('<div class="alert alert-danger">Failed to load branch list.</div>');
        });
    }

    // Select2
    if ($.fn.select2) {
        $('.select2-branch').select2({
            width: '100%',
            tags: true,
            placeholder: 'Enter or select branch code'
        });
    }

    // initial table load
    fetchBranchTable(1);

    // live search on table
    $('#searchBranch').on('keyup', debounce(function () {
        fetchBranchTable(1);
    }, 300));

    // pagination (delegated)
    $(document).on('click', '.branch-page-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var pg = $(this).data('pg');
        if (pg) fetchBranchTable(pg);
    });

    // INSERT form clear
    $('#btnClearForm').on('click', function () {
        $('#branchForm')[0].reset();
        $('#branch_name').val('');
        if ($.fn.select2) {
            $('#branch_code').val(null).trigger('change');
        } else {
            $('#branch_code').val('');
        }
    });

    // INSERT form branch_code → branch_name
    $('#branch_code').on('change', function () {
        var code = $(this).val();
        if (!code) {
            $('#branch_name').val('');
            return;
        }
        var $opt = $('#branch_code').find('option[value="' + code + '"]');
        var knownName = $opt.data('bname');
        if (knownName) {
            $('#branch_name').val(knownName);
            return;
        }
        // Fallback to AJAX
        $.getJSON('ajax-water-get-branch-name.php', { code: code })
         .done(function (resp) {
            if (resp && resp.status === 'ok' && resp.branch_name) {
                $('#branch_name').val(resp.branch_name);
                if ($opt.length) $opt.attr('data-bname', resp.branch_name);
            } else {
                $('#branch_name').val('');
            }
         })
         .fail(function () { $('#branch_name').val(''); });
    });

    // EDIT button → open modal, preload fields
    $(document).on('click', '.btn-edit-branch', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $tr = $(this).closest('tr');
        if (!$tr.length) return;

        $('#edit_id').val($tr.data('id'));
        $('#edit_branch_code').val($tr.data('code') || '');
        $('#edit_branch_name').val($tr.data('name') || '');
        $('#edit_region').val($tr.data('region') || '');
        $('#edit_city').val($tr.data('city') || '');
        $('#edit_address').val($tr.data('address') || '');
        $('#edit_is_active').prop('checked', ($tr.data('active') == 1));

        var modal = new bootstrap.Modal(document.getElementById('branchEditModal'));
        modal.show();
    });

    // UPDATE via AJAX
    $('#btnUpdateBranch').on('click', function () {
        var id      = $('#edit_id').val();
        var code    = $('#edit_branch_code').val();
        var name    = $('#edit_branch_name').val();
        var region  = $('#edit_region').val();
        var city    = $('#edit_city').val();
        var address = $('#edit_address').val();
        var active  = $('#edit_is_active').is(':checked') ? 1 : 0;

        if (!id || !code || !name) {
            showAlert('danger', 'Missing required fields for update.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: 'water-branch-update.php',
            method: 'POST',
            dataType: 'json',
            data: {
                id: id,
                branch_code: code,
                branch_name: name,
                region: region,
                city: city,
                address: address,
                is_active: active
            }
        })
        .done(function (resp) {
            if (resp.status === 'ok') {
                showAlert('success', resp.message || 'Branch updated.');
                var modalEl = document.getElementById('branchEditModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                fetchBranchTable(currentBranchPage);
            } else {
                showAlert('danger', resp.message || 'Update failed.');
            }
        })
        .fail(function (xhr) {
            console.error('UPDATE ERROR:', xhr.status, xhr.statusText, xhr.responseText);
            showAlert('danger', 'Update failed: ' + xhr.statusText);
        })
        .always(function () {
            $btn.prop('disabled', false).text('Update Branch');
        });
    });

});
</script>
