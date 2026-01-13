<?php
// attribute-master.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">

      <h5 class="mb-3 text-primary">Attribute Master</h5>
      <div id="attrAlert"></div>

      <ul class="nav nav-tabs" id="attrTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-attr" data-bs-toggle="tab" data-bs-target="#pane-attr" type="button" role="tab">
            Attributes
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-opt" data-bs-toggle="tab" data-bs-target="#pane-opt" type="button" role="tab">
            Attribute Options
          </button>
        </li>
      </ul>

      <div class="tab-content pt-3">

        <!-- ====================== ATTRIBUTES ====================== -->
        <div class="tab-pane fade show active" id="pane-attr" role="tabpanel">

          <input type="hidden" id="aId" value="0">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Attribute Code</label>
              <input type="text" id="aCode" class="form-control" placeholder="e.g. COLOR / SIZE / GENDER">
              <div class="mt-2" id="aCodeBox"></div>
            </div>

            <div class="col-md-5">
              <label class="form-label fw-bold">Attribute Name</label>
              <input type="text" id="aName" class="form-control" placeholder="e.g. Color / Size / Gender">
              <div class="mt-2" id="aNameBox"></div>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Input Type</label>
              <select id="aType" class="form-select">
                <option value="SELECT" selected>SELECT</option>
                <option value="TEXT">TEXT</option>
                <option value="NUMBER">NUMBER</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Sort Order</label>
              <input type="number" id="aSort" class="form-control" value="0" min="0">
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Active</label>
              <select id="aActive" class="form-select">
                <option value="1" selected>Yes</option>
                <option value="0">No</option>
              </select>
            </div>

            <div class="col-md-6 d-flex align-items-end justify-content-end">
              <button class="btn btn-success w-100" id="btnAttrSubmit" type="button">Submit</button>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h6 class="mb-0 text-secondary">Saved Attributes</h6>
            <div class="d-flex gap-2">
              <input type="text" id="aSearch" class="form-control" style="min-width:260px;" placeholder="Type to search...">
              <select id="aPerPage" class="form-select" style="width:120px;">
                <option value="10" selected>10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
              </select>
            </div>
          </div>

          <div class="mt-2" id="aListAlert"></div>

          <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">ID</th>
                  <th style="width:160px;">Code</th>
                  <th>Name</th>
                  <th style="width:120px;">Type</th>
                  <th style="width:90px;">Sort</th>
                  <th style="width:80px;">Active</th>
                  <th style="width:110px;">Action</th>
                </tr>
              </thead>
              <tbody id="aTbody">
                <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="text-muted" id="aPagerInfo">—</div>
            <div class="btn-group" role="group">
              <button class="btn btn-outline-secondary btn-sm" id="aPrev" type="button">Prev</button>
              <button class="btn btn-outline-secondary btn-sm" id="aNext" type="button">Next</button>
            </div>
          </div>

        </div>

        <!-- ====================== OPTIONS ====================== -->
        <div class="tab-pane fade" id="pane-opt" role="tabpanel">

          <input type="hidden" id="oId" value="0">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Attribute</label>
              <select id="oAttr" class="form-select">
                <option value="">Loading...</option>
              </select>
              <div class="form-text">Select attribute first (Color / Size / Gender...).</div>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Option Code (optional)</label>
              <input type="text" id="oCode" class="form-control" placeholder="e.g. BLK / WHT / M / L">
              <div class="mt-2" id="oCodeBox"></div>
            </div>

            <div class="col-md-5">
              <label class="form-label fw-bold">Option Name</label>
              <input type="text" id="oName" class="form-control" placeholder="e.g. Black / White / Medium / Large">
              <div class="mt-2" id="oNameBox"></div>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Sort Order</label>
              <input type="number" id="oSort" class="form-control" value="0" min="0">
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Active</label>
              <select id="oActive" class="form-select">
                <option value="1" selected>Yes</option>
                <option value="0">No</option>
              </select>
            </div>

            <div class="col-md-6 d-flex align-items-end justify-content-end">
              <button class="btn btn-success w-100" id="btnOptSubmit" type="button">Submit</button>
            </div>
          </div>

          <hr class="my-4">

          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h6 class="mb-0 text-secondary">Saved Options</h6>
            <div class="d-flex gap-2">
              <input type="text" id="oSearch" class="form-control" style="min-width:260px;" placeholder="Type to search... (attribute / option)">
              <select id="oPerPage" class="form-select" style="width:120px;">
                <option value="10" selected>10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
              </select>
            </div>
          </div>

          <div class="mt-2" id="oListAlert"></div>

          <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:80px;">ID</th>
                  <th style="width:180px;">Attribute</th>
                  <th style="width:140px;">Code</th>
                  <th>Option Name</th>
                  <th style="width:90px;">Sort</th>
                  <th style="width:80px;">Active</th>
                  <th style="width:110px;">Action</th>
                </tr>
              </thead>
              <tbody id="oTbody">
                <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="text-muted" id="oPagerInfo">—</div>
            <div class="btn-group" role="group">
              <button class="btn btn-outline-secondary btn-sm" id="oPrev" type="button">Prev</button>
              <button class="btn btn-outline-secondary btn-sm" id="oNext" type="button">Next</button>
            </div>
          </div>

        </div>

      </div>

    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }
  function esc(s){
    return (s||'').toString()
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
  function failBox($el, xhr, textStatus, errorThrown){
    const body = (xhr.responseText||'').toString().substring(0,900);
    $el.html(bsAlert('danger',
      'Request failed: <b>'+esc(textStatus)+'</b> ('+esc(errorThrown||'')+') | HTTP: <b>'+xhr.status+
      '</b><pre class="small mt-2 mb-0">'+esc(body)+'</pre>'
    ));
  }

  /* ===================== ATTR STATE ===================== */
  let aPage=1, aPages=1, aLoading=false;
  let taSearch=null, taCode=null, taName=null;

  function aClear(){
    $('#aId').val('0');
    $('#aCode').val('');
    $('#aName').val('');
    $('#aType').val('SELECT');
    $('#aSort').val('0');
    $('#aActive').val('1');
    $('#aCodeBox').html('');
    $('#aNameBox').html('');
  }
  function aPayload(){
    const code = ($('#aCode').val()||'').trim().toUpperCase();
    $('#aCode').val(code);
    return {
      attribute_id: ($('#aId').val()||'0').trim(),
      attribute_code: code,
      attribute_name: ($('#aName').val()||'').trim(),
      input_type: ($('#aType').val()||'SELECT').trim(),
      sort_order: ($('#aSort').val()||'0').trim(),
      is_active: ($('#aActive').val()||'1').trim()
    };
  }
  function aCheckCode(){
    const p=aPayload();
    if (!p.attribute_code) { $('#aCodeBox').html(''); return; }
    $('#aCodeBox').html('<div class="text-muted">Checking code...</div>');
    $.post('attribute-master-api.php', {action:'attr_check_code', attribute_id:p.attribute_id, attribute_code:p.attribute_code})
      .done(function(html){ $('#aCodeBox').html(html); })
      .fail(function(xhr, t, e){ failBox($('#aCodeBox'), xhr, t, e); });
  }
  function aCheckName(){
    const p=aPayload();
    if (!p.attribute_name) { $('#aNameBox').html(''); return; }
    $('#aNameBox').html('<div class="text-muted">Checking name...</div>');
    $.post('attribute-master-api.php', {action:'attr_check_name', attribute_id:p.attribute_id, attribute_name:p.attribute_name})
      .done(function(html){ $('#aNameBox').html(html); })
      .fail(function(xhr, t, e){ failBox($('#aNameBox'), xhr, t, e); });
  }
  function aSave(){
    const p=aPayload();
    if (!p.attribute_code) { $('#attrAlert').html(bsAlert('danger','Attribute Code is required.')); return; }
    if (!p.attribute_name) { $('#attrAlert').html(bsAlert('danger','Attribute Name is required.')); return; }

    $('#btnAttrSubmit').prop('disabled', true).text('Saving...');
    $.post('attribute-master-api.php', $.extend({action:'attr_save'}, p))
      .done(function(html){
        $('#attrAlert').html(html);
        aLoadList(1);
        aClear();
        oLoadAttrDropdown();
      })
      .fail(function(xhr, t, e){
        failBox($('#attrAlert'), xhr, t, e);
      })
      .always(function(){
        $('#btnAttrSubmit').prop('disabled', false).text('Submit');
      });
  }
  function aLoadOne(id){
    $('#attrAlert').html('<div class="text-muted">Loading attribute...</div>');
    $.ajax({
      url:'attribute-master-api.php',
      method:'POST',
      dataType:'json',
      data:{action:'attr_load_one', attribute_id:id}
    }).done(function(res){
      if (!res || !res.ok || !res.attribute) {
        $('#attrAlert').html(bsAlert('danger', (res && res.error) ? esc(res.error) : 'Load failed.'));
        return;
      }
      const a=res.attribute;
      $('#aId').val(String(a.attribute_id||0));
      $('#aCode').val(a.attribute_code||'');
      $('#aName').val(a.attribute_name||'');
      $('#aType').val(a.input_type||'SELECT');
      $('#aSort').val(String(a.sort_order||0));
      $('#aActive').val(String(a.is_active||1));
      setTimeout(function(){ aCheckCode(); aCheckName(); }, 150);
      $('#attrAlert').html(bsAlert('success','Loaded for editing. Change fields and Submit.'));
    }).fail(function(xhr, t, e){
      failBox($('#attrAlert'), xhr, t, e);
    });
  }
  function aLoadList(goPage){
    if (aLoading) return;
    aLoading=true;

    const q=($('#aSearch').val()||'').trim();
    const per=parseInt($('#aPerPage').val()||'10',10);
    aPage=goPage || aPage;

    $('#aTbody').html('<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>');
    $.ajax({
      url:'attribute-master-api.php',
      method:'POST',
      dataType:'json',
      data:{action:'attr_list', q:q, page:aPage, per_page:per}
    }).done(function(res){
      if (!res || !res.ok) {
        $('#aListAlert').html(bsAlert('danger', (res && res.error)?esc(res.error):'List failed.'));
        $('#aTbody').html('<tr><td colspan="7" class="text-center text-muted">—</td></tr>');
        return;
      }
      $('#aListAlert').html('');
      aPages=res.pages||1; aPage=res.page||1;

      const rows=res.rows||[];
      if (!rows.length){
        $('#aTbody').html('<tr><td colspan="7" class="text-center text-muted">No attributes</td></tr>');
      } else {
        $('#aTbody').html(rows.map(r=>{
          const act = String(r.is_active)==='1' ? 'Yes' : 'No';
          return `<tr>
            <td>${r.attribute_id}</td>
            <td>${esc(r.attribute_code)}</td>
            <td>${esc(r.attribute_name)}</td>
            <td>${esc(r.input_type)}</td>
            <td>${r.sort_order}</td>
            <td>${act}</td>
            <td><button class="btn btn-sm btn-outline-primary btnAttrEdit" data-id="${r.attribute_id}" type="button">Edit</button></td>
          </tr>`;
        }).join(''));
      }
      $('#aPagerInfo').text(`Showing ${res.from}-${res.to} of ${res.total} | Page ${aPage}/${aPages}`);
      $('#aPrev').prop('disabled', aPage<=1);
      $('#aNext').prop('disabled', aPage>=aPages);
    }).fail(function(xhr, t, e){
      failBox($('#aListAlert'), xhr, t, e);
      $('#aTbody').html('<tr><td colspan="7" class="text-center text-muted">Failed</td></tr>');
    }).always(function(){ aLoading=false; });
  }

  /* ===================== OPTIONS STATE ===================== */
  let oPage=1, oPages=1, oLoading=false;
  let toSearch=null, toName=null, toCode=null;

  function oClear(){
    $('#oId').val('0');
    $('#oCode').val('');
    $('#oName').val('');
    $('#oSort').val('0');
    $('#oActive').val('1');
    $('#oCodeBox').html('');
    $('#oNameBox').html('');
  }
  function oPayload(){
    const code = ($('#oCode').val()||'').trim().toUpperCase();
    $('#oCode').val(code);
    return {
      option_id: ($('#oId').val()||'0').trim(),
      attribute_id: ($('#oAttr').val()||'').trim(),
      option_code: code,
      option_name: ($('#oName').val()||'').trim(),
      sort_order: ($('#oSort').val()||'0').trim(),
      is_active: ($('#oActive').val()||'1').trim()
    };
  }
  function oLoadAttrDropdown(selected){
    $('#oAttr').html('<option value="">Loading...</option>');
    $.post('attribute-master-api.php', {action:'opt_load_attrs'})
      .done(function(html){
        $('#oAttr').html('<option value="">-- Select Attribute --</option>'+html);
        if (selected) $('#oAttr').val(String(selected));
      })
      .fail(function(xhr, t, e){
        failBox($('#attrAlert'), xhr, t, e);
        $('#oAttr').html('<option value="">-- Select Attribute --</option>');
      });
  }
  function oCheckName(){
    const p=oPayload();
    if (!p.attribute_id || !p.option_name) { $('#oNameBox').html(''); return; }
    $('#oNameBox').html('<div class="text-muted">Checking option name...</div>');
    $.post('attribute-master-api.php', {action:'opt_check_name', option_id:p.option_id, attribute_id:p.attribute_id, option_name:p.option_name})
      .done(function(html){ $('#oNameBox').html(html); })
      .fail(function(xhr, t, e){ failBox($('#oNameBox'), xhr, t, e); });
  }
  function oCheckCode(){
    const p=oPayload();
    if (!p.attribute_id || !p.option_code) { $('#oCodeBox').html(''); return; }
    $('#oCodeBox').html('<div class="text-muted">Checking option code...</div>');
    $.post('attribute-master-api.php', {action:'opt_check_code', option_id:p.option_id, attribute_id:p.attribute_id, option_code:p.option_code})
      .done(function(html){ $('#oCodeBox').html(html); })
      .fail(function(xhr, t, e){ failBox($('#oCodeBox'), xhr, t, e); });
  }
  function oSave(){
    const p=oPayload();
    if (!p.attribute_id) { $('#attrAlert').html(bsAlert('danger','Attribute is required for options.')); return; }
    if (!p.option_name) { $('#attrAlert').html(bsAlert('danger','Option Name is required.')); return; }

    $('#btnOptSubmit').prop('disabled', true).text('Saving...');
    $.post('attribute-master-api.php', $.extend({action:'opt_save'}, p))
      .done(function(html){
        $('#attrAlert').html(html);
        oLoadList(1);
        oClear();
      })
      .fail(function(xhr, t, e){
        failBox($('#attrAlert'), xhr, t, e);
      })
      .always(function(){
        $('#btnOptSubmit').prop('disabled', false).text('Submit');
      });
  }
  function oLoadOne(id){
    $('#attrAlert').html('<div class="text-muted">Loading option...</div>');
    $.ajax({
      url:'attribute-master-api.php',
      method:'POST',
      dataType:'json',
      data:{action:'opt_load_one', option_id:id}
    }).done(function(res){
      if (!res || !res.ok || !res.option) {
        $('#attrAlert').html(bsAlert('danger', (res && res.error) ? esc(res.error) : 'Load failed.'));
        return;
      }
      const o=res.option;
      $('#oId').val(String(o.option_id||0));
      oLoadAttrDropdown(o.attribute_id);
      setTimeout(function(){ $('#oAttr').val(String(o.attribute_id||'')); },150);
      $('#oCode').val(o.option_code||'');
      $('#oName').val(o.option_name||'');
      $('#oSort').val(String(o.sort_order||0));
      $('#oActive').val(String(o.is_active||1));
      setTimeout(function(){ oCheckCode(); oCheckName(); }, 200);
      $('#attrAlert').html(bsAlert('success','Loaded for editing. Change fields and Submit.'));
    }).fail(function(xhr, t, e){
      failBox($('#attrAlert'), xhr, t, e);
    });
  }
  function oLoadList(goPage){
    if (oLoading) return;
    oLoading=true;

    const q=($('#oSearch').val()||'').trim();
    const per=parseInt($('#oPerPage').val()||'10',10);
    oPage=goPage || oPage;

    $('#oTbody').html('<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>');
    $.ajax({
      url:'attribute-master-api.php',
      method:'POST',
      dataType:'json',
      data:{action:'opt_list', q:q, page:oPage, per_page:per}
    }).done(function(res){
      if (!res || !res.ok) {
        $('#oListAlert').html(bsAlert('danger', (res && res.error)?esc(res.error):'List failed.'));
        $('#oTbody').html('<tr><td colspan="7" class="text-center text-muted">—</td></tr>');
        return;
      }
      $('#oListAlert').html('');
      oPages=res.pages||1; oPage=res.page||1;

      const rows=res.rows||[];
      if (!rows.length){
        $('#oTbody').html('<tr><td colspan="7" class="text-center text-muted">No options</td></tr>');
      } else {
        $('#oTbody').html(rows.map(r=>{
          const act = String(r.is_active)==='1' ? 'Yes' : 'No';
          return `<tr>
            <td>${r.option_id}</td>
            <td>${esc(r.attribute_name||'')}</td>
            <td>${esc(r.option_code||'')}</td>
            <td>${esc(r.option_name||'')}</td>
            <td>${r.sort_order}</td>
            <td>${act}</td>
            <td><button class="btn btn-sm btn-outline-primary btnOptEdit" data-id="${r.option_id}" type="button">Edit</button></td>
          </tr>`;
        }).join(''));
      }
      $('#oPagerInfo').text(`Showing ${res.from}-${res.to} of ${res.total} | Page ${oPage}/${oPages}`);
      $('#oPrev').prop('disabled', oPage<=1);
      $('#oNext').prop('disabled', oPage>=oPages);
    }).fail(function(xhr, t, e){
      failBox($('#oListAlert'), xhr, t, e);
      $('#oTbody').html('<tr><td colspan="7" class="text-center text-muted">Failed</td></tr>');
    }).always(function(){ oLoading=false; });
  }

  /* ===================== EVENTS (blur only) ===================== */
  $('#aCode').on('blur', function(){ clearTimeout(taCode); taCode=setTimeout(aCheckCode, 120); });
  $('#aName').on('blur', function(){ clearTimeout(taName); taName=setTimeout(aCheckName, 120); });
  $('#btnAttrSubmit').on('click', aSave);

  $('#aSearch').on('input', function(){ clearTimeout(taSearch); taSearch=setTimeout(()=>aLoadList(1), 250); });
  $('#aPerPage').on('change', function(){ aLoadList(1); });
  $('#aPrev').on('click', function(){ if (aPage>1) aLoadList(aPage-1); });
  $('#aNext').on('click', function(){ if (aPage<aPages) aLoadList(aPage+1); });
  $(document).on('click', '.btnAttrEdit', function(){
    const id=parseInt($(this).data('id')||'0',10); if (id>0) aLoadOne(id);
  });

  $('#oAttr').on('change', function(){
    if ($('#oCode').val().trim()) oCheckCode();
    if ($('#oName').val().trim()) oCheckName();
  });
  $('#oCode').on('blur', function(){ clearTimeout(toCode); toCode=setTimeout(oCheckCode, 120); });
  $('#oName').on('blur', function(){ clearTimeout(toName); toName=setTimeout(oCheckName, 120); });
  $('#btnOptSubmit').on('click', oSave);

  $('#oSearch').on('input', function(){ clearTimeout(toSearch); toSearch=setTimeout(()=>oLoadList(1), 250); });
  $('#oPerPage').on('change', function(){ oLoadList(1); });
  $('#oPrev').on('click', function(){ if (oPage>1) oLoadList(oPage-1); });
  $('#oNext').on('click', function(){ if (oPage<oPages) oLoadList(oPage+1); });
  $(document).on('click', '.btnOptEdit', function(){
    const id=parseInt($(this).data('id')||'0',10); if (id>0) oLoadOne(id);
  });

  /* init */
  aLoadList(1);
  oLoadAttrDropdown();
  oLoadList(1);

})(jQuery);
</script>
