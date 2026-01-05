<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

$logFile = __DIR__ . '/security-debug.log';
function log_debug($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

$saved = false;
$error_message = "";
$months = [];

// ✅ Fetch months
if ($conn->connect_error) {
    log_debug("❌ DB ERROR: " . $conn->connect_error);
    die("❌ DB ERROR: " . $conn->connect_error);
}

$res = $conn->query("SELECT DISTINCT month_applicable FROM tbl_admin_actual_security ORDER BY STR_TO_DATE(month_applicable, '%M %Y')");
if ($res) {
    while ($r = $res->fetch_assoc()) $months[] = $r['month_applicable'];
    log_debug("✅ Months fetched: " . json_encode($months));
}

$selectedMonth = $_GET['month'] ?? null;
$dataRows = [];

// ✅ Fetch records if month selected
if ($selectedMonth) {
    $m = mysqli_real_escape_string($conn, $selectedMonth);
    $r = $conn->query("SELECT * FROM tbl_admin_actual_security WHERE month_applicable='$m'");
    while ($row = $r->fetch_assoc()) $dataRows[] = $row;
    log_debug("✅ Loaded data for $selectedMonth: " . count($dataRows) . " records.");
}

// ✅ Save data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $month = mysqli_real_escape_string($conn, $_POST['month_applicable']);
    $code = mysqli_real_escape_string($conn, $_POST['branch_code']);
    $branch = mysqli_real_escape_string($conn, $_POST['branch']);
    $shifts = (int)$_POST['actual_shifts'];
    $amount = (float)$_POST['actual_amount'];
    $provision = $_POST['provision'] === 'yes' ? 'yes' : 'no';
    $sr = generate_sr_number();

    log_debug("➡️ INSERT DATA: $month | $code | $branch | $shifts | $amount | $provision");

    if (!$month || !$code || !$branch || $shifts < 1 || $amount <= 0) {
        $error_message = "❌ All fields required.";
        log_debug($error_message);
    } else {
        $sql = "INSERT INTO tbl_admin_actual_security (branch_code, branch, actual_shifts, total_amount, month_applicable, sr_number, provision) 
                VALUES ('$code', '$branch', $shifts, $amount, '$month', '$sr', '$provision')";
        if ($conn->query($sql)) {
            $saved = true;
            log_debug("✅ Inserted successfully.");
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        } else {
            $error_message = "❌ DB Error: ".$conn->error;
            log_debug($error_message);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Monthly Budget</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <div class="card p-4 shadow">
        <h4 class="mb-3">Monthly Budget vs Actual Report</h4>

        <?php if ($saved): ?><div class="alert alert-success">✅ Saved.</div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

        <form method="get" class="mb-4">
            <select name="month" class="form-select w-25 d-inline" onchange="this.form.submit()">
                <option value="">-- Select Month --</option>
                <?php foreach($months as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= ($selectedMonth===$m)?'selected':'' ?>>
                        <?= htmlspecialchars($m) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selectedMonth): ?>
            <h5>Records for <?= htmlspecialchars($selectedMonth) ?>:</h5>
            <?php if (count($dataRows)): ?>
            <table class="table table-bordered">
                <thead><tr><th>Branch Code</th><th>Branch</th><th>Shifts</th><th>Amount</th><th>Provision</th></tr></thead>
                <tbody>
                <?php foreach ($dataRows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['branch_code']) ?></td>
                        <td><?= htmlspecialchars($r['branch']) ?></td>
                        <td><?= (int)$r['actual_shifts'] ?></td>
                        <td><?= (float)$r['total_amount'] ?></td>
                        <td><?= htmlspecialchars($r['provision']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="alert alert-info">No records found.</div><?php endif; ?>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="manual_entry" value="1">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Month</label>
                        <select name="month_applicable" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php $start=strtotime("2025-04-01"); $end=strtotime("2026-03-01");
                            while($start<=$end): $mo=date('F Y',$start);?>
                                <option value="<?= $mo ?>"><?= $mo ?></option>
                            <?php $start=strtotime("+1 month",$start); endwhile; ?>
                        </select>
                    </div>
                </div>
                <table class="table table-bordered">
                    <tr>
                        <td><input type="text" name="branch_code" class="form-control branch-code" placeholder="Branch Code" required></td>
                        <td><input type="text" name="branch" class="form-control branch-name bg-light text-secondary" placeholder="Branch Name" readonly required></td>
                        <td><input type="number" name="actual_shifts" class="form-control shifts" min="1" placeholder="Shifts" required></td>
                        <td>
                            <select name="provision" class="form-select">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" name="actual_amount" class="form-control actual-amount bg-light text-secondary" placeholder="Amount" readonly required></td>
                        <td><button class="btn btn-success">Save</button></td>
                    </tr>
                </table>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
$('.branch-code').on('blur',function(){
    const code=$(this).val().trim();
    if(code) $.post('ajax-get-branch-name.php',{branch_code:code},function(r){
        if(r.success) $('.branch-name').val(r.branch);
        else alert('❌ Branch not found');
    },'json');
});

$('.shifts').on('keydown',function(e){
    if(e.key==='Tab'){
        e.preventDefault();
        const shifts=parseFloat($('.shifts').val());
        const code=$('.branch-code').val();
        const month=$('select[name="month_applicable"]').val();
        if(shifts>0 && code && month){
            $.post('ajax-get-branch-rate.php',{branch_code:code,month:month},function(r){
                if(r.success) $('.actual-amount').val(shifts*r.rate);
                else alert('❌ Rate not found');
            },'json');
        }
    }
});
</script>
</body>
</html>
