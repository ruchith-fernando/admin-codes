<?php
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

$log_file = __DIR__.'/logs/security-insert-log.txt';
function log_data($msg){
    global $log_file;
    file_put_contents($log_file,"[".date("Y-m-d H:i:s")."] $msg\n", FILE_APPEND);
}

// ✅ Month Dropdown
$months_result = $conn->query("SELECT DISTINCT month_applicable AS month FROM tbl_admin_actual_security ORDER BY STR_TO_DATE(month_applicable, '%M %Y')");
$months = [];
while ($row = $months_result->fetch_assoc()) $months[] = $row['month'];

// ✅ Handle Saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_entry']) && $_POST['manual_entry'] == '1') {
    $month = $conn->real_escape_string($_POST['month_applicable']);
    log_data("🟢 Starting Save for Month: $month");

    foreach ($_POST['branch_code'] as $i => $code) {
        $code = trim($conn->real_escape_string($code));
        $branch = trim($conn->real_escape_string($_POST['branch'][$i]));
        $shifts = intval($_POST['actual_shifts'][$i]);
        $amount = floatval(str_replace(',', '', $_POST['actual_amount'][$i]));
        $provision = isset($_POST['provision'][$i]) ? $conn->real_escape_string($_POST['provision'][$i]) : 'no';

        log_data("Row $i | Code: $code | Branch: $branch | Shifts: $shifts | Amount: $amount | Provision: $provision");

        if ($code == '' || $shifts < 1) {
            log_data("❌ Row $i skipped (empty code or shifts < 1)");
            continue;
        }

        $sql = "INSERT INTO tbl_admin_actual_security (branch_code, branch, month_applicable, actual_shifts, total_amount, provision)
                VALUES ('$code','$branch','$month',$shifts,$amount,'$provision')";

        if ($conn->query($sql)) {
            $last_id = $conn->insert_id;
            $sr = generate_sr_number($conn, 'tbl_admin_actual_security', $last_id);
            log_data("✅ Row $i inserted | SR: $sr | SQL: $sql");
        } else {
            log_data("❌ Error Row $i | ".$conn->error." | SQL: $sql");
        }
    }
    log_data("✅✅✅ Finished saving for $month");
    header("Location: security-old.php?month=".urlencode($month)); exit;
}

$selectedMonth = $_GET['month'] ?? null;
?>
<div class="content font-size">
<div class="container-fluid">
<div class="card shadow bg-white rounded p-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="mb-4 text-primary">Monthly Budget vs Actual Report</h5>
<a id="downloadBtn" href="#" class="btn btn-outline-success btn-sm d-none">Download Excel</a>
</div>

<form id="monthForm" class="mb-3">
<div class="row g-2 align-items-center">
<div class="col-auto"><label for="month" class="col-form-label">Select Month:</label></div>
<div class="col-auto">
<select name="month" id="month" class="form-select">
<option value="">-- Choose a month --</option>
<?php foreach ($months as $month): ?>
<option value="<?= htmlspecialchars($month) ?>" <?= ($month === $selectedMonth) ? 'selected' : '' ?>><?= htmlspecialchars($month) ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
</form>

<div id="manualEntryBlock" <?= $selectedMonth ? 'style="display:none;"' : '' ?>>
<form method="post" id="manualEntryForm">
<input type="hidden" name="manual_entry" value="1">
<div class="row mb-3">
<div class="col-md-3">
<label class="mb-2">Select Month:</label>
<select name="month_applicable" class="form-select" required>
<option value="">-- Select Month --</option>
<?php
$start = strtotime("2025-04-01");
$end = strtotime("2026-03-01");
while ($start <= $end):
$monthYear = date('F Y', $start);
?>
<option value="<?= $monthYear ?>"><?= $monthYear ?></option>
<?php $start = strtotime("+1 month", $start); endwhile; ?>
</select>
</div>
</div>

<div class="table-responsive">
<table class="table table-bordered">
<thead>
<tr>
<th>Branch Code</th>
<th>Branch Name</th>
<th>Shifts</th>
<th>Provision?</th>
<th>Amount</th>
</tr>
</thead>
<tbody>
<?php for ($i = 0; $i < 5; $i++): ?>
<tr>
<td><input type="text" name="branch_code[]" class="form-control branch-code"></td>
<td><input type="text" name="branch[]" class="form-control branch-name bg-light text-secondary" readonly></td>
<td><input type="number" name="actual_shifts[]" class="form-control shifts" min="1"></td>
<td>
<select name="provision[]" class="form-select provision-select">
<option value="no" selected>No</option>
<option value="yes">Yes</option>
</select>
</td>
<td><input type="text" name="actual_amount[]" class="form-control actual-amount bg-light text-secondary" readonly></td>
</tr>
<?php endfor; ?>
</tbody>
</table>
</div>
<button type="submit" class="btn btn-success">Save Entries</button>
</form>
</div>

<div id="reportArea"></div>
</div>
</div>
</div>

<div class="modal fade" id="errorModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Error</h5></div>
<div class="modal-body"><p id="errorMessage"></p></div>
<div class="modal-footer"><button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button></div>
</div></div></div>
<script>
$('#month').on('change', function(){
    const m=$(this).val();
    if(m){
        $('#manualEntryBlock').hide();
        $.post('ajax-load-monthly-report.php',{month:m},function(r){
            $('#reportArea').html(r.html);
        },'json');
    }else{
        $('#reportArea').html('');
        $('#manualEntryBlock').show();
    }
});

$(document).on('keydown','.branch-code',function(e){
    if(e.key==="Tab"){
        e.preventDefault();
        const row=$(this).closest('tr');
        const code=row.find('.branch-code').val().trim();
        if(code){
            $.post('ajax-get-branch-name.php',{branch_code:code},function(res){
                if(res.success){
                    row.find('.branch-name').val(res.branch);
                    row.find('.shifts').focus();
                } else {
                    alert('Branch not found');
                    row.find('.branch-name').val('');
                }
            },'json');
        }
    }
});

$(document).on('keydown','.shifts',function(e){
    if(e.key==="Tab"){
        e.preventDefault();
        const row=$(this).closest('tr');
        const shifts=parseInt(row.find('.shifts').val())||0;
        const code=row.find('.branch-code').val().trim();
        const month=$('select[name="month_applicable"]').val();
        const amount=row.find('.actual-amount');

        if(shifts>0&&code){
            $.post('ajax-get-branch-rate.php',{branch_code:code,month:month},function(r){
                if(r.success){
                    amount.val((shifts*r.rate).toLocaleString());
                    row.find('.provision-select').focus();
                }else{
                    alert('Rate not found');
                    amount.val('');
                }
            },'json');
        }
    }
});

$(document).on('change','.provision-select',function(){
    const row=$(this).closest('tr');
    const amount=row.find('.actual-amount');
    if($(this).val()==='yes'){amount.prop('readonly',false).removeClass('bg-light text-secondary');}
    else{amount.prop('readonly',true).addClass('bg-light text-secondary');}
});

$(document).on('input','.actual-amount',function(){
    let val=$(this).val().replace(/,/g,'');
    if(val==='') return;
    if(!/^\d+(\.\d{0,2})?$/.test(val)) return;
    $(this).val(parseFloat(val).toLocaleString('en-US'));
});

$('#manualEntryForm').on('submit',function(e){
    let valid=true;
    $('.shifts').each(function(){
        if(parseInt($(this).val())<1){
            $('#errorMessage').text('Minimum shift should be 1.');
            $('#errorModal').modal('show');
            valid=false; return false;
        }
    });
    if(!valid) e.preventDefault();
});
</script>
