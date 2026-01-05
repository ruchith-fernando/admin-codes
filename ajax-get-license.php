<?php
// ajax-get-license.php
require_once 'connections/connection.php';
$conn->set_charset('utf8mb4');

$vid = intval($_GET['vehicle_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

// 🔹 Helper function for safe escaping
function esc($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

// 🔹 Fetch vehicle number
$s = $conn->prepare("SELECT vehicle_number FROM tbl_admin_vehicle WHERE id=?");
$s->bind_param("i", $vid);
$s->execute();
$r = $s->get_result();

if (!$r || $r->num_rows === 0) {
    echo '<div class="alert alert-warning">Vehicle not found.</div>';
    exit;
}

$vnum = $r->fetch_assoc()['vehicle_number'];

// 🔹 Count total records
$c = $conn->prepare("
    SELECT COUNT(*)
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE vehicle_number=? AND status='Approved'
");
$c->bind_param("s", $vnum);
$c->execute();
$total = $c->get_result()->fetch_row()[0];
$pages = max(1, ceil($total / $limit));

// 🔹 Fetch paginated data
$q = $conn->prepare("
    SELECT *
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE vehicle_number=? AND status='Approved'
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$q->bind_param("sii", $vnum, $limit, $offset);
$q->execute();
$res = $q->get_result();

if ($res->num_rows === 0) {
    echo '<div class="alert alert-warning mb-0">No license/insurance records found.</div>';
    exit;
}

// 🔹 Build table
echo '
<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle font-size">
    <thead class="table-light">
      <tr>
        <th>Revenue License Date</th>
        <th>License Amount</th>
        <th>Insurance Amount</th>
        <th>Emission Test Date</th>
        <th>Emission Test Amount</th>
        <th>Handled By</th>
      </tr>
    </thead>
    <tbody>
';

while ($r = $res->fetch_assoc()) {

    $revenueDate  = ($r['revenue_license_date'] != '0000-00-00')
        ? esc($r['revenue_license_date']) : '';

    $emissionDate = ($r['emission_test_date'] != '0000-00-00')
        ? esc($r['emission_test_date']) : '';

    $licenseAmt   = number_format((float)$r['revenue_license_amount'], 2);
    $insureAmt    = number_format((float)$r['insurance_amount'], 2);
    $emissionAmt  = number_format((float)$r['emission_test_amount'], 2);
    $handledBy    = esc($r['person_handled']);

    echo "
      <tr>
        <td>$revenueDate</td>
        <td>Rs. $licenseAmt</td>
        <td>Rs. $insureAmt</td>
        <td>$emissionDate</td>
        <td>Rs. $emissionAmt</td>
        <td>$handledBy</td>
      </tr>
    ";
}

echo '
    </tbody>
  </table>
';

// 🔹 Pagination
$w = 2;
$s = max(1, $page - $w);
$e = min($pages, $page + $w);

echo '
<nav>
  <ul class="pagination justify-content-end flex-wrap mb-0">
';

if ($page > 1) {
    echo '
      <li class="page-item">
        <span class="page-link page-btn" data-type="license" data-pg="1">« First</span>
      </li>
      <li class="page-item">
        <span class="page-link page-btn" data-type="license" data-pg="' . ($page - 1) . '">‹ Prev</span>
      </li>
    ';
}

if ($s > 1) {
    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
}

for ($i = $s; $i <= $e; $i++) {
    $a = $i == $page ? ' active' : '';
    echo '
      <li class="page-item' . $a . '">
        <span class="page-link page-btn" data-type="license" data-pg="' . $i . '">' . $i . '</span>
      </li>
    ';
}

if ($e < $pages) {
    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
}

if ($page < $pages) {
    echo '
      <li class="page-item">
        <span class="page-link page-btn" data-type="license" data-pg="' . ($page + 1) . '">Next ›</span>
      </li>
      <li class="page-item">
        <span class="page-link page-btn" data-type="license" data-pg="' . $pages . '">Last »</span>
      </li>
    ';
}

echo '
  </ul>
</nav>
</div>
';
?>
