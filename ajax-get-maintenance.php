<?php
// ajax-get-maintenance.php
require_once 'connections/connection.php';
$conn->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$vid = intval($_GET['vehicle_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

// 🔹 Get vehicle number
$stmt = $conn->prepare("SELECT vehicle_number FROM tbl_admin_vehicle WHERE id=?");
$stmt->bind_param("i", $vid);
$stmt->execute();
$r = $stmt->get_result();

if (!$r || $r->num_rows === 0) {
    echo '<div class="alert alert-warning">Vehicle not found.</div>';
    exit;
}
$vnum = $r->fetch_assoc()['vehicle_number'];

// 🔹 Count total maintenance records
$c = $conn->prepare("
    SELECT COUNT(*)
    FROM tbl_admin_vehicle_maintenance
    WHERE vehicle_number=? AND status='Approved'
");
$c->bind_param("s", $vnum);
$c->execute();
$total = $c->get_result()->fetch_row()[0];
$pages = max(1, ceil($total / $limit));

// 🔹 Fetch paginated maintenance records
$q = $conn->prepare("
    SELECT *
    FROM tbl_admin_vehicle_maintenance
    WHERE vehicle_number=? AND status='Approved'
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$q->bind_param("sii", $vnum, $limit, $offset);
$q->execute();
$res = $q->get_result();

if ($res->num_rows === 0) {
    echo '<div class="alert alert-warning mb-0">No maintenance records found.</div>';
    exit;
}

// 🔹 Build table
echo '
<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle font-size">
    <thead class="table-light">
      <tr>
        <th>Purchase Date</th>
        <th>Repair Date</th>
        <th>Type</th>
        <th style="width: 300px;">Problem</th>
        <th>Mileage</th>
        <th>Driver</th>
        <th>Shop</th>
        <th>Price</th>
        <th>Images</th>
      </tr>
    </thead>
    <tbody>
';

while ($r = $res->fetch_assoc()) {
    // 🔸 Handle images
    $imgs = '';

    if ($r['image_path']) {
    $arr = json_decode($r['image_path'], true);
    $arr = is_array($arr) ? $arr : [$r['image_path']];

    foreach ($arr as $i) {
        $safe = htmlspecialchars($i);
        $ext = strtolower(pathinfo($i, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg','jpeg','png'])) {
            $imgs .= "<img src='$safe' data-file='$safe' class='img-thumbnail preview-file' style='max-width:60px;margin:2px;cursor:pointer'>";
        } elseif ($ext === 'pdf') {
            $imgs .= "<img src='assets/pdf-icon.png' data-file='$safe' class='img-thumbnail preview-file' style='max-width:60px;margin:2px;cursor:pointer'>";
        }
    }
}

if ($imgs == '') {
    $imgs = '<span class="text-muted">No Images</span>';
}


    $purchaseDate = ($r['purchase_date'] != '0000-00-00') ? htmlspecialchars($r['purchase_date']) : '';
    $repairDate   = ($r['repair_date'] != '0000-00-00') ? htmlspecialchars($r['repair_date']) : '';

    echo "
      <tr>
        <td>$purchaseDate</td>
        <td>$repairDate</td>
        <td>" . htmlspecialchars($r['maintenance_type']) . "</td>
        <td class='text-wrap' style='max-width: 300px; white-space: normal;'>" . nl2br(htmlspecialchars($r['problem_description'])) . "</td>
        <td>" . htmlspecialchars($r['mileage']) . " km</td>
        <td>" . htmlspecialchars($r['driver_name']) . "</td>
        <td>" . htmlspecialchars($r['shop_name']) . "</td>
        <td>Rs. " . number_format((float)$r['price'], 2) . "</td>
        <td>$imgs</td>
      </tr>
    ";
}

echo '
    </tbody>
  </table>
';

// 🔹 Pagination
$win = 2;
$s = max(1, $page - $win);
$e = min($pages, $page + $win);

echo '
<nav>
  <ul class="pagination justify-content-end flex-wrap mb-0">
';

if ($page > 1) {
    echo '
      <li class="page-item">
        <span class="page-link page-btn" data-type="maintenance" data-pg="1">« First</span>
      </li>
      <li class="page-item">
        <span class="page-link page-btn" data-type="maintenance" data-pg="' . ($page - 1) . '">‹ Prev</span>
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
        <span class="page-link page-btn" data-type="maintenance" data-pg="' . $i . '">' . $i . '</span>
      </li>
    ';
}

if ($e < $pages) {
    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
}

if ($page < $pages) {
    echo '
      <li class="page-item">
        <span class="page-link page-btn" data-type="maintenance" data-pg="' . ($page + 1) . '">Next ›</span>
      </li>
      <li class="page-item">
        <span class="page-link page-btn" data-type="maintenance" data-pg="' . $pages . '">Last »</span>
      </li>
    ';
}

echo '
  </ul>
</nav>
</div>
';
?>
