<!-- tea-service-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Title (left) + Chart Type dropdown (right) -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Tea Service Budget vs Actual</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="teaChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="teaChartTypeLabel">Line</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="teaChartTypeBtn">
            <li><a class="dropdown-item tea-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item tea-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item tea-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item tea-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <div class="chart-container-fluid">
        <canvas id="teaServiceChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const NS = '.page.teaServiceGraph';

  // Remove old handlers for this namespace (when injected via AJAX repeatedly)
  $('#contentArea').off(NS);

  // Teardown previous module if present
  window.ChartPages = window.ChartPages || {};
  if (window.ChartPages.teaService) {
    try { window.ChartPages.teaService.teardown(); } catch(e){}
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
    budget: '#007bff',
    actual: '#28a745'
  };

  // ---------- builders ----------
  function buildDatasets(p){
    const isBar  = (state.mode === 'bar' || state.mode === 'stacked');
    const fillOn = (state.mode === 'area');

    const base = [
      { label:'Budget Amount', data:p.budget_amount, borderColor:COLORS.budget },
      { label:'Actual Amount', data:p.actual_amount, borderColor:COLORS.actual }
    ];

    return base.map(d => {
      if (isBar) {
        return { ...d, type:'bar', backgroundColor:d.borderColor, borderColor:d.borderColor, borderWidth:1 };
      } else {
        return {
          ...d,
          type:'line',
          borderWidth:2,
          backgroundColor: fillOn ? rgba(d.borderColor, 0.15) : 'transparent',
          fill: fillOn,
          tension: 0.3,
          pointRadius: 4
        };
      }
    });
  }

  function buildOptions(){
    const stacked = (state.mode === 'stacked'); // only meaningful for bars, harmless otherwise
    const isBar   = (state.mode === 'bar' || state.mode === 'stacked');
    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Tea Service Budget vs Actual' }
      },
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
  async function hardLoadTea(){
    const my = ++state.loadToken;

    try { state.controller?.abort(); } catch(e){}
    state.controller = new AbortController();

    await clearWebCaches();

    if (state.chart) {
      try { state.chart.destroy(); } catch(e){}
      state.chart = null;
    }

    const ctx = replaceCanvas('teaServiceChart');
    if (!ctx) return;

    const url = 'ajax-tea-service-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

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

      // Use mixed chart: base type 'line'; datasets may override to 'bar'
      state.chart = new Chart(ctx, {
        type: 'line',
        data: { labels: payload.labels, datasets: buildDatasets(payload) },
        options: buildOptions()
      });

      window.teaServiceChartInstance = state.chart; // optional: debug
    } catch (err) {
      console.error('Tea Service chart load failed:', err);
    }
  }

  // ---------- dropdown handler (delegated) ----------
  $('#contentArea').on('click' + NS, '.tea-chart-type', function(e){
    e.preventDefault();
    state.mode = this.getAttribute('data-mode');
    $('#teaChartTypeLabel').text(this.textContent.trim());
    hardLoadTea();
  });

  // Expose for external control/cleanup
  window.ChartPages.teaService = {
    reload:  hardLoadTea,
    teardown(){
      try { state.controller?.abort(); } catch(e){}
      if (state.chart) { try { state.chart.destroy(); } catch(e){} state.chart = null; }
      $('#contentArea').off(NS);
    }
  };

  // Initial load
  hardLoadTea();
})();
</script>
