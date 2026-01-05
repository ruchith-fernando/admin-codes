<?php
// security-branch-firm-map.php
require_once 'connections/connection.php';

// Get active firms for dropdown
$firms_q = mysqli_query($conn, "
    SELECT id, firm_name 
    FROM tbl_admin_security_firms 
    WHERE active = 'yes'
    ORDER BY firm_name
");

// Get distinct branches from old budget table that are NOT already mapped (active=yes)
$branches_q = mysqli_query($conn, "
    SELECT DISTINCT b.branch_code, b.branch
    FROM tbl_admin_budget_security b
    LEFT JOIN tbl_admin_branch_firm_map m 
        ON b.branch_code = m.branch_code 
       AND m.active = 'yes'
    WHERE b.branch_code IS NOT NULL 
      AND b.branch_code <> ''
      AND m.id IS NULL
    ORDER BY CAST(b.branch_code AS UNSIGNED), b.branch_code
");
?>
<style>
  .select2-container--default .select2-selection--single {
      height: calc(2.5rem);
    padding: .375rem .75rem;
    border: 1px solid #ced4da;
    border-radius: .375rem;
  }
/* 
  .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 40px;         
  } */

  .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: calc(2.5rem);          /* align arrow with new height */
  }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Security - Branch & Firm Mapping</h5>

      <!-- 🔹 Firm Dropdown -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Security Firm</label>
        <select id="firm_select" class="form-select">
          <option value="">-- Select Firm --</option>
          <?php while($f = mysqli_fetch_assoc($firms_q)): ?>
            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['firm_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- 🔹 Mapping Form -->
      <div id="mapping_form" class="mb-3 d-none">
        <h6 class="fw-bold">Add / Update Branch Mapping</h6>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Branch Code</label>
            <select id="branch_code_select" class="form-select">
              <option value="">-- Select Branch Code --</option>
              <?php while($b = mysqli_fetch_assoc($branches_q)): ?>
                <option 
                  value="<?= htmlspecialchars($b['branch_code']) ?>"
                  data-branch-name="<?= htmlspecialchars($b['branch']) ?>"
                >
                  <?= htmlspecialchars($b['branch_code']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Branch Name</label>
            <input type="text" id="branch_name" class="form-control" readonly>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-success w-100" id="save_mapping_btn">Save</button>
          </div>
        </div>
        <small class="text-muted">
          If this branch code already exists, it will be reassigned to this firm and branch name will be updated.
        </small>
      </div>

      <!-- 🔹 Status Messages -->
      <div id="mapping_status" class="mt-2"></div>

      <!-- 🔹 Existing Mappings Table -->
      <div id="mapping_table_container" class="table-responsive mt-4">
        <table class="table table-bordered table-sm" id="mapping_table">
          <thead class="table-light">
            <tr>
              <th style="width: 20%;">Security Firm</th>
              <th style="width: 12%;">Branch Code</th>
              <th>Branch Name</th>
              <th style="width: 15%;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- filled via AJAX -->
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>


<script src="security-branch-firm-map.js"></script>
