function updateTotal() {
  let colombo = parseFloat($("input[name='where_to_colombo']").val().replace(/,/g, '')) || 0;
  let outstation = parseFloat($("input[name='where_to_outstation']").val().replace(/,/g, '')) || 0;
  let total = colombo + outstation;
  $('#total_display').val(total.toLocaleString('en-LK')).attr('tabindex', '-1');
}

function updateEndBalance() {
  let open = parseFloat($("input[name='open_balance']").val().replace(/,/g, '')) || 0;
  let stamps = parseFloat($('#total_stamp_amount').val().replace(/,/g, '')) || 0;

  if (stamps === 0) {
    $('#end_balance').val("").attr("placeholder", "Waiting for stamp entry...");
  } else {
    let endBal = open - stamps;
    $('#end_balance').val(endBal.toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })).attr('tabindex', '-1');
  }
}

function updateSubtotals() {
  let grandTotal = 0;
  $('#stampTable tbody tr').each(function () {
    let val = parseFloat($(this).find('.stamp-val').val().replace(/,/g, '')) || 0;
    let qty = parseInt($(this).find('.stamp-qty').val()) || 0;
    let subtotal = val * qty;
    grandTotal += subtotal;
    $(this).find('.subtotal').val(subtotal.toLocaleString('en-LK')).attr('tabindex', '-1');
  });
  $('#total_stamp_amount').val(grandTotal.toLocaleString('en-LK')).attr('tabindex', '-1');
  updateEndBalance();
}

$(document).on('input', '.number', function () {
  let val = $(this).val().replace(/,/g, '');
  if (!isNaN(val) && val !== '') {
    $(this).val(parseFloat(val).toLocaleString('en-LK'));
  }
  updateTotal();
  updateSubtotals();
  updateEndBalance();
});

$(document).on('input', '.stamp-qty', function () {
  updateSubtotals();
});

$('#addRow').click(function () {
  $('#stampTable tbody').append(`
    <tr>
      <td><input type="text" name="stamp_value[]" class="form-control number stamp-val" value="0"></td>
      <td><input type="number" name="stamp_quantity[]" class="form-control stamp-qty" value="0"></td>
      <td><input type="text" class="form-control subtotal" readonly tabindex="-1"></td>
      <td><button type="button" class="btn btn-danger btn-remove">×</button></td>
    </tr>
  `);
});

$(document).on('click', '.btn-remove', function () {
  $(this).closest('tr').remove();
  updateSubtotals();
});

$(document).ready(function () {
  updateTotal();
  updateSubtotals();
  updateEndBalance();

  $('#total_display, #total_stamp_amount').attr('readonly', true).attr('tabindex', '-1');
  $('#end_balance').attr('readonly', true).attr('tabindex', '-1');
});
