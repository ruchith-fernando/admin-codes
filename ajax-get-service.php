<?php
// ajax-get-service.php
require_once 'connections/connection.php';
$conn->set_charset('utf8mb4');

$vid   = intval($_GET['vehicle_id'] ?? 0);
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

// Step 1: get the vehicle number from the vehicle id.
// (Service table stores vehicle_number, so we translate id -> number first.)
$stmtVehicle = $conn->prepare("SELECT vehicle_number FROM tbl_admin_vehicle WHERE id=?");
$stmtVehicle->bind_param("i", $vid);
$stmtVehicle->execute();
$vehicleRes = $stmtVehicle->get_result();

if (!$vehicleRes || $vehicleRes->num_rows === 0) {
  echo '<div class="alert alert-warning">Vehicle not found.</div>';
  exit;
}

$vnum = $vehicleRes->fetch_assoc()['vehicle_number'];

// Step 2: count approved service records so we can build pagination.
$stmtCount = $conn->prepare("
  SELECT COUNT(*)
  FROM tbl_admin_vehicle_service
  WHERE vehicle_number=? AND status='Approved'
");
$stmtCount->bind_param("s", $vnum);
$stmtCount->execute();
$total = (int)($stmtCount->get_result()->fetch_row()[0] ?? 0);

$pages = max(1, (int)ceil($total / $limit));

// Step 3: fetch just one page of approved service records (newest first).
$stmtList = $conn->prepare("
  SELECT *
  FROM tbl_admin_vehicle_service
  WHERE vehicle_number=? AND status='Approved'
  ORDER BY created_at DESC
  LIMIT ? OFFSET ?
");
$stmtList->bind_param("sii", $vnum, $limit, $offset);
$stmtList->execute();
$res = $stmtList->get_result();

if (!$res || $res->num_rows === 0) {
  echo '<div class="alert alert-warning mb-0">No service records found.</div>';
  exit;
}

// Render the table wrapper once, then fill rows.
echo '
<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle font-size">
    <thead class="table-light">
      <tr>
        <th>Service Date</th>
        <th>Service Center</th>
        <th>Previous Meter</th>
        <th>Next Service</th>
        <th>Amount</th>
        <th>Driver</th>
        <th>Images</th>
      </tr>
    </thead>
    <tbody>
';

while ($row = $res->fetch_assoc()) {

  // Build thumbnail list (supports JSON array or a single stored path).
  $imgsHtml = '';

  if (!empty($row['image_path'])) {
    $arr = json_decode($row['image_path'], true);
    $arr = is_array($arr) ? $arr : [$row['image_path']];

    foreach ($arr as $path) {
      $safe = htmlspecialchars((string)$path);
      $ext  = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));

      // We show real thumbnails for images, and a PDF icon for pdf attachments.
      if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $imgsHtml .= "<img src='{$safe}' data-file='{$safe}' class='img-thumbnail preview-file' style='max-width:60px;margin:2px;cursor:pointer'>";
      } elseif ($ext === 'pdf') {
        $imgsHtml .= "<img src='assets/pdf-icon.png' data-file='{$safe}' class='img-thumbnail preview-file' style='max-width:60px;margin:2px;cursor:pointer'>";
      }
    }
  }

  if ($imgsHtml === '') {
    $imgsHtml = '<span class="text-muted">No Images</span>';
  }

  // Some older rows may store a zero-date; treat that as “blank”.
  $serviceDate = ($row['service_date'] ?? '') !== '0000-00-00'
    ? htmlspecialchars((string)$row['service_date'])
    : '';

  $shopName   = htmlspecialchars((string)($row['shop_name'] ?? ''));
  $meter      = htmlspecialchars((string)($row['meter_reading'] ?? '')) . ' km';
  $nextMeter  = htmlspecialchars((string)($row['next_service_meter'] ?? '')) . ' km';
  $amount     = 'Rs. ' . number_format((float)($row['amount'] ?? 0), 2);
  $driverName = htmlspecialchars((string)($row['driver_name'] ?? ''));

  echo "
    <tr>
      <td>{$serviceDate}</td>
      <td>{$shopName}</td>
      <td>{$meter}</td>
      <td>{$nextMeter}</td>
      <td>{$amount}</td>
      <td>{$driverName}</td>
      <td>{$imgsHtml}</td>
    </tr>
  ";
}

echo '
    </tbody>
  </table>
';

// Pagination UI (show a small window around current page).
$window = 2;
$start = max(1, $page - $window);
$end   = min($pages, $page + $window);

echo '
  <nav>
    <ul class="pagination justify-content-end flex-wrap mb-0">
';

if ($page > 1) {
  echo '
      <li class="page-item">
        <span class="page-link page-btn" data-type="service" data-pg="1">« First</span>
      </li>
      <li class="page-item">
        <span class="page-link page-btn" data-type="service" data-pg="' . ($page - 1) . '">‹ Prev</span>
      </li>
  ';
}

if ($start > 1) {
  echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
}

for ($i = $start; $i <= $end; $i++) {
  $active = ($i === $page) ? ' active' : '';
  echo '
      <li class="page-item' . $active . '">
        <span class="page-link page-btn" data-type="service" data-pg="' . $i . '">' . $i . '</span>
      </li>
  ';
}

if ($end < $pages) {
  echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
}

if ($page < $pages) {
  echo '
      <li class="page-item">
        <span class="page-link page-btn" data-type="service" data-pg="' . ($page + 1) . '">Next ›</span>
      </li>
      <li class="page-item">
        <span class="page-link page-btn" data-type="service" data-pg="' . $pages . '">Last »</span>
      </li>
  ';
}

echo '
    </ul>
  </nav>
</div>
';
?>
