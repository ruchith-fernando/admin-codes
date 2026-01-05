<!-- water-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Header: chart title on the left, chart style selector on the right -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Water Consumption Chart</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="waterChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="waterChartTypeLabel">Line</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="waterChartTypeBtn">
            <li><a class="dropdown-item water-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item water-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item water-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item water-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <!-- The chart lives inside this canvas. We sometimes replace the canvas to fully reset Chart.js -->
      <div class="chart-container-fluid">
        <canvas id="waterChart"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
/*
  WATER CHART MODULE

  Small self-contained module that:
  - loads chart data from ajax-water-chart.php
  - draws the chart using Chart.js
  - lets the user switch between line/area/bar/stacked
  - cleans up after itself if the page is reloaded via AJAX/navigation
*/
(function () {

  // If this page gets injected again, kill the previous instance cleanly
  if (window.__waterChartPage && typeof window.__waterChartPage.destroy === 'function') {
    window.__waterChartPage.destroy();
  }

  let mode = 'line';          // current chart mode (line/area/bar/stacked)
  let controller = null;      // AbortController for cancelling in-flight fetches
  let loadToken = 0;          // simple counter to ignore old responses
  let chart = null;           // Chart.js instance

  // Two consistent colors used across all chart modes
  const COLORS = { budget:'#007bff', actual:'#28a745' };

  // Tiny helpers so the code stays readable
  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.from(document.querySelectorAll(sel)); }

  // Chart.js can get weird if you reuse the same canvas after destroy().
  // Replacing the canvas gives us a clean slate every time.
  function replaceCanvas(id){
    const old = document.getElementById(id);
    if (!old) return null;

    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = id;

    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }

  // Convert hex color (#rrggbb) into rgba(...) so we can do a soft fill for "area" mode
  function rgba(hex, a){
    const c = hex.replace('#','');
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  // Build Chart.js datasets depending on mode:
  // - bar/stacked: needs backgroundColor
  // - area: uses translucent fill
  // - line: standard line with points
  function buildDatasets(payload){
    const isBar  = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');

    const base = [
      { label:'Monthly Budget', data:payload.budget, borderColor:COLORS.budget, borderWidth:2 },
      { label:'Monthly Actual', data:payload.actual, borderColor:COLORS.actual, borderWidth:2 }
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

  // Chart config that adapts slightly per mode (stacked vs not, points vs none)
  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar   = (mode === 'bar' || stacked);

    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Water Budget vs Actual' }
      },
      elements: {
        line:  { tension:0.3 },
        point: { radius: isBar ? 0 : 4 }
      },
      scales: {
        x: { stacked },
        y: {
          stacked,
          beginAtZero: true,
          // Display numbers as currency (Rs.)
          ticks: { callback: v => 'Rs. ' + Number(v).toLocaleString() }
        }
      }
    };
  }

  // Fetch data + redraw from scratch (safe even when switching modes quickly)
  async function hardLoad(){
    const myToken = ++loadToken;

    // Cancel the previous request if it's still running
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    // Tear down old chart instance before creating a new one
    if (chart) {
      try { chart.destroy(); } catch(e){}
      chart = null;
    }

    // New canvas = clean Chart.js state
    const ctx = replaceCanvas('waterChart');
    if (!ctx) return;

    // Cache-busting params so we always get fresh data
    const url = 'ajax-water-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = { cache:'reload', headers:{ 'Cache-Control':'no-store' } };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const payload = await res.json();

      // If a newer request already started, ignore this response
      if (myToken !== loadToken) return;

      // Chart.js uses "bar" type for both normal + stacked bars
      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';

      chart = new Chart(ctx, {
        type,
        data: { labels: payload.labels, datasets: buildDatasets(payload) },
        options: buildOptions()
      });

    } catch(err) {
      if (err && err.name === 'AbortError') return; // expected during quick switches
      console.error('Water chart load failed:', err);
    }
  }

  // Handle dropdown clicks (switch mode, update label, reload chart)
  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#waterChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  // Wire up dropdown items
  $all('.water-chart-type').forEach(a => a.addEventListener('click', onTypeClick));

  // First render
  hardLoad();

  // Expose a cleanup method so we can safely re-init if the page is loaded again
  window.__waterChartPage = {
    destroy(){
      const ctl = controller;
      controller = null;

      try { ctl?.abort(); } catch(e){}

      if (chart) {
        try { chart.destroy(); } catch(e){}
        chart = null;
      }

      $all('.water-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__waterChartPage = null;
    }
  };

})();
</script>
