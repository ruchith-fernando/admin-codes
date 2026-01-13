<?php
// item-master-list.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0 text-primary">Items — List</h5>
        <button class="btn btn-outline-secondary" id="btnReloadItems" type="button">Reload</button>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-4">
          <input type="text" id="itSearch" class="form-control" placeholder="Search by code / name / GL / UOM...">
        </div>
      </div>

      <div id="itListAlert"></div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:110px;">Code</th>
              <th>Name</th>
              <th style="width:180px;">GL</th>
              <th style="width:90px;">UOM</th>
              <th style="width:70px;">Active</th>
              <th style="width:270px;">Barcode</th>
              <th style="width:90px;">Action</th>
            </tr>
          </thead>
          <tbody id="itListBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/JsBarcode.all.min.js"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script> -->

<script>
(function($){
  'use strict';
  let allRows = [];

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }

  function escapeHtml(s){
    return (s||'').toString()
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function escapeAttr(s){ return escapeHtml(s).replace(/`/g,'&#096;'); }

  function renderTable(rows){
    const q = ($('#itSearch').val()||'').toLowerCase().trim();
    const filtered = !q ? rows : rows.filter(r => (
      (r.item_code||'').toLowerCase().includes(q) ||
      (r.item_name||'').toLowerCase().includes(q) ||
      (r.gl_label||'').toLowerCase().includes(q) ||
      (r.uom||'').toLowerCase().includes(q)
    ));

    const html = filtered.map(r => {
      const act = (String(r.is_active) === '1') ? 'Yes' : 'No';
      const bc = r.barcode_value || r.item_code;
      return `
        <tr>
          <td><b>${escapeHtml(r.item_code||'')}</b></td>
          <td>${escapeHtml(r.item_name||'')}</td>
          <td>${escapeHtml(r.gl_label||'')}</td>
          <td>${escapeHtml(r.uom||'')}</td>
          <td>${act}</td>
          <td><svg class="bcSvg" data-value="${escapeAttr(bc)}"></svg></td>
          <td><a class="btn btn-sm btn-outline-primary" href="item-master-entry.php?item_id=${r.item_id}">Edit</a></td>
        </tr>
      `;
    }).join('');

    $('#itListBody').html(html || `<tr><td colspan="7" class="text-center text-muted">No items</td></tr>`);

    document.querySelectorAll('.bcSvg').forEach(svg => {
      const v = svg.getAttribute('data-value') || '';
      if (!v) return;
      JsBarcode(svg, v, { format:'CODE128', displayValue:true, height:45, margin:5 });
    });
  }

  function loadItems(){
    $('#itListAlert').html('');
    $('#itListBody').html('<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>');
    $.getJSON('item-master-list-data.php', function(res){
      if (!res || !res.ok) {
        $('#itListAlert').html(bsAlert('danger', (res && res.error) ? res.error : 'Load failed'));
        return;
      }
      allRows = res.rows || [];
      renderTable(allRows);
    }).fail(function(xhr){
      $('#itListAlert').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  $('#btnReloadItems').on('click', loadItems);
  $('#itSearch').on('input', function(){ renderTable(allRows); });

  loadItems();
})(jQuery);
</script>
