<?php
// gl-master-entry.php
require_once 'connections/connection.php';
require_once 'includes/userlog.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Colombo');
?>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <h5 class="mb-3 text-primary">GL Master — Create</h5>
      <div id="glAlert"></div>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-bold">GL Code</label>
          <input type="text" id="glCode" class="form-control" placeholder="e.g. 1110">
          <div class="form-text">Unique code.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-bold">GL Name</label>
          <input type="text" id="glName" class="form-control" placeholder="e.g. Stationery & Gifts">
        </div>

        <div class="col-md-12">
          <label class="form-label fw-bold">Note</label>
          <textarea id="glNote" class="form-control" rows="2" placeholder="What is this GL used for?"></textarea>
        </div>

        <div class="col-md-12 d-flex gap-2 justify-content-end">
          <button class="btn btn-success" id="btnGlSubmit" type="button">Submit</button>
        </div>
      </div>

      <div class="mt-3" id="glCodeBox"></div>
      <div class="mt-3" id="glResult"></div>

      <hr class="my-4">

      <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between">
        <div>
          <h6 class="text-secondary mb-1">Saved GLs</h6>
        </div>

        <div class="input-group" style="max-width: 420px;">
          <span class="input-group-text">Search</span>
          <input type="text" id="glSearch" class="form-control" placeholder="Type code or name...">
          <button class="btn btn-outline-secondary" id="btnGlSearchClear" type="button">Clear</button>
        </div>
      </div>

      <div class="mt-3" id="glListBox"></div>

    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  let glCodeAvailable = false;
  let glPage = 1;
  const glPerPage = 10;
  let tSearch = null;

  function bsAlert(type,msg){
    return `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }

  function normalizeCode(v){
    return (v||'').toString().trim().toUpperCase();
  }

  function payload(){
    const gl_code = normalizeCode($('#glCode').val());
    $('#glCode').val(gl_code);
    return {
      gl_code,
      gl_name: ($('#glName').val()||'').trim(),
      gl_note: ($('#glNote').val()||'').trim()
    };
  }

  function checkCode(){
    const p = payload();
    glCodeAvailable = false;

    if (!p.gl_code){
      $('#glCodeBox').html('');
      return;
    }

    $('#glCodeBox').html('<div class="text-muted">Checking GL code...</div>');

    $.ajax({
      url: 'gl-master-check-code.php',
      method: 'POST',
      dataType: 'json',
      data: { gl_code: p.gl_code }
    })
    .done(function(res){
      if (!res || !res.ok){
        $('#glCodeBox').html(bsAlert('danger', res && res.error ? res.error : 'GL code check failed.'));
        glCodeAvailable = false;
        return;
      }
      $('#glCodeBox').html(res.html || '');
      glCodeAvailable = !!res.available;
    })
    .fail(function(xhr){
      $('#glCodeBox').html(bsAlert('danger', 'Server error: ' + xhr.status));
      glCodeAvailable = false;
    });
  }

  function loadList(page){
    glPage = page || 1;
    const q = ($('#glSearch').val()||'').trim();

    $('#glListBox').html('<div class="text-muted">Loading...</div>');
    $.post('gl-master-list.php', { q: q, page: glPage, per_page: glPerPage }, function(html){
      $('#glListBox').html(html);
    }).fail(function(xhr){
      $('#glListBox').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  function submitGl(){
    const p = payload();

    if (!p.gl_code){ $('#glResult').html(bsAlert('danger','GL Code is required.')); return; }
    if (!p.gl_name){ $('#glResult').html(bsAlert('danger','GL Name is required.')); return; }

    $('#glResult').html('<div class="text-muted">Saving...</div>');

    // enforce a fresh check before save
    $.ajax({
      url: 'gl-master-check-code.php',
      method: 'POST',
      dataType: 'json',
      data: { gl_code: p.gl_code }
    })
    .done(function(res){
      if (!res || !res.ok){
        $('#glResult').html(bsAlert('danger', res && res.error ? res.error : 'GL code check failed.'));
        return;
      }
      $('#glCodeBox').html(res.html || '');
      glCodeAvailable = !!res.available;

      if (!glCodeAvailable){
        $('#glResult').html(bsAlert('danger','This GL Code already exists. Please use a new code.'));
        return;
      }

      $.post('gl-master-save.php', p, function(html){
        $('#glResult').html(html);
        // refresh list (go to page 1 so user sees the newly added GL)
        loadList(1);
      }).fail(function(xhr){
        $('#glResult').html(bsAlert('danger','Server error: ' + xhr.status));
      });
    })
    .fail(function(xhr){
      $('#glResult').html(bsAlert('danger','Server error: ' + xhr.status));
    });
  }

  // events
  $('#glCode').on('blur', function(){ setTimeout(checkCode, 120); });
  $('#btnGlSubmit').on('click', submitGl);

  // keystroke search (debounced)
  $('#glSearch').on('keyup', function(){
    clearTimeout(tSearch);
    tSearch = setTimeout(function(){
      loadList(1);
    }, 250);
  });

  $('#btnGlSearchClear').on('click', function(){
    $('#glSearch').val('');
    loadList(1);
  });

  // pagination click (event delegation)
  $(document).on('click', '.gl-page-link', function(e){
    e.preventDefault();
    const p = parseInt($(this).data('page'), 10);
    if (!isNaN(p) && p > 0) loadList(p);
  });

  // initial list load
  loadList(1);

})(jQuery);
</script>
