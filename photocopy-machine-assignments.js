$(document).ready(function(){
// Branch Select2 (search by name/code)
  $("#pc_branch_select").select2({
    placeholder: "Type branch name or code…",
    allowClear: true,
    ajax: {
      url: "ajax-search-branches.php",
      dataType: "json",
      delay: 250,
      data: function(params){ return { q: params.term || "" }; },
      processResults: function(data){ return data; },
      cache: true
    }
  });

  // When user selects a branch
  $("#pc_branch_select").on("select2:select", function(e){
    const item = e.params.data;
    $("#pc_branch_code").val(item.branch_code || item.id || "");
    $("#pc_branch_name").text(item.branch_name || "");
    $("#pc_confirm").prop("checked", false);
    updateSaveEnabled();
  });

  // When cleared
  $("#pc_branch_select").on("select2:clear", function(){
    $("#pc_branch_code").val("");
    $("#pc_branch_name").text("");
    $("#pc_confirm").prop("checked", false);
    updateSaveEnabled();
  });

  function showMsg(type, html){
    const cls = type === 'ok' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger');
    $("#pc_msg").html(`<div class="alert ${cls}">${html}</div>`);
  }

  function clearMsg(){ $("#pc_msg").html(""); }

  function resetForm(){
    $("#pc_branch_select").val(null).trigger("change");

    $("#pc_serial").val("");
    $("#pc_machine_id").val("");
    $("#pc_model").val("");
    $("#pc_machine_hint").text("");
    $("#pc_branch_code").val("");
    $("#pc_branch_name").text("");
    $("#pc_vendor_id").val("");
    $("#pc_vendor_hint").text("");
    $("#pc_remarks").val("");
    $("#pc_confirm").prop("checked", false);
    $("#pc_current_badge").hide().text("");
    updateSaveEnabled();
    clearMsg();
  }

  function updateSaveEnabled(){
    const ok = $("#pc_confirm").is(":checked")
      && ($("#pc_machine_id").val() || "").trim() !== ""
      && ($("#pc_branch_code").val() || "").trim() !== ""
      && ($("#pc_installed_at").val() || "").trim() !== "";
    $("#pc_save_btn").prop("disabled", !ok);
  }

  function loadCurrentTable(){
    $("#pc_current_table").html("<div class='muted'>Loading…</div>");
    $.get("photocopy-assignment-list.php", function(res){
      $("#pc_current_table").html(res);
    }).fail(function(x){
      $("#pc_current_table").html(`<div class="alert alert-danger">Load failed: ${x.responseText || 'error'}</div>`);
    });
  }

  // Branch lookup
  // $(document).on("blur", "#pc_branch_code", function(){
  //   const branch_code = ($("#pc_branch_code").val() || "").trim();
  //   $("#pc_branch_name").text("");
  //   if(!branch_code) { updateSaveEnabled(); return; }

  //   $.post("ajax-get-branch-photocopy.php", { branch_code }, function(res){
  //     if(res && res.success){
  //       $("#pc_branch_name").text(res.branch_name);
  //     } else {
  //       $("#pc_branch_name").text("Not found");
  //       showMsg("err", res && res.message ? res.message : "Branch not found in master.");
  //     }
  //     updateSaveEnabled();
  //   }, "json").fail(function(x){
  //     showMsg("err", x.responseText || "Branch lookup failed.");
  //     updateSaveEnabled();
  //   });
  // });

  // Machine lookup by serial
  $(document).on("blur", "#pc_serial", function(){
    const serial_no = ($("#pc_serial").val() || "").trim();
    $("#pc_machine_id").val("");
    $("#pc_model").val("");
    $("#pc_machine_hint").text("");
    $("#pc_vendor_hint").text("");
    $("#pc_current_badge").hide().text("");

    if(!serial_no){ updateSaveEnabled(); return; }

    $.post("ajax-get-photocopy-machine.php", { serial_no }, function(res){
      if(res && res.success){
        $("#pc_machine_id").val(res.machine_id);
        $("#pc_model").val(res.model_name || "");
        $("#pc_machine_hint").text(`Machine ID: ${res.machine_id}`);

        if(res.machine_vendor_name){
          $("#pc_vendor_hint").text(`Machine Vendor: ${res.machine_vendor_name} (ID ${res.machine_vendor_id})`);
        }

        if(res.current_assignment && res.current_assignment.branch_code){
          $("#pc_current_badge").show().text(
            `Currently at ${res.current_assignment.branch_name || res.current_assignment.branch_code} (since ${res.current_assignment.installed_at})`
          );
        }
        clearMsg();
      } else {
        showMsg("err", (res && res.message) ? res.message : "Machine not found.");
      }
      updateSaveEnabled();
    }, "json").fail(function(x){
      showMsg("err", x.responseText || "Machine lookup failed.");
      updateSaveEnabled();
    });
  });

  // Confirm toggle
  $(document).on("change", "#pc_confirm", updateSaveEnabled);
  $(document).on("input change", "#pc_branch_code, #pc_installed_at, #pc_vendor_id, #pc_remarks", function(){
    $("#pc_confirm").prop("checked", false);
    updateSaveEnabled();
  });

  // Save / Move
  $("#pc_save_btn").on("click", function(){
    const payload = {
      machine_id: ($("#pc_machine_id").val() || "").trim(),
      branch_code: ($("#pc_branch_code").val() || "").trim(),
      vendor_id: ($("#pc_vendor_id").val() || "").trim(),
      installed_at: ($("#pc_installed_at").val() || "").trim(),
      remarks: ($("#pc_remarks").val() || "").trim()
    };

    $("#pc_save_btn").prop("disabled", true);

    $.post("photocopy-assignment-save.php", payload, function(res){
      if(res && res.success){
        showMsg("ok", res.message || "Saved.");
        loadCurrentTable();
        $("#pc_confirm").prop("checked", false);
      } else {
        showMsg("err", res && res.message ? res.message : "Save failed.");
      }
      updateSaveEnabled();
    }, "json").fail(function(x){
      showMsg("err", x.responseText || "Save failed.");
      updateSaveEnabled();
    });
  });

  // Remove assignment
  $(document).on("click", ".pc_remove_btn", function(){
    const assign_id = $(this).data("id");
    if(!assign_id) return;

    const removed_at = prompt("Enter removed date (YYYY-MM-DD). Leave blank for today:", "");
    const dateVal = (removed_at || "").trim() || new Date().toISOString().slice(0,10);

    const remarks = prompt("Remarks (optional):", "") || "";

    $.post("photocopy-assignment-remove.php", { assign_id, removed_at: dateVal, remarks }, function(res){
      if(res && res.success){
        showMsg("ok", res.message || "Removed.");
        loadCurrentTable();
      } else {
        showMsg("err", res && res.message ? res.message : "Remove failed.");
      }
    }, "json").fail(function(x){
      showMsg("err", x.responseText || "Remove failed.");
    });
  });

  $("#pc_reset_btn").on("click", resetForm);

  // init
  loadCurrentTable();
  updateSaveEnabled();
});
