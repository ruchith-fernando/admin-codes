<?php
// special-notes-fetch.php  — shows BOTH inbox and sent
session_start();
if (!isset($_SESSION['hris']) || empty($_SESSION['hris'])) { echo '<div class="alert alert-warning">Not logged in.</div>'; exit; }
$me = $_SESSION['hris'];

require_once 'connections/connection.php'; // must define $conn (mysqli)
mysqli_set_charset($conn, 'utf8');

function e($str){ return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

/* ───────────────────────────
   CONFIG: user directory table
   ─────────────────────────── */
$USER_DIRECTORY_TABLE = 'tbl_admin_users'; // <- change if needed
$USER_HRIS_COL        = 'hris';            // <- change if needed
$USER_NAME_COL        = 'name';            // <- change if needed

// Filters
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_safe = mysqli_real_escape_string($conn, $search);
$search_clause_inbox = '';
$search_clause_sent  = '';
if ($search !== '') {
  $search_clause_inbox = " AND (
      m.category   LIKE '%$search_safe%' OR
      m.record_key LIKE '%$search_safe%' OR
      m.sr_number  LIKE '%$search_safe%' OR
      m.comment    LIKE '%$search_safe%'
    )";
  $search_clause_sent = $search_clause_inbox; // same filters, alias m exists in both
}

// Pagination
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = 5;
$offset   = ($page - 1) * $pageSize;

$meEsc = mysqli_real_escape_string($conn, $me);

/* UNION (inbox + sent) as a derived table so we can count + paginate together */
$unionSQL = "
  (SELECT 
      m.id,
      m.category,
      m.record_key,
      m.sr_number,
      m.comment,
      m.commented_at,
      m.origin_page,
      r.is_read,
      r.read_at,
      m.sender_hris,
      'inbox' AS scope
   FROM tbl_admin_remarks_recipients r
   JOIN tbl_admin_remarks m ON m.id = r.remark_id
   WHERE r.recipient_hris = '$meEsc' $search_clause_inbox)

  UNION ALL

  (SELECT
      m.id,
      m.category,
      m.record_key,
      m.sr_number,
      m.comment,
      m.commented_at,
      m.origin_page,
      NULL AS is_read,
      NULL AS read_at,
      m.sender_hris,
      'sent' AS scope
   FROM tbl_admin_remarks m
   WHERE m.hris_id = '$meEsc' $search_clause_sent)
";

/* Count */
$sqlCount = "SELECT COUNT(*) AS total FROM ($unionSQL) AS X";
$resCount = mysqli_query($conn, $sqlCount);
$rowCount = $resCount ? mysqli_fetch_assoc($resCount) : ['total'=>0];
$total    = (int)($rowCount['total'] ?? 0);
$pages    = max(1, (int)ceil($total / $pageSize));

/* Fetch page (into array so we can post-process) */
$sqlPage = "
  SELECT * FROM ($unionSQL) AS X
  ORDER BY commented_at DESC, id DESC
  LIMIT $offset, $pageSize
";
$result = mysqli_query($conn, $sqlPage);

$rows = [];
$senders = [];
if ($result) {
  while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    if (!empty($r['sender_hris'])) $senders[$r['sender_hris']] = true;
  }
}

/* Lookup sender names (HRIS → Name) */
$nameMap = [];
if (!empty($senders)) {
  // Build safe IN list
  $inParts = [];
  foreach (array_keys($senders) as $hr) {
    $inParts[] = "'" . mysqli_real_escape_string($conn, $hr) . "'";
  }
  $inList = implode(',', $inParts);

  // Try to fetch names; ignore errors if table/cols differ
  $sqlNames = "
    SELECT {$USER_HRIS_COL} AS hris, {$USER_NAME_COL} AS name
    FROM {$USER_DIRECTORY_TABLE}
    WHERE {$USER_HRIS_COL} IN ($inList)
  ";
  if ($resNames = mysqli_query($conn, $sqlNames)) {
    while ($u = mysqli_fetch_assoc($resNames)) {
      $nameMap[$u['hris']] = $u['name'];
    }
    mysqli_free_result($resNames);
  }
}
?>

