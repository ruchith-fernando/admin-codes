<?php
// ajax-security-budget-summary.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    echo "<div style='color:red;background:#fff3cd;border:1px solid #ffa500;padding:10px;margin-bottom:10px;'>
    ⚠️ PHP Error: $message <br><small>File: $file | Line: $line</small></div>";
});
set_exception_handler(function ($e) {
    echo "<div style='color:red;background:#f8d7da;border:1px solid #dc3545;padding:10px;margin-bottom:10px;'>
    ❌ Exception: " . $e->getMessage() . "</div>";
});

/* -----------------------------
   Helpers (NO DECIMALS)
-------------------------------- */
function money0($n) {
    if ($n === null || $n === '' || !is_numeric($n)) return '-';
    return number_format((int)round((float)$n), 0);
}

require_once __DIR__ . '/../connections/connection.php';
if (!$conn) die("<div style='color:red;'>❌ Database connection failed.</div>");

/* ─────────────────────────────────────────────
 * 1. Build April → March fiscal year months (dynamic)
 * ───────────────────────────────────────────── */
$startYear = (date('n') >= 4) ? (int)date('Y') : (int)date('Y') - 1;

$months1 = $months2 = [];
for ($i = 0; $i < 12; $i++) {
    $monthNum  = ($i + 4) % 12 ?: 12; // April=4
    $year      = ($i < 9) ? $startYear : $startYear + 1;
    $monthName = date('F', mktime(0, 0, 0, $monthNum, 1));

    if ($i < 6) $months1[] = "$monthName $year";     // Apr–Sep
    else        $months2[] = "$monthName $year";     // Oct–Mar
}
$allMonths = array_merge($months1, $months2);

/* ─────────────────────────────────────────────
 * 2. Fetch BUDGET data
 * ───────────────────────────────────────────── */
$sql = "SELECT branch_code, branch, no_of_shifts, rate, month_applicable
        FROM tbl_admin_budget_security";
$result = $conn->query($sql);
if (!$result) {
    echo "<div style='color:red;'>❌ SQL Error: {$conn->error}<br><small>Query: $sql</small></div>";
    return;
}

$data = [];
$monthlyTotals = array_fill_keys($allMonths, 0.0);

// ✅ Monthly category breakdown (BUDGET ONLY)
$budgetBreakdownMonthly = [];
foreach ($allMonths as $m) {
    $budgetBreakdownMonthly[$m] = [
        'branches'   => 0.0, // 1–999 and >= 9000
        'yards'      => 0.0, // 2001–2013
        'police'     => 0.0, // 2014
        'additional' => 0.0, // 2015
        'radio'      => 0.0, // 2016
        'total'      => 0.0,
    ];
}

while ($row = $result->fetch_assoc()) {
    $code  = strtoupper($row['branch_code']);
    $month = $row['month_applicable'];

    // RAW total (keep float), display later with money0()
    $total = (float)$row['no_of_shifts'] * (float)$row['rate'];

    if (!isset($data[$code])) {
        $data[$code] = [
            'branch_code' => $code,
            'branch'      => $row['branch'],
            'months1'     => [],
            'months2'     => [],
        ];
    }

    $target = in_array($month, $months1, true)
        ? 'months1'
        : (in_array($month, $months2, true) ? 'months2' : null);

    if ($target) {
        $data[$code][$target][$month] = [
            'shifts' => $row['no_of_shifts'],
            'rate'   => $row['rate'],
            'total'  => $total,
        ];

        // overall month total (budget)
        $monthlyTotals[$month] += $total;

        // category monthly breakdown
        $num = is_numeric($code) ? (int)$code : 0;

        if (($num >= 1 && $num <= 999) || ($num >= 9000)) {
            $budgetBreakdownMonthly[$month]['branches'] += $total;
        } elseif ($num >= 2001 && $num <= 2013) {
            $budgetBreakdownMonthly[$month]['yards'] += $total;
        } elseif ($num === 2014) {
            $budgetBreakdownMonthly[$month]['police'] += $total;
        } elseif ($num === 2015) {
            $budgetBreakdownMonthly[$month]['additional'] += $total;
        } elseif ($num === 2016) {
            $budgetBreakdownMonthly[$month]['radio'] += $total;
        }

        $budgetBreakdownMonthly[$month]['total'] += $total;
    }
}

// Better sort: numeric first when possible
uksort($data, function ($a, $b) {
    $an = is_numeric($a) ? (int)$a : null;
    $bn = is_numeric($b) ? (int)$b : null;
    if ($an !== null && $bn !== null) return $an <=> $bn;
    return strcmp((string)$a, (string)$b);
});

/* ─────────────────────────────────────────────
 * 3. Start HTML output
 * ───────────────────────────────────────────── */
