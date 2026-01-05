<!-- vehicle-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Header row: chart title + chart style selector -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Vehicle Maintenance — Budget vs Actual</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="vehicleChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="vehicleChartTypeLabel">Line</span>
          </button>

          <!-- Changing this dropdown will rebuild the chart in the selected style -->
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="vehicleChartTypeBtn">
            <li><a class="dropdown-item vehicle-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item vehicle-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item vehicle-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item vehicle-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <!-- Chart.js renders into this canvas -->
      <div class="chart-container-fluid">
        <canvas id="vehicleChart"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
/*
  VEHICLE CHART MODULE

  Responsibilities:
  - fetch "budget vs actual" from ajax-vehicle-chart.php
  - render with Chart.js
  - allow switching between line/area/bar/stacked modes
  - avoid duplicate listeners if the page loads again (destroy old instance first)
*/
(function () {

  // If this widget is being re-initialized, clean up the previous one
  if (window.__vehicleChartPage && typeof window.__vehicleChartPage.destroy === 'function') {
    window.__vehicleChartPage.destroy();
  }

  let mode = 'line';        // current chart style
  let controller = null;    // AbortController for cancelling requests
  let loadToken = 0;        // helps ignore late responses from older requests
  let chart = null;         // Chart.js instance

  // Consistent color pair used everywhere
  const COLORS = { budget:'#007bff', actual:'#28a745' };

  // Lightweight DOM helpers
  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.from(document.querySelectorAll(sel)); }

  // Chart.js sometimes keeps internal state even after destroy().
  // Replacing the canvas ensures a completely clean redraw.
  function replaceCanvas(id){
    const old = document.getElementById(id);
    if (!old) return null;

    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = id;

    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }

  // Convert hex to rgba so we can do a soft fill for the "area" style
  function rgba(hex, a){
    const c = (hex || '').replace('#','');
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  // Build the datasets based on the selected mode
  function buildDatasets(payload){
    const isBar  = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');

    const base = [
      { label:'Monthly Budget', data: payload.budget || [], borderColor: COLORS.budget, borderWidth: 2 },
      { label:'Monthly Actual', data: payload.actual || [], borderColor: COLORS.actual, borderWidth: 2 }
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

  // Chart options (stacked/bar slightly different from line/area)
  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar   = (mode === 'bar' || stacked);

    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Vehicle Maintenance — Budget vs Actual' },

        // Tooltip formatting so values are shown as currency
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

  // Fetch data and redraw the chart from scratch
  async function hardLoad(){
    const myToken = ++loadToken;

    // Cancel any previous request (helps when the user changes mode quickly)
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    // Destroy the existing chart before recreating
    if (chart) {
      try { chart.destroy(); } catch(e){}
      chart = null;
    }

    // New canvas context every time
    const ctx = replaceCanvas('vehicleChart');
    if (!ctx) return;

    // Cache-busting so we always hit the server for fresh numbers
    const url = 'ajax-vehicle-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = { cache:'reload', headers:{ 'Cache-Control':'no-store' } };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const payload = await res.json();

      // If another request started after this one, ignore this result
      if (myToken !== loadToken) return;

      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';

      chart = new Chart(ctx, {
        type,
        data: { labels: payload.labels || [], datasets: buildDatasets(payload) },
        options: buildOptions()
      });

    } catch(err) {
      if (err && err.name === 'AbortError') return; // normal when switching modes fast
      console.error('Vehicle chart load failed:', err);
    }
  }

  // When user picks a chart mode, update label and reload the graph
  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#vehicleChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  // Bind dropdown click handlers
  $all('.vehicle-chart-type').forEach(a => a.addEventListener('click', onTypeClick));

  // Initial draw
  hardLoad();

  // Expose a cleanup hook (useful if the page is re-rendered via AJAX navigation)
  window.__vehicleChartPage = {
    destroy(){
      const ctl = controller;
      controller = null;

      try { ctl?.abort(); } catch(e){}

      if (chart) {
        try { chart.destroy(); } catch(e){}
        chart = null;
      }

      $all('.vehicle-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__vehicleChartPage = null;
    }
  };

})();
</script>
