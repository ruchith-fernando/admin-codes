$(document).ready(function(){
// newspaper-monthly-report.js
  const blankNewsPaperRow = () => `
    <tr>
      <td><input type="text" class="form-control newspaper_branch_code" maxlength="10" /></td>
      <td><input type="text" class="form-control newspaper_branch_name" readonly /></td>
      <td><input type="text" class="form-control newspaper_amount" /></td>
      <td>
        <select class="form-select newspaper_provision">
          <option value="no" selected>No</option>
          <option value="yes">Yes</option>
        </select>
      </td>
      <td><input type="text" class="form-control newspaper_provision_reason" placeholder="Optional" /></td>
    </tr>
  `;

  const resetNewsPaperForm = () => {
    $("#newspaper_entry_rows").html(blankNewsPaperRow());
    $("#newspaper_status_msg").html('');
    $("#newspaper_provision_info").addClass("d-none").html('');
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
  $("#newspaper_month_view").change(function(){
    resetNewsPaperForm();
    const month = $(this).val();
    if (month) {
      $("#newspaper_manual_form").addClass("d-none");
      $("#newspaper_report_section").addClass("d-none").html('');
      $("#newspaper_missing_view_branches").removeClass("d-none").html('Loading...');
      $.post("newspaper-monthly-fetch.php", {month}, function(res){
        $("#newspaper_report_section").removeClass("d-none").html(res.table || '');
        $("#newspaper_csv_download_container").removeClass("d-none");
        updateAlerts(res, month, "#newspaper_missing_view_branches", "#newspaper_provision_info");
      }, 'json');
    } else {
      $("#newspaper_report_section").addClass("d-none").html('');
      $("#newspaper_missing_view_branches").addClass("d-none").html('');
      $("#newspaper_csv_download_container").addClass("d-none");
    }
  });

  // Manual dropdown
  $("#newspaper_month_manual").change(function(){
    resetNewsPaperForm();
    const month = $(this).val();
    if (month) {
      $("#newspaper_manual_form").removeClass("d-none");
      $("#newspaper_missing_manual_branches").removeClass("d-none").html('Loading...');
      $.post("newspaper-monthly-fetch.php", {month}, function(res){
        updateAlerts(res, month, "#newspaper_missing_manual_branches", "#newspaper_provision_info");
      }, 'json');
    } else {
      $("#newspaper_manual_form").addClass("d-none");
      $("#newspaper_missing_manual_branches").addClass("d-none").html('');
      $("#newspaper_provision_info").addClass("d-none").html('');
    }
  });

  // Branch code blur
  $(document).on('blur', '.newspaper_branch_code', function(){
    const row = $(this).closest('tr');
    const branch_code = $(this).val();
    const month = $("#newspaper_month_manual").val();
    if (!branch_code || !month) return;

    $.post("ajax-get-existing-newspaper.php", {branch_code, month}, function(res){
      if (res.exists) {
        row.find('.newspaper_branch_name').val(res.branch).prop('readonly', true);
        row.find('.newspaper_amount').val(res.total_amount || '').prop('readonly', res.is_provision==='no');
        row.find('.newspaper_provision').val(res.is_provision || 'no').prop('disabled', res.is_provision==='no');
        row.find('.newspaper_provision_reason').val(res.provision_reason || '').prop('readonly', res.is_provision==='no');
        $("#newspaper_status_msg").html(`<div class='alert alert-${res.is_provision==='yes'?'warning':'danger'}'>${res.is_provision==='yes'?'Provision entry — you can finalize.':'Locked finalized entry.'}</div>`);
      } else {
        $.post("ajax-get-newspaper-branch.php", {branch_code}, function(b){
          if (b.success) row.find('.newspaper_branch_name').val(b.branch_name).prop('readonly', true);
          else row.find('.newspaper_branch_name').val('Not Found').prop('readonly', true);
        }, 'json');
        row.find('.newspaper_amount').val('').prop('readonly', false);
        row.find('.newspaper_provision').val('no').prop('disabled', false);
        row.find('.newspaper_provision_reason').val('').prop('readonly', false);
        $("#newspaper_status_msg").html('');
      }
    }, 'json');
  });

  // Live thousand separator
  $(document).on('input', '.newspaper_amount', function(){
    let v = $(this).val().replace(/,/g,'');
    if (!isNaN(v) && v !== '') {
      const parts = v.split('.');
      parts[0] = Number(parts[0] || 0).toLocaleString('en-US');
      $(this).val(parts.length > 1 ? parts[0]+'.'+parts[1].slice(0,2) : parts[0]);
    } else $(this).val('');
  });

  // Save
  $("#newspaper_save_entry").click(function(){
    const month = $("#newspaper_month_manual").val();
    const row = $("#newspaper_entry_rows tr").last();
    const branch_code = row.find('.newspaper_branch_code').val();
    const branch_name = row.find('.newspaper_branch_name').val();
    const amount = row.find('.newspaper_amount').val().replace(/,/g,'');
    const provision = row.find('.newspaper_provision').val();
    const provision_reason = row.find('.newspaper_provision_reason').val();

    if (!month || !branch_code || !branch_name || !amount) {
      $("#newspaper_status_msg").html(`<div class='alert alert-danger'>Fill required fields.</div>`);
      return;
    }
    if (isNaN(amount) || Number(amount)<=0) {
      $("#newspaper_status_msg").html(`<div class='alert alert-danger'>Amount must be > 0</div>`);
      return;
    }

    $.post("newspaper-monthly-save.php", {month, branch_code, branch_name, amount, provision, provision_reason}, function(res){
      if (res.success) {
        $("#newspaper_status_msg").html(`<div class='alert alert-success'>${res.message}</div>`);
        $.post("newspaper-monthly-fetch.php", {month}, function(r2){
          updateAlerts(r2, month, "#newspaper_missing_manual_branches", "#newspaper_provision_info");
        }, 'json');
        $("#newspaper_entry_rows").append(blankNewsPaperRow());
      } else {
        $("#newspaper_status_msg").html(`<div class='alert alert-danger'>${res.message}</div>`);
      }
    }, 'json');
  });

  // CSV Download
  $("#newspaper_download_csv_btn").click(function(){
    const table = $("#newspaper_report_section table");
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
    const month=$("#newspaper_month_view").val()?$("#newspaper_month_view").val().replace(/\s+/g,'_'):'Month';
    link.setAttribute("href",URL.createObjectURL(blob));
    link.setAttribute("download",`newspaper_Report_${month}.csv`);
    link.style.display="none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

});
