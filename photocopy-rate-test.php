<?php
// pages/photocopy-rate-test.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "connections/connection.php";

// ------------------------------
// OPTIONAL DEBUG (turn on by URL ?debug=1)
// ------------------------------
$DEBUG = (isset($_GET['debug']) && $_GET['debug'] == '1');
if ($DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    ini_set('display_errors', 0);
}

// ------------------------------
// HELPERS
// ------------------------------
function json_out($arr, $code = 200) {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($arr);
    exit;
}

function tableExists(mysqli $conn, string $table): bool {
    $sql = "
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $stmt->store_result();
    $ok = ($stmt->num_rows > 0);
    $stmt->close();
    return $ok;
}

function norm($s): string {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', ' ', $s);
    return strtoupper($s);
}

function isLikePattern(string $s): bool {
    return (strpos($s, '%') !== false || strpos($s, '_') !== false);
}

/**
 * Convert SQL LIKE pattern to regex:
 *   % -> .*
 *   _ -> .
 */
function likeToRegex(string $likePattern): string {
    // Escape regex special chars first, then re-enable % and _ conversions
    $p = preg_quote($likePattern, '/');
    $p = str_replace('\%', '.*', $p);
    $p = str_replace('\_', '.',  $p);
    return '/^' . $p . '$/i';
}

function money($n): string {
    return number_format((float)$n, 2, '.', ',');
}

/**
 * Resolve best matching profile in PHP (no ESCAPE needed).
 * Scores:
 *  - empty model_match => score 1 (default/fallback)
 *  - LIKE pattern match => score 1000 + length(model_match)
 *  - substring match => score 500 + length(model_match)
 */
function resolveBestProfile(array $profiles, string $modelNorm): ?array {
    $best = null;
    $bestScore = -1;

    foreach ($profiles as $p) {
        $mmRaw  = (string)($p['model_match'] ?? '');
        $mmNorm = norm($mmRaw);

        $score = 0;
        $matched = false;

        if ($mmNorm === '') {
            // default profile
            $score = 1;
            $matched = true;
        } else {
            if (isLikePattern($mmRaw)) {
                $rx = likeToRegex(norm($mmRaw));
                if (preg_match($rx, $modelNorm)) {
                    $score = 1000 + strlen($mmNorm);
                    $matched = true;
                }
            } else {
                if ($modelNorm !== '' && strpos($modelNorm, $mmNorm) !== false) {
                    $score = 500 + strlen($mmNorm);
                    $matched = true;
                }
            }
        }

        if (!$matched) continue;

        // Tie-breakers:
        // 1) higher score
        // 2) longer model_match
        // 3) higher rate_profile_id
        if (
            $score > $bestScore ||
            ($score === $bestScore && strlen($mmNorm) > strlen(norm($best['model_match'] ?? ''))) ||
            ($score === $bestScore && (int)$p['rate_profile_id'] > (int)($best['rate_profile_id'] ?? 0))
        ) {
            $bestScore = $score;
            $best = $p;
            $best['_score'] = $score;
        }
    }

    return $best;
}

// ------------------------------
// TABLE NAMES (supports both vendor masters)
// ------------------------------
$T_RATE  = 'tbl_admin_photocopy_rate_profiles';
$T_MAC   = 'tbl_admin_photocopy_machines';
$T_VEND1 = 'tbl_admin_vendors';            // preferred (your master)
$T_VEND2 = 'tbl_admin_photocopy_vendors';  // fallback if you still have it

$hasRate = tableExists($conn, $T_RATE);
$hasMac  = tableExists($conn, $T_MAC);
$hasVend1= tableExists($conn, $T_VEND1);
$hasVend2= tableExists($conn, $T_VEND2);

$vendorSource = null;
if ($hasVend1) $vendorSource = $T_VEND1;
elseif ($hasVend2) $vendorSource = $T_VEND2;

