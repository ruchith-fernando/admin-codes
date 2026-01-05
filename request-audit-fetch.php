<?php
// request-audit-fetch.php
require_once 'connections/connection.php';

// Robust response
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(E_ALL);
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

mysqli_report(MYSQLI_REPORT_OFF);

$hasConn = (isset($conn) && $conn instanceof mysqli);
function esc($s){ global $conn,$hasConn; $s=(string)$s; return $hasConn?mysqli_real_escape_string($conn,$s):addslashes($s); }
function runq($sql){ global $conn,$hasConn; if(!$hasConn) return false; $rs=mysqli_query($conn,$sql); return $rs?:false; }

if (!$hasConn) {
  echo '<div class="alert alert-danger m-3">Database connection is unavailable. Please check <code>connections/connection.php</code>.</div>';
  exit;
}

// Inputs
$from_date = isset($_POST['from_date']) ? trim($_POST['from_date']) : '';
$to_date   = isset($_POST['to_date'])   ? trim($_POST['to_date'])   : '';
$method    = isset($_POST['method'])    ? trim($_POST['method'])    : '';
$page_like = isset($_POST['page_like']) ? trim($_POST['page_like']) : '';
$q         = isset($_POST['q'])         ? trim($_POST['q'])         : '';
$page      = isset($_POST['page'])      ? max(1,(int)$_POST['page']) : 1;
$per_page  = isset($_POST['per_page'])  ? max(5,(int)$_POST['per_page']) : 25;

$offset = ($page - 1) * $per_page;

// WHERE
$where = " WHERE 1=1 ";
if ($from_date !== '') {
  $from = esc($from_date . " 00:00:00");
  $where .= " AND created_at >= '$from' ";
}
if ($to_date !== '') {
  $to = esc($to_date . " 23:59:59");
  $where .= " AND created_at <= '$to' ";
}
if ($method !== '') {
  $m = esc($method);
  $where .= " AND method = '$m' ";
}
if ($page_like !== '') {
  $pl = esc($page_like);
  $where .= " AND page_name LIKE '%$pl%' ";
}
if ($q !== '') {
  $qq = esc($q);
  $where .= " AND (
      username    LIKE '%$qq%' OR
      hris        LIKE '%$qq%' OR
      ip_address  LIKE '%$qq%' OR
      request_uri LIKE '%$qq%' OR
      user_agent  LIKE '%$qq%' OR
      referer     LIKE '%$qq%' OR
      page_name   LIKE '%$qq%'
    ) ";
}

// Totals
$total = 0;
$sql_total = "SELECT COUNT(*) AS c FROM tbl_admin_request_audit $where";
if ($rs = runq($sql_total)) {
  if ($row = mysqli_fetch_assoc($rs)) $total = (int)$row['c'];
} else {
  echo '<div class="alert alert-danger m-3">Query failed: <code>Total</code><br><small>'.htmlspecialchars(mysqli_error($conn)).'</small></div>';
}

// Summary tiles
$sum7=0; $cu=0; $cp=0;

$sql_7d = "SELECT COUNT(*) AS c7 FROM tbl_admin_request_audit $where AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
if ($rs = runq($sql_7d)) {
  if ($r = mysqli_fetch_assoc($rs)) $sum7 = (int)$r['c7'];
} else {
  echo '<div class="alert alert-warning m-3">Query failed: <code>Last 7 Days</code> — proceeding…<br><small>'.htmlspecialchars(mysqli_error($conn)).'</small></div>';
}

$sql_uniq_users = "SELECT COUNT(DISTINCT COALESCE(NULLIF(username,''), CONCAT('HRIS:',NULLIF(hris,'')))) AS cu FROM tbl_admin_request_audit $where";
if ($rs = runq($sql_uniq_users)) { if ($r = mysqli_fetch_assoc($rs)) $cu = (int)$r['cu']; }

$sql_uniq_pages = "SELECT COUNT(DISTINCT page_name) AS cp FROM tbl_admin_request_audit $where";
if ($rs = runq($sql_uniq_pages)) { if ($r = mysqli_fetch_assoc($rs)) $cp = (int)$r['cp']; }

