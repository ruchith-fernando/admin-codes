<!-- security-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Top row: page title + chart style dropdown -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Security Charges — Budget vs Actual</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="securityChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="securityChartTypeLabel">Line</span>
          </button>

          <!-- When the user changes the chart type, we rebuild the chart -->
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="securityChartTypeBtn">
            <li><a class="dropdown-item security-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item security-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item security-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item security-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <!-- Chart.js renders into this canvas -->
      <div class="chart-container-fluid">
        <canvas id="securityChart"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
/*
  SECURITY CHARGES CHART MODULE

  What this does:
  - loads budget/actual numbers from ajax-security-chart.php
  - draws a Chart.js graph
  - lets the user switch between line / area / bar / stacked
  - cleans itself up if the page is loaded again (prevents duplicate listeners)
*/
(function () {

  // If this widget gets initialized again (AJAX navigation / partial reload),
  // clear the previous instance first so we don't end up with two charts fighting.
  if (window.__securityChargesChartPage && typeof window.__securityChargesChartPage.destroy === 'function') {
    window.__securityChargesChartPage.destroy();
  }

  let mode = 'line';        // current chart style
  let controller = null;    // AbortController to cancel in-flight fetch calls
  let loadToken = 0;        // used to ignore outdated fetch responses
  let chart = null;         // current Chart.js instance

  // Same budget/actual colors used across the report pages
  const COLORS = { budget:'#007bff', actual:'#28a745' };

  // Quick DOM helpers
  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.from(document.querySelectorAll(sel)); }

  // Chart.js sometimes leaves internal state even after destroy().
  // Replacing the canvas gives us a genuinely clean redraw.
  function replaceCanvas(id){
    const old = document.getElementById(id);
    if (!old) return null;

    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = id;

    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }

  // Turn a hex color into rgba(...) so we can do a light fill in "area" mode
  function rgba(hex, a){
    const c = (hex || '').replace('#','');
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  // Datasets are mostly the same, but the styling changes depending on mode
  function buildDatasets(payload){
    const isBar  = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');

    const base = [
      { label:'Monthly Budget', data: payload.budget || [], borderColor: COLORS.budget, borderWidth: 2 },
      { label:'Monthly Actual', data: payload.actual || [], borderColor: COLORS.actual, borderWidth: 2 }
    ];

    return base.map(d => isBar
      ? {
          ...d,
          backgroundColor: d.borderColor
        }
      : {
          ...d,
          backgroundColor: fillOn ? rgba(d.borderColor, 0.15) : 'transparent',
          fill: fillOn,
          tension: 0.3,
          pointRadius: 4
        }
    );
  }

  // Options: stacked bars vs non-stacked, and points are hidden for bar charts
  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar   = (mode === 'bar' || stacked);

    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Security Charges — Budget vs Actual' },

        // Tooltip formatting (currency in a readable way)
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

  // Fetch data + rebuild the chart from scratch
  async function hardLoad(){
    const myToken = ++loadToken;

    // Cancel the previous request if the user clicks quickly
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    // Destroy any existing chart instance
    if (chart) {
      try { chart.destroy(); } catch(e){}
      chart = null;
    }

    // Fresh canvas context every time
    const ctx = replaceCanvas('securityChart');
    if (!ctx) return;

    // Cache busting so the browser won't reuse old responses
    const url = 'ajax-security-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = { cache:'reload', headers:{ 'Cache-Control':'no-store' } };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const payload = await res.json();

      // If a newer load started after this one, ignore this response
      if (myToken !== loadToken) return;

      // Stacked is still a "bar" chart, just with stacked axes
      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';

      chart = new Chart(ctx, {
        type,
        data: { labels: payload.labels || [], datasets: buildDatasets(payload) },
        options: buildOptions()
      });

    } catch(err) {
      if (err && err.name === 'AbortError') return; // normal if a request was cancelled
      console.error('Security Charges chart load failed:', err);
    }
  }

  // Dropdown handler: switch mode and redraw
  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#securityChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  // Hook up dropdown click events
  $all('.security-chart-type').forEach(a => a.addEventListener('click', onTypeClick));

  // First load
  hardLoad();

  // Expose destroy() so we can clean up on partial page reloads
  window.__securityChargesChartPage = {
    destroy(){
      const ctl = controller;
      controller = null;

      try { ctl?.abort(); } catch(e){}

      if (chart) {
        try { chart.destroy(); } catch(e){}
        chart = null;
      }

      $all('.security-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__securityChargesChartPage = null;
    }
  };

})();
</script>
