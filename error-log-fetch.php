<?php
// error-log-fetch.php
require_once 'connections/connection.php';

/**
 * Helper to run safe queries
 */
function safe_query($conn, $sql) {
    $r = mysqli_query($conn, $sql);
    if (!$r) {
        http_response_code(500);
        echo "<div class='alert alert-danger'>SQL Error: " . htmlspecialchars(mysqli_error($conn)) . "</div>";
        exit;
    }
    return $r;
}

// === Input filters ===
$from      = $_POST['from_date']       ?? '';
$to        = $_POST['to_date']         ?? '';
$etype     = $_POST['error_type']      ?? '';
$file      = $_POST['file_like']       ?? '';
$q         = $_POST['q']               ?? '';
$page      = max(1, (int)($_POST['page'] ?? 1));
$per       = 10; // fixed 10 rows per page
$off       = ($page - 1) * $per;

// === Base WHERE (Pending only) ===
$w = " WHERE is_resolved = 0 ";

if ($from) {
    $w .= " AND created_at >= '" . mysqli_real_escape_string($conn, $from) . " 00:00:00'";
}
if ($to) {
    $w .= " AND created_at <= '" . mysqli_real_escape_string($conn, $to) . " 23:59:59'";
}
if ($etype) {
    $w .= " AND error_type = '" . mysqli_real_escape_string($conn, $etype) . "'";
}
if ($file) {
    $w .= " AND file LIKE '%" . mysqli_real_escape_string($conn, $file) . "%'";
}
if ($q) {
    $qq = mysqli_real_escape_string($conn, $q);
    $w .= " AND (error_message LIKE '%$qq%' 
             OR user_info LIKE '%$qq%' 
             OR ip_address LIKE '%$qq%' 
             OR file LIKE '%$qq%')";
}

// === Summary stats ===
$total_pending = (int)mysqli_fetch_assoc(safe_query($conn, "SELECT COUNT(*) c FROM tbl_admin_errors $w"))['c'];
$total_resolved = (int)mysqli_fetch_assoc(safe_query($conn, "SELECT COUNT(*) c FROM tbl_admin_errors WHERE is_resolved = 1"))['c'];
$files  = (int)mysqli_fetch_assoc(safe_query($conn, "SELECT COUNT(DISTINCT file) c FROM tbl_admin_errors $w"))['c'];
$users  = (int)mysqli_fetch_assoc(safe_query($conn, "SELECT COUNT(DISTINCT user_info) c FROM tbl_admin_errors $w"))['c'];