// Top methods
$meth_rs = runq("
  SELECT COALESCE(method,'') AS m, COUNT(*) AS cnt
  FROM tbl_admin_request_audit $where
  GROUP BY COALESCE(method,'')
  ORDER BY cnt DESC
  LIMIT 6
");

// Page data
$list_rs = runq("
  SELECT id, username, hris, page_name, request_uri, method, ip_address, ip_source, xff_chain, user_agent, referer, created_at
  FROM tbl_admin_request_audit
  $where
  ORDER BY created_at DESC, id DESC
  LIMIT $offset, $per_page
");

$total_pages  = max(1, (int)ceil($total / $per_page));
$current_from = $total ? ($offset + 1) : 0;
$current_to   = min($offset + $per_page, $total);
$per_opts     = [10,25,50,100];
?>
<div class="mb-3">
  <div class="row g-3">
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Total Requests</div>
        <div class="fs-4 fw-semibold"><?php echo number_format($total); ?></div>
        <div class="small-muted">Filtered count</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Last 7 Days</div>
        <div class="fs-4 fw-semibold"><?php echo number_format($sum7); ?></div>
        <div class="small-muted">Within current filters</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Unique Users</div>
        <div class="fs-4 fw-semibold"><?php echo number_format($cu); ?></div>
        <div class="small-muted">Username/HRIS</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Unique Pages</div>
        <div class="fs-4 fw-semibold"><?php echo number_format($cp); ?></div>
        <div class="small-muted">Distinct page_name</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card border-0">
      <div class="card-body p-0">
        <h6 class="text-primary mb-3">Top Methods</h6>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr>
                <th>Method</th>
                <th class="text-end">Count</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($meth_rs && mysqli_num_rows($meth_rs)>0): ?>
              <?php while($r=mysqli_fetch_assoc($meth_rs)): ?>
                <tr>
                  <td><?php echo $r['m']===''?'<span class="text-muted">Unknown</span>':htmlspecialchars($r['m']); ?></td>
                  <td class="text-end"><?php echo number_format($r['cnt']); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="2" class="text-muted">No data</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="alert alert-info mb-0">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          Showing <b><?php echo number_format($current_from); ?>–<?php echo number_format($current_to); ?></b> of <b><?php echo number_format($total); ?></b>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="small mb-0">Rows per page</label>
          <select id="per_page" class="form-select form-select-sm" style="width:100px">
            <?php foreach($per_opts as $opt): ?>
              <option value="<?php echo $opt; ?>" <?php echo $per_page==$opt?'selected':''; ?>><?php echo $opt; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th style="width: 90px;">ID</th>
        <th style="width: 165px;">Created At</th>
        <th>Method</th>
        <th>Page</th>
        <th>URI</th>
        <th>User</th>
        <th>IP</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($list_rs && mysqli_num_rows($list_rs)>0): ?>
      <?php while($row=mysqli_fetch_assoc($list_rs)):
        $uri = (string)($row['request_uri'] ?? '');
        $userdisp = trim(($row['username']??'')!=='' ? $row['username'] : (($row['hris']??'')!=='' ? 'HRIS: '.$row['hris'] : ''));
      ?>
      <tr>
        <td><?php echo (int)$row['id']; ?></td>
        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
        <td><span class="badge rounded-pill bg-secondary"><?php echo htmlspecialchars($row['method']??''); ?></span></td>
        <td class="wrap-anywhere"><?php echo htmlspecialchars($row['page_name']??''); ?></td>
        <td class="wrap-anywhere"><?php echo nl2br(htmlspecialchars($uri)); ?></td>
        <td class="wrap-anywhere"><?php echo htmlspecialchars($userdisp); ?></td>
        <td>
          <div><?php echo htmlspecialchars($row['ip_address']??''); ?></div>
          <div class="small-muted"><?php echo htmlspecialchars($row['ip_source']??''); ?></div>
        </td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="7" class="text-center text-muted py-4">No requests found for selected filters.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-end">
    <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
      <a href="javascript:void(0)" class="page-link" data-page="<?php echo $page-1; ?>">Previous</a>
    </li>
    <?php
      $start = max(1, $page - 2);
      $end   = min($total_pages, $page + 2);

      if ($start > 1) {
        echo '<li class="page-item"><a href="javascript:void(0)" class="page-link" data-page="1">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }

      for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active' : '';
        echo '<li class="page-item '.$active.'"><a href="javascript:void(0)" class="page-link" data-page="'.$i.'">'.$i.'</a></li>';
      }

      if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        echo '<li class="page-item"><a href="javascript:void(0)" class="page-link" data-page="'.$total_pages.'">'.$total_pages.'</a></li>';
      }
    ?>
    <li class="page-item <?php echo $page>=$total_pages?'disabled':''; ?>">
      <a href="javascript:void(0)" class="page-link" data-page="<?php echo $page+1; ?>">Next</a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<!-- Echo current page (optional debug) -->
<span id="_cur_page" data-cur="<?php echo (int)$page; ?>" style="display:none"></span>
