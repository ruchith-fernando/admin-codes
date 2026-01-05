<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'connections/connection.php';
?>

<!-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> -->

<style>
.select2-container--default .select2-selection--multiple {
  min-height: 38px !important;
  padding: 4px !important;
  border: 1px solid #ced4da !important;
  border-radius: 0.375rem !important;
}
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div id="alertBox"></div>

      <h5 class="mb-4 text-primary">Register User</h5>

      <form id="registerForm" autocomplete="off">

        <!-- HRIS Search -->
        <div class="row mb-3">
          <div class="col-md-4">
            <label>Enter HRIS</label>
            <input type="text" class="form-control" id="search_hris" placeholder="Enter HRIS to fetch" required autocomplete="off">
          </div>
        </div>

        <!-- Employee Details -->
        <div class="row mb-3">
          <div class="col-md-3">
            <label>Name</label>
            <input type="text" id="name" name="name" class="form-control" readonly>
          </div>
          <div class="col-md-3">
            <label>Designation</label>
            <input type="text" id="designation" name="designation" class="form-control" readonly>
          </div>
          <div class="col-md-1">
            <label>Title</label>
            <input type="text" id="title" name="title" class="form-control" readonly>
          </div>
          <div class="col-md-5">
            <label>Company Hierarchy</label>
            <input type="text" id="company_hierarchy" name="company_hierarchy" class="form-control" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label>Location</label>
            <input type="text" id="location" name="location" class="form-control" readonly>
          </div>
          <div class="col-md-4">
            <label>Employee Category</label>
            <input type="text" id="emp_category" name="emp_category" class="form-control" readonly>
          </div>
          <div class="col-md-4">
            <label>Branch Code</label>
            <input type="text" id="branch_code" name="branch_code" class="form-control" readonly>
          </div>
        </div>

        <!-- Login Credentials -->
        <div class="row mb-3">
          <div class="col-md-4">
            <label>Set Username (HRIS)</label>
            <input type="text" id="username" name="username" class="form-control" required autocomplete="off">
          </div>
          <div class="col-md-4">
            <label>Password</label>
            <input type="password" id="password" name="password" class="form-control" required autocomplete="off">
          </div>
        </div>

        <hr>

        <!-- Utility + Branch -->
        <h5 class="mb-3 text-primary">Assign Utility & Branch Access</h5>

        <div class="row mb-3">
          <div class="col-md-4">
            <label>Select Utility</label>
            <select id="utility_selector" class="form-select">
              <option value="">-- select utility --</option>
              <option value="water">Water</option>
              <option value="electricity">Electricity</option>
              <option value="newspaper">Newspaper</option>
              <option value="courier">Courier</option>
              <option value="photocopy">Photocopy</option>
              <option value="printing">Printing</option>
              <option value="tea-branches">Tea Branches</option>
            </select>
          </div>

          <div class="col-md-6">
            <label>Search Branch</label>
            <select id="branch_search" class="form-select" multiple></select>
            <small class="text-muted">Start typing branch name or code</small>
          </div>

          <div class="col-md-2">
            <label>&nbsp;</label>
            <button type="button" id="add_utility_btn" class="btn btn-success w-100">Add</button>
          </div>
        </div>

        <hr>

        <h5 class="text-primary">Assigned Utilities</h5>

        <table class="table table-bordered" id="utility_table">
          <thead class="table-light">
            <tr>
              <th>Utility</th>
              <th>Branch Codes</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>

        <input type="hidden" name="utility_json" id="utility_json">

        <button type="submit" class="btn btn-primary mt-4">Register</button>

      </form>
    </div>
  </div>
</div>

<script>
$(function () {

  // HRIS Auto load
  $("#search_hris").blur(function () {
    let hris = $(this).val().trim();
    if (!hris) return;

    $.post("ajax-fetch-employee-details.php", { hris }, function (data) {

      if (data.status === "success") {
        $("#name").val(data.name);
        $("#designation").val(data.designation);
        $("#title").val(data.title);
        $("#company_hierarchy").val(data.company_hierarchy);
        $("#location").val(data.location);
        $("#emp_category").val(data.category);
        $("#branch_code").val(data.branch_code);
        $("#username").val(hris);
        showAlert("Employee details loaded", "success");
      } 
      else {
        showAlert("HRIS not found", "danger");
      }

    }, "json");
  });

  // Load Select2 branches
  $("#utility_selector").change(function () {
    let utility = $(this).val();
    $("#branch_search").val(null).empty();

    if (!utility) return;

    $("#branch_search").select2({
      placeholder: "Search branch...",
      minimumInputLength: 1,
      ajax: {
        url: "ajax-search-utility-branches.php",
        type: "POST",
        dataType: "json",
        delay: 250,
        data: params => ({
          term: params.term,
          utility: utility
        }),
        processResults: data => ({ results: data })
      }
    });
  });

  let utilityData = [];

  // Add Utility
  $("#add_utility_btn").click(function () {
    let utility = $("#utility_selector").val();
    let utilityText = $("#utility_selector option[value='" + utility + "']").text();

    let branches = $("#branch_search").val();
    let branchText = $("#branch_search").select2("data").map(x => x.text);

    if (!utility || !branches || branches.length === 0) {
      alert("Select utility and at least one branch");
      return;
    }

    utilityData.push({
      utility,
      utility_name: utilityText,
      branches,
      branch_names: branchText
    });

    $("#utility_table tbody").append(`
      <tr>
        <td>${utilityText}</td>
        <td>${branchText.join("<br>")}</td>
        <td><button class="btn btn-danger btn-sm remove-row">Remove</button></td>
      </tr>
    `);

    $("#utility_selector").val("");
    $("#branch_search").val(null).trigger("change");
  });

  // Remove row
  $(document).on("click", ".remove-row", function () {
    let index = $(this).closest("tr").index();
    utilityData.splice(index, 1);
    $(this).closest("tr").remove();
  });

  // Submit
  $("#registerForm").submit(function (e) {
    e.preventDefault();

    // prevent submission if duplicate exists
    if (usernameExists) {
        showAlert("❌ Cannot continue — username already exists.", "danger");
        return;
    }

    $("#utility_json").val(JSON.stringify(utilityData));

    $.post("ajax-register-user.php", $(this).serialize(), function (res) {

      if (res.status === "success") {
        showAlert(res.message, "success");
        $("#registerForm")[0].reset();
        $("#utility_table tbody").empty();
        utilityData = [];
      } else {
        showAlert(res.message, "danger");
      }

    }, "json");
});


  let usernameExists = false; // global flag

  $("#username").blur(function () {
      let username = $(this).val().trim();

      if (!username) return;

      $.post("ajax-check-username.php", { username }, function (res) {

          if (res.status === "exists") {
              usernameExists = true;
              showAlert(res.message, "danger");
          } else {
              usernameExists = false;
              showAlert("✔ Username is available", "success");
          }

      }, "json");
  });

  function showAlert(msg, type) {
    $("#alertBox").html(`<div class="alert alert-${type}">${msg}</div>`);
  }
});
</script>
