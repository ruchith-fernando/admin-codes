<?php 
session_start();
include 'connections/connection.php';
?>
<link href="styles-layout.css" rel="stylesheet">
<div id="globalLoader">
  <div class="loader-inner line-scale">
    <div></div><div></div><div></div><div></div><div></div>
  </div>
</div>

<div class="content font-size" id="contentArea">
  <div class="container-fluid">
    <div class="card">
      <h5>Assign / Reassign Mobile Connection</h5>

      <!-- Result area -->
      <div id="assignResult" class="result-block" style="display:none"></div>

      <form id="assignForm" action="process-allocation.php" method="post" novalidate>
        
        <!-- Mobile Number -->
        <div class="mb-3">
          <label class="form-label" for="mobile_number">Mobile Number</label>
          <select class="form-control select2" id="mobile_number" name="mobile_number" required>
            <option value="">-- Select Mobile Number --</option>
            <?php
              $q = $conn->query("
                SELECT DISTINCT mobile_no
                FROM tbl_admin_mobile_issues
                WHERE connection_status='Connected'
                ORDER BY mobile_no ASC
              ");
              while($row = $q->fetch_assoc()){
                echo "<option value='".htmlspecialchars($row['mobile_no'])."'>".htmlspecialchars($row['mobile_no'])."</option>";
              }
            ?>
          </select>
        </div>

        <!-- Current User (auto populated) -->
        <div class="mb-3">
          <label class="form-label">Current User</label>
          <input type="text" class="form-control" id="current_user" value="" disabled>
        </div>

        <!-- Active Employee -->
        <div class="mb-3">
          <label class="form-label" for="hris_no">Select Employee (Active Only)</label>
          <select class="form-control select2" id="hris_no" name="hris_no" required>
            <option value="">-- Select Employee --</option>
            <?php
              $q = $conn->query("
                SELECT hris, name_of_employee
                FROM tbl_admin_employee_details
                WHERE status='Active'
                ORDER BY name_of_employee ASC
              ");
              while($row = $q->fetch_assoc()){
                echo "<option value='".htmlspecialchars($row['hris'])."'>"
                    .htmlspecialchars($row['name_of_employee'])." (".$row['hris'].")</option>";
              }
            ?>
          </select>
        </div>

        <!-- Employee’s Existing Connections -->
        <div class="mb-3" id="employee_connections_block" style="display:none;">
          <label class="form-label">Employee’s Existing Connections</label>
          <div id="employee_connections" class="result-block"></div>
        </div>

        <!-- Effective From -->
        <div class="mb-3">
        <label class="form-label" for="effective_from">Effective From</label>
        <input type="text"
                class="form-control datepicker"
                id="effective_from"
                name="effective_from"
                autocomplete="off"
                required>
        </div>


        <button type="submit" class="btn btn-success">Save Allocation</button>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const $form   = $('#assignForm');
  const $loader = $('#globalLoader');
  const $result = $('#assignResult');

  // Enable select2 globally (select2 already loaded in main.php)
  $('.select2').select2({ width: '100%' });

  function showResult(html){ $result.html(html).show(); }
  function showError(msg){ showResult("<div class='alert alert-danger'><b>❌ " + msg + "</b></div>"); }

  // Fetch current user when mobile number changes
  $('#mobile_number').on('change', function(){
    const mobile = $(this).val();
    if(!mobile){
      $('#current_user').val('');
      return;
    }
    $.ajax({
      url: 'get-current-user.php',
      method: 'POST',
      data: { mobile_no: mobile },
      success: function(resp){
        $('#current_user').val(resp || '(No user assigned)');
      },
      error: function(){
        $('#current_user').val('(Error fetching user)');
      }
    });
  });

  // Fetch employee’s existing connections when employee is selected
  $('#hris_no').on('change', function(){
    const hris = $(this).val();
    if(!hris){
      $('#employee_connections_block').hide();
      $('#employee_connections').empty();
      return;
    }
    $.ajax({
      url: 'get-employee-connections.php',
      method: 'POST',
      data: { hris_no: hris },
      success: function(resp){
        $('#employee_connections').html(resp);
        $('#employee_connections_block').show();
      },
      error: function(){
        $('#employee_connections').html('<div class="text-danger">Error fetching connections</div>');
        $('#employee_connections_block').show();
      }
    });
  });

  // Form submit
  $form.on('submit', function(e){
    e.preventDefault();
    $result.hide().empty();

    const fd  = new FormData(this);
    const $btn = $(this).find('button[type="submit"]');

    $btn.prop('disabled', true);
    $loader.css('display','flex');

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: fd,
      contentType: false,
      processData: false,
      success: function(html){
        showResult(html || "<div class='alert alert-success'><b>✅ Allocation saved.</b></div>");
        $form.trigger('reset');
        $('.select2').val(null).trigger('change'); // reset select2 fields
        $('#current_user').val('');
        $('#employee_connections_block').hide();
      },
      error: function(x){
        const txt = x.responseText || '';
        if (/<div[^>]*class=['"][^"']*alert[^"']*['"]/.test(txt)) {
          showResult(txt);
        } else {
          showError(txt || 'Save failed.');
        }
      },
      complete: function(){
        $loader.hide();
        $btn.prop('disabled', false);
      }
    });
  });
})();


// Activate bootstrap-datepicker
$('#effective_from').datepicker({
  format: 'yyyy-mm-dd',
  endDate: new Date(),    // disallow future dates
  autoclose: true,
  todayHighlight: true
});


</script>
