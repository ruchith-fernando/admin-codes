<?php
// water-types-master.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');

if (empty($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

// flash messages for INSERT
$errors  = [];
$success = '';

if (isset($_SESSION['water_type_flash_error'])) {
    $errors[] = $_SESSION['water_type_flash_error'];
    unset($_SESSION['water_type_flash_error']);
}
if (isset($_SESSION['water_type_flash_success'])) {
    $success = $_SESSION['water_type_flash_success'];
    unset($_SESSION['water_type_flash_success']);
}

// defaults for insert form
$data = [
    'water_type_code' => '',
    'water_type_name' => '',
    'is_active'       => 1,
];

/* =======================================================
   INSERT NEW WATER TYPE ONLY
   EDIT is handled by water-types-update.php via modal
   =======================================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['water_type_code'] = strtoupper(trim($_POST['water_type_code'] ?? ''));
    $data['water_type_name'] = trim($_POST['water_type_name'] ?? '');
    $data['is_active']       = isset($_POST['is_active']) ? 1 : 0;

    if ($data['water_type_code'] === '') $errors[] = 'Type code is required.';
    if ($data['water_type_name'] === '') $errors[] = 'Type name is required.';

    if (!$errors) {
        $sql = "
            INSERT INTO tbl_admin_water_types (water_type_code, water_type_name, is_active)
            VALUES (?, ?, ?)
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                'ssi',
                $data['water_type_code'],
                $data['water_type_name'],
                $data['is_active']
            );
            if ($stmt->execute()) {
                $_SESSION['water_type_flash_success'] =
                    'Water type <strong>' . htmlspecialchars($data['water_type_code']) . '</strong> created.';
                userlog('Water type INSERT', $data);
            } else {
                if ($stmt->errno == 1062) {
                    $_SESSION['water_type_flash_error'] =
                        'Type code already exists. Use Edit to change it.';
                } else {
                    $_SESSION['water_type_flash_error'] =
                        'Database error while creating water type. ['.$stmt->errno.']';
                }
                userlog('Water type INSERT failed', ['errno' => $stmt->errno, 'error' => $stmt->error]);
            }
            $stmt->close();
        } else {
            $_SESSION['water_type_flash_error'] = 'Failed to prepare INSERT statement.';
            userlog('Water type INSERT prepare failed', ['error' => $conn->error]);
        }

        // redirect to avoid repost
        header('Location: water-types-master.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Water Types</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
</head>
<body>

<div class="content font-size">
  <div class="container-fluid">

    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Water Types — Add / Edit</h5>

      <div id="alertPlaceholder">
        <?php if ($errors): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <?php foreach ($errors as $e): ?>
              <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible fade show">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
      </div>

      <!-- =========================
           INSERT FORM
      ========================== -->
      <form method="post" autocomplete="off" id="waterTypeForm">
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Type Code <span class="text-danger">*</span></label>
            <input type="text" name="water_type_code" class="form-control"
                   value="<?= htmlspecialchars($data['water_type_code']) ?>" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Type Name <span class="text-danger">*</span></label>
            <input type="text" name="water_type_name" class="form-control"
                   value="<?= htmlspecialchars($data['water_type_name']) ?>" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="is_active" value="1"
                     <?= $data['is_active'] ? 'checked' : '' ?>>
              <label class="form-check-label">Active</label>
            </div>
          </div>
        </div>

        <div class="mt-3 mb-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save Type</button>
          <button type="button" id="btnClearForm" class="btn btn-outline-secondary btn-sm">Clear</button>
        </div>
      </form>

      <!-- =========================
           TABLE + SEARCH
      ========================== -->
      <h5 class="mb-3 text-secondary">Existing Water Types</h5>

      <div class="mb-2 d-flex gap-2 flex-wrap">
        <input type="text" id="searchWaterType" class="form-control"
               placeholder="Search by Code, Name"
               style="max-width: 400px;">
      </div>

      <div id="waterTypeTableWrapper" class="mt-2"><!-- AJAX table loads here --></div>

    </div>
  </div>
</div>

<!-- ========= EDIT MODAL ========= -->
<div class="modal fade" id="waterTypeEditModal" tabindex="-1"
     aria-labelledby="waterTypeEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="waterTypeEditModalLabel">Edit Water Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="waterTypeEditForm">
          <input type="hidden" name="water_type_id" id="edit_water_type_id">

          <div class="mb-3">
            <label class="form-label">Type Code</label>
            <input type="text" name="water_type_code" id="edit_water_type_code"
                   class="form-control" readonly>
          </div>

          <div class="mb-3">
            <label class="form-label">Type Name <span class="text-danger">*</span></label>
            <input type="text" name="water_type_name" id="edit_water_type_name"
                   class="form-control" required>
          </div>

          <div class="mb-2">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
              <label class="form-check-label" for="edit_is_active">Active</label>
            </div>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" id="btnUpdateWaterType" class="btn btn-primary btn-sm">Update Type</button>
      </div>
    </div>
  </div>
</div>

<!-- JS (if not already included globally) -->
<!--
<script src="assets/jquery.min.js"></script>
<script src="assets/bootstrap.bundle.min.js"></script>
-->

<script>
$(document).ready(function () {

    var currentPage = 1;

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

    function loadWaterTypeTable(page) {
        if (!page) page = 1;
        currentPage = page;
        var term = $('#searchWaterType').val();
        $('#waterTypeTableWrapper').html('<div class="text-muted">Loading...</div>');

        $.ajax({
            url: 'water-types-table.php',
            method: 'GET',
            dataType: 'html',
            data: { page: page, search: term }
        })
        .done(function (html) {
            $('#waterTypeTableWrapper').html(html);
        })
        .fail(function (xhr) {
            console.error('TABLE ERROR:', xhr.status, xhr.statusText, xhr.responseText);
            $('#waterTypeTableWrapper').html('<div class="alert alert-danger">Failed to load table.</div>');
        });
    }

    // initial table
    loadWaterTypeTable(1);

    // search
    $('#searchWaterType').on('keyup', debounce(function () {
        loadWaterTypeTable(1);
    }, 300));

    // pagination
    $(document).on('click', '.water-type-page-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var pg = $(this).data('pg');
        if (pg) loadWaterTypeTable(pg);
    });

    // clear insert form
    $('#btnClearForm').on('click', function () {
        $('#waterTypeForm')[0].reset();
    });

    // open edit modal
    $(document).on('click', '.btn-edit-water-type', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $tr = $(this).closest('tr');
        if (!$tr.length) return;

        $('#edit_water_type_id').val($tr.data('id') || '');
        $('#edit_water_type_code').val($tr.data('code') || '');
        $('#edit_water_type_name').val($tr.data('name') || '');
        $('#edit_is_active').prop('checked', ($tr.data('active') == 1));

        var modal = new bootstrap.Modal(document.getElementById('waterTypeEditModal'));
        modal.show();
    });

    // update via AJAX
    $('#btnUpdateWaterType').on('click', function () {
        var id   = $('#edit_water_type_id').val();
        var name = $('#edit_water_type_name').val();
        var code = $('#edit_water_type_code').val();
        var active = $('#edit_is_active').is(':checked') ? 1 : 0;

        if (!id || !code || !name) {
            showAlert('danger', 'Missing required fields.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: 'water-types-update.php',
            method: 'POST',
            dataType: 'json',
            data: {
                water_type_id: id,
                water_type_code: code,
                water_type_name: name,
                is_active: active
            }
        })
        .done(function (resp) {
            if (resp.status === 'ok') {
                showAlert('success', resp.message || 'Water type updated.');
                var modalEl = document.getElementById('waterTypeEditModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                loadWaterTypeTable(currentPage);
            } else {
                showAlert('danger', resp.message || 'Update failed.');
            }
        })
        .fail(function (xhr) {
            console.error('UPDATE ERROR:', xhr.status, xhr.statusText, xhr.responseText);
            showAlert('danger', 'Update failed: ' + xhr.statusText);
        })
        .always(function () {
            $btn.prop('disabled', false).text('Update Type');
        });
    });

});
</script>

</body>
</html>
