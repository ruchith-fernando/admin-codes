
<script>
/* DASHBOARD: load-report click -> single, namespaced, de-duped */
(function () {
  const NS = '.page.dashboard';

  // Clear old handlers and namespace
  $(document).off('click', '.load-report');
  $(document).off(NS);

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

  window.stopEverything = function () { $(document).off(NS); };

  // -------------------- Helpers --------------------
  function nf(x) { return Number(Math.round(x)).toLocaleString('en-US'); }
  function clamp0(x){ return x < 0 ? 0 : x; }

  // Note for remaining months (shown under Budget FY cell)
  function buildRemainingNote(remBase, pct) {
    if (!pct) return '';
    const adjRem  = remBase * (1 + pct/100);
    const diffRem = adjRem - remBase;
    const signCls = diffRem >= 0 ? 'adj-up' : 'adj-down';
    const pctLabel = (pct >= 0 ? '+' : '') + pct + '%';
    const diffLabel = (diffRem >= 0 ? '+' : '') + nf(diffRem);
    return ` <span class="${signCls}">(Remaining ${pctLabel} ⇒ ${nf(adjRem)} [${diffLabel}])</span>`;
  }

  // -------------------- Core calcs --------------------
  function recalcRow($tr) {
    const bfy = parseFloat($tr.find('td.budget-fy').data('base')) || 0;
    const btd = parseFloat($tr.find('td.budget-td').data('base')) || 0;
    const act = parseFloat($tr.find('td.actual-td').data('base')) || 0;
    const pct = parseFloat($tr.find('.adj-budget').val() || '0');

    const remainingBase = clamp0(bfy - btd);
    const remainingAdj  = remainingBase * (1 + pct/100);
    const forecastYE    = act + remainingAdj;                 // Actual + adjusted remaining
    const yeVarBalance  = bfy - forecastYE;                   // positive = under budget
    const yeVarPct      = bfy > 0 ? (yeVarBalance / bfy) * 100 : 0;

    // Update Budget FY note (about remaining impact)
    $tr.find('.adj-budget-fy').html(buildRemainingNote(remainingBase, pct));

    // Update Forecast + YE variances
    $tr.find('.forecast-val').text(nf(forecastYE));
    $tr.find('.forecast-note').text(remainingBase ? `Remaining base ${nf(remainingBase)} → adj ${nf(remainingAdj)}` : 'No remaining');

    const $bal = $tr.find('.ye-variance-balance');
    const $pct = $tr.find('.ye-variance-pct');
    $bal.text(nf(yeVarBalance))
        .toggleClass('text-success', yeVarBalance >= 0)
        .toggleClass('text-danger',  yeVarBalance < 0);
    $pct.text((yeVarPct >= 0 ? '' : '') + Math.round(yeVarPct) + '%')
        .toggleClass('text-success', yeVarPct >= 0)
        .toggleClass('text-danger',  yeVarPct < 0);
  }

  function recalcTotals() {
    let sumBFY = 0, sumBtd = 0, sumAct = 0, sumForecast = 0;

    $('tbody tr').each(function() {
      if ($(this).attr('id') === 'totals-row') return;
      const $tr = $(this);

      // Recompute per row to get current forecast
      const bfy = parseFloat($tr.find('td.budget-fy').data('base')) || 0;
      const btd = parseFloat($tr.find('td.budget-td').data('base')) || 0;
      const act = parseFloat($tr.find('td.actual-td').data('base')) || 0;
      const pct = parseFloat($tr.find('.adj-budget').val() || '0');

      const remainingBase = Math.max(bfy - btd, 0);
      const remainingAdj  = remainingBase * (1 + pct/100);
      const forecastYE    = act + remainingAdj;

      sumBFY      += bfy;
      sumBtd      += btd;
      sumAct      += act;
      sumForecast += forecastYE;
    });

    // Totals: show overall Budget FY adj note (vs base totals)
    const $bfyTot = $('.budget-fy-total');
    const baseBFY = parseFloat($bfyTot.data('base')) || 0;

    // Totals: set Forecast YE and YE variances
    $('.forecast-ye-total').text(nf(sumForecast));

    const yeBalTotal = sumBFY - sumForecast; // positive = under budget
    const yePctTotal = sumBFY > 0 ? (yeBalTotal / sumBFY) * 100 : 0;

    $('.ye-variance-balance-total')
      .text(nf(yeBalTotal))
      .toggleClass('text-success', yeBalTotal >= 0)
      .toggleClass('text-danger',  yeBalTotal < 0);

    $('.ye-variance-pct-total')
      .text(Math.round(yePctTotal) + '%')
      .toggleClass('text-success', yePctTotal >= 0)
      .toggleClass('text-danger',  yePctTotal < 0);

    // Optional: show how far the sum of remaining-adj differs from base totals
    const diffBFY = (sumBFY - baseBFY); // this is usually 0 unless rows changed externally
    $('.adj-budget-fy-total').html(
      diffBFY !== 0
        ? ` <span class="${diffBFY>=0?'adj-up':'adj-down'}">(base total change ${diffBFY>=0?'+':''}${nf(diffBFY)})</span>`
        : ''
    );
  }

  function applyAll() {
    $('tbody tr').each(function(){
      if ($(this).attr('id') === 'totals-row') return;
      recalcRow($(this));
    });
    recalcTotals();
  }

  $(document).on('change' + NS, '.adj-budget', function(){
    const $tr = $(this).closest('tr');
    recalcRow($tr);
    recalcTotals();
  });

  applyAll();

  function raw(x){ return Number(x) || 0; }

  function buildExportTableHtml() {
    let html = '';
    html += '<html><head><meta charset="UTF-8"><style>';
    html += '.export-table{border-collapse:collapse} .export-table th,.export-table td{border:1px solid #999;padding:6px 8px;text-align:right} .export-table th:first-child,.export-table td:first-child{text-align:left}';
    html += '</style></head><body>';
    html += '<table class="export-table">';
    html += '<thead><tr>';
    html += '<th>#</th>';
    html += '<th>Category</th>';
    html += '<th>Budget (Full Year) - Base</th>';
    html += '<th>Budget (To Date)</th>';
    html += '<th>Remaining Base</th>';
    html += '<th>Remaining Adj %</th>';
    html += '<th>Remaining Adjusted</th>';
    html += '<th>Actual (To Date)</th>';
    html += '<th>Forecast (Year-End)</th>';
    html += '<th>YE Variance vs Budget</th>';
    html += '<th>YE Variance (%)</th>';
    html += '<th>Variance Balance (To Date)</th>';
    html += '<th>Variance % (To Date)</th>';
    html += '</tr></thead><tbody>';

    let rowIdx = 0;
    let sumBFY=0,sumBtd=0,sumAct=0,sumForecast=0;

    $('tbody tr').each(function() {
      if ($(this).attr('id') === 'totals-row') return;
      const $tr = $(this);

      const catText = $tr.find('td').eq(1).find('.load-report').text().trim();

      const bfy = raw($tr.find('td.budget-fy').data('base'));
      const btd = raw($tr.find('td.budget-td').data('base'));
      const act = raw($tr.find('td.actual-td').data('base'));
      const pct = raw($tr.find('.adj-budget').val());

      const remainingBase = Math.max(bfy - btd, 0);
      const remainingAdj  = remainingBase * (1 + pct/100);
      const forecastYE    = act + remainingAdj;

      const yeVarBalance  = bfy - forecastYE;                       // positive = under budget
      const yeVarPct      = bfy > 0 ? (yeVarBalance / bfy) * 100 : 0;

      // existing to-date variance (already shown on page)
      const balToDate     = btd - act;
      const varPctToDate  = btd > 0 ? ((btd - act) / btd) * 100 : 0;

      rowIdx++;
      sumBFY += bfy; sumBtd += btd; sumAct += act; sumForecast += forecastYE;

      html += '<tr>';
      html += '<td>' + rowIdx + '</td>';
      html += '<td>' + catText + '</td>';
      html += '<td>' + bfy + '</td>';
      html += '<td>' + btd + '</td>';
      html += '<td>' + remainingBase + '</td>';
      html += '<td>' + (pct>=0?'+':'') + pct + '%</td>';
      html += '<td>' + remainingAdj + '</td>';
      html += '<td>' + act + '</td>';
      html += '<td>' + forecastYE + '</td>';
      html += '<td>' + yeVarBalance + '</td>';
      html += '<td>' + Math.round(yeVarPct) + '%</td>';
      html += '<td>' + balToDate + '</td>';
      html += '<td>' + Math.round(varPctToDate) + '%</td>';
      html += '</tr>';
    });

    const yeBalTotal = sumBFY - sumForecast;
    const yePctTotal = sumBFY > 0 ? (yeBalTotal / sumBFY) * 100 : 0;
    const balToDateTotal = sumBtd - sumAct;
    const varPctToDateTotal = sumBtd > 0 ? (balToDateTotal / sumBtd) * 100 : 0;

    html += '<tr>';
    html += '<th colspan="2" style="text-align:left;">Total</th>';
    html += '<th>' + sumBFY + '</th>';
    html += '<th>' + sumBtd + '</th>';
    html += '<th>' + Math.max(sumBFY - sumBtd, 0) + '</th>';
    html += '<th>—</th>';
    html += '<th>—</th>';
    html += '<th>' + sumAct + '</th>';
    html += '<th>' + sumForecast + '</th>';
    html += '<th>' + yeBalTotal + '</th>';
    html += '<th>' + Math.round(yePctTotal) + '%</th>';
    html += '<th>' + balToDateTotal + '</th>';
    html += '<th>' + Math.round(varPctToDateTotal) + '%</th>';
    html += '</tr>';

    html += '</tbody></table></body></html>';
    return html;
  }

  $(document).on('click' + NS, '#btnExportExcel', function () {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const filename = `Admin_Budget_Overview_Adjusted_${y}-${m}-${d}.xls`;

    const tableHtml = buildExportTableHtml();
    const blob = new Blob([tableHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });

    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
      window.navigator.msSaveOrOpenBlob(blob, filename);
      return;
    }

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });

})();
</script>
