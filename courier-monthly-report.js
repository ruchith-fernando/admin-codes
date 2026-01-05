$(document).ready(function(){
// courier-monthly-report.js
  // ---------------------- Utility: blank row ----------------------
  const blankCourierRow = () => `
    <tr>
      <td><input type="text" class="form-control courier_branch_code" maxlength="10" /></td>
      <td><input type="text" class="form-control courier_branch_name" readonly /></td>
      <td><input type="text" class="form-control courier_amount" /></td>
      <td>
        <select class="form-select courier_provision">
          <option value="no" selected>No</option>
          <option value="yes">Yes</option>
        </select>
      </td>
      <td><input type="text" class="form-control courier_provision_reason" placeholder="Optional" /></td>
    </tr>
  `;

  const resetCourierForm = () => {
    $("#courier_entry_rows").html(blankCourierRow());
    $("#courier_status_msg").html('');
    $("#courier_provision_info").addClass("d-none").html('');
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

  // ---------------------- VIEW REPORT ----------------------
  $("#courier_month_view").change(function(){
    resetCourierForm();
    const month = $(this).val();
    if (month) {
      $("#courier_manual_form").addClass("d-none");
      $("#courier_report_section").addClass("d-none").html('');
      $("#courier_missing_view_branches").removeClass("d-none").html('Loading...');
      $.post("courier-monthly-fetch.php", {month}, function(res){
        $("#courier_report_section").removeClass("d-none").html(res.table || '');
        $("#courier_csv_download_container").removeClass("d-none");
        updateAlerts(res, month, "#courier_missing_view_branches", "#courier_provision_info");
      }, 'json');
    } else {
      $("#courier_report_section").addClass("d-none").html('');
      $("#courier_missing_view_branches").addClass("d-none").html('');
      $("#courier_csv_download_container").addClass("d-none");
    }
  });

  // ---------------------- MANUAL ENTRY ----------------------
  $("#courier_month_manual").change(function(){
    resetCourierForm();
    const month = $(this).val();
    if (month) {
      $("#courier_manual_form").removeClass("d-none");
      $("#courier_missing_manual_branches").removeClass("d-none").html('Loading...');
      $.post("courier-monthly-fetch.php", {month}, function(res){
        updateAlerts(res, month, "#courier_missing_manual_branches", "#courier_provision_info");
      }, 'json');
    } else {
      $("#courier_manual_form").addClass("d-none");
      $("#courier_missing_manual_branches").addClass("d-none").html('');
      $("#courier_provision_info").addClass("d-none").html('');
    }
  });

  // ---------------------- BRANCH CODE BLUR ----------------------
  $(document).on('blur', '.courier_branch_code', function(){
    const row = $(this).closest('tr');
    const branch_code = $(this).val().trim();
    const month = $("#courier_month_manual").val();

    if (!branch_code || !month) return;

    console.log("🔍 Checking existing courier entry:", {branch_code, month});

    $.post("ajax-get-existing-courier.php", {branch_code, month}, function(res){
      console.log("ajax-get-existing-courier.php response:", res);

      // Normalize exists flag to boolean
      const exists = (res.exists === true || res.exists === "true");

      if (exists) {
        // Existing record found
        row.find('.courier_branch_name').val(res.branch).prop('readonly', true);
        row.find('.courier_amount').val(res.total_amount || '').prop('readonly', res.is_provision === 'no');
        row.find('.courier_provision').val(res.is_provision || 'no').prop('disabled', res.is_provision === 'no');
        row.find('.courier_provision_reason').val(res.provision_reason || '').prop('readonly', res.is_provision === 'no');
        $("#courier_status_msg").html(
          `<div class='alert alert-${res.is_provision === 'yes' ? 'warning' : 'danger'}'>
            ${res.is_provision === 'yes' ? 'Provision entry — you can finalize.' : 'Locked finalized entry.'}
          </div>`
        );
      } else {
        // No existing record → fetch branch details
        console.log("Fetching branch details from ajax-get-courier-branch.php...");
        $.post("ajax-get-courier-branch.php", {branch_code}, function(b){
          console.log("ajax-get-courier-branch.php response:", b);

          // Handle both possible PHP formats
          if (b.success && b.branch_name) {
            row.find('.courier_branch_name').val(b.branch_name).prop('readonly', true);
          } else if (b.status === 'success' && b.data && b.data.branch_name) {
            row.find('.courier_branch_name').val(b.data.branch_name).prop('readonly', true);
          } else {
            row.find('.courier_branch_name').val('Not Found').prop('readonly', true);
          }
        }, 'json');

        // Reset input fields for new entry
        row.find('.courier_amount').val('').prop('readonly', false);
        row.find('.courier_provision').val('no').prop('disabled', false);
        row.find('.courier_provision_reason').val('').prop('readonly', false);
        $("#courier_status_msg").html('');
      }
    }, 'json');
  });

  // ---------------------- THOUSAND SEPARATOR ----------------------
  $(document).on('input', '.courier_amount', function(){
    let v = $(this).val().replace(/,/g,'');
    if (!isNaN(v) && v !== '') {
      const parts = v.split('.');
      parts[0] = Number(parts[0] || 0).toLocaleString('en-US');
      $(this).val(parts.length > 1 ? parts[0]+'.'+parts[1].slice(0,2) : parts[0]);
    } else $(this).val('');
  });

  // ---------------------- SAVE ENTRY ----------------------
  $("#courier_save_entry").click(function(){
    const month = $("#courier_month_manual").val();
    const row = $("#courier_entry_rows tr").last();
    const branch_code = row.find('.courier_branch_code').val();
    const branch_name = row.find('.courier_branch_name').val();
    const amount = row.find('.courier_amount').val().replace(/,/g,'');
    const provision = row.find('.courier_provision').val();
    const provision_reason = row.find('.courier_provision_reason').val();

    if (!month || !branch_code || !branch_name || !amount) {
      $("#courier_status_msg").html(`<div class='alert alert-danger'>Fill required fields.</div>`);
      return;
    }
    if (isNaN(amount) || Number(amount) <= 0) {
      $("#courier_status_msg").html(`<div class='alert alert-danger'>Amount must be > 0</div>`);
      return;
    }

    $.post("courier-monthly-save.php", {month, branch_code, branch_name, amount, provision, provision_reason}, function(res){
      if (res.success) {
        $("#courier_status_msg").html(`
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            ${res.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `);
        $.post("courier-monthly-fetch.php", {month}, function(r2){
          updateAlerts(r2, month, "#courier_missing_manual_branches", "#courier_provision_info");
        }, 'json');

        // ✅ Clear previous row and add new one
        $("#courier_entry_rows").html(blankCourierRow());

        // Auto-hide alert after 3 seconds
        setTimeout(() => $(".alert").alert('close'), 3000);
      } else {
        $("#courier_status_msg").html(`
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ${res.message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `);
      }
    }, 'json');


  });

  // ---------------------- CSV DOWNLOAD ----------------------
  $("#courier_download_csv_btn").click(function(){
    const table = $("#courier_report_section table");
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
    const month=$("#courier_month_view").val()?$("#courier_month_view").val().replace(/\s+/g,'_'):'Month';
    link.setAttribute("href",URL.createObjectURL(blob));
    link.setAttribute("download",`Courier_Report_${month}.csv`);
    link.style.display="none";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

});
