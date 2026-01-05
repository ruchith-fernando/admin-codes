<?php
session_start();
require_once "connections/connection.php"; // $conn available

// =======================
// 1. Search Ajax handler
// =======================
if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
    $serial = trim($_POST['serial_number'] ?? '');
    if ($serial === '') {
        echo "<div class='alert alert-danger'>⚠ Please enter a serial number.</div>";
        exit;
    }

    // --- Branch reference ---
    $stmt1 = $conn->prepare("SELECT * FROM tbl_admin_branch_photocopy WHERE serial_number = ? LIMIT 1");
    $stmt1->bind_param("s", $serial);
    $stmt1->execute();
    $branchRes = $stmt1->get_result();
    $branch = $branchRes->fetch_assoc();

    echo "<div class='branch-card'>
            <h6>📌 Branch Reference</h6>
            <div class='table-responsive'>
              <table class='result-table branch-table'>
                <thead>
                  <tr>
                    <th>Serial</th>
                    <th>Branch Name</th>
                    <th>Branch Code</th>
                    <th>Rate</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>";
    if ($branch) {
        echo "<tr data-serial='{$branch['serial_number']}'>
                <td class='serial'>{$branch['serial_number']}</td>
                <td class='branch_name'>{$branch['branch_name']}</td>
                <td class='branch_code'>{$branch['branch_code']}</td>
                <td class='rate'>{$branch['rate']}</td>
                <td><button class='btn btn-primary btn-sm branch-edit'>Edit</button></td>
              </tr>";
        echo "</tbody></table></div></div>";
    } else {
        echo "<tr><td colspan='5'>❌ No branch reference found for serial <b>$serial</b></td></tr>";
        echo "</tbody></table></div></div>";

        // Show vertical form for adding new branch
        echo "<div class='new-branch-form card'>
                <h6>➕ Add Branch Reference</h6>
                <form id='addBranchForm'>
                  <input type='hidden' name='serial' value='{$serial}'>

                  <div class='mb-3'>
                    <label>Branch Name</label>
                    <input type='text' name='branch_name' class='form-control' required>
                  </div>

                  <div class='mb-3'>
                    <label>Branch Code</label>
                    <input type='text' name='branch_code' class='form-control' required>
                  </div>

                  <div class='mb-3'>
                    <label>Rate</label>
                    <input type='number' step='0.01' name='rate' class='form-control' required>
                  </div>

                  <button type='submit' class='btn btn-success'>Save Branch</button>
                </form>
              </div>";
    }

    // --- Actuals table ---
    $stmt2 = $conn->prepare("SELECT * FROM tbl_admin_actual_photocopy WHERE serial_number = ? ORDER BY record_date DESC");
    $stmt2->bind_param("s", $serial);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    if ($res2->num_rows > 0) {
        echo "<div class='table-wrap table-responsive'>
                <table class='result-table actuals-table'>
                  <thead>
                    <tr>
                      <th>Serial</th>
                      <th>Branch Name</th>
                      <th>Branch Code</th>
                      <th>Copies</th>
                      <th>Rate</th>
                      <th>Amount</th>
                      <th>SSCL</th>
                      <th>VAT</th>
                      <th>Total</th>
                      <th>Type</th>
                      <th>Note</th>
                    </tr>
                  </thead>
                  <tbody>";
        while ($row = $res2->fetch_assoc()) {
            echo "<tr data-id='{$row['id']}'>
                    <td class='serial'>{$row['serial_number']}</td>
                    <td class='branch_name'>{$row['branch_name']}</td>
                    <td class='branch_code'>{$row['branch_code']}</td>
                    <td class='copies num'>{$row['number_of_copy']}</td>
                    <td class='rate num'>{$row['rate']}</td>
                    <td class='amount num'>{$row['amount']}</td>
                    <td class='sscl num'>{$row['sscl']}</td>
                    <td class='vat num'>{$row['vat']}</td>
                    <td class='total num'>{$row['total']}</td>
                    <td>{$row['replacement_type']}</td>
                    <td>{$row['replacement_note']}</td>
                  </tr>";
        }
        echo "</tbody></table></div>";
    }
    $stmt1->close();
    $stmt2->close();
    exit;
}

