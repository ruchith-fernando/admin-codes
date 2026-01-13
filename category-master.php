<?php
// category-master.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">Category Master — New / Edit</h5>

      <div id="catAlert"></div>
      <input type="hidden" id="catId" value="0">

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">GL</label>
          <select id="catGl" class="form-select">
            <option value="">Loading...</option>
          </select>
          <div class="form-text">Categories belong to one GL.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Active</label>
          <select id="catActive" class="form-select">
            <option value="1" selected>Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Sort Order</label>
          <input type="number" id="catSort" class="form-control" value="0" min="0">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-bold">Category Code</label>
          <input type="text" id="catCode" class="form-control" placeholder="e.g. UNIF / SHIRT / MUG30">
          <div class="mt-2" id="catCodeBox"></div>
          <div class="form-text">Optional. Unique within the GL.</div>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-bold">Category Name</label>
          <input type="text" id="catName" class="form-control" placeholder="e.g. Uniforms / Mugs / Pens">
          <div class="mt-2" id="catNameBox"></div>
          <div class="form-text">Must be unique within the GL.</div>
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Notes</label>
          <input type="text" id="catNotes" class="form-control" maxlength="500">
        </div>

        <div class="col-md-12 d-flex justify-content-end">
          <button class="btn btn-success" id="btnCatSubmit" type="button">Submit</button>
        </div>
      </div>

      <hr class="my-4">

      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h6 class="mb-0 text-secondary">Saved Categories</h6>
        <div class="d-flex gap-2">
          <input type="text" id="catSearch" class="form-control" style="min-width:260px;" placeholder="Type to search... (GL / name / code)">
          <select id="catPerPage" class="form-select" style="width:120px;">
            <option value="10" selected>10 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
          </select>
        </div>
      </div>

      <div class="mt-2" id="catListAlert"></div>

      <div class="table-responsive mt-2">
        <table class="table table-sm table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:80px;">ID</th>
              <th style="width:140px;">GL</th>
              <th style="width:140px;">Code</th>
              <th>Name</th>
              <th style="width:80px;">Active</th>
              <th style="width:110px;">Action</th>
            </tr>
          </thead>
          <tbody id="catTbody">
            <tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex align-items-center justify-content-between mt-2">
        <div class="text-muted" id="catPagerInfo">—</div>
        <div class="btn-group" role="group">
          <button class="btn btn-outline-secondary btn-sm" id="catPrev" type="button">Prev</button>
          <button class="btn btn-outline-secondary btn-sm" id="catNext" type="button">Next</button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  let page = 1;
  let pages = 1;
  let loadingList = false;
  let tSearch=null, tCode=null, tName=null;

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }

  function normCode(v){ return (v||'').toString().trim().toUpperCase(); }
  function normText(v){ return (v||'').toString().trim(); }

  function clearForm(){
    $('#catId').val('0');
    $('#catCode').val('');
    $('#catName').val('');
    $('#catSort').val('0');
    $('#catActive').val('1');
    $('#catNotes').val('');
    $('#catCodeBox').html('');
    $('#catNameBox').html('');
  }

  function payload(){
    const code = normCode($('#catCode').val());
    $('#catCode').val(code);

    return {
      category_id: ($('#catId').val()||'0').trim(),
      gl_id: ($('#catGl').val()||'').trim(),
      category_code: code,
      category_name: normText($('#catName').val()),
      sort_order: ($('#catSort').val()||'0').trim(),
      is_active: ($('#catActive').val()||'1').trim(),
      notes: normText($('#catNotes').val())
    };
  }

  function loadGLs(selected){
    $('#catGl').html('<option value="">Loading...</option>');
    $.post('category-master-api.php', { action:'load_gls' }, function(html){
      $('#catGl').html('<option value="">-- Select GL --</option>' + html);
      if (selected) $('#catGl').val(String(selected));
    }).fail(function(xhr){
      $('#catGl').html('<option value="">-- Select GL --</option>');
      $('#catAlert').html(bsAlert('danger','Server error loading GLs: ' + xhr.status));
    });
  }

  function checkCode(){
    const p = payload();
    if (!p.gl_id || !p.category_code) { $('#catCodeBox').html(''); return; }
    $('#catCodeBox').html('<div class="text-muted">Checking code...</div>');
    $.post('category-master-api.php', {
      action:'check_code',
      category_id: p.category_id,
      gl_id: p.gl_id,
      category_code: p.category_code
    }, function(html){
      $('#catCodeBox').html(html);
    }).fail(function(xhr){
      $('#catCodeBox').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function checkName(){
    const p = payload();
    if (!p.gl_id || !p.category_name) { $('#catNameBox').html(''); return; }
    $('#catNameBox').html('<div class="text-muted">Checking name...</div>');
    $.post('category-master-api.php', {
      action:'check_name',
      category_id: p.category_id,
      gl_id: p.gl_id,
      category_name: p.category_name
    }, function(html){
      $('#catNameBox').html(html);
    }).fail(function(xhr){
      $('#catNameBox').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function save(){
    const p = payload();

    if (!p.gl_id) { $('#catAlert').html(bsAlert('danger','GL is required.')); return; }
    if (!p.category_name) { $('#catAlert').html(bsAlert('danger','Category Name is required.')); return; }

    $('#catAlert').html('');
    $('#btnCatSubmit').prop('disabled', true).text('Saving...');

    $.post('category-master-api.php', $.extend({ action:'save' }, p), function(html){
      $('#catAlert').html(html);

      // refresh list, then clear form
      loadList(1);
      clearForm();

    }).fail(function(xhr){
      $('#catAlert').html(bsAlert('danger','Server error: ' + xhr.status));
    }).always(function(){
      $('#btnCatSubmit').prop('disabled', false).text('Submit');
    });
  }

  function loadOne(id){
    $('#catAlert').html('<div class="text-muted">Loading category...</div>');
    $.ajax({
      url:'category-master-api.php',
      method:'POST',
      dataType:'json',
      data:{ action:'load_one', category_id:id }
    }).done(function(res){
      if (!res || !res.ok || !res.category) {
        $('#catAlert').html(bsAlert('danger', (res && res.error) ? res.error : 'Load failed.'));
        return;
      }
      const c = res.category;

      $('#catId').val(String(c.category_id||0));
      $('#catCode').val(c.category_code||'');
      $('#catName').val(c.category_name||'');
      $('#catSort').val(String(c.sort_order||0));
      $('#catActive').val(String(c.is_active||1));
      $('#catNotes').val(c.notes||'');

      loadGLs(c.gl_id);
      setTimeout(function(){
        $('#catGl').val(String(c.gl_id||''));
      }, 200);

      setTimeout(function(){ checkCode(); checkName(); }, 300);
      $('#catAlert').html(bsAlert('success','Loaded for editing. Change fields and Submit.'));
    }).fail(function(xhr){
      $('#catAlert').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function loadList(goPage){
    if (loadingList) return;
    loadingList = true;

    const q = ($('#catSearch').val()||'').trim();
    const per = parseInt($('#catPerPage').val()||'10',10);
    page = goPage || page;

    $('#catListAlert').html('');
    $('#catTbody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

    $.ajax({
      url:'category-master-api.php',
      method:'POST',
      dataType:'json',
      data:{
        action:'list',
        q:q,
        page:page,
        per_page:per
      }
    }).done(function(res){
      if (!res || !res.ok) {
        $('#catListAlert').html(bsAlert('danger', (res && res.error) ? res.error : 'List load failed.'));
        return;
      }

      pages = res.pages || 1;
      page = res.page || 1;

      const rows = res.rows || [];
      if (!rows.length){
        $('#catTbody').html('<tr><td colspan="6" class="text-center text-muted">No categories found</td></tr>');
      } else {
        const html = rows.map(r => {
          const act = (String(r.is_active)==='1') ? 'Yes' : 'No';
          return `
            <tr>
              <td>${r.category_id}</td>
              <td>${escapeHtml(r.gl_code||'')}</td>
              <td>${escapeHtml(r.category_code||'')}</td>
              <td>${escapeHtml(r.category_name||'')}</td>
              <td>${act}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary btnEditCat" data-id="${r.category_id}" type="button">Edit</button>
              </td>
            </tr>
          `;
        }).join('');
        $('#catTbody').html(html);
      }

      $('#catPagerInfo').text(`Showing ${res.from}-${res.to} of ${res.total} | Page ${page}/${pages}`);
      $('#catPrev').prop('disabled', page<=1);
      $('#catNext').prop('disabled', page>=pages);

    }).fail(function(xhr){
      $('#catListAlert').html(bsAlert('danger','Server error: ' + xhr.status));
    }).always(function(){
      loadingList = false;
    });
  }

  function escapeHtml(s){
    return (s||'').toString()
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  // events (blur only)
  $('#catGl').on('change', function(){
    if ($('#catCode').val().trim()) checkCode();
    if ($('#catName').val().trim()) checkName();
  });

  $('#catCode').on('blur', function(){
    clearTimeout(tCode);
    tCode = setTimeout(checkCode, 120);
  });

  $('#catName').on('blur', function(){
    clearTimeout(tName);
    tName = setTimeout(checkName, 120);
  });

  $('#btnCatSubmit').on('click', save);

  $('#catSearch').on('input', function(){
    clearTimeout(tSearch);
    tSearch = setTimeout(function(){ loadList(1); }, 250);
  });

  $('#catPerPage').on('change', function(){ loadList(1); });

  $('#catPrev').on('click', function(){ if (page>1) loadList(page-1); });
  $('#catNext').on('click', function(){ if (page<pages) loadList(page+1); });

  $(document).on('click', '.btnEditCat', function(){
    const id = parseInt($(this).data('id')||'0',10);
    if (id>0) loadOne(id);
  });

  // init
  loadGLs();
  loadList(1);

})(jQuery);
</script>