<?php if ($total === 0): ?>
  <div class="alert alert-info mb-0">No notes found.</div>
<?php else: ?>
  <style>
  /* wrap all table cells */
  .table td, .table th {
    white-space: normal !important;
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 300px;
    vertical-align: top;
  }
  /* keep truncated preview */
  .truncate {
    max-height: 4.5em; /* ~3 lines */
    overflow: hidden;
  }
  </style>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 180px;">Date</th>
          <th>Scope</th>
          <th>Report Name</th>
          <th>Record Reference</th>
          <!-- <th>SR</th> -->
          <th>Comment</th>
          <th>Status / From</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
            $scope = $row['scope']; // 'inbox' or 'sent'
            $badge = '';
            if ($scope === 'inbox') {
              $badge = ($row['is_read'] ?? 'no') === 'yes'
                ? '<span class="badge bg-secondary">Read</span>'
                : '<span class="badge bg-warning text-dark">Unread</span>';
            } else {
              $badge = '<span class="badge bg-info text-dark">Sent</span>';
            }
            $sender_hris = $row['sender_hris'] ?? '';
            $sender_name = $sender_hris !== '' ? ($nameMap[$sender_hris] ?? '') : '';
          ?>
          <tr class="note-row clickable"
              data-id="<?php echo (int)$row['id']; ?>"
              data-scope="<?php echo e($scope); ?>">
            <td><span class="text-muted"><?php echo e($row['commented_at']); ?></span></td>
            <td><?php echo ($scope === 'inbox' ? 'Inbox' : 'Sent'); ?></td>
            <td><?php echo e($row['category']); ?></td>
            <td><?php echo e($row['record_key']); ?></td>

            <td>
              <?php
                $comment = $row['comment'] ?? '';
                $isLong  = strlen($comment) > 220;
              ?>
              <div class="truncate"><?php echo nl2br(e($comment)); ?></div>
              <?php if ($isLong): ?>
                <a href="#" class="small mt-1 d-inline-block js-readmore">Read more</a>
                <div class="d-none full-text mt-2"><?php echo nl2br(e($comment)); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php echo $badge; ?>
              <?php if ($sender_hris !== ''): ?>
                <div class="small text-muted mt-1">
                  From: <?php echo e($sender_hris); ?><?php echo $sender_name !== '' ? ' — '.e($sender_name) : ''; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <nav aria-label="Notes pagination">
    <ul class="pagination justify-content-end">
      <?php
        $base = 'special-notes-fetch.php?';
        if ($search !== '') { $base .= 'q='.urlencode($search).'&'; }
      ?>
      <li class="page-item <?php echo $page<=1?'disabled':''; ?>">
        <a class="page-link" href="<?php echo $base.'page='.max(1,$page-1); ?>" tabindex="-1">Previous</a>
      </li>
      <?php
        $start = max(1, $page - 3);
        $end   = min($pages, $page + 3);
        if ($start > 1) {
          echo '<li class="page-item"><a class="page-link" href="'.$base.'page=1">1</a></li>';
          if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($p = $start; $p <= $end; $p++) {
          $active = $p == $page ? ' active' : '';
          echo '<li class="page-item'.$active.'"><a class="page-link" href="'.$base.'page='.$p.'">'.$p.'</a></li>';
        }
        if ($end < $pages) {
          if ($end < $pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
          echo '<li class="page-item"><a class="page-link" href="'.$base.'page='.$pages.'">'.$pages.'</a></li>';
        }
      ?>
      <li class="page-item <?php echo $page>=$pages?'disabled':''; ?>">
        <a class="page-link" href="<?php echo $base.'page='.min($pages,$page+1); ?>">Next</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<style>
.truncate{ display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
.note-row.highlight{ background:#fff9e6; }
</style>
