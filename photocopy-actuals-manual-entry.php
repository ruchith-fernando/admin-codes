<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "connections/connection.php";
set_time_limit(0);

function current_user_id(){
    foreach (['user_id','userid','userId','admin_id','emp_id','uid','id'] as $k) {
        if (isset($_SESSION[$k]) && $_SESSION[$k] !== '') return (string)$_SESSION[$k];
    }
    return '';
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($arr){ header("Content-Type: application/json; charset=utf-8"); echo json_encode($arr); exit; }

function safe_int($v){
    $v = trim((string)$v);
    if ($v === '') return null;
    if (!preg_match('/^-?\d+$/', $v)) return null;
    return (int)$v;
}
function parse_month($raw){
    $raw = trim((string)$raw);
    if ($raw === '') return false;
    $raw = preg_replace('/\s+/', ' ', $raw);
    $raw = str_replace(['-', '/', '.'], ' ', $raw);
    $raw = preg_replace('/\s+/', ' ', $raw);

    $dt = DateTime::createFromFormat('F Y', $raw);
    if (!$dt) $dt = DateTime::createFromFormat('M Y', $raw);
    if (!$dt) return false;

    return [
        "label"      => $dt->format('F Y'),
        "month_date" => $dt->format('Y-m-01'),
        "period_end" => $dt->format('Y-m-t'),
    ];
}

if (!isset($conn) || !($conn instanceof mysqli)) { die("DB connection missing."); }
$userId = current_user_id();
if ($userId === '') { die("Not logged in."); }

// ---------- AJAX save ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_manual') {

    $serialNo = trim((string)($_POST['serial_no'] ?? ''));
    $monthRaw = trim((string)($_POST['month_text'] ?? ''));
    $startRaw = $_POST['start_count'] ?? '';
    $endRaw   = $_POST['end_count'] ?? '';
    $excelLoc = trim((string)($_POST['excel_branch_location'] ?? ''));

    if ($serialNo === '') json_out(["status"=>"error","message"=>"Serial No is required."]);
    $mi = parse_month($monthRaw);
    if (!$mi) json_out(["status"=>"error","message"=>"Invalid month. Use like 'September 2025'."]);

    $startCount = safe_int($startRaw);
    $endCount   = safe_int($endRaw);
    if ($startCount === null || $endCount === null) json_out(["status"=>"error","message"=>"Start/End must be integers."]);
    $copyCount = $endCount - $startCount;
    if ($copyCount < 0) json_out(["status"=>"error","message"=>"End Count is less than Start Count."]);

    $monthDate = $mi['month_date'];
    $periodEnd = $mi['period_end'];

    // Machine
    $stmtMachine = $conn->prepare("SELECT machine_id, model_name, serial_no, vendor_id, rate_profile_id, is_active
                                   FROM tbl_admin_photocopy_machines WHERE serial_no=? LIMIT 1");
    $stmtMachine->bind_param("s", $serialNo);
    $stmtMachine->execute();
    $machine = $stmtMachine->get_result()->fetch_assoc();
    if (!$machine) {
        json_out([
          "status"=>"error",
          "code"=>"SERIAL_NOT_FOUND",
          "message"=>"Serial not found in Machines Master. Add it first, then retry.",
          "help_links"=>[
            ["label"=>"Open Machines Master", "url"=>"photocopy-machines-master.php"]
          ]
        ]);
    }
    if ((int)$machine['is_active'] !== 1) {
        json_out(["status"=>"error","code"=>"MACHINE_INACTIVE","message"=>"Machine is inactive in master. Activate it and retry."]);
    }
    $machineId = (int)$machine['machine_id'];

    // Assignment by period end
    $stmtAssign = $conn->prepare("
        SELECT branch_code, vendor_id, installed_at, removed_at
        FROM tbl_admin_photocopy_machine_assignments
        WHERE machine_id = ?
          AND installed_at <= ?
          AND (removed_at IS NULL OR removed_at >= ?)
        ORDER BY installed_at DESC
        LIMIT 1
    ");
    $stmtAssign->bind_param("iss", $machineId, $periodEnd, $periodEnd);
    $stmtAssign->execute();
    $assign = $stmtAssign->get_result()->fetch_assoc();
    if (!$assign) {
        json_out([
          "status"=>"error",
          "code"=>"NO_ACTIVE_ASSIGNMENT",
          "message"=>"No assignment found for this machine for period end {$periodEnd}. Create assignment and retry.",
          "help_links"=>[
            ["label"=>"(If you have it) Open Assignments Screen", "url"=>"#"]
          ]
        ]);
    }
    $branchCode = $assign['branch_code'];

    // Vendor resolve
    $assignVendor  = isset($assign['vendor_id']) && $assign['vendor_id'] !== null ? (int)$assign['vendor_id'] : null;
    $machineVendor = isset($machine['vendor_id']) && $machine['vendor_id'] !== null ? (int)$machine['vendor_id'] : null;
    $vendorId = $assignVendor ?: ($machineVendor ?: null);
    if (!$vendorId) json_out(["status"=>"error","code"=>"VENDOR_MISSING","message"=>"Vendor not set on assignment or machine."]);

    // Rate resolve
    $rate = null;
    $machineRateProfileId = isset($machine['rate_profile_id']) ? (int)$machine['rate_profile_id'] : 0;
    if ($machineRateProfileId > 0) {
        $stmtRateById = $conn->prepare("
            SELECT rate_profile_id, copy_rate, sscl_percentage, vat_percentage
            FROM tbl_admin_photocopy_rate_profiles
            WHERE rate_profile_id = ?
              AND vendor_id = ?
              AND is_active = 1
              AND (effective_from IS NULL OR effective_from <= ?)
              AND (effective_to   IS NULL OR effective_to   >= ?)
            LIMIT 1
        ");
        $stmtRateById->bind_param("iiss", $machineRateProfileId, $vendorId, $periodEnd, $periodEnd);
        $stmtRateById->execute();
        $rate = $stmtRateById->get_result()->fetch_assoc();
    }

    if (!$rate) {
        $machineModel = trim((string)($machine['model_name'] ?? ''));
        $stmtRateAuto = $conn->prepare("
            SELECT rate_profile_id, copy_rate, sscl_percentage, vat_percentage
            FROM tbl_admin_photocopy_rate_profiles
            WHERE vendor_id = ?
              AND is_active = 1
              AND (effective_from IS NULL OR effective_from <= ?)
              AND (effective_to   IS NULL OR effective_to   >= ?)
            ORDER BY
              CASE
                WHEN model_match = ? THEN 0
                WHEN model_match IS NOT NULL AND model_match <> '' AND ? LIKE CONCAT('%', model_match, '%') THEN 1
                WHEN model_match IS NULL OR model_match = '' THEN 2
                ELSE 3
              END ASC,
              effective_from DESC,
              rate_profile_id DESC
            LIMIT 1
        ");
        $stmtRateAuto->bind_param("issss", $vendorId, $periodEnd, $periodEnd, $machineModel, $machineModel);
        $stmtRateAuto->execute();
        $rate = $stmtRateAuto->get_result()->fetch_assoc();
    }

    if (!$rate) json_out(["status"=>"error","code"=>"RATE_PROFILE_MISSING","message"=>"No active rate profile matched vendor/model/date."]);

    $rateId   = (int)$rate['rate_profile_id'];
    $copyRate = (float)$rate['copy_rate'];
    $ssclPct  = (float)$rate['sscl_percentage'];
    $vatPct   = (float)$rate['vat_percentage'];

    $base = round($copyCount * $copyRate, 2);
    $sscl = round($base * ($ssclPct/100), 2);
    $vat  = round(($base + $sscl) * ($vatPct/100), 2);
    $total = round($base + $sscl + $vat, 2);

    // Upsert actuals
    $stmtCheck = $conn->prepare("SELECT actual_id FROM tbl_admin_actual_photocopy WHERE machine_id=? AND month_applicable=? LIMIT 1");
    $stmtCheck->bind_param("is", $machineId, $monthDate);
    $stmtCheck->execute();
    $existing = $stmtCheck->get_result()->fetch_assoc();

    if (!$existing) {
        $stmtIns = $conn->prepare("
            INSERT INTO tbl_admin_actual_photocopy
            (month_applicable, machine_id, serial_no, model_name,
             branch_code, vendor_id, rate_profile_id,
             copy_rate, sscl_percentage, vat_percentage,
             start_count, end_count, copy_count,
             base_amount, sscl_amount, vat_amount, total_amount,
             excel_branch_location, uploaded_at)
            VALUES
            (?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?, ?,
             ?, NOW())
        ");
        $modelName = $machine['model_name'] ?? null;
        $serialStored = $machine['serial_no'] ?? $serialNo;

        $stmtIns->bind_param(
            "sisssiidddiiidddds",
            $monthDate, $machineId, $serialStored, $modelName,
            $branchCode, $vendorId, $rateId,
            $copyRate, $ssclPct, $vatPct,
            $startCount, $endCount, $copyCount,
            $base, $sscl, $vat, $total,
            $excelLoc
        );

        if (!$stmtIns->execute()) json_out(["status"=>"error","message"=>"Insert failed: ".$conn->error]);
        json_out(["status"=>"success","message"=>"✅ Manual entry saved (INSERT).","action"=>"INSERT","actual_id"=>(int)$conn->insert_id]);
    } else {
        $actualId = (int)$existing['actual_id'];
        $stmtUpd = $conn->prepare("
            UPDATE tbl_admin_actual_photocopy
            SET serial_no=?,
                model_name=?,
                branch_code=?,
                vendor_id=?,
                rate_profile_id=?,
                copy_rate=?,
                sscl_percentage=?,
                vat_percentage=?,
                start_count=?,
                end_count=?,
                copy_count=?,
                base_amount=?,
                sscl_amount=?,
                vat_amount=?,
                total_amount=?,
                excel_branch_location=?,
                updated_at=NOW()
            WHERE actual_id=?
            LIMIT 1
        ");
        $modelName = $machine['model_name'] ?? null;
        $serialStored = $machine['serial_no'] ?? $serialNo;

        $stmtUpd->bind_param(
            "sssiidddiiidddds i",
            $serialStored, $modelName, $branchCode,
            $vendorId, $rateId,
            $copyRate, $ssclPct, $vatPct,
            $startCount, $endCount, $copyCount,
            $base, $sscl, $vat, $total,
            $excelLoc,
            $actualId
        );

        // Fix bind string spacing (PHP requires no spaces)
        // We'll re-bind properly:
        $stmtUpd->bind_param(
            "sssiidddiiiddddsi",
            $serialStored, $modelName, $branchCode,
            $vendorId, $rateId,
            $copyRate, $ssclPct, $vatPct,
            $startCount, $endCount, $copyCount,
            $base, $sscl, $vat, $total,
            $excelLoc,
            $actualId
        );

        if (!$stmtUpd->execute()) json_out(["status"=>"error","message"=>"Update failed: ".$conn->error]);
        json_out(["status"=>"success","message"=>"✅ Manual entry saved (UPDATE).","action"=>"UPDATE","actual_id"=>$actualId]);
    }
}

// ---------- UI ----------
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Photocopy — Manual Actual Entry</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
  .content.font-size{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
  .card h5{margin:0 0 16px;color:#0d6efd}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .col{flex:1;min-width:220px}
  .mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem;font-weight:600}
  .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px;background:#fff}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer;text-decoration:none}
  .btn-success{background:#198754;color:#fff}
  .btn-outline{background:#fff;border:1px solid #0d6efd;color:#0d6efd}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .alert-success{background:#e8f5e9;color:#1b5e20}
  .alert-danger{background:#ffebee;color:#b71c1c}
  .hint{font-size:.92rem;color:#555;line-height:1.45}
  .action-links{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
</style>
</head>
<body>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <h5>Photocopy — Manual Actual Entry</h5>

      <div class="hint">
        Use this when some rows failed in upload and you want to enter them manually.
        The system will still resolve: <b>Serial → Machine → Assignment → Rate Profile</b> and save into <b>tbl_admin_actual_photocopy</b>.
      </div>

      <div id="msg"></div>

      <form id="frm">
        <input type="hidden" name="action" value="save_manual">

        <div class="row mb-3">
          <div class="col">
            <label class="form-label">Serial No <span style="color:#dc3545">*</span></label>
            <input class="form-control" name="serial_no" required placeholder="e.g. 45916610">
          </div>
          <div class="col">
            <label class="form-label">Applicable Month <span style="color:#dc3545">*</span></label>
            <input class="form-control" name="month_text" required placeholder="e.g. September 2025">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col">
            <label class="form-label">Start Count <span style="color:#dc3545">*</span></label>
            <input class="form-control" name="start_count" required>
          </div>
          <div class="col">
            <label class="form-label">End Count <span style="color:#dc3545">*</span></label>
            <input class="form-control" name="end_count" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Excel Branch Location (optional)</label>
          <input class="form-control" name="excel_branch_location" placeholder="e.g. Kurunegala Yard">
        </div>

        <button class="btn btn-success" type="submit">Save Manual Entry</button>
        <a class="btn btn-outline" href="photocopy-machines-master.php">Open Machines Master</a>
        <a class="btn btn-outline" href="photocopy-upload-batches.php">Open Upload Reports</a>
      </form>

    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function(){
  const $msg = $('#msg');

  function esc(s){
    return String(s ?? '').replace(/[&<>"']/g, (m)=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m]));
  }
  function show(type, html){
    const cls = (type==='success') ? 'alert-success' : 'alert-danger';
    $msg.html(`<div class="alert ${cls}">${html}</div>`);
    window.scrollTo({top:0, behavior:'smooth'});
  }

  $('#frm').on('submit', function(e){
    e.preventDefault();
    $msg.empty();

    $.ajax({
      url: 'photocopy-actuals-manual-entry.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(resp){
        if(resp.status === 'success'){
          show('success', `<b>${esc(resp.message)}</b><br>Action: <b>${esc(resp.action)}</b> | Actual ID: <b>${esc(resp.actual_id)}</b>`);
        } else {
          let extra = '';
          if(resp.help_links && resp.help_links.length){
            extra += "<div class='action-links'>";
            resp.help_links.forEach(l=>{
              extra += `<a class="btn btn-outline" href="${esc(l.url)}">${esc(l.label)}</a>`;
            });
            extra += "</div>";
          }
          show('error', `<b>❌ ${esc(resp.message || 'Failed')}</b>` + extra);
        }
      },
      error: function(xhr){
        show('error', `<b>❌ ${esc(xhr.responseText || 'Request failed')}</b>`);
      }
    });
  });
})();
</script>
</body>
</html>
