$(document).ready(function(){
// printing-monthly-report.js
  const blankRow = () => `
    <tr>
      <td><input type="text" class="form-control printing_branch_code" maxlength="10" /></td>
      <td><input type="text" class="form-control printing_branch_name" readonly /></td>
      <td><input type="text" class="form-control printing_amount" /></td>
      <td>
        <select class="form-select printing_provision">
          <option value="no" selected>No</option>
          <option value="yes">Yes</option>
        </select>
      </td>
      <td><input type="text" class="form-control printing_provision_reason" placeholder="Optional" /></td>
    </tr>
  `;

  const resetForm = () => {
    $("#printing_entry_rows").html(blankRow());
    $("#printing_status_msg").html('');
    $("#printing_provision_info").addClass("d-none").html('');
  };

  const updateAlerts = (res, month, missingSel, provisionSel) => {
    let out = '';
    if (res.missing && res.missing.length) {
      out += `<b>${res.missing.length} branches</b> missing for <b>${month}</b>:<br>${res.missing.join(', ')}`;
    }
    if (res.provisions && res.provisions.length) {
      out += `${out ? '<br>' : ''}<b>${res.provisions.length} provisional</b>:<br>${res.provisions.join(', ')}`;
    }
    if (out) $(missingSel).removeClass("d-none").html(out);
    else $(missingSel).addClass("d-none").html('');
    if (res.provisions && res.provisions.length) {
      $(provisionSel).removeClass("d-none").html(`Provisional entries present for <b>${month}</b>.`);
    } else {
      $(provisionSel).addClass("d-none").html('');
    }
  };

  // View dropdown
  $("#printing_month_view").change(function(){
    resetForm();
    const month = $(this).val();
    if (month) {
      $("#printing_manual_form").addClass("d-none");
      $("#printing_report_section").addClass("d-none").html('');
      $("#printing_missing_view_branches").removeClass("d-none").html('Loading...');
      $.post("printing-monthly-fetch.php", {month}, function(res){
        $("#printing_report_section").removeClass("d-none").html(res.table || '');
        $("#printing_csv_download_container").removeClass("d-none");
        updateAlerts(res, month, "#printing_missing_view_branches", "#printing_provision_info");
      }, 'json');
    } else {
      $("#printing_report_section").addClass("d-none").html('');
      $("#printing_missing_view_branches").addClass("d-none").html('');
      $("#printing_csv_download_container").addClass("d-none");
    }
  });

  // Manual dropdown
  $("#printing_month_manual").change(function(){
    resetForm();
    const month = $(this).val();
    if (month) {
      $("#printing_manual_form").removeClass("d-none");
      $("#printing_missing_manual_branches").removeClass("d-none").html('Loading...');
      $.post("printing-monthly-fetch.php", {month}, function(res){
        updateAlerts(res, month, "#printing_missing_manual_branches", "#printing_provision_info");
      }, 'json');
    } else {
      $("#printing_manual_form").addClass("d-none");
      $("#printing_missing_manual_branches").addClass("d-none").html('');
      $("#printing_provision_info").addClass("d-none").html('');
    }
  });

  // Branch code blur
  $(document).on('blur', '.printing_branch_code', function(){
    const row = $(this).closest('tr');
    const branch_code = $(this).val();
    const month = $("#printing_month_manual").val();
    
    if (!branch_code || !month) return;

    $.post("ajax-get-existing-printing.php", {branch_code, month}, function(res){
      if (res.exists) {
        row.find('.printing_branch_name').val(res.branch).prop('readonly', true);
        row.find('.printing_amount').val(res.total_amount || '').prop('readonly', res.is_provision==='no');
        row.find('.printing_provision').val(res.is_provision || 'no').prop('disabled', res.is_provision==='no');
        row.find('.printing_provision_reason').val(res.provision_reason || '').prop('readonly', res.is_provision==='no');
        $("#printing_status_msg").html(`<div class='alert alert-${res.is_provision==='yes'?'warning':'danger'}'>${res.is_provision==='yes'?'Provision entry — you can finalize.':'Locked finalized entry.'}</div>`);
      } else {
        $.post("ajax-get-printing-branch.php", {branch_code}, function(b){
          if (b.status === 'success') row.find('.printing_branch_name').val(b.data.branch_name).prop('readonly', true);
          else row.find('.printing_branch_name').val('Not Found').prop('readonly', true);
        }, 'json');
        row.find('.printing_amount').val('').prop('readonly', false);
        row.find('.printing_provision').val('no').prop('disabled', false);
        row.find('.printing_provision_reason').val('').prop('readonly', false);
        $("#printing_status_msg").html('');
      }
    }, 'json');
  });

  // Thousand separator
  $(document).on('input', '.printing_amount', function(){
    let v = $(this).val().replace(/,/g,'');
    if (!isNaN(v) && v !== '') {
      const parts = v.split('.');
      parts[0] = Number(parts[0] || 0).toLocaleString('en-US');
      $(this).val(parts.length > 1 ? parts[0]+'.'+parts[1].slice(0,2) : parts[0]);
    } else $(this).val('');
  });

  // Save
  $("#printing_save_entry").click(function(){
    const month = $("#printing_month_manual").val();
    const row = $("#printing_entry_rows tr").last();
    const branch_code = row.find('.printing_branch_code').val();
    const branch_name = row.find('.printing_branch_name').val();
    const amount = row.find('.printing_amount').val().replace(/,/g,'');
    const provision = row.find('.printing_provision').val();
    const provision_reason = row.find('.printing_provision_reason').val();

    if (!month || !branch_code || !branch_name || !amount) {
      $("#printing_status_msg").html(`<div class='alert alert-danger'>Fill required fields.</div>`);
      return;
    }
    if (isNaN(amount) || Number(amount)<=0) {
      $("#printing_status_msg").html(`<div class='alert alert-danger'>Amount must be > 0</div>`);
      return;
    }

    $.post("printing-monthly-save.php", {month, branch_code, branch_name, amount, provision, provision_reason}, function(res){
      if (res.status === 'success') {
        $("#printing_status_msg").html(`<div class='alert alert-success'>${res.message}</div>`);
        $.post("printing-monthly-fetch.php", {month}, function(r2){
          updateAlerts(r2, month, "#printing_missing_manual_branches", "#printing_provision_info");
        }, 'json');
        $("#printing_entry_rows").append(blankRow());
      } else {
        $("#printing_status_msg").html(`<div class='alert alert-danger'>${res.message}</div>`);
      }
    }, 'json');
  });

  // CSV Download
  $("#printing_download_csv_btn").click(function(){
    const table = $("#printing_report_section table");
    if (!table.length) return;
    let csv = [];
    table.find("tr").each(function(){
      let row = [];
      $(this).find("th,td").each(function(){
        let text=$(this).text().trim().replace(/"/g,'""');
        row.push(`"${text}"`);
      });
      csv.push(row.join(","));
    });
    const blob=new Blob([csv.join("\n")],{type:"text/csv;charset=utf-8;"});
    const link=document.createElement("a");
    const month=$("#printing_month_view").val()?$("#printing_month_view").val().replace(/\s+/g,'_'):'Month';
    link.setAttribute("href",URL.createObjectURL(blob));
    link.setAttribute("download",`Printing_Report_${month}.csv`);
    link.style.display="none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

});