$types  = safe_query($conn, "
    SELECT COALESCE(error_type,'') et, COUNT(*) cnt 
    FROM tbl_admin_errors $w 
    GROUP BY et 
    ORDER BY cnt DESC 
    LIMIT 5
");

$list = safe_query($conn, "
    SELECT * FROM tbl_admin_errors 
    $w 
    ORDER BY created_at DESC, id DESC 
    LIMIT $off, $per
");

$pages = max(1, ceil($total_pending / $per));
$start = $total_pending ? ($off + 1) : 0;
$end   = min($off + $per, $total_pending);
?>

<!-- === Summary Header === -->
<div class="mb-3">
  <div class="row g-3">
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Pending Errors</div>
        <div class="fs-4 fw-semibold"><?= number_format($total_pending) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Resolved Errors</div>
        <div class="fs-4 fw-semibold text-success"><?= number_format($total_resolved) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Unique Files</div>
        <div class="fs-4 fw-semibold"><?= number_format($files) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3">
        <div class="small text-muted">Unique Users</div>
        <div class="fs-4 fw-semibold"><?= number_format($users) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- === Tabs === -->
<ul class="nav nav-tabs mb-3" id="errorTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-pending" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending Errors</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-resolved" data-bs-toggle="tab" data-bs-target="#resolved" type="button" role="tab">Resolved Errors (<?= number_format($total_resolved) ?>)</button>
  </li>
</ul>

<div class="tab-content">
  <!-- === Pending Errors === -->
  <div class="tab-pane fade show active" id="pending" role="tabpanel">
    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
      <table class="table table-bordered table-striped align-middle table-hover">
        <thead class="table-light sticky-top">
          <tr>
            <th>ID</th>
            <th>Created</th>
            <th>Type</th>
            <th>File:Line</th>
            <th>Message</th>
            <th>User</th>
            <th>IP</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (mysqli_num_rows($list) > 0): ?>
            <?php while ($r = mysqli_fetch_assoc($list)): ?>
              <tr>
                <td><?= htmlspecialchars($r['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($r['error_type'] ?? '') ?></span></td>
                <td class="wrap-anywhere"><?= htmlspecialchars(($r['file'] ?? '') . ':' . ($r['line'] ?? '')) ?></td>
                <td class="wrap-anywhere"><?= htmlspecialchars(mb_substr($r['error_message'] ?? '', 0, 160)) ?></td>
                <td><?= htmlspecialchars($r['user_info'] ?? '') ?></td>
                <td>
                  <?= htmlspecialchars($r['ip_address'] ?? '') ?>
                  <div class="small-muted"><?= htmlspecialchars($r['ip_source'] ?? '') ?></div>
                </td>
                <td class="text-center">
                  <button
                    class="btn btn-sm btn-outline-primary btn-view"
                    data-id="<?= htmlspecialchars($r['id'] ?? '') ?>"
                    data-created="<?= htmlspecialchars($r['created_at'] ?? '') ?>"
                    data-type="<?= htmlspecialchars($r['error_type'] ?? '') ?>"
                    data-file="<?= htmlspecialchars($r['file'] ?? '') ?>"
                    data-line="<?= htmlspecialchars($r['line'] ?? '') ?>"
                    data-message="<?= htmlspecialchars($r['error_message'] ?? '', ENT_QUOTES) ?>"
                    data-user="<?= htmlspecialchars($r['user_info'] ?? '') ?>"
                    data-ip="<?= htmlspecialchars($r['ip_address'] ?? '') ?>"
                    data-ipsource="<?= htmlspecialchars($r['ip_source'] ?? '') ?>"
                    data-ipchain="<?= htmlspecialchars($r['ip_chain'] ?? '') ?>">
                    View
                  </button>

                  <button
                    class="btn btn-sm btn-success btn-mark-done"
                    data-id="<?= htmlspecialchars($r['id'] ?? '') ?>"
                    data-file="<?= htmlspecialchars($r['file'] ?? '') ?>"
                    data-line="<?= htmlspecialchars($r['line'] ?? '') ?>"
                    data-message="<?= htmlspecialchars($r['error_message'] ?? '') ?>">
                    Mark Done
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No pending errors</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-end flex-wrap mb-0">
          <?php
            $win   = 2;
            $start = max(1, $page - $win);
            $end   = min($pages, $page + $win);

            if ($page > 1) {
              echo '<li class="page-item"><span class="page-link page-btn" data-pg="1">« First</span></li>';
              echo '<li class="page-item"><span class="page-link page-btn" data-pg="' . ($page - 1) . '">‹ Prev</span></li>';
            }

            if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

            for ($i = $start; $i <= $end; $i++) {
              $active = $i == $page ? ' active' : '';
              echo '<li class="page-item' . $active . '"><span class="page-link page-btn" data-pg="' . $i . '">' . $i . '</span></li>';
            }

            if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

            if ($page < $pages) {
              echo '<li class="page-item"><span class="page-link page-btn" data-pg="' . ($page + 1) . '">Next ›</span></li>';
              echo '<li class="page-item"><span class="page-link page-btn" data-pg="' . $pages . '">Last »</span></li>';
            }
          ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>

  <!-- === Resolved Errors (loaded dynamically) === -->
  <div class="tab-pane fade" id="resolved" role="tabpanel">
    <div id="resolvedContent" class="text-center text-muted py-4">
      Click this tab to load resolved errors...
    </div>
  </div>
</div>

<span id="_cur_page" data-cur="<?= htmlspecialchars($page ?? '') ?>" style="display:none"></span>

<script>
// === Load Resolved Tab on Demand ===
$('#tab-resolved').one('click', function() {
  $('#resolvedContent').html('Loading...');
  $.post('error-log-fetch-resolved.php', {}, function(html) {
    $('#resolvedContent').html(html);
  }).fail(() => {
    $('#resolvedContent').html('<div class="alert alert-danger">Failed to load resolved errors.</div>');
  });
});
</script>
