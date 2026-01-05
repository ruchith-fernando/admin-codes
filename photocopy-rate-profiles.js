// photocopy-rate-profiles.js
$(document).ready(function(){

  function showMsg(type, html){
    const cls = (type === 'success') ? 'alert-success' : (type === 'warn') ? 'alert-warning' : 'alert-danger';
    $("#pc_rate_msg").html(`<div class="alert ${cls}">${html}</div>`);
  }

  function clearMsg(){ $("#pc_rate_msg").html(""); }

  function resetForm(){
    $("#pc_rate_profile_id").val("");
    $("#pc_vendor_id").val("");
    $("#pc_model_match").val("");
    $("#pc_copy_rate").val("");
    $("#pc_sscl").val("");
    $("#pc_vat").val("");
    $("#pc_eff_from").val("");
    $("#pc_eff_to").val("");
    $("#pc_is_active").val("1");
    clearMsg();
  }

  function loadTable(){
    const vendor_id = $("#pc_vendor_filter").val();
    $.post("photocopy-rate-profiles-fetch.php", { vendor_id }, function(res){
      $("#pc_rate_profiles_table").html(res.table || "");
    }, "json").fail(function(x){
      $("#pc_rate_profiles_table").html(`<div class="alert alert-danger">Load failed: ${x.responseText || 'server error'}</div>`);
    });
  }

  // initial
  loadTable();

  $("#pc_vendor_filter").on("change", loadTable);

  $("#pc_rate_reset").on("click", function(){
    resetForm();
  });

  $("#pc_rate_save").on("click", function(){

    clearMsg();

    const rate_profile_id = ($("#pc_rate_profile_id").val() || "").trim();
    const vendor_id = ($("#pc_vendor_id").val() || "").trim();
    const model_match = ($("#pc_model_match").val() || "").trim();
    const copy_rate = ($("#pc_copy_rate").val() || "").trim();
    const sscl = ($("#pc_sscl").val() || "0").trim();
    const vat  = ($("#pc_vat").val() || "0").trim();
    const effective_from = ($("#pc_eff_from").val() || "").trim();
    const effective_to   = ($("#pc_eff_to").val() || "").trim();
    const is_active = ($("#pc_is_active").val() || "1").trim();

    if (!vendor_id){
      showMsg("error","Please select a vendor.");
      return;
    }
    if (copy_rate === "" || isNaN(copy_rate) || parseFloat(copy_rate) < 0){
      showMsg("error","Please enter a valid copy rate.");
      return;
    }
    if (effective_from && effective_to && effective_to < effective_from){
      showMsg("error","Effective To cannot be earlier than Effective From.");
      return;
    }

    $.post("photocopy-rate-profiles-save.php", {
      rate_profile_id,
      vendor_id,
      model_match,
      copy_rate,
      sscl_percentage: sscl,
      vat_percentage: vat,
      effective_from,
      effective_to,
      is_active
    }, function(res){
      if(res && res.success){
        showMsg("success", res.message || "Saved.");
        resetForm();
        loadTable();
      } else {
        showMsg("error", (res && res.message) ? res.message : "Save failed.");
      }
    }, "json").fail(function(x){
      showMsg("error", x.responseText || "Server error.");
    });
  });

  // Edit button from table
  $(document).on("click", ".pc_rate_edit", function(){
    clearMsg();
    const data = $(this).data();

    $("#pc_rate_profile_id").val(data.rate_profile_id || "");
    $("#pc_vendor_id").val(data.vendor_id || "");
    $("#pc_model_match").val(data.model_match || "");
    $("#pc_copy_rate").val(data.copy_rate || "");
    $("#pc_sscl").val(data.sscl_percentage || "");
    $("#pc_vat").val(data.vat_percentage || "");
    $("#pc_eff_from").val(data.effective_from || "");
    $("#pc_eff_to").val(data.effective_to || "");
    $("#pc_is_active").val(String(data.is_active || "1"));
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  // Deactivate button
  $(document).on("click", ".pc_rate_deactivate", function(){
    const id = $(this).data("rate_profile_id");
    if(!id) return;

    if(!confirm("Deactivate this rate profile?")) return;

    $.post("photocopy-rate-profiles-deactivate.php", { rate_profile_id: id }, function(res){
      if(res && res.success){
        showMsg("success", res.message || "Deactivated.");
        loadTable();
      } else {
        showMsg("error", (res && res.message) ? res.message : "Failed.");
      }
    }, "json").fail(function(x){
      showMsg("error", x.responseText || "Server error.");
    });
  });

});
