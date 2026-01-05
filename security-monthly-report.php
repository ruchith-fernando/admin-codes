<?php
// security-monthly-report.php
require_once 'connections/connection.php';

// Fixed months April 2025 to March 2026
$start = strtotime("2025-04-01");
$end   = strtotime("2026-03-01");
$fixed_months = [];
while ($start <= $end) {
    $fixed_months[] = date("F Y", $start);
    $start = strtotime("+1 month", $start);
}

// Fetch months with shifts > 0 from new firmwise table
$data_months = [];
$data_query = mysqli_query($conn, "
    SELECT DISTINCT month_applicable 
    FROM tbl_admin_actual_security_firmwise 
    WHERE actual_shifts > 0
");
while($d = mysqli_fetch_assoc($data_query)){
    $data_months[] = $d['month_applicable'];
}

// Get active firms, with id=3 (Other) always last
$firms_q = mysqli_query($conn, "
    SELECT id, firm_name 
    FROM tbl_admin_security_firms
    WHERE active = 'yes'
    ORDER BY 
        CASE WHEN id = 3 THEN 1 ELSE 0 END,
        id
");

// Get security reasons
$reason_options_html = '';
$reasons_q = mysqli_query($conn, "
    SELECT id, reason
    FROM tbl_admin_reason
    WHERE tag = 'security' AND active = 'yes'
    ORDER BY reason
");
ob_start();
?>
<option value="">-- Select Reason --</option>
<?php while($r = mysqli_fetch_assoc($reasons_q)): ?>
  <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['reason']) ?></option>
<?php endwhile; ?>
<?php
$reason_options_html = trim(ob_get_clean());

// 🔹 Load all 2000-type branches (2014, 2015, 2016, 2017, etc.)
$special_2000_codes = [];
$sp_q = mysqli_query($conn, "
    SELECT branch_code 
    FROM tbl_admin_security_2000_branches
    WHERE active = 'yes'
");
if ($sp_q) {
    while ($sp = mysqli_fetch_assoc($sp_q)) {
        $special_2000_codes[] = $sp['branch_code'];
    }
}
?>

<style>
  

table thead th { white-space: normal !important; }

/* Summary table: narrower (reduce width) */
table.summary-compact{
    width: fit-content !important;   /* don't stretch full width */
    table-layout: auto;
}

table.summary-compact th,
table.summary-compact td{
    padding: 0.25rem 0.40rem !important;  /* less horizontal space */
}

/* Category column can wrap so it doesn't force width */
table.summary-compact .sum-cat{
    max-width: 160px;
    white-space: normal;             /* allow wrap */
}

/* Numbers stay tight */
table.summary-compact .sum-num{
    text-align: right;
    white-space: nowrap;
}

  /* View report area */
  #report_section {
      max-height: 70vh;
      overflow-x: auto;
      overflow-y: auto;
  }

  #report_section table {
      min-width: 800px; /* force columns to spread, horizontal scroll if needed */
  }

  
  #manual_form .table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
}

#manual_form table.responsive {
    width: 100%;
    min-width: 900px;      /* can bump to 1300 if needed */
    table-layout: auto;
    border-collapse: collapse;
}

#manual_form table.responsive th,
#manual_form table.responsive td {
    padding: 0.5rem 0.75rem;
    vertical-align: middle;
    white-space: nowrap;
}

#manual_form table.responsive input.form-control,
#manual_form table.responsive select.form-select,
#manual_form table.responsive textarea.form-control {
    width: 100%;
    box-sizing: border-box;
}

/* numeric alignment */
#manual_form .amount,
#manual_form .shifts,
#manual_form .budget_shifts {
    text-align: right;
}
</style>

<option value="">-- Select Reason --</option>
<?php while($r = mysqli_fetch_assoc($reasons_q)): ?>
  <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['reason']) ?></option>
<?php endwhile; ?>
<?php
$reason_options_html = trim(ob_get_clean());
?>
<style>
  /* View report area */
  #report_section {
      max-height: 70vh;
      overflow-x: auto;
      overflow-y: auto;
  }

  #report_section table {
      min-width: 1200px; /* force columns to spread, horizontal scroll if needed */
  }

  
  #manual_form .table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
}

#manual_form table.responsive {
    width: 100%;
    min-width: 1200px;      /* can bump to 1300 if needed */
    table-layout: auto;
    border-collapse: collapse;
}

#manual_form table.responsive th,
#manual_form table.responsive td {
    padding: 0.5rem 0.75rem;
    vertical-align: middle;
    white-space: nowrap;
}

#manual_form table.responsive input.form-control,
#manual_form table.responsive select.form-select,
#manual_form table.responsive textarea.form-control {
    width: 100%;
    box-sizing: border-box;
}

/* numeric alignment */
#manual_form .amount,
#manual_form .shifts,
#manual_form .budget_shifts {
    text-align: right;
}


</style>




