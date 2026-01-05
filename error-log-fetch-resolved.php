<?php
require_once 'connections/connection.php';
$page = max(1, (int)($_POST['page'] ?? 1));
$per  = 10;
$off  = ($page - 1) * $per;

$total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM tbl_admin_errors WHERE is_resolved = 1"))['c'];

$rs = mysqli_query($conn, "
  SELECT id, created_at, error_type, file, line, error_message, user_info, resolved_by, resolved_at
  FROM tbl_admin_errors
  WHERE is_resolved = 1
  ORDER BY resolved_at DESC, id DESC
  LIMIT $off, $per
");

$pages = max(1, ceil($total / $per));
$start = $total ? $off + 1 : 0;
$end   = min($off + $per, $total);
?>

<div class="table-responsive" style="max-height:600px;overflow-y:auto;">
  <table class="table table-bordered table-striped align-middle table-hover">
    <thead class="table-success sticky-top">
      <tr>
        <th>ID</th><th>Resolved At</th><th>By</th><th>Type</th><th>File:Line</th>
        <th>Message</th><th>User</th>
      </tr>
    </thead>
    <tbody>
      <?php if (mysqli_num_rows($rs) > 0): ?>
        <?php while ($r = mysqli_fetch_assoc($rs)): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['resolved_at']) ?></td>
            <td><?= htmlspecialchars($r['resolved_by']) ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($r['error_type']) ?></span></td>
            <td><?= htmlspecialchars($r['file'] . ':' . $r['line']) ?></td>
            <td class="wrap-anywhere"><?= htmlspecialchars(mb_substr($r['error_message'], 0, 160)) ?></td>
            <td><?= htmlspecialchars($r['user_info']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No resolved errors found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-end flex-wrap mb-0">
      <?php
        $win = 2;
        $start = max(1, $page - $win);
        $end = min($pages, $page + $win);

        if ($page > 1) {
          echo '<li class="page-item"><span class="page-link page-btn-resolved" data-pg="1">« First</span></li>';
          echo '<li class="page-item"><span class="page-link page-btn-resolved" data-pg="' . ($page - 1) . '">‹ Prev</span></li>';
        }

        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

        for ($i = $start; $i <= $end; $i++) {
          $active = $i == $page ? ' active' : '';
          echo '<li class="page-item' . $active . '"><span class="page-link page-btn-resolved" data-pg="' . $i . '">' . $i . '</span></li>';
        }

        if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';

        if ($page < $pages) {
          echo '<li class="page-item"><span class="page-link page-btn-resolved" data-pg="' . ($page + 1) . '">Next ›</span></li>';
          echo '<li class="page-item"><span class="page-link page-btn-resolved" data-pg="' . $pages . '">Last »</span></li>';
        }
      ?>
    </ul>
  </nav>
<?php endif; ?>
