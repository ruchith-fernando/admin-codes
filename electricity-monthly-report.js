// electricity-monthly-report.js
$(document).ready(function(){

  // ---------- helpers ----------
  const blankElecRow = () => `
    <tr>
      <td><input type="text" class="form-control elec_branch_code" maxlength="5" /></td>
      <td><input type="text" class="form-control elec_branch_name" readonly /></td>
      <td><input type="text" class="form-control elec_account_no" readonly /></td>
      <td><input type="text" class="form-control elec_bank_paid_to" readonly /></td>
      <td><input type="number" step="0.01" min="0.01" class="form-control elec_units" /></td>
      <td><input type="text" class="form-control elec_amount" /></td>
      <td>
        <select class="form-select elec_provision">
          <option value="no" selected>No</option>
          <option value="yes">Yes</option>
        </select>
      </td>
      <td><input type="text" class="form-control elec_provision_reason" placeholder="Optional" /></td>
    </tr>
  `;

  const resetElecManualForm = () => {
    // reset entry rows to a single fresh row
    $("#elec_entry_rows").html(blankElecRow());

    // clear previous month hint
    $(".elec_previous_month_info").val('');

    // clear all bill detail fields
    const billSelectors = [
      '.elec_bill_from_date', '.elec_bill_to_date', '.elec_number_of_days',
      '.elec_bill_amount', '.elec_paid_amount',
      '.elec_cheque_number', '.elec_cheque_date',
      '.elec_ar_cr', '.elec_cheque_amount'
    ];
    $(billSelectors.join(',')).val('');

    // collapse the Bill Details <details> section
    $("#elec_manual_form details").prop('open', false);

    // clear status + provision info alerts
    $("#elec_status_msg").html('');
    $("#elec_provision_info").addClass("d-none").html('');
  };

  const updateAlerts = (res, month, missingSel, provisionSel) => {
    let out = '';
    if (res.missing && res.missing.length) {
      out += `<b>${res.missing.length} branches</b> have not submitted electricity data for <b>${month}</b>:<br>${res.missing.join(', ')}`;
    }
    if (res.provisions && res.provisions.length) {
      out += `${out ? '<br>' : ''}<b>${res.provisions.length} branches</b> have provisional data:<br>${res.provisions.join(', ')}`;
    }
    if (out) {
      $(missingSel).removeClass("d-none").html(out);
    } else {
      $(missingSel).addClass("d-none").html('');
    }
    if (res.provisions && res.provisions.length) {
      $(provisionSel).removeClass("d-none").html(`Provisional entries present for <b>${month}</b>. You may finalize them by editing and setting Provision = No.`);
    } else {
      $(provisionSel).addClass("d-none").html('');
    }
  };

  // ---------- View Report dropdown ----------
  $("#elec_month_view").change(function(){
    // whenever the view dropdown changes, clear any manual-entry / bill-details values
    resetElecManualForm();

    const month = $(this).val();
    if (month) {
      $("#elec_manual_form").addClass("d-none");
      $("#elec_month_manual").val('');
      $("#elec_missing_manual_branches").addClass("d-none").html('');
      $("#elec_missing_view_branches").removeClass("d-none").html('Loading...');
      $("#elec_report_section").addClass("d-none").html('');

      $.post("electricity-monthly-fetch.php", {month: month}, function(res){
        $("#elec_report_section").removeClass("d-none").html(res.table || '');
        $("#elec_csv_download_container").removeClass("d-none");
        updateAlerts(res, month, "#elec_missing_view_branches", "#elec_provision_info");
      }, 'json');
    } else {
      $("#elec_report_section").addClass("d-none").html('');
      $("#elec_missing_view_branches").addClass("d-none").html('');
      $("#elec_csv_download_container").addClass("d-none");
    }
  });

  // ---------- Manual Entry dropdown ----------
  $("#elec_month_manual").change(function(){
    // whenever the manual month changes, clear the manual-entry row and bill details
    resetElecManualForm();

    const month = $(this).val();
    if (month) {
      $("#elec_manual_form").removeClass("d-none");
      $("#elec_report_section").addClass("d-none").html('');
      $("#elec_missing_view_branches").addClass("d-none").html('');
      $("#elec_month_view").val('');
      $(".elec_branch_code").first().focus();

      $("#elec_missing_manual_branches").removeClass("d-none").html('Loading...');
      $.post("electricity-monthly-fetch.php", {month: month}, function(res){
        updateAlerts(res, month, "#elec_missing_manual_branches", "#elec_provision_info");
      }, 'json');
    } else {
      $("#elec_manual_form").addClass("d-none");
      $("#elec_missing_manual_branches").addClass("d-none").html('');
      $("#elec_provision_info").addClass("d-none").html('');
    }
  });

  // ---------- Branch Code blur → existing check OR master fill ----------
  $(document).on('blur', '.elec_branch_code', function(){
    const row = $(this).closest('tr');
    const branch_code = $(this).val();
    const month = $("#elec_month_manual").val();
    if (!branch_code || !month) return;

    $.post("ajax-get-existing-electricity.php", {branch_code, month}, function(res){
      if (res.exists) {
        row.find('.elec_branch_name').val(res.branch).prop('readonly', true);
        row.find('.elec_account_no').val(res.account_no || '').prop('readonly', true);
        row.find('.elec_bank_paid_to').val(res.bank_paid_to || '').prop('readonly', true);
        row.find('.elec_units').val(res.actual_units || '')
          .prop('readonly', res.is_provision === 'no');
        row.find('.elec_amount').val(
          res.total_amount ? Number(String(res.total_amount).replace(/,/g,'')).toLocaleString('en-US', {minimumFractionDigits:2}) : ''
        ).prop('readonly', res.is_provision === 'no');

        // Provision handling
        row.find('.elec_provision').val(res.is_provision || 'no')
          .prop('disabled', res.is_provision === 'no');
        row.find('.elec_provision_reason').val(res.provision_reason || '')
          .prop('readonly', res.is_provision === 'no');

        if (res.is_provision === 'yes') {
          $("#elec_status_msg").html(`<div class='alert alert-warning'>Provision is YES — you can update Units/Amount and set Provision = No to finalize.</div>`);
        } else {
          $("#elec_status_msg").html(`<div class='alert alert-danger'>Record is finalized (Provision = No). Locked. Contact admin.</div>`);
        }

        $(".elec_previous_month_info").val('Not applicable');
      } else {
        // New entry: fill branch master details
        $.post("ajax-get-electricity-branch.php", {branch_code}, function(b){
          if (b.success) {
            row.find('.elec_branch_name').val(b.branch_name).prop('readonly', true);
            row.find('.elec_account_no').val(b.account_no || '').prop('readonly', true);
            row.find('.elec_bank_paid_to').val(b.bank_paid_to || '').prop('readonly', true);
          } else {
            row.find('.elec_branch_name').val('Not Found').prop('readonly', true);
            row.find('.elec_account_no').val('').prop('readonly', true);
            row.find('.elec_bank_paid_to').val('').prop('readonly', true);
          }
        }, 'json');

        // Clear/unlock inputs
        row.find('.elec_units').val('').prop('readonly', false);
        row.find('.elec_amount').val('').prop('readonly', false);
        row.find('.elec_provision').val('no').prop('disabled', false);
        row.find('.elec_provision_reason').val('').prop('readonly', false);
        $("#elec_status_msg").html('');

        // Previous month hint
        $.post("fetch-electricity-previous-month.php", {branch_code, month}, function(prev){
          if (prev.found) {
            $(".elec_previous_month_info").val(`${prev.units || 0} Units – ${Number(String(prev.amount||'0').replace(/,/g,'')).toLocaleString('en-US',{minimumFractionDigits:2})} (${prev.month})`);
          } else {
            $(".elec_previous_month_info").val('No Previous Data');
          }
        }, 'json');
      }
    }, 'json');
  });

  // Provision toggle — Units optional only if Provision=Yes
  $(document).on('change', '.elec_provision', function(){
    const row = $(this).closest('tr');
    const prov = $(this).val();
    if (prov === 'yes') {
      row.find('.elec_units').prop('readonly', false); // optional, still editable
      row.find('.elec_provision_reason').prop('readonly', false);
    } else {
      row.find('.elec_units').prop('readonly', false); // required on save
      row.find('.elec_provision_reason').prop('readonly', false);
    }
  });

  // Bill dates → auto-calc #days
  $(document).on('change', '.elec_bill_from_date, .elec_bill_to_date', function(){
    const from = $('.elec_bill_from_date').val();
    const to   = $('.elec_bill_to_date').val();
    if (from && to) {
      const d1 = new Date(from), d2 = new Date(to);
      if (d2 >= d1) {
        const diff = Math.round((d2 - d1) / (1000*60*60*24)) + 1;
        if (!$('.elec_number_of_days').val()) $('.elec_number_of_days').val(diff);
      }
    }
  });

  // Live thousand separator for Amount
  $(document).on('input', '.elec_amount', function() {
    let v = $(this).val().replace(/,/g, '');
    if (!isNaN(v) && v !== '') {
      const parts = v.split('.');
      parts[0] = Number(parts[0] || 0).toLocaleString('en-US');
      $(this).val(parts.length > 1 ? parts[0] + '.' + parts[1].slice(0,2) : parts[0]);
    } else {
      $(this).val('');
    }
  });

  // Auto-grow previous month textarea
  $(document).on('input click', '.elec_previous_month_info', function () {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
  });

  // Save Entry
  $("#elec_save_entry").click(function(){
    const month = $("#elec_month_manual").val();
    const row = $("#elec_entry_rows tr").last();

    const branch_code = row.find('.elec_branch_code').val();
    const branch_name = row.find('.elec_branch_name').val();
    const account_no  = row.find('.elec_account_no').val();
    const bank_paid_to= row.find('.elec_bank_paid_to').val();
    const units       = row.find('.elec_units').val();
    const amount      = row.find('.elec_amount').val().replace(/,/g, '');
    const provision   = row.find('.elec_provision').val();
    const provision_reason = row.find('.elec_provision_reason').val();

    const bill_from_date  = $('.elec_bill_from_date').val();
    const bill_to_date    = $('.elec_bill_to_date').val();
    const number_of_days  = $('.elec_number_of_days').val();
    const bill_amount     = $('.elec_bill_amount').val();
    const paid_amount     = $('.elec_paid_amount').val();
    const cheque_number   = $('.elec_cheque_number').val();
    const cheque_date     = $('.elec_cheque_date').val();
    const ar_cr           = $('.elec_ar_cr').val();
    const cheque_amount   = $('.elec_cheque_amount').val();

    if (!month || !branch_code || !branch_name || !amount) {
      $("#elec_status_msg").html(`<div class='alert alert-danger'>Fill required fields: Month, Branch, Amount. Units are required if Provision = No.</div>`);
      return;
    }
    if (isNaN(amount) || Number(amount) <= 0) {
      $("#elec_status_msg").html(`<div class='alert alert-danger'>Amount must be a number greater than 0</div>`);
      return;
    }
    if (provision === 'no') {
      if (!units || isNaN(units) || Number(units) <= 0) {
        $("#elec_status_msg").html(`<div class='alert alert-danger'>Units must be a number greater than 0 when finalizing (Provision = No)</div>`);
        return;
      }
    } else {
      if (units && (isNaN(units) || Number(units) < 0)) {
        $("#elec_status_msg").html(`<div class='alert alert-danger'>Units must be a valid number</div>`);
        return;
      }
    }
    if (bill_from_date && bill_to_date) {
      const d1 = new Date(bill_from_date), d2 = new Date(bill_to_date);
      if (d2 < d1) {
        $("#elec_status_msg").html(`<div class='alert alert-danger'>Bill To Date cannot be earlier than Bill From Date</div>`);
        return;
      }
    }

    $.post("electricity-monthly-save.php", {
      month, branch_code, branch_name, account_no, bank_paid_to,
      units, amount, provision, provision_reason,
      bill_from_date, bill_to_date, number_of_days, bill_amount, paid_amount,
      cheque_number, cheque_date, ar_cr, cheque_amount
    }, function(res){
      if (res.success) {
        $("#elec_status_msg").html(`<div class='alert alert-success'>${res.message}</div>`);

        // Refresh alerts
        $.post("electricity-monthly-fetch.php", {month: month}, function(r2){
          updateAlerts(r2, month, "#elec_missing_manual_branches", "#elec_provision_info");
        }, 'json');

        // Next fresh row
        $("#elec_entry_rows").append(blankElecRow());
      } else {
        $("#elec_status_msg").html(`<div class='alert alert-danger'>${res.message}</div>`);
      }
    }, 'json');
  });

  // CSV Download
  $("#elec_download_csv_btn").click(function(){
    const table = $("#elec_report_section table");
    if (!table.length) return;
    let csv = [];
    table.find("tr").each(function(){
      let row = [];
      $(this).find("th, td").each(function(){
        let text = $(this).text().trim().replace(/"/g, '""');
        row.push(`"${text}"`);
      });
      csv.push(row.join(","));
    });
    const csvString = csv.join("\n");
    const blob = new Blob([csvString], {type:"text/csv;charset=utf-8;"});
    const link = document.createElement("a");
    const month = $("#elec_month_view").val() ? $("#elec_month_view").val().replace(/\s+/g,'_') : 'Month';
    link.setAttribute("href", URL.createObjectURL(blob));
    link.setAttribute("download", `Electricity_Report_${month}.csv`);
    link.style.display = "none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

});
