<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/actual-entry-errors.log');

require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

error_log("=== PAGE LOADED ===");

$months = [];
$months_result = $conn->query("SELECT DISTINCT month_applicable AS month FROM tbl_admin_budget_security ORDER BY STR_TO_DATE(month_applicable, '%M %Y')");
while ($row = $months_result->fetch_assoc()) {
    $months[] = $row['month'];
}

$branch_list = [];
$branches_result = $conn->query("SELECT DISTINCT branch_code, branch FROM tbl_admin_budget_security ORDER BY branch ASC");
while ($row = $branches_result->fetch_assoc()) {
    $branch_list[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_entry'])) {
    $stmt = $conn->prepare("INSERT INTO tbl_admin_actual_security 
    (branch_code, branch, month_applicable, actual_shifts, total_amount, is_provision, provision_updated_at) 
    VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) die("SQL Error: ".$conn->error);
    $stmt->bind_param("sssids", $code, $branch, $month, $shifts, $amount, $is_provision);

    foreach ($_POST['branch_code'] as $i => $code) {
        $branch = trim($_POST['branch_name'][$i]);
        $month = trim($_POST['month_applicable']);
        $shifts = (int) ($_POST['actual_shifts'][$i] ?? 0);
        $amount = (int) ($_POST['actual_amount'][$i] ?? 0);
        $is_provision = isset($_POST['is_provision'][$i]) ? 'yes' : 'no';
        if (!$code || !$branch || !$month) continue;
        if ($shifts <= 0 && $amount <= 0) continue;
        if ($stmt->execute()) {
            $last_id = $conn->insert_id;
            generate_sr_number($conn, 'tbl_admin_actual_security', $last_id);
        }
    }
    header("Location: monthly-budget-vs-actual.php?month=".urlencode($month));
    exit;
}
$selectedMonth = $_GET['month'] ?? null;
?>

<body class="bg-light">
<div class="content font-size">
<div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
        <h5 class="mb-4 text-primary">Monthly Budget vs Actual Report</h5>

<form id="monthForm"><div class="row g-2 mb-3">
<div class="col-auto"><label>Select Month:</label></div>
<div class="col-auto">
<select id="month" name="month" class="form-select">
<option value="">-- Select Month --</option>
<?php foreach ($months as $month): ?>
<option value="<?= htmlspecialchars($month) ?>" <?= ($month == $selectedMonth) ? 'selected' : '' ?>><?= htmlspecialchars($month) ?></option>
<?php endforeach; ?>
</select>
</div></div></form>

<div id="manualEntryBlock" <?= $selectedMonth ? 'style="display:none;"' : '' ?>>
<form method="post">
<input type="hidden" name="manual_entry" value="1">
<div class="mb-3"><label>Select Month to Submit:</label>
<select name="month_applicable" class="form-select" required>
<option value="">-- Select Month --</option>
<?php foreach ($months as $month): ?>
<option value="<?= htmlspecialchars($month) ?>"><?= htmlspecialchars($month) ?></option>
<?php endforeach; ?>
</select></div>

<table class="table table-bordered">
<thead><tr><th>Branch Code</th><th>Branch Name</th><th>Shifts</th><th>Amount</th><th>Provision?</th></tr></thead>
<tbody>
<?php for ($i=0; $i<5; $i++): ?>
<tr>
<td>
<select name="branch_code[]" class="form-select branch-dropdown" data-index="<?= $i ?>">
<option value="">-- Select Branch --</option>
<?php foreach ($branch_list as $b): ?>
<option value="<?= htmlspecialchars($b['branch_code']) ?>" data-branch="<?= htmlspecialchars($b['branch']) ?>"><?= htmlspecialchars($b['branch_code']) ?> - <?= htmlspecialchars($b['branch']) ?></option>
<?php endforeach; ?>
</select>
</td>
<td><input type="text" class="form-control branch-display-<?= $i ?>" readonly>
<input type="hidden" name="branch_name[]" class="branch-hidden-<?= $i ?>"></td>
<td><input type="number" name="actual_shifts[]" class="form-control" step="1" placeholder="Optional"></td>
<td>
<input type="text" class="form-control amount-display amount-display-<?= $i ?>" data-index="<?= $i ?>" placeholder="0">
<input type="hidden" name="actual_amount[]" class="amount-hidden-<?= $i ?>">
</td>
<td class="text-center"><input type="checkbox" name="is_provision[<?= $i ?>]" value="yes"></td>
</tr>
<?php endfor; ?>
</tbody>
</table>
<button type="submit" class="btn btn-success">Save Entries</button>
</form></div><div id="reportArea"></div></div></div>

<script>
$('#month').on('change', function() {
    const month = $(this).val();
    if (month) {$('#manualEntryBlock').hide(); $.post('ajax-load-monthly-report.php',{month}, function(res){$('#reportArea').html(res.html)},'json');} 
    else {$('#reportArea').html(''); $('#manualEntryBlock').show();}
});

$(document).on('change','.branch-dropdown',function(){
let idx=$(this).data('index'), branch=$(this).find(':selected').data('branch')||'';
$('.branch-display-'+idx).val(branch);
$('.branch-hidden-'+idx).val(branch);
});

$(document).ready(function(){
$('.branch-dropdown').select2({placeholder:"-- Select Branch --",width:'100%'}).on('change',function(){
let idx=$(this).data('index'), branch=$(this).find('option:selected').data('branch')||'';
$('.branch-display-'+idx).val(branch);$('.branch-hidden-'+idx).val(branch);
});
});

// ✅ WORKING 1000 SEPARATOR NO DECIMALS
$(document).on('input','.amount-display',function(){
let idx=$(this).data('index');
let val=$(this).val().replace(/,/g,'').replace(/[^\d]/g,'');
if(val===''){$('.amount-hidden-'+idx).val('');$(this).val('');return;}
let num=parseInt(val);
if(isNaN(num)){$('.amount-hidden-'+idx).val('');$(this).val('');return;}
$('.amount-hidden-'+idx).val(num);
$(this).val(num.toLocaleString('en-US'));
});
</script>