?>
<div class="card-body font-size">
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle text-center">
      <thead class="table-light">
        <tr>
          <th rowspan="2">Branch Code</th>
          <th rowspan="2">Branch<br><small>(Shifts / Rates or Allocation)</small></th>
          <th colspan="<?= count($months1) ?>">April <?= $startYear ?> - September <?= $startYear ?></th>
          <th colspan="<?= count($months2) ?>">October <?= $startYear ?> - March <?= $startYear + 1 ?></th>
        </tr>
        <tr>
          <?php foreach ($allMonths as $m): ?>
            <th><?= htmlspecialchars($m) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>

        <?php foreach ($data as $branch): ?>
          <?php
            $codeNum = is_numeric($branch['branch_code']) ? (int)$branch['branch_code'] : 0;
            $isAllocation = in_array($codeNum, [2014, 2015, 2016, 2017], true);

            // Apr–Sep
            $sh1 = count($branch['months1'])
                ? (array_sum(array_column($branch['months1'], 'shifts')) / max(count($branch['months1']), 1))
                : 0.0;
            $r1  = array_column($branch['months1'], 'rate');
            $avgRate1 = count($r1) ? (array_sum($r1) / max(count($r1), 1)) : 0.0;

            // Oct–Mar
            $sh2 = count($branch['months2'])
                ? (array_sum(array_column($branch['months2'], 'shifts')) / max(count($branch['months2']), 1))
                : 0.0;
            $r2  = array_column($branch['months2'], 'rate');
            $avgRate2 = count($r2) ? (array_sum($r2) / max(count($r2), 1)) : 0.0;

            // keep rate at 2dp (change to 0 if you want)
            $avgRate1Fmt = number_format((float)$avgRate1, 2);
            $avgRate2Fmt = number_format((float)$avgRate2, 2);
          ?>

          <!-- Row for Apr–Sep -->
          <tr>
            <td class="text-start"><?= htmlspecialchars($branch['branch_code']) ?></td>
            <td class="text-start">
              <?= htmlspecialchars($branch['branch']) ?><br>
              <?php if ($isAllocation): ?>
                <span class="text-muted small">Allocation: <?= $avgRate1Fmt ?></span>
              <?php else: ?>
                <span class="text-muted small">Shifts: <?= number_format((float)$sh1) ?></span>
                <span class="text-muted small ms-2">Rate: <?= $avgRate1Fmt ?></span>
              <?php endif; ?>
            </td>

            <?php foreach ($months1 as $m): ?>
              <td><?= isset($branch['months1'][$m]) ? money0($branch['months1'][$m]['total']) : '-' ?></td>
            <?php endforeach; ?>
            <?php foreach ($months2 as $m): ?>
              <td>-</td>
            <?php endforeach; ?>
          </tr>

          <!-- Row for Oct–Mar -->
          <tr class="table-secondary">
            <td class="text-start"><?= htmlspecialchars($branch['branch_code']) ?></td>
            <td class="text-start">
              <?= htmlspecialchars($branch['branch']) ?><br>
              <?php if ($isAllocation): ?>
                <span class="text-muted small">Allocation: <?= $avgRate2Fmt ?></span>
              <?php else: ?>
                <span class="text-muted small">Shifts: <?= number_format((float)$sh2) ?></span>
                <span class="text-muted small ms-2">Rate: <?= $avgRate2Fmt ?></span>
              <?php endif; ?>
            </td>

            <?php foreach ($months1 as $m): ?>
              <td>-</td>
            <?php endforeach; ?>
            <?php foreach ($months2 as $m): ?>
              <td><?= isset($branch['months2'][$m]) ? money0($branch['months2'][$m]['total']) : '-' ?></td>
            <?php endforeach; ?>
          </tr>

        <?php endforeach; ?>

        <!-- ✅ BUDGET BREAKDOWN ROW (NO ACTUALS / NO VARIANCE) -->
        <tr class="fw-bold bg-light">
          <td colspan="2">Budget Breakdown</td>
          <?php foreach ($allMonths as $m):
              $b = $budgetBreakdownMonthly[$m];
          ?>
            <td class="text-end text-nowrap" style="min-width:160px;font-size:11px;">
              <div><strong>Total:</strong> <?= money0($b['total']) ?></div>
              <div class="text-muted">
                Branches: <?= money0($b['branches']) ?><br>
                Y/B: <?= money0($b['yards']) ?><br>
                Police: <?= money0($b['police']) ?><br>
                Add: <?= money0($b['additional']) ?><br>
                Radio: <?= money0($b['radio']) ?>
              </div>
            </td>
          <?php endforeach; ?>
        </tr>

      </tbody>
    </table>
  </div>

  <?php
  /* ─────────────────────────────────────────
   * 4. YEAR SUMMARY (BUDGET ONLY)
   * ───────────────────────────────────────── */
  $summary = [
    'Branches'             => 0.0,
    'Yard and Bungalow'    => 0.0,
    'Police'               => 0.0,
    'Radio Transmission'   => 0.0,
    'Additional Security'  => 0.0,
  ];

  foreach ($data as $branch) {
    $code = $branch['branch_code'];
    $num  = is_numeric($code) ? (int)$code : 0;

    $total = array_sum(array_column($branch['months1'], 'total'))
           + array_sum(array_column($branch['months2'], 'total'));

    if (($num >= 1 && $num <= 999) || ($num >= 9000)) {
      $summary['Branches'] += $total;
    } elseif ($num >= 2001 && $num <= 2013) {
      $summary['Yard and Bungalow'] += $total;
    } elseif ($num === 2014) {
      $summary['Police'] += $total;
    } elseif ($num === 2016) {
      $summary['Radio Transmission'] += $total;
    } elseif ($num === 2015) {
      $summary['Additional Security'] += $total;
    }
  }

  $grandTotal = array_sum($summary);
  ?>

  <div class="mt-4">
    <h5 class="fw-bold">Summary Breakdown (Budget):</h5>
    <ul class="mb-0">
      <?php foreach ($summary as $label => $val): ?>
        <li><?= htmlspecialchars($label) ?>: <strong><?= money0($val) ?></strong></li>
      <?php endforeach; ?>
    </ul>
    <hr>
    <h6 class="fw-bold">Grand Total (Budget): <strong><?= money0($grandTotal) ?></strong></h6>
  </div>
</div>
