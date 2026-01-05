<!-- printing-chart.php -->
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <!-- Title + Chart Type Dropdown -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 text-primary">Printing & Stationary Chart</h5>

        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" id="printingChartTypeBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="printingChartTypeLabel">Line</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="printingChartTypeBtn">
            <li><a class="dropdown-item printing-chart-type" data-mode="line"    href="#">Line</a></li>
            <li><a class="dropdown-item printing-chart-type" data-mode="area"    href="#">Area (filled)</a></li>
            <li><a class="dropdown-item printing-chart-type" data-mode="bar"     href="#">Bar</a></li>
            <li><a class="dropdown-item printing-chart-type" data-mode="stacked" href="#">Stacked Bar</a></li>
          </ul>
        </div>
      </div>

      <div class="chart-container-fluid">
        <canvas id="printingChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
/* PRINTING CHART MODULE */
(function () {
  if (window.__printingChartPage && typeof window.__printingChartPage.destroy === 'function') {
    window.__printingChartPage.destroy();
  }

  let mode = 'line';
  let controller = null;
  let loadToken = 0;
  let chart = null;

  const COLORS = { budget:'#6f42c1', actual:'#fd7e14' }; // purple + orange

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

  function buildDatasets(payload){
    const isBar = (mode === 'bar' || mode === 'stacked');
    const fillOn = (mode === 'area');
    const base = [
      { label:'Monthly Budget', data:payload.budget, borderColor:COLORS.budget, borderWidth:2 },
      { label:'Total Actual',   data:payload.actual, borderColor:COLORS.actual, borderWidth:2 }
    ];
    return base.map(d => isBar
      ? { ...d, backgroundColor: d.borderColor }
      : { ...d, backgroundColor: fillOn ? rgba(d.borderColor, 0.15) : 'transparent', fill: fillOn, tension:0.3, pointRadius:4 }
    );
  }

  function buildOptions(){
    const stacked = (mode === 'stacked');
    const isBar = (mode === 'bar' || stacked);
    return {
      responsive: true,
      plugins: { legend:{ position:'top' }, title:{ display:true, text:'Printing Budget vs Actual' } },
      elements: { line:{ tension:0.3 }, point:{ radius:isBar?0:4 } },
      scales: {
        x: { stacked },
        y: { stacked, beginAtZero:true, ticks:{ callback:v=>'Rs. '+Number(v).toLocaleString() } }
      }
    };
  }

  async function hardLoad(){
    const myToken = ++loadToken;
    try { controller?.abort(); } catch(e){}
    try { controller = new AbortController(); } catch(e){ controller = null; }
    const ctl = controller;

    if (chart) { try{ chart.destroy(); }catch(e){} chart = null; }
    const ctx = replaceCanvas('printingChart'); if (!ctx) return;

    const url = 'ajax-printing-chart.php?ts='+Date.now()+'&rnd='+Math.random().toString(36).slice(2);
    try {
      const opts = { cache:'reload', headers:{'Cache-Control':'no-store'} };
      if (ctl && 'signal' in ctl) opts.signal = ctl.signal;
      const res = await fetch(url, opts);
      if (!res.ok) throw new Error('HTTP '+res.status);
      const payload = await res.json();
      if (myToken !== loadToken) return;
      const type = (mode === 'bar' || mode === 'stacked') ? 'bar':'line';
      chart = new Chart(ctx,{ type, data:{ labels:payload.labels, datasets:buildDatasets(payload) }, options:buildOptions() });
    } catch(err){
      if (err && (err.name==='AbortError')) return;
      console.error('Printing chart load failed:', err);
    }
  }

  function onTypeClick(e){
    e.preventDefault();
    mode = this.getAttribute('data-mode');
    $('#printingChartTypeLabel').textContent = this.textContent.trim();
    hardLoad();
  }

  $all('.printing-chart-type').forEach(a => a.addEventListener('click', onTypeClick));
  hardLoad();

  window.__printingChartPage = {
    destroy(){
      const ctl = controller; controller=null; try{ctl?.abort();}catch(e){}
      if(chart){try{chart.destroy();}catch(e){} chart=null;}
      $all('.printing-chart-type').forEach(a=>a.removeEventListener('click', onTypeClick));
      window.__printingChartPage=null;
    }
  };
})();
</script>