// ------------------------------
// AJAX: RESOLVE
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve') {

    if (!$hasRate) {
        json_out(['success' => false, 'message' => "Missing table: {$T_RATE}"], 500);
    }

    $vendor_id  = (int)($_POST['vendor_id'] ?? 0);
    $serial_no  = trim($_POST['serial_no'] ?? '');
    $model_name = trim($_POST['model_name'] ?? '');
    $as_of_date = trim($_POST['as_of_date'] ?? date('Y-m-d'));
    $copy_count = (int)($_POST['copy_count'] ?? 0);

    if ($vendor_id <= 0 && $serial_no === '' && $model_name === '') {
        json_out(['success' => false, 'message' => 'Provide Vendor OR Serial OR Model to test.'], 400);
    }

    // Validate date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_of_date)) {
        json_out(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.'], 400);
    }

    $resolvedFromSerial = false;

    // If serial provided, try to fetch model/vendor from machines table
    if ($serial_no !== '' && $hasMac) {
        $sql = "SELECT model_name, vendor_id FROM {$T_MAC} WHERE serial_no = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $serial_no);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ($model_name === '' && !empty($row['model_name'])) {
                $model_name = (string)$row['model_name'];
            }
            if ($vendor_id <= 0 && !empty($row['vendor_id'])) {
                $vendor_id = (int)$row['vendor_id'];
            }
            $resolvedFromSerial = true;
        }
    }

    if ($vendor_id <= 0) {
        json_out(['success' => false, 'message' => 'Vendor is required (or provide a serial that has vendor_id in machines master).'], 400);
    }

    $modelNorm = norm($model_name);

    // Load candidate profiles for vendor/date (no model filtering in SQL)
    $sql = "
        SELECT rate_profile_id, vendor_id, model_match, copy_rate, sscl_percentage, vat_percentage,
               effective_from, effective_to, is_active
        FROM {$T_RATE}
        WHERE vendor_id = ?
          AND is_active = 1
          AND (effective_from IS NULL OR effective_from <= ?)
          AND (effective_to   IS NULL OR effective_to   >= ?)
        ORDER BY rate_profile_id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $vendor_id, $as_of_date, $as_of_date);
    $stmt->execute();
    $res = $stmt->get_result();

    $profiles = [];
    while ($r = $res->fetch_assoc()) $profiles[] = $r;
    $stmt->close();

    if (count($profiles) === 0) {
        json_out(['success' => false, 'message' => 'No active rate profiles found for this vendor on this date.'], 404);
    }

    $best = resolveBestProfile($profiles, $modelNorm);

    if (!$best) {
        // No match at all (even default). This happens if you have profiles but all have model_match and none matched,
        // AND you didn't create a blank/default profile.
        json_out([
            'success' => false,
            'message' => 'No matching rate profile found. Add a default profile (empty model_match) or add a matching model_match.'
        ], 404);
    }

    // Calculate sample totals
    $rate = (float)$best['copy_rate'];
    $ssclP= (float)$best['sscl_percentage'];
    $vatP = (float)$best['vat_percentage'];

    $base = $copy_count * $rate;
    $ssclAmt = $base * ($ssclP / 100.0);
    $subTotal = $base + $ssclAmt;
    $vatAmt = $subTotal * ($vatP / 100.0);
    $grand = $subTotal + $vatAmt;

    json_out([
        'success' => true,
        'input' => [
            'vendor_id' => $vendor_id,
            'serial_no' => $serial_no,
            'model_name'=> $model_name,
            'as_of_date'=> $as_of_date,
            'copy_count'=> $copy_count,
            'resolvedFromSerial' => $resolvedFromSerial
        ],
        'profile' => [
            'rate_profile_id' => (int)$best['rate_profile_id'],
            'model_match'     => (string)$best['model_match'],
            'copy_rate'       => (float)$best['copy_rate'],
            'sscl_percentage' => (float)$best['sscl_percentage'],
            'vat_percentage'  => (float)$best['vat_percentage'],
            'effective_from'  => $best['effective_from'],
            'effective_to'    => $best['effective_to'],
            'score'           => (int)($best['_score'] ?? 0)
        ],
        'calc' => [
            'base'       => $base,
            'sscl'       => $ssclAmt,
            'sub_total'  => $subTotal,
            'vat'        => $vatAmt,
            'grand_total'=> $grand
        ],
        'debug' => $DEBUG ? [
            'candidates' => array_map(function($p){
                return [
                    'rate_profile_id' => (int)$p['rate_profile_id'],
                    'model_match' => (string)$p['model_match'],
                    'copy_rate' => (float)$p['copy_rate'],
                    'sscl' => (float)$p['sscl_percentage'],
                    'vat' => (float)$p['vat_percentage'],
                    'from' => $p['effective_from'],
                    'to' => $p['effective_to'],
                ];
            }, $profiles)
        ] : null
    ]);
}

