<!-- staff-transport-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Title (left) + Chart Type dropdown (right) -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Staff Transport Budget vs Actual</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="stChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="stChartTypeLabel">Line</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="stChartTypeBtn">
            <li><a class="dropdown-item st-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item st-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item st-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item st-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <div class="chart-container-fluid">
        <canvas id="staffTransportChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const NS = '.page.staffTransportGraph';

  // Remove old handlers for this namespace (when injected via AJAX repeatedly)
  $('#contentArea').off(NS);

  // Teardown previous module if present
  window.ChartPages = window.ChartPages || {};
  if (window.ChartPages.staffTransport) {
    try { window.ChartPages.staffTransport.teardown(); } catch(e){}
  }

  // ---------- utils ----------
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
    return `rgba(${r}, ${g}, ${b}, ${a})`;
  }
  async function clearWebCaches(){
    try { if ('caches' in window && caches.keys) { const ks = await caches.keys(); await Promise.all(ks.map(k => caches.delete(k))); } } catch(e){}
    try { performance.clearResourceTimings(); } catch(e){}
  }

  // ---------- state ----------
  const state = {
    mode: 'line',            // 'line' | 'area' | 'bar' | 'stacked'
    controller: null,
    loadToken: 0,
    chart: null
  };

  const COLORS = {
    budget:   '#007bff', // blue
    pickme:   '#17a2b8', // teal
    kangaroo: '#fd7e14', // orange
    total:    '#28a745', // green
  };

  // ---------- builders ----------
  function makeLine(label, data, color, fill){
    return {
      label, data,
      type:'line',
      borderColor: color,
      backgroundColor: fill ? rgba(color, 0.15) : 'transparent',
      fill: !!fill,
      borderWidth:2,
      tension:0.3,
      pointRadius:4
    };
  }
  function makeBar(label, data, color, stack){
    return {
      label, data,
      type:'bar',
      backgroundColor: color,
      borderColor: color,
      borderWidth:1,
      ...(stack ? { stack } : {})
    };
  }

  function buildDatasets(p){
    const isBar  = (state.mode === 'bar' || state.mode === 'stacked');
    const isArea = (state.mode === 'area');
    const stackedId = (state.mode === 'stacked') ? 'rides' : undefined;

    if (isBar) {
      // Bars for PickMe & Kangaroo; Budget & Total as lines to avoid double-counting visuals
      const ds = [
        makeBar('PickMe',   p.pickme_amount,   COLORS.pickme,   stackedId),
        makeBar('Kangaroo', p.kangaroo_amount, COLORS.kangaroo, stackedId),
        makeLine('Budget',  p.budget_amount,   COLORS.budget,   false),
        makeLine('Total Actual', p.total_amount, COLORS.total,  false),
      ];
      return ds;
    }

    // Line/Area: all lines; Budget not filled; others filled in 'area' mode
    return [
      makeLine('Budget',       p.budget_amount,   COLORS.budget,   false),
      makeLine('PickMe',       p.pickme_amount,   COLORS.pickme,   isArea),
      makeLine('Kangaroo',     p.kangaroo_amount, COLORS.kangaroo, isArea),
      makeLine('Total Actual', p.total_amount,    COLORS.total,    isArea),
    ];
  }

  function buildOptions(){
    const stacked = (state.mode === 'stacked'); // apply only for stacked bars
    const isBar   = (state.mode === 'bar' || state.mode === 'stacked');
    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Staff Transport Budget vs Actual' },
        tooltip: { mode:'index', intersect:false }
      },
      interaction: { mode:'index', intersect:false },
      elements: { line:{ tension:0.3 }, point:{ radius: isBar ? 0 : 4 } },
      scales: {
        x: { stacked },
        y: {
          stacked,
          beginAtZero:true,
          ticks: { callback: v => 'Rs. ' + Number(v).toLocaleString() },
          title: { display:true, text:'Amount (Rs.)' }
        }
      }
    };
  }

  // ---------- hard reload ----------
  async function hardLoad(){
    const my = ++state.loadToken;

    try { state.controller?.abort(); } catch(e){}
    state.controller = new AbortController();

    await clearWebCaches();

    if (state.chart) {
      try { state.chart.destroy(); } catch(e){}
      state.chart = null;
    }

    const ctx = replaceCanvas('staffTransportChart');
    if (!ctx) return;

    const url = 'ajax-staff-transport-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const res = await fetch(url, {
        cache: 'reload',
        headers: {
          'Cache-Control': 'no-store, no-cache, must-revalidate, max-age=0',
          'Pragma': 'no-cache',
          'Expires': '0'
        },
        signal: state.controller.signal
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const payload = await res.json();
      if (my !== state.loadToken) return;

      state.chart = new Chart(ctx, {
        type: 'line',
        data: { labels: payload.labels, datasets: buildDatasets(payload) },
        options: buildOptions()
      });

      window.staffTransportChartInstance = state.chart; // optional debug
    } catch (err) {
      console.error('Staff Transport chart load failed:', err);
    }
  }

  // ---------- dropdown handler (delegated) ----------
  $('#contentArea').on('click' + NS, '.st-chart-type', function(e){
    e.preventDefault();
    state.mode = this.getAttribute('data-mode');
    $('#stChartTypeLabel').text(this.textContent.trim());
    hardLoad();
  });

  // Expose for external control/cleanup
  window.ChartPages.staffTransport = {
    reload:  hardLoad,
    teardown(){
      try { state.controller?.abort(); } catch(e){}
      if (state.chart) { try { state.chart.destroy(); } catch(e){} state.chart = null; }
      $('#contentArea').off(NS);
    }
  };

  // Initial load
  hardLoad();
})();
</script>
