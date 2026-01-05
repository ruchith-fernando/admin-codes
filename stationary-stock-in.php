<?php
// stationary-stock-in.php
session_start();
if (!isset($_SESSION['hris'])) {
    header("Location: index.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
?>
<style>
    .select2-container--default .select2-selection--single {
        height: 38px !important;
        padding: 0 12px !important;
        font-size: 1rem;
        line-height: 38px !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        background-color: #fff !important;
        display: flex;
        align-items: center;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
        top: 0 !important;
        right: 10px !important;
    }

    .select2-container {
        width: 100% !important;
    }

    #stockInfo table td, #stockInfo table th {
      text-align: center;
      vertical-align: middle;
    }

    #stockInfo table td:nth-child(2), 
    #stockInfo table th:nth-child(2) {
      text-align: left;
    }
    
    #stockInfo table td:nth-child(2), 
    #stockInfo table th:nth-child(2) {
      width: 250px; /* wider for item name */
      white-space: normal !important; /* allow wrap */
      word-wrap: break-word;
    }


</style>
<div class="content font-size" id="contentArea">
  <div class="container-fluid">
    <div class="card shadow bg-white rounded p-4">
      <div id="successAlertContainer"></div>

      <h5 class="mb-4 text-primary">Add Stock In</h5>

      <form method="post" id="stockForm">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Item</label>
            <select name="item_code" id="item_code" class="form-select" required></select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit Price (Base)</label>
            <input type="text" name="unit_price" id="unit_price" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Received Date</label>
            <input type="text" class="form-control" id="received_date" name="received_date" autocomplete="off" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Does the Unit Price include taxes?</label><br>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="tax_included" id="included" value="yes" checked>
            <label class="form-check-label" for="included">Yes</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="tax_included" id="excluded" value="no">
            <label class="form-check-label" for="excluded">No</label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Entry</button>
      </form>

      <div class="mt-4" id="stockInfo" style="display: none;">
        <h6 class="text-primary">Previous Stock Entries for this Item</h6>
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Stock Available</th>
                <th>Unit Price (Base)</th>
                <th>SSCL (Total)</th>
                <th>VAT (Total)</th>
                <th>Received Date</th>
                <th>Stock Value</th>
              </tr>
            </thead>


            <tbody id="stockInfoBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="successAlertContainer"></div>

<script>
$(document).ready(function () {
  $('#item_code').select2({
    ajax: {
      url: 'fetch-items.php',
      dataType: 'json',
      delay: 250,
      data: params => ({ term: params.term }),
      processResults: data => ({ results: data.results })
    },
    placeholder: 'Search item',
    minimumInputLength: 1,
    width: '100%'
  });

  $('#received_date').datepicker({
    format: 'yyyy-mm-dd',
    endDate: new Date(),
    autoclose: true,
    todayHighlight: true
  }).datepicker('setDate', new Date());

  $('#stockForm').on('submit', function (e) {
    e.preventDefault();

    const itemCode = $('#item_code').val();

    $.ajax({
      url: 'submit-stock-in.php',
      type: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        try {
          const data = (typeof res === 'string') ? JSON.parse(res) : res;

          if (data.status === 'success') {
            showSuccessAlert(`✅ ${data.message} <br> Item code: <strong>${itemCode}</strong>`);

            setTimeout(() => {
              $('#stockForm')[0].reset();
              $('#item_code').val(null).trigger('change');
              $('#received_date').datepicker('setDate', new Date());
              $('#stockInfo').hide();
              $('#successAlertContainer').html('');
            }, 3000);
          } else {
            alert("❌ Error: " + data.message);
          }
        } catch (e) {
          alert("❌ Invalid server response. Check console.");
          console.error("Invalid JSON:", res);
        }
      },
      error: function (xhr, status, error) {
        alert(`❌ AJAX Error\nStatus: ${status}\nError: ${error}\nResponse: ${xhr.responseText}`);
        console.error("AJAX Debug:", xhr.responseText);
      }
    });
  });

  $('#item_code').on('change', function () {
    const selectedItemCode = $(this).val();
    fetchStockInfo(selectedItemCode);
  });

  function showSuccessAlert(message) {
    const html = `
      <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
        ${message}
      </div>
    `;
    $('#successAlertContainer').html(html);
  }
});

// Fetch and display stock info
function fetchStockInfo(itemCode) {
  if (!itemCode) {
    $('#stockInfo').hide();
    return;
  }

  $.ajax({
    url: 'fetch-stock-info.php',
    type: 'POST',
    dataType: 'json',
    data: { item_code: itemCode },
    success: function (data) {
      if (!data || data.length === 0) {
        $('#stockInfo').hide();
        return;
      }

      let html = '';
      let totalStock = 0;
      let totalValue = 0;

      data.forEach(row => {
        const stockQty = parseFloat(row.stock_available) || 0;
        const unitPrice = parseFloat(row.unit_price) || 0;
        const sscl = parseFloat(row.sscl_amount) || 0;
        const vat = parseFloat(row.vat_amount) || 0;

        const totalSSCL = sscl * stockQty;
        const totalVAT = vat * stockQty;
        const rowValue = (unitPrice + sscl + vat) * stockQty;

        html += `
          <tr>
            <td>${row.item_code}</td>
            <td>${row.item_name}</td>
            <td>${stockQty}</td>
            <td>${unitPrice.toFixed(2)}</td>
            <td>${totalSSCL.toFixed(2)}</td>
            <td>${totalVAT.toFixed(2)}</td>
            <td>${row.received_date}</td>
            <td>${rowValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
          </tr>
        `;

        totalStock += stockQty;
        totalValue += rowValue;
      });

      html += `
        <tr class="table-success fw-bold">
          <td colspan="2">Total</td>
          <td>${totalStock}</td>
          <td colspan="4"></td>
          <td>${totalValue.toFixed(2)}</td>
        </tr>
      `;

      $('#stockInfoBody').html(html);
      $('#stockInfo').show();

    },
    error: function (xhr, status, error) {
      alert(`❌ AJAX Error (Stock Info)\nStatus: ${status}\nError: ${error}\nResponse: ${xhr.responseText}`);
      console.error("Stock Info AJAX Error:", xhr.responseText);
      $('#stockInfo').hide();
    }
  });
}

</script>
