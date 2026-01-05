<?php
include 'connections/connection.php';

$hris_search = $_POST['search_hris'] ?? '';
$mobile_data = [];

if (!empty($hris_search)) {
    $query = "SELECT mobile_no, company_contribution FROM tbl_admin_mobile_issues WHERE hris_no = ?";
    $stmt  = $conn->prepare($query);
    $stmt->bind_param("s", $hris_search);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $mobile_data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Update HRIS Company Contribution</title>
  <style>
    .card{position:relative;background:#fff;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:24px}
    .loader-inner.line-scale>div{height:72px;width:10.8px;margin:3.6px;display:inline-block;animation:scaleStretchDelay 1.2s infinite ease-in-out}
    .loader-inner.line-scale>div:nth-child(odd){background:#0070C0}.loader-inner.line-scale>div:nth-child(even){background:#E60028}
    .loader-inner.line-scale>div:nth-child(1){animation-delay:-1.2s}.loader-inner.line-scale>div:nth-child(2){animation-delay:-1.1s}
    .loader-inner.line-scale>div:nth-child(3){animation-delay:-1.0s}.loader-inner.line-scale>div:nth-child(4){animation-delay:-0.9s}
    .loader-inner.line-scale>div:nth-child(5){animation-delay:-0.8s}
    @keyframes scaleStretchDelay{0%,40%,100%{transform:scaleY(.4)}20%{transform:scaleY(1)}}

    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f8fb;margin:0}
    .content.font-size{padding:20px}.container-fluid{max-width:1100px;margin:0 auto}
    .card h5{margin:0 0 16px;color:#0d6efd}.mb-3{margin-bottom:1rem}.form-label{display:block;margin-bottom:.5rem}
    .form-control{width:100%;padding:.55rem .75rem;border:1px solid #ced4da;border-radius:8px}
    .btn{display:inline-block;padding:.55rem 1rem;border-radius:8px;border:1px solid transparent;cursor:pointer}
    .btn-success{background:#198754;color:#fff}.btn-success:disabled{opacity:.6;cursor:not-allowed}
    .btn-primary{background:#0d6efd;color:#fff}
    .result-block{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:12px;background:#fafafa;display:none}
    .row-result{border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-top:10px;background:#fafafa;display:none}
    .small-muted{font-size:.9rem;color:#666}
    .fw-semibold{font-weight:600}

    /* >>> EXACT MATCH: full-screen centered loader overlay like your CDMA page <<< */
    #cardLoader{
      position:fixed; top:0; left:0; width:100%; height:100%;
      background:rgba(255,255,255,.9);
      display:none; align-items:center; justify-content:center;
      z-index:9999;
    }
  </style>
</head>
<body>
<div class="content font-size">
  <div class="container-fluid">
    <div class="card">
      <!-- Full-screen overlay loader (centered) -->
      <div id="cardLoader">
        <div class="loader-inner line-scale"><div></div><div></div><div></div><div></div><div></div></div>
      </div>

      <h5>Update HRIS Company Contribution</h5>

      <!-- Global inline results -->
      <div id="globalResult" class="result-block"></div>

      <!-- HRIS Search Form -->
      <form id="searchForm" class="mb-4" novalidate>
        <div class="mb-3">
          <label for="search_hris" class="form-label">Enter HRIS Number</label>
          <input type="text" name="search_hris" id="search_hris" class="form-control" required value="<?= htmlspecialchars($hris_search) ?>">
          <div class="small-muted">Search for an employee’s linked mobile numbers by their HRIS.</div>
        </div>
        <button type="submit" class="btn btn-primary" id="searchBtn">Search</button>
      </form>

      <?php if (!empty($mobile_data)): ?>
        <hr>
        <h6 class="fw-semibold mb-3">Mobile Numbers Linked to HRIS: <?= htmlspecialchars($hris_search) ?></h6>

        <?php foreach ($mobile_data as $row): ?>
          <form class="contribution-form border rounded p-3 mb-3 bg-light" novalidate>
            <input type="hidden" name="hris_no" value="<?= htmlspecialchars($hris_search) ?>">
            <input type="hidden" name="mobile_no" value="<?= htmlspecialchars($row['mobile_no']) ?>">

            <div class="row g-2 align-items-center">
              <div class="col-md-5 fw-semibold">
                <span class="mobile-label">
                  <?= htmlspecialchars($row['mobile_no']) ?> — Rs. <?= number_format((float)$row['company_contribution'], 2) ?>
                </span>
              </div>

              <div class="col-md-3">
                <input type="number" step="0.01" name="contribution_amount" class="form-control" placeholder="New Amount" required>
              </div>

              <div class="col-md-2">
                <input type="text" name="effective_from" class="form-control datepicker" placeholder="Effective From (yyyy-mm-dd)" required>
              </div>

              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-success">Update</button>
              </div>
            </div>

            <!-- Per-row inline result -->
            <div class="row-result"></div>
          </form>
        <?php endforeach; ?>

      <?php elseif (!empty($hris_search)): ?>
        <div id="noDataBlock" class="result-block" style="display:block">
          <div class="alert alert-warning mb-0">
            No mobile numbers found for HRIS <strong><?= htmlspecialchars($hris_search) ?></strong>.
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
(function($){
  // UTIL (exact style of helpers you used)
  function showFlex(el){ el.style.display='flex'; }
  function hide(el){ el.style.display='none'; }

  const $globalResult = $('#globalResult');
  const loader = document.getElementById('cardLoader');

  // Datepicker init
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  });

  // HRIS Search
  $('#searchForm').on('submit', function(e){
    e.preventDefault();
    const hris = $('#search_hris').val().trim();
    if(!hris){
      $globalResult.html("<div class='alert alert-danger fw-bold mb-0'>❌ Please enter an HRIS number.</div>").slideDown();
      return;
    }

    showFlex(loader);
    $('#searchBtn').prop('disabled', true);
    $globalResult.hide().empty();

    $.post('update-contribution.php', { search_hris: hris })
      .done(function(html){
        $('#contentArea').html(html);
        try{ window.scrollTo({top:0, behavior:'smooth'});}catch(_){}
      })
      .fail(function(){
        $globalResult.html("<div class='alert alert-danger fw-bold mb-0'>❌ Search failed. Please try again.</div>").slideDown();
      })
      .always(function(){
        hide(loader);
        $('#searchBtn').prop('disabled', false);
      });
  });

  // Contribution Update
  $(document).on('submit', '.contribution-form', function(e){
    e.preventDefault();
    const $form = $(this);
    const $btn  = $form.find('button[type="submit"]');
    const $rowR = $form.find('.row-result');

    showFlex(loader);
    $btn.prop('disabled', true);
    $rowR.hide().empty();

    $.ajax({
      url: 'ajax-update-contribution.php',
      type: 'POST',
      data: $form.serialize(),
      dataType: 'json'
    })
    .done(function(res){
      if(res.status === 'success'){
        const newAmt = Number($form.find('[name="contribution_amount"]').val() || 0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
        const mobile = $form.find('[name="mobile_no"]').val();
        $form.find('.mobile-label').text(`${mobile} — Rs. ${newAmt}`);
        $rowR.html(`<div class="alert alert-success mb-0">${res.message}</div>`).slideDown();
        $form[0].reset();
      } else {
        $rowR.html(`<div class="alert alert-warning mb-0">${res.message}</div>`).slideDown();
      }
      try{ $rowR[0].scrollIntoView({behavior:'smooth', block:'center'});}catch(_){}
    })
    .fail(function(){
      $rowR.html("<div class='alert alert-danger mb-0'>An unexpected error occurred.</div>").slideDown();
    })
    .always(function(){
      hide(loader);
      $btn.prop('disabled', false);
    });
  });

})(jQuery);
</script>
</body>
</html>
