<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'connections/connection.php';
require_once 'includes/sr-generator.php';

$logFile = __DIR__ . '/security-debug.log';
function log_debug($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL, FILE_APPEND);
}

$saved = false;
$error_message = "";
$months = [];

// ✅ Fetch Months
$months_result = $conn->query("SELECT DISTINCT month_applicable FROM tbl_admin_actual_security ORDER BY STR_TO_DATE(month_applicable, '%M %Y')");
if ($months_result) {
    while ($row = $months_result->fetch_assoc()) $months[] = $row['month_applicable'];
    log_debug("✅ Months fetched: " . json_encode($months));
} else {
    log_debug("❌ Month fetch failed: " . $conn->error);
}

$selectedMonth = $_GET['month'] ?? null;

// ✅ Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_entry'])) {
    $month = $_POST['month_applicable'] ?? '';
    $branch_code = trim($_POST['branch_code'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $shifts = (int) ($_POST['actual_shifts'] ?? 0);
    $amount = floatval($_POST['actual_amount'] ?? 0);
    $provision = ($_POST['provision'] ?? 'no') === 'yes' ? 'yes' : 'no';

    log_debug("✅ Form Submitted ➡️ Month: $month | Code: $branch_code | Branch: $branch | Shifts: $shifts | Amount: $amount | Provision: $provision");

    if (empty($month) || empty($branch_code) || $shifts < 1) {
        $error_message = "❌ Invalid submission";
        log_debug($error_message);
    } else {
        $sql = "INSERT INTO tbl_admin_actual_security (branch_code, branch, actual_shifts, total_amount, month_applicable, sr_number, provision)
                VALUES ('$branch_code', '$branch', $shifts, $amount, '$month', '', '$provision')";
        log_debug("✅ SQL Insert ➡️ $sql");

        if ($conn->query($sql)) {
            $last_id = $conn->insert_id;
            $sr_number = generate_sr_number($conn, 'tbl_admin_actual_security', $last_id);
            log_debug("✅ Insert Successful | ID: $last_id | SR: $sr_number");
            $saved = true;
        } else {
            $error_message = "❌ Insert Failed: " . $conn->error;
            log_debug($error_message);
        }
    }
}
?>
<div class="content font-size">
    <div class="container-fluid">
        <div class="card shadow bg-white rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-4 text-primary">Monthly Budget vs Actual Report</h5>
</div>
            <?php if ($saved): ?>
                <div class="alert alert-success">✅ Entry Saved</div>
            <?php elseif ($error_message): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>

            <form id="monthForm">
                <label>Select Month:</label>
                <select name="month" id="month" class="form-select mb-3">
                    <option value="">-- Select --</option>
                    <?php foreach ($months as $month): ?>
                        <option value="<?= htmlspecialchars($month) ?>" <?= $month === $selectedMonth ? 'selected' : '' ?>><?= htmlspecialchars($month) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div id="reportArea">
                <?php if ($selectedMonth): 
                    $res = $conn->query("SELECT * FROM tbl_admin_actual_security WHERE month_applicable='$selectedMonth'");
                    echo "<h6>Records for $selectedMonth:</h6>";
                    if ($res && $res->num_rows > 0) {
                        echo "<table class='table table-bordered'><tr><th>Branch</th><th>Shifts</th><th>Amount</th></tr>";
                        while ($r = $res->fetch_assoc()) {
                            echo "<tr><td>{$r['branch']}</td><td>{$r['actual_shifts']}</td><td>{$r['total_amount']}</td></tr>";
                        }
                        echo "</table>";
                    } else echo "<p>No records found.</p>";
                endif; ?>
            </div>

            <form method="post" id="manualEntryForm">
                <input type="hidden" name="manual_entry" value="1">
                <label>Month:</label>
                <select name="month_applicable" class="form-select mb-2" required>
                    <?php foreach ($months as $month): ?>
                        <option value="<?= $month ?>"><?= $month ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="branch_code" placeholder="Branch Code" class="form-control mb-2 branch-code" required>
                <input type="text" name="branch" placeholder="Branch Name" class="form-control mb-2 branch-name" readonly required>
                <input type="number" name="actual_shifts" placeholder="Shifts" class="form-control mb-2 shifts" required>
                <select name="provision" class="form-select mb-2">
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
                <input type="number" step="0.01" name="actual_amount" placeholder="Amount" class="form-control mb-2 actual-amount" required>
                <button class="btn btn-success">Save</button>
            </form>
        </div>
       
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#month').change(function(){
    const m = $(this).val();
    if (m) window.location.href = '?month=' + encodeURIComponent(m);
});

$('.branch-code').blur(function(){
    let code = $(this).val();
    if (code) {
        $.post('ajax-get-branch-name.php', {branch_code: code}, function(r){
            $('.branch-name').val(r.success ? r.branch : '');
        }, 'json');
    }
});

$('.shifts').blur(function(){
    let code = $('.branch-code').val();
    let shifts = parseInt($('.shifts').val()) || 0;
    let month = $('select[name="month_applicable"]').val();
    if (code && shifts > 0 && month) {
        $.post('ajax-get-branch-rate.php', {branch_code: code, month}, function(r){
            $('.actual-amount').val(r.success ? (shifts * r.rate) : '');
        }, 'json');
    }
});
</script>
