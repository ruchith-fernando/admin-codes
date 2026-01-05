<?php
  include_once 'dashboard-php.php'
?>
<style>
  table td { word-wrap: break-word; white-space: normal; }
  .adj-note { display:inline-block; font-size: 0.85rem; color:#6c757d; margin-left: 6px; }
  .adj-up { color:#198754; }
  .adj-down { color:#dc3545; }
  .adj-select { width: 84px; }
  .cell-controls { margin-top: 6px; }

  .export-table { border-collapse: collapse; }
  .export-table th, .export-table td { border: 1px solid #999; padding: 6px 8px; text-align: right; }
  .export-table th:first-child, .export-table td:first-child { text-align: left; }

  .table.table-bordered {
    table-layout: fixed;
    width: 100%;
  }
  .table.table-bordered th {
    white-space: normal !important;
    word-wrap: break-word;
    vertical-align: middle;
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8f9fa;
  }
  .table-scroll {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 600px;
    max-width: 120%;
    margin-bottom: 1rem;
  }
  .table-scroll table { min-width: 1400px; }
  .badge {
    display: inline-block;
    min-width: 90px;             /* Increased from 70px → 90px */
    font-size: 1.2rem;           /* ~60% larger text */
    font-weight: 600;
    text-align: center;
    padding: 8px 16px;           /* Increased padding for height/width */
    border-radius: 10px;         /* Slightly rounder for cleaner look */
    line-height: 1.4;
  }

  /* Optional: consistent colors with softer shades */
  .bg-success {
    background-color: #28a745 !important;
    color: #fff !important;
  }
  .bg-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
  }
  .bg-danger {
    background-color: #dc3545 !important;
    color: #fff !important;
  }
    .progress {
      overflow: hidden;
      border-radius: 8px;
      height: 16px;
    }
    .progress-bar {
      transition: width 0.6s ease;
    }
    .progress-container {
      width: 100%;
      max-width: 240px;
      margin: 0 auto;
    }
    .status-label {
      text-align: center;
      margin-top: 4px;
    }

    .spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
  }

</style>

<div class="content font-size bg-light">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Administration Overall Budget Overview</h5>
        <button id="btnExportExcel" class="btn btn-sm btn-success">Download Excel</button>
      </div>

      <div class="table-scroll">
        <table class="table table-bordered">
          <colgroup>
            <col style="width:40px">    <!-- # -->
            <col style="width:230px">   <!-- Category -->
            <col style="width:100px">   <!-- Budget (Full Year) -->
            <col style="width:100px">   <!-- Budget (To Date) -->
            <col style="width:100px">   <!-- Actual (To Date) -->
            <col style="width:100px">   <!-- Variance Balance -->
            <col style="width:100px">   <!-- Variance (%) -->
            <col style="width:100px">   <!-- Adjustment % -->
            <col style="width:100px">   <!-- Adjustment Calculation -->
            <col style="width:100px">   <!-- Adjusted New Budget -->
            <col style="width:100px">   <!-- Monthly Adjustment -->
            <col style="width:200px">   <!-- Status -->
          </colgroup>
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Category</th>
              <th>Budget (Full Year)</th>
              <th>Budget (To Date)</th>
              <th>Actual (To Date)</th>
              <th>Variance Balance</th>
              <th>Variance (%)</th>
              <th>Adjustment %</th>
              <th>Adjustment Calculation</th>
              <th>Adjusted New Budget</th>
              <th>Monthly Adjustment</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
// 🔽 Sort categories automatically by month+year
usort($combined, function($a, $b) {
    $timeA = strtotime("01 " . $a['category']);
    $timeB = strtotime("01 " . $b['category']);
    return $timeA <=> $timeB;
});

$i = 1;
$totals = ['budget' => 0, 'to_date' => 0, 'actual' => 0, 'balance' => 0];

foreach ($combined as $row) {
    $category       = $row['category'];
    $budget_full    = $row['budget_full'];
    $budget_to_date = $row['budget_to_date'];
    $actual         = $row['actual'];
    $balance        = $row['balance'];
    $variance       = $row['variance']; // % remaining (can be negative)
    $month_count    = $row['month_count'];
    $months_text    = $row['months_text'];

    $link = $category_links[$category] ?? '#';

    echo "<tr>";
    echo "<td style='width:60px;'>{$i}</td>";
    echo "<td style='width:460px; max-width:460px; word-break:break-word; white-space:normal;'>
            <span class='load-report text-primary' style='cursor:pointer;' data-url='{$link}'>{$category}</span><br>
            <span class='text-danger'>($months_text)</span>
          </td>";

    echo "<td style='width:180px;' class='text-end budget-fy' data-base='{$budget_full}'>" . number_format($budget_full) . "</td>";

    echo "<td style='width:180px;' class='text-end budget-td' data-base='{$budget_to_date}'>" . number_format($budget_to_date);
    if ($month_count > 0) {
        echo "<br><span class='text-danger'>({$month_count} months)</span>";
    } else {
        echo "<br><span class='text-danger'>(No months selected to display)</span>";
    }
    echo "</td>";

    echo "<td style='width:180px;' class='text-end actual-td' data-base='{$actual}'>" . number_format($actual) . "</td>";

    // Variance Balance
    echo "<td style='width:200px;' class='text-end'>" . number_format($balance) . "</td>";

    // ===== Determine color/status based on variance =====
    $var_num = floatval($variance);
    $LOW_LEFT_THRESHOLD = 20;

    if ($var_num < 0) {
        $status_label   = 'Over Budget';
        $bar_color      = 'bg-danger';
        $label_color    = '#dc3545';
        $variance_color = '#dc3545';
        $display_note   = number_format(abs($var_num)) . '% over';
    } elseif ($var_num === 0.0) {
        $status_label   = 'Not Utilized';
        $bar_color      = 'bg-primary';
        $label_color    = '#0d6efd';
        $variance_color = '#0d6efd';
        $display_note   = '100% left';
    } elseif ($var_num <= $LOW_LEFT_THRESHOLD) {
        $status_label   = 'Poor';
        $bar_color      = 'bg-warning';
        $label_color    = '#ffc107';
        $variance_color = '#ffc107';
        $display_note   = number_format($var_num) . '% left';
    } else {
        $status_label   = 'Good';
        $bar_color      = 'bg-success';
        $label_color    = '#198754';
        $variance_color = '#198754';
        $display_note   = number_format($var_num) . '% left';
    }

    // ===== Variance % (colored & bold) =====
    echo "<td class='text-end fw-bold' style='width:160px; color: {$variance_color};'>" . number_format($variance) . "%</td>";

    // Adjustment %
    echo "<td style='width:100px;' class='text-center'>
            <select class='form-select form-select-sm adj-select adj-budget'>
              <option value='0'>0%</option>
              <option value='5'>+5%</option>
              <option value='10'>+10%</option>
              <option value='15'>+15%</option>
              <option value='20'>+20%</option>
              <option value='25'>+25%</option>
              <option value='50'>+50%</option>
              <option value='-5'>-5%</option>
              <option value='-10'>-10%</option>
              <option value='-15'>-15%</option>
              <option value='-20'>-20%</option>
              <option value='-25'>-25%</option>
              <option value='-50'>-50%</option>
            </select>
          </td>";

    // Adjustment Calculation
    echo "<td style='width:200px;' class='text-end adj-calc'>0</td>";

    // Adjusted New Budget
    echo "<td style='width:200px;' class='text-end adj-new-budget'>0</td>";

    // Monthly Adjustment
    echo "<td style='width:200px;' class='text-end adj-monthly'>0</td>";

    // ===== Progress Bar Column (Usage-based fill) =====
    // bar shows % used (not remaining)
    $used_percent = 100 - $var_num;
    if ($used_percent < 0) $used_percent = 100; // over budget -> full
    if ($var_num === 0.0) $used_percent = 0;    // not utilized -> empty

    echo "<td style='width:260px; vertical-align:middle; text-align:center;'>
            <div class='progress-container' style='margin-bottom:6px;'>
              <div class='progress' style='height:16px; border-radius:8px; background:#e9ecef; width:100%;'>
                <div class='progress-bar {$bar_color}' role='progressbar'
                     style='width: {$used_percent}%; transition:width 0.6s ease;'
                     aria-valuenow='{$used_percent}' aria-valuemin='0' aria-valuemax='100'></div>
              </div>
            </div>
            <div class='status-label' style='font-weight:600; color: {$label_color}; font-size:0.9rem;'>
              {$status_label} ({$display_note})
            </div>
          </td>";

    echo "</tr>";

    $i++;
    $totals['budget']  += $budget_full;
    $totals['to_date'] += $budget_to_date;
    $totals['actual']  += $actual;
    $totals['balance'] += $balance;
}

$total_variance = ($totals['to_date'] > 0)
  ? round((($totals['to_date'] - $totals['actual']) / $totals['to_date']) * 100)
  : 0;
?>



            <tr id="totals-row" class="fw-bold table-light">
              <td colspan="2" class="text-start">Total</td>
              <td class="text-end"><?= number_format($totals['budget']) ?></td>
              <td class="text-end"><?= number_format($totals['to_date']) ?></td>
              <td class="text-end"><?= number_format($totals['actual']) ?></td>
              <td class="text-end"><?= number_format($totals['balance']) ?></td>
              <td class="text-end <?= ($total_variance < 0) ? 'text-danger' : 'text-success' ?>">
                <?= number_format($total_variance) ?>%
              </td>
              <td>—</td>
              <td class="text-end adj-calc-total">0</td>
              <td class="text-end adj-new-budget-total">0</td>
              <td class="text-end adj-monthly-total">0</td>
              <td>—</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>
<script>
(function () {
  const NS = '.page.dashboard';
  $(document).off('click', '.load-report'); $(document).off(NS);

  $(document).on('click' + NS, '.load-report', function (e) {
    e.preventDefault();
    const url = $(this).data('url');
    const $area = $('#contentArea');
    $area.html('<div class="text-center p-4"><div class="spinner-border text-primary"></div><p class="mt-3">Loading report...</p></div>');
    $.get(url, function (res) {
      $area.html(res);
    }).fail(function () {
      $area.html('<div class="alert alert-danger p-4 text-center">Failed to load report.</div>');
    });
  });

  function nf(x){return Number(Math.round(x)).toLocaleString('en-US');}

  function recalcRow($tr){
    const bfy = parseFloat($tr.find('td.budget-fy').data('base')) || 0;
    const pct = parseFloat($tr.find('.adj-budget').val() || '0');
    const adjCalc = bfy * (pct / 100);

    if (pct === 0) {
      $tr.find('.adj-calc').text('0');
      $tr.find('.adj-new-budget').text('0');
      $tr.find('.adj-monthly').text('0');
    } else {
      const newBudget = bfy + adjCalc;
      const adjMonthly = newBudget / 12;
      $tr.find('.adj-calc').text(nf(adjCalc));
      $tr.find('.adj-new-budget').text(nf(newBudget));
      $tr.find('.adj-monthly').text(nf(adjMonthly));
    }
}
  function recalcTotals(){
    let sumAdj=0,sumNewBudget=0,sumMonthly=0;
    $('tbody tr').each(function(){
      if(this.id==='totals-row') return;
      const adj=parseFloat($(this).find('.adj-calc').text().replace(/,/g,''))||0;
      const newBudget=parseFloat($(this).find('.adj-new-budget').text().replace(/,/g,''))||0;
      const mon=parseFloat($(this).find('.adj-monthly').text().replace(/,/g,''))||0;
      sumAdj+=adj; sumNewBudget+=newBudget; sumMonthly+=mon;
    });
    $('.adj-calc-total').text(nf(sumAdj));
    $('.adj-new-budget-total').text(nf(sumNewBudget));
    $('.adj-monthly-total').text(nf(sumMonthly));
  }

  function applyAll(){
    $('tbody tr').each(function(){ if(this.id==='totals-row') return; recalcRow($(this)); });
    recalcTotals();
  }

  $(document).on('change' + NS, '.adj-budget', function(){
    recalcRow($(this).closest('tr')); recalcTotals();
  });

  applyAll();

  function raw(x){return Number(x)||0;}

  function buildExportTableHtml() {
    let html = '';
    html += '<html><head><meta charset="UTF-8"><style>';
    html += '.export-table{border-collapse:collapse}';
    html += '.export-table th,.export-table td{border:1px solid #999;padding:6px 8px;text-align:right}';
    html += '.export-table th:first-child,.export-table td:first-child{text-align:left}';
    html += '</style></head><body>';
    html += '<table class="export-table"><thead><tr>';
    html += '<th>#</th>';
    html += '<th>Category</th>';
    html += '<th>Budget (Full Year)</th>';
    html += '<th>Budget (To Date)</th>';
    html += '<th>Actual (To Date)</th>';
    html += '<th>Variance Balance</th>';
    html += '<th>Variance (%)</th>';
    html += '<th>Adjustment %</th>';
    html += '<th>Adjustment Calculation</th>';
    html += '<th>Adjusted New Budget</th>';
    html += '<th>Monthly Adjustment</th>';
    html += '<th>Status</th>';
    html += '</tr></thead><tbody>';

    let rowIdx = 0, sumBFY = 0, sumBtd = 0, sumAct = 0, sumAdj = 0, sumNewBudget = 0, sumMonthly = 0;

    $('tbody tr').each(function () {
      if (this.id === 'totals-row') return;

      const $tr = $(this);
      const catText = $tr.find('td').eq(1).find('.load-report').text().trim();
      const bfy = raw($tr.find('td.budget-fy').data('base'));
      const btd = raw($tr.find('td.budget-td').data('base'));
      const act = raw($tr.find('td.actual-td').data('base'));
      const bal = $tr.find('td').eq(5).text().replace(/,/g, '');
      const variance = parseFloat($tr.find('td').eq(6).text()) || 0; // e.g. "45%" → 45
      const pct = raw($tr.find('.adj-budget').val());
      const adjCalc = bfy * (pct / 100);
      const newBudget = bfy + adjCalc;
      const adjMonthly = newBudget / 12;

      rowIdx++;
      sumBFY += bfy; sumBtd += btd; sumAct += act; sumAdj += adjCalc;
      if (pct !== 0) { sumNewBudget += newBudget; sumMonthly += adjMonthly; }

      // === Status (same logic as UI) with correct phrasing
      const LOW_LEFT_THRESHOLD = 20;
      const leftDisplay = (variance === 0 ? 100 : variance); // 0 → show 100% left
      let status_label = '';

      if (variance < 0) {
        status_label = `Over Budget (${Math.abs(variance)}% over)`;
      } else if (variance === 0) {
        status_label = `Not Utilized (${leftDisplay}% left)`;
      } else if (variance <= LOW_LEFT_THRESHOLD) {
        status_label = `Poor (${leftDisplay}% left)`;
      } else {
        status_label = `Good (${leftDisplay}% left)`;
      }

      html += '<tr>';
      html += '<td>' + rowIdx + '</td>';
      html += '<td>' + catText + '</td>';
      html += '<td>' + nf(bfy) + '</td>';
      html += '<td>' + nf(btd) + '</td>';
      html += '<td>' + nf(act) + '</td>';
      html += '<td>' + nf(bal) + '</td>';
      html += '<td>' + variance + '%</td>';
      html += '<td>' + (pct >= 0 ? '+' : '') + pct + '%</td>';
      html += '<td>' + nf(adjCalc) + '</td>';
      if (pct === 0) {
        html += '<td></td><td></td>';
      } else {
        html += '<td>' + nf(newBudget) + '</td>';
        html += '<td>' + nf(adjMonthly) + '</td>';
      }
      html += '<td>' + status_label + '</td>';
      html += '</tr>';
    });

    // === Totals Row
    html += '<tr>';
    html += '<th colspan="2" style="text-align:left;">Total</th>';
    html += '<th>' + nf(sumBFY) + '</th>';
    html += '<th>' + nf(sumBtd) + '</th>';
    html += '<th>' + nf(sumAct) + '</th>';
    html += '<th>—</th>';
    html += '<th>—</th>';
    html += '<th>—</th>';
    html += '<th>' + nf(sumAdj) + '</th>';
    html += '<th>' + (sumNewBudget === 0 ? '' : nf(sumNewBudget)) + '</th>';
    html += '<th>' + (sumMonthly === 0 ? '' : nf(sumMonthly)) + '</th>';
    html += '<th>—</th>';
    html += '</tr>';

    html += '</tbody></table></body></html>';
    return html;
  }


  // ✅ Updated Excel Export with recalculation, feedback, and timestamp
  $(document).on('click' + NS, '#btnExportExcel', function() {
    const $btn = $('#btnExportExcel');
    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-2"></span> Preparing...');

    setTimeout(() => {
      // 🔄 Ensure latest recalculations before export
      applyAll();

      // 🕒 Build filename with timestamp
      const today = new Date();
      const y = today.getFullYear();
      const m = String(today.getMonth() + 1).padStart(2, '0');
      const d = String(today.getDate()).padStart(2, '0');
      const hh = String(today.getHours()).padStart(2, '0');
      const mm = String(today.getMinutes()).padStart(2, '0');
      const filename = `Admin_Budget_Overview_Adjusted_${y}-${m}-${d}_${hh}-${mm}.xls`;

      // 🧾 Build Excel table HTML
      const tableHtml = buildExportTableHtml();
      const blob = new Blob([tableHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });

      // 💾 Trigger download
      if (window.navigator && window.navigator.msSaveOrOpenBlob) {
        window.navigator.msSaveOrOpenBlob(blob, filename);
      } else {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }

      // ✅ Restore button state
      $btn.prop('disabled', false).text('Download Excel');
    }, 300); // slight delay for recalculation + spinner visibility
  });


})();
</script>
