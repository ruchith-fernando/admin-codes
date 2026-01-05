<!-- tea-all-graph-report.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Tea — Head Office + Branches (Budget vs Actual)</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="teaAllChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="teaAllChartTypeLabel">Line</span>
          </button>

          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="teaAllChartTypeBtn">
            <li><a class="dropdown-item teaAll-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item teaAll-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item teaAll-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item teaAll-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <div class="chart-container-fluid">
        <canvas id="teaAllChart"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
(function () {

  if (window.__teaAllChartPage && typeof window.__teaAllChartPage.destroy === 'function') {
    window.__teaAllChartPage.destroy();
  }

  let mode = 'line';
  let controller = null;
  let loadToken = 0;
  let chart = null;

  // 4-series colors (keep consistent / readable)
  const COLORS = {
    ho_budget:'#007bff',
    ho_actual:'#28a745',
    br_budget:'#17a2b8',
    br_actual:'#ffc107'
  };

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
    const r = parseInt(c.slice(0,2),16);
    const g = parseInt(c.slice(2,4),16);
    const b = parseInt(c.slice(4,6),16);
    return `rgba(${r},${g},${b},${a})`;
  }

  function buildDatasets(payload){
    const isBar  = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');
    const stacked = (mode === 'stacked');

    const base = [
      { key:'ho_budget', label:'HO Budget',    color: COLORS.ho_budget, stack: 'Budget' },
      { key:'br_budget', label:'Branch Budget',color: COLORS.br_budget, stack: 'Budget' },
      { key:'ho_actual', label:'HO Actual',    color: COLORS.ho_actual, stack: 'Actual' },
      { key:'br_actual', label:'Branch Actual',color: COLORS.br_actual, stack: 'Actual' }
    ];

    return base.map(s => {
      const data = payload[s.key] || [];
      if (isBar) {
        return {
          label: s.label,
          data,
          backgroundColor: s.color,
          borderColor: s.color,
          borderWidth: 1,
          // stack budgets together, actuals together (only when stacked mode)
          stack: stacked ? s.stack : undefined
        };
      }
      return {
        label: s.label,
        data,
        borderColor: s.color,
        borderWidth: 2,
        backgroundColor: fillOn ? rgba(s.color, 0.15) : 'transparent',
        fill: fillOn,
        tension: 0.3,
        pointRadius: 4
      };
    });
  }

  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar   = (mode === 'bar' || stacked);

    return {
      responsive: true,
      plugins: {
        legend: { position:'top' },
        title:  { display:true, text:'Tea — Head Office + Branches (Budget vs Actual)' },
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

  async function hardLoad(){
    const myToken = ++loadToken;

    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    if (chart) {
      try { chart.destroy(); } catch(e){}
      chart = null;
    }

    const ctx = replaceCanvas('teaAllChart');
    if (!ctx) return;

    const url = 'ajax-tea-all-chart.php?ts=' + Date.now() + '&rnd=' + Math.random().toString(36).slice(2);

    try {
      const opts = { cache:'reload', headers:{ 'Cache-Control':'no-store' } };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;

      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const payload = await res.json();
      if (myToken !== loadToken) return;

      const type = (mode === 'bar' || mode === 'stacked') ? 'bar' : 'line';

      chart = new Chart(ctx, {
        type,
        data: { labels: payload.labels || [], datasets: buildDatasets(payload) },
        options: buildOptions()
      });

    } catch(err) {
      if (err && err.name === 'AbortError') return;
      console.error('Tea ALL chart load failed:', err);
    }
  }

  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#teaAllChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  $all('.teaAll-chart-type').forEach(a => a.addEventListener('click', onTypeClick));
  hardLoad();

  window.__teaAllChartPage = {
    destroy(){
      const ctl = controller;
      controller = null;
      try { ctl?.abort(); } catch(e){}

      if (chart) { try { chart.destroy(); } catch(e){} chart = null; }

      $all('.teaAll-chart-type').forEach(a => a.removeEventListener('click', onTypeClick));
      window.__teaAllChartPage = null;
    }
  };

})();
</script>
