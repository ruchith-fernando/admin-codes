<!-- postage-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Header row: title on the left, chart style dropdown on the right -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Postage & Stamps — Budget vs Actual</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="postageChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="postageChartTypeLabel">Line</span>
          </button>

          <!-- User can switch how the chart is drawn (we rebuild the chart each time) -->
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="postageChartTypeBtn">
            <li><a class="dropdown-item postage-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item postage-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item postage-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item postage-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <!-- Chart.js renders into this canvas -->
      <div class="chart-container-fluid">
        <canvas id="postageChart"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
/*
  POSTAGE CHART MODULE

  Handles everything needed for this widget:
  - loads data from ajax-postage-chart.php
  - renders "Budget vs Actual" with Chart.js
  - supports switching chart styles (line/area/bar/stacked)
  - cleans itself up if the page is loaded again (prevents duplicate listeners)
*/
(function () {

  // If the page is injected again, destroy the previous instance first
  if (window.__postageChartPage && typeof window.__postageChartPage.destroy === 'function') {
    window.__postageChartPage.destroy();
  }

  let mode = 'line';        // current display mode
  let controller = null;    // AbortController to cancel fetch requests
  let loadToken = 0;        // used to ignore late responses from older requests
  let chart = null;         // Chart.js instance

  // Keep chart colors consistent with the rest of the reports
  const COLORS = { budget:'#007bff', actual:'#28a745' };

  // Simple DOM helper shortcuts
  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.from(document.querySelectorAll(sel)); }

  // Fully reset Chart.js state by swapping the canvas element
  function replaceCanvas(id){
    const old = document.getElementById(id);
    if (!old) return null;

    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = id;

    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }

  // Convert hex to rgba() for the filled "area" mode
  function rgba(hex, a){
    const c = hex.replace('#','');
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  // Build datasets depending on chart type:
  // - bar/stacked: uses solid bars
  // - area: uses translucent fill under the line
  // - line: standard line with points
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

  // Chart options with a couple of tweaks based on mode
  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar   = (mode === 'bar' || stacked);

    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Postage & Stamps — Budget vs Actual' },

        // Tooltip formatting (currency)
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

  // Fetch data + redraw from scratch (safe for rapid mode switching)
  async function hardLoad(){
    const myToken = ++loadToken;

    // Cancel previous request if it’s still running
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    // Tear down any existing chart
    if (chart) {
      try { chart.destroy(); } catch(e){}
      chart = null;
    }

    // Fresh canvas context every time
    const ctx = replaceCanvas('postageChart');
    if (!ctx) return;

    // Cache-busting params so the browser doesn’t reuse old responses
    const url = 'ajax-postage-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = { cache:'reload', headers:{ 'Cache-Control':'no-store' } };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const payload = await res.json();

      // Ignore if a newer request already started
      if (myToken !== loadToken) return;

      // Stacked uses "bar" type with stacked axes
      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';

      chart = new Chart(ctx, {
        type,
        data: { labels: payload.labels || [], datasets: buildDatasets(payload) },
        options: buildOptions()
      });

    } catch(err) {
      if (err && err.name === 'AbortError') return; // normal when switching modes quickly
      console.error('Postage chart load failed:', err);
    }
  }

  // Dropdown click handler: update mode + label, then reload chart
  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#postageChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  // Hook up dropdown options
  $all('.postage-chart-type').forEach(a => a.addEventListener('click', onTypeClick));

  // Initial draw
  hardLoad();

  // Expose destroy() so we can cleanly re-init later if needed
  window.__postageChartPage = {
    destroy(){
      const ctl = controller;
      controller = null;

      try { ctl?.abort(); } catch(e){}

      if (chart) {
        try { chart.destroy(); } catch(e){}
        chart = null;
      }

      $all('.postage-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__postageChartPage = null;
    }
  };

})();
</script>
