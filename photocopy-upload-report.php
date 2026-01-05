<?php
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

$batchId = (int)($_GET['batch_id'] ?? 0);
if ($batchId <= 0) die("Invalid batch_id.");

$stmt = $conn->prepare("SELECT batch_id, original_filename, file_name, uploaded_by, month_applicable,
                               total_rows, inserted_rows, updated_rows, error_rows, created_at
                        FROM tbl_admin_photocopy_upload_batches WHERE batch_id=? LIMIT 1");
$stmt->bind_param("i", $batchId);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
if (!$batch) die("Batch not found.");

$uploadedBy = trim((string)$batch['uploaded_by']);
if ($uploadedBy !== '' && $uploadedBy !== $userId) die("No access to this batch.");

// report file location (must match your upload script)
$reportDir  = __DIR__ . "/tmp_photocopy_reports";
$reportFile = $reportDir . "/photocopy_batch_{$batchId}_report.csv";
if (!is_file($reportFile)) {
    die("Report file not found on server for this batch.");
}

$filter = strtolower(trim((string)($_GET['filter'] ?? 'failed'))); // failed|all|success
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(500, max(25, (int)($_GET['per_page'] ?? 100)));

// carry search filters from batches screen
$carry = [];
foreach (['q','from','to'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $carry[$k] = trim((string)$_GET[$k]);
}

function url_with($file, array $params = []) {
    $qs = http_build_query($params);
    return $qs ? ($file . '?' . $qs) : $file;
}

function read_report_page($file, $filter, $page, $perPage){
    $fp = fopen($file, "r");
    if (!$fp) return ["total"=>0,"rows"=>[],"headers"=>[]];

    $headers = fgetcsv($fp);
    if (!$headers) { fclose($fp); return ["total"=>0,"rows"=>[],"headers"=>[]]; }

    $idx = array_flip($headers);
    $statusKey = isset($idx['status']) ? $idx['status'] : null;

    $want = function($row) use ($filter, $statusKey){
        if ($statusKey === null) return true;
        $status = strtoupper(trim((string)($row[$statusKey] ?? '')));
        if ($filter === 'all') return true;
        if ($filter === 'failed') return ($status === 'FAILED');
        if ($filter === 'success') return ($status !== 'FAILED');
        return true;
    };

    $start = ($page-1) * $perPage;
    $end   = $start + $perPage;

    $rows = [];
    $total = 0;

    while(($r = fgetcsv($fp)) !== false){
        if (!$want($r)) continue;
        if ($total >= $start && $total < $end) $rows[] = $r;
        $total++;
    }
    fclose($fp);
    return ["total"=>$total,"rows"=>$rows,"headers"=>$headers];
}

$data = read_report_page($reportFile, $filter, $page, $perPage);
$total = $data['total'];
$headers = $data['headers'];
$rows = $data['rows'];

$totalPages = max(1, (int)ceil($total / $perPage));

// links
$backUrl = url_with('photocopy-upload-batches.php', $carry);

$baseSelf = array_merge($carry, ['batch_id'=>$batchId, 'per_page'=>$perPage]);
$failedUrl  = url_with('photocopy-upload-report.php', array_merge($baseSelf, ['filter'=>'failed','page'=>1]));
$successUrl = url_with('photocopy-upload-report.php', array_merge($baseSelf, ['filter'=>'success','page'=>1]));
$allUrl     = url_with('photocopy-upload-report.php', array_merge($baseSelf, ['filter'=>'all','page'=>1]));

$prevUrl = url_with('photocopy-upload-report.php', array_merge($baseSelf, ['filter'=>$filter,'page'=>max(1,$page-1)]));
$nextUrl = url_with('photocopy-upload-report.php', array_merge($baseSelf, ['filter'=>$filter,'page'=>min($totalPages,$page+1)]));

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// columns that should wrap
$wrapHeaders = ['error_message','csv_branch_location'];
?>
<?php if(!$isAjax): ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Photocopy — Upload Report</title>
<?php endif; ?>

<style>
  /* keep wrapping readable inside bootstrap table */
  td.wrap-cell { white-space: normal !important; }
</style>

<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <div class="card-body">

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <h4 class="text-primary mb-1">Photocopy — Upload Report (Batch #<?=h($batchId)?>)</h4>
            <div class="text-muted">
              File: <b><?=h($batch['original_filename'] ?: $batch['file_name'])?></b> |
              Month: <b><?=h($batch['month_applicable'])?></b> |
              Created: <b><?=h($batch['created_at'])?></b>
            </div>
            <div class="mt-2 d-flex flex-wrap gap-2">
              <span class="badge bg-success">Inserted <?=h($batch['inserted_rows'])?></span>
              <span class="badge bg-success">Updated <?=h($batch['updated_rows'])?></span>
              <span class="badge <?=((int)$batch['error_rows']>0?'bg-danger':'bg-success')?>">Errors <?=h($batch['error_rows'])?></span>
            </div>
          </div>

          <div class="d-flex gap-2">
            <!-- ✅ AJAX Back (stays inside main.php) -->
            <a class="btn btn-outline-primary quick-access"
               href="<?=h($backUrl)?>"
               data-page="<?=h($backUrl)?>">⬅ Back to Batches</a>

            <!-- download should be real navigation -->
            <a class="btn btn-outline-secondary"
               href="upload-photocopy-actuals.php?download_report=1&batch_id=<?=h($batchId)?>">⬇ Download CSV</a>
          </div>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <a class="btn <?=($filter==='failed'?'btn-primary':'btn-outline-primary')?> quick-access"
             href="<?=h($failedUrl)?>" data-page="<?=h($failedUrl)?>">Failed Only</a>

          <a class="btn <?=($filter==='success'?'btn-primary':'btn-outline-primary')?> quick-access"
             href="<?=h($successUrl)?>" data-page="<?=h($successUrl)?>">Success Only</a>

          <a class="btn <?=($filter==='all'?'btn-primary':'btn-outline-primary')?> quick-access"
             href="<?=h($allUrl)?>" data-page="<?=h($allUrl)?>">All Rows</a>
        </div>

        <div class="text-muted mt-2">
          Showing <b><?=h(count($rows))?></b> rows out of <b><?=h($total)?></b> (filter: <b><?=h($filter)?></b>)
        </div>

        <?php if(!$headers): ?>
          <div class="mt-3">Report file is empty or invalid.</div>
        <?php else: ?>
          <div class="table-responsive mt-3">
            <table class="table table-bordered table-hover table-sm align-middle">
              <thead class="table-light">
                <tr>
                  <?php foreach($headers as $hh): ?>
                    <th><?=h($hh)?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php if(!$rows): ?>
                  <tr><td colspan="<?=h(count($headers))?>" class="text-muted">No rows for this filter.</td></tr>
                <?php endif; ?>

                <?php foreach($rows as $r): ?>
                  <tr>
                    <?php foreach($headers as $i=>$hh): ?>
                      <?php $wrap = in_array($hh, $wrapHeaders, true); ?>
                      <td class="<?=($wrap?'wrap-cell':'')?>"><?=h($r[$i] ?? '')?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
            <div class="text-muted">
              Page <?=h($page)?> / <?=h($totalPages)?> | Per page: <?=h($perPage)?>
            </div>
            <div class="d-flex gap-2">
              <?php if($page > 1): ?>
                <a class="btn btn-outline-primary quick-access"
                   href="<?=h($prevUrl)?>" data-page="<?=h($prevUrl)?>">⬅ Prev</a>
              <?php endif; ?>
              <?php if($page < $totalPages): ?>
                <a class="btn btn-outline-primary quick-access"
                   href="<?=h($nextUrl)?>" data-page="<?=h($nextUrl)?>">Next ➡</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<!-- ✅ prevent default navigation for quick-access anchors (only runs inside main where jQuery exists) -->
<script>
(function(){
  if (!window.jQuery) return;
  var $ = window.jQuery;

  $(document).off('click.pcopyQA2', 'a.quick-access[data-page]')
    .on('click.pcopyQA2', 'a.quick-access[data-page]', function(e){
      e.preventDefault();
    });
})();
</script>

<?php if(!$isAjax): ?>
</body>
</html>
<?php endif; ?>