// =======================
// 2. Save Branch Updates
// =======================
if (isset($_POST['action']) && $_POST['action'] == 'save_branch') {
    $serial = $_POST['serial'];
    $branch_name = $_POST['branch_name'];
    $branch_code = $_POST['branch_code'];
    $rate = $_POST['rate'];

    // Update reference table
    $stmt = $conn->prepare("UPDATE tbl_admin_branch_photocopy 
        SET branch_name=?, branch_code=?, rate=? 
        WHERE serial_number=?");
    $stmt->bind_param("ssss", $branch_name, $branch_code, $rate, $serial);
    $ok1 = $stmt->execute();

    // Also update actuals table
    $stmt2 = $conn->prepare("UPDATE tbl_admin_actual_photocopy 
        SET branch_name=?, branch_code=? 
        WHERE serial_number=?");
    $stmt2->bind_param("sss", $branch_name, $branch_code, $serial);
    $ok2 = $stmt2->execute();

    echo ($ok1 && $ok2) ? "success" : "error";
    exit;
}

// =======================
// 3. Insert New Branch
// =======================
if (isset($_POST['action']) && $_POST['action'] == 'insert_branch') {
    $serial = $_POST['serial'];
    $branch_name = $_POST['branch_name'];
    $branch_code = $_POST['branch_code'];
    $rate = $_POST['rate'];

    // Insert into branch reference
    $stmt = $conn->prepare("INSERT INTO tbl_admin_branch_photocopy 
        (serial_number, branch_name, branch_code, rate) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $serial, $branch_name, $branch_code, $rate);
    $ok1 = $stmt->execute();

    // Also update actuals table branch info if serial already exists
    $stmt2 = $conn->prepare("UPDATE tbl_admin_actual_photocopy 
        SET branch_name=?, branch_code=? 
        WHERE serial_number=?");
    $stmt2->bind_param("sss", $branch_name, $branch_code, $serial);
    $ok2 = $stmt2->execute();

    echo ($ok1 && $ok2) ? "success" : "error";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Search Photocopy Records</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
  .content{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px;margin-bottom:20px}
  .card h5{margin:0 0 16px;color:#0d6efd}
  .mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
  .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
  .btn{padding:.35rem .75rem;border-radius:6px;cursor:pointer}
  .btn-success{background:#198754;color:#fff;border:0}
  .btn-primary{background:#0d6efd;color:#fff;border:0}
  .btn-secondary{background:#6c757d;color:#fff;border:0}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .branch-card{margin:20px 0}
  .table-wrap{margin-top:25px}
  .result-table{width:100%;border-collapse:collapse;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:15px}
  .result-table th,.result-table td{padding:10px 12px;border:1px solid #e5e7eb;text-align:left;font-size:.9rem}
  .result-table th{background:#f1f5f9;font-weight:600;color:#333}
  .result-table tr:nth-child(even){background:#fafafa}
  .num{text-align:right}
  .editing input{padding:6px 8px;margin:3px;border:1px solid #ced4da;border-radius:6px;width:100px}
  .table-responsive {width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>

<div class="content">
  <div class="container-fluid">
    <div class="card">
      <h5>Search Photocopy Records</h5>
      <form id="searchForm" method="post" style="margin-bottom:20px;">
        <div class="mb-3">
          <label class="form-label" for="serial_number">Enter Serial Number</label>
          <input type="text" class="form-control" id="serial_number" name="serial_number" required>
        </div>
        <button type="submit" class="btn btn-success">Search</button>
      </form>
      <div id="searchResult"></div>
    </div>
  </div>
</div>

<script>
// Handle search
$(function(){
  $('#searchForm').on('submit', function(e){
    e.preventDefault();
    var serial = $('#serial_number').val().trim();
    if(!serial){ return; }
    $('#searchResult').html("<div class='alert'>⏳ Searching...</div>");
    $.post('update-branch-information.php', {ajax:1, serial_number:serial}, function(resp){
      $('#searchResult').html(resp);
    });
  });

  // Branch edit/save/cancel (Table 1 only)
  $(document).on('click', '.branch-edit', function(){
    var row = $(this).closest('tr');
    row.addClass('editing');
    row.find('.branch_name').html("<input value='"+row.find('.branch_name').text()+"'>");
    row.find('.branch_code').html("<input value='"+row.find('.branch_code').text()+"'>");
    row.find('.rate').html("<input value='"+row.find('.rate').text()+"'>");
    $(this).replaceWith("<button class='btn btn-success btn-sm branch-save'>Save</button> <button class='btn btn-secondary btn-sm branch-cancel'>Cancel</button>");
  });

  $(document).on('click', '.branch-save', function(){
    var row = $(this).closest('tr');
    $.post('update-branch-information.php', {
      action:'save_branch',
      serial: row.data('serial'),
      branch_name: row.find('.branch_name input').val(),
      branch_code: row.find('.branch_code input').val(),
      rate: row.find('.rate input').val()
    }, function(resp){ 
      if(resp=="success"){ $('#searchForm').submit(); } 
      else{ alert("Error updating branch"); } 
    });
  });

  $(document).on('click', '.branch-cancel', function(){ $('#searchForm').submit(); });

  // Add branch form submit
  $(document).on('submit', '#addBranchForm', function(e){
    e.preventDefault();
    $.post('update-branch-information.php', 
      $(this).serialize() + '&action=insert_branch', 
      function(resp){
        if(resp=="success"){ $('#searchForm').submit(); } 
        else{ alert("Error inserting branch"); }
      }
    );
  });
});
</script>
</body>
</html>