<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-4 text-primary">Security – Report & Monthly Data Entry</h5>

      <!-- 🔹 Report View Dropdown -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Month to View Report</label>
        <select id="month_view" class="form-select">
          <option value="">-- Choose a Month --</option>
          <?php foreach($fixed_months as $month): 
              if (in_array($month, $data_months)): ?>
              <option value="<?= htmlspecialchars($month) ?>"><?= htmlspecialchars($month) ?></option>
          <?php endif; endforeach; ?>
        </select>
      </div>

      <!-- 🆕 CSV Download Button -->
      <div id="csv_download_container" class="mb-3 d-none">
        <button class="btn btn-outline-primary" id="download_csv_btn">⬇️ Download CSV</button>
      </div>

      <!-- 🔹 Missing Branches for Report View -->
      <div id="missing_view_branches" class="alert alert-warning d-none"></div>

      <!-- 🔹 Report Table Section -->
      <div id="report_section" class="d-none"></div>

      <hr>

      <!-- 🔹 Firm Selection for Manual Entry -->
      <div class="mb-3">
        <label class="form-label fw-bold">Select Security Firm</label>
        <select id="firm_select" class="form-select">
          <option value="">-- Select Firm --</option>
          <?php while($f = mysqli_fetch_assoc($firms_q)): ?>
            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['firm_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- 🔹 Manual Entry Month Dropdown -->
      <div class="mb-1">
        <label class="form-label fw-bold">Select Month to Enter Data</label>
        <select id="month_manual" class="form-select">
          <option value="">-- Select Month --</option>
          <?php foreach($fixed_months as $m): ?>
              <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="missing_manual_branches" class="alert alert-warning mt-3 d-none"></div>
      <div id="provision_branch_info" class="alert alert-info mt-3 d-none"></div>

      <!-- 🔹 Manual Form Entry -->
<div id="manual_form" class="d-none">
  <div class="table-responsive">
    <table class="table table-bordered responsive">
        <thead class="table-light">
            <tr>
                <th>Branch Code</th>
                <th>Branch Name</th>

                <!-- columns we will HIDE for 2014/5/6 -->
                <th class="col-budget">Budgeted Shifts</th>
                <th class="col-shifts">Shifts</th>
                <th class="col-reason">Reason (if &gt; Budget)</th>

                <th>Provision?</th>
                <th>Previous Month</th>

                <!-- Invoice column – shown only for 2014/5/6 -->
                <th class="col-invoice d-none">Invoice / Reference No</th>

                <th>Amount</th>
            </tr>
        </thead>

        <tbody id="entry_rows">
            <tr>
                <td>
                    <input type="text" class="form-control branch_code" maxlength="5" />
                </td>
                <td>
                    <input type="text" class="form-control branch_name" readonly />
                </td>

                <!-- match header classes -->
                <td class="col-budget">
                    <input type="number" class="form-control budget_shifts" readonly />
                </td>
                <td class="col-shifts">
                    <input type="number" class="form-control shifts" min="1" />
                </td>
                <td class="col-reason">
                    <select class="form-select reason_select d-none">
                        <?= $reason_options_html ?>
                    </select>
                </td>

                <td>
                    <select class="form-select provision">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </td>
                <td>
                  <textarea class="form-control previous_month_info" readonly rows="1" 
                    style="resize: none; overflow: hidden; height: auto;"></textarea>
                </td>

                <!-- invoice input – only visible for 2014/5/6 -->
                <td class="col-invoice d-none">
                    <input type="text" class="form-control invoice_no" />
                </td>

                <td>
                    <input type="text" class="form-control amount" readonly />
                </td>
            </tr>
        </tbody>
    </table>
  </div>
  <button class="btn btn-success" id="save_entry">Save Entry</button>
</div>



      <!-- 🔹 Submission Status -->
      <div id="status_msg" class="mt-3"></div>
    </div>
  </div>
</div>

<!-- 🔹 Modal for viewing reason in report -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reason for Extra Shifts</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="reasonModalBody">
      </div>
    </div>
  </div>
</div>
<script>
  var SECURITY_REASON_OPTIONS = <?= json_encode($reason_options_html) ?>;
</script>
<?php
// Build reason <option> list from tbl_admin_reason (active = 'yes')
$reasonOptions = "<option value=''>-- Select Reason --</option>";

$q = mysqli_query($conn, "
    SELECT id, reason
    FROM tbl_admin_reason
    WHERE active = 'yes'
    ORDER BY reason
");

if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $id = (int)$r['id'];
        $reason = htmlspecialchars($r['reason'], ENT_QUOTES);
        $reasonOptions .= "<option value='{$id}'>{$reason}</option>";
    }
}
?>
<script>
    // Used by security-monthly-report.js when it builds new rows
    var SECURITY_REASON_OPTIONS = <?php echo json_encode($reasonOptions); ?>;
</script>

<script src="security-monthly-report.js?v=12"></script>

