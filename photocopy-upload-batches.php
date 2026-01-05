<?php
// photocopy-upload-batches.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/connections/connection.php";

function current_user_id(){
    foreach (['user_id','userid','userId','admin_id','emp_id','uid','id'] as $k) {
        if (isset($_SESSION[$k]) && $_SESSION[$k] !== '') return (string)$_SESSION[$k];
    }
    return '';
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($conn) || !($conn instanceof mysqli)) { die("DB connection missing."); }

$userId = current_user_id();
if ($userId === '') { die("Not logged in."); }

// Filters
$q = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

// Carry these back/forth so Back keeps search filters
$carry = [];
if ($q   !== '') $carry['q'] = $q;
if ($from!== '') $carry['from'] = $from;
if ($to  !== '') $carry['to'] = $to;

function url_with($file, array $params = []) {
    $qs = http_build_query($params);
    return $qs ? ($file . '?' . $qs) : $file;
}

// Build query
$sql = "SELECT batch_id, file_name, original_filename, month_applicable, uploaded_by,
               total_rows, inserted_rows, updated_rows, error_rows, created_at
        FROM tbl_admin_photocopy_upload_batches
        WHERE 1=1 ";

$params = [];
$types = "";

// Only my uploads (as you had)
$sql .= " AND (uploaded_by = ? OR uploaded_by IS NULL OR uploaded_by = '') ";
$params[] = $userId;
$types .= "s";

if ($q !== '') {
    $sql .= " AND (batch_id = ? OR file_name LIKE ? OR original_filename LIKE ?) ";
    $qid = ctype_digit($q) ? (int)$q : 0;
    $params[] = $qid;
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $types .= "iss";
}

if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $sql .= " AND created_at >= ? ";
    $params[] = $from . " 00:00:00";
    $types .= "s";
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $sql .= " AND created_at <= ? ";
    $params[] = $to . " 23:59:59";
    $types .= "s";
}

$sql .= " ORDER BY batch_id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: ".$conn->error);

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while($r = $res->fetch_assoc()) $rows[] = $r;

// If loaded via your main.php AJAX loader, jQuery exists and we should NOT do full-page navigation.
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
?>
<?php if(!$isAjax): ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Photocopy — Upload Reports</title>
<?php endif; ?>

<style>
  .cell-file { max-width: 520px; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">

        <h4 class="text-primary mb-4">Photocopy — Upload Reports</h4>

        <form id="pcopyBatchForm" method="get" action="photocopy-upload-batches.php" class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label">Search (Batch ID / filename)</label>
            <input class="form-control" name="q" value="<?=h($q)?>" placeholder="e.g. 13 or November.csv">
          </div>

          <div class="col-md-3">
            <label class="form-label">From (created date)</label>
            <input class="form-control" type="date" name="from" value="<?=h($from)?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">To (created date)</label>
            <input class="form-control" type="date" name="to" value="<?=h($to)?>">
          </div>

          <div class="col-md-1 d-flex gap-2">
            <button class="btn btn-primary w-100" type="submit">Load</button>

            <?php $resetUrl = "photocopy-upload-batches.php"; ?>
            <a class="btn btn-light w-100 quick-access"
               href="<?=h($resetUrl)?>"
               data-page="<?=h($resetUrl)?>">Reset</a>
          </div>
        </form>

        <div class="text-muted mt-2">
          Tip: This screen lets you open any old report anytime — independent of the upload page.
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Batch ID</th>
                <th>Month</th>
                <th>File</th>
                <th>Total</th>
                <th>Inserted</th>
                <th>Updated</th>
                <th>Errors</th>
                <th>Created</th>
                <th style="width:1%">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(!$rows): ?>
                <tr><td colspan="9" class="text-muted">No batches found.</td></tr>
              <?php endif; ?>

              <?php foreach($rows as $r): ?>
                <?php
                  $err = (int)$r['error_rows'];
                  $viewUrl = url_with('photocopy-upload-report.php', array_merge($carry, ['batch_id'=>$r['batch_id']]));
                ?>
                <tr>
                  <td class="fw-bold"><?=h($r['batch_id'])?></td>
                  <td class="text-nowrap"><?=h($r['month_applicable'])?></td>
                  <td class="cell-file text-break">
                    <?=h($r['original_filename'] ?: $r['file_name'])?>
                  </td>
                  <td><?=h($r['total_rows'])?></td>
                  <td><?=h($r['inserted_rows'])?></td>
                  <td><?=h($r['updated_rows'])?></td>
                  <td>
                    <span class="badge rounded-pill <?=($err>0?'bg-danger':'bg-success')?>"><?=h($err)?></span>
                  </td>
                  <td class="text-nowrap"><?=h($r['created_at'])?></td>
                  <td>
                    <div class="d-flex gap-2">
                      <!-- ✅ AJAX-IN-MAIN: does NOT navigate away -->
                      <a class="btn btn-sm btn-outline-primary quick-access"
                         href="<?=h($viewUrl)?>"
                         data-page="<?=h($viewUrl)?>">View</a>

                      <!-- keep as real link (download should work normally) -->
                      <a class="btn btn-sm btn-outline-secondary"
                         href="upload-photocopy-actuals.php?download_report=1&batch_id=<?=h($r['batch_id'])?>">Download CSV</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ✅ Make quick-access anchors not navigate away (ONLY when inside main, where jQuery exists) -->
<script>
(function(){
  if (!window.jQuery) return;
  var $ = window.jQuery;

  // prevent browser navigation for our ajax links
  $(document).off('click.pcopyQA', 'a.quick-access[data-page]')
    .on('click.pcopyQA', 'a.quick-access[data-page]', function(e){
      e.preventDefault();
    });

  // intercept search form submit to load inside main.php
  $(document).off('submit.pcopyBatch', '#pcopyBatchForm')
    .on('submit.pcopyBatch', '#pcopyBatchForm', function(e){
      if (!document.getElementById('contentArea')) return; // not in main
      e.preventDefault();

      var qs = $(this).serialize();
      var url = (this.action || 'photocopy-upload-batches.php') + (qs ? ('?' + qs) : '');

      // trigger main.php's existing .quick-access loader
      var $tmp = $('<a href="#" class="quick-access" data-page=""></a>');
      $tmp.attr('data-page', url);
      $('body').append($tmp);
      $tmp.trigger('click');
      $tmp.remove();
    });
})();
</script>

<?php if(!$isAjax): ?>
</body>
</html>
<?php endif; ?>
