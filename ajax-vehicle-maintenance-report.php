<?php
require_once 'connections/connection.php';

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$rows = [];

// Build vehicle_number → assigned_user map
$assignedUsers = [];
$resultAssigned = $conn->query("SELECT vehicle_number, assigned_user FROM tbl_admin_vehicle WHERE status = 'Approved'");
while ($row = $resultAssigned->fetch_assoc()) {
    $assignedUsers[$row['vehicle_number']] = $row['assigned_user'];
}

/* -------------------------------------------------------
   FIXED: Smart date range check
--------------------------------------------------------*/
function inDateRange($date, $start, $end) {
    if (!$date || $date === '0000-00-00') return false;

    $timestamp = strtotime($date);
    $start_ts = $start ? strtotime($start) : null;
    $end_ts   = $end ? strtotime($end . ' +1 day') : null; // inclusive end date

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

/* -------------------------------------------------------
   LICENSING & INSURANCE
--------------------------------------------------------*/
$queryLic = "
    SELECT vehicle_number, emission_test_date AS entry_date, emission_test_amount AS amount, 
           'Emission Test' AS type, person_handled AS person
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE STATUS = 'Approved' AND emission_test_date IS NOT NULL AND emission_test_date <> '0000-00-00'
    UNION ALL
    SELECT vehicle_number, revenue_license_date AS entry_date, revenue_license_amount AS amount, 
           'Revenue License' AS type, person_handled AS person
    FROM tbl_admin_vehicle_licensing_insurance
    WHERE STATUS = 'Approved' AND revenue_license_date IS NOT NULL AND revenue_license_date <> '0000-00-00'
";
$result = $conn->query($queryLic);
while ($row = $result->fetch_assoc()) {
    $date = $row['entry_date'];
    if (!inDateRange($date, $start_date, $end_date)) continue;

    $type = $row['type'];
    $rows[] = [
        'vehicle' => $row['vehicle_number'],
        'date' => $date,
        'type' => $type,
        'desc' => getDescription($type),
        'mileage' => '',
        'meter' => '',
        'amount' => $row['amount'],
        'person' => $row['person'],
        'assigned_user' => $assignedUsers[$row['vehicle_number']] ?? ''
    ];
}

/* -------------------------------------------------------
   MAINTENANCE
--------------------------------------------------------*/
$queryMaint = "
    SELECT * FROM tbl_admin_vehicle_maintenance
    WHERE STATUS = 'Approved'
      AND (
          (purchase_date IS NOT NULL AND purchase_date <> '0000-00-00')
          OR (repair_date IS NOT NULL AND repair_date <> '0000-00-00')
      )
";
$result = $conn->query($queryMaint);
while ($row = $result->fetch_assoc()) {
    $date = ($row['purchase_date'] && $row['purchase_date'] !== '0000-00-00')
        ? $row['purchase_date']
        : $row['repair_date'];

    if (!inDateRange($date, $start_date, $end_date)) continue;

    $type = $row['maintenance_type'] ?: 'Other';
    $desc = getDescription($type, $row['problem_description']);
    $type_display = ($type === 'AC') ? 'AC Repair' : $type;

    $rows[] = [
        'vehicle' => $row['vehicle_number'],
        'date' => $date,
        'type' => $type_display,
        'desc' => $desc,
        'mileage' => $row['mileage'],
        'meter' => '',
        'amount' => $row['price'],
        'person' => $row['driver_name'],
        'assigned_user' => $assignedUsers[$row['vehicle_number']] ?? ''
    ];
}

/* -------------------------------------------------------
   SERVICE
--------------------------------------------------------*/
$queryServ = "
    SELECT * FROM tbl_admin_vehicle_service
    WHERE STATUS = 'Approved'
      AND service_date IS NOT NULL AND service_date <> '0000-00-00'
";
$result = $conn->query($queryServ);
while ($row = $result->fetch_assoc()) {
    $date = $row['service_date'];
    if (!inDateRange($date, $start_date, $end_date)) continue;

    $type = 'Service';
    $rows[] = [
        'vehicle' => $row['vehicle_number'],
        'date' => $date,
        'type' => $type,
        'desc' => getDescription($type),
        'mileage' => '',
        'meter' => $row['meter_reading'],
        'amount' => $row['amount'],
        'person' => $row['driver_name'],
        'assigned_user' => $assignedUsers[$row['vehicle_number']] ?? ''
    ];
}

/* -------------------------------------------------------
   SORT BY VEHICLE + DATE
--------------------------------------------------------*/
usort($rows, function($a, $b) {
    $cmp = strcmp($a['vehicle'], $b['vehicle']);
    if ($cmp === 0) return strcmp($a['date'], $b['date']);
    return $cmp;
});
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<div class="table-responsive">
    <table class="table table-bordered table-striped table-sm align-middle">
        <thead class="table-light text-center">
            <tr>
                <th>#</th>
                <th>Vehicle Number</th>
                <th class="left-align">Assigned User</th>
                <th>Date</th>
                <th class="left-align">Maintenance Type</th>
                <th class="text-start" style="min-width: 220px;">Replacement / Repair<br>Problem Description</th>
                <th class="text-start" style="min-width: 120px;">Mileage /<br>Meter Reading</th>
                <th class="text-end">Amount (Rs)</th>
                <th class="text-start">Handled By</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1; $total = 0;
            if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-3">No records found for the selected date range.</td></tr>
            <?php else:
            foreach ($rows as $r):
                $total += (float)preg_replace('/[^\d.]/', '', $r['amount']);
            ?>
            <tr>
                <td class="text-center"><?= $i++ ?></td>
                <td class="text-center"><?= htmlspecialchars($r['vehicle']) ?></td>
                <td class="left-align text-wrap"><?= htmlspecialchars($r['assigned_user']) ?></td>
                <td class="text-center">
                    <?= ($r['date'] && $r['date'] !== '0000-00-00') ? date('d-M-Y', strtotime($r['date'])) : '-' ?>
                </td>
                <td class="left-align"><?= htmlspecialchars($r['type']) ?></td>
                <td class="text-start text-wrap"><?= htmlspecialchars($r['desc']) ?></td>
                <td class="text-start"><?= htmlspecialchars($r['mileage'] ?: $r['meter']) ?></td>
                <td class="text-end"><?= number_format((float)$r['amount'], 2) ?></td>
                <td class="text-start"><?= htmlspecialchars($r['person']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold table-light">
                <td colspan="7" class="text-end">Total</td>
                <td class="text-end"><?= number_format($total, 2) ?></td>
                <td></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
