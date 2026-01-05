<!-- edit-user.php -->
<?php include("connections/connection.php"); ?>

<!-- Select2 Styling -->
<style>
  .select2-container--default .select2-selection--single,
  .select2-container--default .select2-selection--multiple {
      height: auto !important;
      padding: 6px 12px;
      border: 1px solid #ced4da;
      border-radius: 0.375rem;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered,
  .select2-container--default .select2-selection--multiple .select2-selection__rendered {
      line-height: 24px;
  }
  .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
      top: 1px;
      right: 6px;
  }
  .select2-container { z-index: 9999 !important; }
  table td, table th {
    white-space: normal !important;
    word-wrap: break-word;
    max-width: 200px;
  }
</style>

<?php
// Fetch dropdown values
$hierarchies = [];
$designations = [];
$emp_result = mysqli_query($conn, "SELECT DISTINCT company_hierarchy, designation FROM tbl_admin_employee_details WHERE status = 'Active'");
while ($row = mysqli_fetch_assoc($emp_result)) {
    if (!empty($row['company_hierarchy'])) $hierarchies[] = $row['company_hierarchy'];
    if (!empty($row['designation'])) $designations[] = $row['designation'];
}
$hierarchies = array_unique($hierarchies);
$designations = array_unique($designations);

// Fetch user levels
$user_levels = [];
$level_result = mysqli_query($conn, "SELECT level_key, level_label FROM tbl_admin_user_levels");
while ($lvl = mysqli_fetch_assoc($level_result)) {
    $user_levels[] = $lvl;
}
?>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">User Management</h5>

      <div id="successAlert" class="alert alert-success d-none">User updated successfully!</div>

      <div class="table-responsive">
        <table class="table table-bordered text-wrap">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>HRIS</th>
              <th>User Levels</th>
              <th>Division / Department</th>
              <th>Designation</th>
              <th>Category</th>
              <th>Branch Code</th>
              <th>Branch Name</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $result = mysqli_query($conn, "SELECT * FROM tbl_admin_users ORDER BY id DESC");
            while ($row = mysqli_fetch_assoc($result)) {
              echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['name']}</td>
                <td>{$row['hris']}</td>
                <td>{$row['user_level']}</td>
                <td>{$row['company_hierarchy']}</td>
                <td>{$row['designation']}</td>
                <td>{$row['category']}</td>
                <td>{$row['branch_code']}</td>
                <td>{$row['branch_name']}</td>
                <td>
                  <button class='btn btn-sm btn-primary edit-btn'
                    data-id='{$row['id']}'
                    data-company_hierarchy='{$row['company_hierarchy']}'
                    data-designation='{$row['designation']}'
                    data-category='{$row['category']}'
                    data-user_level='{$row['user_level']}'
                    data-branch_code='{$row['branch_code']}'
                    data-branch_name='{$row['branch_name']}'>
                    Edit
                  </button>
                </td>
              </tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <form id="editForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Company Hierarchy</label>
              <select class="form-select" name="company_hierarchy" id="edit_company_hierarchy">
                <option value="">-- Select --</option>
                <?php foreach($hierarchies as $h) echo "<option value=\"$h\">$h</option>"; ?>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Designation</label>
              <select class="form-select" name="designation" id="edit_designation">
                <option value="">-- Select --</option>
                <?php foreach($designations as $d) echo "<option value=\"$d\">$d</option>"; ?>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Category</label>
              <select name="category" id="edit_category" class="form-select" required>
                <option value="">Select</option>
                <option value="Marketing">Marketing</option>
                <option value="Branch Operation">Branch Operation</option>
                <option value="Operations">Operations</option>
                <option value="All">All (Admin, Acceptor, Issuer)</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">User Level(s)</label>
              <select name="user_level[]" id="edit_user_level" class="form-select" multiple required>
                <?php foreach($user_levels as $ul): ?>
                  <option value="<?= $ul['level_key'] ?>"><?= $ul['level_label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Branch Code</label>
              <input type="text" class="form-control" name="branch_code" id="edit_branch_code">
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Branch Name</label>
              <input type="text" class="form-control" name="branch_name" id="edit_branch_name" readonly>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
  // Select2 setup
  $('#edit_company_hierarchy, #edit_designation, #edit_category').select2({ dropdownParent: $('#editModal'), width: '100%' });
  $('#edit_user_level').select2({ dropdownParent: $('#editModal'), width: '100%', placeholder: "Select user level(s)", allowClear: true });

  $('.edit-btn').click(function () {
    $('#edit_id').val($(this).data('id'));
    $('#edit_company_hierarchy').val($(this).data('company_hierarchy')).trigger('change');
    $('#edit_designation').val($(this).data('designation')).trigger('change');
    $('#edit_category').val($(this).data('category')).trigger('change');
    $('#edit_branch_code').val($(this).data('branch_code'));
    $('#edit_branch_name').val($(this).data('branch_name'));

    const userLevels = $(this).data('user_level')?.split(',') || [];
    $('#edit_user_level').val(userLevels).trigger('change');

    new bootstrap.Modal(document.getElementById('editModal')).show();
  });

  $('#edit_branch_code').on('input', function () {
    const branchCode = $(this).val();
    $.ajax({
      url: 'ajax-get-branch-name.php',
      type: 'POST',
      dataType: 'json',
      data: { branch_code: branchCode },
      success: function (response) {
        $('#edit_branch_name').val(response.success ? response.branch : '');
      },
      error: function () {
        $('#edit_branch_name').val('');
      }
    });
  });

  $('#editForm').submit(function (e) {
    e.preventDefault();
    $.ajax({
      url: 'update-user.php',
      type: 'POST',
      data: $(this).serialize(),
      success: function (response) {
        $('#editModal').modal('hide');
        $('#successAlert').removeClass('d-none');
        setTimeout(() => location.reload(), 1000);
      },
      error: function () {
        alert("Error while updating.");
      }
    });
  });
});
</script>
