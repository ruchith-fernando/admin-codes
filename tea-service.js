$(document).ready(function () {

  let previewToken = null;

  function escHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    })[m]);
  }

  function formatMoney(val){
    const n = Number(val);
    if (isNaN(n)) return "0.00";
    return n.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function showAlert(type, msg){
    const cls = type === "success" ? "alert-success"
              : type === "warning" ? "alert-warning"
              : "alert-danger";
    $("#tea_status_msg").html(`<div class="alert ${cls}">${msg}</div>`);
  }

  function clearPreview() {
    previewToken = null;
    $("#tea_preview_area").html("");
    $("#tea_confirm_checked").prop("checked", false).prop("disabled", true);
    $("#tea_btn_save").prop("disabled", true);
  }

  /**
   * ✅ OT floor = floor_no 8 (Over Time). DB shows OT is id=12, floor_no=8.
   * When OT floor selected:
   *   - show ONLY OT box
   *   - hide items table completely
   */
  function toggleOtInput(){
    const floorId = parseInt(("" + ($("#tea_floor_id").val() || "0")).trim(), 10) || 0;
    const floorNo = parseInt(("" + ($("#tea_floor_id option:selected").data("floor-no") || "0")).trim(), 10) || 0;

    // Main rule: floor_no=8. Fallback: id=12.
    const isOTFloor = (floorNo === 8) || (floorId === 12);

    const entryEnabled = !$("#tea_entry_section").hasClass("d-none");

    if(isOTFloor){
      $("#tea_ot_box").removeClass("d-none");
      $("#tea_ot_amount").prop("disabled", !entryEnabled);

      $("#tea_items_box").addClass("d-none");
      $(".tea_units").val("0").prop("disabled", true);
    } else {
      $("#tea_ot_box").addClass("d-none");
      $("#tea_ot_amount").val("0").prop("disabled", true);

      $("#tea_items_box").removeClass("d-none");
      $(".tea_units").prop("disabled", !entryEnabled);
    }

    clearPreview();
  }

  function loadMonthSummary(){
    const month_year = ($("#tea_month").val() || "").trim();
    $("#tea_month_summary_box").addClass("d-none").html("");
    if(!month_year) return;

    $("#tea_month_summary_box").removeClass("d-none").html(`
      <div class="alert alert-light border mb-0">
        <div class="d-flex align-items-center gap-2">
          <div class="spinner-border spinner-border-sm"></div>
          <div>Loading invoice summary for <b>${escHtml(month_year)}</b>...</div>
        </div>
      </div>
    `);

    $.post("tea-month-summary.php", { month_year }, function(res){
      if(!res || !res.success){
        $("#tea_month_summary_box").removeClass("d-none").html(
          `<div class="alert alert-danger mb-0">Failed to load invoice summary.</div>`
        );
        return;
      }

      if(!res.exists){
        $("#tea_month_summary_box").removeClass("d-none").html(
          `<div class="alert alert-secondary mb-0">No invoices found for <b>${escHtml(month_year)}</b>.</div>`
        );
        return;
      }

      $("#tea_month_summary_box").removeClass("d-none").html(res.html || "");
    }, "json");
  }

  function resetEntryInputs(){
    $("#tea_sr_number").val("");
    $("#tea_ot_amount").val("0");
    $(".tea_units").val("0");
    clearPreview();
  }

  function setEntryEnabled(enabled){
    $("#tea_entry_section").toggleClass("d-none", !enabled);
    $("#tea_entry_section").find("input, select, textarea, button").prop("disabled", !enabled);

    if(enabled){
      $("#tea_btn_preview").prop("disabled", false);
      clearPreview();
    } else {
      clearPreview();
    }

    // keep OT/items layout correct
    toggleOtInput();
  }

  function getPayloadForCalc(){
    const month_year = ($("#tea_month").val() || "").trim();
    const floor_id   = ($("#tea_floor_id").val() || "").trim();
    const sr_number  = ($("#tea_sr_number").val() || "").trim();
    const ot_amount  = ($("#tea_ot_amount").val() || "0").trim();

    const units = {};
    $(".tea_units").each(function(){
      const id = $(this).data("item-id");
      let v = $(this).val();

      if (String(v).includes("-")) v = String(v).replace(/-/g,'');
      v = v === "" ? "0" : v;

      units[id] = v;
    });

    return { month_year, floor_id, sr_number, ot_amount, units };
  }

  function loadExisting(){
    toggleOtInput();
    clearPreview();
    $("#tea_status_msg").html("");
    $("#tea_existing_box").addClass("d-none").html("");

    const month_year = ($("#tea_month").val() || "").trim();
    const floor_id   = ($("#tea_floor_id").val() || "").trim();

    if(!month_year || !floor_id){
      setEntryEnabled(true);
      return;
    }

    resetEntryInputs();

    $.post("tea-service-existing.php", { month_year, floor_id }, function(res){

      if(!res || !res.success){
        setEntryEnabled(true);
        return;
      }

      if(!res.exists){
        setEntryEnabled(true);
        return;
      }

      $("#tea_existing_box").removeClass("d-none").html(res.html || "");

      const status = (res.status || "").toLowerCase();

      if(status === "pending" || status === "approved"){
        setEntryEnabled(false);
        showAlert("warning", "This month + floor already has a " + status.toUpperCase() + " record. Entry is locked. Select another Month/Floor to enter.");
      } else {
        setEntryEnabled(true);
        showAlert("warning", "This record was REJECTED. You can correct and re-submit.");
      }

    }, "json");
  }

  $("#tea_month").on("change", function(){
    loadMonthSummary();
    loadExisting();
  });

  $("#tea_floor_id").on("change", function(){
    toggleOtInput();
    loadExisting();
  });

  $(document).on("input change", "#tea_sr_number, #tea_ot_amount, .tea_units", function(){
    clearPreview();
  });

  $(document).on("input", "input[type='number']", function(){
    let v = $(this).val();
    if (String(v).includes("-")) v = String(v).replace(/-/g,'');
    if (parseFloat(v) < 0) v = "";
    $(this).val(v);
  });

  $("#tea_btn_preview").on("click", function(){

    const p = getPayloadForCalc();
    if(!p.month_year || !p.floor_id){
      return showAlert("error", "Please select Month and Floor.");
    }

    $("#tea_preview_area").html(`
      <div class="text-center">
        <div class="spinner-border"></div>
        <div>Calculating...</div>
      </div>
    `);
    $("#tea_status_msg").html("");

    $.post("tea-service-calc.php", p, function(res){

      if(!res || !res.success){
        $("#tea_preview_area").html("");
        clearPreview();
        return showAlert("error", res && res.message ? res.message : "Preview failed.");
      }

      previewToken = res.preview_token;
      $("#tea_confirm_checked").prop("disabled", false);

      let html = `<h6 class="text-primary">Preview</h6>`;

      if((res.lines || []).length > 0){
        html += `
          <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Item</th>
                <th>Units</th>
                <th class="text-end">Rate</th>
                <th class="text-end">Total</th>
                <th class="text-end">SSCL</th>
                <th class="text-end">VAT</th>
                <th class="text-end">Grand</th>
              </tr>
            </thead>
            <tbody>
        `;

        (res.lines || []).forEach(l=>{
          html += `
            <tr>
              <td>${escHtml(l.item_name)}</td>
              <td>${escHtml(l.units)}</td>
              <td class="text-end">${formatMoney(l.unit_price)}</td>
              <td class="text-end">${formatMoney(l.total_price)}</td>
              <td class="text-end">${formatMoney(l.sscl_amount)}</td>
              <td class="text-end">${formatMoney(l.vat_amount)}</td>
              <td class="text-end">${formatMoney(l.line_grand_total)}</td>
            </tr>
          `;
        });

        html += `</tbody></table></div>`;
      } else {
        html += `<div class="alert alert-secondary">Over Time entry (no items).</div>`;
      }

      html += `
        <div class="row mt-2">
          <div class="col-md-3"><b>Items Total:</b> ${formatMoney(res.summary.total_price)}</div>
          <div class="col-md-3"><b>SSCL:</b> ${formatMoney(res.summary.sscl_amount)}</div>
          <div class="col-md-3"><b>VAT:</b> ${formatMoney(res.summary.vat_amount)}</div>
          <div class="col-md-3"><b>OT:</b> ${formatMoney(res.summary.ot_amount)}</div>
        </div>
        <div class="mt-2"><b>Grand Total:</b> ${formatMoney(res.summary.grand_total)}</div>
      `;

      $("#tea_preview_area").html(html);

    }, "json");
  });

  $("#tea_confirm_checked").on("change", function(){
    const ok = $(this).is(":checked");
    $("#tea_btn_save").prop("disabled", !(ok && previewToken));
  });

  $("#tea_btn_save").on("click", function(){
    if(!previewToken) return showAlert("error", "Please preview first.");

    if(!$("#tea_confirm_checked").is(":checked")){
      return showAlert("error", "Please confirm before saving.");
    }

    $("#tea_btn_save").prop("disabled", true);

    $.post("tea-service-save.php", { preview_token: previewToken }, function(res){

      if(!res || !res.success){
        $("#tea_btn_save").prop("disabled", false);
        return showAlert("error", res && res.message ? res.message : "Save failed.");
      }

      showAlert("success", res.message || "Saved successfully as PENDING.");
      clearPreview();

      loadExisting();
      loadMonthSummary();

    }, "json");
  });

  setEntryEnabled(true);
  toggleOtInput();
  loadMonthSummary();
});
