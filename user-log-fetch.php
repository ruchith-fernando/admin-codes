<?php
// user-log-fetch.php
require_once 'connections/connection.php';
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(E_ALL);
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

$from_date = $_POST['from_date'] ?? '';
$to_date   = $_POST['to_date'] ?? '';
$page_like = $_POST['page_like'] ?? '';
$q         = $_POST['q'] ?? '';
$page      = max(1, (int)($_POST['page'] ?? 1));
$per_page  = max(5, (int)($_POST['per_page'] ?? 25));
$offset    = ($page - 1) * $per_page;

$where = " WHERE 1=1 ";
if ($from_date) $where .= " AND created_at >= '".$conn->real_escape_string($from_date)." 00:00:00' ";
if ($to_date)   $where .= " AND created_at <= '".$conn->real_escape_string($to_date)." 23:59:59' ";
if ($page_like) $where .= " AND page LIKE '%".$conn->real_escape_string($page_like)."%' ";
if ($q) {
  $qq = $conn->real_escape_string($q);
  $where .= " AND (
    log_uid LIKE '%$qq%' OR
    user LIKE '%$qq%' OR
    hris LIKE '%$qq%' OR
    ip_address LIKE '%$qq%' OR
    ip_source LIKE '%$qq%' OR
    action LIKE '%$qq%' OR
    page LIKE '%$qq%'
  ) ";
}

// ✅ Combined query (live + archive) ordered numerically by log_uid DESC
$sql_union = "
  SELECT log_uid, user, hris, action, page, ip_address, ip_source, user_agent, created_at, 'live' AS source
  FROM tbl_admin_user_logs
  $where
  UNION ALL
  SELECT log_uid, user, hris, action, page, ip_address, ip_source, user_agent, created_at, 'archive' AS source
  FROM tbl_admin_user_logs_archive
  $where
  ORDER BY CAST(SUBSTRING_INDEX(log_uid, '-', -1) AS UNSIGNED) DESC
  LIMIT $offset, $per_page
";

// ✅ Count total rows
$count_union = "
  SELECT SUM(c) AS total FROM (
    SELECT COUNT(*) AS c FROM tbl_admin_user_logs $where
    UNION ALL
    SELECT COUNT(*) AS c FROM tbl_admin_user_logs_archive $where
  ) t
";

$total_rows = 0;
if ($res = $conn->query($count_union)) {
  $total_rows = (int)($res->fetch_assoc()['total'] ?? 0);
}
$total_pages = max(1, ceil($total_rows / $per_page));

$res = $conn->query($sql_union);
?>
<div class="alert alert-info mb-3">
  Showing <b><?= $offset+1 ?></b>–<b><?= min($offset+$per_page,$total_rows) ?></b> of <b><?= $total_rows ?></b> total
  <div class="float-end">
    <label class="small">Rows per page</label>
    <select id="per_page" class="form-select form-select-sm d-inline-block" style="width:80px">
      <?php foreach([10,25,50,100] as $opt): ?>
        <option value="<?= $opt ?>" <?= $opt==$per_page?'selected':'' ?>><?= $opt ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>Log&nbsp;UID</th>
        <th>Date</th>
        <th>User</th>
        <th>HRIS</th>
        <th>Action</th>
        <th>Page</th>
        <th>IP</th>
        <th>Source</th>
      </tr>
    </thead>
    <tbody>
      <?php if($res && $res->num_rows): ?>
        <?php while($r=$res->fetch_assoc()): ?>
        <tr>
          <td><code><?= htmlspecialchars($r['log_uid'] ?? '') ?></code></td>
          <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['user'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['hris'] ?? '') ?></td>
          <td class="wrap-anywhere"><?= nl2br(htmlspecialchars($r['action'] ?? '')) ?></td>
          <td><?= htmlspecialchars($r['page'] ?? '') ?></td>
          <td>
            <div><?= htmlspecialchars($r['ip_address'] ?? '') ?></div>
            <div class="text-muted small"><?= htmlspecialchars($r['ip_source'] ?? '') ?></div>
          </td>
          <td><span class="badge bg-<?= ($r['source'] ?? '')=='live'?'success':'secondary' ?>">
            <?= htmlspecialchars($r['source'] ?? '') ?>
          </span></td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No results found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if($total_pages>1): ?>
<nav>
  <ul class="pagination justify-content-end flex-wrap mb-0">
    <?php
      $win = 2;
      $start = max(1, $page - $win);
      $end = min($total_pages, $page + $win);

      if ($page > 1) {
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="1">« First</span></li>';
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.($page-1).'">‹ Prev</span></li>';
      }

      if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      for ($i = $start; $i <= $end; $i++) {
        $active = $i == $page ? ' active' : '';
        echo '<li class="page-item'.$active.'"><span class="page-link page-btn" data-pg="'.$i.'">'.$i.'</span></li>';
      }
      if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

      if ($page < $total_pages) {
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.($page+1).'">Next ›</span></li>';
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.$total_pages.'">Last »</span></li>';
      }
    ?>
  </ul>
</nav>
<?php endif; ?>
