<!-- telephone-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Telephone Budget vs Actual — Chart</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="telChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="telChartTypeLabel">Line</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="telChartTypeBtn">
            <li><a class="dropdown-item tel-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item tel-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item tel-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item tel-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <div class="chart-container-fluid">
        <canvas id="telephoneChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const NS = '.page.telephoneGraph';

  // Kill any old delegated handlers for this page namespace
  $('#contentArea').off(NS);

  // Teardown previous module if it exists
  window.ChartPages = window.ChartPages || {};
  if (window.ChartPages.telephone) {
    try { window.ChartPages.telephone.teardown(); } catch(e){}
  }

  // ---------- utilities ----------
  function replaceCanvas(canvasId) {
    const old = document.getElementById(canvasId);
    if (!old) return null;
    const parent = old.parentNode;
    const fresh = document.createElement('canvas');
    fresh.id = canvasId;
    parent.replaceChild(fresh, old);
    return fresh.getContext('2d');
  }
  function rgba(hex, a) {
    const c = hex.replace('#','');
    const r = parseInt(c.slice(0,2),16), g = parseInt(c.slice(2,4),16), b = parseInt(c.slice(4,6),16);
    return `rgba(${r}, ${g}, ${b}, ${a})`;
  }
  async function clearWebCaches() {
    try {
      if ('caches' in window && caches.keys) {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
      }
    } catch(e){}
    try { performance.clearResourceTimings(); } catch(e){}
  }

  // ---------- state ----------
  const state = {
    mode: 'line',                 // 'line'|'area'|'bar'|'stacked'
    controller: null,
    loadToken: 0,
    chart: null
  };

  const COLORS = {
    budget: '#007bff',
    total:  '#28a745',
    dialog: '#6610f2',
    cdma:   '#fd7e14',
    slt:    '#6c757d'
  };

  // ---------- builders ----------
  function datasets(payload, mode) {
    const isBar  = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');

    const base = [
      { label: 'Monthly Budget', data: payload.budget,       borderColor: COLORS.budget, borderWidth: 2 },
      { label: 'Total Actual',   data: payload.total_actual, borderColor: COLORS.total,  borderWidth: 2 },
      { label: 'Dialog',         data: payload.dialog_total, borderColor: COLORS.dialog, borderWidth: 2 },
      { label: 'CDMA',           data: payload.cdma_total,   borderColor: COLORS.cdma,   borderWidth: 2 },
      { label: 'SLT',            data: payload.slt_total,    borderColor: COLORS.slt,    borderWidth: 2 }
    ];

    return base.map(d => isBar
      ? { ...d, backgroundColor: d.borderColor }
      : { ...d, backgroundColor: fillOn ? rgba(d.borderColor, 0.15) : 'transparent', fill: fillOn, tension: 0.3, pointRadius: 4 }
    );
  }

  function options(mode) {
    const isBar = (mode === 'bar' || mode === 'stacked');
    const stacked = (mode === 'stacked');
    return {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        title:  { display: true, text: 'Telephone Budget vs Actual (Dialog / CDMA / SLT)' },
        tooltip: {
          callbacks: {
            footer(items) {
              if (!items?.length) return '';
              const i = items[0].dataIndex;
              const ds = this.chart.data.datasets;
              const dialog = Number(ds[2].data[i] || 0);
              const cdma   = Number(ds[3].data[i] || 0);
              const slt    = Number(ds[4].data[i] || 0);
              return 'Actual total: Rs. ' + (dialog + cdma + slt).toLocaleString();
            }
          }
        }
      },
      elements: { line: { tension: 0.3 }, point: { radius: isBar ? 0 : 4 } },
      scales: {
        x: { stacked },
        y: { stacked, beginAtZero: true, ticks: { callback: v => 'Rs. ' + Number(v).toLocaleString() } }
      }
    };
  }

  // ---------- hard reload ----------
  async function hardLoad() {
    const myToken = ++state.loadToken;

    try { state.controller?.abort(); } catch(e){}
    state.controller = new AbortController();

    await clearWebCaches();

    if (state.chart) {
      try { state.chart.destroy(); } catch(e){}
      state.chart = null;
    }

    const ctx = replaceCanvas('telephoneChart');
    if (!ctx) return;

    const url = 'ajax-telephone-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

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
      if (myToken !== state.loadToken) return;

      const chartType = (state.mode === 'bar' || state.mode === 'stacked') ? 'bar' : 'line';
      state.chart = new Chart(ctx, {
        type: chartType,
        data: { labels: payload.labels, datasets: datasets(payload, state.mode) },
        options: options(state.mode)
      });
      window.telephoneChartInstance = state.chart; // optional debug
    } catch (err) { console.error('Telephone chart load failed:', err); }
  }

  // ---------- delegated bindings ----------
  $('#contentArea').on('click' + NS, '.tel-chart-type', function(e){
    e.preventDefault();
    state.mode = this.getAttribute('data-mode');
    $('#telChartTypeLabel').text(this.textContent.trim());
    hardLoad();
  });

  // expose module for teardown/reload
  window.ChartPages.telephone = {
    reload: hardLoad,
    teardown(){
      try { state.controller?.abort(); } catch(e){}
      if (state.chart) { try { state.chart.destroy(); } catch(e){} state.chart = null; }
      $('#contentArea').off(NS);
    }
  };

  // initial load
  hardLoad();
})();
</script>
