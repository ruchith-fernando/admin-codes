<?php
require_once 'connections/connection.php';

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$search     = trim($_GET['search'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 10;
$offset     = ($page - 1) * $limit;

$rows = [];

/* ======= VEHICLE MAP ======= */
$assignedUsers = [];
$resultAssigned = $conn->query("SELECT vehicle_number, assigned_user FROM tbl_admin_vehicle WHERE status = 'Approved'");
while ($row = $resultAssigned->fetch_assoc()) {
  $assignedUsers[$row['vehicle_number']] = $row['assigned_user'];
}

function inDateRange($date, $start, $end) {
  if (!$date || $date === '0000-00-00') return false;
  $timestamp = strtotime($date);
  $start_ts = $start ? strtotime($start) : null;
  $end_ts   = $end ? strtotime($end . ' +1 day') : null;
  if ($start_ts && $timestamp < $start_ts) return false;
  if ($end_ts && $timestamp >= $end_ts) return false;
  return true;
}

function getDescription($type, $raw = '') {
  return match ($type) {
    'Emission Test'   => 'For Emission Test',
    'Revenue License' => 'For Revenue License',
    'Battery'         => 'For Purchase of Battery',
    'Tire'            => 'Replacement of Tires',
    'AC', 'AC Repair' => 'For Repair of AC',
    'Other'           => $raw ?: 'Other Maintenance Work',
    'Service'         => 'For Service',
    default           => $raw
  };
}

/* ======= LICENSING ======= */
$queryLic = "
SELECT vehicle_number, emission_test_date AS entry_date, emission_test_amount AS amount, 'Emission Test' AS type, person_handled AS person
FROM tbl_admin_vehicle_licensing_insurance
WHERE STATUS='Approved' AND emission_test_date IS NOT NULL AND emission_test_date <> '0000-00-00'
UNION ALL
SELECT vehicle_number, revenue_license_date AS entry_date, revenue_license_amount AS amount, 'Revenue License' AS type, person_handled AS person
FROM tbl_admin_vehicle_licensing_insurance
WHERE STATUS='Approved' AND revenue_license_date IS NOT NULL AND revenue_license_date <> '0000-00-00'";
$result = $conn->query($queryLic);
while ($row = $result->fetch_assoc()) {
  $date = $row['entry_date'];
  if (!inDateRange($date, $start_date, $end_date)) continue;
  $rows[] = [
    'vehicle' => $row['vehicle_number'],
    'date' => $date,
    'type' => $row['type'],
    'desc' => getDescription($row['type']),
    'mileage' => '',
    'meter' => '',
    'amount' => $row['amount'],
    'person' => $row['person'],
    'assigned_user' => $assignedUsers[$row['vehicle_number']] ?? ''
  ];
}

/* ======= MAINTENANCE ======= */
$qMaint = "
SELECT * FROM tbl_admin_vehicle_maintenance
WHERE STATUS='Approved'
AND ((purchase_date IS NOT NULL AND purchase_date <> '0000-00-00') OR (repair_date IS NOT NULL AND repair_date <> '0000-00-00'))";
$result = $conn->query($qMaint);
while ($row = $result->fetch_assoc()) {
  $date = ($row['purchase_date'] && $row['purchase_date'] !== '0000-00-00') ? $row['purchase_date'] : $row['repair_date'];
  if (!inDateRange($date, $start_date, $end_date)) continue;
  $type = $row['maintenance_type'] ?: 'Other';
  $desc = getDescription($type, $row['problem_description']);
  $rows[] = [
    'vehicle' => $row['vehicle_number'],
    'date' => $date,
    'type' => ($type === 'AC') ? 'AC Repair' : $type,
    'desc' => $desc,
    'mileage' => $row['mileage'],
    'meter' => '',
    'amount' => $row['price'],
    'person' => $row['driver_name'],
    'assigned_user' => $assignedUsers[$row['vehicle_number']] ?? ''
  ];
}

/* ======= SERVICE ======= */
$qServ = "
SELECT * FROM tbl_admin_vehicle_service
WHERE STATUS='Approved' AND service_date IS NOT NULL AND service_date <> '0000-00-00'";
$result = $conn->query($qServ);
while ($row = $result->fetch_assoc()) {
  $date = $row['service_date'];
  if (!inDateRange($date, $start_date, $end_date)) continue;
  $rows[] = [
    'vehicle' => $row['vehicle_number'],
    'date' => $date,
    'type' => 'Service',
    'desc' => getDescription('Service'),
    'mileage' => '',
    'meter' => $row['meter_reading'],
    'amount' => $row['amount'],
    'person' => $row['driver_name'],
    'assigned_user' => $assignedUsers[$row['vehicle_number']] ?? ''
  ];
}

/* ======= SORT ======= */
usort($rows, fn($a, $b) => strcmp($a['vehicle'], $b['vehicle']) ?: strcmp($a['date'], $b['date']));

/* ======= SEARCH FILTER ======= */
if ($search !== '') {
  $rows = array_filter($rows, function($r) use ($search) {
    return stripos($r['vehicle'], $search) !== false ||
           stripos($r['assigned_user'], $search) !== false ||
           stripos($r['desc'], $search) !== false ||
           stripos($r['type'], $search) !== false ||
           stripos($r['person'], $search) !== false;
  });
}

/* ======= PAGINATION ======= */
$rowsAll = $rows; // save all filtered rows for grand total
$total = count($rows);
$pages = max(1, ceil($total / $limit));
$rows = array_slice($rows, $offset, $limit);

/* ======= GRAND TOTAL ======= */
$grandTotal = array_sum(array_map(fn($r) => (float)preg_replace('/[^\d.]/', '', $r['amount']), $rowsAll));
?>

<div class="table-responsive">
  <table class="table table-bordered table-striped table-sm align-middle">
    <thead class="table-light text-center">
      <tr>
        <th>#</th>
        <th>Vehicle Number</th>
        <th class="left-align">Assigned User</th>
        <th>Date</th>
        <th>Maintenance Type</th>
        <th class="text-start" style="min-width: 220px;">Description</th>
        <th class="text-start">Mileage / Meter</th>
        <th class="text-end">Amount (Rs)</th>
        <th>Handled By</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="text-center text-muted py-3">No records found.</td></tr>
    <?php else:
      $i = $offset + 1;
      foreach ($rows as $r):
    ?>
      <tr>
        <td class="text-center"><?= $i++ ?></td>
        <td class="text-center"><?= htmlspecialchars($r['vehicle']) ?></td>
        <td class="text-start"><?= htmlspecialchars($r['assigned_user']) ?></td>
        <td class="text-center"><?= date('d-M-Y', strtotime($r['date'])) ?></td>
        <td class="text-start"><?= htmlspecialchars($r['type']) ?></td>
        <td class="text-start text-wrap"><?= htmlspecialchars($r['desc']) ?></td>
        <td class="text-start"><?= htmlspecialchars($r['mileage'] ?: $r['meter']) ?></td>
        <td class="text-end"><?= number_format((float)$r['amount'], 2) ?></td>
        <td class="text-start"><?= htmlspecialchars($r['person']) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr class="fw-bold table-light">
        <td colspan="7" class="text-end">Grand Total</td>
        <td class="text-end"><?= number_format($grandTotal, 2) ?></td>
        <td></td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- ✅ Pagination -->
  <nav>
    <ul class="pagination justify-content-end flex-wrap mb-0">
      <?php
      if ($page > 1) {
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="1">« First</span></li>';
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.($page-1).'">‹ Prev</span></li>';
      }
      for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++) {
        $active = ($i == $page) ? ' active' : '';
        echo '<li class="page-item'.$active.'"><span class="page-link page-btn" data-pg="'.$i.'">'.$i.'</span></li>';
      }
      if ($page < $pages) {
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.($page+1).'">Next ›</span></li>';
        echo '<li class="page-item"><span class="page-link page-btn" data-pg="'.$pages.'">Last »</span></li>';
      }
      ?>
    </ul>
  </nav>
</div>