// ------------------------------
// LOAD VENDORS FOR UI
// ------------------------------
$vendors = [];
if ($vendorSource) {
    if ($vendorSource === $T_VEND1) {
        // tbl_admin_vendors: you said "lets use this"
        // vendor_type is enum('WATER','ELECTRICITY','OTHER') in your screenshot.
        // We'll show ALL active vendors (or restrict to OTHER if you want).
        $sql = "SELECT vendor_id, vendor_name, vendor_type FROM {$T_VEND1} WHERE is_active = 1 ORDER BY vendor_name";
        $q = $conn->query($sql);
        while ($r = $q->fetch_assoc()) $vendors[] = $r;
    } else {
        $sql = "SELECT vendor_id, vendor_name FROM {$T_VEND2} WHERE is_active = 1 ORDER BY vendor_name";
        $q = $conn->query($sql);
        while ($r = $q->fetch_assoc()) $vendors[] = $r;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Photocopy — Test Rate Resolve</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
  .content{padding:20px}
  .container{max-width:1100px;margin:0 auto}
  .card{background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
  h5{margin:0 0 16px;color:#0d6efd}
  .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
  .col-12{grid-column:span 12}
  .col-6{grid-column:span 6}
  .col-4{grid-column:span 4}
  .col-3{grid-column:span 3}
  .col-2{grid-column:span 2}
  label{display:block;margin-bottom:6px;font-weight:600}
  input,select{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px;font-size:14px}
  .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
  .btn-primary{background:#0d6efd;color:#fff}
  .btn-secondary{background:#6c757d;color:#fff}
  .btn:disabled{opacity:.6;cursor:not-allowed}
  .muted{color:#6c757d;font-size:.92rem}
  .result{margin-top:14px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa;padding:12px;display:none}
  .alert{padding:.65rem 1rem;border-radius:8px;margin:8px 0}
  .alert-success{background:#e8f5e9;color:#1b5e20}
  .alert-danger{background:#ffebee;color:#b71c1c}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  table{border-collapse:collapse;width:100%;margin-top:10px}
  th,td{border:1px solid #e5e7eb;padding:8px;font-size:14px}
  th{background:#f1f5f9;text-align:left}
  td.num{text-align:right}
</style>
</head>
<body>
<div class="content">
  <div class="container">
    <div class="card">
      <h5>Photocopy — Test Rate Resolve</h5>

      <div class="muted">
        Enter <b>Vendor + Model</b> OR just <b>Serial</b> (if serial is in machine master).<br>
        This page resolves the best rate profile and shows per-row tax calculation.
        <?php if ($DEBUG): ?>
          <br><b>DEBUG ON</b> (remove <code>?debug=1</code> to hide debug).
        <?php endif; ?>
      </div>

      <hr style="border:none;border-top:1px solid #e5e7eb;margin:14px 0">

      <div class="grid">
        <div class="col-6">
          <label>Vendor</label>
          <select id="vendor_id">
            <option value="">-- Select Vendor --</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= (int)$v['vendor_id'] ?>">
                <?= htmlspecialchars($v['vendor_name'] ?? '') ?>
                <?= isset($v['vendor_type']) ? " (" . htmlspecialchars($v['vendor_type']) . ")" : "" ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="muted">Vendor source: <?= htmlspecialchars($vendorSource ?: 'NOT FOUND') ?></div>
        </div>

        <div class="col-6">
          <label>As Of Date</label>
          <input type="date" id="as_of_date" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
          <div class="muted">Rate profile effective dates are checked against this date.</div>
        </div>

        <div class="col-6">
          <label>Serial No (optional)</label>
          <input type="text" id="serial_no" placeholder="e.g. 85023275">
          <div class="muted">
            If serial exists in <code><?= htmlspecialchars($T_MAC) ?></code>, model + vendor can be auto-resolved.
          </div>
        </div>

        <div class="col-6">
          <label>Model Name (optional)</label>
          <input type="text" id="model_name" placeholder="e.g. ATM-MXM6050">
          <div class="muted">If you use patterns in model_match (like <code>%MXM6050%</code>) they’ll match too.</div>
        </div>

        <div class="col-3">
          <label>Copy Count (for calc)</label>
          <input type="number" id="copy_count" value="0" min="0" step="1">
        </div>

        <div class="col-12">
          <div class="row">
            <button class="btn btn-primary" id="btnResolve">Resolve Rate</button>
            <button class="btn btn-secondary" id="btnClear">Clear</button>
          </div>
        </div>
      </div>

      <div id="result" class="result"></div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function(){
  const $result = $('#result');

  function esc(s){
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }
  function show(html){
    $result.html(html).show();
  }
  function hide(){
    $result.hide().html('');
  }

  $('#btnClear').on('click', function(){
    $('#vendor_id').val('');
    $('#serial_no').val('');
    $('#model_name').val('');
    $('#copy_count').val('0');
    $('#as_of_date').val(new Date().toISOString().slice(0,10));
    hide();
  });

  $('#btnResolve').on('click', function(){
    hide();

    const payload = {
      action: 'resolve',
      vendor_id: $('#vendor_id').val(),
      serial_no: $('#serial_no').val().trim(),
      model_name: $('#model_name').val().trim(),
      as_of_date: $('#as_of_date').val(),
      copy_count: $('#copy_count').val()
    };

    $(this).prop('disabled', true);

    $.ajax({
      url: 'photocopy-rate-test.php<?= $DEBUG ? "?debug=1" : "" ?>',
      type: 'POST',
      data: payload,
      dataType: 'json'
    }).done(function(resp){
      if(!resp || !resp.success){
        show("<div class='alert alert-danger'><b>❌ " + esc(resp && resp.message ? resp.message : "Failed") + "</b></div>");
        return;
      }

      const p = resp.profile;
      const c = resp.calc;
      const inp = resp.input;

      let html = "";
      html += "<div class='alert alert-success'><b>✅ Rate resolved.</b></div>";

      html += "<table>";
      html += "<tr><th colspan='2'>Resolved Input</th></tr>";
      html += "<tr><td>Vendor ID</td><td class='num'>" + esc(inp.vendor_id) + "</td></tr>";
      html += "<tr><td>Serial No</td><td>" + esc(inp.serial_no) + (inp.resolvedFromSerial ? " <span class='muted'>(used to resolve)</span>" : "") + "</td></tr>";
      html += "<tr><td>Model Name</td><td>" + esc(inp.model_name) + "</td></tr>";
      html += "<tr><td>As Of Date</td><td>" + esc(inp.as_of_date) + "</td></tr>";
      html += "<tr><td>Copy Count</td><td class='num'>" + esc(inp.copy_count) + "</td></tr>";
      html += "</table>";

      html += "<table>";
      html += "<tr><th colspan='2'>Selected Rate Profile</th></tr>";
      html += "<tr><td>Rate Profile ID</td><td class='num'>" + esc(p.rate_profile_id) + "</td></tr>";
      html += "<tr><td>Model Match</td><td>" + esc(p.model_match) + "</td></tr>";
      html += "<tr><td>Copy Rate</td><td class='num'>" + esc(p.copy_rate) + "</td></tr>";
      html += "<tr><td>SSCL %</td><td class='num'>" + esc(p.sscl_percentage) + "</td></tr>";
      html += "<tr><td>VAT %</td><td class='num'>" + esc(p.vat_percentage) + "</td></tr>";
      html += "<tr><td>Effective From</td><td>" + esc(p.effective_from) + "</td></tr>";
      html += "<tr><td>Effective To</td><td>" + esc(p.effective_to) + "</td></tr>";
      html += "<tr><td>Match Score</td><td class='num'>" + esc(p.score) + "</td></tr>";
      html += "</table>";

      html += "<table>";
      html += "<tr><th colspan='2'>Row-wise Calculation (Sample)</th></tr>";
      html += "<tr><td>Base (copies × rate)</td><td class='num'>" + esc(Number(c.base).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})) + "</td></tr>";
      html += "<tr><td>SSCL Amount</td><td class='num'>" + esc(Number(c.sscl).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})) + "</td></tr>";
      html += "<tr><td>Sub Total (Base + SSCL)</td><td class='num'>" + esc(Number(c.sub_total).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})) + "</td></tr>";
      html += "<tr><td>VAT Amount</td><td class='num'>" + esc(Number(c.vat).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})) + "</td></tr>";
      html += "<tr><td><b>Grand Total</b></td><td class='num'><b>" + esc(Number(c.grand_total).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})) + "</b></td></tr>";
      html += "</table>";

      <?php if ($DEBUG): ?>
      if(resp.debug && resp.debug.candidates){
        html += "<table>";
        html += "<tr><th colspan='7'>DEBUG: Candidate Profiles</th></tr>";
        html += "<tr><th>ID</th><th>Model Match</th><th class='num'>Rate</th><th class='num'>SSCL</th><th class='num'>VAT</th><th>From</th><th>To</th></tr>";
        resp.debug.candidates.forEach(function(x){
          html += "<tr>";
          html += "<td class='num'>"+esc(x.rate_profile_id)+"</td>";
          html += "<td>"+esc(x.model_match)+"</td>";
          html += "<td class='num'>"+esc(x.copy_rate)+"</td>";
          html += "<td class='num'>"+esc(x.sscl)+"</td>";
          html += "<td class='num'>"+esc(x.vat)+"</td>";
          html += "<td>"+esc(x.from)+"</td>";
          html += "<td>"+esc(x.to)+"</td>";
          html += "</tr>";
        });
        html += "</table>";
      }
      <?php endif; ?>

      show(html);

    }).fail(function(xhr){
      show("<div class='alert alert-danger'><b>❌ Server error.</b><br>" + esc(xhr.responseText || '') + "</div>");
    }).always(() => {
      $('#btnResolve').prop('disabled', false);
    });
  });
})();
</script>
</body>
</html>
