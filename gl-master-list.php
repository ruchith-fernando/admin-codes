<?php
// gl-master-list.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db() {
  global $conn, $con, $mysqli;
  if (isset($conn) && $conn instanceof mysqli) return $conn;
  if (isset($con) && $con instanceof mysqli) return $con;
  if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
  return null;
}

$mysqli = db();
if (!$mysqli) { http_response_code(500); echo '<div class="alert alert-danger">DB connection not found.</div>'; exit; }

$q = trim($_POST['q'] ?? '');
$page = (int)($_POST['page'] ?? 1);
$perPage = (int)($_POST['per_page'] ?? 10);

if ($page < 1) $page = 1;
if ($perPage < 1) $perPage = 10;
if ($perPage > 50) $perPage = 50; // safety

$offset = ($page - 1) * $perPage;

$where = "";
$params = [];
$types = "";

if ($q !== "") {
  $where = " WHERE gl_code LIKE ? OR gl_name LIKE ? ";
  $like = "%".$q."%";
  $params = [$like, $like];
  $types = "ss";
}

// count total
$sqlCount = "SELECT COUNT(*) AS cnt FROM tbl_admin_gl_account" . $where;
$stCount = $mysqli->prepare($sqlCount);
if ($q !== "") $stCount->bind_param($types, ...$params);
$stCount->execute();
$total = (int)($stCount->get_result()->fetch_assoc()['cnt'] ?? 0);

$totalPages = (int)ceil($total / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) { $page = $totalPages; $offset = ($page-1)*$perPage; }

// fetch page
$sql = "SELECT gl_id, gl_code, gl_name, gl_note, created_at
        FROM tbl_admin_gl_account
        $where
        ORDER BY gl_code ASC
        LIMIT ? OFFSET ?";
$st = $mysqli->prepare($sql);

if ($q !== "") {
  // add limit/offset
  $types2 = $types . "ii";
  $st->bind_param($types2, ...$params, $perPage, $offset);
} else {
  $st->bind_param("ii", $perPage, $offset);
}

$st->execute();
$res = $st->get_result();

// header
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<div class="text-muted small">Showing <b>'.($total===0?0:($offset+1)).'</b> to <b>'.min($offset+$perPage,$total).'</b> of <b>'.$total.'</b></div>';
echo '<div class="text-muted small">Page <b>'.$page.'</b> / <b>'.$totalPages.'</b></div>';
echo '</div>';

if ($total === 0) {
  echo '<div class="alert alert-warning mb-0">No GL records found.</div>';
  exit;
}

// table
echo '<div class="table-responsive">';
echo '<table class="table table-sm table-bordered align-middle mb-2">';
echo '<thead class="table-light"><tr>
        <th style="width:120px;">GL Code</th>
        <th>GL Name</th>
        <th>Note</th>
        <th style="width:160px;">Created</th>
      </tr></thead><tbody>';

while ($r = $res->fetch_assoc()) {
  $code = htmlspecialchars($r['gl_code']);
  $name = htmlspecialchars($r['gl_name']);
  $note = htmlspecialchars($r['gl_note'] ?? '');
  $created = htmlspecialchars($r['created_at'] ?? '');
  echo "<tr>
          <td><b>{$code}</b></td>
          <td>{$name}</td>
          <td>{$note}</td>
          <td>{$created}</td>
        </tr>";
}
echo '</tbody></table>';
echo '</div>';

// pagination window
$window = 2; // show +/-2 around current
$start = max(1, $page - $window);
$end = min($totalPages, $page + $window);

// ensure first/last presence
echo '<nav aria-label="GL pagination">';
echo '<ul class="pagination pagination-sm mb-0 justify-content-end">';

$prev = max(1, $page - 1);
$next = min($totalPages, $page + 1);

$disabledPrev = ($page <= 1) ? ' disabled' : '';
$disabledNext = ($page >= $totalPages) ? ' disabled' : '';

echo '<li class="page-item'.$disabledPrev.'"><a class="page-link gl-page-link" href="#" data-page="'.$prev.'">Prev</a></li>';

if ($start > 1) {
  echo '<li class="page-item"><a class="page-link gl-page-link" href="#" data-page="1">1</a></li>';
  if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
}

for ($p = $start; $p <= $end; $p++) {
  $active = ($p === $page) ? ' active' : '';
  echo '<li class="page-item'.$active.'"><a class="page-link gl-page-link" href="#" data-page="'.$p.'">'.$p.'</a></li>';
}

if ($end < $totalPages) {
  if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
  echo '<li class="page-item"><a class="page-link gl-page-link" href="#" data-page="'.$totalPages.'">'.$totalPages.'</a></li>';
}

echo '<li class="page-item'.$disabledNext.'"><a class="page-link gl-page-link" href="#" data-page="'.$next.'">Next</a></li>';
echo '</ul></nav>';
