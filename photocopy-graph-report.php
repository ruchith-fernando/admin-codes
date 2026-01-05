<!-- photocopy-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Header row: title + dropdown to switch the chart style -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Photocopy Budget vs Actual (Report)</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="photocopyChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="photocopyChartTypeLabel">Line</span>
          </button>

          <!-- Same chart style choices used across the other report pages -->
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="photocopyChartTypeBtn">
            <li><a class="dropdown-item photocopy-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item photocopy-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item photocopy-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item photocopy-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <!-- Chart.js will draw into this canvas -->
      <div class="chart-container-fluid">
        <canvas id="photocopyChart"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
/*
  PHOTOCOPY CHART MODULE

  This follows the same pattern as the other chart widgets:
  - fetches data from ajax-photocopy-chart.php
  - draws Budget vs Actual using Chart.js
  - supports line / area / bar / stacked bar
  - cleans up properly if the page is reloaded or injected again
*/
(function () {

  // If this page/widget is initialized again, destroy the previous instance first
  if (window.__photocopyChartPage && typeof window.__photocopyChartPage.destroy === 'function') {
    window.__photocopyChartPage.destroy();
  }

  let mode = 'line';        // current chart style
  let controller = null;    // AbortController to cancel requests
  let loadToken = 0;        // prevents older responses from overwriting the latest chart
  let chart = null;         // Chart.js instance

  // Standard color pair used across your charts (budget=blue, actual=green)
  const COLORS = { budget:'#007bff', actual:'#28a745' };

  // Tiny DOM helpers to keep things readable
  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.from(document.querySelectorAll(sel)); }

  // Chart.js sometimes behaves better if we replace the canvas entirely on reload
  function replaceCanvas(id){
    const old = document.getElementById(id);
    if (!old) return null;

    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = id;

    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }

  // Used only for "area" mode so we can fill the chart with a light transparent color
  function rgba(hex, a){
    const c = hex.replace('#','');
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  // Build datasets based on selected mode.
  // Note: this endpoint may return actuals as "total_actual" (older) or "actual" (newer),
  // so we support both without breaking the chart.
  function buildDatasets(payload){
    const actualArr = payload.total_actual || payload.actual || [];

    const isBar  = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');

    const base = [
      { label:'Monthly Budget', data: payload.budget || [], borderColor: COLORS.budget, borderWidth: 2 },
      { label:'Monthly Actual', data: actualArr,             borderColor: COLORS.actual, borderWidth: 2 }
    ];

    return base.map(d => isBar
      ? { ...d, backgroundColor: d.borderColor }
      : {
          ...d,
          backgroundColor: fillOn ? rgba(d.borderColor, 0.15) : 'transparent',
          fill: fillOn,
          tension: 0.3,
          pointRadius: 4
        }
    );
  }

  // Standard chart options with stacking + point tweaks depending on mode
  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar   = (mode === 'bar' || stacked);

    return {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        title:  { display: true, text: 'Photocopy Budget vs Actual' },

        // Tooltip: show values as currency
        tooltip: {
          callbacks: {
            label: function(ctx){
              const v = Number(ctx.parsed.y || 0);
              return (ctx.dataset.label || '') + ': Rs. ' + v.toLocaleString();
            }
          }
        }
      },
      elements: {
        line:  { tension: 0.3 },
        point: { radius: isBar ? 0 : 4 }
      },
      scales: {
        x: { stacked },
        y: {
          stacked,
          beginAtZero: true,
          ticks: { callback: v => 'Rs. ' + Number(v).toLocaleString() }
        }
      }
    };
  }

  // Fetch data and rebuild the chart (safe even when user switches styles quickly)
  async function hardLoad(){
    const myToken = ++loadToken;

    // Cancel the previous request if it hasn't finished yet
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    // Destroy existing chart (if any) before recreating
    if (chart) {
      try { chart.destroy(); } catch(e){}
      chart = null;
    }

    // Fresh canvas context every time
    const ctx = replaceCanvas('photocopyChart');
    if (!ctx) return;

    // Cache busting: avoids stale responses in aggressive browser caches
    const url = 'ajax-photocopy-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = { cache:'reload', headers:{ 'Cache-Control':'no-store' } };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const payload = await res.json();

      // If another load started after this one, ignore this response
      if (myToken !== loadToken) return;

      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';

      chart = new Chart(ctx, {
        type,
        data: { labels: payload.labels || [], datasets: buildDatasets(payload) },
        options: buildOptions()
      });

    } catch(err) {
      if (err && err.name === 'AbortError') return; // expected when switching modes quickly
      console.error('Photocopy chart load failed:', err);
    }
  }

  // When user selects a chart type, update the label and reload
  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#photocopyChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  // Bind dropdown option clicks
  $all('.photocopy-chart-type').forEach(a => a.addEventListener('click', onTypeClick));

  // First render
  hardLoad();

  // Expose a destroy hook so other scripts/pages can safely re-init this widget
  window.__photocopyChartPage = {
    destroy(){
      const ctl = controller;
      controller = null;

      try { ctl?.abort(); } catch(e){}

      if (chart) {
        try { chart.destroy(); } catch(e){}
        chart = null;
      }

      $all('.photocopy-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__photocopyChartPage = null;
    }
  };

})();
</script>
