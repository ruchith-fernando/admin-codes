<!-- electricity-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Title (left) + Chart Type dropdown (right) -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Electricity Consumption Chart</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="elecChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="elecChartTypeLabel">Line</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="elecChartTypeBtn">
            <li><a class="dropdown-item elec-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item elec-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item elec-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item elec-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <div class="chart-container-fluid">
        <canvas id="electricityChart"></canvas>
      </div>
    </div>
  </div>
</div>
<script>
/* ELECTRICITY CHART MODULE (no globals, safe on AJAX reloads) */
(function () {
  if (window.__electricityChartPage && typeof window.__electricityChartPage.destroy === 'function') {
    window.__electricityChartPage.destroy();
  }

  let mode = 'line';
  let controller = null;
  let loadToken = 0;
  let chart = null;

  const COLORS = { budget:'#007bff', total:'#28a745', branch:'#ffc107', yard:'#17a2b8', bungalow:'#dc3545' };

  function $(sel){ return document.querySelector(sel); }
  function $all(sel){ return Array.from(document.querySelectorAll(sel)); }
  function replaceCanvas(id){
    const old = document.getElementById(id);
    if (!old) return null;
    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = id;
    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }
  function rgba(hex, a){
    const c = hex.replace('#','');
    const r = parseInt(c.slice(0,2),16), g = parseInt(c.slice(2,4),16), b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }
  async function clearWebCaches(){
    try { if ('caches' in window && caches.keys) { const ks = await caches.keys(); await Promise.all(ks.map(k => caches.delete(k))); } } catch(e){}
    try { performance.clearResourceTimings(); } catch(e){}
  }

  function buildDatasets(payload){
    const isBar = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');
    const base = [
      { label:'Monthly Budget',     data:payload.budget,        borderColor:COLORS.budget,   borderWidth:2 },
      { label:'Total Actual',       data:payload.total_actual,  borderColor:COLORS.total,    borderWidth:2 },
      { label:'Branches (Numeric)', data:payload.branch_total,  borderColor:COLORS.branch,   borderWidth:2 },
      { label:'Yards (Y*)',         data:payload.yard_total,    borderColor:COLORS.yard,     borderWidth:2 },
      { label:'Bungalows (B*)',     data:payload.bungalow_total,borderColor:COLORS.bungalow, borderWidth:2 }
    ];
    return base.map(d => isBar
      ? { ...d, backgroundColor: d.borderColor }
      : { ...d, backgroundColor: fillOn ? rgba(d.borderColor, 0.15) : 'transparent', fill: fillOn, tension: 0.3, pointRadius: 4 }
    );
  }

  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar = (mode === 'bar' || stacked);
    return {
      responsive: true,
      plugins: { legend: { position: 'top' }, title: { display: true, text: 'Electricity Budget vs Actual by Category' } },
      elements: { line: { tension: 0.3 }, point: { radius: isBar ? 0 : 4 } },
      scales: {
        x: { stacked },
        y: { stacked, beginAtZero: true, ticks: { callback: v => 'Rs. ' + Number(v).toLocaleString() } }
      }
    };
  }

  async function hardLoad(){
    const myToken = ++loadToken;

    // Abort any in-flight request for older runs
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;              // <-- SAFE CAPTURE

    await clearWebCaches();

    // Kill old chart instance (if any)
    if (chart) { try { chart.destroy(); } catch(e){} chart = null; }

    const ctx = replaceCanvas('electricityChart');
    if (!ctx) return;

    const url = 'ajax-electricity-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = {
        cache: 'reload',
        headers: { 'Cache-Control':'no-store, no-cache, must-revalidate, max-age=0', 'Pragma':'no-cache', 'Expires':'0' }
      };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;   // <-- ONLY PASS SIGNAL IF PRESENT

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const payload = await res.json();
      if (myToken !== loadToken) return;

      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';
      chart = new Chart(ctx, { type, data: { labels: payload.labels, datasets: buildDatasets(payload) }, options: buildOptions() });
      window.electricityChartInstance = chart; // debug
    } catch (err) {
      // Ignore harmless aborts from page swaps
      if (err && (err.name === 'AbortError' || /aborted|abort/i.test(String(err.message)))) return;
      console.error('Electricity chart load failed:', err);
    }
  }

  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#elecChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  // bind + initial load
  $all('.elec-chart-type').forEach(a => a.addEventListener('click', onTypeClick));
  hardLoad();

  // expose destroy for next AJAX load
  window.__electricityChartPage = {
    destroy(){
      const ctl = controller; controller = null;   // <-- prevent races
      try { ctl?.abort(); } catch(e){}
      if (chart) { try { chart.destroy(); } catch(e){} chart = null; }
      $all('.elec-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__electricityChartPage = null;
    }
  };
})();
</script>

